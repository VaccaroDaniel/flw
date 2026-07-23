<?php
// Native Moodle competency rating writer for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Writes C-UP-KP competency mastery states into Moodle's native competency records.
 */
final class moodle_competency_writer {
    /** @var array C-UP-KP competency states that mean Moodle proficient. */
    private const PROFICIENT_STATES = ['achieved', 'sustained', 'mastered'];

    /** @var array C-UP-KP states that mean Moodle not yet proficient when evidence exists. */
    private const NOT_PROFICIENT_STATES = [
        'not_started',
        'developing',
        'provisionally_achieved',
        'not_observed',
        'emerging',
        'not_introduced',
        'introduced',
        'practiced',
        'controlled_use',
        'independent_use',
        'review_due',
    ];

    /**
     * Sync all linked C-UP-KP competency states to native Moodle competencies.
     *
     * @param bool $dryrun
     * @param int $limit
     * @return array
     */
    public static function sync_all(bool $dryrun = true, int $limit = 0): array {
        global $DB;

        $readiness = curriculum_manager::sync_readiness();
        $writeenabled = (bool)get_config('local_flwcupkp', 'enablesyncwrites');
        $effectivewrite = !$dryrun && $writeenabled && !empty($readiness['readyforwrites']);
        if (!$dryrun && !$effectivewrite) {
            throw new \invalid_parameter_exception('Moodle competency sync writes are not ready.');
        }

        $records = $DB->get_records_sql(
            "SELECT s.*, c.externalid AS competencyexternalid, c.title AS competencytitle,
                    c.moodlecompetencyid, c.frameworkid
               FROM {flwcupkp_state} s
               JOIN {flwcupkp_comp} c ON c.id = s.targetid
              WHERE s.targettype = :targettype
                AND c.moodlecompetencyid IS NOT NULL
                AND c.moodlecompetencyid > 0
           ORDER BY s.timemodified ASC, s.id ASC",
            ['targettype' => 'competency'],
            0,
            $limit > 0 ? $limit : 0
        );

        $summary = [
            'dryrun' => $dryrun,
            'writeenabled' => $writeenabled,
            'readyforwrites' => !empty($readiness['readyforwrites']),
            'scanned' => 0,
            'written' => 0,
            'wouldwrite' => 0,
            'skipped' => 0,
            'errors' => 0,
            'coursewrites' => 0,
            'globalwrites' => 0,
            'details' => [],
        ];

        foreach ($records as $state) {
            $summary['scanned']++;
            try {
                $result = self::sync_state_record($state, $dryrun);
                $summary['details'][] = $result;
                if ($result['status'] === 'written') {
                    $summary['written']++;
                    if ($result['scope'] === 'course') {
                        $summary['coursewrites']++;
                    } else if ($result['scope'] === 'global') {
                        $summary['globalwrites']++;
                    }
                } else if ($result['status'] === 'would_write') {
                    $summary['wouldwrite']++;
                } else {
                    $summary['skipped']++;
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['details'][] = [
                    'status' => 'error',
                    'userid' => (int)($state->userid ?? 0),
                    'targetid' => (int)($state->targetid ?? 0),
                    'message' => $e->getMessage(),
                ];
                repository::audit('moodle_competency_rating_sync_failed', 'competency', (int)($state->targetid ?? 0), [
                    'userid' => (int)($state->userid ?? 0),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Sync one C-UP-KP competency state row.
     *
     * @param \stdClass $state
     * @param bool $dryrun
     * @return array
     */
    public static function sync_state_record(\stdClass $state, bool $dryrun = true): array {
        global $DB;

        if ((string)$state->targettype !== 'competency') {
            return self::skip_result($state, 'not_competency_state');
        }
        if ((int)($state->evidencecount ?? 0) <= 0) {
            return self::skip_result($state, 'no_evidence');
        }

        $competency = $DB->get_record('flwcupkp_comp', ['id' => (int)$state->targetid], '*', IGNORE_MISSING);
        if (!$competency || empty($competency->moodlecompetencyid)) {
            return self::skip_result($state, 'unlinked_competency');
        }
        if (!$DB->record_exists('user', ['id' => (int)$state->userid, 'deleted' => 0])) {
            return self::skip_result($state, 'missing_user');
        }

        $rating = self::rating_for_state($state, (int)$competency->moodlecompetencyid);
        if ($rating === null) {
            return self::skip_result($state, 'state_not_rateable');
        }

        $courseid = self::courseid_for_state($state, $competency, (int)$competency->moodlecompetencyid, $dryrun);
        $scope = $courseid > 0 ? 'course' : 'global';
        $existing = self::existing_moodle_rating((int)$state->userid, (int)$competency->moodlecompetencyid, $courseid);
        if ($existing && (int)$existing->grade === $rating['grade'] &&
                (int)$existing->proficiency === (int)$rating['proficiency']) {
            return [
                'status' => 'skipped',
                'reason' => 'already_current',
                'scope' => $scope,
                'userid' => (int)$state->userid,
                'targetid' => (int)$state->targetid,
                'moodlecompetencyid' => (int)$competency->moodlecompetencyid,
                'courseid' => $courseid,
                'grade' => $rating['grade'],
                'proficiency' => $rating['proficiency'],
            ];
        }

        $result = [
            'status' => $dryrun ? 'would_write' : 'written',
            'scope' => $scope,
            'userid' => (int)$state->userid,
            'targetid' => (int)$state->targetid,
            'moodlecompetencyid' => (int)$competency->moodlecompetencyid,
            'courseid' => $courseid,
            'grade' => $rating['grade'],
            'proficiency' => $rating['proficiency'],
            'masterystate' => (string)$state->masterystate,
            'masteryscore' => (float)$state->masteryscore,
        ];

        if ($dryrun) {
            return $result;
        }

        $note = self::rating_note($state, $competency, $rating, $courseid);
        self::run_as_admin(static function() use ($courseid, $state, $competency, $rating, $note): void {
            if ($courseid > 0) {
                \core_competency\api::grade_competency_in_course(
                    $courseid,
                    (int)$state->userid,
                    (int)$competency->moodlecompetencyid,
                    $rating['grade'],
                    $note
                );
            } else {
                \core_competency\api::grade_competency(
                    (int)$state->userid,
                    (int)$competency->moodlecompetencyid,
                    $rating['grade'],
                    $note
                );
            }
        });

        repository::audit('moodle_competency_rating_synced', 'competency', (int)$state->targetid, [
            'userid' => (int)$state->userid,
            'moodlecompetencyid' => (int)$competency->moodlecompetencyid,
            'courseid' => $courseid,
            'scope' => $scope,
            'grade' => $rating['grade'],
            'proficiency' => $rating['proficiency'],
            'masterystate' => (string)$state->masterystate,
            'masteryscore' => (float)$state->masteryscore,
            'confidence' => (float)$state->confidence,
            'evidencecount' => (int)$state->evidencecount,
        ]);

        return $result;
    }

    /**
     * Sync one stored C-UP-KP competency state if present.
     *
     * @param int $userid
     * @param int $targetid
     * @param bool $dryrun
     * @return array|null
     */
    public static function sync_competency_state(int $userid, int $targetid, bool $dryrun = true): ?array {
        global $DB;

        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => 'competency',
            'targetid' => $targetid,
        ], '*', IGNORE_MISSING);

        return $state ? self::sync_state_record($state, $dryrun) : null;
    }

    /**
     * Build a skipped sync result.
     *
     * @param \stdClass $state
     * @param string $reason
     * @return array
     */
    private static function skip_result(\stdClass $state, string $reason): array {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'userid' => (int)($state->userid ?? 0),
            'targetid' => (int)($state->targetid ?? 0),
            'masterystate' => (string)($state->masterystate ?? ''),
        ];
    }

    /**
     * Resolve the native Moodle grade for a C-UP-KP state.
     *
     * @param \stdClass $state
     * @param int $moodlecompetencyid
     * @return array|null
     */
    private static function rating_for_state(\stdClass $state, int $moodlecompetencyid): ?array {
        $stateName = (string)$state->masterystate;
        $grades = self::scale_grades_for_competency($moodlecompetencyid);

        if (in_array($stateName, self::PROFICIENT_STATES, true)) {
            return [
                'grade' => $grades['proficient'],
                'proficiency' => true,
            ];
        }
        if (in_array($stateName, self::NOT_PROFICIENT_STATES, true)) {
            return [
                'grade' => $grades['notproficient'],
                'proficiency' => false,
            ];
        }

        return null;
    }

    /**
     * Resolve Moodle scale grade numbers for a native competency.
     *
     * @param int $moodlecompetencyid
     * @return array
     */
    private static function scale_grades_for_competency(int $moodlecompetencyid): array {
        global $DB;

        $competency = $DB->get_record('competency', ['id' => $moodlecompetencyid], '*', MUST_EXIST);
        $framework = $DB->get_record('competency_framework', ['id' => (int)$competency->competencyframeworkid],
            '*', MUST_EXIST);
        $scaleid = !empty($competency->scaleid) ? (int)$competency->scaleid : (int)$framework->scaleid;
        $scaleconfiguration = !empty($competency->scaleconfiguration) ?
            $competency->scaleconfiguration : $framework->scaleconfiguration;
        $scale = $DB->get_record('scale', ['id' => $scaleid], '*', MUST_EXIST);
        $scaleitems = array_map('trim', explode(',', $scale->scale));
        $maxgrade = max(1, count($scaleitems));

        $proficientgrades = [];
        $defaultgrade = 1;
        $decoded = json_decode((string)$scaleconfiguration, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!empty($item['proficient']) && !empty($item['id'])) {
                    $proficientgrades[] = (int)$item['id'];
                }
                if (!empty($item['scaledefault']) && !empty($item['id'])) {
                    $defaultgrade = (int)$item['id'];
                }
            }
        }

        $proficientgrade = $proficientgrades ? max($proficientgrades) : $maxgrade;
        if ($defaultgrade === $proficientgrade && $maxgrade > 1) {
            $defaultgrade = $proficientgrade === 1 ? 2 : 1;
        }

        return [
            'proficient' => max(1, min($maxgrade, $proficientgrade)),
            'notproficient' => max(1, min($maxgrade, $defaultgrade)),
        ];
    }

    /**
     * Pick a course-scoped sync target when the learner can be rated there.
     *
     * @param \stdClass $state
     * @param \stdClass $competency
     * @param int $moodlecompetencyid
     * @param bool $dryrun
     * @return int
     */
    private static function courseid_for_state(\stdClass $state, \stdClass $competency, int $moodlecompetencyid,
            bool $dryrun): int {
        global $DB;

        $courseid = (int)$DB->get_field_sql(
            "SELECT courseid
               FROM {flwcupkp_evidence}
              WHERE userid = :userid
                AND targettype = :targettype
                AND targetid = :targetid
                AND courseid IS NOT NULL
                AND courseid > 0
           ORDER BY timecreated DESC, id DESC",
            [
                'userid' => (int)$state->userid,
                'targettype' => 'competency',
                'targetid' => (int)$state->targetid,
            ],
            IGNORE_MULTIPLE
        );
        if ($courseid <= 0) {
            $courseid = self::courseid_from_child_evidence((int)$state->userid, (int)$state->targetid);
        }
        if ($courseid <= 0) {
            $frameworkcourseid = $DB->get_field('flwcupkp_framework', 'courseid',
                ['id' => (int)$competency->frameworkid], IGNORE_MISSING);
            $courseid = (int)$frameworkcourseid;
        }
        if ($courseid <= 0 || !$DB->record_exists('course', ['id' => $courseid])) {
            return 0;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        $user = $DB->get_record('user', ['id' => (int)$state->userid, 'deleted' => 0], '*', IGNORE_MISSING);
        if (!$context || !$user || !is_enrolled($context, $user, '', true)) {
            return 0;
        }

        $exists = $DB->record_exists('competency_coursecomp', [
            'courseid' => $courseid,
            'competencyid' => $moodlecompetencyid,
        ]);
        if (!$exists && !$dryrun) {
            self::run_as_admin(static function() use ($courseid, $moodlecompetencyid): void {
                \core_competency\api::add_competency_to_course($courseid, $moodlecompetencyid);
            });
            $exists = true;
        }

        return $exists || $dryrun ? $courseid : 0;
    }

    /**
     * Infer course scope from mapped child UP/KP evidence for roll-up competency states.
     *
     * @param int $userid
     * @param int $competencyid
     * @return int
     */
    private static function courseid_from_child_evidence(int $userid, int $competencyid): int {
        global $DB;

        $courseid = (int)$DB->get_field_sql(
            "SELECT e.courseid
               FROM {flwcupkp_evidence} e
               JOIN {flwcupkp_comp_up} cu ON cu.upid = e.targetid
              WHERE e.userid = :userid
                AND e.targettype = :targettype
                AND cu.competencyid = :competencyid
                AND e.courseid IS NOT NULL
                AND e.courseid > 0
           ORDER BY e.timecreated DESC, e.id DESC",
            [
                'userid' => $userid,
                'targettype' => 'up',
                'competencyid' => $competencyid,
            ],
            IGNORE_MULTIPLE
        );
        if ($courseid > 0) {
            return $courseid;
        }

        return (int)$DB->get_field_sql(
            "SELECT e.courseid
               FROM {flwcupkp_evidence} e
               JOIN {flwcupkp_up_kp} uk ON uk.kpid = e.targetid
               JOIN {flwcupkp_comp_up} cu ON cu.upid = uk.upid
              WHERE e.userid = :userid
                AND e.targettype = :targettype
                AND cu.competencyid = :competencyid
                AND e.courseid IS NOT NULL
                AND e.courseid > 0
           ORDER BY e.timecreated DESC, e.id DESC",
            [
                'userid' => $userid,
                'targettype' => 'kp',
                'competencyid' => $competencyid,
            ],
            IGNORE_MULTIPLE
        );
    }

    /**
     * Fetch an existing native Moodle rating for idempotency.
     *
     * @param int $userid
     * @param int $moodlecompetencyid
     * @param int $courseid
     * @return \stdClass|null
     */
    private static function existing_moodle_rating(int $userid, int $moodlecompetencyid, int $courseid): ?\stdClass {
        global $DB;

        if ($courseid > 0) {
            return $DB->get_record('competency_usercompcourse', [
                'userid' => $userid,
                'courseid' => $courseid,
                'competencyid' => $moodlecompetencyid,
            ], '*', IGNORE_MISSING) ?: null;
        }

        return $DB->get_record('competency_usercomp', [
            'userid' => $userid,
            'competencyid' => $moodlecompetencyid,
        ], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Run a native Moodle competency operation as admin, then restore the previous user.
     *
     * @param callable $callback
     * @return mixed
     */
    private static function run_as_admin(callable $callback) {
        global $USER;

        $previoususer = $USER ?? null;
        \core\session\manager::set_user(get_admin());
        try {
            return $callback();
        } finally {
            if ($previoususer && !empty($previoususer->id)) {
                \core\session\manager::set_user($previoususer);
            }
        }
    }

    /**
     * Build a traceable Moodle evidence note.
     *
     * @param \stdClass $state
     * @param \stdClass $competency
     * @param array $rating
     * @param int $courseid
     * @return string
     */
    private static function rating_note(\stdClass $state, \stdClass $competency, array $rating, int $courseid): string {
        return 'FLW C-UP-KP sync: ' . $competency->externalid .
            ' -> ' . $state->masterystate .
            ' (score ' . sprintf('%.5f', (float)$state->masteryscore) .
            ', confidence ' . sprintf('%.5f', (float)$state->confidence) .
            ', evidence ' . (int)$state->evidencecount .
            ', Moodle grade ' . (int)$rating['grade'] .
            ($courseid > 0 ? ', course ' . $courseid : ', global') .
            ').';
    }
}

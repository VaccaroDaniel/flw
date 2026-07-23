<?php
// Reusable unit-level report helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds generic C-UP-KP unit progress, class overview, and parent decision data.
 */
class unit_report {
    /** @var array States treated as mastered/achieved/demonstrated in summary counts. */
    private const STRONG_STATES = [
        'mastered',
        'stable',
        'transfer_ready',
        'achieved',
        'sustained',
    ];

    /** @var array Valid KP state override options. */
    public const KP_STATES = [
        'not_introduced' => 'not_introduced',
        'introduced' => 'introduced',
        'practiced' => 'practiced',
        'controlled_use' => 'controlled_use',
        'independent_use' => 'independent_use',
        'mastered' => 'mastered',
        'review_due' => 'review_due',
    ];

    /** @var array Valid UP state override options. */
    public const UP_STATES = [
        'not_observed' => 'not_observed',
        'emerging' => 'emerging',
        'developing' => 'developing',
        'demonstrated' => 'demonstrated',
        'stable' => 'stable',
        'transfer_ready' => 'transfer_ready',
    ];

    /** @var array Valid competency state override options. */
    public const COMPETENCY_STATES = [
        'not_started' => 'not_started',
        'developing' => 'developing',
        'provisionally_achieved' => 'provisionally_achieved',
        'achieved' => 'achieved',
        'sustained' => 'sustained',
        'mastered' => 'mastered',
    ];

    /** @var array Competency states that count as achieved. */
    private const COMPETENCY_ACHIEVED_STATES = [
        'achieved',
        'sustained',
        'mastered',
    ];

    /** @var array UP states that count as demonstrated. */
    private const UP_DEMONSTRATED_STATES = [
        'demonstrated',
        'stable',
        'transfer_ready',
    ];

    /**
     * Unit target records from mapped learning objects.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function unit_targets(int $courseid, string $unitcode): array {
        global $DB;

        $keysql = $DB->sql_concat('m.targettype', "'-'", 'm.targetid');
        $params = ['unitcode' => $unitcode];
        $coursesql = '';
        if ($courseid > 0) {
            $coursesql = ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
            $params['courseid'] = $courseid;
        }

        $maps = $DB->get_records_sql(
            "SELECT DISTINCT {$keysql} AS targetkey, m.targettype, m.targetid
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE o.unitcode = :unitcode {$coursesql}
           ORDER BY m.targettype ASC, m.targetid ASC",
            $params
        );

        $targets = [];
        foreach ($maps as $map) {
            $target = self::target_record((string)$map->targettype, (int)$map->targetid);
            if ($target) {
                $target->targettype = (string)$map->targettype;
                $target->targetid = (int)$map->targetid;
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * Target record by type.
     *
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    public static function target_record(string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $table = $targettype === 'competency' ? 'flwcupkp_comp' :
            ($targettype === 'up' ? 'flwcupkp_up' : 'flwcupkp_kp');
        return $DB->get_record($table, ['id' => $targetid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Learners for a course, or a bounded site-wide learner list when no course is selected.
     *
     * @param int $courseid
     * @return array
     */
    public static function learners(int $courseid, string $unitcode = ''): array {
        global $DB;

        if ($courseid > 0) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            $namefields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, ' .
                'u.middlename, u.alternatename, u.email';
            $users = $context ? get_enrolled_users($context, '', 0, $namefields, 'u.lastname, u.firstname') : [];
        } else {
            $users = $DB->get_records('user', ['deleted' => 0, 'confirmed' => 1],
                'lastname ASC, firstname ASC',
                'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email',
                0, 200);
        }

        if ($unitcode === '') {
            return $users;
        }

        $evidenceusers = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                    u.middlename, u.alternatename, u.email
               FROM {user} u
               JOIN {flwcupkp_evidence} e ON e.userid = u.id
               JOIN {flwcupkp_object} o ON o.id = e.objectid
              WHERE o.unitcode = :unitcode
           ORDER BY u.lastname, u.firstname",
            ['unitcode' => $unitcode]
        );
        foreach ($evidenceusers as $user) {
            $users[$user->id] = $user;
        }

        return $users;
    }

    /**
     * Learner states keyed by target type and ID.
     *
     * @param int $userid
     * @param array $targets
     * @return array
     */
    public static function states(int $userid, array $targets): array {
        $states = [];
        foreach ($targets as $target) {
            $state = self::state_for($userid, (string)$target->targettype, (int)$target->targetid);
            if ($state) {
                $states[self::target_key((string)$target->targettype, (int)$target->targetid)] = $state;
            }
        }
        return $states;
    }

    /**
     * Objects grouped by target key.
     *
     * @param array $targets
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function objects_by_target(array $targets, int $courseid = 0, string $unitcode = ''): array {
        global $DB;

        $out = [];
        foreach ($targets as $target) {
            $params = [
                'targettype' => (string)$target->targettype,
                'targetid' => (int)$target->targetid,
            ];
            $coursesql = '';
            if ($courseid > 0) {
                $coursesql .= ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
                $params['courseid'] = $courseid;
            }
            if ($unitcode !== '') {
                $coursesql .= ' AND o.unitcode = :unitcode';
                $params['unitcode'] = $unitcode;
            }

            $records = $DB->get_records_sql(
                "SELECT o.*, m.name AS modname
                   FROM {flwcupkp_object_map} om
                   JOIN {flwcupkp_object} o ON o.id = om.objectid
              LEFT JOIN {course_modules} cm ON cm.id = o.cmid
              LEFT JOIN {modules} m ON m.id = cm.module
                  WHERE om.targettype = :targettype
                    AND om.targetid = :targetid
                        {$coursesql}
               ORDER BY o.lesson ASC, o.externalid ASC",
                $params
            );
            $out[self::target_key((string)$target->targettype, (int)$target->targetid)] = $records;
        }

        return $out;
    }

    /**
     * Target class stats.
     *
     * @param \stdClass $target
     * @param array $learners
     * @return array
     */
    public static function target_stats(\stdClass $target, array $learners): array {
        global $DB;

        $strong = 0;
        $weak = 0;
        foreach ($learners as $learner) {
            $state = self::state_for((int)$learner->id, (string)$target->targettype, (int)$target->targetid);
            if ($state && in_array((string)$state->masterystate, self::STRONG_STATES, true)) {
                $strong++;
            } else {
                $weak++;
            }
        }

        $evidence = $DB->count_records('flwcupkp_evidence', [
            'targettype' => (string)$target->targettype,
            'targetid' => (int)$target->targetid,
        ]);

        return ['strong' => $strong, 'weak' => $weak, 'evidence' => $evidence];
    }

    /**
     * Build a lightweight learner progress summary for any mapped unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    public static function student_progress(int $courseid, string $unitcode, int $userid): array {
        $targets = self::unit_targets($courseid, $unitcode);
        $states = self::states($userid, $targets);
        $objects = self::objects_by_target($targets, $courseid, $unitcode);
        $rows = [];
        $strong = 0;
        $withevidence = 0;

        foreach ($targets as $target) {
            $targettype = (string)$target->targettype;
            $targetid = (int)$target->targetid;
            $key = self::target_key($targettype, $targetid);
            $state = $states[$key] ?? null;
            $masterystate = $state->masterystate ?? self::default_state($targettype);
            $isstrong = in_array((string)$masterystate, self::STRONG_STATES, true);
            $targetobjects = array_values($objects[$key] ?? []);
            $nextobject = reset($targetobjects) ?: null;

            if ($isstrong) {
                $strong++;
            }
            if ($state && (int)$state->evidencecount > 0) {
                $withevidence++;
            }

            $rows[] = [
                'targettype' => $targettype,
                'targetid' => $targetid,
                'externalid' => (string)$target->externalid,
                'title' => (string)$target->title,
                'state' => (string)$masterystate,
                'mastery_score' => $state ? (float)$state->masteryscore : null,
                'evidence_count' => $state ? (int)$state->evidencecount : 0,
                'is_mastered' => $isstrong,
                'is_gap' => !$isstrong,
                'next_activity' => $nextobject ? [
                    'title' => (string)$nextobject->title,
                    'url' => self::activity_url($nextobject),
                    'reason' => $state && (int)$state->evidencecount > 0 ?
                        get_string('coursegenericnextpractice', 'local_flwcupkp') :
                        get_string('coursegenericnextstart', 'local_flwcupkp'),
                    'lesson' => (string)($nextobject->lesson ?? ''),
                ] : null,
            ];
        }

        $total = count($rows);
        return [
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'mastered' => $strong,
                'gaps' => $total - $strong,
                'with_evidence' => $withevidence,
                'percent' => $total > 0 ? round(($strong / $total) * 100) : 0,
            ],
            'next_recommendation' => self::first_gap($rows),
        ];
    }

    /**
     * Build generic learner/KP evidence verification rows for a mapped unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param array $filters
     * @return array
     */
    public static function kp_report(int $courseid, string $unitcode, array $filters = []): array {
        $learners = self::learners($courseid, $unitcode);
        $targets = self::kp_targets($courseid, $unitcode);
        $rows = [];

        foreach ($learners as $learner) {
            if (!empty($filters['userid']) && (int)$filters['userid'] !== (int)$learner->id) {
                continue;
            }

            foreach ($targets as $target) {
                if (!empty($filters['domain']) && (string)$filters['domain'] !== (string)$target->domain) {
                    continue;
                }
                if (!empty($filters['lesson']) && (string)$filters['lesson'] !== (string)$target->lesson) {
                    continue;
                }

                $state = self::state_for((int)$learner->id, 'kp', (int)$target->kpid);
                if (!empty($filters['state']) && (!$state || (string)$filters['state'] !== (string)$state->masterystate)) {
                    continue;
                }

                $evidence = self::latest_evidence((int)$learner->id, (int)$target->objectid, 'kp', (int)$target->kpid);
                $row = self::kp_row($learner, $target, $state, $evidence);
                if (!self::matches_evidence_filter($row, (string)($filters['evidence'] ?? ''))) {
                    continue;
                }
                $rows[] = $row;
            }
        }

        return [
            'learners' => $learners,
            'targets' => $targets,
            'rows' => $rows,
            'filters' => [
                'domains' => self::distinct_values($targets, 'domain'),
                'lessons' => self::distinct_values($targets, 'lesson'),
                'states' => self::state_options($rows),
            ],
        ];
    }

    /**
     * Build generic UP/competency mastery overview rows for a mapped unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param array $filters
     * @return array
     */
    public static function mastery_overview(int $courseid, string $unitcode, array $filters = []): array {
        $learners = self::learners($courseid, $unitcode);
        $targets = self::parent_targets($courseid, $unitcode);
        $rows = [];
        $competencyachieved = 0;
        $competencytotal = 0;
        $updemonstrated = 0;
        $uptotal = 0;

        foreach ($learners as $learner) {
            if (!empty($filters['userid']) && (int)$filters['userid'] !== (int)$learner->id) {
                continue;
            }

            foreach ($targets as $target) {
                $targettype = (string)$target->targettype;
                if (!empty($filters['targettype']) && (string)$filters['targettype'] !== $targettype) {
                    continue;
                }

                $state = self::state_for((int)$learner->id, $targettype, (int)$target->targetid);
                if (!empty($filters['state']) && (!$state ||
                        (string)$filters['state'] !== (string)$state->masterystate)) {
                    continue;
                }

                $evidence = self::latest_target_evidence((int)$learner->id, $targettype, (int)$target->targetid);
                $row = self::parent_row($learner, $target, $state, $evidence);
                if (!self::matches_parent_state_group($row, (string)($filters['stategroup'] ?? ''))) {
                    continue;
                }
                if (!self::matches_parent_review_filter($row, (string)($filters['parentreview'] ?? ''))) {
                    continue;
                }

                $rows[] = $row;
                if ($row['targettype'] === 'competency') {
                    $competencytotal++;
                    if (self::is_parent_achieved($row)) {
                        $competencyachieved++;
                    }
                } else if ($row['targettype'] === 'up') {
                    $uptotal++;
                    if (self::is_parent_demonstrated($row)) {
                        $updemonstrated++;
                    }
                }
            }
        }

        return [
            'learners' => $learners,
            'targets' => $targets,
            'rows' => $rows,
            'summary' => [
                'competency_total' => $competencytotal,
                'competency_achieved' => $competencyachieved,
                'up_total' => $uptotal,
                'up_demonstrated' => $updemonstrated,
            ],
            'filters' => [
                'targettypes' => self::distinct_values($targets, 'targettype'),
                'states' => self::state_options($rows),
            ],
        ];
    }

    /**
     * Build parent queue summary data for the current class or selected learner.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    public static function parent_queue_summary(int $courseid, string $unitcode, int $userid = 0): array {
        $basefilters = [];
        if ($userid > 0) {
            $basefilters['userid'] = $userid;
        }

        $competencyqueue = self::mastery_overview($courseid, $unitcode, $basefilters + [
            'targettype' => 'competency',
            'stategroup' => 'notachieved',
            'parentreview' => 'review',
        ]);
        $upqueue = self::mastery_overview($courseid, $unitcode, $basefilters + [
            'targettype' => 'up',
            'stategroup' => 'notdemonstrated',
            'parentreview' => 'review',
        ]);

        return [
            'competency' => [
                'count' => count($competencyqueue['rows']),
                'first' => reset($competencyqueue['rows']) ?: null,
            ],
            'up' => [
                'count' => count($upqueue['rows']),
                'first' => reset($upqueue['rows']) ?: null,
            ],
            'total' => count($competencyqueue['rows']) + count($upqueue['rows']),
        ];
    }

    /**
     * Record a generic teacher decision for one parent UP/competency row.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $action
     * @param array $data
     * @return string Action status key
     */
    public static function record_parent_action(int $courseid, string $unitcode, string $action, array $data): string {
        if ($action === 'approveparent') {
            self::confirm_parent_state(
                $courseid,
                $unitcode,
                (int)($data['userid'] ?? 0),
                (string)($data['parenttargettype'] ?? ''),
                (int)($data['targetid'] ?? 0)
            );
            return 'approved';
        }

        if ($action === 'overrideparent') {
            self::override_parent_state(
                $courseid,
                $unitcode,
                (int)($data['userid'] ?? 0),
                (string)($data['parenttargettype'] ?? ''),
                (int)($data['targetid'] ?? 0),
                (string)($data['state'] ?? ''),
                (float)($data['score'] ?? 0),
                (string)($data['reason'] ?? '')
            );
            return 'overridden';
        }

        if ($action === 'clearparentoverride') {
            self::clear_parent_override(
                $courseid,
                $unitcode,
                (int)($data['userid'] ?? 0),
                (string)($data['parenttargettype'] ?? ''),
                (int)($data['targetid'] ?? 0)
            );
            return 'cleared';
        }

        throw new \invalid_parameter_exception('Unsupported parent teacher action.');
    }

    /**
     * Record a generic teacher decision for one KP evidence/state row.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $action
     * @param array $data
     * @return string Action status key
     */
    public static function record_kp_action(int $courseid, string $unitcode, string $action, array $data): string {
        if ($action === 'approve') {
            self::approve_evidence($courseid, $unitcode, (int)($data['evidenceid'] ?? 0));
            return 'approved';
        }

        if ($action === 'override') {
            self::override_kp_state(
                $courseid,
                $unitcode,
                (int)($data['userid'] ?? 0),
                (int)($data['targetid'] ?? 0),
                (string)($data['state'] ?? ''),
                (float)($data['score'] ?? 0),
                (string)($data['reason'] ?? '')
            );
            return 'overridden';
        }

        if ($action === 'clearoverride') {
            self::clear_kp_override(
                $courseid,
                $unitcode,
                (int)($data['userid'] ?? 0),
                (int)($data['targetid'] ?? 0)
            );
            return 'cleared';
        }

        throw new \invalid_parameter_exception('Unsupported KP teacher action.');
    }

    /**
     * Stable key for target arrays.
     *
     * @param string $targettype
     * @param int $targetid
     * @return string
     */
    public static function target_key(string $targettype, int $targetid): string {
        return $targettype . ':' . $targetid;
    }

    /**
     * Stable parent row anchor.
     *
     * @param array $row
     * @return string
     */
    public static function parent_row_anchor(array $row): string {
        $prefix = $row['targettype'] === 'competency' ? 'comp' : 'up';
        return 'u' . (int)$row['userid'] . '-' . $prefix . (int)$row['targetid'];
    }

    /**
     * Parent mastery state-group filter predicate.
     *
     * @param array $row
     * @param string $filter
     * @return bool
     */
    public static function matches_parent_state_group(array $row, string $filter): bool {
        if ($filter === '') {
            return true;
        }

        if ($filter === 'achieved') {
            return $row['targettype'] === 'competency' && self::is_parent_achieved($row);
        }
        if ($filter === 'notachieved') {
            return $row['targettype'] === 'competency' && !self::is_parent_achieved($row);
        }
        if ($filter === 'demonstrated') {
            return $row['targettype'] === 'up' && self::is_parent_demonstrated($row);
        }
        if ($filter === 'notdemonstrated') {
            return $row['targettype'] === 'up' && !self::is_parent_demonstrated($row);
        }
        if ($filter === 'attention') {
            return self::parent_needs_attention($row);
        }

        return true;
    }

    /**
     * Parent teacher-decision filter predicate.
     *
     * @param array $row
     * @param string $filter
     * @return bool
     */
    public static function matches_parent_review_filter(array $row, string $filter): bool {
        if ($filter === '') {
            return true;
        }

        $hasdecision = !empty($row['verification']);
        if ($filter === 'review') {
            return self::parent_needs_attention($row) && !$hasdecision;
        }
        if ($filter === 'decided') {
            return $hasdecision;
        }

        return true;
    }

    /**
     * Whether a parent row needs teacher attention.
     *
     * @param array $row
     * @return bool
     */
    public static function parent_needs_attention(array $row): bool {
        return ($row['targettype'] === 'competency' && !self::is_parent_achieved($row)) ||
            ($row['targettype'] === 'up' && !self::is_parent_demonstrated($row));
    }

    /**
     * Stable KP row anchor.
     *
     * @param array $row
     * @return string
     */
    public static function kp_row_anchor(array $row): string {
        return 'u' . (int)$row['userid'] . '-kp' . (int)$row['kp_id'];
    }

    /**
     * Approve the latest evidence/state pairing for a mapped KP row.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $evidenceid
     */
    private static function approve_evidence(int $courseid, string $unitcode, int $evidenceid): void {
        global $DB;

        if ($evidenceid <= 0) {
            throw new \invalid_parameter_exception('Evidence ID is required.');
        }

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $evidenceid], '*', MUST_EXIST);
        if ((int)$evidence->userid <= 0 || (string)$evidence->targettype !== 'kp') {
            throw new \invalid_parameter_exception('Evidence is not a KP evidence row.');
        }
        self::validate_unit_learner($courseid, $unitcode, (int)$evidence->userid);
        $target = self::validated_kp_target($courseid, $unitcode, (int)$evidence->targetid, (int)$evidence->objectid);
        $state = self::state_for((int)$evidence->userid, 'kp', (int)$target->kpid);

        repository::audit('teacher_evidence_approved', 'kp', (int)$target->kpid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => (int)$evidence->userid,
            'evidenceid' => $evidenceid,
            'objectid' => (int)$evidence->objectid,
            'cmid' => (int)$target->cmid,
            'state' => $state->masterystate ?? null,
            'masteryscore' => $state ? (float)$state->masteryscore : null,
            'normalizedscore' => (float)$evidence->normalizedscore,
        ]);
    }

    /**
     * Manually override a learner's mapped KP state.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param int $targetid
     * @param string $state
     * @param float $score
     * @param string $reason
     */
    private static function override_kp_state(int $courseid, string $unitcode, int $userid, int $targetid,
            string $state, float $score, string $reason): void {
        global $DB;

        self::validate_unit_learner($courseid, $unitcode, $userid);
        if (!isset(self::KP_STATES[$state])) {
            throw new \invalid_parameter_exception('Invalid KP state override.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \invalid_parameter_exception('Override reason is required.');
        }

        $target = self::validated_kp_target($courseid, $unitcode, $targetid);
        $score = max(0, min(1, $score));
        $existing = self::state_for($userid, 'kp', $targetid);
        $latest = self::latest_target_evidence($userid, 'kp', $targetid);

        $record = (object)[
            'userid' => $userid,
            'targettype' => 'kp',
            'targetid' => $targetid,
            'masteryscore' => $score,
            'masterystate' => $state,
            'confidence' => 1.0,
            'evidencecount' => $existing ? (int)$existing->evidencecount : ($latest ? 1 : 0),
            'lastevidence' => $existing->lastevidence ?? ($latest ? (int)$latest->timecreated : null),
            'lastsuccess' => $existing->lastsuccess ?? ($score >= 0.70 ? time() : null),
            'nextreview' => null,
            'manualoverride' => 1,
            'overridereason' => $reason,
            'ruleversion' => 'teacher-override-v1',
            'timemodified' => time(),
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('flwcupkp_state', $record);
        } else {
            $DB->insert_record('flwcupkp_state', $record);
        }

        repository::audit('teacher_state_overridden', 'kp', $targetid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
            'state' => $state,
            'masteryscore' => $score,
            'reason' => $reason,
            'evidenceid' => $latest ? (int)$latest->id : null,
            'objectid' => (int)$target->objectid,
            'cmid' => (int)$target->cmid,
        ]);

        self::recalculate_rollups_after_kp_change($courseid, $unitcode, $userid, $targetid, 'override');
    }

    /**
     * Clear manual override and recalculate a mapped KP from existing evidence.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param int $targetid
     */
    private static function clear_kp_override(int $courseid, string $unitcode, int $userid, int $targetid): void {
        global $DB;

        self::validate_unit_learner($courseid, $unitcode, $userid);
        $target = self::validated_kp_target($courseid, $unitcode, $targetid);
        $events = $DB->get_records('flwcupkp_evidence', [
            'userid' => $userid,
            'targettype' => 'kp',
            'targetid' => $targetid,
        ], 'timecreated ASC');
        $state = mastery_engine::calculate('kp', array_values($events));
        $state['manualoverride'] = 0;
        $state['overridereason'] = null;

        $existing = self::state_for($userid, 'kp', $targetid);
        if ($existing) {
            $record = (object)[
                'id' => $existing->id,
                'userid' => $userid,
                'targettype' => 'kp',
                'targetid' => $targetid,
                'masteryscore' => $state['masteryscore'],
                'masterystate' => $state['masterystate'],
                'confidence' => $state['confidence'],
                'evidencecount' => $state['evidencecount'],
                'lastevidence' => $state['lastevidence'] ?? null,
                'lastsuccess' => $state['lastsuccess'] ?? null,
                'nextreview' => $state['nextreview'] ?? null,
                'manualoverride' => 0,
                'overridereason' => null,
                'ruleversion' => $state['ruleversion'] ?? 'default-v1',
                'timemodified' => time(),
            ];
            $DB->update_record('flwcupkp_state', $record);
        } else {
            repository::upsert_state($userid, 'kp', $targetid, $state);
        }

        repository::audit('teacher_override_cleared', 'kp', $targetid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
            'objectid' => (int)$target->objectid,
            'cmid' => (int)$target->cmid,
            'recalculated_state' => $state['masterystate'],
            'recalculated_score' => $state['masteryscore'],
        ]);

        self::recalculate_rollups_after_kp_change($courseid, $unitcode, $userid, $targetid, 'clearoverride');
    }

    /**
     * Confirm a learner's current parent UP/competency state.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     */
    private static function confirm_parent_state(int $courseid, string $unitcode, int $userid, string $targettype,
            int $targetid): void {
        self::validate_unit_learner($courseid, $unitcode, $userid);
        $target = self::validated_parent_target($courseid, $unitcode, $targettype, $targetid);
        $state = self::state_for($userid, $targettype, $targetid);
        $latest = self::latest_target_evidence($userid, $targettype, $targetid);

        repository::audit('teacher_parent_state_confirmed', $targettype, $targetid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
            'state' => $state->masterystate ?? self::default_state($targettype),
            'masteryscore' => $state ? (float)$state->masteryscore : null,
            'confidence' => $state ? (float)$state->confidence : null,
            'evidencecount' => $state ? (int)$state->evidencecount : 0,
            'evidenceid' => $latest ? (int)$latest->id : null,
            'targetexternalid' => (string)$target->externalid,
        ]);

        if ($targettype === 'competency' && $state) {
            self::sync_parent_competency_if_ready($courseid, $unitcode, $userid, $targetid, 'confirm');
        }
    }

    /**
     * Manually override a learner's parent UP/competency state.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param string $state
     * @param float $score
     * @param string $reason
     */
    private static function override_parent_state(int $courseid, string $unitcode, int $userid, string $targettype,
            int $targetid, string $state, float $score, string $reason): void {
        global $DB;

        self::validate_unit_learner($courseid, $unitcode, $userid);
        if (!self::valid_parent_state($targettype, $state)) {
            throw new \invalid_parameter_exception('Invalid parent state override.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \invalid_parameter_exception('Override reason is required.');
        }

        $target = self::validated_parent_target($courseid, $unitcode, $targettype, $targetid);
        $score = max(0, min(1, $score));
        $existing = self::state_for($userid, $targettype, $targetid);
        $latest = self::latest_target_evidence($userid, $targettype, $targetid);
        $evidencecount = max(1, $existing ? (int)$existing->evidencecount : ($latest ? 1 : 0));

        $record = (object)[
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'masteryscore' => $score,
            'masterystate' => $state,
            'confidence' => 1.0,
            'evidencecount' => $evidencecount,
            'lastevidence' => $existing->lastevidence ?? ($latest ? (int)$latest->timecreated : time()),
            'lastsuccess' => $score >= 0.70 ? time() : ($existing->lastsuccess ?? null),
            'nextreview' => null,
            'manualoverride' => 1,
            'overridereason' => $reason,
            'ruleversion' => 'teacher-parent-override-v1',
            'timemodified' => time(),
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('flwcupkp_state', $record);
        } else {
            $DB->insert_record('flwcupkp_state', $record);
        }

        repository::audit('teacher_parent_state_overridden', $targettype, $targetid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
            'state' => $state,
            'masteryscore' => $score,
            'reason' => $reason,
            'evidenceid' => $latest ? (int)$latest->id : null,
            'targetexternalid' => (string)$target->externalid,
        ]);

        if ($targettype === 'up') {
            self::recalculate_rollups_after_parent_change($courseid, $unitcode, $userid, $targettype, $targetid,
                'override');
        } else {
            self::sync_parent_competency_if_ready($courseid, $unitcode, $userid, $targetid, 'override');
        }
    }

    /**
     * Clear a parent manual override and recalculate the affected parent state.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     */
    private static function clear_parent_override(int $courseid, string $unitcode, int $userid, string $targettype,
            int $targetid): void {
        global $DB;

        self::validate_unit_learner($courseid, $unitcode, $userid);
        $target = self::validated_parent_target($courseid, $unitcode, $targettype, $targetid);
        $existing = self::state_for($userid, $targettype, $targetid);
        if (!$existing) {
            throw new \invalid_parameter_exception('No parent state exists for this learner.');
        }

        $existing->manualoverride = 0;
        $existing->overridereason = null;
        $existing->timemodified = time();
        $DB->update_record('flwcupkp_state', $existing);

        $rollup = self::recalculate_rollups_after_parent_change($courseid, $unitcode, $userid, $targettype, $targetid,
            'clearoverride');
        $recalculated = self::state_for($userid, $targettype, $targetid);

        repository::audit('teacher_parent_override_cleared', $targettype, $targetid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
            'targetexternalid' => (string)$target->externalid,
            'recalculated_state' => $recalculated->masterystate ?? null,
            'recalculated_score' => $recalculated ? (float)$recalculated->masteryscore : null,
            'rollup' => $rollup,
        ]);
    }

    /**
     * Validate that the learner belongs to this unit's course/evidence roster.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     */
    private static function validate_unit_learner(int $courseid, string $unitcode, int $userid): void {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner is required.');
        }
        foreach (self::learners($courseid, $unitcode) as $learner) {
            if ((int)$learner->id === $userid) {
                return;
            }
        }
        throw new \invalid_parameter_exception('Learner is not available in this unit report.');
    }

    /**
     * Validate that a parent target belongs to the selected unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass
     */
    private static function validated_parent_target(int $courseid, string $unitcode, string $targettype,
            int $targetid): \stdClass {
        if (!in_array($targettype, ['up', 'competency'], true)) {
            throw new \invalid_parameter_exception('Invalid parent target type.');
        }
        foreach (self::parent_targets($courseid, $unitcode) as $target) {
            if ((string)$target->targettype === $targettype && (int)$target->targetid === $targetid) {
                return $target;
            }
        }
        throw new \invalid_parameter_exception('Target is not a UP or competency target in this unit.');
    }

    /**
     * Validate parent state option.
     *
     * @param string $targettype
     * @param string $state
     * @return bool
     */
    private static function valid_parent_state(string $targettype, string $state): bool {
        if ($targettype === 'up') {
            return isset(self::UP_STATES[$state]);
        }
        if ($targettype === 'competency') {
            return isset(self::COMPETENCY_STATES[$state]);
        }
        return false;
    }

    /**
     * Recalculate parent state dependencies after a teacher changes a UP or competency.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param string $source
     * @return array|null
     */
    private static function recalculate_rollups_after_parent_change(int $courseid, string $unitcode, int $userid,
            string $targettype, int $targetid, string $source): ?array {
        try {
            return rollup_engine::recalculate_dependents($userid, $targettype, $targetid, true);
        } catch (\Throwable $e) {
            repository::audit('rollup_state_sync_failed', $targettype, $targetid, [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'userid' => $userid,
                'message' => $e->getMessage(),
                'source' => 'teacher_parent_' . $source,
            ]);
            return null;
        }
    }

    /**
     * Best-effort native Moodle competency sync for parent competency teacher decisions.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param int $targetid
     * @param string $source
     * @return array|null
     */
    private static function sync_parent_competency_if_ready(int $courseid, string $unitcode, int $userid,
            int $targetid, string $source): ?array {
        if (!(bool)get_config('local_flwcupkp', 'enablesyncwrites')) {
            return null;
        }
        $readiness = curriculum_manager::sync_readiness();
        if (empty($readiness['readyforwrites'])) {
            return null;
        }

        try {
            $result = moodle_competency_writer::sync_competency_state($userid, $targetid, false);
            repository::audit('teacher_parent_moodle_sync_checked', 'competency', $targetid, [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'userid' => $userid,
                'source' => 'teacher_parent_' . $source,
                'result' => $result,
            ]);
            return $result;
        } catch (\Throwable $e) {
            repository::audit('moodle_competency_rating_sync_failed', 'competency', $targetid, [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'userid' => $userid,
                'message' => $e->getMessage(),
                'source' => 'teacher_parent_' . $source,
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recalculate parent states after a teacher changes a KP state.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param int $targetid
     * @param string $source
     */
    private static function recalculate_rollups_after_kp_change(int $courseid, string $unitcode, int $userid,
            int $targetid, string $source): void {
        try {
            rollup_engine::recalculate_dependents($userid, 'kp', $targetid, true);
        } catch (\Throwable $e) {
            repository::audit('rollup_state_sync_failed', 'kp', $targetid, [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'userid' => $userid,
                'message' => $e->getMessage(),
                'source' => 'teacher_' . $source,
            ]);
        }
    }

    /**
     * Pick the first actionable gap, prioritizing concrete KP practice.
     *
     * @param array $rows
     * @return array|null
     */
    private static function first_gap(array $rows): ?array {
        $typeorder = ['kp' => 0, 'up' => 1, 'competency' => 2];
        usort($rows, static function(array $left, array $right) use ($typeorder): int {
            $lefttype = $typeorder[(string)$left['targettype']] ?? 9;
            $righttype = $typeorder[(string)$right['targettype']] ?? 9;
            if ($lefttype !== $righttype) {
                return $lefttype <=> $righttype;
            }
            $leftlesson = (string)($left['next_activity']['lesson'] ?? '');
            $rightlesson = (string)($right['next_activity']['lesson'] ?? '');
            if ($leftlesson !== $rightlesson) {
                return strnatcasecmp($leftlesson, $rightlesson);
            }
            return strcmp((string)$left['externalid'], (string)$right['externalid']);
        });

        foreach ($rows as $row) {
            if (!empty($row['is_gap'])) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Build a Moodle activity URL from a mapped object.
     *
     * @param \stdClass $object
     * @return \moodle_url|null
     */
    private static function activity_url(\stdClass $object): ?\moodle_url {
        if (empty($object->cmid) || empty($object->modname)) {
            return null;
        }
        return new \moodle_url('/mod/' . $object->modname . '/view.php', ['id' => $object->cmid]);
    }

    /**
     * Default state for a target type with no calculated state yet.
     *
     * @param string $targettype
     * @return string
     */
    private static function default_state(string $targettype): string {
        return $targettype === 'kp' ? 'not_introduced' : ($targettype === 'up' ? 'not_observed' : 'not_started');
    }

    /**
     * Get mapped KP targets for a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function kp_targets(int $courseid, string $unitcode): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('o.id', "'-'", 'kp.id') . " AS rowid,
                    o.id AS objectid,
                    o.externalid AS objectexternalid,
                    o.title AS objecttitle,
                    o.lesson,
                    o.cmid,
                    m.name AS modname,
                    kp.id AS kpid,
                    kp.externalid AS kpexternalid,
                    kp.title AS kptitle,
                    kp.domain
               FROM {flwcupkp_object} o
               JOIN {flwcupkp_object_map} om ON om.objectid = o.id
               JOIN {flwcupkp_kp} kp ON kp.id = om.targetid
          LEFT JOIN {course_modules} cm ON cm.id = o.cmid
          LEFT JOIN {modules} m ON m.id = cm.module
              WHERE o.unitcode = :unitcode
                AND om.targettype = 'kp'
                AND (:courseid = 0 OR o.courseid = :courseid1 OR o.courseid IS NULL)
           ORDER BY o.lesson ASC, kp.domain ASC, kp.externalid ASC",
            [
                'unitcode' => $unitcode,
                'courseid' => $courseid,
                'courseid1' => $courseid,
            ]
        );
    }

    /**
     * Convert generic KP target state into a display row.
     *
     * @param \stdClass $learner
     * @param \stdClass $target
     * @param \stdClass|null $state
     * @param \stdClass|null $evidence
     * @return array
     */
    private static function kp_row(\stdClass $learner, \stdClass $target, ?\stdClass $state,
            ?\stdClass $evidence): array {
        $rubric = $evidence ? json_decode((string)$evidence->rubricjson, true) : [];
        if (!is_array($rubric)) {
            $rubric = [];
        }

        return [
            'userid' => (int)$learner->id,
            'learner' => fullname($learner),
            'lesson' => (string)$target->lesson,
            'domain' => (string)$target->domain,
            'kp_id' => (int)$target->kpid,
            'kp_externalid' => (string)$target->kpexternalid,
            'kp_title' => (string)$target->kptitle,
            'object_id' => (int)$target->objectid,
            'object_externalid' => (string)$target->objectexternalid,
            'object_title' => (string)$target->objecttitle,
            'cmid' => (int)$target->cmid,
            'modname' => $target->modname ?? '',
            'state' => $state->masterystate ?? 'not_introduced',
            'mastery_score' => $state ? (float)$state->masteryscore : null,
            'confidence' => $state ? (float)$state->confidence : null,
            'evidence_count' => $state ? (int)$state->evidencecount : 0,
            'manual_override' => $state ? (int)$state->manualoverride : 0,
            'override_reason' => $state->overridereason ?? '',
            'evidence_id' => $evidence ? (int)$evidence->id : null,
            'attempt_id' => self::attempt_id($evidence, $rubric),
            'evidence_score' => $evidence ? (float)$evidence->normalizedscore : null,
            'evidence_strength' => $evidence->evidencestrength ?? '',
            'evidence_time' => $evidence ? (int)$evidence->timecreated : null,
            'provenance' => $evidence->provenance ?? '',
            'sourceattempt' => $evidence->sourceattempt ?? '',
            'verification' => self::latest_kp_verification($learner, $target, $evidence),
            'explanation' => self::kp_explanation($state, $evidence),
        ];
    }

    /**
     * Fetch latest evidence for a learner/object/target.
     *
     * @param int $userid
     * @param int $objectid
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function latest_evidence(int $userid, int $objectid, string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $records = $DB->get_records('flwcupkp_evidence', [
            'userid' => $userid,
            'objectid' => $objectid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], 'timecreated DESC, id DESC', '*', 0, 1);

        $evidence = reset($records);
        return $evidence ?: null;
    }

    /**
     * Extract attempt ID.
     *
     * @param \stdClass|null $evidence
     * @param array $rubric
     * @return int|null
     */
    private static function attempt_id(?\stdClass $evidence, array $rubric): ?int {
        if (isset($rubric['attemptid'])) {
            return (int)$rubric['attemptid'];
        }
        if ($evidence && preg_match('/quiz_attempt:(\d+)/', (string)$evidence->sourceattempt, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Human-readable KP explanation.
     *
     * @param \stdClass|null $state
     * @param \stdClass|null $evidence
     * @return string
     */
    private static function kp_explanation(?\stdClass $state, ?\stdClass $evidence): string {
        if (!$evidence) {
            return 'No mapped evidence has been recorded for this learner and learning point yet.';
        }
        if (!$state) {
            return 'Evidence exists, but learner state has not been calculated yet.';
        }

        $score = format_float((float)$evidence->normalizedscore, 2);
        $strength = $evidence->evidencestrength ?: 'unspecified';
        return 'State "' . $state->masterystate . '" is based on latest mapped evidence score ' . $score .
            ' with evidence strength "' . $strength . '" and confidence ' .
            format_float((float)$state->confidence, 2) . '.';
    }

    /**
     * Latest teacher verification audit for a learner/KP row.
     *
     * @param \stdClass $learner
     * @param \stdClass $target
     * @param \stdClass|null $evidence
     * @return array|null
     */
    private static function latest_kp_verification(\stdClass $learner, \stdClass $target,
            ?\stdClass $evidence): ?array {
        global $DB;

        $records = $DB->get_records('flwcupkp_audit', [
            'targettype' => 'kp',
            'targetid' => (int)$target->kpid,
        ], 'timecreated DESC, id DESC', '*', 0, 30);

        foreach ($records as $record) {
            if (!in_array($record->action, ['teacher_evidence_approved', 'teacher_state_overridden',
                    'teacher_override_cleared'], true)) {
                continue;
            }
            $details = json_decode((string)$record->detailsjson, true);
            if (!is_array($details) || (int)($details['userid'] ?? 0) !== (int)$learner->id) {
                continue;
            }
            if ($record->action === 'teacher_evidence_approved' && $evidence &&
                    (int)($details['evidenceid'] ?? 0) !== (int)$evidence->id) {
                continue;
            }

            $teacher = $DB->get_record('user', ['id' => $record->userid], '*', IGNORE_MISSING);
            return [
                'action' => $record->action,
                'timecreated' => (int)$record->timecreated,
                'teacher' => $teacher ? fullname($teacher) : '',
                'details' => $details,
            ];
        }

        return null;
    }

    /**
     * Evidence/verification filter predicate.
     *
     * @param array $row
     * @param string $filter
     * @return bool
     */
    private static function matches_evidence_filter(array $row, string $filter): bool {
        if ($filter === '') {
            return true;
        }

        $hasevidence = !empty($row['evidence_id']);
        $isverified = !empty($row['verification']) &&
            in_array($row['verification']['action'], ['teacher_evidence_approved', 'teacher_state_overridden'], true);

        if ($filter === 'with') {
            return $hasevidence;
        }
        if ($filter === 'verified') {
            return $isverified;
        }
        if ($filter === 'review') {
            return $hasevidence && !$isverified;
        }

        return true;
    }

    /**
     * Validate that a KP target belongs to the selected unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $targetid
     * @param int $objectid
     * @return \stdClass
     */
    private static function validated_kp_target(int $courseid, string $unitcode, int $targetid,
            int $objectid = 0): \stdClass {
        foreach (self::kp_targets($courseid, $unitcode) as $target) {
            if ((int)$target->kpid !== $targetid) {
                continue;
            }
            if ($objectid > 0 && (int)$target->objectid !== $objectid) {
                continue;
            }
            return $target;
        }
        throw new \invalid_parameter_exception('Target is not a KP target in this unit.');
    }

    /**
     * Unit parent targets discovered through direct target maps and child topology.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function parent_targets(int $courseid, string $unitcode): array {
        global $DB;

        $targets = [];

        $ups = $DB->get_records_sql(
            "SELECT DISTINCT u.id AS targetid,
                    'up' AS targettype,
                    u.externalid,
                    u.title,
                    u.cefr,
                    u.languagemode,
                    u.interactiontype
               FROM {flwcupkp_up} u
          LEFT JOIN {flwcupkp_up_kp} uk ON uk.upid = u.id
          LEFT JOIN {flwcupkp_object_map} kpom ON kpom.targettype = 'kp' AND kpom.targetid = uk.kpid
          LEFT JOIN {flwcupkp_object} kpo ON kpo.id = kpom.objectid
          LEFT JOIN {flwcupkp_object_map} upom ON upom.targettype = 'up' AND upom.targetid = u.id
          LEFT JOIN {flwcupkp_object} upo ON upo.id = upom.objectid
              WHERE (kpo.unitcode = :unitcode1 OR upo.unitcode = :unitcode2)
                AND (:courseid = 0 OR kpo.courseid = :courseid1 OR kpo.courseid IS NULL
                 OR  upo.courseid = :courseid2 OR upo.courseid IS NULL)
           ORDER BY u.externalid ASC",
            [
                'unitcode1' => $unitcode,
                'unitcode2' => $unitcode,
                'courseid' => $courseid,
                'courseid1' => $courseid,
                'courseid2' => $courseid,
            ]
        );
        foreach ($ups as $up) {
            $targets['up:' . (int)$up->targetid] = $up;
        }

        $competencies = $DB->get_records_sql(
            "SELECT DISTINCT c.id AS targetid,
                    'competency' AS targettype,
                    c.externalid,
                    c.title,
                    c.cefr,
                    c.domain,
                    c.scope
               FROM {flwcupkp_comp} c
          LEFT JOIN {flwcupkp_comp_up} cu ON cu.competencyid = c.id
          LEFT JOIN {flwcupkp_up_kp} uk ON uk.upid = cu.upid
          LEFT JOIN {flwcupkp_object_map} kpom ON kpom.targettype = 'kp' AND kpom.targetid = uk.kpid
          LEFT JOIN {flwcupkp_object} kpo ON kpo.id = kpom.objectid
          LEFT JOIN {flwcupkp_object_map} upom ON upom.targettype = 'up' AND upom.targetid = cu.upid
          LEFT JOIN {flwcupkp_object} upo ON upo.id = upom.objectid
          LEFT JOIN {flwcupkp_object_map} compom ON compom.targettype = 'competency' AND compom.targetid = c.id
          LEFT JOIN {flwcupkp_object} compo ON compo.id = compom.objectid
              WHERE (kpo.unitcode = :unitcode1 OR upo.unitcode = :unitcode2 OR compo.unitcode = :unitcode3)
                AND (:courseid = 0 OR kpo.courseid = :courseid1 OR kpo.courseid IS NULL
                 OR  upo.courseid = :courseid2 OR upo.courseid IS NULL
                 OR  compo.courseid = :courseid3 OR compo.courseid IS NULL)
           ORDER BY c.externalid ASC",
            [
                'unitcode1' => $unitcode,
                'unitcode2' => $unitcode,
                'unitcode3' => $unitcode,
                'courseid' => $courseid,
                'courseid1' => $courseid,
                'courseid2' => $courseid,
                'courseid3' => $courseid,
            ]
        );
        foreach ($competencies as $competency) {
            $targets['competency:' . (int)$competency->targetid] = $competency;
        }

        uasort($targets, static function($left, $right): int {
            $typeorder = ['competency' => 0, 'up' => 1];
            $lefttype = $typeorder[(string)$left->targettype] ?? 9;
            $righttype = $typeorder[(string)$right->targettype] ?? 9;
            if ($lefttype !== $righttype) {
                return $lefttype <=> $righttype;
            }
            return strcmp((string)$left->externalid, (string)$right->externalid);
        });

        return $targets;
    }

    /**
     * Fetch learner state.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function state_for(int $userid, string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], '*', IGNORE_MISSING);
        return $state ?: null;
    }

    /**
     * Fetch latest direct evidence for a learner target.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function latest_target_evidence(int $userid, string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $records = $DB->get_records('flwcupkp_evidence', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], 'timecreated DESC, id DESC', '*', 0, 1);

        $evidence = reset($records);
        return $evidence ?: null;
    }

    /**
     * Convert parent target state into a display row.
     *
     * @param \stdClass $learner
     * @param \stdClass $target
     * @param \stdClass|null $state
     * @param \stdClass|null $evidence
     * @return array
     */
    private static function parent_row(\stdClass $learner, \stdClass $target, ?\stdClass $state,
            ?\stdClass $evidence): array {
        return [
            'userid' => (int)$learner->id,
            'learner' => fullname($learner),
            'targettype' => (string)$target->targettype,
            'targetid' => (int)$target->targetid,
            'externalid' => (string)$target->externalid,
            'title' => (string)$target->title,
            'cefr' => (string)($target->cefr ?? ''),
            'domain' => (string)($target->domain ?? $target->languagemode ?? ''),
            'state' => $state->masterystate ?? ((string)$target->targettype === 'up' ? 'not_observed' : 'not_started'),
            'mastery_score' => $state ? (float)$state->masteryscore : null,
            'confidence' => $state ? (float)$state->confidence : null,
            'evidence_count' => $state ? (int)$state->evidencecount : 0,
            'manual_override' => $state ? (int)$state->manualoverride : 0,
            'override_reason' => $state->overridereason ?? '',
            'ruleversion' => $state->ruleversion ?? '',
            'evidence_id' => $evidence ? (int)$evidence->id : null,
            'evidence_score' => $evidence ? (float)$evidence->normalizedscore : null,
            'evidence_strength' => $evidence->evidencestrength ?? '',
            'evidence_time' => $evidence ? (int)$evidence->timecreated : null,
            'provenance' => $evidence->provenance ?? '',
            'sourceref' => $evidence->sourceref ?? '',
            'verification' => self::latest_parent_verification($learner, $target),
            'explanation' => self::parent_explanation($state, $evidence, (string)$target->targettype),
        ];
    }

    /**
     * Explain parent UP/competency states.
     *
     * @param \stdClass|null $state
     * @param \stdClass|null $evidence
     * @param string $targettype
     * @return string
     */
    private static function parent_explanation(?\stdClass $state, ?\stdClass $evidence, string $targettype): string {
        if (!$state || (int)$state->evidencecount <= 0) {
            return $targettype === 'up' ?
                'No mapped child or direct UP evidence has contributed to this Use Point yet.' :
                'No mapped child or direct competency evidence has contributed to this competency yet.';
        }

        $prefix = 'State "' . $state->masterystate . '" is based on ' . (int)$state->evidencecount .
            ' contributing evidence event(s)';
        if ($evidence) {
            $prefix .= ', including direct evidence score ' . format_float((float)$evidence->normalizedscore, 2) .
                ' from ' . ($evidence->sourceref ?: $evidence->provenance ?: 'mapped evidence');
        } else {
            $prefix .= ' rolled up from mapped child states';
        }

        return $prefix . '.';
    }

    /**
     * Latest teacher verification audit for a learner parent UP/competency row.
     *
     * @param \stdClass $learner
     * @param \stdClass $target
     * @return array|null
     */
    private static function latest_parent_verification(\stdClass $learner, \stdClass $target): ?array {
        global $DB;

        $records = $DB->get_records('flwcupkp_audit', [
            'targettype' => (string)$target->targettype,
            'targetid' => (int)$target->targetid,
        ], 'timecreated DESC, id DESC', '*', 0, 30);

        foreach ($records as $record) {
            if (!in_array($record->action, [
                'teacher_parent_state_confirmed',
                'teacher_parent_state_overridden',
                'teacher_parent_override_cleared',
            ], true)) {
                continue;
            }
            $details = json_decode((string)$record->detailsjson, true);
            if (!is_array($details) || (int)($details['userid'] ?? 0) !== (int)$learner->id) {
                continue;
            }

            $teacher = $DB->get_record('user', ['id' => $record->userid], '*', IGNORE_MISSING);
            return [
                'action' => $record->action,
                'timecreated' => (int)$record->timecreated,
                'teacher' => $teacher ? fullname($teacher) : '',
                'details' => $details,
            ];
        }

        return null;
    }

    /**
     * Whether a parent competency row is achieved.
     *
     * @param array $row
     * @return bool
     */
    private static function is_parent_achieved(array $row): bool {
        return in_array($row['state'], self::COMPETENCY_ACHIEVED_STATES, true);
    }

    /**
     * Whether a parent UP row is demonstrated.
     *
     * @param array $row
     * @return bool
     */
    private static function is_parent_demonstrated(array $row): bool {
        return in_array($row['state'], self::UP_DEMONSTRATED_STATES, true);
    }

    /**
     * Distinct values.
     *
     * @param array $records
     * @param string $field
     * @return array
     */
    private static function distinct_values(array $records, string $field): array {
        $values = [];
        foreach ($records as $record) {
            if (isset($record->{$field}) && (string)$record->{$field} !== '') {
                $values[(string)$record->{$field}] = (string)$record->{$field};
            }
        }
        ksort($values);
        return $values;
    }

    /**
     * State options from report rows.
     *
     * @param array $rows
     * @return array
     */
    private static function state_options(array $rows): array {
        $states = [];
        foreach ($rows as $row) {
            if (!empty($row['state'])) {
                $states[(string)$row['state']] = (string)$row['state'];
            }
        }
        ksort($states);
        return $states;
    }
}

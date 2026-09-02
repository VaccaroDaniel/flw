<?php
// Learner dashboard composition service for local_flwhistory H5.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_system;

/**
 * Composes H0-H4 trusted history services into the H5 learner dashboard model.
 */
class dashboard_service {
    /** Dashboard table page size. */
    private const DEFAULT_LIMIT = 10;

    /** Maximum rows requested from H4 services for trend calculations. */
    private const TREND_LIMIT = 100;

    /**
     * Build the dashboard for a web request and enforce learner access.
     *
     * @param int $courseid Course id.
     * @param int $requesteduserid Requested learner id, or 0 for current user.
     * @param array $options Pagination options.
     * @return array
     */
    public static function learner_dashboard_for_request(int $courseid, int $requesteduserid = 0, array $options = []): array {
        $userid = self::require_learner_access($courseid, $requesteduserid);
        return self::learner_dashboard_core($courseid, $userid, $options);
    }

    /**
     * Require access to a learner's dashboard and return the resolved learner id.
     *
     * @param int $courseid Course id.
     * @param int $requesteduserid Requested learner id, or 0 for current user.
     * @return int Resolved learner id.
     */
    public static function require_learner_access(int $courseid, int $requesteduserid = 0): int {
        global $USER;

        $context = context_course::instance($courseid);
        $systemcontext = context_system::instance();
        $currentuserid = isset($USER->id) ? (int)$USER->id : 0;
        $userid = $requesteduserid > 0 ? $requesteduserid : $currentuserid;

        if ($userid <= 0) {
            throw new \moodle_exception('missinglearner', 'local_flwhistory');
        }

        if ($userid === $currentuserid) {
            require_capability('local/flwhistory:viewown', $context);
            return $userid;
        }

        if (has_capability('local/flwhistory:viewall', $systemcontext)) {
            return $userid;
        }

        require_capability('local/flwhistory:viewcourse', $context);
        $learner = self::learner_record($userid);
        if (!is_enrolled($context, $learner, '', true)) {
            throw new \moodle_exception('learnernotincourse', 'local_flwhistory');
        }

        return $userid;
    }

    /**
     * Build the dashboard model from H4 services and trusted H3 grade summaries.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @param array $options Pagination options.
     * @return array
     */
    public static function learner_dashboard_core(int $courseid, int $userid, array $options = []): array {
        $options = self::normalise_options($options);
        $present = history_api_service::present_summary_core($courseid, $userid);
        $journey = history_api_service::learning_journey_core($courseid, $userid);
        $attempts = history_api_service::attempt_history_query(
            $courseid,
            $userid,
            $options['limit'],
            $options['attemptoffset']
        );
        $grades = history_api_service::grade_history_query(
            $courseid,
            $userid,
            $options['limit'],
            $options['gradeoffset'],
            0,
            0,
            0,
            false
        );
        $learning = history_api_service::learning_history_query(
            $courseid,
            $userid,
            $options['limit'],
            $options['historyoffset']
        );
        $recent = history_api_service::recent_activity_query(
            $courseid,
            $userid,
            $options['limit'],
            $options['activityoffset']
        );
        $trendattempts = history_api_service::attempt_history_query($courseid, $userid, self::TREND_LIMIT, 0);
        $trendgrades = history_api_service::grade_history_query($courseid, $userid, self::TREND_LIMIT, 0);

        return [
            'type' => 'LearnerHistoryDashboardCore',
            'userid' => $userid,
            'courseid' => $courseid,
            'learner' => self::learner_identity($userid),
            'present' => $present,
            'journey' => $journey,
            'standard_next_action' => self::standard_next_action($journey),
            'grade_distinctions' => self::grade_distinctions($courseid, $userid, $grades),
            'trend' => self::basic_trend($trendattempts['records'], $trendgrades['records']),
            'attempt_history' => $attempts,
            'grade_history' => $grades,
            'learning_history' => $learning,
            'recent_activity' => $recent,
            'program3_placeholders' => self::program3_placeholders(),
            'pagination' => $options,
            'generatedat' => time(),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Normalize dashboard pagination options.
     *
     * @param array $options Raw options.
     * @return array
     */
    private static function normalise_options(array $options): array {
        return [
            'limit' => self::bounded_int($options['limit'] ?? self::DEFAULT_LIMIT, 1, 100),
            'attemptoffset' => self::bounded_int($options['attemptoffset'] ?? 0, 0, PHP_INT_MAX),
            'gradeoffset' => self::bounded_int($options['gradeoffset'] ?? 0, 0, PHP_INT_MAX),
            'historyoffset' => self::bounded_int($options['historyoffset'] ?? 0, 0, PHP_INT_MAX),
            'activityoffset' => self::bounded_int($options['activityoffset'] ?? 0, 0, PHP_INT_MAX),
        ];
    }

    /**
     * Bound an integer option.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @return int
     */
    private static function bounded_int($value, int $min, int $max): int {
        $value = (int)$value;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }

    /**
     * Build learner identity.
     *
     * @param int $userid User id.
     * @return array
     */
    private static function learner_identity(int $userid): array {
        $user = self::learner_record($userid);
        return [
            'id' => (int)$user->id,
            'fullname' => fullname($user),
            'username' => (string)$user->username,
        ];
    }

    /**
     * Fetch a user record suitable for fullname().
     *
     * @param int $userid User id.
     * @return \stdClass
     */
    private static function learner_record(int $userid): \stdClass {
        global $DB;

        return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }

    /**
     * Build a safe standard next action from the non-adaptive journey order.
     *
     * @param array $journey Learning journey DTO.
     * @return array
     */
    private static function standard_next_action(array $journey): array {
        $items = $journey['items'] ?? [];
        foreach ($items as $item) {
            if (($item['state'] ?? '') === 'current' || ($item['state'] ?? '') === 'inprogress') {
                return self::action_dto('continue_current_unit', 'Continue current unit', $item);
            }
        }
        foreach ($items as $item) {
            if (($item['state'] ?? '') !== 'completed') {
                return self::action_dto('next_standard_available_activity', 'Next standard available activity', $item);
            }
        }
        return [
            'status' => 'insufficient_data',
            'label' => 'No standard activity available',
            'reason' => 'NO_UNCOMPLETED_VISIBLE_COURSE_ACTIVITY',
            'adaptive' => false,
        ];
    }

    /**
     * Build action DTO.
     *
     * @param string $type Action type.
     * @param string $label Label.
     * @param array $item Journey item.
     * @return array
     */
    private static function action_dto(string $type, string $label, array $item): array {
        return [
            'status' => 'available',
            'type' => $type,
            'label' => $label,
            'cmid' => isset($item['cmid']) ? (int)$item['cmid'] : null,
            'modname' => $item['modname'] ?? null,
            'activityname' => $item['name'] ?? null,
            'unitid' => $item['identity']['unitid'] ?? null,
            'reason' => 'STANDARD_COURSE_ORDER_NOT_PERSONALISED_ADAPTIVE',
            'adaptive' => false,
        ];
    }

    /**
     * Build grade distinction facts from trusted H3 summaries and H4 grade history.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @param array $gradehistory H4 grade history DTO.
     * @return array
     */
    private static function grade_distinctions(int $courseid, int $userid, array $gradehistory): array {
        global $DB;

        $records = $DB->get_records('flwhist_grade_summary', [
            'courseid' => $courseid,
            'userid' => $userid,
            'reconciliationstatus' => 'current',
        ], 'timemodified DESC, id DESC', '*', 0, self::TREND_LIMIT);

        $summaries = array_values(array_map([self::class, 'grade_summary_dto'], array_values($records)));

        return [
            'type' => 'GradeDistinctions',
            'status' => $summaries ? 'available' : 'insufficient_data',
            'latest_attempt' => self::latest_fact($records, 'latestattemptscore', 'latestattempttime', 100.0),
            'best_attempt' => self::best_fact($records, 'bestattemptscore', 'bestattempttime', 100.0),
            'official_moodle_grade' => self::latest_fact($records, 'officialfinalgrade', 'officialgradetime', 1.0),
            'latest_grade_version' => self::latest_grade_version_fact($gradehistory['records'] ?? []),
            'summaries' => $summaries,
        ];
    }

    /**
     * Build a safe grade summary DTO.
     *
     * @param \stdClass $record Grade summary row.
     * @return array
     */
    private static function grade_summary_dto(\stdClass $record): array {
        return [
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'gradeitemid' => isset($record->gradeitemid) ? (int)$record->gradeitemid : null,
            'itemmodule' => $record->itemmodule ?? null,
            'latestattemptscore' => self::float_or_null($record->latestattemptscore ?? null),
            'latestattempttime' => isset($record->latestattempttime) ? (int)$record->latestattempttime : null,
            'bestattemptscore' => self::float_or_null($record->bestattemptscore ?? null),
            'bestattempttime' => isset($record->bestattempttime) ? (int)$record->bestattempttime : null,
            'officialfinalgrade' => self::float_or_null($record->officialfinalgrade ?? null),
            'officialgradetime' => isset($record->officialgradetime) ? (int)$record->officialgradetime : null,
            'latestgradeversionid' => isset($record->latestgradeversionid) ? (int)$record->latestgradeversionid : null,
            'normpolicyversion' => (string)$record->normpolicyversion,
        ];
    }

    /**
     * Build latest fact by time field.
     *
     * @param array $records Grade summary records.
     * @param string $scorefield Score field.
     * @param string $timefield Time field.
     * @param float $multiplier Score multiplier.
     * @return array
     */
    private static function latest_fact(array $records, string $scorefield, string $timefield, float $multiplier): array {
        $best = null;
        foreach ($records as $record) {
            $score = self::float_or_null($record->{$scorefield} ?? null);
            if ($score === null) {
                continue;
            }
            $time = isset($record->{$timefield}) ? (int)$record->{$timefield} : 0;
            if ($best === null || $time > $best['time']) {
                $best = [
                    'status' => 'available',
                    'value' => round($score * $multiplier, 2),
                    'rawvalue' => $score,
                    'time' => $time > 0 ? $time : null,
                    'gradeitemid' => isset($record->gradeitemid) ? (int)$record->gradeitemid : null,
                    'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
                ];
            }
        }
        return $best ?? self::insufficient_fact('NO_CURRENT_GRADE_SUMMARY_VALUE');
    }

    /**
     * Build best fact by score field.
     *
     * @param array $records Grade summary records.
     * @param string $scorefield Score field.
     * @param string $timefield Time field.
     * @param float $multiplier Score multiplier.
     * @return array
     */
    private static function best_fact(array $records, string $scorefield, string $timefield, float $multiplier): array {
        $best = null;
        foreach ($records as $record) {
            $score = self::float_or_null($record->{$scorefield} ?? null);
            if ($score === null) {
                continue;
            }
            $time = isset($record->{$timefield}) ? (int)$record->{$timefield} : 0;
            if ($best === null || $score > $best['rawvalue']) {
                $best = [
                    'status' => 'available',
                    'value' => round($score * $multiplier, 2),
                    'rawvalue' => $score,
                    'time' => $time > 0 ? $time : null,
                    'gradeitemid' => isset($record->gradeitemid) ? (int)$record->gradeitemid : null,
                    'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
                ];
            }
        }
        return $best ?? self::insufficient_fact('NO_CURRENT_GRADE_SUMMARY_VALUE');
    }

    /**
     * Build latest H4 grade version fact.
     *
     * @param array $records H4 grade history records.
     * @return array
     */
    private static function latest_grade_version_fact(array $records): array {
        foreach ($records as $record) {
            if (array_key_exists('finalgrade', $record) && $record['finalgrade'] !== null) {
                return [
                    'status' => 'available',
                    'value' => round((float)$record['finalgrade'], 2),
                    'time' => isset($record['gradetime']) ? (int)$record['gradetime'] : null,
                    'action' => $record['action'] ?? 'recorded',
                    'gradeitemid' => isset($record['gradeitemid']) ? (int)$record['gradeitemid'] : null,
                    'cmid' => isset($record['cmid']) ? (int)$record['cmid'] : null,
                ];
            }
        }
        return self::insufficient_fact('NO_GRADE_VERSION_HISTORY');
    }

    /**
     * Build basic non-mastery trend DTO.
     *
     * @param array $attemptrecords H4 attempt records.
     * @param array $graderecords H4 grade history records.
     * @return array
     */
    private static function basic_trend(array $attemptrecords, array $graderecords): array {
        return [
            'type' => 'BasicEvidenceTrend',
            'attempt_score' => self::trend_from_records($attemptrecords, 'scaledscore', 'timefinish', 100.0, 'attempt'),
            'official_grade' => self::trend_from_records($graderecords, 'finalgrade', 'gradetime', 1.0, 'grade'),
            'skill' => [
                'status' => 'insufficient_data',
                'reason' => 'NO_H4_SKILL_TAXONOMY_EVIDENCE',
                'mastery_based' => false,
            ],
        ];
    }

    /**
     * Build a basic trend from safe DTO records.
     *
     * @param array $records DTO records ordered newest first.
     * @param string $valuefield Value field.
     * @param string $timefield Time field.
     * @param float $multiplier Value multiplier.
     * @param string $labelprefix Point label prefix.
     * @return array
     */
    private static function trend_from_records(
        array $records,
        string $valuefield,
        string $timefield,
        float $multiplier,
        string $labelprefix
    ): array {
        $points = [];
        foreach (array_reverse($records) as $record) {
            if (!array_key_exists($valuefield, $record) || $record[$valuefield] === null) {
                continue;
            }
            $points[] = [
                'label' => $labelprefix . ' ' . (string)(count($points) + 1),
                'value' => round(((float)$record[$valuefield]) * $multiplier, 2),
                'time' => isset($record[$timefield]) ? (int)$record[$timefield] : null,
            ];
        }
        if (count($points) < 2) {
            return [
                'status' => 'insufficient_data',
                'reason' => 'NEED_AT_LEAST_TWO_EVIDENCE_POINTS',
                'points' => $points,
            ];
        }
        $points = array_slice($points, -12);
        $first = reset($points);
        $last = end($points);
        $delta = round($last['value'] - $first['value'], 2);
        return [
            'status' => 'available',
            'points' => $points,
            'first' => $first['value'],
            'latest' => $last['value'],
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'mastery_based' => false,
        ];
    }

    /**
     * Program 3 reserved placeholders.
     *
     * @return array
     */
    private static function program3_placeholders(): array {
        $reason = 'PROGRAM_3_OWNS_CUPKP_AND_ADAPTIVE_LOGIC';
        return [
            [
                'key' => 'cupkp_mastery',
                'title' => 'C-UP-KP Mastery',
                'status' => 'not_available_yet',
                'reason' => $reason,
            ],
            [
                'key' => 'adaptive_next',
                'title' => 'Adaptive Next',
                'status' => 'not_available_yet',
                'reason' => $reason,
            ],
            [
                'key' => 'goal_readiness',
                'title' => 'Goal Readiness',
                'status' => 'not_available_yet',
                'reason' => $reason,
            ],
            [
                'key' => 'projected_future_roadmap',
                'title' => 'Projected Future Roadmap',
                'status' => 'not_available_yet',
                'reason' => $reason,
            ],
            [
                'key' => 'mastery_based_skill_progress',
                'title' => 'Mastery-based Skill Progress',
                'status' => 'not_available_yet',
                'reason' => $reason,
            ],
        ];
    }

    /**
     * Insufficient data fact.
     *
     * @param string $reason Reason code.
     * @return array
     */
    private static function insufficient_fact(string $reason): array {
        return [
            'status' => 'insufficient_data',
            'value' => null,
            'time' => null,
            'reason' => $reason,
        ];
    }

    /**
     * Convert a value to float or null.
     *
     * @param mixed $value Raw value.
     * @return float|null
     */
    private static function float_or_null($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }
}

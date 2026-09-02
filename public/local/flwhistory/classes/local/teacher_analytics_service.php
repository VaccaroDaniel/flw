<?php
// Teacher history analytics service for local_flwhistory H6.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

use context_course;
use context_system;

/**
 * Builds descriptive teacher analytics from trusted history facts.
 */
class teacher_analytics_service {
    /** Default learner rows per page. */
    private const DEFAULT_LIMIT = 25;

    /** Maximum learner rows per page. */
    private const MAX_LIMIT = 100;

    /** Recent activity window used for inactive/stalled signals. */
    private const INACTIVE_WINDOW = 1209600; // 14 days.

    /** Repeated unsuccessful attempt threshold. */
    private const UNSUCCESSFUL_SCORE_THRESHOLD = 0.60;

    /** Bounded detail rows fetched for page-level trend and audit maps. */
    private const MAX_DETAIL_RECORDS = 2000;

    /** Recent audit rows shown on the page. */
    private const AUDIT_LIMIT = 25;

    /**
     * Build the teacher dashboard for a web request.
     *
     * @param int $courseid Course id.
     * @param array $options Request options.
     * @return array
     */
    public static function teacher_dashboard_for_request(int $courseid, array $options = []): array {
        $access = self::require_teacher_access($courseid);
        return self::teacher_dashboard_core($courseid, $options, $access['canaudit']);
    }

    /**
     * Require teacher analytics access.
     *
     * @param int $courseid Course id.
     * @return array Access flags.
     */
    public static function require_teacher_access(int $courseid): array {
        $context = context_course::instance($courseid);
        $systemcontext = context_system::instance();

        if (!has_capability('local/flwhistory:viewall', $systemcontext)) {
            require_capability('local/flwhistory:viewcourse', $context);
        }

        return [
            'canviewcourse' => true,
            'canaudit' => has_capability('local/flwhistory:viewgradeaudit', $context)
                || has_capability('local/flwhistory:viewall', $systemcontext),
        ];
    }

    /**
     * Build teacher analytics without enforcing access.
     *
     * @param int $courseid Course id.
     * @param array $options Pagination options.
     * @param bool $includeaudit Include grade audit panel.
     * @return array
     */
    public static function teacher_dashboard_core(int $courseid, array $options = [], bool $includeaudit = false): array {
        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        $options = self::normalise_options($options);
        $inactivecutoff = time() - self::INACTIVE_WINDOW;

        $learnercount = self::learner_count($courseid, $context->id);
        $learners = self::course_learners($courseid, $context->id, $options['limit'], $options['offset']);
        $learnerids = array_map(fn(\stdClass $user): int => (int)$user->id, array_values($learners));
        $trackedcmids = self::tracked_course_module_ids($courseid);

        $classsummary = self::class_summary($courseid, $context->id, $learnercount, $trackedcmids, $inactivecutoff);
        $maps = self::page_maps($courseid, $learnerids, $trackedcmids);
        $rows = self::learner_rows($courseid, $learners, $trackedcmids, $maps, $inactivecutoff);

        return [
            'type' => 'TeacherHistoryAnalyticsCore',
            'courseid' => $courseid,
            'course' => [
                'id' => (int)$course->id,
                'fullname' => (string)$course->fullname,
                'shortname' => (string)$course->shortname,
            ],
            'pagination' => [
                'limit' => $options['limit'],
                'offset' => $options['offset'],
                'total' => $learnercount,
                'hasmore' => ($options['offset'] + $options['limit']) < $learnercount,
            ],
            'class_summary' => $classsummary,
            'learners' => $rows,
            'attention_definitions' => self::attention_definitions(),
            'checkpoint_placement_summary' => self::checkpoint_placement_summary($rows),
            'grade_audit' => self::grade_audit($courseid, $includeaudit),
            'program3_boundary' => [
                'status' => 'not_in_scope',
                'reason' => 'PROGRAM_3_OWNS_ADAPTIVE_POLICY_AND_CUPKP_MASTERY',
            ],
            'generatedat' => time(),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Normalize options.
     *
     * @param array $options Raw options.
     * @return array
     */
    private static function normalise_options(array $options): array {
        return [
            'limit' => self::bounded_int($options['limit'] ?? self::DEFAULT_LIMIT, 1, self::MAX_LIMIT),
            'offset' => self::bounded_int($options['offset'] ?? 0, 0, PHP_INT_MAX),
        ];
    }

    /**
     * Bound an integer.
     *
     * @param mixed $value Value.
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
     * Build SQL that resolves active student-role learners in a course.
     *
     * @param int $courseid Course id.
     * @param int $contextid Course context id.
     * @param string $prefix Parameter prefix.
     * @return array SQL and parameters.
     */
    private static function learner_id_sql(int $courseid, int $contextid, string $prefix): array {
        $sql = "SELECT DISTINCT u.id
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE e.courseid = :{$prefix}courseid
                   AND ra.contextid = :{$prefix}contextid
                   AND r.shortname = :{$prefix}role
                   AND ue.status = 0
                   AND e.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0";
        return [$sql, [
            $prefix . 'courseid' => $courseid,
            $prefix . 'contextid' => $contextid,
            $prefix . 'role' => 'student',
        ]];
    }

    /**
     * Count course learners.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @return int
     */
    private static function learner_count(int $courseid, int $contextid): int {
        global $DB;

        [$sql, $params] = self::learner_id_sql($courseid, $contextid, 'lc');
        return (int)$DB->count_records_sql("SELECT COUNT(1) FROM ({$sql}) learners", $params);
    }

    /**
     * Fetch paged course learners.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @return array
     */
    private static function course_learners(int $courseid, int $contextid, int $limit, int $offset): array {
        global $DB;

        [$idsql, $params] = self::learner_id_sql($courseid, $contextid, 'lr');
        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email, u.idnumber
                  FROM {user} u
                  JOIN ({$idsql}) learners ON learners.id = u.id
              ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Completion-tracked course module ids.
     *
     * @param int $courseid Course id.
     * @return array
     */
    private static function tracked_course_module_ids(int $courseid): array {
        global $DB;

        $records = $DB->get_records_select('course_modules',
            'course = :courseid AND deletioninprogress = 0 AND completion <> :none',
            ['courseid' => $courseid, 'none' => COMPLETION_TRACKING_NONE],
            '',
            'id'
        );
        return array_map('intval', array_keys($records));
    }

    /**
     * Build class-level summary with batched SQL.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @param int $learnercount Learner count.
     * @param array $trackedcmids Completion-tracked cmids.
     * @param int $inactivecutoff Inactive cutoff timestamp.
     * @return array
     */
    private static function class_summary(
        int $courseid,
        int $contextid,
        int $learnercount,
        array $trackedcmids,
        int $inactivecutoff
    ): array {
        global $DB;

        $trackedtotal = count($trackedcmids);
        $completed = 0;
        if ($trackedcmids) {
            [$insql, $inparams] = $DB->get_in_or_equal($trackedcmids, SQL_PARAMS_NAMED, 'classcm');
            [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'cc');
            $completed = (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {course_modules_completion} cmc
                   JOIN ({$learnersql}) learners ON learners.id = cmc.userid
                  WHERE cmc.coursemoduleid {$insql}
                    AND cmc.completionstate > 0",
                array_merge($inparams, $learnerparams)
            );
        }

        $possible = $learnercount * $trackedtotal;
        return [
            'type' => 'ClassHistorySummary',
            'learnercount' => $learnercount,
            'completion' => [
                'trackedactivities' => $trackedtotal,
                'completed' => $completed,
                'possible' => $possible,
                'percent' => $possible > 0 ? round(($completed / $possible) * 100, 2) : null,
                'status' => $possible > 0 ? 'available' : 'insufficient_data',
            ],
            'activity' => self::class_activity_summary($courseid, $contextid, $inactivecutoff),
            'official_grade' => self::class_grade_summary($courseid, $contextid),
            'attempts' => self::class_attempt_summary($courseid, $contextid),
            'attention_counts' => self::class_attention_counts($courseid, $contextid, $trackedcmids, $inactivecutoff),
        ];
    }

    /**
     * Build class activity summary.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @param int $inactivecutoff Cutoff.
     * @return array
     */
    private static function class_activity_summary(int $courseid, int $contextid, int $inactivecutoff): array {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'as');
        $sql = "SELECT COUNT(1) AS learnercount,
                       SUM(CASE WHEN latest.lastactivity >= :inactivecutoff THEN 1 ELSE 0 END) AS activecount,
                       SUM(CASE WHEN latest.lastactivity IS NULL THEN 1 ELSE 0 END) AS missingcount,
                       MAX(latest.lastactivity) AS latestactivity
                  FROM ({$learnersql}) learners
             LEFT JOIN (
                       SELECT userid, MAX(eventtime) AS lastactivity
                         FROM {flwhist_source_event}
                        WHERE courseid = :activitycourseid
                     GROUP BY userid
                       ) latest ON latest.userid = learners.id";
        $record = $DB->get_record_sql($sql, array_merge($learnerparams, [
            'inactivecutoff' => $inactivecutoff,
            'activitycourseid' => $courseid,
        ]));
        $learnercount = (int)($record->learnercount ?? 0);
        $activecount = (int)($record->activecount ?? 0);
        return [
            'status' => $learnercount > 0 ? 'available' : 'insufficient_data',
            'activecount' => $activecount,
            'inactivecount' => max(0, $learnercount - $activecount),
            'missingactivitycount' => (int)($record->missingcount ?? 0),
            'latestactivity' => isset($record->latestactivity) ? (int)$record->latestactivity : null,
            'windowdays' => 14,
        ];
    }

    /**
     * Build class grade summary.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @return array
     */
    private static function class_grade_summary(int $courseid, int $contextid): array {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'gs');
        $sql = "SELECT COUNT(gs.officialfinalgrade) AS gradecount,
                       AVG(gs.officialfinalgrade) AS averagegrade,
                       MAX(gs.officialgradetime) AS latestgrade
                  FROM {flwhist_grade_summary} gs
                  JOIN ({$learnersql}) learners ON learners.id = gs.userid
                 WHERE gs.courseid = :gradecourseid
                   AND gs.reconciliationstatus = :gradestatus
                   AND gs.officialfinalgrade IS NOT NULL";
        $record = $DB->get_record_sql($sql, array_merge($learnerparams, [
            'gradecourseid' => $courseid,
            'gradestatus' => 'current',
        ]));
        $count = (int)($record->gradecount ?? 0);
        return [
            'status' => $count > 0 ? 'available' : 'insufficient_data',
            'average' => $count > 0 ? round((float)$record->averagegrade, 2) : null,
            'count' => $count,
            'latesttime' => isset($record->latestgrade) ? (int)$record->latestgrade : null,
        ];
    }

    /**
     * Build class attempt summary.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @return array
     */
    private static function class_attempt_summary(int $courseid, int $contextid): array {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'ats');
        $sql = "SELECT COUNT(a.id) AS attemptcount,
                       COUNT(a.scaledscore) AS scoredcount,
                       AVG(a.scaledscore) AS averagescore,
                       MAX(a.timefinish) AS latestattempt
                  FROM {flwhist_attempt} a
                  JOIN ({$learnersql}) learners ON learners.id = a.userid
                 WHERE a.courseid = :attemptcourseid";
        $record = $DB->get_record_sql($sql, array_merge($learnerparams, [
            'attemptcourseid' => $courseid,
        ]));
        $attemptcount = (int)($record->attemptcount ?? 0);
        $scoredcount = (int)($record->scoredcount ?? 0);
        return [
            'status' => $attemptcount > 0 ? 'available' : 'insufficient_data',
            'attemptcount' => $attemptcount,
            'scoredcount' => $scoredcount,
            'averagescore' => $scoredcount > 0 ? round((float)$record->averagescore, 5) : null,
            'latestattempt' => isset($record->latestattempt) ? (int)$record->latestattempt : null,
        ];
    }

    /**
     * Build class-level attention signal counts.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @param array $trackedcmids Tracked cmids.
     * @param int $inactivecutoff Inactive cutoff.
     * @return array
     */
    private static function class_attention_counts(int $courseid, int $contextid, array $trackedcmids, int $inactivecutoff): array {
        return [
            'inactive' => self::count_inactive_learners($courseid, $contextid, $inactivecutoff),
            'repeated_unsuccessful_attempts' => self::count_repeated_unsuccessful_attempts($courseid, $contextid),
            'grade_decline_with_enough_comparable_data' => self::count_grade_declines($courseid, $contextid),
            'stalled_completion' => self::count_stalled_completion($courseid, $contextid, $trackedcmids, $inactivecutoff),
            'missing_activity_evidence' => self::count_missing_activity_evidence($courseid, $contextid),
        ];
    }

    /**
     * Count inactive learners.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @param int $inactivecutoff Cutoff.
     * @return int
     */
    private static function count_inactive_learners(int $courseid, int $contextid, int $inactivecutoff): int {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'ial');
        $sql = "SELECT COUNT(1)
                  FROM ({$learnersql}) learners
                  JOIN (
                       SELECT userid, MAX(eventtime) AS lastactivity
                         FROM {flwhist_source_event}
                        WHERE courseid = :iacourseid
                     GROUP BY userid
                       ) latest ON latest.userid = learners.id
                 WHERE latest.lastactivity < :inactivecutoff";
        return (int)$DB->count_records_sql($sql, array_merge($learnerparams, [
            'iacourseid' => $courseid,
            'inactivecutoff' => $inactivecutoff,
        ]));
    }

    /**
     * Count learners with repeated unsuccessful attempts.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @return int
     */
    private static function count_repeated_unsuccessful_attempts(int $courseid, int $contextid): int {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'rul');
        $sql = "SELECT COUNT(1)
                  FROM (
                        SELECT a.userid
                          FROM {flwhist_attempt} a
                          JOIN ({$learnersql}) learners ON learners.id = a.userid
                         WHERE a.courseid = :rucourseid
                           AND a.scaledscore IS NOT NULL
                           AND a.scaledscore < :threshold
                      GROUP BY a.userid
                        HAVING COUNT(1) >= 2
                       ) repeated";
        return (int)$DB->count_records_sql($sql, array_merge($learnerparams, [
            'rucourseid' => $courseid,
            'threshold' => self::UNSUCCESSFUL_SCORE_THRESHOLD,
        ]));
    }

    /**
     * Count learners with comparable grade declines.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @return int
     */
    private static function count_grade_declines(int $courseid, int $contextid): int {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'gdl');
        $sql = "SELECT COUNT(1)
                  FROM (
                        SELECT gv.userid
                          FROM {flwhist_grade_version} gv
                          JOIN ({$learnersql}) learners ON learners.id = gv.userid
                         WHERE gv.courseid = :gdcourseid
                           AND gv.finalgrade IS NOT NULL
                      GROUP BY gv.userid
                        HAVING COUNT(1) >= 2
                           AND SUM(CASE WHEN gv.previousgrade IS NOT NULL AND gv.finalgrade < gv.previousgrade THEN 1 ELSE 0 END) > 0
                       ) declined";
        return (int)$DB->count_records_sql($sql, array_merge($learnerparams, [
            'gdcourseid' => $courseid,
        ]));
    }

    /**
     * Count learners with stalled completion.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @param array $trackedcmids Tracked cmids.
     * @param int $inactivecutoff Cutoff.
     * @return int
     */
    private static function count_stalled_completion(
        int $courseid,
        int $contextid,
        array $trackedcmids,
        int $inactivecutoff
    ): int {
        global $DB;

        if (!$trackedcmids) {
            return 0;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($trackedcmids, SQL_PARAMS_NAMED, 'stallcm');
        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'scl');
        $sql = "SELECT COUNT(1)
                  FROM ({$learnersql}) learners
             LEFT JOIN (
                       SELECT userid, COUNT(1) AS completed
                         FROM {course_modules_completion}
                        WHERE coursemoduleid {$insql}
                          AND completionstate > 0
                     GROUP BY userid
                       ) completion ON completion.userid = learners.id
             LEFT JOIN (
                       SELECT userid, MAX(eventtime) AS lastactivity
                         FROM {flwhist_source_event}
                        WHERE courseid = :sccourseid
                     GROUP BY userid
                       ) activity ON activity.userid = learners.id
                 WHERE COALESCE(completion.completed, 0) < :trackedtotal
                   AND activity.lastactivity IS NOT NULL
                   AND activity.lastactivity < :inactivecutoff";
        return (int)$DB->count_records_sql($sql, array_merge($inparams, $learnerparams, [
            'sccourseid' => $courseid,
            'trackedtotal' => count($trackedcmids),
            'inactivecutoff' => $inactivecutoff,
        ]));
    }

    /**
     * Count learners without activity or attempt evidence.
     *
     * @param int $courseid Course id.
     * @param int $contextid Context id.
     * @return int
     */
    private static function count_missing_activity_evidence(int $courseid, int $contextid): int {
        global $DB;

        [$learnersql, $learnerparams] = self::learner_id_sql($courseid, $contextid, 'ma');
        $sql = "SELECT COUNT(1)
                  FROM ({$learnersql}) learners
             LEFT JOIN (
                       SELECT userid, COUNT(1) AS eventcount
                         FROM {flwhist_source_event}
                        WHERE courseid = :maeventcourseid
                     GROUP BY userid
                       ) events ON events.userid = learners.id
             LEFT JOIN (
                       SELECT userid, COUNT(1) AS attemptcount
                         FROM {flwhist_attempt}
                        WHERE courseid = :maattemptcourseid
                     GROUP BY userid
                       ) attempts ON attempts.userid = learners.id
                 WHERE events.eventcount IS NULL
                   AND attempts.attemptcount IS NULL";
        return (int)$DB->count_records_sql($sql, array_merge($learnerparams, [
            'maeventcourseid' => $courseid,
            'maattemptcourseid' => $courseid,
        ]));
    }

    /**
     * Build page-level maps.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @param array $trackedcmids Tracked cmids.
     * @return array
     */
    private static function page_maps(int $courseid, array $learnerids, array $trackedcmids): array {
        return [
            'completion' => self::completion_map($learnerids, $trackedcmids),
            'activity' => self::activity_map($courseid, $learnerids),
            'attempts' => self::attempt_map($courseid, $learnerids),
            'grades' => self::grade_summary_map($courseid, $learnerids),
            'gradeversions' => self::grade_version_map($courseid, $learnerids),
            'checkpoint' => self::checkpoint_map($courseid, $learnerids),
            'placement' => self::placement_map($courseid, $learnerids),
        ];
    }

    /**
     * Build learner rows.
     *
     * @param int $courseid Course id.
     * @param array $learners Learner records.
     * @param array $trackedcmids Tracked cmids.
     * @param array $maps Page maps.
     * @param int $inactivecutoff Inactive cutoff.
     * @return array
     */
    private static function learner_rows(
        int $courseid,
        array $learners,
        array $trackedcmids,
        array $maps,
        int $inactivecutoff
    ): array {
        $rows = [];
        $trackedtotal = count($trackedcmids);
        foreach ($learners as $learner) {
            $userid = (int)$learner->id;
            $completion = self::completion_dto($maps['completion'][$userid] ?? 0, $trackedtotal);
            $activity = $maps['activity'][$userid] ?? self::missing_activity_dto();
            $attempt = $maps['attempts'][$userid] ?? self::empty_attempt_dto();
            $grades = $maps['grades'][$userid] ?? self::empty_grade_dto();
            $gradeversions = $maps['gradeversions'][$userid] ?? self::empty_grade_version_dto();
            $checkpoint = $maps['checkpoint'][$userid] ?? self::empty_checkpoint_dto();
            $placement = $maps['placement'][$userid] ?? self::empty_placement_dto();

            $rows[] = [
                'userid' => $userid,
                'learner' => [
                    'fullname' => fullname($learner),
                    'username' => (string)$learner->username,
                    'idnumber' => $learner->idnumber ?? '',
                ],
                'completion' => $completion,
                'last_meaningful_activity' => $activity,
                'official_grade_summary' => $grades,
                'attempt_trend' => $attempt,
                'checkpoint_history' => $checkpoint,
                'placement_history' => $placement,
                'grade_history' => $gradeversions,
                'attention_signals' => self::attention_signals($completion, $activity, $attempt, $gradeversions, $inactivecutoff),
                'drilldownurl' => (new \moodle_url('/local/flwhistory/dashboard.php', [
                    'courseid' => $courseid,
                    'userid' => $userid,
                ]))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * Completion map for page learners.
     *
     * @param array $learnerids Learner ids.
     * @param array $trackedcmids Tracked cmids.
     * @return array
     */
    private static function completion_map(array $learnerids, array $trackedcmids): array {
        global $DB;

        if (!$learnerids || !$trackedcmids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'compuser');
        [$cminsql, $cmparams] = $DB->get_in_or_equal($trackedcmids, SQL_PARAMS_NAMED, 'compcm');
        $records = $DB->get_records_sql(
            "SELECT userid AS id, userid, COUNT(1) AS completed
               FROM {course_modules_completion}
              WHERE userid {$userinsql}
                AND coursemoduleid {$cminsql}
                AND completionstate > 0
           GROUP BY userid",
            array_merge($userparams, $cmparams)
        );
        return self::count_map($records, 'completed');
    }

    /**
     * Activity map for page learners.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @return array
     */
    private static function activity_map(int $courseid, array $learnerids): array {
        global $DB;

        if (!$learnerids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'actuser');
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_source_event}
              WHERE courseid = :courseid
                AND userid {$userinsql}
           ORDER BY userid ASC, eventtime DESC, id DESC",
            array_merge($userparams, ['courseid' => $courseid]),
            0,
            self::MAX_DETAIL_RECORDS
        );
        $map = [];
        $counts = [];
        foreach ($records as $record) {
            $userid = (int)$record->userid;
            $counts[$userid] = ($counts[$userid] ?? 0) + 1;
            if (!isset($map[$userid])) {
                $map[$userid] = [
                    'status' => 'available',
                    'eventtime' => (int)$record->eventtime,
                    'sourcefamily' => (string)$record->sourcefamily,
                    'eventtype' => (string)$record->eventtype,
                    'unitid' => $record->unitid ?? null,
                    'activityid' => $record->activityid ?? null,
                    'evidencecount' => 0,
                ];
            }
        }
        foreach ($counts as $userid => $count) {
            if (isset($map[$userid])) {
                $map[$userid]['evidencecount'] = $count;
            }
        }
        return $map;
    }

    /**
     * Attempt map for page learners.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @return array
     */
    private static function attempt_map(int $courseid, array $learnerids): array {
        global $DB;

        if (!$learnerids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'attuser');
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_attempt}
              WHERE courseid = :courseid
                AND userid {$userinsql}
           ORDER BY userid ASC, timefinish DESC, id DESC",
            array_merge($userparams, ['courseid' => $courseid]),
            0,
            self::MAX_DETAIL_RECORDS
        );
        $map = [];
        foreach ($records as $record) {
            $userid = (int)$record->userid;
            if (!isset($map[$userid])) {
                $map[$userid] = [
                    'status' => 'available',
                    'attemptcount' => 0,
                    'unsuccessfulcount' => 0,
                    'latestscore' => null,
                    'latesttime' => null,
                    'bestscore' => null,
                    'besttime' => null,
                    'trend' => [
                        'status' => 'insufficient_data',
                        'reason' => 'NEED_AT_LEAST_TWO_ATTEMPTS',
                    ],
                    'recentpoints' => [],
                ];
            }
            $score = self::float_or_null($record->scaledscore ?? null);
            $time = isset($record->timefinish) ? (int)$record->timefinish : null;
            $map[$userid]['attemptcount']++;
            if ($score !== null && $score < self::UNSUCCESSFUL_SCORE_THRESHOLD) {
                $map[$userid]['unsuccessfulcount']++;
            }
            if ($map[$userid]['latesttime'] === null) {
                $map[$userid]['latestscore'] = $score;
                $map[$userid]['latesttime'] = $time;
            }
            if ($score !== null && ($map[$userid]['bestscore'] === null || $score > $map[$userid]['bestscore'])) {
                $map[$userid]['bestscore'] = $score;
                $map[$userid]['besttime'] = $time;
            }
            if ($score !== null) {
                $map[$userid]['recentpoints'][] = [
                    'score' => $score,
                    'time' => $time,
                ];
            }
        }
        foreach ($map as $userid => $summary) {
            $map[$userid]['trend'] = self::score_trend($summary['recentpoints']);
        }
        return $map;
    }

    /**
     * Grade summary map for page learners.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @return array
     */
    private static function grade_summary_map(int $courseid, array $learnerids): array {
        global $DB;

        if (!$learnerids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'gradeuser');
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_grade_summary}
              WHERE courseid = :courseid
                AND reconciliationstatus = :status
                AND userid {$userinsql}
           ORDER BY userid ASC, timemodified DESC, id DESC",
            array_merge($userparams, ['courseid' => $courseid, 'status' => 'current']),
            0,
            self::MAX_DETAIL_RECORDS
        );
        $map = [];
        foreach ($records as $record) {
            $userid = (int)$record->userid;
            if (!isset($map[$userid])) {
                $map[$userid] = [
                    'status' => 'available',
                    'count' => 0,
                    'officialaverage' => null,
                    'officialsum' => 0.0,
                    'latesttime' => null,
                    'latestgradeitemid' => null,
                ];
            }
            $grade = self::float_or_null($record->officialfinalgrade ?? null);
            if ($grade !== null) {
                $map[$userid]['officialsum'] += $grade;
                $map[$userid]['count']++;
                $time = isset($record->officialgradetime) ? (int)$record->officialgradetime : null;
                if ($map[$userid]['latesttime'] === null || ($time !== null && $time > $map[$userid]['latesttime'])) {
                    $map[$userid]['latesttime'] = $time;
                    $map[$userid]['latestgradeitemid'] = isset($record->gradeitemid) ? (int)$record->gradeitemid : null;
                }
            }
        }
        foreach ($map as $userid => $summary) {
            if ($summary['count'] > 0) {
                $map[$userid]['officialaverage'] = round($summary['officialsum'] / $summary['count'], 2);
            } else {
                $map[$userid] = self::empty_grade_dto();
            }
            unset($map[$userid]['officialsum']);
        }
        return $map;
    }

    /**
     * Grade version map for page learners.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @return array
     */
    private static function grade_version_map(int $courseid, array $learnerids): array {
        global $DB;

        if (!$learnerids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'gvuser');
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_grade_version}
              WHERE courseid = :courseid
                AND userid {$userinsql}
           ORDER BY userid ASC, gradetime DESC, id DESC",
            array_merge($userparams, ['courseid' => $courseid]),
            0,
            self::MAX_DETAIL_RECORDS
        );
        $map = [];
        foreach ($records as $record) {
            $userid = (int)$record->userid;
            if (!isset($map[$userid])) {
                $map[$userid] = [
                    'status' => 'available',
                    'versioncount' => 0,
                    'latestfinalgrade' => self::float_or_null($record->finalgrade ?? null),
                    'latesttime' => isset($record->gradetime) ? (int)$record->gradetime : null,
                    'declinecount' => 0,
                    'hascomparabledecline' => false,
                ];
            }
            $map[$userid]['versioncount']++;
            $previous = self::float_or_null($record->previousgrade ?? null);
            $final = self::float_or_null($record->finalgrade ?? null);
            if ($previous !== null && $final !== null && $final < $previous) {
                $map[$userid]['declinecount']++;
            }
        }
        foreach ($map as $userid => $summary) {
            $map[$userid]['hascomparabledecline'] = $summary['versioncount'] >= 2 && $summary['declinecount'] > 0;
        }
        return $map;
    }

    /**
     * Checkpoint map for page learners.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @return array
     */
    private static function checkpoint_map(int $courseid, array $learnerids): array {
        global $DB;

        if (!$learnerids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'chkuser');
        $like = $DB->sql_like('eventtype', ':checkpointpattern', false);
        $records = $DB->get_records_sql(
            "SELECT userid AS id,
                    userid,
                    COUNT(1) AS checkpointcount,
                    MAX(eventtime) AS lastcheckpoint
               FROM {flwhist_source_event}
              WHERE courseid = :courseid
                AND userid {$userinsql}
                AND (assessmentid IS NOT NULL OR {$like} OR sourcefamily = :quizfamily)
           GROUP BY userid",
            array_merge($userparams, [
                'courseid' => $courseid,
                'checkpointpattern' => '%CHECKPOINT%',
                'quizfamily' => 'quiz',
            ])
        );
        $map = [];
        foreach ($records as $record) {
            $map[(int)$record->userid] = [
                'status' => 'available',
                'count' => (int)$record->checkpointcount,
                'lasttime' => isset($record->lastcheckpoint) ? (int)$record->lastcheckpoint : null,
            ];
        }
        return $map;
    }

    /**
     * Placement map for page learners.
     *
     * @param int $courseid Course id.
     * @param array $learnerids Learner ids.
     * @return array
     */
    private static function placement_map(int $courseid, array $learnerids): array {
        global $DB;

        if (!$learnerids) {
            return [];
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'pluser');
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_placement}
              WHERE (courseid = :courseid OR courseid IS NULL)
                AND userid {$userinsql}
           ORDER BY userid ASC, placementtime DESC, id DESC",
            array_merge($userparams, ['courseid' => $courseid]),
            0,
            self::MAX_DETAIL_RECORDS
        );
        $map = [];
        $counts = [];
        foreach ($records as $record) {
            $userid = (int)$record->userid;
            $counts[$userid] = ($counts[$userid] ?? 0) + 1;
            if (!isset($map[$userid])) {
                $map[$userid] = [
                    'status' => 'available',
                    'currentlevel' => $record->currentlevel ?? null,
                    'previouslevel' => $record->previouslevel ?? null,
                    'placementstatus' => (string)$record->placementstatus,
                    'score' => self::float_or_null($record->score ?? null),
                    'confidence' => self::float_or_null($record->confidence ?? null),
                    'time' => isset($record->placementtime) ? (int)$record->placementtime : null,
                    'count' => 0,
                ];
            }
        }
        foreach ($counts as $userid => $count) {
            if (isset($map[$userid])) {
                $map[$userid]['count'] = $count;
            }
        }
        return $map;
    }

    /**
     * Build grade audit records.
     *
     * @param int $courseid Course id.
     * @param bool $includeaudit Include audit.
     * @return array
     */
    private static function grade_audit(int $courseid, bool $includeaudit): array {
        global $DB;

        if (!$includeaudit) {
            return [
                'status' => 'capability_required',
                'records' => [],
            ];
        }
        $records = $DB->get_records_sql(
            "SELECT gv.id,
                    gv.userid,
                    gv.gradeitemid,
                    gv.cmid,
                    gv.previousgrade,
                    gv.finalgrade,
                    gv.graderid,
                    gv.action,
                    gv.reason,
                    gv.gradetime,
                    lu.firstname AS learnerfirstname,
                    lu.lastname AS learnerlastname,
                    gu.firstname AS graderfirstname,
                    gu.lastname AS graderlastname
               FROM {flwhist_grade_version} gv
          LEFT JOIN {user} lu ON lu.id = gv.userid
          LEFT JOIN {user} gu ON gu.id = gv.graderid
              WHERE gv.courseid = :courseid
           ORDER BY gv.gradetime DESC, gv.id DESC",
            ['courseid' => $courseid],
            0,
            self::AUDIT_LIMIT
        );
        $dtos = [];
        foreach ($records as $record) {
            $learnername = trim((string)($record->learnerfirstname ?? '') . ' ' . (string)($record->learnerlastname ?? ''));
            $gradername = trim((string)($record->graderfirstname ?? '') . ' ' . (string)($record->graderlastname ?? ''));
            $dtos[] = [
                'id' => (int)$record->id,
                'userid' => (int)$record->userid,
                'learnername' => $learnername,
                'gradeitemid' => isset($record->gradeitemid) ? (int)$record->gradeitemid : null,
                'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
                'previousgrade' => self::float_or_null($record->previousgrade ?? null),
                'finalgrade' => self::float_or_null($record->finalgrade ?? null),
                'graderid' => isset($record->graderid) ? (int)$record->graderid : null,
                'gradername' => $gradername,
                'action' => (string)$record->action,
                'reason' => $record->reason ?? null,
                'gradetime' => isset($record->gradetime) ? (int)$record->gradetime : null,
            ];
        }
        return [
            'status' => $dtos ? 'available' : 'empty',
            'records' => $dtos,
            'limit' => self::AUDIT_LIMIT,
        ];
    }

    /**
     * Build checkpoint/placement summary for visible rows.
     *
     * @param array $rows Learner rows.
     * @return array
     */
    private static function checkpoint_placement_summary(array $rows): array {
        $checkpointcount = 0;
        $placementcount = 0;
        $levels = [];
        foreach ($rows as $row) {
            $checkpointcount += (int)($row['checkpoint_history']['count'] ?? 0);
            if (($row['placement_history']['status'] ?? '') === 'available') {
                $placementcount++;
                $level = (string)($row['placement_history']['currentlevel'] ?? '');
                if ($level !== '') {
                    $levels[$level] = ($levels[$level] ?? 0) + 1;
                }
            }
        }
        ksort($levels);
        return [
            'status' => ($checkpointcount > 0 || $placementcount > 0) ? 'available' : 'insufficient_data',
            'visiblecheckpointcount' => $checkpointcount,
            'visibleplacementcount' => $placementcount,
            'levels' => $levels,
        ];
    }

    /**
     * Build completion DTO.
     *
     * @param int $completed Completed activities.
     * @param int $total Total tracked activities.
     * @return array
     */
    private static function completion_dto(int $completed, int $total): array {
        return [
            'status' => $total > 0 ? 'available' : 'insufficient_data',
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? round(($completed / $total) * 100, 2) : null,
        ];
    }

    /**
     * Build allowed attention signals.
     *
     * @param array $completion Completion DTO.
     * @param array $activity Activity DTO.
     * @param array $attempt Attempt DTO.
     * @param array $gradehistory Grade version DTO.
     * @param int $inactivecutoff Inactive cutoff.
     * @return array
     */
    private static function attention_signals(
        array $completion,
        array $activity,
        array $attempt,
        array $gradehistory,
        int $inactivecutoff
    ): array {
        $signals = [];
        $lastactivity = $activity['eventtime'] ?? null;
        if ($lastactivity === null && (int)($attempt['attemptcount'] ?? 0) === 0) {
            $signals[] = self::signal('missing_activity_evidence', 'Missing activity evidence', 'medium',
                'No normalized activity or attempt evidence is available for this course.');
        } else if ($lastactivity !== null && $lastactivity < $inactivecutoff) {
            $signals[] = self::signal('inactive', 'Inactive', 'medium',
                'Last meaningful activity is outside the 14-day activity window.');
        }
        if ((int)($attempt['unsuccessfulcount'] ?? 0) >= 2) {
            $signals[] = self::signal('repeated_unsuccessful_attempts', 'Repeated unsuccessful attempts', 'high',
                'At least two attempts have a scaled score below 60%.');
        }
        if (!empty($gradehistory['hascomparabledecline'])) {
            $signals[] = self::signal('grade_decline_with_enough_comparable_data',
                'Grade decline with enough comparable data', 'medium',
                'Grade version history includes a lower final grade after a comparable previous grade.');
        }
        if (($completion['status'] ?? '') === 'available'
                && (float)($completion['percent'] ?? 0) < 100.0
                && $lastactivity !== null
                && $lastactivity < $inactivecutoff) {
            $signals[] = self::signal('stalled_completion', 'Stalled completion', 'medium',
                'Completion is not finished and activity is outside the 14-day window.');
        }
        return $signals;
    }

    /**
     * Signal DTO.
     *
     * @param string $key Signal key.
     * @param string $label Label.
     * @param string $severity Severity.
     * @param string $evidence Evidence.
     * @return array
     */
    private static function signal(string $key, string $label, string $severity, string $evidence): array {
        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'evidence' => $evidence,
            'adaptive' => false,
        ];
    }

    /**
     * Attention definitions.
     *
     * @return array
     */
    private static function attention_definitions(): array {
        return [
            'inactive' => 'No recent normalized learning activity.',
            'repeated_unsuccessful_attempts' => 'At least two attempts below the configured descriptive score threshold.',
            'grade_decline_with_enough_comparable_data' => 'Comparable grade version data shows a decline.',
            'stalled_completion' => 'Completion remains unfinished after older activity.',
            'missing_activity_evidence' => 'No activity or attempt evidence has been captured.',
        ];
    }

    /**
     * Score trend from recent points.
     *
     * @param array $points Points ordered newest first.
     * @return array
     */
    private static function score_trend(array $points): array {
        $points = array_values(array_filter($points, fn(array $point): bool => $point['score'] !== null));
        if (count($points) < 2) {
            return [
                'status' => 'insufficient_data',
                'reason' => 'NEED_AT_LEAST_TWO_ATTEMPTS',
            ];
        }
        $latest = $points[0]['score'];
        $previous = $points[1]['score'];
        $delta = round(($latest - $previous) * 100, 2);
        return [
            'status' => 'available',
            'latest' => round($latest * 100, 2),
            'previous' => round($previous * 100, 2),
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'mastery_based' => false,
        ];
    }

    /**
     * Count map helper.
     *
     * @param array $records Records.
     * @param string $field Field.
     * @return array
     */
    private static function count_map(array $records, string $field): array {
        $map = [];
        foreach ($records as $record) {
            $map[(int)$record->userid] = (int)$record->{$field};
        }
        return $map;
    }

    /**
     * Missing activity DTO.
     *
     * @return array
     */
    private static function missing_activity_dto(): array {
        return [
            'status' => 'insufficient_data',
            'eventtime' => null,
            'sourcefamily' => null,
            'eventtype' => null,
            'unitid' => null,
            'activityid' => null,
            'evidencecount' => 0,
        ];
    }

    /**
     * Empty attempt DTO.
     *
     * @return array
     */
    private static function empty_attempt_dto(): array {
        return [
            'status' => 'insufficient_data',
            'attemptcount' => 0,
            'unsuccessfulcount' => 0,
            'latestscore' => null,
            'latesttime' => null,
            'bestscore' => null,
            'besttime' => null,
            'trend' => [
                'status' => 'insufficient_data',
                'reason' => 'NO_ATTEMPT_EVIDENCE',
            ],
            'recentpoints' => [],
        ];
    }

    /**
     * Empty grade DTO.
     *
     * @return array
     */
    private static function empty_grade_dto(): array {
        return [
            'status' => 'insufficient_data',
            'count' => 0,
            'officialaverage' => null,
            'latesttime' => null,
            'latestgradeitemid' => null,
        ];
    }

    /**
     * Empty grade version DTO.
     *
     * @return array
     */
    private static function empty_grade_version_dto(): array {
        return [
            'status' => 'insufficient_data',
            'versioncount' => 0,
            'latestfinalgrade' => null,
            'latesttime' => null,
            'declinecount' => 0,
            'hascomparabledecline' => false,
        ];
    }

    /**
     * Empty checkpoint DTO.
     *
     * @return array
     */
    private static function empty_checkpoint_dto(): array {
        return [
            'status' => 'insufficient_data',
            'count' => 0,
            'lasttime' => null,
        ];
    }

    /**
     * Empty placement DTO.
     *
     * @return array
     */
    private static function empty_placement_dto(): array {
        return [
            'status' => 'insufficient_data',
            'currentlevel' => null,
            'previouslevel' => null,
            'placementstatus' => null,
            'score' => null,
            'confidence' => null,
            'time' => null,
            'count' => 0,
        ];
    }

    /**
     * Convert value to float or null.
     *
     * @param mixed $value Value.
     * @return float|null
     */
    private static function float_or_null($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }
}

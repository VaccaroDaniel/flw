<?php
// Secure summary and query services for local_flwhistory H4.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

/**
 * Bounded read services for Program 2 history data.
 */
class history_api_service {
    /** Default query page size. */
    private const DEFAULT_LIMIT = 50;

    /** Maximum query page size exposed by H4. */
    private const MAX_LIMIT = 100;

    /** Maximum number of journey activities in one response. */
    private const MAX_JOURNEY_ITEMS = 250;

    /** Default active-day window. */
    private const ACTIVE_DAY_WINDOW = 7776000; // 90 days.

    /** Default recent-activity window. */
    private const RECENT_ACTIVITY_WINDOW = 2592000; // 30 days.

    /**
     * Build the trusted present summary core.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @return array
     */
    public static function present_summary_core(int $courseid, int $userid): array {
        $course = get_course($courseid);
        $courseidentity = p1_resolver::resolve_course($courseid);
        $latestunit = self::latest_unit_identity($courseid, $userid);
        $completion = self::completion_progress($courseid, $userid);
        $activewindowstart = time() - self::ACTIVE_DAY_WINDOW;

        return [
            'type' => 'PresentSummaryCore',
            'userid' => $userid,
            'courseid' => $courseid,
            'course' => [
                'id' => (int)$course->id,
                'fullname' => (string)$course->fullname,
                'shortname' => (string)$course->shortname,
            ],
            'current' => [
                'course_status' => $courseidentity['status'] ?? 'unresolved',
                'worldid' => $latestunit['worldid'] ?? ($courseidentity['worldid'] ?? null),
                'stageid' => $latestunit['stageid'] ?? ($courseidentity['stageid'] ?? null),
                'unitid' => $latestunit['unitid'] ?? null,
                'activityid' => $latestunit['activityid'] ?? null,
                'source' => $latestunit['source'] ?? 'none',
                'status' => empty($latestunit['unitid']) ? 'insufficient_data' : 'available',
            ],
            'completion' => $completion,
            'active_days' => self::active_days($courseid, $userid, $activewindowstart, time()),
            'scores' => self::score_summary($courseid, $userid),
            'study_time' => [
                'status' => 'insufficient_data',
                'seconds' => null,
                'reason' => 'NO_RELIABLE_STUDY_TIME_SOURCE_H4',
            ],
            'generatedat' => time(),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Query normalized learning history source events.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @param int $limit Page size.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @param string $sourcefamily Optional source family.
     * @return array
     */
    public static function learning_history_query(
        int $courseid,
        int $userid,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0,
        string $sourcefamily = ''
    ): array {
        global $DB;

        [$where, $params] = self::source_event_conditions($courseid, $userid, $timestart, $timeend, $sourcefamily);
        $limit = self::normalise_limit($limit);
        $offset = self::normalise_offset($offset);
        $whereclause = implode(' AND ', $where);
        $total = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {flwhist_source_event} WHERE {$whereclause}", $params);
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_source_event}
              WHERE {$whereclause}
           ORDER BY eventtime DESC, id DESC",
            $params,
            $offset,
            $limit
        );

        return [
            'type' => 'LearningHistoryQuery',
            'userid' => $userid,
            'courseid' => $courseid,
            'pagination' => self::pagination($limit, $offset, $total),
            'filters' => self::filters($timestart, $timeend, $sourcefamily),
            'records' => array_map([self::class, 'source_event_dto'], array_values($records)),
        ];
    }

    /**
     * Query normalized attempts.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @param int $limit Page size.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @param int $cmid Optional course module id.
     * @return array
     */
    public static function attempt_history_query(
        int $courseid,
        int $userid,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0,
        int $cmid = 0
    ): array {
        global $DB;

        [$where, $params] = self::attempt_conditions($courseid, $userid, $timestart, $timeend, $cmid);
        $limit = self::normalise_limit($limit);
        $offset = self::normalise_offset($offset);
        $whereclause = implode(' AND ', $where);
        $total = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {flwhist_attempt} WHERE {$whereclause}", $params);
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_attempt}
              WHERE {$whereclause}
           ORDER BY timefinish DESC, id DESC",
            $params,
            $offset,
            $limit
        );

        return [
            'type' => 'AttemptHistoryQuery',
            'userid' => $userid,
            'courseid' => $courseid,
            'pagination' => self::pagination($limit, $offset, $total),
            'filters' => [
                'timestart' => $timestart > 0 ? $timestart : null,
                'timeend' => $timeend > 0 ? $timeend : null,
                'cmid' => $cmid > 0 ? $cmid : null,
            ],
            'records' => array_map([self::class, 'attempt_dto'], array_values($records)),
        ];
    }

    /**
     * Query grade-version history.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @param int $limit Page size.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @param int $gradeitemid Optional grade item id.
     * @param bool $includeaudit Include teacher/admin audit fields.
     * @return array
     */
    public static function grade_history_query(
        int $courseid,
        int $userid,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0,
        int $gradeitemid = 0,
        bool $includeaudit = false
    ): array {
        global $DB;

        [$where, $params] = self::grade_conditions($courseid, $userid, $timestart, $timeend, $gradeitemid);
        $limit = self::normalise_limit($limit);
        $offset = self::normalise_offset($offset);
        $whereclause = implode(' AND ', $where);
        $total = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {flwhist_grade_version} WHERE {$whereclause}", $params);
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_grade_version}
              WHERE {$whereclause}
           ORDER BY gradetime DESC, id DESC",
            $params,
            $offset,
            $limit
        );

        return [
            'type' => 'GradeHistoryQuery',
            'userid' => $userid,
            'courseid' => $courseid,
            'pagination' => self::pagination($limit, $offset, $total),
            'filters' => [
                'timestart' => $timestart > 0 ? $timestart : null,
                'timeend' => $timeend > 0 ? $timeend : null,
                'gradeitemid' => $gradeitemid > 0 ? $gradeitemid : null,
                'includeaudit' => $includeaudit,
            ],
            'records' => array_map(function(\stdClass $record) use ($includeaudit): array {
                return self::grade_version_dto($record, $includeaudit);
            }, array_values($records)),
        ];
    }

    /**
     * Query recent normalized source activity.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @param int $limit Page size.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @return array
     */
    public static function recent_activity_query(
        int $courseid,
        int $userid,
        int $limit = 20,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0
    ): array {
        if ($timestart <= 0 && $timeend <= 0) {
            $timestart = time() - self::RECENT_ACTIVITY_WINDOW;
        }
        $result = self::learning_history_query($courseid, $userid, $limit, $offset, $timestart, $timeend);
        $result['type'] = 'RecentActivityQuery';
        $result['window'] = [
            'defaulted' => $timestart > 0 && $timeend <= 0,
            'timestart' => $timestart > 0 ? $timestart : null,
            'timeend' => $timeend > 0 ? $timeend : null,
        ];
        return $result;
    }

    /**
     * Build non-adaptive learning journey from structure, completion, and history.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id.
     * @return array
     */
    public static function learning_journey_core(int $courseid, int $userid): array {
        $course = get_course($courseid);
        $orderedmodules = self::ordered_course_modules($courseid);
        $cmids = array_map(fn(array $module): int => $module['cmid'], $orderedmodules);
        $links = self::content_links_by_cmid($cmids);
        $completion = self::completion_by_cmid($cmids, $userid);
        $sourcecounts = self::source_event_counts_by_cmid($courseid, $userid, $cmids);
        $attemptcounts = self::attempt_counts_by_cmid($courseid, $userid, $cmids);

        $items = [];
        foreach ($orderedmodules as $index => $module) {
            $cmid = $module['cmid'];
            $link = $links[$cmid] ?? null;
            $completionstate = $completion[$cmid]->completionstate ?? null;
            $completed = self::is_completion_state_complete($completionstate);
            $evidencecount = (int)($sourcecounts[$cmid] ?? 0) + (int)($attemptcounts[$cmid] ?? 0);
            $state = $completed ? 'completed' : ($evidencecount > 0 ? 'inprogress' : 'notstarted');
            $items[] = [
                'index' => $index,
                'cmid' => $cmid,
                'modname' => $module['modname'],
                'sectionnum' => $module['sectionnum'],
                'sectionname' => $module['sectionname'],
                'name' => $module['name'],
                'state' => $state,
                'checkpoint' => self::is_checkpoint($module, $link),
                'completionstate' => $completionstate === null ? null : (int)$completionstate,
                'completiontime' => isset($completion[$cmid]->timemodified) ? (int)$completion[$cmid]->timemodified : null,
                'evidencecount' => $evidencecount,
                'identity' => self::content_link_dto($link),
            ];
        }

        $currentindex = self::current_journey_index($items);
        foreach ($items as $index => $item) {
            if ($currentindex !== null && $index === $currentindex && $item['state'] !== 'completed') {
                $items[$index]['state'] = 'current';
            } else if ($currentindex !== null && $index > $currentindex && $item['state'] === 'notstarted') {
                $items[$index]['state'] = 'future';
            }
            unset($items[$index]['index']);
        }

        return [
            'type' => 'LearningJourneyCore',
            'userid' => $userid,
            'courseid' => $courseid,
            'course' => [
                'id' => (int)$course->id,
                'fullname' => (string)$course->fullname,
                'shortname' => (string)$course->shortname,
            ],
            'summary' => self::journey_counts($items),
            'items' => $items,
            'generatedat' => time(),
            'adaptive' => [
                'status' => 'not_in_scope',
                'reason' => 'PROGRAM_3_OWNS_ADAPTIVE_RECOMMENDATIONS',
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Build source event conditions.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Start time.
     * @param int $timeend End time.
     * @param string $sourcefamily Source family.
     * @return array
     */
    private static function source_event_conditions(
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        string $sourcefamily = ''
    ): array {
        $where = ['courseid = :courseid', 'userid = :userid'];
        $params = ['courseid' => $courseid, 'userid' => $userid];
        if ($timestart > 0) {
            $where[] = 'eventtime >= :timestart';
            $params['timestart'] = $timestart;
        }
        if ($timeend > 0) {
            $where[] = 'eventtime <= :timeend';
            $params['timeend'] = $timeend;
        }
        $sourcefamily = history_policy::clean_family($sourcefamily);
        if ($sourcefamily !== 'unknown') {
            $where[] = 'sourcefamily = :sourcefamily';
            $params['sourcefamily'] = $sourcefamily;
        }
        return [$where, $params];
    }

    /**
     * Build attempt conditions.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Start time.
     * @param int $timeend End time.
     * @param int $cmid Course module id.
     * @return array
     */
    private static function attempt_conditions(int $courseid, int $userid, int $timestart, int $timeend, int $cmid): array {
        $where = ['courseid = :courseid', 'userid = :userid'];
        $params = ['courseid' => $courseid, 'userid' => $userid];
        if ($timestart > 0) {
            $where[] = 'timefinish >= :timestart';
            $params['timestart'] = $timestart;
        }
        if ($timeend > 0) {
            $where[] = 'timefinish <= :timeend';
            $params['timeend'] = $timeend;
        }
        if ($cmid > 0) {
            $where[] = 'cmid = :cmid';
            $params['cmid'] = $cmid;
        }
        return [$where, $params];
    }

    /**
     * Build grade-version conditions.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Start time.
     * @param int $timeend End time.
     * @param int $gradeitemid Grade item id.
     * @return array
     */
    private static function grade_conditions(
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        int $gradeitemid
    ): array {
        $where = ['courseid = :courseid', 'userid = :userid'];
        $params = ['courseid' => $courseid, 'userid' => $userid];
        if ($timestart > 0) {
            $where[] = 'gradetime >= :timestart';
            $params['timestart'] = $timestart;
        }
        if ($timeend > 0) {
            $where[] = 'gradetime <= :timeend';
            $params['timeend'] = $timeend;
        }
        if ($gradeitemid > 0) {
            $where[] = 'gradeitemid = :gradeitemid';
            $params['gradeitemid'] = $gradeitemid;
        }
        return [$where, $params];
    }

    /**
     * Get latest known unit identity from history.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array
     */
    private static function latest_unit_identity(int $courseid, int $userid): array {
        global $DB;

        $sql = "SELECT id, worldid, stageid, unitid, activityid, eventtime
                  FROM {flwhist_source_event}
                 WHERE courseid = :courseid
                   AND userid = :userid
                   AND unitid IS NOT NULL
              ORDER BY eventtime DESC, id DESC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid], 0, 1);
        if ($records) {
            $record = reset($records);
            return [
                'source' => 'source_event',
                'worldid' => $record->worldid ?? null,
                'stageid' => $record->stageid ?? null,
                'unitid' => $record->unitid ?? null,
                'activityid' => $record->activityid ?? null,
                'eventtime' => (int)$record->eventtime,
            ];
        }

        $sql = "SELECT id, worldid, stageid, unitid, activityid, timefinish
                  FROM {flwhist_attempt}
                 WHERE courseid = :courseid
                   AND userid = :userid
                   AND unitid IS NOT NULL
              ORDER BY timefinish DESC, id DESC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid], 0, 1);
        if ($records) {
            $record = reset($records);
            return [
                'source' => 'attempt',
                'worldid' => $record->worldid ?? null,
                'stageid' => $record->stageid ?? null,
                'unitid' => $record->unitid ?? null,
                'activityid' => $record->activityid ?? null,
                'eventtime' => isset($record->timefinish) ? (int)$record->timefinish : null,
            ];
        }

        return ['source' => 'none'];
    }

    /**
     * Calculate Moodle completion progress.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array
     */
    private static function completion_progress(int $courseid, int $userid): array {
        global $DB;

        $modules = $DB->get_records_select('course_modules',
            'course = :courseid AND deletioninprogress = 0 AND completion <> :none',
            ['courseid' => $courseid, 'none' => COMPLETION_TRACKING_NONE],
            '',
            'id'
        );
        $total = count($modules);
        if ($total === 0) {
            return [
                'status' => 'insufficient_data',
                'completed' => 0,
                'total' => 0,
                'percent' => null,
                'reason' => 'NO_COMPLETION_TRACKED_MODULES',
            ];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($modules), SQL_PARAMS_NAMED, 'cmp');
        $params = array_merge($inparams, ['userid' => $userid]);
        $completed = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {course_modules_completion}
              WHERE userid = :userid
                AND coursemoduleid {$insql}
                AND completionstate > 0",
            $params
        );

        return [
            'status' => 'available',
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? round(($completed / $total) * 100, 2) : null,
        ];
    }

    /**
     * Calculate active days from normalized source events.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Start time.
     * @param int $timeend End time.
     * @return array
     */
    private static function active_days(int $courseid, int $userid, int $timestart, int $timeend): array {
        global $DB;

        $records = $DB->get_records_select('flwhist_source_event',
            'courseid = :courseid AND userid = :userid AND eventtime >= :timestart AND eventtime <= :timeend',
            ['courseid' => $courseid, 'userid' => $userid, 'timestart' => $timestart, 'timeend' => $timeend],
            'eventtime DESC',
            'id,eventtime',
            0,
            10000
        );
        $days = [];
        foreach ($records as $record) {
            $days[gmdate('Y-m-d', (int)$record->eventtime)] = true;
        }

        return [
            'status' => 'available',
            'count' => count($days),
            'windowdays' => 90,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];
    }

    /**
     * Build score summary from local summaries and attempts.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array
     */
    private static function score_summary(int $courseid, int $userid): array {
        global $DB;

        $official = $DB->get_record_sql(
            "SELECT COUNT(officialfinalgrade) AS gradecount, AVG(officialfinalgrade) AS averagegrade
               FROM {flwhist_grade_summary}
              WHERE courseid = :courseid
                AND userid = :userid
                AND reconciliationstatus = :status
                AND officialfinalgrade IS NOT NULL",
            ['courseid' => $courseid, 'userid' => $userid, 'status' => 'current']
        );
        $assessment = $DB->get_record_sql(
            "SELECT COUNT(scaledscore) AS attemptcount, AVG(scaledscore) AS averagescore
               FROM {flwhist_attempt}
              WHERE courseid = :courseid
                AND userid = :userid
                AND scaledscore IS NOT NULL",
            ['courseid' => $courseid, 'userid' => $userid]
        );

        $officialcount = (int)($official->gradecount ?? 0);
        $attemptcount = (int)($assessment->attemptcount ?? 0);
        return [
            'official_moodle_grade' => [
                'status' => $officialcount > 0 ? 'available' : 'insufficient_data',
                'average' => $officialcount > 0 ? round((float)$official->averagegrade, 5) : null,
                'count' => $officialcount,
            ],
            'assessment_attempt_score' => [
                'status' => $attemptcount > 0 ? 'available' : 'insufficient_data',
                'average' => $attemptcount > 0 ? round((float)$assessment->averagescore, 5) : null,
                'count' => $attemptcount,
            ],
        ];
    }

    /**
     * Fetch visible course modules in course order.
     *
     * @param int $courseid Course id.
     * @return array
     */
    private static function ordered_course_modules(int $courseid): array {
        global $DB;

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC', 'id,section,name,sequence');
        $orderedcmids = [];
        $sectionbycmid = [];
        foreach ($sections as $section) {
            foreach (array_filter(explode(',', (string)$section->sequence)) as $cmid) {
                $cmid = (int)$cmid;
                if ($cmid <= 0 || isset($sectionbycmid[$cmid])) {
                    continue;
                }
                $orderedcmids[] = $cmid;
                $sectionbycmid[$cmid] = $section;
            }
        }
        if (!$orderedcmids) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($orderedcmids, SQL_PARAMS_NAMED, 'cm');
        $records = $DB->get_records_sql(
            "SELECT cm.id AS cmid, cm.instance, cm.section, cm.completion, cm.visible, m.name AS modname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id {$insql}
                AND cm.course = :courseid
                AND cm.deletioninprogress = 0
                AND cm.visible = 1",
            array_merge($inparams, ['courseid' => $courseid])
        );

        $items = [];
        foreach ($orderedcmids as $cmid) {
            if (!isset($records[$cmid])) {
                continue;
            }
            $record = $records[$cmid];
            $section = $sectionbycmid[$cmid] ?? null;
            $items[] = [
                'cmid' => $cmid,
                'modname' => (string)$record->modname,
                'name' => self::module_instance_name((string)$record->modname, (int)$record->instance),
                'sectionnum' => $section ? (int)$section->section : null,
                'sectionname' => $section->name ?? null,
                'completion' => isset($record->completion) ? (int)$record->completion : null,
            ];
            if (count($items) >= self::MAX_JOURNEY_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * Get module instance name.
     *
     * @param string $modname Module name.
     * @param int $instance Instance id.
     * @return string|null
     */
    private static function module_instance_name(string $modname, int $instance): ?string {
        global $DB;

        if ($modname === '' || $instance <= 0 || !$DB->get_manager()->table_exists($modname)) {
            return null;
        }
        $name = $DB->get_field($modname, 'name', ['id' => $instance], IGNORE_MISSING);
        return $name === false ? null : (string)$name;
    }

    /**
     * Fetch content-link rows by cmid.
     *
     * @param array $cmids Course module ids.
     * @return array
     */
    private static function content_links_by_cmid(array $cmids): array {
        global $DB;

        if (!$cmids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'linkcm');
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwhist_content_link}
              WHERE cmid {$insql}
           ORDER BY timemodified DESC, id DESC",
            $params
        );
        $links = [];
        foreach ($records as $record) {
            $cmid = (int)$record->cmid;
            if (!isset($links[$cmid])) {
                $links[$cmid] = $record;
            }
        }
        return $links;
    }

    /**
     * Fetch completion rows by cmid.
     *
     * @param array $cmids Course module ids.
     * @param int $userid User id.
     * @return array
     */
    private static function completion_by_cmid(array $cmids, int $userid): array {
        global $DB;

        if (!$cmids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'compcm');
        return $DB->get_records_sql(
            "SELECT coursemoduleid AS id, coursemoduleid, userid, completionstate, timemodified
               FROM {course_modules_completion}
              WHERE userid = :userid
                AND coursemoduleid {$insql}",
            array_merge($inparams, ['userid' => $userid])
        );
    }

    /**
     * Count source events by course module.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param array $cmids Course module ids.
     * @return array
     */
    private static function source_event_counts_by_cmid(int $courseid, int $userid, array $cmids): array {
        global $DB;

        if (!$cmids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'eventcm');
        $records = $DB->get_records_sql(
            "SELECT cmid AS id, cmid, COUNT(1) AS recordcount
               FROM {flwhist_source_event}
              WHERE courseid = :courseid
                AND userid = :userid
                AND cmid {$insql}
           GROUP BY cmid",
            array_merge($inparams, ['courseid' => $courseid, 'userid' => $userid])
        );
        return self::count_map($records);
    }

    /**
     * Count attempts by course module.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param array $cmids Course module ids.
     * @return array
     */
    private static function attempt_counts_by_cmid(int $courseid, int $userid, array $cmids): array {
        global $DB;

        if (!$cmids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'attemptcm');
        $records = $DB->get_records_sql(
            "SELECT cmid AS id, cmid, COUNT(1) AS recordcount
               FROM {flwhist_attempt}
              WHERE courseid = :courseid
                AND userid = :userid
                AND cmid {$insql}
           GROUP BY cmid",
            array_merge($inparams, ['courseid' => $courseid, 'userid' => $userid])
        );
        return self::count_map($records);
    }

    /**
     * Convert count records to cmid => count.
     *
     * @param array $records Count records.
     * @return array
     */
    private static function count_map(array $records): array {
        $counts = [];
        foreach ($records as $record) {
            $counts[(int)$record->cmid] = (int)$record->recordcount;
        }
        return $counts;
    }

    /**
     * Decide whether a completion state is complete.
     *
     * @param mixed $completionstate Completion state.
     * @return bool
     */
    private static function is_completion_state_complete($completionstate): bool {
        return in_array((int)$completionstate, [
            COMPLETION_COMPLETE,
            COMPLETION_COMPLETE_PASS,
            COMPLETION_COMPLETE_FAIL,
            COMPLETION_COMPLETE_FAIL_HIDDEN,
        ], true);
    }

    /**
     * Decide whether a journey item is a checkpoint.
     *
     * @param array $module Module row.
     * @param \stdClass|null $link Program 1 content link.
     * @return bool
     */
    private static function is_checkpoint(array $module, ?\stdClass $link): bool {
        return $module['modname'] === 'quiz' || !empty($link->assessmentid);
    }

    /**
     * Return the current journey index.
     *
     * @param array $items Journey items.
     * @return int|null
     */
    private static function current_journey_index(array $items): ?int {
        foreach ($items as $index => $item) {
            if ($item['state'] === 'inprogress') {
                return $index;
            }
        }
        foreach ($items as $index => $item) {
            if ($item['state'] === 'notstarted') {
                return $index;
            }
        }
        return null;
    }

    /**
     * Count journey states.
     *
     * @param array $items Journey items.
     * @return array
     */
    private static function journey_counts(array $items): array {
        $counts = [
            'completed' => 0,
            'current' => 0,
            'inprogress' => 0,
            'future' => 0,
            'notstarted' => 0,
            'checkpoint' => 0,
            'total' => count($items),
        ];
        foreach ($items as $item) {
            if (isset($counts[$item['state']])) {
                $counts[$item['state']]++;
            }
            if (!empty($item['checkpoint'])) {
                $counts['checkpoint']++;
            }
        }
        return $counts;
    }

    /**
     * Build source event DTO.
     *
     * @param \stdClass $record Record.
     * @return array
     */
    private static function source_event_dto(\stdClass $record): array {
        return [
            'id' => (int)$record->id,
            'sourcefamily' => (string)$record->sourcefamily,
            'sourcesystem' => (string)$record->sourcesystem,
            'sourcetype' => (string)$record->sourcetype,
            'eventtype' => (string)$record->eventtype,
            'userid' => isset($record->userid) ? (int)$record->userid : null,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'unitid' => $record->unitid ?? null,
            'activityid' => $record->activityid ?? null,
            'assessmentid' => $record->assessmentid ?? null,
            'eventtime' => (int)$record->eventtime,
            'status' => (string)$record->status,
            'normpolicyversion' => (string)$record->normpolicyversion,
            'summary' => self::decode_json($record->summaryjson ?? null),
        ];
    }

    /**
     * Build attempt DTO.
     *
     * @param \stdClass $record Record.
     * @return array
     */
    private static function attempt_dto(\stdClass $record): array {
        return [
            'id' => (int)$record->id,
            'sourcefamily' => (string)$record->sourcefamily,
            'sourcesystem' => (string)$record->sourcesystem,
            'sourcetype' => (string)$record->sourcetype,
            'userid' => (int)$record->userid,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'unitid' => $record->unitid ?? null,
            'activityid' => $record->activityid ?? null,
            'assessmentid' => $record->assessmentid ?? null,
            'attemptno' => isset($record->attemptno) ? (int)$record->attemptno : null,
            'attemptstate' => (string)$record->attemptstate,
            'rawscore' => self::float_or_null($record->rawscore ?? null),
            'maxscore' => self::float_or_null($record->maxscore ?? null),
            'scaledscore' => self::float_or_null($record->scaledscore ?? null),
            'timestart' => isset($record->timestart) ? (int)$record->timestart : null,
            'timefinish' => isset($record->timefinish) ? (int)$record->timefinish : null,
            'normpolicyversion' => (string)$record->normpolicyversion,
            'summary' => self::decode_json($record->summaryjson ?? null),
        ];
    }

    /**
     * Build grade-version DTO.
     *
     * @param \stdClass $record Record.
     * @param bool $includeaudit Include audit fields.
     * @return array
     */
    private static function grade_version_dto(\stdClass $record, bool $includeaudit): array {
        $dto = [
            'id' => (int)$record->id,
            'userid' => (int)$record->userid,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'gradeitemid' => isset($record->gradeitemid) ? (int)$record->gradeitemid : null,
            'gradegradeid' => isset($record->gradegradeid) ? (int)$record->gradegradeid : null,
            'itemmodule' => $record->itemmodule ?? null,
            'iteminstance' => isset($record->iteminstance) ? (int)$record->iteminstance : null,
            'itemnumber' => isset($record->itemnumber) ? (int)$record->itemnumber : null,
            'rawgrade' => self::float_or_null($record->rawgrade ?? null),
            'previousgrade' => self::float_or_null($record->previousgrade ?? null),
            'finalgrade' => self::float_or_null($record->finalgrade ?? null),
            'action' => (string)$record->action,
            'gradetime' => isset($record->gradetime) ? (int)$record->gradetime : null,
            'normpolicyversion' => (string)$record->normpolicyversion,
            'summary' => self::decode_json($record->summaryjson ?? null),
        ];
        if ($includeaudit) {
            $dto['audit'] = [
                'sourceeventid' => isset($record->sourceeventid) ? (int)$record->sourceeventid : null,
                'gradehistoryid' => isset($record->gradehistoryid) ? (int)$record->gradehistoryid : null,
                'graderid' => isset($record->graderid) ? (int)$record->graderid : null,
                'reason' => $record->reason ?? null,
                'correctionof' => isset($record->correctionof) ? (int)$record->correctionof : null,
                'supersededby' => isset($record->supersededby) ? (int)$record->supersededby : null,
            ];
        }
        return $dto;
    }

    /**
     * Build content-link DTO.
     *
     * @param \stdClass|null $record Content link.
     * @return array
     */
    private static function content_link_dto(?\stdClass $record): array {
        if (!$record) {
            return ['status' => 'unresolved'];
        }
        return [
            'status' => (string)$record->status,
            'freshness' => (string)$record->freshness,
            'worldid' => $record->worldid ?? null,
            'stageid' => $record->stageid ?? null,
            'unitid' => $record->unitid ?? null,
            'lessonid' => $record->lessonid ?? null,
            'componentid' => $record->componentid ?? null,
            'activityid' => $record->activityid ?? null,
            'assessmentid' => $record->assessmentid ?? null,
            'resolver' => $record->resolver ?? 'p1_contract',
        ];
    }

    /**
     * Build pagination DTO.
     *
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param int $total Total.
     * @return array
     */
    private static function pagination(int $limit, int $offset, int $total): array {
        return [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'hasmore' => ($offset + $limit) < $total,
        ];
    }

    /**
     * Build filters DTO.
     *
     * @param int $timestart Start time.
     * @param int $timeend End time.
     * @param string $sourcefamily Source family.
     * @return array
     */
    private static function filters(int $timestart, int $timeend, string $sourcefamily): array {
        $sourcefamily = history_policy::clean_family($sourcefamily);
        return [
            'timestart' => $timestart > 0 ? $timestart : null,
            'timeend' => $timeend > 0 ? $timeend : null,
            'sourcefamily' => $sourcefamily === 'unknown' ? null : $sourcefamily,
        ];
    }

    /**
     * Bound limit.
     *
     * @param int $limit Limit.
     * @return int
     */
    private static function normalise_limit(int $limit): int {
        return max(1, min(self::MAX_LIMIT, $limit ?: self::DEFAULT_LIMIT));
    }

    /**
     * Bound offset.
     *
     * @param int $offset Offset.
     * @return int
     */
    private static function normalise_offset(int $offset): int {
        return max(0, min(100000, $offset));
    }

    /**
     * Decode a JSON field.
     *
     * @param mixed $json JSON.
     * @return array|null
     */
    private static function decode_json($json): ?array {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Convert numeric values to nullable float.
     *
     * @param mixed $value Value.
     * @return float|null
     */
    private static function float_or_null($value): ?float {
        return $value === null || $value === '' ? null : (float)$value;
    }
}

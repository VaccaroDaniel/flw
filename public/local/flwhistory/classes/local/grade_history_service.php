<?php
// Grade history service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/grade/constants.php');
require_once($GLOBALS['CFG']->libdir . '/grade/grade_grade.php');
require_once($GLOBALS['CFG']->libdir . '/grade/grade_item.php');

/**
 * Service boundary for grade history records.
 */
class grade_history_service {
    public const ACTION_INITIAL = 'INITIAL';
    public const ACTION_RETAKE = 'RETAKE';
    public const ACTION_REGRADE = 'REGRADE';
    public const ACTION_TEACHER_OVERRIDE = 'TEACHER_OVERRIDE';
    public const ACTION_CORRECTION = 'CORRECTION';
    public const ACTION_IMPORT = 'IMPORT';
    public const ACTION_OTHER = 'OTHER';

    /**
     * Record a grade version.
     *
     * @param array $data Grade version data.
     * @return int Record id.
     */
    public static function record_grade_version(array $data): int {
        return repository::upsert_grade_version($data);
    }

    /**
     * Capture a Moodle user_graded event as grade history.
     *
     * @param \core\event\base $event Moodle grade event.
     * @param array $options Test/developer options.
     * @return array Capture result.
     */
    public static function capture_user_graded_event(\core\event\base $event, array $options = []): array {
        return self::capture_grade_event($event, $options);
    }

    /**
     * Capture a Moodle grade_deleted event as grade history.
     *
     * @param \core\event\base $event Moodle grade event.
     * @param array $options Test/developer options.
     * @return array Capture result.
     */
    public static function capture_grade_deleted_event(\core\event\base $event, array $options = []): array {
        $options['action'] = $options['action'] ?? self::ACTION_OTHER;
        $options['reason'] = $options['reason'] ?? 'grade_deleted';
        return self::capture_grade_event($event, $options);
    }

    /**
     * Record the current Moodle Gradebook grade as a source-linked grade version.
     *
     * @param \grade_grade $grade Moodle grade object.
     * @param array $extra Extra context.
     * @return int Grade version id.
     */
    public static function record_moodle_grade_version(\grade_grade $grade, array $extra = []): int {
        $gradeitem = self::grade_item_for_grade($grade);
        $history = self::latest_grade_history_row($grade);
        $previousgrade = self::previous_grade_from_history($history);
        if ($previousgrade === null) {
            $previousgrade = self::previous_grade_from_local_history((int)$grade->userid, (int)$grade->itemid);
        }

        $action = self::classify_grade_action($grade, $gradeitem, $history, $previousgrade, $extra);
        $gradetime = self::grade_time($grade, $history);
        $sourceid = $history ? (string)$history->id : (string)$grade->id;
        $sourcetype = $history ? 'grade_grades_history' : 'grade_grade';
        $sourcekey = source_identity::make_key('moodle', $sourcetype, $sourceid, (string)$gradetime, $action);
        $sourcefactkey = self::sourcefactkey_from_extra($extra, $sourcekey);
        $cmid = self::cmid_for_grade_item($gradeitem);
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course((int)($gradeitem->courseid ?? 0));

        $record = array_merge([
            'sourcekey' => $sourcekey,
            'sourcefactkey' => $sourcefactkey,
            'sourcefamily' => 'gradebook',
            'sourceeventid' => $extra['sourceeventid'] ?? null,
            'userid' => (int)$grade->userid,
            'courseid' => isset($gradeitem->courseid) ? (int)$gradeitem->courseid : null,
            'cmid' => $cmid > 0 ? $cmid : null,
            'gradeitemid' => (int)$grade->itemid,
            'gradegradeid' => (int)$grade->id,
            'gradehistoryid' => $history ? (int)$history->id : null,
            'itemmodule' => self::empty_to_null($gradeitem->itemmodule ?? null),
            'iteminstance' => isset($gradeitem->iteminstance) ? (int)$gradeitem->iteminstance : null,
            'itemnumber' => isset($gradeitem->itemnumber) ? (int)$gradeitem->itemnumber : null,
            'rawgrade' => self::float_or_null($grade->rawgrade ?? null),
            'finalgrade' => self::float_or_null($grade->finalgrade ?? null),
            'previousgrade' => $previousgrade,
            'graderid' => self::grade_actor($grade, $history, $extra),
            'action' => $action,
            'reason' => self::grade_reason($history, $extra),
            'gradetime' => $gradetime,
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
            'summaryjson' => self::grade_summary_payload($grade, $gradeitem, $history, $extra),
        ], self::mapping_fields($mapping));

        return repository::upsert_grade_version($record);
    }

    /**
     * Record a Moodle grade_grades_history row as a grade version.
     *
     * @param \stdClass $history Grade history row.
     * @param array $extra Extra context.
     * @return int Grade version id.
     */
    public static function record_grade_history_row(\stdClass $history, array $extra = []): int {
        $gradeitem = self::fetch_grade_item((int)$history->itemid);
        $previousgrade = self::previous_grade_from_history($history);
        $action = self::classify_history_action($history, $gradeitem, $previousgrade, $extra);
        $gradetime = (int)($history->timemodified ?? time());
        $sourcekey = source_identity::make_key('moodle', 'grade_grades_history', (string)$history->id,
            (string)$gradetime, $action);
        $sourcefactkey = self::sourcefactkey_from_extra($extra, $sourcekey);
        $cmid = self::cmid_for_grade_item($gradeitem);
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course((int)($gradeitem->courseid ?? 0));

        $record = array_merge([
            'sourcekey' => $sourcekey,
            'sourcefactkey' => $sourcefactkey,
            'sourcefamily' => 'gradebook',
            'sourceeventid' => $extra['sourceeventid'] ?? null,
            'userid' => (int)$history->userid,
            'courseid' => isset($gradeitem->courseid) ? (int)$gradeitem->courseid : null,
            'cmid' => $cmid > 0 ? $cmid : null,
            'gradeitemid' => (int)$history->itemid,
            'gradegradeid' => (int)$history->oldid,
            'gradehistoryid' => (int)$history->id,
            'itemmodule' => self::empty_to_null($gradeitem->itemmodule ?? null),
            'iteminstance' => isset($gradeitem->iteminstance) ? (int)$gradeitem->iteminstance : null,
            'itemnumber' => isset($gradeitem->itemnumber) ? (int)$gradeitem->itemnumber : null,
            'rawgrade' => self::float_or_null($history->rawgrade ?? null),
            'finalgrade' => self::float_or_null($history->finalgrade ?? null),
            'previousgrade' => $previousgrade,
            'graderid' => self::history_actor($history),
            'action' => $action,
            'reason' => self::history_reason($history, $extra),
            'gradetime' => $gradetime,
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
            'summaryjson' => self::history_summary_payload($history, $gradeitem, $extra),
        ], self::mapping_fields($mapping));

        return repository::upsert_grade_version($record);
    }

    /**
     * Return the source key that record_grade_history_row() will use for a Moodle grade history row.
     *
     * @param \stdClass $history Grade history row.
     * @param array $extra Extra context.
     * @return string Source key.
     */
    public static function grade_history_row_sourcekey(\stdClass $history, array $extra = []): string {
        $gradeitem = self::fetch_grade_item((int)$history->itemid);
        $previousgrade = self::previous_grade_from_history($history);
        $action = self::classify_history_action($history, $gradeitem, $previousgrade, $extra);
        $gradetime = (int)($history->timemodified ?? time());

        return source_identity::make_key('moodle', 'grade_grades_history', (string)$history->id, (string)$gradetime,
            $action);
    }

    /**
     * Reconcile Program 2 current grade summary with Moodle Gradebook.
     *
     * This updates only local derived summary state.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @param array $options Options.
     * @return array Reconciliation result.
     */
    public static function reconcile_grade_summary(int $userid, int $gradeitemid, array $options = []): array {
        $before = repository::get_grade_summary($userid, $gradeitemid);
        $record = self::build_grade_summary_record($userid, $gradeitemid, $options);
        $changed = self::summary_changed($before, $record);
        $summaryid = repository::upsert_grade_summary($record);

        if ($options['recordrun'] ?? true) {
            self::record_reconcile_run(
                'h3_grade_summary_reconcile',
                $userid,
                (int)($record['courseid'] ?? 0),
                $gradeitemid,
                'complete',
                1,
                $changed ? 1 : 0,
                $changed ? 0 : 1
            );
        }

        return [
            'status' => $record['reconciliationstatus'],
            'summaryid' => $summaryid,
            'changed' => $changed,
            'latestattemptid' => $record['latestattemptid'] ?? null,
            'bestattemptid' => $record['bestattemptid'] ?? null,
            'latestgradeversionid' => $record['latestgradeversionid'] ?? null,
        ];
    }

    /**
     * Reconcile current summaries for current Moodle grade rows in a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Max rows to process.
     * @return array Reconciliation result.
     */
    public static function reconcile_course_grade_summaries(int $courseid, int $limit = 500): array {
        global $DB;

        $now = time();
        $seen = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $sql = 'SELECT gg.id, gg.userid, gg.itemid
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
              ORDER BY gg.timemodified DESC, gg.id DESC';
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, $limit);
        foreach ($rows as $row) {
            $seen++;
            try {
                $result = self::reconcile_grade_summary((int)$row->userid, (int)$row->itemid, ['recordrun' => false]);
                if (!empty($result['changed'])) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < 10) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $status = $failed > 0 ? 'complete_with_errors' : 'complete';
        repository::upsert_reconcile_run([
            'sourcekey' => source_identity::make_key('flwhistory', 'reconcile_run',
                'h3_grade_course:' . $courseid, (string)$now, 'grade_summary'),
            'runtype' => 'h3_grade_course_summary_reconcile',
            'scopejson' => ['courseid' => $courseid, 'limit' => $limit],
            'status' => $status,
            'courseid' => $courseid,
            'timestarted' => $now,
            'timefinished' => time(),
            'recordsseen' => $seen,
            'recordsupdated' => $updated,
            'recordsskipped' => $skipped,
            'recordsfailed' => $failed,
            'errorjson' => $errors ? ['errors' => $errors] : null,
        ]);

        return [
            'status' => $status,
            'recordsseen' => $seen,
            'recordsupdated' => $updated,
            'recordsskipped' => $skipped,
            'recordsfailed' => $failed,
        ];
    }

    /**
     * Fetch current derived grade summary.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @return \stdClass|null
     */
    public static function get_grade_summary(int $userid, int $gradeitemid): ?\stdClass {
        return repository::get_grade_summary($userid, $gradeitemid);
    }

    /**
     * Fetch grade versions for a learner.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Optional grade item id.
     * @param int $courseid Optional course id.
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_grade_versions(int $userid, int $gradeitemid = 0, int $courseid = 0,
            int $limit = 100): array {
        return repository::get_grade_versions($userid, $gradeitemid, $courseid, $limit);
    }

    /**
     * Record an explicit grade correction relation.
     *
     * @param int $newgradeversionid New grade version id.
     * @param int $oldgradeversionid Corrected grade version id.
     * @param string $reason Correction reason.
     * @param int|null $userid Actor id.
     * @return int Correction id.
     */
    public static function record_grade_correction(
        int $newgradeversionid,
        int $oldgradeversionid,
        string $reason,
        ?int $userid = null
    ): int {
        return repository::record_correction([
            'recordtable' => 'flwhist_grade_version',
            'recordid' => $newgradeversionid,
            'correctedtable' => 'flwhist_grade_version',
            'correctedid' => $oldgradeversionid,
            'correctiontype' => 'grade_correction',
            'reason' => $reason,
            'userid' => $userid,
        ]);
    }

    /**
     * Capture a Moodle grade event after source fact persistence.
     *
     * @param \core\event\base $event Moodle grade event.
     * @param array $options Options.
     * @return array Capture result.
     */
    private static function capture_grade_event(\core\event\base $event, array $options = []): array {
        $grade = self::grade_from_event($event);
        $gradeitemid = self::event_gradeitemid($event, $grade);
        $gradeitem = $gradeitemid > 0 ? self::fetch_grade_item($gradeitemid) : null;
        $sourceeventid = self::record_grade_source_event($event, $grade, $gradeitem, $options);

        if (!empty($options['simulatepostsourcefailure'])) {
            self::record_reconcile_run('h3_grade_capture', self::event_related_userid($event) ?? 0,
                (int)($event->courseid ?? 0), $gradeitemid, 'failed_after_source', 1, 0, 0, 1,
                ['message' => 'simulated_post_source_failure', 'sourceeventid' => $sourceeventid]);
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }

        $gradeversionid = null;
        $userid = self::event_related_userid($event) ?? ($grade ? (int)$grade->userid : 0);
        if ($grade) {
            $gradeversionid = self::record_moodle_grade_version($grade, [
                'sourceeventid' => $sourceeventid,
                'event' => $event,
                'action' => $options['action'] ?? null,
                'reason' => $options['reason'] ?? null,
            ]);
        } else if ($userid > 0 && $gradeitemid > 0) {
            $gradeversionid = self::record_grade_event_payload($event, $gradeitem, $sourceeventid, $options);
        }

        $reconcile = null;
        if ($userid > 0 && $gradeitemid > 0) {
            $reconcile = self::reconcile_grade_summary($userid, $gradeitemid, [
                'latestgradeversionid' => $gradeversionid,
            ]);
        }

        return [
            'status' => $gradeversionid ? 'captured' : 'source_recorded',
            'sourceeventid' => $sourceeventid,
            'gradeversionid' => $gradeversionid,
            'summaryid' => $reconcile['summaryid'] ?? null,
        ];
    }

    /**
     * Record a source event for the grade action.
     *
     * @param \core\event\base $event Moodle event.
     * @param \grade_grade|null $grade Grade object.
     * @param \grade_item|null $gradeitem Grade item.
     * @param array $options Options.
     * @return int Source event id.
     */
    private static function record_grade_source_event(
        \core\event\base $event,
        ?\grade_grade $grade,
        ?\grade_item $gradeitem,
        array $options
    ): int {
        $data = $event->get_data();
        $eventtime = (int)($data['timecreated'] ?? time());
        $gradeitemid = self::event_gradeitemid($event, $grade);
        $userid = self::event_related_userid($event) ?? ($grade ? (int)$grade->userid : null);
        $sourceid = (string)($data['objectid'] ?? '');
        if ($sourceid === '') {
            $sourceid = (string)$gradeitemid . ':' . (string)($userid ?? 0);
        }
        $eventtype = get_class($event) === \core\event\grade_deleted::class
            ? 'OFFICIAL_GRADE_DELETED'
            : 'OFFICIAL_GRADE_CHANGED';
        $sourcefactkey = source_identity::make_key('moodle', 'grade_event', $sourceid, (string)$eventtime, 'source_fact');
        $cmid = self::cmid_for_grade_item($gradeitem);
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course((int)($gradeitem->courseid ?? 0));

        $record = array_merge([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'gradebook',
            'sourcetype' => 'grade_event',
            'sourceid' => $sourceid,
            'sourceversion' => (string)$eventtime,
            'eventtype' => $eventtype,
            'sourcefactkey' => $sourcefactkey,
            'userid' => $userid,
            'courseid' => isset($gradeitem->courseid) ? (int)$gradeitem->courseid : ((int)($data['courseid'] ?? 0) ?: null),
            'cmid' => $cmid > 0 ? $cmid : null,
            'gradeitemid' => $gradeitemid > 0 ? $gradeitemid : null,
            'eventtime' => $eventtime,
            'status' => 'recorded',
            'normalizer' => 'h3_grade_capture',
            'summaryjson' => [
                'moodleevent' => $data['eventname'] ?? get_class($event),
                'objectid' => $data['objectid'] ?? null,
                'itemid' => $data['other']['itemid'] ?? $gradeitemid,
                'overridden' => $data['other']['overridden'] ?? ($grade->overridden ?? null),
                'finalgrade' => $data['other']['finalgrade'] ?? ($grade->finalgrade ?? null),
                'requestedaction' => $options['action'] ?? null,
            ],
            'payloadhash' => source_identity::payload_hash($data['other'] ?? []),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
            'usermodified' => isset($data['userid']) ? (int)$data['userid'] : null,
        ], self::mapping_fields($mapping));

        return history_service::record_source_event($record);
    }

    /**
     * Record a grade event when only event payload is available.
     *
     * @param \core\event\base $event Moodle event.
     * @param \grade_item|null $gradeitem Grade item.
     * @param int $sourceeventid Source event id.
     * @param array $options Options.
     * @return int Grade version id.
     */
    private static function record_grade_event_payload(
        \core\event\base $event,
        ?\grade_item $gradeitem,
        int $sourceeventid,
        array $options
    ): int {
        $sourceevent = repository::get_source_event($sourceeventid);
        $data = $event->get_data();
        $eventtime = (int)($data['timecreated'] ?? time());
        $gradeitemid = self::event_gradeitemid($event, null);
        $userid = self::event_related_userid($event);
        $cmid = self::cmid_for_grade_item($gradeitem);
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course((int)($gradeitem->courseid ?? 0));
        $action = self::normalise_action($options['action'] ?? self::ACTION_OTHER);

        $record = array_merge([
            'sourcekey' => source_identity::make_key('moodle', 'grade_event_payload',
                (string)($data['objectid'] ?? ($gradeitemid . ':' . $userid)), (string)$eventtime, $action),
            'sourcefactkey' => $sourceevent->sourcefactkey ?? null,
            'sourcefamily' => 'gradebook',
            'sourceeventid' => $sourceeventid,
            'userid' => $userid,
            'courseid' => isset($gradeitem->courseid) ? (int)$gradeitem->courseid : ((int)($data['courseid'] ?? 0) ?: null),
            'cmid' => $cmid > 0 ? $cmid : null,
            'gradeitemid' => $gradeitemid,
            'gradegradeid' => isset($data['objectid']) ? (int)$data['objectid'] : null,
            'itemmodule' => self::empty_to_null($gradeitem->itemmodule ?? null),
            'iteminstance' => isset($gradeitem->iteminstance) ? (int)$gradeitem->iteminstance : null,
            'itemnumber' => isset($gradeitem->itemnumber) ? (int)$gradeitem->itemnumber : null,
            'finalgrade' => self::float_or_null($data['other']['finalgrade'] ?? null),
            'graderid' => isset($data['userid']) ? (int)$data['userid'] : null,
            'action' => $action,
            'reason' => $options['reason'] ?? null,
            'gradetime' => $eventtime,
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
            'summaryjson' => [
                'eventpayloadonly' => true,
                'moodleevent' => $data['eventname'] ?? get_class($event),
                'overridden' => $data['other']['overridden'] ?? null,
            ],
        ], self::mapping_fields($mapping));

        return repository::upsert_grade_version($record);
    }

    /**
     * Build derived grade summary fields.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @param array $options Options.
     * @return array Summary record.
     */
    private static function build_grade_summary_record(int $userid, int $gradeitemid, array $options): array {
        $gradeitem = self::fetch_grade_item($gradeitemid);
        if (!$gradeitem) {
            throw new \invalid_parameter_exception('Grade item does not exist.');
        }
        $grade = self::fetch_grade($userid, $gradeitemid);
        $cmid = self::cmid_for_grade_item($gradeitem);
        $latestattempt = self::latest_attempt_for_grade_item($userid, $gradeitem, $cmid);
        $bestattempt = self::best_attempt_for_grade_item($userid, $gradeitem, $cmid);
        $latestgradeversion = !empty($options['latestgradeversionid'])
            ? self::grade_version_by_id((int)$options['latestgradeversionid'])
            : self::latest_grade_version($userid, $gradeitemid, (int)$gradeitem->courseid);

        $record = [
            'sourcekey' => source_identity::make_key('flwhistory', 'grade_summary',
                (string)$userid . ':' . (string)$gradeitemid, history_policy::NORMALIZATION_POLICY_VERSION, 'current'),
            'sourcefamily' => 'gradebook',
            'userid' => $userid,
            'courseid' => (int)$gradeitem->courseid,
            'cmid' => $cmid > 0 ? $cmid : null,
            'gradeitemid' => $gradeitemid,
            'gradegradeid' => $grade ? (int)$grade->id : null,
            'itemmodule' => self::empty_to_null($gradeitem->itemmodule ?? null),
            'iteminstance' => isset($gradeitem->iteminstance) ? (int)$gradeitem->iteminstance : null,
            'itemnumber' => isset($gradeitem->itemnumber) ? (int)$gradeitem->itemnumber : null,
            'latestattemptid' => $latestattempt ? (int)$latestattempt->id : null,
            'latestattemptsourceid' => $latestattempt->sourceattemptid ?? null,
            'latestattemptscore' => self::attempt_score($latestattempt),
            'latestattempttime' => self::attempt_time($latestattempt),
            'bestattemptid' => $bestattempt ? (int)$bestattempt->id : null,
            'bestattemptsourceid' => $bestattempt->sourceattemptid ?? null,
            'bestattemptscore' => self::attempt_score($bestattempt),
            'bestattempttime' => self::attempt_time($bestattempt),
            'officialgradegradeid' => $grade ? (int)$grade->id : null,
            'officialrawgrade' => $grade ? self::float_or_null($grade->rawgrade ?? null) : null,
            'officialfinalgrade' => $grade ? self::float_or_null($grade->finalgrade ?? null) : null,
            'officialgradetime' => $grade ? (int)($grade->timemodified ?? 0) ?: null : null,
            'latestgradeversionid' => $latestgradeversion ? (int)$latestgradeversion->id : null,
            'reconciliationstatus' => $grade ? 'current' : 'official_grade_missing',
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record['summaryjson'] = [
            'official_moodle_grade' => [
                'gradegradeid' => $record['officialgradegradeid'],
                'rawgrade' => $record['officialrawgrade'],
                'finalgrade' => $record['officialfinalgrade'],
                'timemodified' => $record['officialgradetime'],
            ],
            'latest_attempt' => self::attempt_summary($latestattempt),
            'best_attempt' => self::attempt_summary($bestattempt),
            'latest_grade_version_id' => $record['latestgradeversionid'],
            'reconciledat' => time(),
        ];
        return $record;
    }

    /**
     * Return grade object from an event or DB fetch.
     *
     * @param \core\event\base $event Moodle event.
     * @return \grade_grade|null
     */
    private static function grade_from_event(\core\event\base $event): ?\grade_grade {
        try {
            if (method_exists($event, 'get_grade')) {
                $grade = $event->get_grade();
                if ($grade instanceof \grade_grade) {
                    self::ensure_grade_item($grade);
                    return $grade;
                }
            }
        } catch (\Throwable $e) {
            // Restored events and some tests do not carry the transient grade object.
        }

        $gradeid = (int)($event->objectid ?? 0);
        if ($gradeid <= 0) {
            return null;
        }
        $grade = \grade_grade::fetch(['id' => $gradeid]);
        if ($grade instanceof \grade_grade) {
            self::ensure_grade_item($grade);
            return $grade;
        }
        return null;
    }

    /**
     * Fetch a Moodle grade object.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @return \grade_grade|null
     */
    private static function fetch_grade(int $userid, int $gradeitemid): ?\grade_grade {
        $grade = \grade_grade::fetch(['userid' => $userid, 'itemid' => $gradeitemid]);
        if ($grade instanceof \grade_grade) {
            self::ensure_grade_item($grade);
            return $grade;
        }
        return null;
    }

    /**
     * Fetch a Moodle grade item.
     *
     * @param int $gradeitemid Grade item id.
     * @return \grade_item|null
     */
    private static function fetch_grade_item(int $gradeitemid): ?\grade_item {
        $gradeitem = \grade_item::fetch(['id' => $gradeitemid]);
        return $gradeitem instanceof \grade_item ? $gradeitem : null;
    }

    /**
     * Ensure a grade object has its grade_item property populated.
     *
     * @param \grade_grade $grade Grade object.
     */
    private static function ensure_grade_item(\grade_grade $grade): void {
        if (empty($grade->grade_item) && !empty($grade->itemid)) {
            $gradeitem = self::fetch_grade_item((int)$grade->itemid);
            if ($gradeitem) {
                $grade->grade_item = $gradeitem;
            }
        }
    }

    /**
     * Return grade item for a Moodle grade.
     *
     * @param \grade_grade $grade Grade object.
     * @return \grade_item|null
     */
    private static function grade_item_for_grade(\grade_grade $grade): ?\grade_item {
        self::ensure_grade_item($grade);
        return $grade->grade_item instanceof \grade_item ? $grade->grade_item : self::fetch_grade_item((int)$grade->itemid);
    }

    /**
     * Determine grade item id from event/grade.
     *
     * @param \core\event\base $event Moodle event.
     * @param \grade_grade|null $grade Grade object.
     * @return int
     */
    private static function event_gradeitemid(\core\event\base $event, ?\grade_grade $grade): int {
        $other = $event->other ?? [];
        if (!empty($other['itemid'])) {
            return (int)$other['itemid'];
        }
        return $grade && !empty($grade->itemid) ? (int)$grade->itemid : 0;
    }

    /**
     * Return related learner id from an event.
     *
     * @param \core\event\base $event Moodle event.
     * @return int|null
     */
    private static function event_related_userid(\core\event\base $event): ?int {
        if (!empty($event->relateduserid)) {
            return (int)$event->relateduserid;
        }
        $other = $event->other ?? [];
        if (!empty($other['userid'])) {
            return (int)$other['userid'];
        }
        return null;
    }

    /**
     * Find a grade item course module.
     *
     * @param \grade_item|null $gradeitem Grade item.
     * @return int
     */
    private static function cmid_for_grade_item(?\grade_item $gradeitem): int {
        global $DB;

        if (!$gradeitem || (string)($gradeitem->itemtype ?? '') !== 'mod' || empty($gradeitem->itemmodule)
                || empty($gradeitem->iteminstance) || empty($gradeitem->courseid)) {
            return 0;
        }
        $moduleid = $DB->get_field('modules', 'id', ['name' => $gradeitem->itemmodule], IGNORE_MISSING);
        if (!$moduleid) {
            return 0;
        }
        $cmid = $DB->get_field('course_modules', 'id', [
            'course' => (int)$gradeitem->courseid,
            'module' => (int)$moduleid,
            'instance' => (int)$gradeitem->iteminstance,
        ], IGNORE_MISSING);
        return $cmid ? (int)$cmid : 0;
    }

    /**
     * Fetch latest Moodle grade history row for a grade.
     *
     * @param \grade_grade $grade Grade object.
     * @return \stdClass|null
     */
    private static function latest_grade_history_row(\grade_grade $grade): ?\stdClass {
        global $DB;

        if (empty($grade->id) || empty($grade->itemid) || empty($grade->userid)) {
            return null;
        }
        $sql = 'SELECT *
                  FROM {grade_grades_history}
                 WHERE oldid = :oldid
                   AND itemid = :itemid
                   AND userid = :userid
              ORDER BY timemodified DESC, id DESC';
        $records = $DB->get_records_sql($sql, [
            'oldid' => (int)$grade->id,
            'itemid' => (int)$grade->itemid,
            'userid' => (int)$grade->userid,
        ], 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Fetch previous final grade from Moodle grade history.
     *
     * @param \stdClass|null $history Current history row.
     * @return float|null
     */
    private static function previous_grade_from_history(?\stdClass $history): ?float {
        global $DB;

        if (!$history || empty($history->oldid) || empty($history->itemid) || empty($history->userid)) {
            return null;
        }
        $sql = 'SELECT finalgrade
                  FROM {grade_grades_history}
                 WHERE oldid = :oldid
                   AND itemid = :itemid
                   AND userid = :userid
                   AND (timemodified < :timemodifiedbefore
                        OR (timemodified = :timemodifiedequal AND id < :id))
              ORDER BY timemodified DESC, id DESC';
        $records = $DB->get_records_sql($sql, [
            'oldid' => (int)$history->oldid,
            'itemid' => (int)$history->itemid,
            'userid' => (int)$history->userid,
            'timemodifiedbefore' => (int)($history->timemodified ?? 0),
            'timemodifiedequal' => (int)($history->timemodified ?? 0),
            'id' => (int)$history->id,
        ], 0, 1);
        if (!$records) {
            return null;
        }
        $row = reset($records);
        return self::float_or_null($row->finalgrade ?? null);
    }

    /**
     * Fetch previous final grade from local grade versions.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @return float|null
     */
    private static function previous_grade_from_local_history(int $userid, int $gradeitemid): ?float {
        $versions = repository::get_grade_versions($userid, $gradeitemid, 0, 1);
        if (!$versions) {
            return null;
        }
        return self::float_or_null($versions[0]->finalgrade ?? null);
    }

    /**
     * Determine H3 grade action from reliable source evidence.
     *
     * @param \grade_grade $grade Grade object.
     * @param \grade_item|null $gradeitem Grade item.
     * @param \stdClass|null $history History row.
     * @param float|null $previousgrade Previous grade.
     * @param array $extra Extra context.
     * @return string H3 action.
     */
    private static function classify_grade_action(
        \grade_grade $grade,
        ?\grade_item $gradeitem,
        ?\stdClass $history,
        ?float $previousgrade,
        array $extra
    ): string {
        if (!empty($extra['action'])) {
            return self::normalise_action($extra['action']);
        }
        if (!empty($grade->overridden) || self::event_other_flag($extra['event'] ?? null, 'overridden')) {
            return self::ACTION_TEACHER_OVERRIDE;
        }
        if ($history) {
            return self::classify_history_action($history, $gradeitem, $previousgrade, $extra);
        }
        return $previousgrade === null ? self::ACTION_INITIAL : self::ACTION_OTHER;
    }

    /**
     * Determine H3 grade action from grade_grades_history.
     *
     * @param \stdClass $history Grade history row.
     * @param \grade_item|null $gradeitem Grade item.
     * @param float|null $previousgrade Previous grade.
     * @param array $extra Extra context.
     * @return string H3 action.
     */
    private static function classify_history_action(
        \stdClass $history,
        ?\grade_item $gradeitem,
        ?float $previousgrade,
        array $extra
    ): string {
        if (!empty($extra['action'])) {
            return self::normalise_action($extra['action']);
        }
        $source = strtolower((string)($history->source ?? ''));
        if (str_contains($source, 'import')) {
            return self::ACTION_IMPORT;
        }
        if (str_contains($source, 'regrade')) {
            return self::ACTION_REGRADE;
        }
        if (!empty($history->overridden) || str_contains($source, 'gradebook') || str_contains($source, 'manual')) {
            return self::ACTION_TEACHER_OVERRIDE;
        }
        if ((int)($history->action ?? 0) === GRADE_HISTORY_INSERT || $previousgrade === null) {
            return self::ACTION_INITIAL;
        }
        if ($gradeitem && self::attempt_count_for_grade_item((int)$history->userid, $gradeitem) > 1) {
            return self::ACTION_RETAKE;
        }
        return self::ACTION_OTHER;
    }

    /**
     * Normalize H3 action.
     *
     * @param mixed $action Raw action.
     * @return string
     */
    private static function normalise_action($action): string {
        $action = strtoupper(trim((string)$action));
        $allowed = [
            self::ACTION_INITIAL,
            self::ACTION_RETAKE,
            self::ACTION_REGRADE,
            self::ACTION_TEACHER_OVERRIDE,
            self::ACTION_CORRECTION,
            self::ACTION_IMPORT,
            self::ACTION_OTHER,
        ];
        return in_array($action, $allowed, true) ? $action : self::ACTION_OTHER;
    }

    /**
     * Return grade time.
     *
     * @param \grade_grade $grade Grade object.
     * @param \stdClass|null $history History row.
     * @return int
     */
    private static function grade_time(\grade_grade $grade, ?\stdClass $history): int {
        return (int)($history->timemodified ?? $grade->timemodified ?? time());
    }

    /**
     * Return trustworthy actor id.
     *
     * @param \grade_grade $grade Grade object.
     * @param \stdClass|null $history History row.
     * @param array $extra Extra context.
     * @return int|null
     */
    private static function grade_actor(\grade_grade $grade, ?\stdClass $history, array $extra): ?int {
        $event = $extra['event'] ?? null;
        if ($event instanceof \core\event\base && !empty($event->userid)) {
            return (int)$event->userid;
        }
        if ($history) {
            return self::history_actor($history);
        }
        return isset($grade->usermodified) ? (int)$grade->usermodified : null;
    }

    /**
     * Return history actor id.
     *
     * @param \stdClass $history History row.
     * @return int|null
     */
    private static function history_actor(\stdClass $history): ?int {
        if (!empty($history->loggeduser)) {
            return (int)$history->loggeduser;
        }
        return !empty($history->usermodified) ? (int)$history->usermodified : null;
    }

    /**
     * Return source-supplied grade reason.
     *
     * @param \stdClass|null $history History row.
     * @param array $extra Extra context.
     * @return string|null
     */
    private static function grade_reason(?\stdClass $history, array $extra): ?string {
        if (!empty($extra['reason'])) {
            return substr((string)$extra['reason'], 0, 255);
        }
        return $history ? self::history_reason($history, $extra) : null;
    }

    /**
     * Return source-supplied history reason.
     *
     * @param \stdClass $history History row.
     * @param array $extra Extra context.
     * @return string|null
     */
    private static function history_reason(\stdClass $history, array $extra): ?string {
        if (!empty($extra['reason'])) {
            return substr((string)$extra['reason'], 0, 255);
        }
        return !empty($history->source) ? substr((string)$history->source, 0, 255) : null;
    }

    /**
     * Return grade payload.
     *
     * @param \grade_grade $grade Grade object.
     * @param \grade_item|null $gradeitem Grade item.
     * @param \stdClass|null $history History row.
     * @param array $extra Extra context.
     * @return array
     */
    private static function grade_summary_payload(
        \grade_grade $grade,
        ?\grade_item $gradeitem,
        ?\stdClass $history,
        array $extra
    ): array {
        return [
            'gradehistoryid' => $history->id ?? null,
            'gradehistoryaction' => $history->action ?? null,
            'historysource' => $history->source ?? null,
            'itemmodule' => $gradeitem->itemmodule ?? null,
            'iteminstance' => $gradeitem->iteminstance ?? null,
            'overridden' => $grade->overridden ?? null,
            'excluded' => $grade->excluded ?? null,
            'aggregationstatus' => $grade->aggregationstatus ?? null,
            'sourceeventid' => $extra['sourceeventid'] ?? null,
        ];
    }

    /**
     * Return history payload.
     *
     * @param \stdClass $history History row.
     * @param \grade_item|null $gradeitem Grade item.
     * @param array $extra Extra context.
     * @return array
     */
    private static function history_summary_payload(\stdClass $history, ?\grade_item $gradeitem, array $extra): array {
        return [
            'gradehistoryaction' => $history->action ?? null,
            'historysource' => $history->source ?? null,
            'rawgrademax' => $history->rawgrademax ?? null,
            'rawgrademin' => $history->rawgrademin ?? null,
            'overridden' => $history->overridden ?? null,
            'excluded' => $history->excluded ?? null,
            'itemmodule' => $gradeitem->itemmodule ?? null,
            'iteminstance' => $gradeitem->iteminstance ?? null,
            'sourceeventid' => $extra['sourceeventid'] ?? null,
        ];
    }

    /**
     * Get sourcefactkey from source event.
     *
     * @param array $extra Extra context.
     * @param string $fallback Fallback key.
     * @return string
     */
    private static function sourcefactkey_from_extra(array $extra, string $fallback): string {
        if (empty($extra['sourceeventid'])) {
            return $fallback;
        }
        $sourceevent = repository::get_source_event((int)$extra['sourceeventid']);
        return !empty($sourceevent->sourcefactkey) ? (string)$sourceevent->sourcefactkey : $fallback;
    }

    /**
     * Fetch latest local grade version.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @param int $courseid Course id.
     * @return \stdClass|null
     */
    private static function latest_grade_version(int $userid, int $gradeitemid, int $courseid = 0): ?\stdClass {
        $versions = repository::get_grade_versions($userid, $gradeitemid, $courseid, 1);
        return $versions ? $versions[0] : null;
    }

    /**
     * Fetch grade version by id.
     *
     * @param int $id Grade version id.
     * @return \stdClass|null
     */
    private static function grade_version_by_id(int $id): ?\stdClass {
        global $DB;

        return $id > 0 ? ($DB->get_record('flwhist_grade_version', ['id' => $id]) ?: null) : null;
    }

    /**
     * Return all local attempts for a grade item.
     *
     * @param int $userid User id.
     * @param \grade_item|null $gradeitem Grade item.
     * @param int $cmid Course module id.
     * @return array
     */
    private static function attempts_for_grade_item(int $userid, ?\grade_item $gradeitem, int $cmid = 0): array {
        global $DB;

        if (!$gradeitem) {
            return [];
        }
        $cmid = $cmid > 0 ? $cmid : self::cmid_for_grade_item($gradeitem);
        if ($cmid <= 0) {
            return [];
        }
        return array_values($DB->get_records('flwhist_attempt', [
            'userid' => $userid,
            'courseid' => (int)$gradeitem->courseid,
            'cmid' => $cmid,
        ]));
    }

    /**
     * Count local attempts for a grade item.
     *
     * @param int $userid User id.
     * @param \grade_item|null $gradeitem Grade item.
     * @return int
     */
    private static function attempt_count_for_grade_item(int $userid, ?\grade_item $gradeitem): int {
        return count(self::attempts_for_grade_item($userid, $gradeitem));
    }

    /**
     * Return latest local attempt for a grade item.
     *
     * @param int $userid User id.
     * @param \grade_item|null $gradeitem Grade item.
     * @param int $cmid Course module id.
     * @return \stdClass|null
     */
    private static function latest_attempt_for_grade_item(int $userid, ?\grade_item $gradeitem, int $cmid): ?\stdClass {
        $attempts = self::attempts_for_grade_item($userid, $gradeitem, $cmid);
        usort($attempts, function($left, $right): int {
            return self::attempt_time($right) <=> self::attempt_time($left) ?: ((int)$right->id <=> (int)$left->id);
        });
        return $attempts ? $attempts[0] : null;
    }

    /**
     * Return best local attempt for a grade item.
     *
     * @param int $userid User id.
     * @param \grade_item|null $gradeitem Grade item.
     * @param int $cmid Course module id.
     * @return \stdClass|null
     */
    private static function best_attempt_for_grade_item(int $userid, ?\grade_item $gradeitem, int $cmid): ?\stdClass {
        $attempts = self::attempts_for_grade_item($userid, $gradeitem, $cmid);
        usort($attempts, function($left, $right): int {
            $scorecompare = (self::attempt_score($right) ?? -INF) <=> (self::attempt_score($left) ?? -INF);
            return $scorecompare ?: (self::attempt_time($right) <=> self::attempt_time($left));
        });
        return $attempts ? $attempts[0] : null;
    }

    /**
     * Return normalized attempt score.
     *
     * @param \stdClass|null $attempt Attempt row.
     * @return float|null
     */
    private static function attempt_score(?\stdClass $attempt): ?float {
        if (!$attempt) {
            return null;
        }
        if ($attempt->scaledscore !== null) {
            return (float)$attempt->scaledscore;
        }
        return $attempt->rawscore !== null ? (float)$attempt->rawscore : null;
    }

    /**
     * Return attempt finish time.
     *
     * @param \stdClass|null $attempt Attempt row.
     * @return int|null
     */
    private static function attempt_time(?\stdClass $attempt): ?int {
        if (!$attempt) {
            return null;
        }
        return (int)($attempt->timefinish ?? $attempt->timemodified ?? $attempt->timecreated ?? 0);
    }

    /**
     * Return compact attempt summary.
     *
     * @param \stdClass|null $attempt Attempt row.
     * @return array|null
     */
    private static function attempt_summary(?\stdClass $attempt): ?array {
        if (!$attempt) {
            return null;
        }
        return [
            'id' => (int)$attempt->id,
            'sourceattemptid' => $attempt->sourceattemptid ?? null,
            'attemptno' => isset($attempt->attemptno) ? (int)$attempt->attemptno : null,
            'score' => self::attempt_score($attempt),
            'timefinish' => self::attempt_time($attempt),
        ];
    }

    /**
     * Compare derived summary fields.
     *
     * @param \stdClass|null $before Existing row.
     * @param array $after New fields.
     * @return bool
     */
    private static function summary_changed(?\stdClass $before, array $after): bool {
        if (!$before) {
            return true;
        }
        $fields = [
            'latestattemptid', 'latestattemptscore', 'bestattemptid', 'bestattemptscore',
            'officialgradegradeid', 'officialrawgrade', 'officialfinalgrade', 'officialgradetime',
            'latestgradeversionid', 'reconciliationstatus',
        ];
        foreach ($fields as $field) {
            $left = $before->{$field} ?? null;
            $right = $after[$field] ?? null;
            if (!self::summary_values_equal($left, $right)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare summary scalar values with tolerance for DB decimal formatting.
     *
     * @param mixed $left Stored value.
     * @param mixed $right New value.
     * @return bool
     */
    private static function summary_values_equal($left, $right): bool {
        if (($left === null || $left === '') && ($right === null || $right === '')) {
            return true;
        }
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float)$left - (float)$right) < 0.00001;
        }
        return (string)$left === (string)$right;
    }

    /**
     * Record reconciliation diagnostics.
     *
     * @param string $runtype Run type.
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $gradeitemid Grade item id.
     * @param string $status Status.
     * @param int $seen Seen records.
     * @param int $updated Updated records.
     * @param int $skipped Skipped records.
     * @param int $failed Failed records.
     * @param array|null $errors Error payload.
     */
    private static function record_reconcile_run(
        string $runtype,
        int $userid,
        int $courseid,
        int $gradeitemid,
        string $status,
        int $seen = 1,
        int $updated = 0,
        int $skipped = 0,
        int $failed = 0,
        ?array $errors = null
    ): void {
        $now = time();
        repository::upsert_reconcile_run([
            'sourcekey' => source_identity::make_key('flwhistory', 'reconcile_run',
                $runtype . ':' . $userid . ':' . $gradeitemid, (string)$now, 'grade_summary'),
            'runtype' => $runtype,
            'scopejson' => ['userid' => $userid, 'courseid' => $courseid, 'gradeitemid' => $gradeitemid],
            'status' => $status,
            'userid' => $userid > 0 ? $userid : null,
            'courseid' => $courseid > 0 ? $courseid : null,
            'timestarted' => $now,
            'timefinished' => time(),
            'recordsseen' => $seen,
            'recordsupdated' => $updated,
            'recordsskipped' => $skipped,
            'recordsfailed' => $failed,
            'errorjson' => $errors,
        ]);
    }

    /**
     * Return mapping fields supported by grade version/source rows.
     *
     * @param array $mapping Mapping row.
     * @return array
     */
    private static function mapping_fields(array $mapping): array {
        $fields = [];
        foreach (['worldid', 'stageid', 'unitid', 'lessonid', 'componentid', 'activityid', 'assessmentid'] as $field) {
            if (!empty($mapping[$field])) {
                $fields[$field] = $mapping[$field];
            }
        }
        return $fields;
    }

    /**
     * Return event boolean flag.
     *
     * @param mixed $event Event.
     * @param string $name Flag name.
     * @return bool
     */
    private static function event_other_flag($event, string $name): bool {
        if (!$event instanceof \core\event\base) {
            return false;
        }
        $other = $event->other ?? [];
        return !empty($other[$name]);
    }

    /**
     * Cast to float/null.
     *
     * @param mixed $value Raw value.
     * @return float|null
     */
    private static function float_or_null($value): ?float {
        return $value === null || $value === '' ? null : (float)$value;
    }

    /**
     * Convert empty values to null.
     *
     * @param mixed $value Raw value.
     * @return mixed
     */
    private static function empty_to_null($value) {
        return $value === '' ? null : $value;
    }
}

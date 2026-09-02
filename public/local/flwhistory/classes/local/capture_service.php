<?php
// Production-safe H2 capture service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Captures source-grounded history facts from verified Moodle/FLW events.
 */
class capture_service {
    /** Maximum question rows captured synchronously for one quiz attempt. */
    private const QUESTION_CAPTURE_LIMIT = 200;

    /**
     * Capture a verified quiz attempt lifecycle event.
     *
     * @param \core\event\base $event Moodle event.
     * @param array $options Test/developer options.
     * @return array Capture result.
     */
    public static function capture_quiz_attempt_event(\core\event\base $event, array $options = []): array {
        $sourceeventid = self::record_source_event_from_event($event, [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'quiz_attempt',
            'eventtype' => self::educational_event_for_event($event),
        ]);

        if (!empty($options['simulatepostsourcefailure'])) {
            self::record_capture_failure('h2_quiz_attempt_capture', $event, $sourceeventid, 'simulated_post_source_failure');
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }

        try {
            return self::record_quiz_attempt_details($event, $sourceeventid);
        } catch (\Throwable $e) {
            self::record_capture_failure('h2_quiz_attempt_capture', $event, $sourceeventid, $e->getMessage());
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }
    }

    /**
     * Capture a Moodle SCORM score or status event as one replay-safe attempt.
     *
     * @param \core\event\base $event Moodle event.
     * @param array $options Test/developer options.
     * @return array Capture result.
     */
    public static function capture_scorm_attempt_event(\core\event\base $event, array $options = []): array {
        $attemptid = (int)($event->other['attemptid'] ?? 0);
        $sourceeventid = self::record_source_event_from_event($event, [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'scorm',
            'sourcetype' => 'scorm_cmi_element',
            'eventtype' => self::scorm_event_type($event),
            'sourceattemptid' => $attemptid > 0 ? (string)$attemptid : null,
            'attemptid' => $attemptid > 0 ? $attemptid : null,
        ]);

        if (!empty($options['simulatepostsourcefailure'])) {
            self::record_capture_failure('h2_scorm_attempt_capture', $event, $sourceeventid,
                'simulated_post_source_failure');
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }

        try {
            return self::record_scorm_attempt_details(
                $attemptid,
                $sourceeventid,
                self::event_cmid($event),
                (int)($event->courseid ?? 0)
            );
        } catch (\Throwable $e) {
            self::record_capture_failure('h2_scorm_attempt_capture', $event, $sourceeventid, $e->getMessage());
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }
    }

    /**
     * Idempotently repair one SCORM attempt missed before observer deployment.
     *
     * @param int $attemptid Moodle scorm_attempt id.
     * @return array Repair result.
     */
    public static function repair_scorm_attempt(int $attemptid): array {
        if ($attemptid <= 0) {
            throw new \invalid_parameter_exception('A positive SCORM attempt id is required.');
        }

        $context = self::scorm_attempt_context($attemptid);
        if ($context === null) {
            return ['status' => 'not_found', 'attemptid' => $attemptid];
        }

        $sourceeventid = self::record_scorm_repair_source_event($context);
        $result = self::record_scorm_attempt_details(
            $attemptid,
            $sourceeventid,
            (int)$context['cmid'],
            (int)$context['courseid']
        );
        $result['repair'] = true;
        return $result;
    }

    /**
     * Capture a Moodle course module completion transition.
     *
     * @param \core\event\course_module_completion_updated $event Moodle event.
     * @param array $options Test/developer options.
     * @return array Capture result.
     */
    public static function capture_course_module_completion(
        \core\event\course_module_completion_updated $event,
        array $options = []
    ): array {
        $sourceeventid = self::record_source_event_from_event($event, [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'completion',
            'sourcetype' => 'course_module_completion',
            'eventtype' => 'CHECKPOINT_COMPLETED',
        ]);

        if (!empty($options['simulatepostsourcefailure'])) {
            self::record_capture_failure('h2_completion_capture', $event, $sourceeventid, 'simulated_post_source_failure');
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }

        try {
            return self::record_completion_details($event, $sourceeventid);
        } catch (\Throwable $e) {
            self::record_capture_failure('h2_completion_capture', $event, $sourceeventid, $e->getMessage());
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }
    }

    /**
     * Capture a Moodle course completion event as a source fact.
     *
     * @param \core\event\course_completion_updated $event Moodle event.
     * @return array Capture result.
     */
    public static function capture_course_completion_event(\core\event\course_completion_updated $event): array {
        $sourceeventid = self::record_source_event_from_event($event, [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'completion',
            'sourcetype' => 'course_completion',
            'eventtype' => 'CHECKPOINT_COMPLETED',
        ]);
        return ['status' => 'source_recorded', 'sourceeventid' => $sourceeventid];
    }

    /**
     * Capture the verified FLW VR Room attempt submitted event.
     *
     * @param \core\event\base $event Moodle event.
     * @param array $options Test/developer options.
     * @return array Capture result.
     */
    public static function capture_flwvrroom_attempt_submitted(\core\event\base $event, array $options = []): array {
        $sourceeventid = self::record_source_event_from_event($event, [
            'sourcesystem' => 'flwvrroom',
            'sourcefamily' => 'flwvrroom',
            'sourcetype' => 'flwvrroom_attempt',
            'eventtype' => 'SPEAKING_ATTEMPTED',
        ]);

        if (!empty($options['simulatepostsourcefailure'])) {
            self::record_capture_failure('h2_flwvrroom_capture', $event, $sourceeventid, 'simulated_post_source_failure');
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }

        try {
            return self::record_flwvrroom_attempt_details($event, $sourceeventid);
        } catch (\Throwable $e) {
            self::record_capture_failure('h2_flwvrroom_capture', $event, $sourceeventid, $e->getMessage());
            return ['status' => 'failed_after_source', 'sourceeventid' => $sourceeventid];
        }
    }

    /**
     * Refresh aggregate course coverage facts for captured source events.
     *
     * @param int $limit Maximum aggregate rows to process.
     * @return array Refresh result.
     */
    public static function refresh_capture_coverage(int $limit = 500): array {
        global $DB;

        $now = time();
        $sourcekey = source_identity::make_key('flwhistory', 'reconcile_run', 'h2_capture_coverage_refresh', (string)$now);
        repository::upsert_reconcile_run([
            'sourcekey' => $sourcekey,
            'runtype' => 'h2_capture_coverage_refresh',
            'scopejson' => ['limit' => $limit],
            'status' => 'running',
            'timestarted' => $now,
        ]);

        $sql = "SELECT " . $DB->sql_concat_join("':'", ['sourcefamily', 'courseid'])
            . " AS id, sourcefamily, courseid, MIN(eventtime) AS earliestevent,
                     MAX(eventtime) AS latestevent, COUNT(1) AS eventcount
                  FROM {flwhist_source_event}
                 WHERE courseid IS NOT NULL
                   AND sourcefamily IN ('quiz', 'scorm', 'completion', 'flwvrroom')
              GROUP BY sourcefamily, courseid
              ORDER BY latestevent DESC";
        $records = $DB->get_records_sql($sql, [], 0, $limit);
        $created = 0;
        foreach ($records as $record) {
            coverage_service::record_coverage([
                'scopelevel' => 'course',
                'sourcefamily' => (string)$record->sourcefamily,
                'courseid' => (int)$record->courseid,
                'timerangestart' => (int)$record->earliestevent,
                'timerangeend' => (int)$record->latestevent,
                'coveragestatus' => history_policy::COVERAGE_NOT_BACKFILLED,
                'eventcount' => (int)$record->eventcount,
                'capturestartedat' => (int)$record->earliestevent,
                'earliestreliableeventat' => (int)$record->earliestevent,
                'latestreconciledat' => (int)$record->latestevent,
                'reasoncode' => 'H2_CAPTURE_ACTIVE_BACKFILL_PENDING',
                'detailsjson' => ['task' => 'refresh_capture_coverage'],
            ]);
            $created++;
        }

        repository::upsert_reconcile_run([
            'sourcekey' => $sourcekey,
            'runtype' => 'h2_capture_coverage_refresh',
            'scopejson' => ['limit' => $limit],
            'status' => 'complete',
            'timestarted' => $now,
            'timefinished' => time(),
            'recordsseen' => count($records),
            'recordscreated' => $created,
        ]);

        return ['status' => 'complete', 'recordsseen' => count($records), 'recordscreated' => $created];
    }

    /**
     * Record the source event first, with Program 1 mapping if available.
     *
     * @param \core\event\base $event Moodle event.
     * @param array $overrides Source field overrides.
     * @return int Source event id.
     */
    private static function record_source_event_from_event(\core\event\base $event, array $overrides): int {
        $data = $event->get_data();
        $eventtime = (int)($data['timecreated'] ?? time());
        $cmid = self::event_cmid($event);
        $courseid = isset($data['courseid']) ? (int)$data['courseid'] : 0;
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course($courseid);
        $sourceid = (string)($data['objectid'] ?? '');
        if ($sourceid === '') {
            $sourceid = (string)($data['contextinstanceid'] ?? $eventtime);
        }

        $sourcesystem = (string)($overrides['sourcesystem'] ?? 'moodle');
        $sourcetype = (string)($overrides['sourcetype'] ?? ($data['target'] ?? 'event'));
        $sourceversion = (string)($overrides['sourceversion'] ?? $eventtime);
        $eventtype = (string)($overrides['eventtype'] ?? self::educational_event_for_event($event));
        $sourcefactkey = source_identity::make_key($sourcesystem, $sourcetype, $sourceid, $sourceversion, 'source_fact');
        $summary = [
            'moodleevent' => $data['eventname'] ?? get_class($event),
            'crud' => $data['crud'] ?? null,
            'edulevel' => $data['edulevel'] ?? null,
            'component' => $data['component'] ?? null,
            'target' => $data['target'] ?? null,
            'action' => $data['action'] ?? null,
            'objecttable' => $data['objecttable'] ?? null,
            'objectid' => $data['objectid'] ?? null,
            'contextid' => $data['contextid'] ?? null,
            'contextinstanceid' => $data['contextinstanceid'] ?? null,
            'relateduserid' => $data['relateduserid'] ?? null,
            'mappingstatus' => $mapping['status'] ?? 'unresolved',
            'other' => self::bounded_event_other($data['other'] ?? []),
        ];

        $record = [
            'sourcesystem' => $sourcesystem,
            'sourcefamily' => (string)($overrides['sourcefamily'] ?? history_policy::source_family($sourcesystem, $sourcetype)),
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'sourceversion' => $sourceversion,
            'eventtype' => $eventtype,
            'sourcefactkey' => $sourcefactkey,
            'userid' => self::event_related_userid($event),
            'courseid' => $courseid > 0 ? $courseid : null,
            'cmid' => $cmid > 0 ? $cmid : null,
            'sourceattemptid' => array_key_exists('sourceattemptid', $overrides)
                ? $overrides['sourceattemptid']
                : $sourceid,
            'attemptid' => array_key_exists('attemptid', $overrides)
                ? $overrides['attemptid']
                : (isset($data['objectid']) ? (int)$data['objectid'] : null),
            'eventtime' => $eventtime,
            'status' => ($mapping['status'] ?? 'unresolved') === 'resolved' ? 'recorded' : 'unresolved_mapping',
            'normalizer' => 'h2_capture',
            'summaryjson' => $summary,
            'payloadhash' => source_identity::payload_hash($summary),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
            'usermodified' => isset($data['userid']) ? (int)$data['userid'] : null,
        ];
        $record = array_merge($record, self::mapping_fields($mapping));
        $sourceeventid = history_service::record_source_event($record);

        coverage_service::record_coverage([
            'scopelevel' => $record['userid'] ? 'learner' : 'course',
            'sourcefamily' => $record['sourcefamily'],
            'userid' => $record['userid'],
            'courseid' => $record['courseid'],
            'unitid' => $record['unitid'] ?? null,
            'timerangestart' => $eventtime,
            'timerangeend' => $eventtime,
            'coveragestatus' => history_policy::COVERAGE_NOT_BACKFILLED,
            'eventcount' => 1,
            'capturestartedat' => $eventtime,
            'earliestreliableeventat' => $eventtime,
            'latestreconciledat' => $eventtime,
            'reasoncode' => 'H2_EVENT_CAPTURED_BACKFILL_PENDING',
            'detailsjson' => [
                'sourceeventid' => $sourceeventid,
                'mappingstatus' => $mapping['status'] ?? 'unresolved',
            ],
        ]);

        return $sourceeventid;
    }

    /**
     * Record quiz attempt and question details when the source row exists.
     *
     * @param \core\event\base $event Moodle event.
     * @param int $sourceeventid Source event id.
     * @return array Capture result.
     */
    private static function record_quiz_attempt_details(\core\event\base $event, int $sourceeventid): array {
        global $DB;

        $attemptid = (int)($event->objectid ?? 0);
        $sourceevent = repository::get_source_event($sourceeventid);
        if ($attemptid <= 0) {
            return ['status' => 'source_recorded', 'reason' => 'no_attempt_id', 'sourceeventid' => $sourceeventid];
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt) {
            self::record_deleted_attempt_stub($event, $sourceeventid, $sourceevent);
            return ['status' => 'source_recorded', 'reason' => 'attempt_row_missing', 'sourceeventid' => $sourceeventid];
        }
        if ((int)$attempt->preview === 1) {
            return ['status' => 'ignored', 'reason' => 'preview_attempt', 'sourceeventid' => $sourceeventid];
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', IGNORE_MISSING);
        $courseid = $quiz ? (int)$quiz->course : (int)($event->courseid ?? 0);
        $cmid = self::event_cmid($event);
        if ($cmid <= 0 && $quiz) {
            $cmid = self::cmid_for_module_instance('quiz', (int)$quiz->id, $courseid);
        }
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course($courseid);
        $maxscore = $quiz ? (float)$quiz->sumgrades : null;
        $scaledscore = self::scaled_score($attempt->sumgrades ?? null, $maxscore);
        $gradepass = self::gradepass_for_module('quiz', (int)$attempt->quiz, $courseid);

        $attemptrecordid = attempt_service::record_quiz_attempt($attempt, array_merge([
            'sourceeventid' => $sourceeventid,
            'courseid' => $courseid > 0 ? $courseid : null,
            'cmid' => $cmid > 0 ? $cmid : null,
            'sectionid' => self::sectionid_for_cmid($cmid),
            'maxscore' => $maxscore,
            'scaledscore' => $scaledscore,
            'lastsourceevent' => $sourceeventid,
            'result' => self::quiz_result_label($attempt, $scaledscore, $gradepass, $quiz),
            'pass' => self::quiz_pass($attempt, $scaledscore, $gradepass, $quiz),
            'gradepass' => $gradepass,
        ], self::mapping_fields($mapping), [
            'sourcefactkey' => $sourceevent->sourcefactkey ?? null,
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ]));

        $questioncount = self::record_question_attempts($attempt, $sourceeventid, $attemptrecordid, $courseid, $cmid, $mapping);

        return [
            'status' => 'captured',
            'sourceeventid' => $sourceeventid,
            'attemptrecordid' => $attemptrecordid,
            'questionattempts' => $questioncount,
        ];
    }

    /**
     * Normalize the current Moodle SCORM attempt snapshot into History V1.
     *
     * @param int $attemptid Moodle scorm_attempt id.
     * @param int $sourceeventid Captured source event id.
     * @param int $cmid Course module id when known.
     * @param int $courseid Course id when known.
     * @return array Capture result.
     */
    private static function record_scorm_attempt_details(
        int $attemptid,
        int $sourceeventid,
        int $cmid = 0,
        int $courseid = 0
    ): array {
        if ($attemptid <= 0) {
            return ['status' => 'source_recorded', 'reason' => 'no_attempt_id', 'sourceeventid' => $sourceeventid];
        }

        $context = self::scorm_attempt_context($attemptid, $cmid, $courseid);
        if ($context === null) {
            return [
                'status' => 'source_recorded',
                'reason' => 'attempt_row_missing',
                'sourceeventid' => $sourceeventid,
            ];
        }

        $attempt = $context['attempt'];
        $scorm = $context['scorm'];
        $snapshot = $context['snapshot'];
        $mapping = $context['mapping'];
        $sourceevent = repository::get_source_event($sourceeventid);
        $sourceversion = (string)($snapshot['timemodified'] ?: time());
        $sourcekey = source_identity::make_key('moodle', 'scorm_attempt', (string)$attemptid);

        $record = array_merge([
            'sourcekey' => $sourcekey,
            'sourcefactkey' => $sourceevent->sourcefactkey ?? null,
            'sourceeventid' => $sourceeventid,
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'scorm',
            'sourcetype' => 'scorm_attempt',
            'sourceid' => (string)$attemptid,
            'sourceversion' => $sourceversion,
            'sourceattemptid' => (string)$attemptid,
            'userid' => (int)$attempt->userid,
            'courseid' => (int)$context['courseid'],
            'sectionid' => self::sectionid_for_cmid((int)$context['cmid']),
            'cmid' => (int)$context['cmid'],
            'attemptno' => (int)$attempt->attempt,
            'attemptstate' => (string)$snapshot['attemptstate'],
            'rawscore' => $snapshot['rawscore'],
            'maxscore' => $snapshot['maxscore'],
            'scaledscore' => $snapshot['scaledscore'],
            'timestart' => $snapshot['timestart'],
            'timefinish' => $snapshot['timefinish'],
            'lastsourceevent' => $sourceeventid,
            'summaryjson' => [
                'scormid' => (int)$scorm->id,
                'attemptid' => $attemptid,
                'attemptno' => (int)$attempt->attempt,
                'status' => (string)$snapshot['status'],
                'result' => $snapshot['result'],
                'pass' => $snapshot['pass'],
                'durationseconds' => $snapshot['durationseconds'],
                'trackvalues' => (int)$snapshot['trackvalues'],
                'normalizer' => 'h2_scorm_capture',
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ], self::mapping_fields($mapping));

        $attemptrecordid = attempt_service::record_attempt($record);
        return [
            'status' => 'captured',
            'sourceeventid' => $sourceeventid,
            'attemptrecordid' => $attemptrecordid,
            'attemptid' => $attemptid,
            'attemptstate' => (string)$snapshot['attemptstate'],
            'scaledscore' => $snapshot['scaledscore'],
            'mappingstatus' => $mapping['status'] ?? 'unresolved',
        ];
    }

    /**
     * Load the canonical source rows and derived values for one SCORM attempt.
     *
     * @param int $attemptid Moodle scorm_attempt id.
     * @param int $cmid Course module id when known.
     * @param int $courseid Course id when known.
     * @return array|null
     */
    private static function scorm_attempt_context(int $attemptid, int $cmid = 0, int $courseid = 0): ?array {
        global $DB;

        $attempt = $DB->get_record('scorm_attempt', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt) {
            return null;
        }
        $scorm = $DB->get_record('scorm', ['id' => $attempt->scormid], '*', IGNORE_MISSING);
        if (!$scorm) {
            return null;
        }

        $courseid = (int)$scorm->course ?: $courseid;
        $resolvedcmid = self::cmid_for_module_instance('scorm', (int)$scorm->id, $courseid);
        if ($resolvedcmid > 0) {
            $cmid = $resolvedcmid;
        }
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course($courseid);

        return [
            'attempt' => $attempt,
            'scorm' => $scorm,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'mapping' => $mapping,
            'snapshot' => self::scorm_attempt_snapshot($attemptid),
        ];
    }

    /**
     * Build a bounded SCORM score/status snapshot from Moodle's normalized track tables.
     *
     * @param int $attemptid Moodle scorm_attempt id.
     * @return array
     */
    private static function scorm_attempt_snapshot(int $attemptid): array {
        global $DB;

        $tracks = $DB->get_records_sql(
            "SELECT v.id, e.element, v.value, v.timemodified
               FROM {scorm_scoes_value} v
               JOIN {scorm_element} e ON e.id = v.elementid
              WHERE v.attemptid = :attemptid
           ORDER BY v.timemodified ASC, v.id ASC",
            ['attemptid' => $attemptid]
        );

        $rawscore = null;
        $maxscore = null;
        $scaledscore = null;
        $status = 'unknown';
        $terminalstatus = null;
        $timestart = null;
        $timemodified = null;

        foreach ($tracks as $track) {
            $element = strtolower((string)$track->element);
            $value = trim((string)$track->value);
            $modified = (int)$track->timemodified;
            $timestart = $timestart === null ? $modified : min($timestart, $modified);
            $timemodified = $timemodified === null ? $modified : max($timemodified, $modified);

            if (str_ends_with($element, '.score.raw') && is_numeric($value)) {
                $rawscore = (float)$value;
            } else if (str_ends_with($element, '.score.max') && is_numeric($value)) {
                $maxscore = (float)$value;
            } else if (str_ends_with($element, '.score.scaled') && is_numeric($value)) {
                $scaledscore = round(max(0.0, min(1.0, (float)$value)), 5);
            }

            if (in_array($element, ['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status'], true)) {
                $candidate = strtolower($value);
                if (in_array($candidate, ['passed', 'completed', 'failed'], true)) {
                    $terminalstatus = $candidate;
                } else if ($terminalstatus === null && $candidate !== '') {
                    $status = $candidate;
                }
            }
        }

        if ($terminalstatus !== null) {
            $status = $terminalstatus;
        }
        if ($rawscore !== null && ($maxscore === null || $maxscore <= 0)) {
            $maxscore = abs($rawscore) > 1 ? 100.0 : 1.0;
        }
        if ($scaledscore === null) {
            $scaledscore = self::scaled_score($rawscore, $maxscore);
        }

        $complete = in_array($status, ['passed', 'completed'], true);
        $failed = $status === 'failed';
        $attemptstate = $complete ? 'complete' : ($failed ? 'failed' : ($tracks ? 'in_progress' : 'unknown'));
        $timefinish = ($complete || $failed) ? $timemodified : null;
        $durationseconds = ($timestart !== null && $timefinish !== null && $timefinish >= $timestart)
            ? $timefinish - $timestart
            : null;

        return [
            'status' => $status,
            'attemptstate' => $attemptstate,
            'rawscore' => $rawscore,
            'maxscore' => $maxscore,
            'scaledscore' => $scaledscore,
            'timestart' => $timestart,
            'timefinish' => $timefinish,
            'timemodified' => $timemodified,
            'durationseconds' => $durationseconds,
            'result' => in_array($status, ['passed', 'failed'], true) ? $status : ($complete ? 'completed' : null),
            'pass' => $status === 'passed' ? 1 : ($status === 'failed' ? 0 : null),
            'trackvalues' => count($tracks),
        ];
    }

    /**
     * Record a synthetic, auditable source fact for controlled SCORM repair.
     *
     * @param array $context SCORM attempt context.
     * @return int Source event id.
     */
    private static function record_scorm_repair_source_event(array $context): int {
        $attempt = $context['attempt'];
        $snapshot = $context['snapshot'];
        $mapping = $context['mapping'];
        $eventtime = (int)($snapshot['timemodified'] ?: time());
        $sourcekey = source_identity::make_key(
            'moodle',
            'scorm_attempt_repair',
            (string)$attempt->id,
            (string)$eventtime,
            'source_fact'
        );
        $summary = [
            'repair' => true,
            'source_table' => 'scorm_attempt',
            'attemptid' => (int)$attempt->id,
            'status' => (string)$snapshot['status'],
            'mappingstatus' => $mapping['status'] ?? 'unresolved',
        ];
        $record = array_merge([
            'sourcekey' => $sourcekey,
            'sourcefactkey' => $sourcekey,
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'scorm',
            'sourcetype' => 'scorm_attempt_repair',
            'sourceid' => (string)$attempt->id,
            'sourceversion' => (string)$eventtime,
            'eventtype' => in_array($snapshot['attemptstate'], ['complete', 'failed'], true)
                ? 'SCORM_ATTEMPT_COMPLETED'
                : 'ACTIVITY_ATTEMPTED',
            'userid' => (int)$attempt->userid,
            'courseid' => (int)$context['courseid'],
            'cmid' => (int)$context['cmid'],
            'sourceattemptid' => (string)$attempt->id,
            'attemptid' => (int)$attempt->id,
            'eventtime' => $eventtime,
            'status' => ($mapping['status'] ?? 'unresolved') === 'resolved' ? 'recorded' : 'unresolved_mapping',
            'normalizer' => 'h2_scorm_repair',
            'summaryjson' => $summary,
            'payloadhash' => source_identity::payload_hash($summary),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ], self::mapping_fields($mapping));
        $sourceeventid = history_service::record_source_event($record);

        coverage_service::record_coverage([
            'scopelevel' => 'learner',
            'sourcefamily' => 'scorm',
            'userid' => (int)$attempt->userid,
            'courseid' => (int)$context['courseid'],
            'unitid' => $record['unitid'] ?? null,
            'timerangestart' => $eventtime,
            'timerangeend' => $eventtime,
            'coveragestatus' => history_policy::COVERAGE_NOT_BACKFILLED,
            'eventcount' => 1,
            'capturestartedat' => $eventtime,
            'earliestreliableeventat' => $eventtime,
            'latestreconciledat' => $eventtime,
            'reasoncode' => 'H2_SCORM_CONTROLLED_REPAIR',
            'detailsjson' => ['sourceeventid' => $sourceeventid, 'attemptid' => (int)$attempt->id],
        ]);

        return $sourceeventid;
    }

    /**
     * Record a deleted quiz attempt stub when Moodle no longer has the attempt row.
     *
     * @param \core\event\base $event Moodle event.
     * @param int $sourceeventid Source event id.
     * @param \stdClass|null $sourceevent Source event row.
     */
    private static function record_deleted_attempt_stub(\core\event\base $event, int $sourceeventid, ?\stdClass $sourceevent): void {
        $userid = self::event_related_userid($event);
        if ($userid === null) {
            return;
        }
        $eventtime = (int)($event->timecreated ?? time());
        $attemptid = (int)($event->objectid ?? 0);
        attempt_service::record_attempt([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$attemptid,
            'sourceversion' => (string)$eventtime,
            'eventtype' => 'ACTIVITY_ATTEMPTED',
            'sourcefactkey' => $sourceevent->sourcefactkey ?? null,
            'sourceeventid' => $sourceeventid,
            'sourceattemptid' => (string)$attemptid,
            'userid' => $userid,
            'courseid' => (int)($event->courseid ?? 0) ?: null,
            'cmid' => self::event_cmid($event) ?: null,
            'attemptstate' => 'deleted',
            'lastsourceevent' => $sourceeventid,
            'summaryjson' => ['moodleevent' => get_class($event), 'rowavailable' => false],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ]);
    }

    /**
     * Record question attempt facts for a quiz attempt.
     *
     * @param \stdClass $attempt Quiz attempt.
     * @param int $sourceeventid Source event id.
     * @param int $attemptrecordid Attempt history id.
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param array $mapping Program 1 mapping.
     * @return int Number of records captured.
     */
    private static function record_question_attempts(
        \stdClass $attempt,
        int $sourceeventid,
        int $attemptrecordid,
        int $courseid,
        int $cmid,
        array $mapping
    ): int {
        global $DB;

        if (empty($attempt->uniqueid)) {
            return 0;
        }

        $sql = "SELECT qa.*,
                       qas.id AS lateststepid,
                       qas.state AS lateststate,
                       qas.fraction AS latestfraction,
                       qas.timecreated AS lateststeptime
                  FROM {question_attempts} qa
             LEFT JOIN {question_attempt_steps} qas
                    ON qas.questionattemptid = qa.id
                   AND qas.sequencenumber = (
                       SELECT MAX(qas2.sequencenumber)
                         FROM {question_attempt_steps} qas2
                        WHERE qas2.questionattemptid = qa.id
                   )
                 WHERE qa.questionusageid = :questionusageid
              ORDER BY qa.slot ASC";
        $rows = $DB->get_records_sql($sql, ['questionusageid' => (int)$attempt->uniqueid], 0, self::QUESTION_CAPTURE_LIMIT);
        $captured = 0;
        foreach ($rows as $row) {
            $steptime = (int)($row->lateststeptime ?? $attempt->timemodified ?? time());
            $rawmark = isset($row->latestfraction, $row->maxmark) && $row->latestfraction !== null
                ? round((float)$row->latestfraction * (float)$row->maxmark, 5)
                : null;
            attempt_service::record_question_attempt(array_merge(
                normalizer::question_attempt_to_record($row, [
                    'sourceeventid' => $sourceeventid,
                    'attemptid' => $attemptrecordid,
                    'userid' => (int)$attempt->userid,
                    'courseid' => $courseid > 0 ? $courseid : null,
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'resultstate' => $row->lateststate ?? null,
                    'rawmark' => $rawmark,
                    'fraction' => isset($row->latestfraction) ? (float)$row->latestfraction : null,
                    'steptime' => $steptime,
                    'responsehash' => !empty($row->responsesummary)
                        ? source_identity::payload_hash((string)$row->responsesummary)
                        : null,
                    'sourcefactkey' => source_identity::make_key(
                        'moodle',
                        'question_attempt',
                        (string)$row->id,
                        (string)$steptime,
                        'source_fact'
                    ),
                ]),
                self::mapping_fields($mapping)
            ));
            $captured++;
        }

        return $captured;
    }

    /**
     * Record completion row details from Moodle completion source of truth.
     *
     * @param \core\event\course_module_completion_updated $event Moodle event.
     * @param int $sourceeventid Source event id.
     * @return array Capture result.
     */
    private static function record_completion_details(
        \core\event\course_module_completion_updated $event,
        int $sourceeventid
    ): array {
        global $DB;

        $cmid = self::event_cmid($event);
        $userid = self::event_related_userid($event);
        if ($cmid <= 0 || $userid === null) {
            return ['status' => 'source_recorded', 'reason' => 'completion_scope_missing', 'sourceeventid' => $sourceeventid];
        }

        $completion = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cmid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);
        if (!$completion) {
            return ['status' => 'source_recorded', 'reason' => 'completion_row_missing', 'sourceeventid' => $sourceeventid];
        }

        $mapping = p1_resolver::resolve_cmid($cmid);
        $completionid = completion_service::record_moodle_completion($completion, array_merge([
            'sourceeventid' => $sourceeventid,
            'courseid' => (int)($event->courseid ?? 0) ?: null,
        ], self::mapping_fields($mapping)));

        return ['status' => 'captured', 'sourceeventid' => $sourceeventid, 'completionid' => $completionid];
    }

    /**
     * Record FLW VR Room attempt details from the verified event payload/source row.
     *
     * @param \core\event\base $event Moodle event.
     * @param int $sourceeventid Source event id.
     * @return array Capture result.
     */
    private static function record_flwvrroom_attempt_details(\core\event\base $event, int $sourceeventid): array {
        global $DB;

        $attemptid = (int)($event->objectid ?? 0);
        $sourceevent = repository::get_source_event($sourceeventid);
        $row = $attemptid > 0 ? $DB->get_record('flwvrroom_attempts', ['id' => $attemptid], '*', IGNORE_MISSING) : false;
        $other = $event->other ?? [];
        $userid = self::event_related_userid($event);
        if ($userid === null) {
            return ['status' => 'source_recorded', 'reason' => 'userid_missing', 'sourceeventid' => $sourceeventid];
        }

        $rawscore = $row && isset($row->score) ? (float)$row->score : (isset($other['score']) ? (float)$other['score'] : null);
        $maxscore = isset($other['maxscore']) ? (float)$other['maxscore'] : null;
        $eventtime = (int)($event->timecreated ?? time());
        $attempttime = $row && isset($row->timecreated) ? (int)$row->timecreated : $eventtime;
        $taskcomplete = $row && isset($row->taskcomplete) ? (int)$row->taskcomplete : null;
        $durationseconds = $row && isset($row->durationseconds) ? (int)$row->durationseconds : null;
        $cmid = self::event_cmid($event);
        $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course((int)($event->courseid ?? 0));

        $recordid = attempt_service::record_attempt(array_merge([
            'sourcesystem' => 'flwvrroom',
            'sourcefamily' => 'flwvrroom',
            'sourcetype' => 'flwvrroom_attempt',
            'sourceid' => (string)$attemptid,
            'sourceversion' => (string)$attempttime,
            'eventtype' => 'SPEAKING_ATTEMPTED',
            'sourcefactkey' => $sourceevent->sourcefactkey ?? null,
            'sourceeventid' => $sourceeventid,
            'sourceattemptid' => (string)$attemptid,
            'userid' => $userid,
            'courseid' => (int)($event->courseid ?? 0) ?: null,
            'cmid' => $cmid > 0 ? $cmid : null,
            'attemptstate' => $taskcomplete ? 'complete' : 'submitted',
            'rawscore' => $rawscore,
            'maxscore' => $maxscore,
            'scaledscore' => self::scaled_score($rawscore, $maxscore),
            'timefinish' => $attempttime,
            'lastsourceevent' => $sourceeventid,
            'summaryjson' => [
                'durationseconds' => $durationseconds,
                'taskcomplete' => $taskcomplete,
                'kpcodes' => $other['kpcodes'] ?? null,
                'xrmode' => $other['xrmode'] ?? null,
                'scenario' => $other['scenario'] ?? null,
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ], self::mapping_fields($mapping)));

        return ['status' => 'captured', 'sourceeventid' => $sourceeventid, 'attemptrecordid' => $recordid];
    }

    /**
     * Record a failed post-source capture step.
     *
     * @param string $runtype Run type.
     * @param \core\event\base $event Moodle event.
     * @param int $sourceeventid Source event id.
     * @param string $message Failure message.
     */
    private static function record_capture_failure(
        string $runtype,
        \core\event\base $event,
        int $sourceeventid,
        string $message
    ): void {
        repository::upsert_reconcile_run([
            'runtype' => $runtype,
            'scopejson' => [
                'sourceeventid' => $sourceeventid,
                'eventname' => get_class($event),
                'objectid' => $event->objectid ?? null,
            ],
            'status' => 'failed_after_source',
            'userid' => self::event_related_userid($event),
            'courseid' => (int)($event->courseid ?? 0) ?: null,
            'timestarted' => time(),
            'timefinished' => time(),
            'recordsseen' => 1,
            'recordsfailed' => 1,
            'errorjson' => ['message' => $message],
        ]);
    }

    /**
     * Determine an H2 educational event type.
     *
     * @param \core\event\base $event Moodle event.
     * @return string
     */
    private static function educational_event_for_event(\core\event\base $event): string {
        $eventname = ltrim(get_class($event), '\\');
        $map = [
            'mod_quiz\\event\\attempt_started' => 'ACTIVITY_ATTEMPTED',
            'mod_quiz\\event\\attempt_submitted' => 'ASSESSMENT_COMPLETED',
            'mod_quiz\\event\\attempt_graded' => 'ASSESSMENT_COMPLETED',
            'mod_quiz\\event\\attempt_regraded' => 'ASSESSMENT_COMPLETED',
            'mod_quiz\\event\\attempt_manual_grading_completed' => 'ASSESSMENT_COMPLETED',
            'mod_quiz\\event\\attempt_reopened' => 'ACTIVITY_ATTEMPTED',
            'mod_quiz\\event\\attempt_deleted' => 'ACTIVITY_ATTEMPTED',
            'mod_scorm\\event\\scoreraw_submitted' => 'ASSESSMENT_UPDATED',
            'mod_scorm\\event\\status_submitted' => 'ACTIVITY_ATTEMPTED',
            'core\\event\\course_module_completion_updated' => 'CHECKPOINT_COMPLETED',
            'core\\event\\course_completion_updated' => 'CHECKPOINT_COMPLETED',
            'mod_flwvrroom\\event\\attempt_submitted' => 'SPEAKING_ATTEMPTED',
        ];

        return $map[$eventname] ?? 'ACTIVITY_ATTEMPTED';
    }

    /**
     * Return a bounded educational event type for a SCORM CMI update.
     *
     * @param \core\event\base $event Moodle event.
     * @return string
     */
    private static function scorm_event_type(\core\event\base $event): string {
        $eventname = ltrim(get_class($event), '\\');
        if ($eventname === 'mod_scorm\\event\\scoreraw_submitted') {
            return 'ASSESSMENT_UPDATED';
        }
        $status = strtolower((string)($event->other['cmivalue'] ?? ''));
        return in_array($status, ['passed', 'completed', 'failed'], true)
            ? 'SCORM_ATTEMPT_COMPLETED'
            : 'ACTIVITY_ATTEMPTED';
    }

    /**
     * Return the related learner/user id for an event.
     *
     * @param \core\event\base $event Moodle event.
     * @return int|null
     */
    private static function event_related_userid(\core\event\base $event): ?int {
        $data = $event->get_data();
        $userid = $data['relateduserid'] ?? $data['userid'] ?? null;
        return $userid === null ? null : (int)$userid;
    }

    /**
     * Return the course module id for a module-level event.
     *
     * @param \core\event\base $event Moodle event.
     * @return int
     */
    private static function event_cmid(\core\event\base $event): int {
        $data = $event->get_data();
        if ((int)($data['contextlevel'] ?? 0) === CONTEXT_MODULE) {
            return (int)($data['contextinstanceid'] ?? 0);
        }
        return isset($event->contextinstanceid) ? (int)$event->contextinstanceid : 0;
    }

    /**
     * Copy Program 1 mapping fields when available.
     *
     * @param array $mapping Resolver result.
     * @return array
     */
    private static function mapping_fields(array $mapping): array {
        $fields = [];
        foreach (['worldid', 'stageid', 'unitid', 'lessonid', 'componentid', 'activityid', 'assessmentid', 'questionid'] as $field) {
            if (!empty($mapping[$field])) {
                $fields[$field] = $mapping[$field];
            }
        }
        return $fields;
    }

    /**
     * Keep only bounded scalar event payload fields.
     *
     * @param array $other Event other payload.
     * @return array
     */
    private static function bounded_event_other(array $other): array {
        $bounded = [];
        foreach ($other as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $text = (string)$value;
                $bounded[$key] = strlen($text) > 255 ? substr($text, 0, 255) : $value;
            }
        }
        return $bounded;
    }

    /**
     * Compute normalized score.
     *
     * @param mixed $raw Raw score.
     * @param float|null $max Max score.
     * @return float|null
     */
    private static function scaled_score($raw, ?float $max): ?float {
        if ($raw === null || $max === null || $max <= 0) {
            return null;
        }
        return round(max(0.0, min(1.0, (float)$raw / $max)), 5);
    }

    /**
     * Find grade pass for a module grade item.
     *
     * @param string $module Module name.
     * @param int $instance Instance id.
     * @param int $courseid Course id.
     * @return float|null
     */
    private static function gradepass_for_module(string $module, int $instance, int $courseid): ?float {
        global $DB;

        if ($courseid <= 0) {
            return null;
        }
        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemmodule' => $module,
            'iteminstance' => $instance,
        ], 'id, gradepass', IGNORE_MISSING);
        if (!$gradeitem || $gradeitem->gradepass === null || (float)$gradeitem->gradepass <= 0) {
            return null;
        }
        return (float)$gradeitem->gradepass;
    }

    /**
     * Return quiz pass state when source data supports it.
     *
     * @param \stdClass $attempt Quiz attempt.
     * @param float|null $scaledscore Scaled score.
     * @param float|null $gradepass Grade pass.
     * @param \stdClass|null $quiz Quiz.
     * @return int|null
     */
    private static function quiz_pass(\stdClass $attempt, ?float $scaledscore, ?float $gradepass, ?\stdClass $quiz): ?int {
        if ($attempt->sumgrades === null || $scaledscore === null || $gradepass === null || !$quiz || (float)$quiz->grade <= 0) {
            return null;
        }
        $officialattemptgrade = $scaledscore * (float)$quiz->grade;
        return $officialattemptgrade >= $gradepass ? 1 : 0;
    }

    /**
     * Return a bounded quiz result label.
     *
     * @param \stdClass $attempt Quiz attempt.
     * @param float|null $scaledscore Scaled score.
     * @param float|null $gradepass Grade pass.
     * @param \stdClass|null $quiz Quiz.
     * @return string|null
     */
    private static function quiz_result_label(\stdClass $attempt, ?float $scaledscore, ?float $gradepass, ?\stdClass $quiz): ?string {
        $pass = self::quiz_pass($attempt, $scaledscore, $gradepass, $quiz);
        if ($pass === null) {
            return $attempt->sumgrades === null ? 'ungraded' : null;
        }
        return $pass ? 'passed' : 'failed';
    }

    /**
     * Resolve course module id for a module instance.
     *
     * @param string $module Module name.
     * @param int $instance Instance id.
     * @param int $courseid Course id.
     * @return int
     */
    private static function cmid_for_module_instance(string $module, int $instance, int $courseid): int {
        global $DB;

        if ($courseid <= 0) {
            return 0;
        }
        $moduleid = $DB->get_field('modules', 'id', ['name' => $module], IGNORE_MISSING);
        if (!$moduleid) {
            return 0;
        }
        $cmid = $DB->get_field('course_modules', 'id', [
            'course' => $courseid,
            'module' => (int)$moduleid,
            'instance' => $instance,
        ], IGNORE_MISSING);
        return $cmid ? (int)$cmid : 0;
    }

    /**
     * Return course section id for a course module id.
     *
     * @param int $cmid Course module id.
     * @return int|null
     */
    private static function sectionid_for_cmid(int $cmid): ?int {
        global $DB;

        if ($cmid <= 0) {
            return null;
        }
        $sectionid = $DB->get_field('course_modules', 'section', ['id' => $cmid], IGNORE_MISSING);
        return $sectionid ? (int)$sectionid : null;
    }
}

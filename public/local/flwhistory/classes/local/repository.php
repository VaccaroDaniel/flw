<?php
// Repository for local_flwhistory records.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence boundary for Program 2 history facts.
 */
class repository {
    /** @var array Allowed DB fields per table. */
    private const TABLE_FIELDS = [
        'flwhist_source_event' => [
            'id', 'sourcekey', 'sourcefactkey', 'sourcesystem', 'sourcefamily', 'sourcetype', 'sourceid', 'sourceversion', 'eventtype',
            'userid', 'courseid', 'sectionid', 'cmid', 'gradeitemid', 'sourceattemptid', 'attemptid',
            'worldid', 'stageid', 'unitid', 'lessonid', 'componentid', 'activityid', 'assessmentid',
            'questionid', 'eventtime', 'status', 'normalizer', 'normpolicyversion', 'summaryjson', 'payloadhash', 'correctionof',
            'supersededby', 'timecreated', 'timemodified', 'usermodified',
        ],
        'flwhist_attempt' => [
            'id', 'sourcekey', 'sourcefactkey', 'sourceeventid', 'sourcesystem', 'sourcefamily', 'sourcetype', 'sourceid', 'sourceversion',
            'sourceattemptid', 'userid', 'courseid', 'sectionid', 'cmid', 'worldid', 'stageid', 'unitid', 'lessonid',
            'componentid', 'activityid', 'assessmentid', 'attemptno', 'attemptstate', 'rawscore',
            'maxscore', 'scaledscore', 'timestart', 'timefinish', 'lastsourceevent', 'normpolicyversion', 'summaryjson',
            'timecreated', 'timemodified',
        ],
        'flwhist_placement' => [
            'id', 'sourcekey', 'sourcefactkey', 'sourceeventid', 'sourcesystem', 'sourcefamily', 'sourcetype', 'sourceid', 'sourceversion',
            'userid', 'courseid', 'previouslevel', 'currentlevel', 'placementstatus', 'score', 'confidence',
            'profilejson', 'placementtime', 'normpolicyversion', 'timecreated', 'timemodified',
        ],
        'flwhist_question_attempt' => [
            'id', 'sourcekey', 'sourcefactkey', 'sourcefamily', 'attemptid', 'sourceeventid', 'userid', 'courseid', 'cmid', 'questionusageid',
            'questionattemptid', 'slot', 'questionid', 'questionversionid', 'questionbankentryid',
            'activityid', 'assessmentid', 'resultstate', 'responsehash', 'rawmark', 'maxmark', 'fraction',
            'steptime', 'normpolicyversion', 'summaryjson', 'timecreated', 'timemodified',
        ],
        'flwhist_grade_version' => [
            'id', 'sourcekey', 'sourcefactkey', 'sourcefamily', 'sourceeventid', 'userid', 'courseid', 'cmid', 'gradeitemid', 'gradegradeid',
            'gradehistoryid', 'itemmodule', 'iteminstance', 'itemnumber', 'rawgrade', 'finalgrade',
            'previousgrade', 'graderid', 'action', 'reason', 'gradetime', 'correctionof', 'supersededby',
            'normpolicyversion', 'summaryjson', 'timecreated', 'timemodified',
        ],
        'flwhist_grade_summary' => [
            'id', 'sourcekey', 'sourcefamily', 'userid', 'courseid', 'cmid', 'gradeitemid', 'gradegradeid',
            'itemmodule', 'iteminstance', 'itemnumber', 'latestattemptid', 'latestattemptsourceid',
            'latestattemptscore', 'latestattempttime', 'bestattemptid', 'bestattemptsourceid',
            'bestattemptscore', 'bestattempttime', 'officialgradegradeid', 'officialrawgrade',
            'officialfinalgrade', 'officialgradetime', 'latestgradeversionid', 'reconciliationstatus',
            'normpolicyversion', 'summaryjson', 'timecreated', 'timemodified',
        ],
        'flwhist_completion' => [
            'id', 'sourcekey', 'sourcefactkey', 'sourcefamily', 'sourceeventid', 'userid', 'courseid', 'cmid', 'completionstate', 'viewed',
            'overrideby', 'completiontime', 'normpolicyversion', 'detailsjson', 'timecreated', 'timemodified',
        ],
        'flwhist_coverage' => [
            'id', 'sourcekey', 'scopelevel', 'sourcefamily', 'userid', 'courseid', 'worldid', 'stageid',
            'unitid', 'timerangestart', 'timerangeend', 'coveragestatus', 'eventavailability',
            'capturestartedat', 'backfillstartedat', 'backfillcompletedat', 'earliestreliableeventat',
            'latestreconciledat', 'sourceavailable', 'eventcount', 'reasoncode', 'normpolicyversion',
            'detailsjson', 'timecreated', 'timemodified', 'usermodified',
        ],
        'flwhist_content_link' => [
            'id', 'sourcekey', 'moodlecourseid', 'moodlesectionid', 'cmid', 'scoidentifier', 'worldid',
            'stageid', 'unitid', 'lessonid', 'componentid', 'activityid', 'assessmentid', 'questionid',
            'sourcerevision', 'freshness', 'resolver', 'status', 'metadatajson', 'timecreated',
            'timemodified',
        ],
        'flwhist_reconcile_run' => [
            'id', 'sourcekey', 'runtype', 'normpolicyversion', 'scopejson', 'status', 'userid', 'courseid', 'timestarted',
            'timefinished', 'recordsseen', 'recordscreated', 'recordsupdated', 'recordsskipped',
            'recordsfailed', 'errorjson', 'timecreated', 'timemodified',
        ],
        'flwhist_correction' => [
            'id', 'sourcekey', 'recordtable', 'recordid', 'correctedtable', 'correctedid', 'correctiontype',
            'reason', 'summaryjson', 'userid', 'timecreated', 'timemodified',
        ],
    ];

    /**
     * Insert or update a normalized source event by source key.
     *
     * @param array $data Record data.
     * @return int Record id.
     */
    public static function upsert_source_event(array $data): int {
        $data['eventtime'] = (int)($data['eventtime'] ?? time());
        $data['eventtype'] = $data['eventtype'] ?? 'recorded';
        $data['status'] = $data['status'] ?? 'recorded';
        $data['normalizer'] = $data['normalizer'] ?? 'h1';
        $data['normpolicyversion'] = $data['normpolicyversion'] ?? history_policy::NORMALIZATION_POLICY_VERSION;
        $data = source_identity::normalise_record($data);
        if (empty($data['payloadhash']) && !empty($data['summaryjson'])) {
            $data['payloadhash'] = source_identity::payload_hash($data['summaryjson']);
        }
        return self::upsert_by_sourcekey('flwhist_source_event', $data);
    }

    /**
     * Fetch a source event by source key.
     *
     * @param string $sourcekey Source key.
     * @return \stdClass|null
     */
    public static function get_source_event_by_key(string $sourcekey): ?\stdClass {
        global $DB;
        return $DB->get_record('flwhist_source_event', ['sourcekey' => $sourcekey]) ?: null;
    }

    /**
     * Fetch a source event by id.
     *
     * @param int $id Source event id.
     * @return \stdClass|null
     */
    public static function get_source_event(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('flwhist_source_event', ['id' => $id]) ?: null;
    }

    /**
     * Insert or update an attempt by source key.
     *
     * @param array $data Attempt data.
     * @return int Record id.
     */
    public static function upsert_attempt(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['eventtype'] = $data['eventtype'] ?? 'attempt';
            $data = source_identity::normalise_record($data);
        }
        $data['attemptstate'] = $data['attemptstate'] ?? 'unknown';
        return self::upsert_by_sourcekey('flwhist_attempt', $data);
    }

    /**
     * Fetch an attempt by source key.
     *
     * @param string $sourcekey Source key.
     * @return \stdClass|null
     */
    public static function get_attempt_by_sourcekey(string $sourcekey): ?\stdClass {
        global $DB;
        return $DB->get_record('flwhist_attempt', ['sourcekey' => $sourcekey]) ?: null;
    }

    /**
     * Insert or update a question/item attempt by source key.
     *
     * @param array $data Question attempt data.
     * @return int Record id.
     */
    public static function upsert_question_attempt(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['sourcesystem'] = $data['sourcesystem'] ?? 'moodle';
            $data['sourcetype'] = $data['sourcetype'] ?? 'question_attempt';
            $data['sourceid'] = $data['sourceid'] ?? (string)($data['questionattemptid'] ?? '');
            $data['sourceversion'] = $data['sourceversion'] ?? (string)($data['steptime'] ?? time());
            $data['eventtype'] = $data['eventtype'] ?? 'question_attempt';
            $data = source_identity::normalise_record($data);
        }
        return self::upsert_by_sourcekey('flwhist_question_attempt', $data);
    }

    /**
     * Insert or update a placement fact by source key.
     *
     * @param array $data Placement data.
     * @return int Record id.
     */
    public static function upsert_placement(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['sourcesystem'] = $data['sourcesystem'] ?? 'flwplacement';
            $data['sourcetype'] = $data['sourcetype'] ?? 'placement';
            $data['sourceid'] = $data['sourceid'] ?? '';
            $data['sourceversion'] = $data['sourceversion'] ?? (string)($data['placementtime'] ?? time());
            $data['eventtype'] = $data['eventtype'] ?? 'placement_recorded';
            $data = source_identity::normalise_record($data);
        }
        $data['placementstatus'] = $data['placementstatus'] ?? 'recorded';
        return self::upsert_by_sourcekey('flwhist_placement', $data);
    }

    /**
     * Insert or update a coverage fact by source key.
     *
     * @param array $data Coverage data.
     * @return int Record id.
     */
    public static function upsert_coverage(array $data): int {
        $data['scopelevel'] = $data['scopelevel'] ?? self::infer_scope_level($data);
        $data['sourcefamily'] = history_policy::clean_family((string)($data['sourcefamily'] ?? 'unknown'));
        $data['coveragestatus'] = history_policy::normalise_coverage_status(
            (string)($data['coveragestatus'] ?? history_policy::COVERAGE_UNKNOWN)
        );
        $data['eventcount'] = (int)($data['eventcount'] ?? 0);
        $data['eventavailability'] = isset($data['eventavailability'])
            ? history_policy::normalise_event_availability((string)$data['eventavailability'])
            : history_policy::infer_event_availability($data['coveragestatus'], $data['eventcount']);
        $data['sourceavailable'] = isset($data['sourceavailable'])
            ? (int)(bool)$data['sourceavailable']
            : (int)($data['coveragestatus'] !== history_policy::COVERAGE_SOURCE_LIMITED);
        $data['normpolicyversion'] = $data['normpolicyversion'] ?? history_policy::NORMALIZATION_POLICY_VERSION;
        if (empty($data['sourcekey'])) {
            $data['sourcekey'] = history_policy::coverage_source_key($data);
        }
        return self::upsert_by_sourcekey('flwhist_coverage', $data);
    }

    /**
     * Fetch a coverage fact by scope.
     *
     * @param array $criteria Search criteria.
     * @return \stdClass|null
     */
    public static function get_coverage(array $criteria): ?\stdClass {
        global $DB;

        if (!empty($criteria['sourcekey'])) {
            return $DB->get_record('flwhist_coverage', ['sourcekey' => $criteria['sourcekey']]) ?: null;
        }
        if (empty($criteria['sourcefamily'])) {
            return null;
        }

        $where = ['sourcefamily = :sourcefamily'];
        $params = ['sourcefamily' => history_policy::clean_family((string)$criteria['sourcefamily'])];
        foreach (['scopelevel', 'userid', 'courseid', 'worldid', 'stageid', 'unitid'] as $field) {
            if (array_key_exists($field, $criteria) && $criteria[$field] !== null && $criteria[$field] !== '') {
                $where[] = "{$field} = :{$field}";
                $params[$field] = $criteria[$field];
            }
        }
        if (!empty($criteria['timerangestart'])) {
            $where[] = '(timerangestart IS NULL OR timerangestart <= :timerangestart)';
            $params['timerangestart'] = (int)$criteria['timerangestart'];
        }
        if (!empty($criteria['timerangeend'])) {
            $where[] = '(timerangeend IS NULL OR timerangeend >= :timerangeend)';
            $params['timerangeend'] = (int)$criteria['timerangeend'];
        }

        $sql = 'SELECT * FROM {flwhist_coverage} WHERE ' . implode(' AND ', $where)
            . ' ORDER BY timemodified DESC, id DESC';
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Insert or update a grade version by source key.
     *
     * @param array $data Grade version data.
     * @return int Record id.
     */
    public static function upsert_grade_version(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['sourcesystem'] = $data['sourcesystem'] ?? 'moodle';
            $data['sourcetype'] = $data['sourcetype'] ?? 'grade_version';
            $data['sourceid'] = $data['sourceid'] ?? (string)($data['gradegradeid'] ?? $data['gradehistoryid'] ?? '');
            $data['sourceversion'] = $data['sourceversion'] ?? (string)($data['gradetime'] ?? time());
            $data['eventtype'] = $data['eventtype'] ?? ($data['action'] ?? 'grade_recorded');
            $data = source_identity::normalise_record($data);
        }
        $data['action'] = $data['action'] ?? 'recorded';
        return self::upsert_by_sourcekey('flwhist_grade_version', $data);
    }

    /**
     * Insert or update the current derived grade summary.
     *
     * @param array $data Summary data.
     * @return int Record id.
     */
    public static function upsert_grade_summary(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['sourcekey'] = source_identity::make_key(
                'flwhistory',
                'grade_summary',
                (string)($data['userid'] ?? '') . ':' . (string)($data['gradeitemid'] ?? ''),
                history_policy::NORMALIZATION_POLICY_VERSION,
                'current'
            );
        }
        $data['sourcefamily'] = $data['sourcefamily'] ?? 'gradebook';
        $data['reconciliationstatus'] = $data['reconciliationstatus'] ?? 'current';
        $data['normpolicyversion'] = $data['normpolicyversion'] ?? history_policy::NORMALIZATION_POLICY_VERSION;
        return self::upsert_by_sourcekey('flwhist_grade_summary', $data);
    }

    /**
     * Fetch the current derived grade summary.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Grade item id.
     * @return \stdClass|null
     */
    public static function get_grade_summary(int $userid, int $gradeitemid): ?\stdClass {
        global $DB;

        return $DB->get_record('flwhist_grade_summary', [
            'userid' => $userid,
            'gradeitemid' => $gradeitemid,
        ], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Insert or update a completion transition by source key.
     *
     * @param array $data Completion data.
     * @return int Record id.
     */
    public static function upsert_completion(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['sourcesystem'] = $data['sourcesystem'] ?? 'moodle';
            $data['sourcetype'] = $data['sourcetype'] ?? 'completion';
            $data['sourceid'] = $data['sourceid'] ?? (string)($data['cmid'] ?? $data['courseid'] ?? '');
            $data['sourceversion'] = $data['sourceversion'] ?? (string)($data['completiontime'] ?? time());
            $data['eventtype'] = $data['eventtype'] ?? 'completion_changed';
            $data = source_identity::normalise_record($data);
        }
        return self::upsert_by_sourcekey('flwhist_completion', $data);
    }

    /**
     * Insert or update a Program 1 content link cache row.
     *
     * @param array $data Content link data.
     * @return int Record id.
     */
    public static function upsert_content_link(array $data): int {
        if (empty($data['sourcekey'])) {
            $sourceid = implode('|', [
                (string)($data['moodlecourseid'] ?? ''),
                (string)($data['moodlesectionid'] ?? ''),
                (string)($data['cmid'] ?? ''),
                (string)($data['scoidentifier'] ?? ''),
                (string)($data['questionid'] ?? ''),
            ]);
            $data['sourcekey'] = source_identity::make_key(
                'program1',
                'content_link',
                $sourceid,
                (string)($data['sourcerevision'] ?? ''),
                (string)($data['resolver'] ?? 'p1_contract')
            );
        }
        $data['freshness'] = $data['freshness'] ?? 'unknown';
        $data['resolver'] = $data['resolver'] ?? 'p1_contract';
        $data['status'] = $data['status'] ?? 'resolved';
        return self::upsert_by_sourcekey('flwhist_content_link', $data);
    }

    /**
     * Fetch the best cached content link for criteria.
     *
     * @param array $criteria Search criteria.
     * @return \stdClass|null
     */
    public static function get_content_link(array $criteria): ?\stdClass {
        global $DB;

        $allowed = ['sourcekey', 'moodlecourseid', 'moodlesectionid', 'cmid', 'scoidentifier', 'unitid',
            'activityid', 'assessmentid', 'questionid'];
        $where = [];
        $params = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $criteria) || $criteria[$field] === null || $criteria[$field] === '') {
                continue;
            }
            $where[] = $field . ' = :' . $field;
            $params[$field] = $criteria[$field];
        }
        if (!$where) {
            return null;
        }
        $sql = 'SELECT * FROM {flwhist_content_link} WHERE ' . implode(' AND ', $where)
            . ' ORDER BY timemodified DESC, id DESC';
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Record a correction/supersession relation.
     *
     * @param array $data Correction data.
     * @return int Record id.
     */
    public static function record_correction(array $data): int {
        if (empty($data['sourcekey'])) {
            $correctionid = ($data['recordtable'] ?? '') . ':' . ($data['recordid'] ?? '') . ':'
                . ($data['correctedtable'] ?? '') . ':' . ($data['correctedid'] ?? '');
            $data['sourcekey'] = source_identity::make_key(
                'flwhistory',
                'correction',
                $correctionid,
                substr(source_identity::payload_hash((string)($data['reason'] ?? '')), 0, 16),
                (string)($data['correctiontype'] ?? 'supersession')
            );
        }
        $data['correctiontype'] = $data['correctiontype'] ?? 'supersession';
        $id = self::upsert_by_sourcekey('flwhist_correction', $data);
        self::link_correction($data);
        return $id;
    }

    /**
     * Fetch a learner timeline.
     *
     * @param int $userid User id.
     * @param int $courseid Optional course id.
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_learner_timeline(int $userid, int $courseid = 0, int $limit = 100): array {
        global $DB;

        $where = ['userid = :userid'];
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $sql = 'SELECT * FROM {flwhist_source_event} WHERE ' . implode(' AND ', $where)
            . ' ORDER BY eventtime DESC, id DESC';
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Count source events by source system/type for a course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_course_source_counts(int $courseid): array {
        global $DB;

        $sql = "SELECT " . $DB->sql_concat_join("':'", ['sourcesystem', 'sourcetype', 'status'])
            . " AS id, sourcesystem, sourcetype, status, COUNT(1) AS recordcount
                 FROM {flwhist_source_event}
                WHERE courseid = :courseid
             GROUP BY sourcesystem, sourcetype, status
             ORDER BY sourcesystem, sourcetype, status";
        return array_values($DB->get_records_sql($sql, ['courseid' => $courseid]));
    }

    /**
     * Fetch grade versions.
     *
     * @param int $userid User id.
     * @param int $gradeitemid Optional grade item id.
     * @param int $courseid Optional course id.
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_grade_versions(int $userid, int $gradeitemid = 0, int $courseid = 0,
            int $limit = 100): array {
        global $DB;

        $where = ['userid = :userid'];
        $params = ['userid' => $userid];
        if ($gradeitemid > 0) {
            $where[] = 'gradeitemid = :gradeitemid';
            $params['gradeitemid'] = $gradeitemid;
        }
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $sql = 'SELECT * FROM {flwhist_grade_version} WHERE ' . implode(' AND ', $where)
            . ' ORDER BY gradetime DESC, id DESC';
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Fetch placement history for a learner.
     *
     * @param int $userid User id.
     * @param int $courseid Optional course id.
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_placement_history(int $userid, int $courseid = 0, int $limit = 50): array {
        global $DB;

        $where = ['userid = :userid'];
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $sql = 'SELECT * FROM {flwhist_placement} WHERE ' . implode(' AND ', $where)
            . ' ORDER BY placementtime DESC, id DESC';
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Get all coverage records for a course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_course_coverage(int $courseid): array {
        global $DB;

        return array_values($DB->get_records('flwhist_coverage', ['courseid' => $courseid],
            'sourcefamily ASC, userid ASC, timerangestart ASC'));
    }

    /**
     * Insert or update a reconciliation run.
     *
     * @param array $data Reconciliation run data.
     * @return int Record id.
     */
    public static function upsert_reconcile_run(array $data): int {
        if (empty($data['sourcekey'])) {
            $data['sourcekey'] = source_identity::make_key(
                'flwhistory',
                'reconcile_run',
                (string)($data['runtype'] ?? 'unknown'),
                (string)($data['timestarted'] ?? time()),
                (string)($data['userid'] ?? 0)
            );
        }
        $data['status'] = $data['status'] ?? 'running';
        $data['timestarted'] = (int)($data['timestarted'] ?? time());
        $data['normpolicyversion'] = $data['normpolicyversion'] ?? history_policy::NORMALIZATION_POLICY_VERSION;
        return self::upsert_by_sourcekey('flwhist_reconcile_run', $data);
    }

    /**
     * Fetch recent reconciliation runs.
     *
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_recent_reconcile_runs(int $limit = 20): array {
        global $DB;

        return array_values($DB->get_records('flwhist_reconcile_run', null, 'timestarted DESC, id DESC', '*', 0, $limit));
    }

    /**
     * Insert or update a record by sourcekey.
     *
     * @param string $table Table name.
     * @param array $data Record data.
     * @return int Record id.
     */
    private static function upsert_by_sourcekey(string $table, array $data): int {
        global $DB;

        $now = time();
        $data = self::apply_history_defaults($table, $data);
        $data = self::normalise_json_fields($data);
        $data['timemodified'] = $now;
        $existing = !empty($data['sourcekey']) ? $DB->get_record($table, ['sourcekey' => $data['sourcekey']]) : false;
        if ($existing) {
            $data['id'] = $existing->id;
            $record = self::to_record($table, $data);
            $DB->update_record($table, $record);
            return (int)$existing->id;
        }
        $data['timecreated'] = $data['timecreated'] ?? $now;
        $record = self::to_record($table, $data);
        return (int)$DB->insert_record($table, $record);
    }

    /**
     * Convert arrays in known JSON fields before storage.
     *
     * @param array $data Data.
     * @return array
     */
    private static function normalise_json_fields(array $data): array {
        foreach (['summaryjson', 'detailsjson', 'metadatajson', 'scopejson', 'errorjson', 'profilejson'] as $field) {
            if (isset($data[$field]) && (is_array($data[$field]) || is_object($data[$field]))) {
                $data[$field] = source_identity::stable_json($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Filter unknown fields and convert to a DB record.
     *
     * @param string $table Table name.
     * @param array $data Data.
     * @return \stdClass
     */
    private static function to_record(string $table, array $data): \stdClass {
        if (!isset(self::TABLE_FIELDS[$table])) {
            throw new \coding_exception('Unknown FLW history table: ' . $table);
        }
        $record = [];
        foreach (self::TABLE_FIELDS[$table] as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field];
            }
        }
        return (object)$record;
    }

    /**
     * Add correction links on supported history tables.
     *
     * @param array $data Correction data.
     */
    private static function link_correction(array $data): void {
        global $DB;

        $supported = ['flwhist_source_event', 'flwhist_grade_version'];
        $recordtable = (string)($data['recordtable'] ?? '');
        $correctedtable = (string)($data['correctedtable'] ?? '');
        $recordid = (int)($data['recordid'] ?? 0);
        $correctedid = (int)($data['correctedid'] ?? 0);

        if ($recordid > 0 && $correctedid > 0 && in_array($recordtable, $supported, true)) {
            $DB->set_field($recordtable, 'correctionof', $correctedid, ['id' => $recordid]);
        }
        if ($recordid > 0 && $correctedid > 0 && in_array($correctedtable, $supported, true)) {
            $DB->set_field($correctedtable, 'supersededby', $recordid, ['id' => $correctedid]);
        }
    }

    /**
     * Apply H1B source family, source fact, and normalization defaults.
     *
     * @param string $table Table name.
     * @param array $data Data.
     * @return array
     */
    private static function apply_history_defaults(string $table, array $data): array {
        if (self::table_has_field($table, 'normpolicyversion') && empty($data['normpolicyversion'])) {
            $data['normpolicyversion'] = history_policy::NORMALIZATION_POLICY_VERSION;
        }
        if (self::table_has_field($table, 'sourcefamily') && empty($data['sourcefamily'])) {
            $data['sourcefamily'] = self::default_source_family($table, $data);
        }
        if (self::table_has_field($table, 'sourcefactkey') && empty($data['sourcefactkey']) && !empty($data['sourcekey'])) {
            $data['sourcefactkey'] = $data['sourcekey'];
        }
        return $data;
    }

    /**
     * Infer default source family for a table.
     *
     * @param string $table Table.
     * @param array $data Data.
     * @return string
     */
    private static function default_source_family(string $table, array $data): string {
        if (!empty($data['sourcesystem']) || !empty($data['sourcetype'])) {
            return history_policy::source_family((string)($data['sourcesystem'] ?? ''), (string)($data['sourcetype'] ?? ''));
        }
        $defaults = [
            'flwhist_question_attempt' => 'quiz',
            'flwhist_grade_version' => 'gradebook',
            'flwhist_grade_summary' => 'gradebook',
            'flwhist_completion' => 'completion',
            'flwhist_placement' => 'placement',
        ];
        return $defaults[$table] ?? 'unknown';
    }

    /**
     * Check whether a table allows a field.
     *
     * @param string $table Table.
     * @param string $field Field.
     * @return bool
     */
    private static function table_has_field(string $table, string $field): bool {
        return isset(self::TABLE_FIELDS[$table]) && in_array($field, self::TABLE_FIELDS[$table], true);
    }

    /**
     * Infer the simplest accurate coverage scope level.
     *
     * @param array $data Coverage data.
     * @return string
     */
    private static function infer_scope_level(array $data): string {
        if (!empty($data['userid'])) {
            return 'learner';
        }
        if (!empty($data['unitid'])) {
            return 'unit';
        }
        if (!empty($data['stageid'])) {
            return 'stage';
        }
        if (!empty($data['worldid'])) {
            return 'world';
        }
        if (!empty($data['courseid'])) {
            return 'course';
        }
        return 'system';
    }
}

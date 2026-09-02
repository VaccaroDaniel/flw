<?php
// Program 3 evidence source adapter contract for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only contract for exposing history facts to Program 3.
 */
class evidence_source_adapter {
    /** Frozen downstream contract version. */
    public const CONTRACT_VERSION = 'FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1';

    /** Maximum records exposed by one adapter call. */
    private const MAX_LIMIT = 500;

    /**
     * Return the frozen downstream contract summary.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'HistoryEvidenceSourceContract',
            'version' => self::CONTRACT_VERSION,
            'source' => 'local_flwhistory',
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
            'facttypes' => [
                'source_events',
                'attempts',
                'grades',
                'completion',
                'placement',
                'content_identities',
            ],
            'guarantees' => [
                'read_only' => true,
                'bounded_queries' => true,
                'stable_source_keys' => true,
                'coverage_included' => true,
                'no_adaptive_policy' => true,
                'no_cupkp_mastery_mutation' => true,
            ],
        ];
    }

    /**
     * Convert a source event to a neutral Program 3 evidence-source payload.
     *
     * This does not decide mastery, evidence strength, or recommendations.
     *
     * @param \stdClass $sourceevent Source event record.
     * @return array
     */
    public static function source_event_to_payload(\stdClass $sourceevent): array {
        return [
            'source' => 'local_flwhistory',
            'facttype' => 'source_event',
            'sourcekey' => (string)$sourceevent->sourcekey,
            'sourcefactkey' => $sourceevent->sourcefactkey ?? $sourceevent->sourcekey,
            'sourcesystem' => (string)$sourceevent->sourcesystem,
            'sourcefamily' => !empty($sourceevent->sourcefamily)
                ? (string)$sourceevent->sourcefamily
                : history_policy::source_family((string)$sourceevent->sourcesystem, (string)$sourceevent->sourcetype),
            'sourcetype' => (string)$sourceevent->sourcetype,
            'sourceid' => (string)$sourceevent->sourceid,
            'sourceversion' => $sourceevent->sourceversion ?? null,
            'eventtype' => (string)$sourceevent->eventtype,
            'normpolicyversion' => $sourceevent->normpolicyversion ?? history_policy::NORMALIZATION_POLICY_VERSION,
            'userid' => isset($sourceevent->userid) ? (int)$sourceevent->userid : null,
            'courseid' => isset($sourceevent->courseid) ? (int)$sourceevent->courseid : null,
            'cmid' => isset($sourceevent->cmid) ? (int)$sourceevent->cmid : null,
            'unitid' => $sourceevent->unitid ?? null,
            'activityid' => $sourceevent->activityid ?? null,
            'assessmentid' => $sourceevent->assessmentid ?? null,
            'questionid' => $sourceevent->questionid ?? null,
            'eventtime' => (int)$sourceevent->eventtime,
            'summaryjson' => $sourceevent->summaryjson ?? null,
            'payloadhash' => $sourceevent->payloadhash ?? null,
            'coverage' => coverage_service::get_coverage_for_event($sourceevent),
        ];
    }

    /**
     * Fetch bounded source-event payloads for a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public static function source_events_for_course(int $courseid, int $limit = 100, int $offset = 0): array {
        return self::course_records('flwhist_source_event', $courseid, $limit, $offset, 'eventtime DESC, id DESC',
            [self::class, 'source_event_to_payload']);
    }

    /**
     * Fetch bounded attempt payloads for a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public static function attempts_for_course(int $courseid, int $limit = 100, int $offset = 0): array {
        return self::course_records('flwhist_attempt', $courseid, $limit, $offset, 'timefinish DESC, id DESC',
            [self::class, 'attempt_to_payload']);
    }

    /**
     * Fetch bounded grade-version payloads for a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public static function grades_for_course(int $courseid, int $limit = 100, int $offset = 0): array {
        return self::course_records('flwhist_grade_version', $courseid, $limit, $offset, 'gradetime DESC, id DESC',
            [self::class, 'grade_to_payload']);
    }

    /**
     * Fetch bounded completion payloads for a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public static function completions_for_course(int $courseid, int $limit = 100, int $offset = 0): array {
        return self::course_records('flwhist_completion', $courseid, $limit, $offset, 'completiontime DESC, id DESC',
            [self::class, 'completion_to_payload']);
    }

    /**
     * Fetch bounded placement payloads for a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public static function placements_for_course(int $courseid, int $limit = 100, int $offset = 0): array {
        return self::course_records('flwhist_placement', $courseid, $limit, $offset, 'placementtime DESC, id DESC',
            [self::class, 'placement_to_payload']);
    }

    /**
     * Fetch bounded Program 1 content identity payloads for a course.
     *
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public static function content_identities_for_course(int $courseid, int $limit = 100, int $offset = 0): array {
        return self::course_records('flwhist_content_link', $courseid, $limit, $offset, 'timemodified DESC, id DESC',
            [self::class, 'content_identity_to_payload'], 'moodlecourseid');
    }

    /**
     * Convert an attempt row to the downstream payload.
     *
     * @param \stdClass $record Attempt record.
     * @return array
     */
    public static function attempt_to_payload(\stdClass $record): array {
        return [
            'source' => 'local_flwhistory',
            'facttype' => 'attempt',
            'sourcekey' => (string)$record->sourcekey,
            'sourcefactkey' => $record->sourcefactkey ?? $record->sourcekey,
            'sourceeventid' => isset($record->sourceeventid) ? (int)$record->sourceeventid : null,
            'sourcefamily' => (string)$record->sourcefamily,
            'sourcesystem' => (string)$record->sourcesystem,
            'sourcetype' => (string)$record->sourcetype,
            'sourceid' => (string)$record->sourceid,
            'sourceattemptid' => $record->sourceattemptid ?? null,
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
            'normpolicyversion' => $record->normpolicyversion ?? history_policy::NORMALIZATION_POLICY_VERSION,
            'summary' => self::decode_json($record->summaryjson ?? null),
        ];
    }

    /**
     * Convert a grade-version row to the downstream payload.
     *
     * @param \stdClass $record Grade-version record.
     * @return array
     */
    public static function grade_to_payload(\stdClass $record): array {
        return [
            'source' => 'local_flwhistory',
            'facttype' => 'grade',
            'sourcekey' => (string)$record->sourcekey,
            'sourcefactkey' => $record->sourcefactkey ?? $record->sourcekey,
            'sourceeventid' => isset($record->sourceeventid) ? (int)$record->sourceeventid : null,
            'sourcefamily' => (string)$record->sourcefamily,
            'userid' => (int)$record->userid,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'gradeitemid' => isset($record->gradeitemid) ? (int)$record->gradeitemid : null,
            'gradegradeid' => isset($record->gradegradeid) ? (int)$record->gradegradeid : null,
            'gradehistoryid' => isset($record->gradehistoryid) ? (int)$record->gradehistoryid : null,
            'itemmodule' => $record->itemmodule ?? null,
            'iteminstance' => isset($record->iteminstance) ? (int)$record->iteminstance : null,
            'rawgrade' => self::float_or_null($record->rawgrade ?? null),
            'finalgrade' => self::float_or_null($record->finalgrade ?? null),
            'previousgrade' => self::float_or_null($record->previousgrade ?? null),
            'action' => (string)$record->action,
            'gradetime' => isset($record->gradetime) ? (int)$record->gradetime : null,
            'correctionof' => isset($record->correctionof) ? (int)$record->correctionof : null,
            'supersededby' => isset($record->supersededby) ? (int)$record->supersededby : null,
            'normpolicyversion' => $record->normpolicyversion ?? history_policy::NORMALIZATION_POLICY_VERSION,
            'summary' => self::decode_json($record->summaryjson ?? null),
        ];
    }

    /**
     * Convert a completion row to the downstream payload.
     *
     * @param \stdClass $record Completion record.
     * @return array
     */
    public static function completion_to_payload(\stdClass $record): array {
        return [
            'source' => 'local_flwhistory',
            'facttype' => 'completion',
            'sourcekey' => (string)$record->sourcekey,
            'sourcefactkey' => $record->sourcefactkey ?? $record->sourcekey,
            'sourceeventid' => isset($record->sourceeventid) ? (int)$record->sourceeventid : null,
            'sourcefamily' => (string)$record->sourcefamily,
            'userid' => (int)$record->userid,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'completionstate' => isset($record->completionstate) ? (int)$record->completionstate : null,
            'viewed' => isset($record->viewed) ? (int)$record->viewed : null,
            'completiontime' => isset($record->completiontime) ? (int)$record->completiontime : null,
            'normpolicyversion' => $record->normpolicyversion ?? history_policy::NORMALIZATION_POLICY_VERSION,
            'details' => self::decode_json($record->detailsjson ?? null),
        ];
    }

    /**
     * Convert a placement row to the downstream payload.
     *
     * @param \stdClass $record Placement record.
     * @return array
     */
    public static function placement_to_payload(\stdClass $record): array {
        return [
            'source' => 'local_flwhistory',
            'facttype' => 'placement',
            'sourcekey' => (string)$record->sourcekey,
            'sourcefactkey' => $record->sourcefactkey ?? $record->sourcekey,
            'sourceeventid' => isset($record->sourceeventid) ? (int)$record->sourceeventid : null,
            'sourcefamily' => (string)$record->sourcefamily,
            'sourcesystem' => (string)$record->sourcesystem,
            'sourcetype' => (string)$record->sourcetype,
            'userid' => (int)$record->userid,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'previouslevel' => $record->previouslevel ?? null,
            'currentlevel' => $record->currentlevel ?? null,
            'placementstatus' => (string)$record->placementstatus,
            'score' => self::float_or_null($record->score ?? null),
            'confidence' => self::float_or_null($record->confidence ?? null),
            'placementtime' => isset($record->placementtime) ? (int)$record->placementtime : null,
            'normpolicyversion' => $record->normpolicyversion ?? history_policy::NORMALIZATION_POLICY_VERSION,
            'profile' => self::decode_json($record->profilejson ?? null),
        ];
    }

    /**
     * Convert a content-link row to the downstream payload.
     *
     * @param \stdClass $record Content-link record.
     * @return array
     */
    public static function content_identity_to_payload(\stdClass $record): array {
        return [
            'source' => 'local_flwhistory',
            'facttype' => 'content_identity',
            'sourcekey' => (string)$record->sourcekey,
            'courseid' => isset($record->moodlecourseid) ? (int)$record->moodlecourseid : null,
            'sectionid' => isset($record->moodlesectionid) ? (int)$record->moodlesectionid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'scoidentifier' => $record->scoidentifier ?? null,
            'worldid' => $record->worldid ?? null,
            'stageid' => $record->stageid ?? null,
            'unitid' => $record->unitid ?? null,
            'lessonid' => $record->lessonid ?? null,
            'componentid' => $record->componentid ?? null,
            'activityid' => $record->activityid ?? null,
            'assessmentid' => $record->assessmentid ?? null,
            'questionid' => $record->questionid ?? null,
            'freshness' => (string)$record->freshness,
            'resolver' => (string)$record->resolver,
            'status' => (string)$record->status,
            'metadata' => self::decode_json($record->metadatajson ?? null),
        ];
    }

    /**
     * Fetch bounded course records and convert them to adapter payloads.
     *
     * @param string $table Table name.
     * @param int $courseid Course id.
     * @param int $limit Result limit.
     * @param int $offset Result offset.
     * @param string $order Sort order.
     * @param callable $mapper Mapper callback.
     * @param string $coursefield Course field name.
     * @return array
     */
    private static function course_records(
        string $table,
        int $courseid,
        int $limit,
        int $offset,
        string $order,
        callable $mapper,
        string $coursefield = 'courseid'
    ): array {
        global $DB;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('A course ID is required.');
        }
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $offset = max(0, $offset);
        $where = "{$coursefield} = :courseid";
        $params = ['courseid' => $courseid];
        $total = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {{$table}} WHERE {$where}", $params);
        $records = $DB->get_records_select($table, $where, $params, $order, '*', $offset, $limit);

        return [
            'type' => 'HistoryEvidenceSource',
            'contract' => self::CONTRACT_VERSION,
            'facttable' => $table,
            'courseid' => $courseid,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'hasmore' => ($offset + $limit) < $total,
            ],
            'records' => array_map($mapper, array_values($records)),
        ];
    }

    /**
     * Decode JSON safely.
     *
     * @param mixed $json JSON field.
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

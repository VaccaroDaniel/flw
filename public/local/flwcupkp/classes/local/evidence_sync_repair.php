<?php
// Repair helpers for C-UP-KP evidence sync.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Finds and repairs Moodle activity attempts that should have C-UP-KP evidence.
 */
final class evidence_sync_repair {
    /**
     * Count pending mapped quiz attempts across course/unit scopes.
     *
     * @param array $scopes
     * @return int
     */
    public static function pending_quiz_attempt_count_for_scopes(array $scopes): int {
        global $DB;

        $params = [];
        $scope = self::scope_condition($scopes, $params, 'pqac');
        if ($scope === '') {
            return 0;
        }

        return (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT qa.id)
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
               JOIN {modules} m ON m.name = :quizmod
               JOIN {course_modules} cm ON cm.module = m.id
                    AND cm.instance = q.id
                    AND cm.course = q.course
               JOIN {flwcupkp_object} o ON o.cmid = cm.id
              WHERE {$scope}
                AND " . self::attempt_pending_sql(),
            $params + [
                'quizmod' => 'quiz',
                'finished' => 'finished',
                'evidencetype' => 'quiz_attempt_submitted',
            ]
        );
    }

    /**
     * Pending mapped quiz attempts across course/unit scopes.
     *
     * @param array $scopes
     * @param int $limit
     * @return array
     */
    public static function pending_quiz_attempts_for_scopes(array $scopes, int $limit = 0): array {
        global $DB;

        $params = [];
        $scope = self::scope_condition($scopes, $params, 'pqa');
        if ($scope === '') {
            return [];
        }

        return array_values($DB->get_records_sql(
            "SELECT DISTINCT qa.id,
                    qa.id AS attemptid,
                    qa.userid,
                    qa.timefinish,
                    o.courseid,
                    o.unitcode,
                    o.id AS objectid,
                    o.externalid,
                    o.title,
                    cm.id AS cmid,
                    q.name AS quizname
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
               JOIN {modules} m ON m.name = :quizmod
               JOIN {course_modules} cm ON cm.module = m.id
                    AND cm.instance = q.id
                    AND cm.course = q.course
               JOIN {flwcupkp_object} o ON o.cmid = cm.id
              WHERE {$scope}
                AND " . self::attempt_pending_sql() . "
           ORDER BY qa.timefinish ASC, qa.id ASC",
            $params + [
                'quizmod' => 'quiz',
                'finished' => 'finished',
                'evidencetype' => 'quiz_attempt_submitted',
            ],
            0,
            max(0, $limit)
        ));
    }

    /**
     * Pending mapped quiz attempts for one course/unit scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function pending_quiz_attempts(int $courseid, string $unitcode = '', int $limit = 0): array {
        return self::pending_quiz_attempts_for_scopes([[
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]], $limit);
    }

    /**
     * Repair one quiz attempt.
     *
     * @param int $attemptid
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function repair_quiz_attempt(int $attemptid, int $courseid, string $unitcode = ''): array {
        $context = self::attempt_context($attemptid, $courseid, $unitcode);

        repository::audit('quiz_evidence_repair_requested', 'quiz_attempt', $attemptid, [
            'courseid' => $courseid,
            'unitcode' => (string)$context->unitcode,
            'cmid' => (int)$context->cmid,
            'objectid' => (int)$context->objectid,
        ]);

        $result = quiz_evidence_adapter::replay_attempt($attemptid, $courseid, (int)$context->cmid);
        repository::audit('quiz_evidence_repair_completed', 'quiz_attempt', $attemptid, [
            'courseid' => $courseid,
            'unitcode' => (string)$context->unitcode,
            'cmid' => (int)$context->cmid,
            'objectid' => (int)$context->objectid,
            'adapter_result' => $result,
        ]);

        return $result + [
            'attemptid' => $attemptid,
            'courseid' => $courseid,
            'unitcode' => (string)$context->unitcode,
        ];
    }

    /**
     * Repair all pending quiz attempts in one course/unit scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function repair_pending_quiz_attempts(int $courseid, string $unitcode = ''): array {
        $pending = self::pending_quiz_attempts($courseid, $unitcode);
        if (!$pending) {
            return [
                'status' => 'none',
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'found' => 0,
                'processed' => 0,
                'created' => 0,
                'ignored' => 0,
                'failed' => 0,
                'attemptids' => [],
                'failures' => [],
            ];
        }

        $attemptids = array_map(static function(\stdClass $row): int {
            return (int)$row->attemptid;
        }, $pending);
        repository::audit('quiz_evidence_repair_all_queued', 'course', $courseid, [
            'unitcode' => $unitcode,
            'pending' => count($pending),
            'attemptids' => $attemptids,
        ]);

        $summary = [
            'status' => 'completed',
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'found' => count($pending),
            'processed' => 0,
            'created' => 0,
            'ignored' => 0,
            'failed' => 0,
            'attemptids' => $attemptids,
            'failures' => [],
        ];

        foreach ($pending as $row) {
            $attemptid = (int)$row->attemptid;
            try {
                $result = self::repair_quiz_attempt($attemptid, $courseid, $unitcode);
                $summary['processed']++;
                $summary['created'] += count($result['evidenceids'] ?? []);
                if (($result['status'] ?? '') !== 'processed') {
                    $summary['ignored']++;
                }
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['failures'][] = [
                    'attemptid' => $attemptid,
                    'message' => $e->getMessage(),
                ];
                repository::audit('quiz_evidence_repair_failed', 'quiz_attempt', $attemptid, [
                    'courseid' => $courseid,
                    'unitcode' => $unitcode,
                    'message' => $e->getMessage(),
                    'source' => 'bulk_repair',
                ]);
            }
        }

        if ($summary['failed'] > 0) {
            $summary['status'] = $summary['processed'] > 0 ? 'completed_with_errors' : 'failed';
        }

        repository::audit('quiz_evidence_repair_all_completed', 'course', $courseid, $summary);
        return $summary;
    }

    /**
     * Recent repair history for Dashboard course/unit scopes.
     *
     * @param array $scopes
     * @param int $limit
     * @param bool $includerequested
     * @return array
     */
    public static function recent_repair_history_for_scopes(array $scopes, int $limit = 5,
            bool $includerequested = false): array {
        global $DB;

        $normalizedscopes = self::normalize_scopes($scopes);
        if (!$normalizedscopes) {
            return [];
        }

        $actions = [
            'quiz_evidence_repair_all_completed',
            'quiz_evidence_repair_all_queued',
            'quiz_evidence_repair_completed',
            'quiz_evidence_repair_failed',
        ];
        if ($includerequested) {
            $actions[] = 'quiz_evidence_repair_requested';
        }
        [$actionsql, $params] = $DB->get_in_or_equal($actions, SQL_PARAMS_NAMED, 'repairaction');
        $records = $DB->get_records_sql(
            "SELECT a.id,
                    a.action,
                    a.targettype,
                    a.targetid,
                    a.detailsjson,
                    a.userid,
                    a.timecreated,
                    u.firstname,
                    u.lastname,
                    u.username
               FROM {flwcupkp_audit} a
          LEFT JOIN {user} u ON u.id = a.userid
              WHERE a.action {$actionsql}
           ORDER BY a.timecreated DESC, a.id DESC",
            $params,
            0,
            max(20, $limit * 8)
        );

        $history = [];
        foreach ($records as $record) {
            $details = json_decode((string)$record->detailsjson, true);
            if (!is_array($details)) {
                $details = [];
            }
            if (!self::audit_row_in_scopes($record, $details, $normalizedscopes)) {
                continue;
            }

            $history[] = [
                'id' => (int)$record->id,
                'action' => (string)$record->action,
                'targettype' => (string)($record->targettype ?? ''),
                'targetid' => (int)($record->targetid ?? 0),
                'courseid' => (int)($details['courseid'] ?? ($record->targettype === 'course' ? $record->targetid : 0)),
                'unitcode' => (string)($details['unitcode'] ?? ''),
                'details' => $details,
                'userid' => (int)($record->userid ?? 0),
                'firstname' => (string)($record->firstname ?? ''),
                'lastname' => (string)($record->lastname ?? ''),
                'username' => (string)($record->username ?? ''),
                'timecreated' => (int)$record->timecreated,
            ];
            if (count($history) >= $limit) {
                break;
            }
        }

        return $history;
    }

    /**
     * Validate and load attempt context.
     *
     * @param int $attemptid
     * @param int $courseid
     * @param string $unitcode
     * @return \stdClass
     */
    private static function attempt_context(int $attemptid, int $courseid, string $unitcode = ''): \stdClass {
        global $DB;

        if ($attemptid <= 0 || $courseid <= 0) {
            throw new \invalid_parameter_exception('Quiz attempt and course are required.');
        }

        $record = $DB->get_record_sql(
            "SELECT qa.id AS attemptid,
                    qa.userid,
                    qa.timefinish,
                    q.course,
                    cm.id AS cmid,
                    o.id AS objectid,
                    o.courseid,
                    o.unitcode
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
               JOIN {modules} m ON m.name = :quizmod
               JOIN {course_modules} cm ON cm.module = m.id
                    AND cm.instance = q.id
                    AND cm.course = q.course
               JOIN {flwcupkp_object} o ON o.cmid = cm.id
              WHERE qa.id = :attemptid",
            [
                'quizmod' => 'quiz',
                'attemptid' => $attemptid,
            ],
            IGNORE_MULTIPLE
        );
        if (!$record) {
            throw new \invalid_parameter_exception('Quiz attempt is not mapped to a C-UP-KP object.');
        }
        if ((int)$record->course !== $courseid || (!empty($record->courseid) && (int)$record->courseid !== $courseid)) {
            throw new \invalid_parameter_exception('Quiz attempt does not belong to the selected course.');
        }
        if ($unitcode !== '' && (string)$record->unitcode !== $unitcode) {
            throw new \invalid_parameter_exception('Mapped object does not belong to the selected unit.');
        }

        return $record;
    }

    /**
     * SQL for a submitted quiz attempt with no matching C-UP-KP quiz evidence.
     *
     * @return string
     */
    private static function attempt_pending_sql(): string {
        global $DB;

        $attemptprefix = $DB->sql_concat("'quiz_attempt:'", 'qa.id', "':%'");
        return "qa.preview = 0
                AND qa.state = :finished
                AND qa.timefinish > 0
                AND NOT EXISTS (
                    SELECT 1
                      FROM {flwcupkp_evidence} e
                     WHERE e.objectid = o.id
                       AND e.evidencetype = :evidencetype
                       AND e.sourceattempt LIKE {$attemptprefix}
                )";
    }

    /**
     * Course/unit scope SQL.
     *
     * @param array $scopes
     * @param array $params
     * @param string $prefix
     * @return string
     */
    private static function scope_condition(array $scopes, array &$params, string $prefix): string {
        $parts = [];
        $seen = [];
        $index = 0;
        foreach ($scopes as $scope) {
            $courseid = (int)($scope['courseid'] ?? 0);
            $unitcode = trim((string)($scope['unitcode'] ?? ''));
            if ($courseid <= 0) {
                continue;
            }

            $key = $courseid . ':' . $unitcode;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $courseparam = $prefix . 'course' . $index;
            $unitparam = $prefix . 'unit' . $index;
            $params[$courseparam] = $courseid;
            if ($unitcode !== '') {
                $params[$unitparam] = $unitcode;
                $parts[] = "(o.courseid = :{$courseparam} AND o.unitcode = :{$unitparam})";
            } else {
                $parts[] = "o.courseid = :{$courseparam}";
            }
            $index++;
        }

        return $parts ? '(' . implode(' OR ', $parts) . ')' : '';
    }

    /**
     * Normalize course/unit scopes.
     *
     * @param array $scopes
     * @return array
     */
    private static function normalize_scopes(array $scopes): array {
        $normalized = [];
        $seen = [];
        foreach ($scopes as $scope) {
            $courseid = (int)($scope['courseid'] ?? 0);
            $unitcode = trim((string)($scope['unitcode'] ?? ''));
            if ($courseid <= 0) {
                continue;
            }

            $key = $courseid . ':' . $unitcode;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
            ];
        }

        return $normalized;
    }

    /**
     * Whether an audit row belongs to one of the Dashboard scopes.
     *
     * @param \stdClass $record
     * @param array $details
     * @param array $scopes
     * @return bool
     */
    private static function audit_row_in_scopes(\stdClass $record, array $details, array $scopes): bool {
        $courseid = (int)($details['courseid'] ?? 0);
        if ($courseid <= 0 && (string)($record->targettype ?? '') === 'course') {
            $courseid = (int)($record->targetid ?? 0);
        }
        $unitcode = trim((string)($details['unitcode'] ?? ''));

        if ($courseid <= 0) {
            return false;
        }

        foreach ($scopes as $scope) {
            if ((int)$scope['courseid'] !== $courseid) {
                continue;
            }
            if ((string)$scope['unitcode'] !== '' && $unitcode !== '' &&
                    (string)$scope['unitcode'] !== $unitcode) {
                continue;
            }
            return true;
        }

        return false;
    }
}

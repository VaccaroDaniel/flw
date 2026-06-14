<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwaiassessment\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Database helper for FLW AI assessment results.
 */
class result_repository {
    /** @var string Waiting for offline scoring. */
    public const STATUS_PENDING = 'pending';

    /** @var string Being processed by the scheduled task. */
    public const STATUS_PROCESSING = 'processing';

    /** @var string AI result is available. */
    public const STATUS_COMPLETE = 'complete';

    /** @var string Scoring failed. */
    public const STATUS_FAILED = 'failed';

    /** @var string Record does not yet have enough learner input to score. */
    public const STATUS_NEEDS_INPUT = 'needsinput';

    /**
     * Create a pending result record.
     *
     * @param array $data Result data.
     * @return int New record id.
     */
    public static function create_pending(array $data): int {
        global $DB;

        $now = time();
        $record = (object) array_merge([
            'userid' => 0,
            'courseid' => 0,
            'cmid' => 0,
            'activitytype' => '',
            'sourcecomponent' => '',
            'submissionid' => 0,
            'skilltype' => 'writing',
            'rawtext' => null,
            'transcript' => null,
            'audiopath' => null,
            'prompttext' => null,
            'status' => self::STATUS_PENDING,
            'cefrlevel' => '',
            'totalscore' => 0,
            'rubricjson' => null,
            'weakkpjson' => null,
            'recommendjson' => null,
            'airesponsejson' => null,
            'error' => null,
            'teachercefrlevel' => '',
            'teacherscore' => 0,
            'teachernote' => null,
            'teacherconfirmed' => 0,
            'confirmedby' => 0,
            'timeconfirmed' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        return (int) $DB->insert_record('local_flwai_results', $record);
    }

    /**
     * Fetch results for the review page.
     *
     * @param array $filters Supported keys: skilltype, status.
     * @param int $limit Number of records.
     * @return array
     */
    public static function get_results(array $filters = [], int $limit = 100): array {
        global $DB;

        $conditions = [];
        $params = [];

        if (!empty($filters['skilltype'])) {
            $conditions[] = 'skilltype = :skilltype';
            $params['skilltype'] = $filters['skilltype'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $where = $conditions ? implode(' AND ', $conditions) : '1 = 1';

        return $DB->get_records_select('local_flwai_results', $where, $params, 'timecreated DESC', '*', 0, $limit);
    }

    /**
     * Fetch a single result.
     *
     * @param int $id Record id.
     * @return \stdClass
     */
    public static function get_result(int $id): \stdClass {
        global $DB;

        return $DB->get_record('local_flwai_results', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Return records that are ready for background scoring.
     *
     * @param int $limit Number of records.
     * @return array
     */
    public static function get_pending_for_processing(int $limit = 10): array {
        global $DB;

        return $DB->get_records('local_flwai_results', ['status' => self::STATUS_PENDING], 'timecreated ASC', '*', 0, $limit);
    }

    /**
     * Save AI scoring output.
     *
     * @param int $id Result id.
     * @param array $response Local scoring API response.
     */
    public static function save_ai_response(int $id, array $response): void {
        global $DB;

        $record = (object) [
            'id' => $id,
            'status' => self::STATUS_COMPLETE,
            'cefrlevel' => (string) ($response['cefr_level'] ?? $response['cefrlevel'] ?? ''),
            'totalscore' => (float) ($response['total_score'] ?? $response['totalscore'] ?? 0),
            'rubricjson' => self::encode_json($response['rubric'] ?? []),
            'weakkpjson' => self::encode_json($response['weak_kps'] ?? $response['weak_knowledge_points'] ?? []),
            'recommendjson' => self::encode_json($response['recommended_lessons'] ?? $response['recommendations'] ?? []),
            'airesponsejson' => self::encode_json($response),
            'error' => null,
            'timemodified' => time(),
        ];

        $DB->update_record('local_flwai_results', $record);
    }

    /**
     * Update processing status and optional error.
     *
     * @param int $id Result id.
     * @param string $status Status value.
     * @param string|null $error Error message.
     */
    public static function update_status(int $id, string $status, ?string $error = null): void {
        global $DB;

        $DB->update_record('local_flwai_results', (object) [
            'id' => $id,
            'status' => $status,
            'error' => $error,
            'timemodified' => time(),
        ]);
    }

    /**
     * Save teacher confirmation.
     *
     * @param int $id Result id.
     * @param string $cefrlevel Confirmed CEFR level.
     * @param float $score Confirmed score.
     * @param string $note Teacher note.
     * @param int $userid Confirming user id.
     */
    public static function confirm_teacher_review(int $id, string $cefrlevel, float $score, string $note, int $userid): void {
        global $DB;

        $DB->update_record('local_flwai_results', (object) [
            'id' => $id,
            'teachercefrlevel' => $cefrlevel,
            'teacherscore' => $score,
            'teachernote' => $note,
            'teacherconfirmed' => 1,
            'confirmedby' => $userid,
            'timeconfirmed' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * JSON encode stable Moodle text fields.
     *
     * @param mixed $value Value to encode.
     * @return string
     */
    private static function encode_json($value): string {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

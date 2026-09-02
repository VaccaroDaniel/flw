<?php
// Reconciliation service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service boundary for repair/backfill/replay run metadata.
 */
class reconciliation_service {
    /**
     * Start a reconciliation run.
     *
     * @param string $runtype Run type.
     * @param array $scope Scope summary.
     * @param int|null $userid Actor id.
     * @param int|null $courseid Course id.
     * @return int Run id.
     */
    public static function start_run(string $runtype, array $scope = [], ?int $userid = null, ?int $courseid = null): int {
        $now = time();
        return repository::upsert_reconcile_run([
            'runtype' => $runtype,
            'scopejson' => $scope,
            'status' => 'running',
            'userid' => $userid,
            'courseid' => $courseid,
            'timestarted' => $now,
        ]);
    }

    /**
     * Mark a reconciliation run successful.
     *
     * @param int $runid Run id.
     * @param array $counts Count fields.
     */
    public static function finish_run(int $runid, array $counts = []): void {
        self::complete_run($runid, 'finished', $counts);
    }

    /**
     * Mark a reconciliation run failed.
     *
     * @param int $runid Run id.
     * @param string $message Error message.
     * @param array $counts Count fields.
     */
    public static function fail_run(int $runid, string $message, array $counts = []): void {
        $counts['errorjson'] = ['message' => $message];
        self::complete_run($runid, 'failed', $counts);
    }

    /**
     * Get recent reconciliation runs.
     *
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_recent_runs(int $limit = 20): array {
        return repository::get_recent_reconcile_runs($limit);
    }

    /**
     * Complete a run.
     *
     * @param int $runid Run id.
     * @param string $status New status.
     * @param array $data Extra count/error data.
     */
    private static function complete_run(int $runid, string $status, array $data): void {
        global $DB;

        $record = $DB->get_record('flwhist_reconcile_run', ['id' => $runid], '*', MUST_EXIST);
        $record->status = $status;
        $record->timefinished = time();
        foreach (['recordsseen', 'recordscreated', 'recordsupdated', 'recordsskipped', 'recordsfailed'] as $field) {
            if (array_key_exists($field, $data)) {
                $record->{$field} = (int)$data[$field];
            }
        }
        if (array_key_exists('errorjson', $data)) {
            $record->errorjson = is_string($data['errorjson'])
                ? $data['errorjson']
                : source_identity::stable_json($data['errorjson']);
        }
        $record->timemodified = time();
        $DB->update_record('flwhist_reconcile_run', $record);
    }
}


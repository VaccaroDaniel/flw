<?php
// Completion service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service boundary for completion history.
 */
class completion_service {
    /**
     * Record or replay a completion transition.
     *
     * @param array $data Completion data.
     * @return int Completion history id.
     */
    public static function record_completion(array $data): int {
        return repository::upsert_completion($data);
    }

    /**
     * Record a Moodle completion row.
     *
     * @param \stdClass $completion Completion row.
     * @param array $extra Extra normalized fields.
     * @return int Completion history id.
     */
    public static function record_moodle_completion(\stdClass $completion, array $extra = []): int {
        return repository::upsert_completion(normalizer::completion_to_record($completion, $extra));
    }
}


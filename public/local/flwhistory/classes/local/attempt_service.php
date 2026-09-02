<?php
// Attempt service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service boundary for attempt and item-attempt history.
 */
class attempt_service {
    /**
     * Record or replay a normalized attempt.
     *
     * @param array $data Attempt data.
     * @return int Attempt id.
     */
    public static function record_attempt(array $data): int {
        return repository::upsert_attempt($data);
    }

    /**
     * Record a Moodle quiz attempt row.
     *
     * @param \stdClass $attempt Quiz attempt row.
     * @param array $extra Extra normalized fields.
     * @return int Attempt id.
     */
    public static function record_quiz_attempt(\stdClass $attempt, array $extra = []): int {
        return repository::upsert_attempt(normalizer::quiz_attempt_to_attempt($attempt, $extra));
    }

    /**
     * Record or replay a normalized question attempt.
     *
     * @param array $data Question attempt data.
     * @return int Question attempt history id.
     */
    public static function record_question_attempt(array $data): int {
        return repository::upsert_question_attempt($data);
    }

    /**
     * Fetch an attempt by source key.
     *
     * @param string $sourcekey Source key.
     * @return \stdClass|null
     */
    public static function get_attempt_by_sourcekey(string $sourcekey): ?\stdClass {
        return repository::get_attempt_by_sourcekey($sourcekey);
    }
}


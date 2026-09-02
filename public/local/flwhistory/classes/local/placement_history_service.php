<?php
// Placement history service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service boundary for placement source facts.
 */
class placement_history_service {
    /**
     * Record or replay a placement source fact.
     *
     * @param array $data Placement data.
     * @return int Placement history id.
     */
    public static function record_placement(array $data): int {
        return repository::upsert_placement($data);
    }

    /**
     * Record a FLW placement row.
     *
     * @param \stdClass $placement Placement row.
     * @param array $extra Extra normalized fields.
     * @return int Placement history id.
     */
    public static function record_flwplacement(\stdClass $placement, array $extra = []): int {
        return repository::upsert_placement(normalizer::placement_to_history($placement, $extra));
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
        return repository::get_placement_history($userid, $courseid, $limit);
    }
}


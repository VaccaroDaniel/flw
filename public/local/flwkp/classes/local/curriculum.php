<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read helpers for the FLW curriculum graph.
 *
 * This class intentionally stays small for the MVP. Future dashboard and
 * placement services can build on these queries without duplicating joins.
 *
 * @package    local_flwkp
 */
class curriculum {
    /**
     * Return knowledge points for a unit code.
     *
     * @param string $unitcode Unit code, for example EN-A1-U01.
     * @return array
     */
    public static function get_points_for_unit(string $unitcode): array {
        global $DB;

        $sql = "SELECT kp.*, d.code AS domaincode, d.name AS domainname,
                       u.code AS unitcode, u.name AS unitname,
                       l.code AS levelcode, lang.code AS languagecode
                  FROM {local_flwkp_points} kp
                  JOIN {local_flwkp_domains} d ON d.id = kp.domainid
                  JOIN {local_flwkp_units} u ON u.id = kp.unitid
                  JOIN {local_flwkp_levels} l ON l.id = u.levelid
                  JOIN {local_flwkp_languages} lang ON lang.id = l.languageid
                 WHERE u.code = :unitcode
              ORDER BY d.sortorder, kp.sortorder";

        return $DB->get_records_sql($sql, ['unitcode' => $unitcode]);
    }

    /**
     * Return a single knowledge point by code.
     *
     * @param string $code Knowledge point code.
     * @return \stdClass|false
     */
    public static function get_point_by_code(string $code) {
        global $DB;

        return $DB->get_record('local_flwkp_points', ['code' => $code]);
    }

    /**
     * Link a Moodle item to a knowledge point.
     *
     * @param string $pointcode Knowledge point code.
     * @param string $component Moodle component, for example mod_quiz.
     * @param string $itemtype Item type, for example question or activity.
     * @param int $itemid Moodle item id.
     * @param float $weight Contribution weight.
     * @return int New mapping id.
     */
    public static function add_mapping(
        string $pointcode,
        string $component,
        string $itemtype,
        int $itemid,
        float $weight = 1.0
    ): int {
        global $DB;

        $point = self::get_point_by_code($pointcode);
        if (!$point) {
            throw new \moodle_exception('invalidknowledgepoint', 'local_flwkp', '', $pointcode);
        }

        return $DB->insert_record('local_flwkp_mappings', (object) [
            'pointid' => $point->id,
            'component' => $component,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'weight' => $weight,
            'timecreated' => time(),
        ]);
    }
}

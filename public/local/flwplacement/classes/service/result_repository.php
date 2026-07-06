<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwplacement\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for FLW placement attempts.
 *
 * @package    local_flwplacement
 */
class result_repository {
    /**
     * Save a placement result.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param array $result Placement result JSON decoded as an array.
     * @param array $attempt Attempt evidence decoded as an array.
     * @return int Record id.
     */
    public static function save_result(int $userid, int $courseid, array $result, array $attempt): int {
        global $DB;

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'cefrlevel' => clean_param($result['cefr_level'] ?? '', PARAM_TEXT),
            'recommendedcourse' => clean_param($result['recommended_course'] ?? '', PARAM_TEXT),
            'startingunit' => (int)($result['starting_unit'] ?? 0),
            'confidencescore' => (int)($result['confidence_score'] ?? 0),
            'weightedscore' => (float)($result['weighted_score'] ?? 0),
            'skillprofilejson' => json_encode($result['skill_profile'] ?? [], JSON_UNESCAPED_SLASHES),
            'skillpercentjson' => json_encode($result['skill_percentages'] ?? [], JSON_UNESCAPED_SLASHES),
            'weakskillsjson' => json_encode($result['weak_skill_warnings'] ?? [], JSON_UNESCAPED_SLASHES),
            'resultjson' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'attemptjson' => json_encode($attempt, JSON_UNESCAPED_SLASHES),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        return $DB->insert_record('local_flwplacement', $record);
    }

    /**
     * Return site-level placement reports.
     *
     * @param int|null $userid Optional learner filter.
     * @return array
     */
    public static function get_results(?int $userid = null): array {
        global $DB;

        $params = ['courseid' => SITEID];
        $where = 'courseid = :courseid';
        if ($userid !== null) {
            $where .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }

        return $DB->get_records_select('local_flwplacement', $where, $params, 'timecreated DESC');
    }

    /**
     * Return a single report.
     *
     * @param int $id Record id.
     * @return \stdClass
     */
    public static function get_result(int $id): \stdClass {
        global $DB;

        return $DB->get_record('local_flwplacement', ['id' => $id], '*', MUST_EXIST);
    }
}

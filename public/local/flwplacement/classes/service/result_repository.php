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
            'cefrlevel' => clean_param($result['overall_cefr'] ?? $result['cefr_level'] ?? '', PARAM_TEXT),
            'recommendedcourse' => clean_param($result['recommended_course'] ?? '', PARAM_TEXT),
            'startingunit' => (int)($result['recommended_start_unit'] ?? $result['starting_unit'] ?? 0),
            'confidencescore' => (int)($result['confidence_score'] ?? round(((float)($result['placement_confidence'] ?? 0)) * 100)),
            'weightedscore' => (float)($result['weighted_score'] ?? 0),
            'skillprofilejson' => self::encode_json($result['skill_profile'] ?? $result['skill_levels'] ?? []),
            'skillpercentjson' => json_encode($result['skill_percentages'] ?? [], JSON_UNESCAPED_SLASHES),
            'weakskillsjson' => json_encode($result['weak_skill_warnings'] ?? [], JSON_UNESCAPED_SLASHES),
            'resultjson' => self::encode_json($result),
            'attemptjson' => self::encode_json($attempt),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $id = (int) $DB->insert_record('local_flwplacement', $record);
        self::save_latest_profile($userid, $id, $result);

        return $id;
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

    /**
     * Return the latest learning-map profile for a learner/course key.
     *
     * @param int $userid User id.
     * @param string $coursekey FLW course key.
     * @return \stdClass|false
     */
    public static function get_latest_profile(int $userid, string $coursekey = '') {
        global $DB;

        $params = ['userid' => $userid];
        $where = 'userid = :userid';
        if ($coursekey !== '') {
            $where .= ' AND coursekey = :coursekey';
            $params['coursekey'] = $coursekey;
        }

        $records = $DB->get_records_select('local_flwplacement_profile', $where, $params, 'timemodified DESC', '*', 0, 1);
        return $records ? reset($records) : false;
    }

    /**
     * Save the latest placement learning-map profile while preserving history.
     *
     * @param int $userid User id.
     * @param int $resultid Attempt/result id.
     * @param array $result Placement result.
     */
    private static function save_latest_profile(int $userid, int $resultid, array $result): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_flwplacement_profile')) {
            return;
        }

        $now = time();
        $coursekey = clean_param($result['course'] ?? $result['recommended_course'] ?? 'FLW_REAL_WORLD', PARAM_TEXT);
        $existing = $DB->get_record('local_flwplacement_profile', [
            'userid' => $userid,
            'coursekey' => $coursekey,
        ]);
        $history = [];
        if ($existing && !empty($existing->placementhistoryjson)) {
            $decoded = json_decode($existing->placementhistoryjson, true);
            if (is_array($decoded)) {
                $history = $decoded;
            }
        }
        $history[] = [
            'resultid' => $resultid,
            'placement_date' => $result['placement_date'] ?? gmdate('c', $now),
            'overall_cefr' => $result['overall_cefr'] ?? $result['cefr_level'] ?? '',
            'status' => $result['placement_status'] ?? '',
        ];
        $history = array_slice($history, -20);

        $record = (object) [
            'userid' => $userid,
            'coursekey' => $coursekey,
            'latestresultid' => $resultid,
            'overallcefr' => clean_param($result['overall_cefr'] ?? $result['cefr_level'] ?? '', PARAM_TEXT),
            'recommendedstartunit' => (int)($result['recommended_start_unit'] ?? $result['starting_unit'] ?? 0),
            'nextcheckpointunit' => (int)($result['next_checkpoint_unit'] ?? 0),
            'placementconfidence' => (float)($result['placement_confidence'] ?? (($result['confidence_score'] ?? 0) / 100)),
            'placementstatus' => clean_param($result['placement_status'] ?? '', PARAM_TEXT),
            'skilllevelsjson' => self::encode_json($result['skill_levels'] ?? []),
            'kpmasteryjson' => self::encode_json($result['kp_mastery'] ?? []),
            'supportflagsjson' => self::encode_json($result['support_flags'] ?? []),
            'speakingprofilejson' => self::encode_json($result['speaking_profile'] ?? []),
            'learningpathjson' => self::encode_json($result['learning_path'] ?? []),
            'profilejson' => self::encode_json($result),
            'placementhistoryjson' => self::encode_json($history),
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_flwplacement_profile', $record);
            return;
        }

        $record->timecreated = $now;
        $DB->insert_record('local_flwplacement_profile', $record);
    }

    /**
     * JSON encode placement data for DB text fields.
     *
     * @param mixed $value Value to encode.
     * @return string
     */
    private static function encode_json($value): string {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

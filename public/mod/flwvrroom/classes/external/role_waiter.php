<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwvrroom\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * External service for generating AI role-character replies.
 */
class role_waiter extends \external_api {
    /**
     * Parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
            'character' => new \external_value(PARAM_TEXT, 'Character name', VALUE_DEFAULT, 'Waiter'),
            'role' => new \external_value(PARAM_TEXT, 'Character role', VALUE_DEFAULT, 'Cafe waiter'),
            'scenario' => new \external_value(PARAM_TEXT, 'Scenario', VALUE_DEFAULT, ''),
            'cefrlevel' => new \external_value(PARAM_TEXT, 'CEFR level', VALUE_DEFAULT, 'A1'),
            'currentline' => new \external_value(PARAM_RAW, 'Current character line', VALUE_DEFAULT, ''),
            'learnerreply' => new \external_value(PARAM_RAW, 'Learner reply transcript', VALUE_DEFAULT, ''),
            'history' => new \external_value(PARAM_RAW, 'Conversation history', VALUE_DEFAULT, ''),
            'personality' => new \external_value(PARAM_RAW, 'Character personality guidance', VALUE_DEFAULT, ''),
            'difficulty' => new \external_value(PARAM_TEXT, 'Conversation difficulty', VALUE_DEFAULT, ''),
            'targetpattern' => new \external_value(PARAM_RAW, 'Target language pattern', VALUE_DEFAULT, ''),
            'maxretries' => new \external_value(PARAM_INT, 'Maximum retry count', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Generate the next role-character line.
     *
     * @return array
     */
    public static function execute($cmid, $character = 'Waiter', $role = 'Cafe waiter', $scenario = '', $cefrlevel = 'A1',
            $currentline = '', $learnerreply = '', $history = '', $personality = '', $difficulty = '',
            $targetpattern = '', $maxretries = 1) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'character' => $character,
            'role' => $role,
            'scenario' => $scenario,
            'cefrlevel' => $cefrlevel,
            'currentline' => $currentline,
            'learnerreply' => $learnerreply,
            'history' => $history,
            'personality' => $personality,
            'difficulty' => $difficulty,
            'targetpattern' => $targetpattern,
            'maxretries' => $maxretries,
        ]);

        $cm = get_coursemodule_from_id('flwvrroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/flwvrroom:submit', $context);

        $baseurl = trim((string) ($flwvrroom->speakingscoringurl ?? '')) ?: 'http://127.0.0.1:8000';
        $payload = [
            'userid' => (int) $USER->id,
            'courseid' => (int) $course->id,
            'cmid' => (int) $cm->id,
            'character' => $params['character'],
            'role' => $params['role'],
            'scenario' => $params['scenario'],
            'cefr_level' => $params['cefrlevel'],
            'current_line' => $params['currentline'],
            'learner_reply' => $params['learnerreply'],
            'history' => $params['history'],
            'personality' => trim((string)($params['personality'] ?: ($flwvrroom->roleaipersonality ?? ''))),
            'difficulty' => trim((string)($params['difficulty'] ?: ($flwvrroom->roleaidifficulty ?? 'friendly'))),
            'target_pattern' => trim((string)($params['targetpattern'] ?: ($flwvrroom->roleaitargetpattern ?? ''))),
            'max_retries' => max(0, min(5, (int)$params['maxretries'])),
        ];

        try {
            $response = self::post_json(rtrim($baseurl, '/') . '/role/waiter', $payload);
            $line = trim((string) ($response['line'] ?? $response['next_line'] ?? ''));
            if ($line !== '') {
                return [
                    'status' => true,
                    'line' => clean_param($line, PARAM_TEXT),
                    'rawjson' => json_encode($response),
                ];
            }
        } catch (\Throwable $exc) {
            return [
                'status' => false,
                'line' => '',
                'rawjson' => $exc->getMessage(),
            ];
        }

        return [
            'status' => false,
            'line' => '',
            'rawjson' => '',
        ];
    }

    /**
     * Return structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Generation status'),
            'line' => new \external_value(PARAM_TEXT, 'Next character line'),
            'rawjson' => new \external_value(PARAM_RAW, 'Raw JSON or error'),
        ]);
    }

    /**
     * Post JSON to the local AI service.
     *
     * @param string $url
     * @param array $payload
     * @return array
     */
    private static function post_json($url, array $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpcode >= 400) {
            throw new \moodle_exception('aiwaiterfailed', 'flwvrroom', '', $error ?: ('HTTP ' . $httpcode . ': ' . $response));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \moodle_exception('aiwaiterfailed', 'flwvrroom', '', 'Invalid JSON response');
        }

        return $data;
    }
}

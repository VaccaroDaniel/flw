<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwvrroom\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/flwvrroom/lib.php');

/**
 * External service for saving learner attempts.
 */
class submit_attempt extends \external_api {
    /**
     * Parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
            'score' => new \external_value(PARAM_INT, 'Attempt score'),
            'completedobjects' => new \external_value(PARAM_TEXT, 'Comma-separated completed object keys', VALUE_DEFAULT, ''),
            'kpcodes' => new \external_value(PARAM_TEXT, 'Comma-separated FLW knowledge point codes', VALUE_DEFAULT, ''),
            'speakingtext' => new \external_value(PARAM_TEXT, 'Learner speaking transcript', VALUE_DEFAULT, ''),
            'aifeedback' => new \external_value(PARAM_TEXT, 'Speaking feedback text', VALUE_DEFAULT, ''),
            'hotspotsjson' => new \external_value(PARAM_RAW, 'Structured hotspot evidence JSON', VALUE_DEFAULT, ''),
            'roleturnsjson' => new \external_value(PARAM_RAW, 'Structured role-turn evidence JSON', VALUE_DEFAULT, ''),
            'speakingjson' => new \external_value(PARAM_RAW, 'Structured speaking evidence JSON', VALUE_DEFAULT, ''),
            'taskcomplete' => new \external_value(PARAM_BOOL, 'Whether the mission task is complete', VALUE_DEFAULT, false),
            'durationseconds' => new \external_value(PARAM_INT, 'Attempt duration in seconds', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save the learner attempt and update grade/completion.
     *
     * @param int $cmid
     * @param int $score
     * @param string $completedobjects
     * @param string $kpcodes
     * @param string $speakingtext
     * @param string $aifeedback
     * @param string $hotspotsjson
     * @param string $roleturnsjson
     * @param string $speakingjson
     * @param bool $taskcomplete
     * @param int $durationseconds
     * @return array
     */
    public static function execute($cmid, $score, $completedobjects = '', $kpcodes = '', $speakingtext = '', $aifeedback = '',
            $hotspotsjson = '', $roleturnsjson = '', $speakingjson = '', $taskcomplete = false, $durationseconds = 0) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'score' => $score,
            'completedobjects' => $completedobjects,
            'kpcodes' => $kpcodes,
            'speakingtext' => $speakingtext,
            'aifeedback' => $aifeedback,
            'hotspotsjson' => $hotspotsjson,
            'roleturnsjson' => $roleturnsjson,
            'speakingjson' => $speakingjson,
            'taskcomplete' => $taskcomplete,
            'durationseconds' => $durationseconds,
        ]);

        $cm = get_coursemodule_from_id('flwvrroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/flwvrroom:submit', $context);

        $score = max(0, min((int) $params['score'], (int) $flwvrroom->grade));

        $record = (object) [
            'flwvrroomid' => $flwvrroom->id,
            'userid' => $USER->id,
            'score' => $score,
            'completedobjects' => clean_param($params['completedobjects'], PARAM_TEXT),
            'kpcodes' => clean_param($params['kpcodes'], PARAM_TEXT),
            'speakingtext' => clean_param($params['speakingtext'], PARAM_TEXT),
            'aifeedback' => clean_param($params['aifeedback'], PARAM_TEXT),
            'hotspotsjson' => self::clean_json_payload($params['hotspotsjson']),
            'roleturnsjson' => self::clean_json_payload($params['roleturnsjson']),
            'speakingjson' => self::clean_json_payload($params['speakingjson']),
            'taskcomplete' => $params['taskcomplete'] ? 1 : 0,
            'durationseconds' => max(0, (int) $params['durationseconds']),
            'timecreated' => time(),
        ];
        $attemptid = $DB->insert_record('flwvrroom_attempts', $record);

        $event = \mod_flwvrroom\event\attempt_submitted::create([
            'objectid' => $attemptid,
            'context' => $context,
            'courseid' => $course->id,
            'userid' => $USER->id,
            'relateduserid' => $USER->id,
            'other' => [
                'attemptid' => $attemptid,
                'cmid' => (int) $cm->id,
                'courseid' => (int) $course->id,
                'userid' => (int) $USER->id,
                'score' => $score,
                'maxscore' => (int) $flwvrroom->grade,
                'kpcodes' => $record->kpcodes,
                'xrmode' => (string) ($flwvrroom->roommode ?? 'panorama'),
                'scenario' => (string) ($flwvrroom->scenario ?? ''),
            ],
        ]);
        $event->trigger();

        flwvrroom_grade_item_update($flwvrroom, (object) [
            'userid' => $USER->id,
            'rawgrade' => $score,
        ]);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, $record->taskcomplete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE, $USER->id);
        }

        return [
            'status' => true,
            'attemptid' => $attemptid,
            'score' => $score,
            'passed' => !empty($record->taskcomplete),
        ];
    }

    /**
     * Return structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Save status'),
            'attemptid' => new \external_value(PARAM_INT, 'Attempt id'),
            'score' => new \external_value(PARAM_INT, 'Saved score'),
            'passed' => new \external_value(PARAM_BOOL, 'Whether the learner passed'),
        ]);
    }

    /**
     * Keep structured attempt JSON valid and free from raw audio blobs.
     *
     * @param string $json
     * @return string
     */
    private static function clean_json_payload($json) {
        $json = trim((string) $json);
        if ($json === '') {
            return '';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }

        self::strip_audio_payloads($data);
        return json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Remove raw audio keys before storing evidence JSON.
     *
     * @param array $data
     */
    private static function strip_audio_payloads(array &$data) {
        foreach (['audio', 'audiofile', 'audiobase64', 'blob'] as $key) {
            unset($data[$key]);
        }

        foreach ($data as &$value) {
            if (is_array($value)) {
                self::strip_audio_payloads($value);
            }
        }
    }
}

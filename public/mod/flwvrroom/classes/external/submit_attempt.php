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
     * @param bool $taskcomplete
     * @param int $durationseconds
     * @return array
     */
    public static function execute($cmid, $score, $completedobjects = '', $kpcodes = '', $speakingtext = '', $aifeedback = '', $taskcomplete = false, $durationseconds = 0) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'score' => $score,
            'completedobjects' => $completedobjects,
            'kpcodes' => $kpcodes,
            'speakingtext' => $speakingtext,
            'aifeedback' => $aifeedback,
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
            'taskcomplete' => $params['taskcomplete'] ? 1 : 0,
            'durationseconds' => max(0, (int) $params['durationseconds']),
            'timecreated' => time(),
        ];
        $attemptid = $DB->insert_record('flwvrroom_attempts', $record);

        flwvrroom_grade_item_update($flwvrroom, (object) [
            'userid' => $USER->id,
            'rawgrade' => $score,
        ]);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, $score >= (int) $flwvrroom->passinggrade ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE, $USER->id);
        }

        return [
            'status' => true,
            'attemptid' => $attemptid,
            'score' => $score,
            'passed' => $score >= (int) $flwvrroom->passinggrade,
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
}

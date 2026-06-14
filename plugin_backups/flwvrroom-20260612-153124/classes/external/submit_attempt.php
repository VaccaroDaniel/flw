<?php
// AJAX external function for saving FLW VR Room attempts.

namespace mod_flwvrroom\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/flwvrroom/lib.php');

class submit_attempt extends \external_api {
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
            'score' => new \external_value(PARAM_INT, 'Score'),
            'maxscore' => new \external_value(PARAM_INT, 'Maximum score'),
            'completedobjects' => new \external_value(PARAM_TEXT, 'Comma-separated completed objects', VALUE_DEFAULT, ''),
            'completedquiz' => new \external_value(PARAM_INT, 'Quiz completed', VALUE_DEFAULT, 0),
            'completed' => new \external_value(PARAM_INT, 'Activity completed', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute($cmid, $score, $maxscore, $completedobjects = '', $completedquiz = 0, $completed = 0) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'score' => $score,
            'maxscore' => $maxscore,
            'completedobjects' => $completedobjects,
            'completedquiz' => $completedquiz,
            'completed' => $completed,
        ]);

        $cm = get_coursemodule_from_id('flwvrroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/flwvrroom:submit', $context);

        $now = time();
        $score = max(0, min((int)$params['score'], (int)$params['maxscore']));

        $attempt = new \stdClass();
        $attempt->flwvrroomid = $flwvrroom->id;
        $attempt->userid = $USER->id;
        $attempt->score = $score;
        $attempt->maxscore = (int)$params['maxscore'];
        $attempt->completedobjects = $params['completedobjects'];
        $attempt->completedquiz = !empty($params['completedquiz']) ? 1 : 0;
        $attempt->completed = !empty($params['completed']) ? 1 : 0;
        $attempt->timestarted = $now;
        $attempt->timefinished = $attempt->completed ? $now : 0;
        $attempt->timemodified = $now;

        $attempt->id = $DB->insert_record('flwvrroom_attempts', $attempt);

        flwvrroom_update_grades($flwvrroom, $USER->id);

        if ($attempt->completed) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            }
        }

        return [
            'status' => true,
            'attemptid' => $attempt->id,
            'score' => $score,
            'completed' => $attempt->completed,
        ];
    }

    public static function execute_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Save status'),
            'attemptid' => new \external_value(PARAM_INT, 'Attempt id'),
            'score' => new \external_value(PARAM_INT, 'Saved score'),
            'completed' => new \external_value(PARAM_INT, 'Completion status'),
        ]);
    }
}

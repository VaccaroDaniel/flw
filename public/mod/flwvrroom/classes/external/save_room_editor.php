<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwvrroom\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/flwvrroom/lib.php');

/**
 * External service for saving teacher room-editor changes.
 */
class save_room_editor extends \external_api {
    /**
     * Parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
            'kpcodes' => new \external_value(PARAM_RAW, 'Activity-level KP codes', VALUE_DEFAULT, ''),
            'customhotspots' => new \external_value(PARAM_RAW, 'Custom hotspot lines', VALUE_DEFAULT, ''),
            'custommissiontitle' => new \external_value(PARAM_TEXT, 'Mission title', VALUE_DEFAULT, ''),
            'custommissiontext' => new \external_value(PARAM_RAW, 'Mission text', VALUE_DEFAULT, ''),
            'customquizquestion' => new \external_value(PARAM_RAW, 'Quiz question', VALUE_DEFAULT, ''),
            'customanswers' => new \external_value(PARAM_RAW, 'Custom answers', VALUE_DEFAULT, ''),
            'rolecharacterposition' => new \external_value(PARAM_TEXT, 'Role character 3D position', VALUE_DEFAULT, ''),
            'rolecharacterline' => new \external_value(PARAM_RAW, 'Role character first line', VALUE_DEFAULT, ''),
            'roleexpectedanswer' => new \external_value(PARAM_RAW, 'Expected learner answer', VALUE_DEFAULT, ''),
            'rolekpcodes' => new \external_value(PARAM_RAW, 'Role KP codes', VALUE_DEFAULT, ''),
            'rolescore' => new \external_value(PARAM_INT, 'Role score', VALUE_DEFAULT, 20),
            'roleturns' => new \external_value(PARAM_RAW, 'Role-play turns', VALUE_DEFAULT, ''),
            'roleaienabled' => new \external_value(PARAM_BOOL, 'Use AI role character', VALUE_DEFAULT, false),
            'roleaiturns' => new \external_value(PARAM_INT, 'AI role turn count', VALUE_DEFAULT, 3),
            'roleaipersonality' => new \external_value(PARAM_RAW, 'AI role personality', VALUE_DEFAULT, ''),
            'roleaidifficulty' => new \external_value(PARAM_TEXT, 'AI role difficulty', VALUE_DEFAULT, 'friendly'),
            'roleaitargetpattern' => new \external_value(PARAM_RAW, 'AI role target language pattern', VALUE_DEFAULT, ''),
            'roleaimaxretries' => new \external_value(PARAM_INT, 'AI role max retries', VALUE_DEFAULT, 1),
            'completionrequirehotspots' => new \external_value(PARAM_BOOL, 'Require hotspots for completion', VALUE_DEFAULT, true),
            'completionrequirespeaking' => new \external_value(PARAM_BOOL, 'Require speaking for completion', VALUE_DEFAULT, true),
            'completionrequirerole' => new \external_value(PARAM_BOOL, 'Require role play for completion', VALUE_DEFAULT, false),
            'completionminscore' => new \external_value(PARAM_INT, 'Minimum score for completion', VALUE_DEFAULT, 70),
        ]);
    }

    /**
     * Save teacher room editor changes.
     *
     * @return array
     */
    public static function execute(
        $cmid,
        $kpcodes = '',
        $customhotspots = '',
        $custommissiontitle = '',
        $custommissiontext = '',
        $customquizquestion = '',
        $customanswers = '',
        $rolecharacterposition = '',
        $rolecharacterline = '',
        $roleexpectedanswer = '',
        $rolekpcodes = '',
        $rolescore = 20,
        $roleturns = '',
        $roleaienabled = false,
        $roleaiturns = 3,
        $roleaipersonality = '',
        $roleaidifficulty = 'friendly',
        $roleaitargetpattern = '',
        $roleaimaxretries = 1,
        $completionrequirehotspots = true,
        $completionrequirespeaking = true,
        $completionrequirerole = false,
        $completionminscore = 70
    ) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'kpcodes' => $kpcodes,
            'customhotspots' => $customhotspots,
            'custommissiontitle' => $custommissiontitle,
            'custommissiontext' => $custommissiontext,
            'customquizquestion' => $customquizquestion,
            'customanswers' => $customanswers,
            'rolecharacterposition' => $rolecharacterposition,
            'rolecharacterline' => $rolecharacterline,
            'roleexpectedanswer' => $roleexpectedanswer,
            'rolekpcodes' => $rolekpcodes,
            'rolescore' => $rolescore,
            'roleturns' => $roleturns,
            'roleaienabled' => $roleaienabled,
            'roleaiturns' => $roleaiturns,
            'roleaipersonality' => $roleaipersonality,
            'roleaidifficulty' => $roleaidifficulty,
            'roleaitargetpattern' => $roleaitargetpattern,
            'roleaimaxretries' => $roleaimaxretries,
            'completionrequirehotspots' => $completionrequirehotspots,
            'completionrequirespeaking' => $completionrequirespeaking,
            'completionrequirerole' => $completionrequirerole,
            'completionminscore' => $completionminscore,
        ]);

        $cm = get_coursemodule_from_id('flwvrroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $flwvrroom->customsceneenabled = 1;
        $flwvrroom->kpcodes = self::clean_code_list($params['kpcodes']);
        $flwvrroom->customhotspots = self::clean_multiline($params['customhotspots']);
        $flwvrroom->custommissiontitle = clean_param($params['custommissiontitle'], PARAM_TEXT);
        $flwvrroom->custommissiontext = self::clean_multiline($params['custommissiontext']);
        $flwvrroom->customquizquestion = self::clean_multiline($params['customquizquestion']);
        $flwvrroom->customanswers = self::clean_multiline($params['customanswers']);
        $flwvrroom->rolecharacterposition = clean_param($params['rolecharacterposition'], PARAM_TEXT);
        $flwvrroom->rolecharacterline = self::clean_multiline($params['rolecharacterline']);
        $flwvrroom->roleexpectedanswer = self::clean_multiline($params['roleexpectedanswer']);
        $flwvrroom->rolekpcodes = self::clean_multiline($params['rolekpcodes']);
        $flwvrroom->rolescore = max(0, (int) $params['rolescore']);
        $flwvrroom->roleturns = self::clean_multiline($params['roleturns']);
        $flwvrroom->roleaienabled = !empty($params['roleaienabled']) ? 1 : 0;
        $flwvrroom->roleaiturns = max(1, min(10, (int) $params['roleaiturns']));
        $flwvrroom->roleaipersonality = self::clean_multiline($params['roleaipersonality']);
        $difficulty = clean_param($params['roleaidifficulty'], PARAM_ALPHA);
        $flwvrroom->roleaidifficulty = in_array($difficulty, ['friendly', 'standard', 'challenge'], true) ? $difficulty : 'friendly';
        $flwvrroom->roleaitargetpattern = self::clean_multiline($params['roleaitargetpattern']);
        $flwvrroom->roleaimaxretries = max(0, min(5, (int) $params['roleaimaxretries']));
        $flwvrroom->completionrequirehotspots = !empty($params['completionrequirehotspots']) ? 1 : 0;
        $flwvrroom->completionrequirespeaking = !empty($params['completionrequirespeaking']) ? 1 : 0;
        $flwvrroom->completionrequirerole = !empty($params['completionrequirerole']) ? 1 : 0;
        $flwvrroom->completionminscore = max(0, min((int)($flwvrroom->grade ?? 100), (int) $params['completionminscore']));
        $flwvrroom->timemodified = time();

        $DB->update_record('flwvrroom', $flwvrroom);
        flwvrroom_grade_item_update($flwvrroom);

        return [
            'status' => true,
            'message' => get_string('roomeditorsaved', 'flwvrroom'),
            'timemodified' => $flwvrroom->timemodified,
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
            'message' => new \external_value(PARAM_TEXT, 'Status message'),
            'timemodified' => new \external_value(PARAM_INT, 'Modified time'),
        ]);
    }

    /**
     * Clean multiline text while preserving line breaks.
     *
     * @param string $value
     * @return string
     */
    private static function clean_multiline($value) {
        $lines = preg_split('/\R/', (string) $value);
        $lines = array_map(static function($line) {
            return clean_param($line, PARAM_TEXT);
        }, $lines);

        return trim(implode("\n", $lines));
    }

    /**
     * Clean KP code lists while preserving line breaks and commas.
     *
     * @param string $value
     * @return string
     */
    private static function clean_code_list($value) {
        $parts = preg_split('/[\r\n,]+/', (string) $value);
        $parts = array_map(static function($part) {
            return clean_param(trim($part), PARAM_TEXT);
        }, $parts);
        $parts = array_values(array_filter($parts, static function($part) {
            return $part !== '';
        }));

        return implode("\n", $parts);
    }
}

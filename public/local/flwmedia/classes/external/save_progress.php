<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_flwmedia\manager;

/**
 * External function for saving media progress.
 *
 * @package    local_flwmedia
 */
class save_progress extends external_api {
    /**
     * Define request parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'mode' => new external_value(PARAM_ALPHA, 'Practice mode'),
            'courseid' => new external_value(PARAM_INT, 'Optional legacy course id', VALUE_DEFAULT, 0),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code', VALUE_DEFAULT, ''),
            'percentdone' => new external_value(PARAM_INT, 'Percent done', VALUE_DEFAULT, 0),
            'secondsdone' => new external_value(PARAM_INT, 'Seconds done', VALUE_DEFAULT, 0),
            'completed' => new external_value(PARAM_BOOL, 'Completion flag', VALUE_DEFAULT, false),
            'score' => new external_value(PARAM_FLOAT, 'Optional score', VALUE_DEFAULT, null),
            'attemptjson' => new external_value(PARAM_RAW, 'Attempt metadata JSON', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute request.
     *
     * @param int $itemid Item id.
     * @param string $mode Practice mode.
     * @param int $courseid Optional legacy course id.
     * @param string $language Language code.
     * @param int $percentdone Percent complete.
     * @param int $secondsdone Seconds complete.
     * @param bool $completed Completion flag.
     * @param float|null $score Score.
     * @param string $attemptjson Attempt JSON.
     * @return array
     */
    public static function execute(
        int $itemid,
        string $mode,
        int $courseid = 0,
        string $language = '',
        int $percentdone = 0,
        int $secondsdone = 0,
        bool $completed = false,
        ?float $score = null,
        string $attemptjson = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'itemid' => $itemid,
            'mode' => $mode,
            'courseid' => $courseid,
            'language' => $language,
            'percentdone' => $percentdone,
            'secondsdone' => $secondsdone,
            'completed' => $completed,
            'score' => $score,
            'attemptjson' => $attemptjson,
        ]);

        $context = manager::validate_practice_view();
        self::validate_context($context);

        return manager::save_progress($params);
    }

    /**
     * Define returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'progressid' => new external_value(PARAM_INT, 'Progress id'),
            'completed' => new external_value(PARAM_INT, 'Completion flag'),
        ]);
    }
}

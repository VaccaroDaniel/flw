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
 * Base class for attempt-saving web services.
 *
 * @package    local_flwmedia
 */
abstract class save_attempt_base extends external_api {
    /** @var string Practice mode handled by the concrete endpoint. */
    protected const MODE = '';

    /**
     * Define request parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'courseid' => new external_value(PARAM_INT, 'Optional legacy course id', VALUE_DEFAULT, 0),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code', VALUE_DEFAULT, ''),
            'response' => new external_value(PARAM_RAW, 'Learner response', VALUE_DEFAULT, ''),
            'transcript' => new external_value(PARAM_RAW, 'Transcript', VALUE_DEFAULT, ''),
            'score' => new external_value(PARAM_FLOAT, 'Optional score', VALUE_DEFAULT, null),
            'feedback' => new external_value(PARAM_RAW, 'Feedback text or JSON', VALUE_DEFAULT, ''),
            'audiofileurl' => new external_value(PARAM_RAW, 'Optional external audio file URL', VALUE_DEFAULT, ''),
            'attemptjson' => new external_value(PARAM_RAW, 'Attempt metadata JSON', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute request.
     *
     * @param int $courseid Course id.
     * @param int $itemid Item id.
     * @param string $response Response.
     * @param string $transcript Transcript.
     * @param float|null $score Score.
     * @param string $feedback Feedback.
     * @param string $audiofileurl Audio URL.
     * @param string $attemptjson Attempt JSON.
     * @return array
     */
    public static function execute(
        int $itemid,
        int $courseid = 0,
        string $language = '',
        string $response = '',
        string $transcript = '',
        ?float $score = null,
        string $feedback = '',
        string $audiofileurl = '',
        string $attemptjson = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'itemid' => $itemid,
            'courseid' => $courseid,
            'language' => $language,
            'response' => $response,
            'transcript' => $transcript,
            'score' => $score,
            'feedback' => $feedback,
            'audiofileurl' => $audiofileurl,
            'attemptjson' => $attemptjson,
        ]);

        $context = manager::validate_practice_view();
        self::validate_context($context);

        return manager::save_attempt($params, static::MODE);
    }

    /**
     * Define returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'progressid' => new external_value(PARAM_INT, 'Progress id'),
            'score' => new external_value(PARAM_FLOAT, 'Score'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback'),
        ]);
    }
}

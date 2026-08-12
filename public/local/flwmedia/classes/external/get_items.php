<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_flwmedia\manager;

/**
 * External function for loading FLW media items.
 *
 * @package    local_flwmedia
 */
class get_items extends external_api {
    /**
     * Define request parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code', VALUE_DEFAULT, 'en'),
            'courseid' => new external_value(PARAM_INT, 'Optional legacy course id', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'FLW unit code', VALUE_DEFAULT, ''),
            'mode' => new external_value(PARAM_ALPHA, 'Practice mode', VALUE_DEFAULT, 'watch'),
            'category' => new external_value(PARAM_ALPHANUMEXT, 'Category key or all', VALUE_DEFAULT, 'all'),
            'search' => new external_value(PARAM_RAW, 'Search query', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 12),
        ]);
    }

    /**
     * Execute request.
     *
     * @param string $language Language code.
     * @param int $courseid Optional legacy course id.
     * @param string $unitcode Unit code.
     * @param string $mode Practice mode.
     * @param string $category Category.
     * @param string $search Search text.
     * @param int $page Page.
     * @param int $perpage Per page.
     * @return array
     */
    public static function execute(
        string $language = 'en',
        int $courseid = 0,
        string $unitcode = '',
        string $mode = 'watch',
        string $category = 'all',
        string $search = '',
        int $page = 1,
        int $perpage = 12
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'language' => $language,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'mode' => $mode,
            'category' => $category,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = manager::validate_practice_view();
        self::validate_context($context);

        return manager::get_items($params);
    }

    /**
     * Define returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Item id'),
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'unitcode' => new external_value(PARAM_RAW, 'Unit code'),
                'lessoncode' => new external_value(PARAM_RAW, 'Lesson code'),
                'mode' => new external_value(PARAM_ALPHA, 'Mode'),
                'category' => new external_value(PARAM_RAW, 'Category'),
                'title' => new external_value(PARAM_RAW, 'Title'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'mediaurl' => new external_value(PARAM_RAW, 'Media URL'),
                'posterurl' => new external_value(PARAM_RAW, 'Poster URL'),
                'subtitleurl' => new external_value(PARAM_RAW, 'Subtitle URL'),
                'transcript' => new external_value(PARAM_RAW, get_string('transcript', 'local_flwmedia')),
                'readtext' => new external_value(PARAM_RAW, 'Read text'),
                'expectedtext' => new external_value(PARAM_RAW, 'Expected text'),
                'duration' => new external_value(PARAM_INT, 'Duration'),
                'lang' => new external_value(PARAM_RAW, 'Language'),
                'cefr' => new external_value(PARAM_RAW, 'CEFR level'),
                'kptags' => new external_value(PARAM_RAW, 'Knowledge-point tags'),
                'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                'visible' => new external_value(PARAM_INT, 'Visible flag'),
                'hasmediaurl' => new external_value(PARAM_INT, 'Has media URL'),
                'hasposterurl' => new external_value(PARAM_INT, 'Has poster URL'),
                'hassubtitleurl' => new external_value(PARAM_INT, 'Has subtitle URL'),
                'hastranscript' => new external_value(PARAM_INT, 'Has transcript'),
                'hasreadtext' => new external_value(PARAM_INT, 'Has read text'),
                'hasexpectedtext' => new external_value(PARAM_INT, 'Has expected text'),
            ])),
            'categories' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Category key'),
                'label' => new external_value(PARAM_RAW, 'Category label'),
                'mode' => new external_value(PARAM_RAW, 'Optional mode'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matching records'),
            'page' => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Records per page'),
            'pages' => new external_value(PARAM_INT, 'Total pages'),
        ]);
    }
}

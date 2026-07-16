<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_flwmedia_get_items' => [
        'classname' => 'local_flwmedia\external\get_items',
        'methodname' => 'execute',
        'description' => 'Get paginated FLW media practice items.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwmedia:view',
    ],
    'local_flwmedia_save_progress' => [
        'classname' => 'local_flwmedia\external\save_progress',
        'methodname' => 'execute',
        'description' => 'Save FLW media progress.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/flwmedia:view',
    ],
    'local_flwmedia_save_speaking_attempt' => [
        'classname' => 'local_flwmedia\external\save_speaking_attempt',
        'methodname' => 'execute',
        'description' => 'Save FLW speaking attempt metadata.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/flwmedia:view',
    ],
    'local_flwmedia_save_reading_attempt' => [
        'classname' => 'local_flwmedia\external\save_reading_attempt',
        'methodname' => 'execute',
        'description' => 'Save FLW reading attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/flwmedia:view',
    ],
    'local_flwmedia_save_dictation_attempt' => [
        'classname' => 'local_flwmedia\external\save_dictation_attempt',
        'methodname' => 'execute',
        'description' => 'Save FLW dictation attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/flwmedia:view',
    ],
];

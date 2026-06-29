<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_flwvrroom_submit_attempt' => [
        'classname' => 'mod_flwvrroom\external\submit_attempt',
        'methodname' => 'execute',
        'description' => 'Save an FLW VR Room attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/flwvrroom:submit',
    ],
    'mod_flwvrroom_score_speaking' => [
        'classname' => 'mod_flwvrroom\external\score_speaking',
        'methodname' => 'execute',
        'description' => 'Score an FLW VR Room speaking recording with the local FLW AI service.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/flwvrroom:submit',
    ],
    'mod_flwvrroom_save_room_editor' => [
        'classname' => 'mod_flwvrroom\external\save_room_editor',
        'methodname' => 'execute',
        'description' => 'Save teacher edits for an FLW VR Room.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'mod_flwvrroom_role_waiter' => [
        'classname' => 'mod_flwvrroom\external\role_waiter',
        'methodname' => 'execute',
        'description' => 'Generate the next AI role-character line with the local FLW AI service.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/flwvrroom:submit',
    ],
];

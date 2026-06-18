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
];

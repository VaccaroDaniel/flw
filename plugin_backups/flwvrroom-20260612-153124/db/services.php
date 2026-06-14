<?php
// Web service functions for AJAX calls.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_flwvrroom_submit_attempt' => [
        'classname' => 'mod_flwvrroom\\external\\submit_attempt',
        'methodname' => 'execute',
        'description' => 'Save a learner attempt for an FLW VR Room activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/flwvrroom:submit',
    ],
];

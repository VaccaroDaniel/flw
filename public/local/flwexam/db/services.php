<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_flwexam_get_my_history' => [
        'classname' => 'local_flwexam_external',
        'methodname' => 'get_my_history',
        'classpath' => 'local/flwexam/externallib.php',
        'description' => 'Get the current learner exam history.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwexam:viewown',
    ],
    'local_flwexam_get_result' => [
        'classname' => 'local_flwexam_external',
        'methodname' => 'get_result',
        'classpath' => 'local/flwexam/externallib.php',
        'description' => 'Get one FLW Exam result when permitted.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_flwexam_verify_certificate' => [
        'classname' => 'local_flwexam_external',
        'methodname' => 'verify_certificate',
        'classpath' => 'local/flwexam/externallib.php',
        'description' => 'Verify a public FLW certificate code.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_flwexam_submit_result' => [
        'classname' => 'local_flwexam_external',
        'methodname' => 'submit_result',
        'classpath' => 'local/flwexam/externallib.php',
        'description' => 'Submit an official FLW Exam result.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'local/flwexam:submitresult',
    ],
];

$services = [
    'FLW Exam services' => [
        'functions' => [
            'local_flwexam_get_my_history',
            'local_flwexam_get_result',
            'local_flwexam_verify_certificate',
            'local_flwexam_submit_result',
        ],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'flwexam',
    ],
];

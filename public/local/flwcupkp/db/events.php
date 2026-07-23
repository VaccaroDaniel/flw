<?php
// Conservative event observers for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_flwcupkp\observer::course_module_completion_updated',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_flwcupkp\observer::quiz_attempt_submitted',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_graded',
        'callback' => '\local_flwcupkp\observer::quiz_attempt_graded',
    ],
];

<?php
// Event observers for local_flwplacement.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_flwplacement\observer::quiz_attempt_submitted',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_graded',
        'callback' => '\local_flwplacement\observer::quiz_attempt_graded',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_manual_grading_completed',
        'callback' => '\local_flwplacement\observer::quiz_attempt_manual_grading_completed',
    ],
];

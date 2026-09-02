<?php
// Lightweight source capture observers for local_flwhistory.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_started',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_graded',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_regraded',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_manual_grading_completed',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_reopened',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_deleted',
        'callback' => '\local_flwhistory\observer::quiz_attempt_event',
    ],
    [
        'eventname' => '\mod_scorm\event\scoreraw_submitted',
        'callback' => '\local_flwhistory\observer::scorm_attempt_event',
    ],
    [
        'eventname' => '\mod_scorm\event\status_submitted',
        'callback' => '\local_flwhistory\observer::scorm_attempt_event',
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_flwhistory\observer::course_module_completion_updated',
    ],
    [
        'eventname' => '\core\event\course_completion_updated',
        'callback' => '\local_flwhistory\observer::course_completion_updated',
    ],
    [
        'eventname' => '\mod_flwvrroom\event\attempt_submitted',
        'callback' => '\local_flwhistory\observer::flwvrroom_attempt_submitted',
    ],
    [
        'eventname' => '\core\event\user_graded',
        'callback' => '\local_flwhistory\observer::user_graded',
    ],
    [
        'eventname' => '\core\event\grade_deleted',
        'callback' => '\local_flwhistory\observer::grade_deleted',
    ],
];

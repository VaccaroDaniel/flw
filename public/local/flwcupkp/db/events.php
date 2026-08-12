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
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\local_flwcupkp\observer::assign_assessable_submitted',
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\local_flwcupkp\observer::assign_submission_graded',
    ],
    [
        'eventname' => '\mod_h5pactivity\event\statement_received',
        'callback' => '\local_flwcupkp\observer::h5p_statement_received',
    ],
    [
        'eventname' => '\mod_scorm\event\status_submitted',
        'callback' => '\local_flwcupkp\observer::scorm_status_submitted',
    ],
    [
        'eventname' => '\mod_scorm\event\scoreraw_submitted',
        'callback' => '\local_flwcupkp\observer::scorm_scoreraw_submitted',
    ],
];

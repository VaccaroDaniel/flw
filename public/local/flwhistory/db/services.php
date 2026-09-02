<?php
// Web service definitions for local_flwhistory.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_flwhistory_get_present_summary' => [
        'classname' => 'local_flwhistory\external\api',
        'methodname' => 'get_present_summary',
        'classpath' => '',
        'description' => 'Get the trusted Program 2 present summary for the current or authorized learner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwhistory:viewown',
    ],
    'local_flwhistory_get_learning_history' => [
        'classname' => 'local_flwhistory\external\api',
        'methodname' => 'get_learning_history',
        'classpath' => '',
        'description' => 'Query normalized learning history events for the current or authorized learner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwhistory:viewown',
    ],
    'local_flwhistory_get_attempt_history' => [
        'classname' => 'local_flwhistory\external\api',
        'methodname' => 'get_attempt_history',
        'classpath' => '',
        'description' => 'Query normalized attempt history for the current or authorized learner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwhistory:viewown',
    ],
    'local_flwhistory_get_grade_history' => [
        'classname' => 'local_flwhistory\external\api',
        'methodname' => 'get_grade_history',
        'classpath' => '',
        'description' => 'Query grade-version history for the current or authorized learner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwhistory:viewown',
    ],
    'local_flwhistory_get_recent_activity' => [
        'classname' => 'local_flwhistory\external\api',
        'methodname' => 'get_recent_activity',
        'classpath' => '',
        'description' => 'Query recent normalized activity for the current or authorized learner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwhistory:viewown',
    ],
    'local_flwhistory_get_learning_journey' => [
        'classname' => 'local_flwhistory\external\api',
        'methodname' => 'get_learning_journey',
        'classpath' => '',
        'description' => 'Get the non-adaptive Program 2 learning journey for the current or authorized learner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/flwhistory:viewown',
    ],
];

<?php
// Web service definitions for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_flwcupkp_get_frameworks' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'get_frameworks',
        'classpath' => '',
        'description' => 'List C-UP-KP frameworks.',
        'type' => 'read',
        'capabilities' => 'local/flwcupkp:viewreports',
    ],
    'local_flwcupkp_import_package' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'import_package',
        'classpath' => '',
        'description' => 'Validate and import a C-UP-KP JSON package.',
        'type' => 'write',
        'capabilities' => 'local/flwcupkp:import',
    ],
    'local_flwcupkp_record_evidence' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'record_evidence',
        'classpath' => '',
        'description' => 'Record normalized learner evidence.',
        'type' => 'write',
        'capabilities' => 'local/flwcupkp:override',
    ],
    'local_flwcupkp_get_learner_states' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'get_learner_states',
        'classpath' => '',
        'description' => 'Get learner C-UP-KP states.',
        'type' => 'read',
        'capabilities' => 'local/flwcupkp:viewlearnerpath',
    ],
    'local_flwcupkp_get_recommendations' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'get_recommendations',
        'classpath' => '',
        'description' => 'Get learner learning-path recommendations.',
        'type' => 'read',
        'capabilities' => 'local/flwcupkp:viewlearnerpath',
    ],
    'local_flwcupkp_get_coverage_report' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'get_coverage_report',
        'classpath' => '',
        'description' => 'Get curriculum coverage report.',
        'type' => 'read',
        'capabilities' => 'local/flwcupkp:viewreports',
    ],
    'local_flwcupkp_sync_moodle_competencies' => [
        'classname' => 'local_flwcupkp\external\api',
        'methodname' => 'sync_moodle_competencies',
        'classpath' => '',
        'description' => 'Run Moodle competency sync, dry-run by default.',
        'type' => 'write',
        'capabilities' => 'local/flwcupkp:synccompetencies',
    ],
];

$services = [
    'FLW C-UP-KP service' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 1,
        'enabled' => 0,
    ],
];

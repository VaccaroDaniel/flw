<?php
// Capability definitions for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/flwcupkp:manageframeworks' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/flwcupkp:import' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/flwcupkp:viewreports' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
    'local/flwcupkp:viewlearnerpath' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'student' => CAP_ALLOW],
    ],
    'local/flwcupkp:override' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
    'local/flwcupkp:synccompetencies' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
];

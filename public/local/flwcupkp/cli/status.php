<?php
// Report C-UP-KP installation status from CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$tables = [
    'flwcupkp_framework',
    'flwcupkp_comp',
    'flwcupkp_up',
    'flwcupkp_kp',
    'flwcupkp_comp_up',
    'flwcupkp_up_kp',
    'flwcupkp_kp_prereq',
    'flwcupkp_object',
    'flwcupkp_object_map',
    'flwcupkp_rule',
    'flwcupkp_import',
];

$counts = [];
foreach ($tables as $table) {
    $counts[$table] = $DB->count_records($table);
}

$coverage = \local_flwcupkp\local\audit_service::coverage();
$readiness = \local_flwcupkp\local\curriculum_manager::sync_readiness();

echo json_encode([
    'component' => 'local_flwcupkp',
    'release' => get_config('local_flwcupkp', 'release') ?: '0.1.0-alpha',
    'writeenabled' => (bool)get_config('local_flwcupkp', 'enablesyncwrites'),
    'sync_readiness' => $readiness,
    'counts' => $counts,
    'coverage' => $coverage,
], JSON_PRETTY_PRINT) . "\n";

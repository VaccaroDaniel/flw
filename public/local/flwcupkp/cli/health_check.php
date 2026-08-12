<?php
// Production health check for local_flwcupkp.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'strict' => false,
    'help' => false,
], [
    's' => 'strict',
    'h' => 'help',
]);

if ($options['help']) {
    echo "Run production health checks for local_flwcupkp.\n";
    echo "Usage: php local/flwcupkp/cli/health_check.php [--strict]\n";
    echo "--strict exits non-zero when warnings are present.\n";
    exit(0);
}

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
    'flwcupkp_evidence',
    'flwcupkp_state',
    'flwcupkp_recommend',
    'flwcupkp_rule',
    'flwcupkp_import',
    'flwcupkp_calsnapshot',
    'flwcupkp_calproposal',
    'flwcupkp_calrecalc',
    'flwcupkp_audit',
];

$counts = [];
foreach ($tables as $table) {
    $counts[$table] = $DB->count_records($table);
}

$errors = [];
$warnings = [];
$integrity = [
    'object_maps_missing_object' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_object_map} m
      LEFT JOIN {flwcupkp_object} o ON o.id = m.objectid
          WHERE o.id IS NULL"
    ),
    'object_maps_invalid_target_type' => $DB->count_records_select(
        'flwcupkp_object_map',
        "targettype NOT IN ('competency', 'up', 'kp')"
    ),
    'object_maps_missing_competency' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_object_map} m
      LEFT JOIN {flwcupkp_comp} c ON c.id = m.targetid
          WHERE m.targettype = 'competency' AND c.id IS NULL"
    ),
    'object_maps_missing_up' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_object_map} m
      LEFT JOIN {flwcupkp_up} u ON u.id = m.targetid
          WHERE m.targettype = 'up' AND u.id IS NULL"
    ),
    'object_maps_missing_kp' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_object_map} m
      LEFT JOIN {flwcupkp_kp} kp ON kp.id = m.targetid
          WHERE m.targettype = 'kp' AND kp.id IS NULL"
    ),
    'comp_up_missing_endpoint' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_comp_up} m
      LEFT JOIN {flwcupkp_comp} c ON c.id = m.competencyid
      LEFT JOIN {flwcupkp_up} u ON u.id = m.upid
          WHERE c.id IS NULL OR u.id IS NULL"
    ),
    'up_kp_missing_endpoint' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_up_kp} m
      LEFT JOIN {flwcupkp_up} u ON u.id = m.upid
      LEFT JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
          WHERE u.id IS NULL OR kp.id IS NULL"
    ),
    'kp_prereq_missing_endpoint' => local_flwcupkp_health_count_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_kp_prereq} m
      LEFT JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
      LEFT JOIN {flwcupkp_kp} prereq ON prereq.id = m.prereqkpid
          WHERE kp.id IS NULL OR prereq.id IS NULL"
    ),
];

foreach ($integrity as $name => $count) {
    if ($count > 0) {
        $errors[] = $name . ': ' . $count;
    }
}

$readiness = \local_flwcupkp\local\curriculum_manager::sync_readiness();
$writeenabled = (bool)get_config('local_flwcupkp', 'enablesyncwrites');
if ($writeenabled && empty($readiness['readyforwrites'])) {
    $errors[] = 'Moodle competency write mode is enabled but sync readiness is incomplete.';
}
if ($counts['flwcupkp_framework'] === 0) {
    $warnings[] = 'No C-UP-KP framework records are installed.';
}
if (empty($readiness['readyforwrites'])) {
    $warnings[] = 'Moodle competency writes are locked until every framework and competency has a Moodle link.';
}

$coverage = \local_flwcupkp\local\audit_service::coverage();
foreach ($coverage['warnings'] ?? [] as $warning) {
    $warnings[] = $warning;
}

$requiredfiles = [
    'setup_page' => 'local/flwcupkp/setup.php',
    'student_page' => 'local/flwcupkp/student.php',
    'teacher_page' => 'local/flwcupkp/teacher.php',
    'performance_page' => 'local/flwcupkp/performance.php',
    'traceability_page' => 'local/flwcupkp/trace.php',
    'calibration_page' => 'local/flwcupkp/calibration.php',
    'calibration_proposal_page' => 'local/flwcupkp/calibration_proposal.php',
    'u038_student_page' => 'local/flwcupkp/student_u038.php',
    'u038_teacher_page' => 'local/flwcupkp/teacher_u038.php',
    'u038_performance_page' => 'local/flwcupkp/performance_u038.php',
    'quiz_adapter' => 'local/flwcupkp/classes/local/quiz_evidence_adapter.php',
    'activity_adapter' => 'local/flwcupkp/classes/local/activity_evidence_adapter.php',
    'specialized_evidence_adapter' => 'local/flwcupkp/classes/local/specialized_evidence_adapter.php',
    'performance_service' => 'local/flwcupkp/classes/local/performance_service.php',
    'calibration_report' => 'local/flwcupkp/classes/local/calibration_report.php',
    'calibration_proposal' => 'local/flwcupkp/classes/local/calibration_proposal.php',
    'calibration_recalculation_task' => 'local/flwcupkp/classes/task/calibration_recalculation.php',
    'u038_performance_service' => 'local/flwcupkp/classes/local/u038_performance_service.php',
    'unit_setup_service' => 'local/flwcupkp/classes/local/unit_setup_service.php',
    'unit_linker_cli' => 'local/flwcupkp/cli/link_unit.php',
    'guard' => 'local/flwcupkp/classes/local/evidence_guard.php',
    'moodle_writer' => 'local/flwcupkp/classes/local/moodle_competency_writer.php',
    'rollup_engine' => 'local/flwcupkp/classes/local/rollup_engine.php',
];

$files = [];
foreach ($requiredfiles as $name => $relativepath) {
    $exists = file_exists($CFG->dirroot . '/' . $relativepath);
    $files[$name] = $exists;
    if (!$exists) {
        $errors[] = 'Missing required plugin file: ' . $relativepath;
    }
}

$status = empty($errors) ? (empty($warnings) ? 'ok' : 'warn') : 'fail';
$result = [
    'component' => 'local_flwcupkp',
    'release' => get_config('local_flwcupkp', 'release') ?: '0.1.0-alpha',
    'status' => $status,
    'writeenabled' => $writeenabled,
    'sync_readiness' => $readiness,
    'counts' => $counts,
    'integrity' => $integrity,
    'coverage' => $coverage,
    'files' => $files,
    'errors' => $errors,
    'warnings' => array_values(array_unique($warnings)),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($status === 'fail') {
    exit(2);
}
if (!empty($options['strict']) && $status === 'warn') {
    exit(1);
}
exit(0);

/**
 * Count SQL helper with a stable integer return type.
 *
 * @param string $sql
 * @param array $params
 * @return int
 */
function local_flwcupkp_health_count_sql(string $sql, array $params = []): int {
    global $DB;

    return (int)$DB->count_records_sql($sql, $params);
}

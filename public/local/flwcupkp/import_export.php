<?php
// C-UP-KP package import and export UI.

require_once(__DIR__ . '/../../config.php');

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);
$export = optional_param('export', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

if ($export) {
    require_capability('local/flwcupkp:import', $context);
    $package = \local_flwcupkp\local\curriculum_manager::export_package($frameworkid);
    $filename = 'flw-cupkp-export-' . ($package['unit_code'] ?: 'framework') . '-' . date('Ymd-His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$url = new moodle_url('/local/flwcupkp/import_export.php');
if ($frameworkid) {
    $url->param('frameworkid', $frameworkid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('importexport', 'local_flwcupkp'));
$PAGE->set_heading(get_string('importexport', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    require_capability('local/flwcupkp:import', $context);

    $mode = required_param('mode', PARAM_ALPHANUMEXT);
    $json = optional_param('jsonpackage', '', PARAM_RAW);
    $csv = optional_param('csvpackage', '', PARAM_RAW);
    $csvtype = optional_param('csvtype', 'activity_mappings', PARAM_ALPHANUMEXT);
    $sourcefile = optional_param('sourcefile', '', PARAM_PATH);
    if (in_array($mode, ['validate', 'import'], true) && trim($json) === '' && $sourcefile !== '') {
        [$json, $sourcefile] = local_flwcupkp_read_import_source($sourcefile);
    }
    if (in_array($mode, ['validatecsv', 'importcsv'], true) && trim($csv) === '' && $sourcefile !== '') {
        [$csv, $sourcefile] = local_flwcupkp_read_import_source($sourcefile);
    }

    if ($mode === 'validate') {
        $package = json_decode($json, true);
        $result = \local_flwcupkp\local\validator::validate_package(is_array($package) ? $package : []);
    } else if ($mode === 'import') {
        $result = \local_flwcupkp\local\import_service::import_json($json, $sourcefile);
    } else if ($mode === 'validatecsv') {
        $result = \local_flwcupkp\local\import_service::validate_csv($csv, $csvtype);
    } else if ($mode === 'importcsv') {
        $result = \local_flwcupkp\local\import_service::import_csv($csv, $csvtype, $sourcefile);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importexport', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', ['frameworkid' => $frameworkid]),
    get_string('backtocurriculum', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/import_export.php', ['frameworkid' => $frameworkid, 'export' => 1]),
    get_string('exportjson', 'local_flwcupkp'), ['class' => 'btn btn-primary']);
echo html_writer::end_tag('div');

if ($result !== null) {
    echo html_writer::tag('h3', get_string('result', 'local_flwcupkp'));
    echo html_writer::tag('pre', s(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
        ['class' => 'local-flwcupkp-json-result']);
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url,
    'class' => 'local-flwcupkp-editform local-flwcupkp-importform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
echo html_writer::label(get_string('serverfilepath', 'local_flwcupkp'), 'id_sourcefile');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'sourcefile',
    'id' => 'id_sourcefile',
    'value' => 'local/flwcupkp/fixtures/rew_u038_problem_solving_reference.json',
]);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
echo html_writer::label(get_string('jsonpackage', 'local_flwcupkp'), 'id_jsonpackage');
echo html_writer::tag('textarea', '', ['name' => 'jsonpackage', 'id' => 'id_jsonpackage', 'rows' => 16]);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
echo html_writer::label(get_string('csvtype', 'local_flwcupkp'), 'id_csvtype');
echo html_writer::select([
    'activity_mappings' => get_string('csvactivitymappings', 'local_flwcupkp'),
    'quiz_kp_mappings' => get_string('csvquizkpmappings', 'local_flwcupkp'),
], 'csvtype', 'activity_mappings', false, ['id' => 'id_csvtype']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
echo html_writer::label(get_string('csvpackage', 'local_flwcupkp'), 'id_csvpackage');
echo html_writer::tag('textarea', '', ['name' => 'csvpackage', 'id' => 'id_csvpackage', 'rows' => 8]);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
echo html_writer::tag('button', get_string('validatepackage', 'local_flwcupkp'), [
    'type' => 'submit',
    'name' => 'mode',
    'value' => 'validate',
    'class' => 'btn btn-secondary',
]);
echo html_writer::tag('button', get_string('importpackage', 'local_flwcupkp'), [
    'type' => 'submit',
    'name' => 'mode',
    'value' => 'import',
    'class' => 'btn btn-primary',
]);
echo html_writer::tag('button', get_string('validatecsvpackage', 'local_flwcupkp'), [
    'type' => 'submit',
    'name' => 'mode',
    'value' => 'validatecsv',
    'class' => 'btn btn-secondary',
]);
echo html_writer::tag('button', get_string('importcsvpackage', 'local_flwcupkp'), [
    'type' => 'submit',
    'name' => 'mode',
    'value' => 'importcsv',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('div');
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

/**
 * Read a package from a safe plugin-relative import path.
 *
 * @param string $sourcefile
 * @return array
 */
function local_flwcupkp_read_import_source(string $sourcefile): array {
    return \local_flwcupkp\local\unit_setup_service::read_import_source($sourcefile);
}

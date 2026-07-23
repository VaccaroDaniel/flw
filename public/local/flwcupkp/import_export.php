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
    $sourcefile = optional_param('sourcefile', '', PARAM_PATH);
    if (trim($json) === '' && $sourcefile !== '') {
        [$json, $sourcefile] = local_flwcupkp_read_import_source($sourcefile);
    }

    if ($mode === 'validate') {
        $package = json_decode($json, true);
        $result = \local_flwcupkp\local\validator::validate_package(is_array($package) ? $package : []);
    } else {
        $result = \local_flwcupkp\local\import_service::import_json($json, $sourcefile);
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
    global $CFG;

    $sourcefile = trim(str_replace('\\', '/', $sourcefile));
    if ($sourcefile === '') {
        return ['', ''];
    }
    if (preg_match('/^[A-Za-z]:|^\//', $sourcefile) || strpos($sourcefile, '..') !== false) {
        throw new invalid_parameter_exception('Only plugin-relative C-UP-KP import paths are allowed.');
    }

    $allowedprefixes = [
        'local/flwcupkp/fixtures/',
        'local/flwcupkp/imports/',
    ];
    $allowed = false;
    foreach ($allowedprefixes as $prefix) {
        if (strpos($sourcefile, $prefix) === 0) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        throw new invalid_parameter_exception('C-UP-KP import path must be under local/flwcupkp/fixtures or local/flwcupkp/imports.');
    }

    $fullpath = $CFG->dirroot . '/' . $sourcefile;
    if (!is_readable($fullpath)) {
        throw new moodle_exception('invalidfile', 'error', '', $sourcefile);
    }

    return [file_get_contents($fullpath), $sourcefile];
}

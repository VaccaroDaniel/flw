<?php
// Admin setup wizard for C-UP-KP units.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$unitselect = optional_param('unitselect', '', PARAM_ALPHANUMEXT);
$statusmessage = optional_param('statusmessage', '', PARAM_ALPHANUMEXT);

if ($unitcode === '' && $unitselect !== '') {
    $unitcode = $unitselect;
}

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:import', $context);

$url = new moodle_url('/local/flwcupkp/setup.php');
if ($courseid > 0) {
    $url->param('courseid', $courseid);
}
if ($unitcode !== '') {
    $url->param('unitcode', $unitcode);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('unitsetupwizard', 'local_flwcupkp'));
$PAGE->set_heading(get_string('unitsetupwizard', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $mode = required_param('mode', PARAM_ALPHANUMEXT);
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
    $unitselect = optional_param('unitselect', '', PARAM_ALPHANUMEXT);
    if ($unitcode === '' && $unitselect !== '') {
        $unitcode = $unitselect;
    }

    try {
        if (in_array($mode, ['validate', 'import'], true)) {
            [$json, $sourcefile] = local_flwcupkp_setup_read_package_from_request();
            $package = json_decode($json, true);
            if (!is_array($package)) {
                throw new invalid_parameter_exception('Invalid JSON package.');
            }
            $inferredunitcode = \local_flwcupkp\local\unit_setup_service::infer_unit_code_from_package($package);
            if ($unitcode === '') {
                $unitcode = $inferredunitcode;
            }
            if ($mode === 'validate') {
                $result = [
                    'status' => 'validated',
                    'inferred_unitcode' => $inferredunitcode,
                    'validation' => \local_flwcupkp\local\validator::validate_package($package),
                ];
            } else {
                $result = \local_flwcupkp\local\import_service::import_json($json, $sourcefile);
                $result['inferred_unitcode'] = $inferredunitcode;
            }
        } else if ($mode === 'link') {
            $result = \local_flwcupkp\local\unit_setup_service::link_course($unitcode, $courseid);
        } else if ($mode === 'create_shell') {
            $shortname = optional_param('shortname', '', PARAM_TEXT);
            $result = \local_flwcupkp\local\unit_setup_service::create_shell($unitcode, $shortname, false);
            $courseid = (int)$result['courseid'];
        } else if ($mode === 'refresh') {
            $result = [
                'status' => 'refreshed',
                'setup' => \local_flwcupkp\local\unit_setup_service::status($unitcode, $courseid),
            ];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$setupstatus = null;
if ($unitcode !== '') {
    try {
        $setupstatus = \local_flwcupkp\local\unit_setup_service::status($unitcode, $courseid);
        if ($courseid <= 0 && !empty($setupstatus['courseid'])) {
            $courseid = (int)$setupstatus['courseid'];
        }
    } catch (Exception $e) {
        if ($error === '') {
            $error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unitsetupwizard', 'local_flwcupkp'));

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'), get_string('pluginname', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/import_export.php'), get_string('importexport', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', ['unitcode' => $unitcode]),
    get_string('curriculummanager', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

if ($statusmessage !== '') {
    echo $OUTPUT->notification(s($statusmessage), 'success');
}
if ($error !== '') {
    echo $OUTPUT->notification(s($error), 'error');
}
if ($result !== null) {
    echo html_writer::tag('h3', get_string('result', 'local_flwcupkp'));
    echo html_writer::tag('pre', s(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
        ['class' => 'local-flwcupkp-json-result']);
}

echo \local_flwcupkp\local\visuals::setup_stepper($setupstatus, $courseid, $unitcode);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-setup-shell']);
local_flwcupkp_setup_scope_form($courseid, $unitcode);
local_flwcupkp_setup_import_form($courseid, $unitcode);
local_flwcupkp_setup_status($setupstatus, $courseid, $unitcode);
local_flwcupkp_setup_activation_form($setupstatus, $courseid, $unitcode);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render the unit/course selection form.
 *
 * @param int $courseid
 * @param string $unitcode
 */
function local_flwcupkp_setup_scope_form(int $courseid, string $unitcode): void {
    echo html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-setup-section',
        'id' => 'flwcupkp-setup-course',
    ]);
    echo html_writer::tag('h3', get_string('setupstepselect', 'local_flwcupkp'));
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/setup.php'),
        'class' => 'local-flwcupkp-editform local-flwcupkp-setup-form',
    ]);

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-setup-grid']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow local-flwcupkp-setup-course-row']);
    echo html_writer::label(get_string('course', 'moodle'), 'id_courseid');
    echo html_writer::select(local_flwcupkp_setup_course_options(), 'courseid', $courseid, ['0' => get_string('choose')], [
        'id' => 'id_courseid',
    ]);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label(get_string('setupexistingunit', 'local_flwcupkp'), 'id_unitselect');
    echo html_writer::select(local_flwcupkp_setup_unit_options(), 'unitselect', $unitcode,
        ['' => get_string('choose')], ['id' => 'id_unitselect']);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label(get_string('field_unitcode', 'local_flwcupkp'), 'id_unitcode');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'unitcode',
        'id' => 'id_unitcode',
        'value' => $unitcode,
        'placeholder' => 'U038',
    ]);
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
    echo html_writer::tag('button', get_string('refreshstatus', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('section');
}

/**
 * Render status cards and object link status.
 *
 * @param array|null $status
 * @param int $courseid
 * @param string $unitcode
 */
function local_flwcupkp_setup_status(?array $status, int $courseid, string $unitcode): void {
    echo html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-setup-section',
        'id' => 'flwcupkp-setup-links',
    ]);
    echo html_writer::tag('h3', get_string('setupstepstatus', 'local_flwcupkp'));
    if ($unitcode === '') {
        echo html_writer::tag('p', get_string('setupnounitselected', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted local-flwcupkp-setup-note',
        ]);
        echo html_writer::end_tag('section');
        return;
    }
    if ($status === null) {
        echo html_writer::tag('p', get_string('setupstatusunavailable', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted local-flwcupkp-setup-note',
        ]);
        echo html_writer::end_tag('section');
        return;
    }

    $counts = $status['counts'];
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-setup-cards']);
    echo local_flwcupkp_setup_card(get_string('learningobjects', 'local_flwcupkp'), $status['objectcount']);
    echo local_flwcupkp_setup_card(get_string('linkedactivities', 'local_flwcupkp'), $counts['linked']);
    echo local_flwcupkp_setup_card(get_string('missingactivity', 'local_flwcupkp'), $counts['missing_activity']);
    echo local_flwcupkp_setup_card(get_string('readytolink', 'local_flwcupkp'), $counts['ready_to_link']);
    echo local_flwcupkp_setup_card(get_string('objectmappings', 'local_flwcupkp'), $status['objectmapcount']);
    echo local_flwcupkp_setup_card(get_string('activationstatus', 'local_flwcupkp'),
        $status['activation']['ready'] ? get_string('active', 'local_flwcupkp') : get_string('notready', 'local_flwcupkp'),
        $status['activation']['ready'] ? 'ready' : 'blocked');
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
    echo html_writer::tag('span', get_string('competencies', 'local_flwcupkp') . ': ' .
        $status['targetcounts']['competency']);
    echo html_writer::tag('span', get_string('usepoints', 'local_flwcupkp') . ': ' . $status['targetcounts']['up']);
    echo html_writer::tag('span', get_string('knowledgepoints', 'local_flwcupkp') . ': ' . $status['targetcounts']['kp']);
    echo html_writer::end_tag('div');

    if (!empty($status['activation']['issues'])) {
        echo $GLOBALS['OUTPUT']->notification(implode('<br>', array_map('s', $status['activation']['issues'])), 'warning');
        echo local_flwcupkp_setup_fix_toolbar($status, $courseid, $unitcode);
    } else {
        echo $GLOBALS['OUTPUT']->notification(get_string('setupactivationready', 'local_flwcupkp'), 'success');
    }

    if ($status['activation']['ready'] && $courseid > 0) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
        echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('course'), ['class' => 'btn btn-secondary']);
        echo html_writer::link(new moodle_url('/local/flwcupkp/student.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]), get_string('unitprogressgeneric', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
        echo html_writer::link(new moodle_url('/local/flwcupkp/teacher.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]), get_string('unitteacheroverview', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
    }

    if (empty($status['objects'])) {
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table local-flwcupkp-setup-table';
    $table->head = [
        get_string('learningobject', 'local_flwcupkp'),
        get_string('lesson', 'local_flwcupkp'),
        get_string('activity'),
        get_string('status'),
        get_string('field_cmid', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
    ];
    foreach ($status['objects'] as $row) {
        $label = html_writer::tag('strong', s($row['externalid'])) . html_writer::tag('div', s($row['title']),
            ['class' => 'local-flwcupkp-muted']);
        $table->data[] = [
            $label,
            s($row['lesson']),
            s($row['activity_name']),
            local_flwcupkp_setup_link_status($row['link_status']),
            $row['cmid'] ? (int)$row['cmid'] : ($row['matchedcmid'] ? (int)$row['matchedcmid'] : '-'),
            local_flwcupkp_setup_fix_action($row, $courseid, $unitcode),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render package import form.
 *
 * @param int $courseid
 * @param string $unitcode
 */
function local_flwcupkp_setup_import_form(int $courseid, string $unitcode): void {
    echo html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-setup-section',
        'id' => 'flwcupkp-setup-import',
    ]);
    echo html_writer::tag('h3', get_string('setupstepimport', 'local_flwcupkp'));
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/setup.php'),
        'class' => 'local-flwcupkp-editform local-flwcupkp-setup-form',
        'enctype' => 'multipart/form-data',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label(get_string('jsonpackagefile', 'local_flwcupkp'), 'id_jsonfile');
    echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'jsonfile', 'id' => 'id_jsonfile', 'accept' => '.json']);
    echo html_writer::end_tag('div');

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
    echo html_writer::tag('textarea', '', ['name' => 'jsonpackage', 'id' => 'id_jsonpackage', 'rows' => 10]);
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
    echo html_writer::end_tag('section');
}

/**
 * Render link/activation form.
 *
 * @param array|null $status
 * @param int $courseid
 * @param string $unitcode
 */
function local_flwcupkp_setup_activation_form(?array $status, int $courseid, string $unitcode): void {
    echo html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-setup-section',
        'id' => 'flwcupkp-setup-activate',
    ]);
    echo html_writer::tag('h3', get_string('setupstepactivate', 'local_flwcupkp'));
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/setup.php'),
        'class' => 'local-flwcupkp-editform local-flwcupkp-setup-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-setup-grid']);
    echo html_writer::start_tag('div', [
        'class' => 'local-flwcupkp-formrow local-flwcupkp-setup-course-row local-flwcupkp-setup-activate-course-row',
    ]);
    echo html_writer::label(get_string('course', 'moodle'), 'id_activatecourseid');
    echo html_writer::select(local_flwcupkp_setup_course_options(), 'courseid', $courseid, ['0' => get_string('choose')], [
        'id' => 'id_activatecourseid',
    ]);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label(get_string('field_unitcode', 'local_flwcupkp'), 'id_activateunitcode');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'unitcode',
        'id' => 'id_activateunitcode',
        'value' => $unitcode,
        'placeholder' => 'U038',
    ]);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label(get_string('shortname'), 'id_shortname');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'shortname',
        'id' => 'id_shortname',
        'placeholder' => 'FLW-REW-U038-CUPKP',
    ]);
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
    echo html_writer::tag('button', get_string('linkselectedcourse', 'local_flwcupkp'), [
        'type' => 'submit',
        'name' => 'mode',
        'value' => 'link',
        'class' => 'btn btn-primary',
        'disabled' => ($unitcode === '' || $courseid <= 0) ? 'disabled' : null,
    ]);
    echo html_writer::tag('button', get_string('createshellcourse', 'local_flwcupkp'), [
        'type' => 'submit',
        'name' => 'mode',
        'value' => 'create_shell',
        'class' => 'btn btn-secondary',
        'disabled' => $unitcode === '' ? 'disabled' : null,
    ]);
    echo html_writer::tag('button', get_string('refreshstatus', 'local_flwcupkp'), [
        'type' => 'submit',
        'name' => 'mode',
        'value' => 'refresh',
        'class' => 'btn btn-secondary',
        'disabled' => $unitcode === '' ? 'disabled' : null,
    ]);
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('section');
}

/**
 * Read JSON from textarea, upload, or safe plugin-relative source path.
 *
 * @return array
 */
function local_flwcupkp_setup_read_package_from_request(): array {
    $json = optional_param('jsonpackage', '', PARAM_RAW);
    $sourcefile = optional_param('sourcefile', '', PARAM_PATH);
    if (trim($json) !== '') {
        return [$json, 'textarea'];
    }
    if (!empty($_FILES['jsonfile']['tmp_name']) && is_uploaded_file($_FILES['jsonfile']['tmp_name'])) {
        $filename = clean_param($_FILES['jsonfile']['name'] ?? 'uploaded-package.json', PARAM_FILE);
        return [file_get_contents($_FILES['jsonfile']['tmp_name']), 'upload:' . $filename];
    }
    if ($sourcefile !== '') {
        return \local_flwcupkp\local\unit_setup_service::read_import_source($sourcefile);
    }
    throw new invalid_parameter_exception('Choose a JSON package file, paste JSON, or provide a plugin-relative package path.');
}

/**
 * Course select options.
 *
 * @return array
 */
function local_flwcupkp_setup_course_options(): array {
    global $DB, $SITE;

    $records = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => $SITE->id],
        'sortorder ASC, fullname ASC', 'id, fullname, shortname', 0, 500);
    $options = [];
    foreach ($records as $course) {
        $options[(int)$course->id] = format_string($course->fullname) . ' (' . s($course->shortname) . ')';
    }
    return $options;
}

/**
 * Unit select options.
 *
 * @return array
 */
function local_flwcupkp_setup_unit_options(): array {
    return \local_flwcupkp\local\curriculum_manager::unit_options();
}

/**
 * Render one setup card.
 *
 * @param string $label
 * @param mixed $value
 * @param string $state
 * @return string
 */
function local_flwcupkp_setup_card(string $label, $value, string $state = ''): string {
    $classes = 'local-flwcupkp-setup-card';
    if ($state !== '') {
        $classes .= ' local-flwcupkp-setup-card-' . clean_param($state, PARAM_ALPHANUMEXT);
    }
    return html_writer::tag('div',
        html_writer::tag('strong', s((string)$value)) . html_writer::tag('span', s($label)),
        ['class' => $classes]
    );
}

/**
 * Render next-fix links for the selected setup status.
 *
 * @param array $status
 * @param int $courseid
 * @param string $unitcode
 * @return string
 */
function local_flwcupkp_setup_fix_toolbar(array $status, int $courseid, string $unitcode): string {
    $links = [];
    if ((int)($status['objectcount'] ?? 0) === 0) {
        $links[] = html_writer::link(local_flwcupkp_setup_anchor_url($courseid, $unitcode, 'flwcupkp-setup-import'),
            get_string('fiximportpackage', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    if ($courseid <= 0) {
        $links[] = html_writer::link(local_flwcupkp_setup_anchor_url($courseid, $unitcode, 'flwcupkp-setup-course'),
            get_string('fixchoosecourse', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    if ((int)($status['objectcount'] ?? 0) > 0 &&
            (int)($status['counts']['linked'] ?? 0) < (int)($status['objectcount'] ?? 0)) {
        $links[] = html_writer::link(local_flwcupkp_setup_anchor_url($courseid, $unitcode, 'flwcupkp-setup-activate'),
            get_string('fixlinkactivities', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    if ((int)($status['objectmapcount'] ?? 0) === 0) {
        $links[] = html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', ['unitcode' => $unitcode]),
            get_string('fixmapping', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    if (!$links) {
        return '';
    }

    return html_writer::tag('div',
        html_writer::tag('strong', get_string('fixnextsteps', 'local_flwcupkp')) .
        html_writer::tag('div', implode('', $links), ['class' => 'local-flwcupkp-formactions']),
        ['class' => 'local-flwcupkp-setup-actions']
    );
}

/**
 * Render a targeted fix link for one object status row.
 *
 * @param array $row
 * @param int $courseid
 * @param string $unitcode
 * @return string
 */
function local_flwcupkp_setup_fix_action(array $row, int $courseid, string $unitcode): string {
    $status = (string)($row['link_status'] ?? '');
    $linkedcourseid = (int)($row['courseid'] ?? 0);
    if ($linkedcourseid <= 0) {
        $linkedcourseid = $courseid;
    }

    if ($status === 'linked' && $linkedcourseid > 0) {
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $linkedcourseid]),
            get_string('fixviewcourse', 'local_flwcupkp'), ['class' => 'btn btn-link btn-sm']);
    }
    if ($status === 'ready_to_link') {
        return html_writer::link(local_flwcupkp_setup_anchor_url($courseid, $unitcode, 'flwcupkp-setup-activate'),
            get_string('fixlinkready', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    if ($status === 'missing_activity' && $courseid > 0) {
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('fixmissingactivity', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    if ($status === 'linked_to_other_course' && $linkedcourseid > 0) {
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $linkedcourseid]),
            get_string('fixothercourse', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
    }
    return html_writer::link(local_flwcupkp_setup_anchor_url($courseid, $unitcode, 'flwcupkp-setup-course'),
        get_string('fixchoosecourse', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
}

/**
 * Build a setup URL pointing at a page section.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param string $anchor
 * @return moodle_url
 */
function local_flwcupkp_setup_anchor_url(int $courseid, string $unitcode, string $anchor): moodle_url {
    $params = [];
    if ($courseid > 0) {
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $params['unitcode'] = $unitcode;
    }
    $url = new moodle_url('/local/flwcupkp/setup.php', $params);
    $url->set_anchor($anchor);
    return $url;
}

/**
 * Render a link status badge.
 *
 * @param string $status
 * @return string
 */
function local_flwcupkp_setup_link_status(string $status): string {
    $labels = [
        'linked' => get_string('linked', 'local_flwcupkp'),
        'ready_to_link' => get_string('readytolink', 'local_flwcupkp'),
        'missing_activity' => get_string('missingactivity', 'local_flwcupkp'),
        'linked_to_other_course' => get_string('linkedtoothercourse', 'local_flwcupkp'),
        'not_linked' => get_string('notlinked', 'local_flwcupkp'),
    ];
    $label = $labels[$status] ?? $status;
    return html_writer::tag('span', s($label), [
        'class' => 'local-flwcupkp-setup-badge local-flwcupkp-setup-badge-' . clean_param($status, PARAM_ALPHANUMEXT),
    ]);
}

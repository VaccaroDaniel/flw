<?php
// Manual evidence recording page for C-UP-KP.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = $courseid > 0 ? context_course::instance($courseid) : context_system::instance();
require_capability('local/flwcupkp:override', $context);

$url = new moodle_url('/local/flwcupkp/manual_evidence.php');
if ($courseid) {
    $url->param('courseid', $courseid);
}
if ($unitcode !== '') {
    $url->param('unitcode', $unitcode);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $mapid = required_param('mapid', PARAM_INT);
    $userid = required_param('userid', PARAM_INT);
    $rawscore = optional_param('rawscore', 0, PARAM_FLOAT);
    $normalizedscore = required_param('normalizedscore', PARAM_FLOAT);
    $confidence = optional_param('confidence', 0.75, PARAM_FLOAT);
    $evidencestrength = required_param('evidencestrength', PARAM_ALPHANUMEXT);
    $note = optional_param('note', '', PARAM_TEXT);

    $map = $DB->get_record('flwcupkp_object_map', ['id' => $mapid], '*', MUST_EXIST);
    $object = $DB->get_record('flwcupkp_object', ['id' => $map->objectid], '*', MUST_EXIST);
    \local_flwcupkp\local\evidence_guard::assert_object_map_can_record($object, $map, $userid, $courseid, $unitcode);
    $result = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
        'userid' => $userid,
        'courseid' => $courseid ?: (int)$object->courseid,
        'unitcode' => $object->unitcode,
        'objectid' => (int)$object->id,
        'sourceattempt' => 'manual:' . time() . ':map:' . $mapid,
        'evidencetype' => 'manual_teacher_evidence',
        'targettype' => $map->targettype,
        'targetid' => (int)$map->targetid,
        'rawscore' => $rawscore,
        'normalizedscore' => max(0, min(1, $normalizedscore)),
        'rubricjson' => json_encode(['note' => $note]),
        'assessortype' => 'teacher',
        'confidence' => max(0, min(1, $confidence)),
        'evidencestrength' => $evidencestrength,
        'provenance' => 'manual_teacher_entry',
        'sourceref' => 'manual_evidence',
    ]);
    \local_flwcupkp\local\repository::audit('manual_evidence_recorded', $map->targettype, (int)$map->targetid, [
        'evidenceid' => $result['evidenceid'],
        'userid' => $userid,
        'objectid' => (int)$object->id,
        'mapid' => $mapid,
    ]);
    redirect(new moodle_url('/local/flwcupkp/manual_evidence.php', [
        'courseid' => $courseid,
        'unitcode' => $object->unitcode,
        'status' => 'saved',
    ]));
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manualevidence', 'local_flwcupkp'));
$PAGE->set_heading(get_string('manualevidence', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualevidence', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('backtocurriculum', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/flwcupkp/manual_evidence.php'), 'class' => 'local-flwcupkp-filters']);
echo html_writer::tag('label', get_string('courseid', 'local_flwcupkp') .
    html_writer::empty_tag('input', ['type' => 'number', 'name' => 'courseid', 'value' => $courseid]), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

$mapoptions = local_flwcupkp_manual_evidence_map_options($courseid, $unitcode);
$useroptions = local_flwcupkp_manual_evidence_user_options($courseid);

if (!$mapoptions || !$useroptions) {
    echo $OUTPUT->notification(get_string('manualevidencenotready', 'local_flwcupkp'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-editform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
local_flwcupkp_manual_select_row('userid', get_string('learner', 'local_flwcupkp'), $useroptions);
local_flwcupkp_manual_select_row('mapid', get_string('evidencesource', 'local_flwcupkp'), $mapoptions);
local_flwcupkp_manual_input_row('rawscore', get_string('rawscore', 'local_flwcupkp'), '1.00', 'number');
local_flwcupkp_manual_input_row('normalizedscore', get_string('normalizedscore', 'local_flwcupkp'), '1.00', 'number');
local_flwcupkp_manual_input_row('confidence', get_string('confidence', 'local_flwcupkp'), '0.75', 'number');
local_flwcupkp_manual_select_row('evidencestrength', get_string('evidencestrength', 'local_flwcupkp'), [
    'exposure' => 'exposure',
    'recognition' => 'recognition',
    'controlled_production' => 'controlled_production',
    'guided_performance' => 'guided_performance',
    'independent_performance' => 'independent_performance',
    'transfer_performance' => 'transfer_performance',
]);
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
echo html_writer::label(get_string('note', 'local_flwcupkp'), 'id_note');
echo html_writer::tag('textarea', '', ['name' => 'note', 'id' => 'id_note', 'rows' => 4]);
echo html_writer::end_tag('div');
echo html_writer::tag('button', get_string('recordevidence', 'local_flwcupkp'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

/**
 * Build mapped target options.
 */
function local_flwcupkp_manual_evidence_map_options(int $courseid, string $unitcode): array {
    global $DB;

    $params = [];
    $where = '1=1';
    if ($courseid > 0) {
        $where .= ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $where .= ' AND o.unitcode = :unitcode';
        $params['unitcode'] = $unitcode;
    }
    $records = $DB->get_records_sql(
        "SELECT m.id, m.targettype, m.targetid, o.externalid AS objectexternalid,
                o.title AS objecttitle, o.unitcode, o.lesson
           FROM {flwcupkp_object_map} m
           JOIN {flwcupkp_object} o ON o.id = m.objectid
          WHERE {$where}
       ORDER BY o.unitcode ASC, o.lesson ASC, o.externalid ASC, m.targettype ASC",
        $params
    );
    $options = [];
    foreach ($records as $record) {
        $target = local_flwcupkp_manual_target_label($record->targettype, (int)$record->targetid);
        $options[(int)$record->id] = $record->unitcode . ' L' . $record->lesson . ' ' .
            $record->objectexternalid . ' -> ' . $target;
    }
    return $options;
}

/**
 * Target display label.
 */
function local_flwcupkp_manual_target_label(string $targettype, int $targetid): string {
    global $DB;

    $table = $targettype === 'competency' ? 'flwcupkp_comp' : ($targettype === 'up' ? 'flwcupkp_up' : 'flwcupkp_kp');
    $record = $DB->get_record($table, ['id' => $targetid], 'externalid, title', IGNORE_MISSING);
    return $record ? $targettype . ':' . $record->externalid . ' ' . $record->title : $targettype . ':' . $targetid;
}

/**
 * User select options.
 */
function local_flwcupkp_manual_evidence_user_options(int $courseid): array {
    global $DB;

    if ($courseid > 0) {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email', 'u.lastname, u.firstname');
        } else {
            $users = [];
        }
    } else {
        $users = $DB->get_records('user', ['deleted' => 0, 'confirmed' => 1], 'lastname ASC, firstname ASC', 'id, firstname, lastname, email', 0, 200);
    }
    $options = [];
    foreach ($users as $user) {
        $options[(int)$user->id] = fullname($user) . ' (' . $user->email . ')';
    }
    return $options;
}

/**
 * Select row helper.
 */
function local_flwcupkp_manual_select_row(string $name, string $label, array $options): void {
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($label, 'id_' . $name);
    echo html_writer::select($options, $name, '', false, ['id' => 'id_' . $name, 'required' => 'required']);
    echo html_writer::end_tag('div');
}

/**
 * Input row helper.
 */
function local_flwcupkp_manual_input_row(string $name, string $label, string $value, string $type): void {
    $attrs = ['type' => $type, 'name' => $name, 'id' => 'id_' . $name, 'value' => $value, 'required' => 'required'];
    if ($type === 'number') {
        $attrs['min'] = '0';
        $attrs['max'] = '1';
        $attrs['step'] = '0.01';
    }
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($label, 'id_' . $name);
    echo html_writer::empty_tag('input', $attrs);
    echo html_writer::end_tag('div');
}

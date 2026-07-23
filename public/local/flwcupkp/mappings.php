<?php
// C-UP-KP mapping manager.

require_once(__DIR__ . '/../../config.php');

$type = optional_param('type', 'comp_up', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:manageframeworks', $context);

$config = \local_flwcupkp\local\curriculum_manager::mapping_config($type);
$url = new moodle_url('/local/flwcupkp/mappings.php', ['type' => $type]);
if ($frameworkid) {
    $url->param('frameworkid', $frameworkid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHANUMEXT);
    if ($action === 'delete') {
        \local_flwcupkp\local\curriculum_manager::delete_mapping($type, required_param('id', PARAM_INT));
        redirect(new moodle_url('/local/flwcupkp/mappings.php', ['type' => $type, 'frameworkid' => $frameworkid, 'status' => 'deleted']));
    }

    $data = [];
    foreach ($config['fields'] as $field) {
        $data[$field] = optional_param($field, '', PARAM_RAW_TRIMMED);
    }
    if ($type === 'object_map') {
        $target = required_param('target', PARAM_RAW_TRIMMED);
        [$targettype, $targetid] = array_pad(explode(':', $target, 2), 2, '');
        $data['targettype'] = clean_param($targettype, PARAM_ALPHANUMEXT);
        $data['targetid'] = clean_param($targetid, PARAM_INT);
    }
    \local_flwcupkp\local\curriculum_manager::save_mapping($type, $data);
    redirect(new moodle_url('/local/flwcupkp/mappings.php', ['type' => $type, 'frameworkid' => $frameworkid, 'status' => 'saved']));
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('mappingmanager', 'local_flwcupkp'));
$PAGE->set_heading(get_string('mappingmanager', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mappingmanager', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', ['frameworkid' => $frameworkid]),
    get_string('backtocurriculum', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
foreach (\local_flwcupkp\local\curriculum_manager::mapping_types() as $mappingtype => $mappingconfig) {
    echo html_writer::link(new moodle_url('/local/flwcupkp/mappings.php', [
        'type' => $mappingtype,
        'frameworkid' => $frameworkid,
    ]), get_string('mapping_' . $mappingtype, 'local_flwcupkp'), [
        'class' => $mappingtype === $type ? 'btn btn-primary' : 'btn btn-secondary',
    ]);
}
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/mappings.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]);
echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
    html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
        \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

local_flwcupkp_render_mapping_form($type, $config, $frameworkid, $url);
local_flwcupkp_render_mapping_table($type, $frameworkid);

echo $OUTPUT->footer();

/**
 * Render add mapping form.
 */
function local_flwcupkp_render_mapping_form(string $type, array $config, int $frameworkid, moodle_url $url): void {
    echo html_writer::tag('h3', get_string('addmapping', 'local_flwcupkp'));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-editform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

    if ($type === 'comp_up') {
        local_flwcupkp_select_row('competencyid', get_string('competency', 'local_flwcupkp'),
            local_flwcupkp_entity_options('competency', $frameworkid));
        local_flwcupkp_select_row('upid', get_string('usepoint', 'local_flwcupkp'),
            local_flwcupkp_entity_options('up', $frameworkid));
    } else if ($type === 'up_kp') {
        local_flwcupkp_select_row('upid', get_string('usepoint', 'local_flwcupkp'),
            local_flwcupkp_entity_options('up', $frameworkid));
        local_flwcupkp_select_row('kpid', get_string('knowledgepoint', 'local_flwcupkp'),
            local_flwcupkp_entity_options('kp', $frameworkid));
    } else if ($type === 'kp_prereq') {
        local_flwcupkp_select_row('kpid', get_string('knowledgepoint', 'local_flwcupkp'),
            local_flwcupkp_entity_options('kp', $frameworkid));
        local_flwcupkp_select_row('prereqkpid', get_string('prerequisite', 'local_flwcupkp'),
            local_flwcupkp_entity_options('kp', $frameworkid));
    } else {
        local_flwcupkp_select_row('objectid', get_string('learningobject', 'local_flwcupkp'),
            local_flwcupkp_entity_options('object', $frameworkid));
        local_flwcupkp_select_row('target', get_string('target', 'local_flwcupkp'), local_flwcupkp_target_options($frameworkid));
    }

    foreach ($config['fields'] as $field) {
        if (in_array($field, ['competencyid', 'upid', 'kpid', 'prereqkpid', 'objectid', 'targettype', 'targetid'], true)) {
            continue;
        }
        local_flwcupkp_input_row($field, get_string('field_' . $field, 'local_flwcupkp'), local_flwcupkp_default_mapping_value($field));
    }

    echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}

/**
 * Render mapping table.
 */
function local_flwcupkp_render_mapping_table(string $type, int $frameworkid): void {
    $records = \local_flwcupkp\local\curriculum_manager::list_mappings($type, $frameworkid);
    echo html_writer::tag('h3', get_string('existingmappings', 'local_flwcupkp'));
    if (!$records) {
        echo html_writer::tag('p', get_string('nomappings', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [get_string('source', 'local_flwcupkp'), get_string('target', 'local_flwcupkp'), get_string('role', 'local_flwcupkp'), get_string('weight', 'local_flwcupkp'), get_string('actions')];
    foreach ($records as $record) {
        $target = $type === 'object_map' ?
            s($record->targettype . ':' . $record->targetid) :
            html_writer::tag('strong', s($record->rightexternalid)) . html_writer::tag('div', s($record->righttitle));
        $delete = html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/local/flwcupkp/mappings.php', ['type' => $type, 'frameworkid' => $frameworkid]), 'class' => 'local-flwcupkp-inlineform']);
        $delete .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $delete .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'delete']);
        $delete .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $record->id]);
        $delete .= html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-link btn-sm']);
        $delete .= html_writer::end_tag('form');
        $table->data[] = [
            html_writer::tag('strong', s($record->leftexternalid)) . html_writer::tag('div', s($record->lefttitle)),
            $target,
            s($record->role ?? $record->relationshiptype ?? ''),
            isset($record->weight) ? format_float((float)$record->weight, 2) : (isset($record->strength) ? format_float((float)$record->strength, 2) : ''),
            $delete,
        ];
    }
    echo html_writer::table($table);
}

/**
 * Select form row.
 */
function local_flwcupkp_select_row(string $name, string $label, array $options): void {
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($label, 'id_' . $name);
    echo html_writer::select($options, $name, '', false, ['id' => 'id_' . $name, 'required' => 'required']);
    echo html_writer::end_tag('div');
}

/**
 * Input form row.
 */
function local_flwcupkp_input_row(string $name, string $label, string $value): void {
    $type = in_array($name, ['weight', 'minmastery', 'minreadiness', 'strength'], true) ? 'number' : 'text';
    $attrs = ['type' => $type, 'name' => $name, 'id' => 'id_' . $name, 'value' => $value];
    if ($type === 'number') {
        $attrs['step'] = '0.01';
    }
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($label, 'id_' . $name);
    echo html_writer::empty_tag('input', $attrs);
    echo html_writer::end_tag('div');
}

/**
 * Entity select options.
 */
function local_flwcupkp_entity_options(string $type, int $frameworkid): array {
    $records = \local_flwcupkp\local\curriculum_manager::list_entities($type, $frameworkid);
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = $record->externalid . ' - ' . ($record->title ?? $record->name);
    }
    return $options;
}

/**
 * Object-map target select options.
 */
function local_flwcupkp_target_options(int $frameworkid): array {
    $options = [];
    foreach (['competency', 'up', 'kp'] as $type) {
        foreach (local_flwcupkp_entity_options($type, $frameworkid) as $id => $label) {
            $options[$type . ':' . $id] = get_string($type === 'up' ? 'usepoint' : ($type === 'kp' ? 'knowledgepoint' : 'competency'), 'local_flwcupkp') . ': ' . $label;
        }
    }
    return $options;
}

/**
 * Defaults for mapping fields.
 */
function local_flwcupkp_default_mapping_value(string $field): string {
    $defaults = [
        'role' => 'required',
        'weight' => '1.00',
        'sortorder' => '0',
        'minmastery' => '',
        'minreadiness' => '',
        'evidencerule' => '{}',
        'relationshiptype' => 'prerequisite',
        'strength' => '1.00',
        'requirement' => 'recommended',
        'evidencestrength' => 'recognition',
        'notes' => '',
    ];
    return $defaults[$field] ?? '';
}

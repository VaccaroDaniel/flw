<?php
// Program 3 Gate CM2 controlled C-UP-KP relationship editor.

require_once(__DIR__ . '/../../config.php');

$type = optional_param('type', 'comp_up', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);
$editid = optional_param('editid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:manageframeworks', $context);

$config = \local_flwcupkp\local\curriculum_manager::mapping_config($type);
$urlparams = ['type' => $type];
if ($frameworkid) {
    $urlparams['frameworkid'] = $frameworkid;
}
if ($courseid) {
    $urlparams['courseid'] = $courseid;
}
if ($unitcode !== '') {
    $urlparams['unitcode'] = $unitcode;
}
$url = new moodle_url('/local/flwcupkp/mappings.php', $urlparams);
$preview = null;
$previewerror = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHANUMEXT);
    try {
        if ($action === 'confirmdelete') {
            \local_flwcupkp\local\relationship_where_used_manager::apply_mapping_change($type, [
                'id' => required_param('id', PARAM_INT),
            ], 'delete', $courseid, $unitcode, 50);
            redirect(new moodle_url('/local/flwcupkp/mappings.php', $urlparams + ['status' => 'deleted']));
        } else if ($action === 'previewdelete' || $action === 'delete') {
            $preview = \local_flwcupkp\local\relationship_where_used_manager::preview_mapping_change($type, [
                'id' => required_param('id', PARAM_INT),
            ], 'delete', $courseid, $unitcode, 50);
        } else if ($action === 'confirmsave') {
            \local_flwcupkp\local\relationship_where_used_manager::apply_mapping_change(
                $type,
                local_flwcupkp_cm2_mapping_form_data($type, $config),
                'save',
                $courseid,
                $unitcode,
                50
            );
            redirect(new moodle_url('/local/flwcupkp/mappings.php', $urlparams + ['status' => 'saved']));
        } else {
            $preview = \local_flwcupkp\local\relationship_where_used_manager::preview_mapping_change(
                $type,
                local_flwcupkp_cm2_mapping_form_data($type, $config),
                'save',
                $courseid,
                $unitcode,
                50
            );
        }
    } catch (Throwable $e) {
        $previewerror = $e->getMessage();
    }
}

$editrecord = null;
if ($editid > 0) {
    $editrecord = \local_flwcupkp\local\curriculum_manager::get_mapping($type, $editid);
}
$cm2status = \local_flwcupkp\local\relationship_where_used_manager::status($courseid, $unitcode, $frameworkid, 50);
$governance = \local_flwcupkp\local\relationship_where_used_manager::coverage_governance_status(
    $frameworkid,
    $courseid,
    $unitcode,
    50
);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('cm2relationshipeditor', 'local_flwcupkp'));
$PAGE->set_heading(get_string('cm2relationshipeditor', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cm2relationshipeditor', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), 'success');
}
if ($previewerror !== '') {
    echo $OUTPUT->notification($previewerror, 'error');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm2-shell']);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', [
    'frameworkid' => $frameworkid,
    'unitcode' => $unitcode,
]), get_string('backtocurriculum', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/foundation.php', [
    'frameworkid' => $frameworkid,
    'unitcode' => $unitcode,
]), get_string('foundationinspector', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
foreach (\local_flwcupkp\local\curriculum_manager::mapping_types() as $mappingtype => $mappingconfig) {
    echo html_writer::link(new moodle_url('/local/flwcupkp/mappings.php', [
        'type' => $mappingtype,
        'frameworkid' => $frameworkid,
        'courseid' => $courseid,
        'unitcode' => $unitcode,
    ]), get_string('mapping_' . $mappingtype, 'local_flwcupkp'), [
        'class' => $mappingtype === $type ? 'btn btn-primary' : 'btn btn-secondary',
    ]);
}
echo html_writer::end_tag('div');

echo html_writer::tag('p', get_string('cm2relationshipeditorintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm2-intro',
]);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/mappings.php'),
    'class' => 'local-flwcupkp-cm2-filters',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]);
echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
    html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
        \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('course') .
    html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'courseid',
        'value' => $courseid ?: '',
        'min' => 0,
    ]),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('field_unitcode', 'local_flwcupkp') .
    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'unitcode', 'value' => s($unitcode)]),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo local_flwcupkp_cm2_status_panel($cm2status);
echo local_flwcupkp_cm2_governance_panel($governance);

if ($preview !== null) {
    echo local_flwcupkp_cm2_preview_panel($preview, $url);
}

local_flwcupkp_cm2_render_mapping_form($type, $config, $frameworkid, $url, $editrecord);
local_flwcupkp_cm2_render_mapping_table($type, $frameworkid, $courseid, $unitcode);

echo html_writer::end_tag('div');
echo $OUTPUT->footer();

/**
 * Read mapping form data.
 */
function local_flwcupkp_cm2_mapping_form_data(string $type, array $config): array {
    $data = [];
    $id = optional_param('id', 0, PARAM_INT);
    if ($id > 0) {
        $data['id'] = $id;
    }
    foreach ($config['fields'] as $field) {
        $data[$field] = optional_param($field, '', PARAM_RAW_TRIMMED);
    }
    if ($type === 'object_map') {
        $target = optional_param('target', '', PARAM_RAW_TRIMMED);
        if ($target !== '') {
            [$targettype, $targetid] = array_pad(explode(':', $target, 2), 2, '');
            $data['targettype'] = clean_param($targettype, PARAM_ALPHANUMEXT);
            $data['targetid'] = clean_param($targetid, PARAM_INT);
        }
    }
    return $data;
}

/**
 * CM2 status panel.
 */
function local_flwcupkp_cm2_status_panel(array $status): string {
    $cards = [
        [get_string('status'), $status['status'] ?? 'unknown', get_string('cm2statusintro', 'local_flwcupkp')],
        [get_string('foundationinspector', 'local_flwcupkp'), $status['foundation']['status'] ?? 'unknown',
            get_string('foundationnextgatedetail', 'local_flwcupkp')],
        [get_string('cm2nextgate', 'local_flwcupkp'), $status['next_allowed_gate'] ?? 'CM3',
            get_string('cm2nextgatedetail', 'local_flwcupkp')],
    ];

    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-cardgrid local-flwcupkp-cm2-cardgrid']);
    foreach ($cards as $card) {
        $html .= html_writer::tag('article',
            html_writer::tag('span', s($card[0])) .
            html_writer::tag('strong', s($card[1])) .
            html_writer::tag('em', s($card[2])),
            ['class' => 'local-flwcupkp-foundation-card local-flwcupkp-health-muted']
        );
    }
    $html .= html_writer::end_tag('section');
    return $html;
}

/**
 * Coverage governance panel.
 */
function local_flwcupkp_cm2_governance_panel(array $governance): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [get_string('metric', 'local_flwcupkp'), get_string('value', 'local_flwcupkp')];
    foreach ($governance['counts'] as $key => $value) {
        $table->data[] = [s(local_flwcupkp_cm2_human($key)), s((string)$value)];
    }
    $table->data[] = [
        get_string('cm2cachedcounts', 'local_flwcupkp'),
        s($governance['aggregation']['mode'] ?? ''),
    ];
    return local_flwcupkp_cm2_panel(get_string('cm2coveragegovernance', 'local_flwcupkp'),
        get_string('cm2coveragegovernanceintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * Render add/edit mapping form.
 */
function local_flwcupkp_cm2_render_mapping_form(string $type, array $config, int $frameworkid,
        moodle_url $url, ?stdClass $editrecord = null): void {
    $heading = $editrecord ? get_string('cm2editmapping', 'local_flwcupkp') :
        get_string('addmapping', 'local_flwcupkp');
    echo html_writer::tag('h3', $heading);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-editform local-flwcupkp-cm2-editform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'previewsave']);
    if ($editrecord) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$editrecord->id]);
    }

    if ($type === 'comp_up') {
        local_flwcupkp_cm2_select_row('competencyid', get_string('competency', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('competency', $frameworkid), $editrecord->competencyid ?? '');
        local_flwcupkp_cm2_select_row('upid', get_string('usepoint', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('up', $frameworkid), $editrecord->upid ?? '');
    } else if ($type === 'up_kp') {
        local_flwcupkp_cm2_select_row('upid', get_string('usepoint', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('up', $frameworkid), $editrecord->upid ?? '');
        local_flwcupkp_cm2_select_row('kpid', get_string('knowledgepoint', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('kp', $frameworkid), $editrecord->kpid ?? '');
    } else if ($type === 'kp_prereq') {
        local_flwcupkp_cm2_select_row('kpid', get_string('knowledgepoint', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('kp', $frameworkid), $editrecord->kpid ?? '');
        local_flwcupkp_cm2_select_row('prereqkpid', get_string('prerequisite', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('kp', $frameworkid), $editrecord->prereqkpid ?? '');
    } else {
        local_flwcupkp_cm2_select_row('objectid', get_string('learningobject', 'local_flwcupkp'),
            local_flwcupkp_cm2_entity_options('object', $frameworkid), $editrecord->objectid ?? '');
        $target = $editrecord ? (string)$editrecord->targettype . ':' . (int)$editrecord->targetid : '';
        local_flwcupkp_cm2_select_row('target', get_string('target', 'local_flwcupkp'),
            local_flwcupkp_cm2_target_options($frameworkid), $target);
    }

    foreach ($config['fields'] as $field) {
        if (in_array($field, ['competencyid', 'upid', 'kpid', 'prereqkpid', 'objectid', 'targettype', 'targetid'], true)) {
            continue;
        }
        $value = $editrecord && property_exists($editrecord, $field) ?
            (string)$editrecord->{$field} : local_flwcupkp_cm2_default_mapping_value($field);
        local_flwcupkp_cm2_input_row($field, get_string('field_' . $field, 'local_flwcupkp'), $value);
    }

    echo html_writer::tag('button', get_string('cm2previewrelationshipchange', 'local_flwcupkp'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}

/**
 * Render mapping table.
 */
function local_flwcupkp_cm2_render_mapping_table(string $type, int $frameworkid, int $courseid,
        string $unitcode): void {
    $records = \local_flwcupkp\local\curriculum_manager::list_mappings($type, $frameworkid);
    echo html_writer::tag('h3', get_string('existingmappings', 'local_flwcupkp'));
    if (!$records) {
        echo html_writer::tag('p', get_string('nomappings', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table local-flwcupkp-cm2-table';
    $table->head = [
        get_string('source', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('cm2semantic', 'local_flwcupkp'),
        get_string('role', 'local_flwcupkp'),
        get_string('weight', 'local_flwcupkp'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $endpoint = local_flwcupkp_cm2_endpoint_for_record($type, $record);
        $semantic = \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping($type, (array)$record);
        $editurl = new moodle_url('/local/flwcupkp/mappings.php', [
            'type' => $type,
            'frameworkid' => $frameworkid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'editid' => $record->id,
        ]);
        $actions = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-link btn-sm']);
        $actions .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/mappings.php', [
                'type' => $type,
                'frameworkid' => $frameworkid,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
            ]),
            'class' => 'local-flwcupkp-inlineform',
        ]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'previewdelete']);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $record->id]);
        $actions .= html_writer::tag('button', get_string('cm2previewdelete', 'local_flwcupkp'),
            ['type' => 'submit', 'class' => 'btn btn-link btn-sm']);
        $actions .= html_writer::end_tag('form');

        $table->data[] = [
            local_flwcupkp_cm2_entity_link($endpoint['source_type'], $endpoint['source_id'], $courseid, $unitcode),
            local_flwcupkp_cm2_entity_link($endpoint['target_type'], $endpoint['target_id'], $courseid, $unitcode),
            local_flwcupkp_cm2_badge($semantic),
            s($record->role ?? $record->relationshiptype ?? ''),
            isset($record->weight) ? format_float((float)$record->weight, 2) :
                (isset($record->strength) ? format_float((float)$record->strength, 2) : ''),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render CM2 preview and confirmation.
 */
function local_flwcupkp_cm2_preview_panel(array $preview, moodle_url $url): string {
    $body = html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm2-preview']);
    $body .= html_writer::tag('div',
        local_flwcupkp_cm2_badge($preview['valid'] ? get_string('cm2previewvalid', 'local_flwcupkp') :
            get_string('cm2previewblocked', 'local_flwcupkp')) .
        html_writer::tag('span', s((string)($preview['semantic'] ?? '')), ['class' => 'local-flwcupkp-cm2-semantic']),
        ['class' => 'local-flwcupkp-cm2-preview-head']
    );
    $body .= local_flwcupkp_cm2_validation_list($preview);
    $body .= local_flwcupkp_cm2_impact_cards($preview['impact']['counts'] ?? []);
    $body .= local_flwcupkp_cm2_endpoint_table($preview['endpoint'] ?? []);
    $body .= local_flwcupkp_cm2_object_table($preview['impact']['objects'] ?? []);

    if (!empty($preview['valid'])) {
        $body .= local_flwcupkp_cm2_confirm_form($preview, $url);
    }
    $body .= html_writer::end_tag('div');
    return local_flwcupkp_cm2_panel(get_string('cm2whereusedimpact', 'local_flwcupkp'),
        get_string('cm2whereusedimpactintro', 'local_flwcupkp'), $body);
}

/**
 * Preview validation details.
 */
function local_flwcupkp_cm2_validation_list(array $preview): string {
    $items = [];
    foreach ($preview['errors'] ?? [] as $error) {
        $items[] = html_writer::tag('li', s($error), ['class' => 'local-flwcupkp-cm2-error']);
    }
    foreach ($preview['warnings'] ?? [] as $warning) {
        $items[] = html_writer::tag('li', s($warning), ['class' => 'local-flwcupkp-cm2-warning']);
    }
    foreach ($preview['impact']['warnings'] ?? [] as $warning) {
        $items[] = html_writer::tag('li', s($warning), ['class' => 'local-flwcupkp-cm2-warning']);
    }
    if (!$items) {
        $items[] = html_writer::tag('li', get_string('cm2noimpact', 'local_flwcupkp'));
    }
    return html_writer::tag('ul', implode('', $items), ['class' => 'local-flwcupkp-cm2-validation']);
}

/**
 * Impact count cards.
 */
function local_flwcupkp_cm2_impact_cards(array $counts): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm2-impact-grid']);
    foreach ($counts as $key => $value) {
        $html .= html_writer::tag('article',
            html_writer::tag('span', s(local_flwcupkp_cm2_human((string)$key))) .
            html_writer::tag('strong', s((string)$value)),
            ['class' => 'local-flwcupkp-cm2-impact-card']
        );
    }
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Endpoint table.
 */
function local_flwcupkp_cm2_endpoint_table(array $endpoint): string {
    if (!$endpoint) {
        return '';
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [get_string('source', 'local_flwcupkp'), get_string('target', 'local_flwcupkp')];
    $table->data[] = [
        s($endpoint['source_label'] ?? ''),
        s($endpoint['target_label'] ?? ''),
    ];
    return html_writer::table($table);
}

/**
 * Impact object table.
 */
function local_flwcupkp_cm2_object_table(array $objects): string {
    if (!$objects) {
        return '';
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('learningobject', 'local_flwcupkp'),
        get_string('field_unitcode', 'local_flwcupkp'),
        get_string('field_lesson', 'local_flwcupkp'),
        get_string('field_cmid', 'local_flwcupkp'),
        get_string('cm2questionrefs', 'local_flwcupkp'),
    ];
    foreach ($objects as $object) {
        $table->data[] = [
            html_writer::tag('code', s($object['externalid'] ?? '')) .
                html_writer::tag('div', s($object['title'] ?? ''), ['class' => 'local-flwcupkp-muted']),
            s($object['unitcode'] ?? ''),
            s($object['lesson'] ?? ''),
            !empty($object['cmid']) ? 'CMID ' . (int)$object['cmid'] : '',
            s($object['questionid'] ?? ''),
        ];
    }
    return html_writer::table($table);
}

/**
 * Confirmation form.
 */
function local_flwcupkp_cm2_confirm_form(array $preview, moodle_url $url): string {
    $html = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url,
        'class' => 'local-flwcupkp-cm2-confirm',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    if (($preview['action'] ?? '') === 'delete') {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirmdelete']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',
            'value' => (int)($preview['proposed']['id'] ?? 0)]);
        $label = get_string('cm2confirmdelete', 'local_flwcupkp');
        $class = 'btn btn-danger';
    } else {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirmsave']);
        foreach (($preview['proposed'] ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $html .= html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => $key,
                    'value' => (string)$value,
                ]);
            }
        }
        $label = get_string('cm2confirmrelationshipchange', 'local_flwcupkp');
        $class = 'btn btn-primary';
    }
    $html .= html_writer::tag('p', get_string('cm2confirmnote', 'local_flwcupkp'));
    $html .= html_writer::tag('button', $label, ['type' => 'submit', 'class' => $class]);
    $html .= html_writer::end_tag('form');
    return $html;
}

/**
 * Select form row.
 */
function local_flwcupkp_cm2_select_row(string $name, string $label, array $options, $selected = ''): void {
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($label, 'id_' . $name);
    echo html_writer::select($options, $name, $selected, false, ['id' => 'id_' . $name, 'required' => 'required']);
    echo html_writer::end_tag('div');
}

/**
 * Input form row.
 */
function local_flwcupkp_cm2_input_row(string $name, string $label, string $value): void {
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
function local_flwcupkp_cm2_entity_options(string $type, int $frameworkid): array {
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
function local_flwcupkp_cm2_target_options(int $frameworkid): array {
    $options = [];
    foreach (['competency', 'up', 'kp'] as $type) {
        foreach (local_flwcupkp_cm2_entity_options($type, $frameworkid) as $id => $label) {
            $stringkey = $type === 'up' ? 'usepoint' : ($type === 'kp' ? 'knowledgepoint' : 'competency');
            $options[$type . ':' . $id] = get_string($stringkey, 'local_flwcupkp') . ': ' . $label;
        }
    }
    return $options;
}

/**
 * Defaults for mapping fields.
 */
function local_flwcupkp_cm2_default_mapping_value(string $field): string {
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

/**
 * Endpoint for table display.
 */
function local_flwcupkp_cm2_endpoint_for_record(string $type, stdClass $record): array {
    if ($type === 'comp_up') {
        return ['source_type' => 'competency', 'source_id' => (int)$record->competencyid,
            'target_type' => 'up', 'target_id' => (int)$record->upid];
    }
    if ($type === 'up_kp') {
        return ['source_type' => 'up', 'source_id' => (int)$record->upid,
            'target_type' => 'kp', 'target_id' => (int)$record->kpid];
    }
    if ($type === 'kp_prereq') {
        return ['source_type' => 'kp', 'source_id' => (int)$record->kpid,
            'target_type' => 'kp', 'target_id' => (int)$record->prereqkpid];
    }
    return ['source_type' => 'object', 'source_id' => (int)$record->objectid,
        'target_type' => (string)$record->targettype, 'target_id' => (int)$record->targetid];
}

/**
 * Entity detail link.
 */
function local_flwcupkp_cm2_entity_link(string $type, int $id, int $courseid, string $unitcode): string {
    $params = ['type' => $type, 'id' => $id];
    if ($courseid > 0) {
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $params['unitcode'] = $unitcode;
    }
    return html_writer::link(new moodle_url('/local/flwcupkp/entity.php', $params),
        s(local_flwcupkp_cm2_node_label($type, $id)));
}

/**
 * Human node label.
 */
function local_flwcupkp_cm2_node_label(string $type, int $id): string {
    global $DB;

    $tables = [
        'competency' => 'flwcupkp_comp',
        'up' => 'flwcupkp_up',
        'kp' => 'flwcupkp_kp',
        'object' => 'flwcupkp_object',
        'framework' => 'flwcupkp_framework',
    ];
    if (empty($tables[$type]) || $id <= 0) {
        return $type . ':' . $id;
    }
    $record = $DB->get_record($tables[$type], ['id' => $id], '*', IGNORE_MISSING);
    if (!$record) {
        return $type . ':' . $id;
    }
    return trim($type . ' ' . ($record->externalid ?? '') . ' - ' . ($record->title ?? ($record->name ?? '')));
}

/**
 * Standard CM2 panel.
 */
function local_flwcupkp_cm2_panel(string $heading, string $intro, string $body): string {
    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm2-panel']);
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    $html .= html_writer::tag('h3', s($heading));
    if ($intro !== '') {
        $html .= html_writer::tag('p', s($intro));
    }
    $html .= html_writer::end_tag('div');
    $html .= $body;
    $html .= html_writer::end_tag('section');
    return $html;
}

/**
 * Badge.
 */
function local_flwcupkp_cm2_badge(string $status): string {
    $status = trim($status) !== '' ? $status : 'unknown';
    $class = 'local-flwcupkp-foundation-badge local-flwcupkp-foundation-badge-' .
        clean_param(strtolower($status), PARAM_ALPHANUMEXT);
    return html_writer::tag('span', s(local_flwcupkp_cm2_human($status)), ['class' => $class]);
}

/**
 * Human-readable label.
 */
function local_flwcupkp_cm2_human(string $label): string {
    return ucwords(str_replace(['_', '-'], ' ', strtolower(trim($label))));
}

<?php
// Program 3 Gate CM1 selected C-UP-KP entity detail page.

require_once(__DIR__ . '/../../config.php');

$type = required_param('type', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
$permissions = \local_flwcupkp\local\core_curriculum_manager::permission_matrix($context);
if (empty($permissions['view'])) {
    require_capability('local/flwcupkp:viewreports', $context);
}

$url = local_flwcupkp_cm1_entity_url($type, $id, $courseid, $unitcode);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    require_capability('local/flwcupkp:manageframeworks', $context);
    $action = required_param('action', PARAM_ALPHANUMEXT);
    if ($action === 'transitionstatus') {
        $newstatus = required_param('newstatus', PARAM_ALPHANUMEXT);
        if ($newstatus === 'deprecated' && optional_param('impactack', 0, PARAM_BOOL) !== 1) {
            throw new invalid_parameter_exception('CM2 impact preview acknowledgement is required before deprecation.');
        }
        \local_flwcupkp\local\curriculum_manager::transition_entity_status($type, $id, $newstatus);
        redirect(new moodle_url('/local/flwcupkp/entity.php', [
            'type' => $type,
            'id' => $id,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'status' => 'workflowupdated',
        ]));
    }
    throw new invalid_parameter_exception('Unknown CM1 entity action.');
}

$detail = \local_flwcupkp\local\core_curriculum_manager::entity_detail($type, $id, $courseid, $unitcode, 50);
$impact = \local_flwcupkp\local\relationship_where_used_manager::where_used_impact($type, $id, $courseid, $unitcode, 50);
$record = $detail['record'];
$title = local_flwcupkp_cm1_entity_title($detail);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading(get_string('curriculumentitydetail', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('curriculumentitydetail', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm1-shell']);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/curriculum.php', [
    'frameworkid' => $detail['frameworkid'],
    'unitcode' => $unitcode,
    'entitytype' => $detail['entity_type'],
]), get_string('backtocurriculum', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/foundation.php', [
    'frameworkid' => $detail['frameworkid'],
    'unitcode' => $unitcode,
]), get_string('foundationinspector', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
if (!empty($permissions['edit_prepublication_rows'])) {
    echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/edit_entity.php', [
        'type' => $detail['entity_type'],
        'id' => $record->id,
    ]), get_string('edit'), ['class' => 'btn btn-primary']);
}
echo html_writer::end_tag('div');

echo html_writer::tag('p', get_string('curriculumentitydetailintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm1-intro',
]);

echo local_flwcupkp_cm1_identity_cards($detail);
echo local_flwcupkp_cm2_entity_impact_panel($impact);
if (!empty($permissions['review'])) {
    echo local_flwcupkp_cm1_workflow_panel($detail, $url);
}
echo local_flwcupkp_cm1_definition_panel($detail);
echo local_flwcupkp_cm1_edges_panel(get_string('relationships', 'local_flwcupkp'),
    $detail['relationships']['direct_edges']);
echo local_flwcupkp_cm1_edges_panel(get_string('prerequisites', 'local_flwcupkp'),
    $detail['prerequisites']['edges'] ?? []);
echo local_flwcupkp_cm1_edges_panel(get_string('whereused', 'local_flwcupkp'),
    $detail['relationships']['where_used']['edges'] ?? []);
echo local_flwcupkp_cm1_content_panel($detail['content_usage']);
echo local_flwcupkp_cm1_evidence_panel($detail['evidence_coverage']);
echo local_flwcupkp_cm1_validation_panel($detail['validation']);
echo local_flwcupkp_cm1_history_panel($detail['history']);

echo html_writer::end_tag('div');
echo $OUTPUT->footer();

/**
 * Stable entity page URL.
 */
function local_flwcupkp_cm1_entity_url(string $type, int $id, int $courseid, string $unitcode): moodle_url {
    $params = ['type' => $type, 'id' => $id];
    if ($courseid > 0) {
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $params['unitcode'] = $unitcode;
    }
    return new moodle_url('/local/flwcupkp/entity.php', $params);
}

/**
 * Page title.
 */
function local_flwcupkp_cm1_entity_title(array $detail): string {
    $record = $detail['record'];
    $label = $record->externalid ?? $record->title ?? $record->name ?? '';
    return get_string('curriculumentitydetail', 'local_flwcupkp') . ': ' . $label;
}

/**
 * Top identity cards.
 */
function local_flwcupkp_cm1_identity_cards(array $detail): string {
    $record = $detail['record'];
    $coverage = $detail['evidence_coverage'];
    $cards = [
        [
            'label' => get_string('entitytype', 'local_flwcupkp'),
            'value' => local_flwcupkp_cm1_entity_label($detail['entity_type']),
            'detail' => $detail['table'],
            'state' => 'muted',
        ],
        [
            'label' => get_string('stablecode', 'local_flwcupkp'),
            'value' => (string)($record->externalid ?? ''),
            'detail' => get_string('cm1stablecodedetail', 'local_flwcupkp'),
            'state' => 'ok',
        ],
        [
            'label' => get_string('revisionversion', 'local_flwcupkp'),
            'value' => (string)($record->version ?? '-'),
            'detail' => get_string('status') . ': ' . (string)($record->status ?? '-'),
            'state' => 'pending',
        ],
        [
            'label' => get_string('evidencecoverage', 'local_flwcupkp'),
            'value' => (string)($coverage['evidence_rows'] ?? 0),
            'detail' => get_string('learnerstatereferences', 'local_flwcupkp') . ': ' .
                (int)($coverage['learner_state_rows'] ?? 0),
            'state' => 'muted',
        ],
    ];

    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-cardgrid']);
    foreach ($cards as $card) {
        $html .= html_writer::tag('article',
            html_writer::tag('span', s($card['label'])) .
            html_writer::tag('strong', s($card['value'])) .
            html_writer::tag('em', s($card['detail'])),
            ['class' => 'local-flwcupkp-foundation-card local-flwcupkp-health-' . $card['state']]
        );
    }
    $html .= html_writer::end_tag('section');
    return $html;
}

/**
 * Workflow transition buttons.
 */
function local_flwcupkp_cm1_workflow_panel(array $detail, moodle_url $url): string {
    if (!$detail['workflow']) {
        return local_flwcupkp_cm1_panel(get_string('workflow', 'local_flwcupkp'),
            get_string('cm1noworkflowactions', 'local_flwcupkp'), '');
    }

    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm1-workflow']);
    foreach ($detail['workflow'] as $action) {
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url,
            'class' => 'local-flwcupkp-inlineform',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'transitionstatus']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'newstatus', 'value' => $action['status']]);
        if ($action['status'] === 'deprecated') {
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'impactack', 'value' => 1]);
        }
        $html .= html_writer::tag('button', s($action['label']), ['type' => 'submit', 'class' => 'btn btn-secondary']);
        $html .= html_writer::end_tag('form');
    }
    $html .= html_writer::end_tag('div');
    return local_flwcupkp_cm1_panel(get_string('workflow', 'local_flwcupkp'),
        get_string('cm1workflowintro', 'local_flwcupkp') . ' ' .
            get_string('cm2impactbeforedeprecation', 'local_flwcupkp'), $html);
}

/**
 * CM2 where-used impact panel for entity detail.
 */
function local_flwcupkp_cm2_entity_impact_panel(array $impact): string {
    $counts = $impact['counts'] ?? [];
    $cards = '';
    foreach ($counts as $key => $value) {
        $cards .= html_writer::tag('article',
            html_writer::tag('span', s(local_flwcupkp_cm2_entity_human((string)$key))) .
            html_writer::tag('strong', s((string)$value)),
            ['class' => 'local-flwcupkp-cm2-impact-card']
        );
    }

    $objects = new html_table();
    $objects->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $objects->head = [
        get_string('learningobject', 'local_flwcupkp'),
        get_string('field_unitcode', 'local_flwcupkp'),
        get_string('field_lesson', 'local_flwcupkp'),
        get_string('field_cmid', 'local_flwcupkp'),
        get_string('cm2questionrefs', 'local_flwcupkp'),
    ];
    foreach (($impact['objects'] ?? []) as $object) {
        $objects->data[] = [
            html_writer::tag('code', s($object['externalid'] ?? '')) .
                html_writer::tag('div', s($object['title'] ?? ''), ['class' => 'local-flwcupkp-muted']),
            s($object['unitcode'] ?? ''),
            s($object['lesson'] ?? ''),
            !empty($object['cmid']) ? 'CMID ' . (int)$object['cmid'] : '',
            s($object['questionid'] ?? ''),
        ];
    }
    if (!$objects->data) {
        $objects->data[] = [get_string('none'), '', '', '', ''];
    }

    $warnings = '';
    foreach (($impact['warnings'] ?? []) as $warning) {
        $warnings .= html_writer::tag('li', s($warning));
    }
    if ($warnings !== '') {
        $warnings = html_writer::tag('ul', $warnings, ['class' => 'local-flwcupkp-cm2-validation']);
    }

    $body = html_writer::tag('div', $cards, ['class' => 'local-flwcupkp-cm2-impact-grid']) .
        $warnings .
        html_writer::tag('p', get_string('cm2cachedcounts', 'local_flwcupkp') . ': ' .
            s($impact['aggregation']['mode'] ?? ''), ['class' => 'local-flwcupkp-muted']) .
        html_writer::table($objects);

    return local_flwcupkp_cm1_panel(get_string('cm2whereusedimpact', 'local_flwcupkp'),
        get_string('cm2whereusedimpactintro', 'local_flwcupkp'), $body);
}

/**
 * Definition table.
 */
function local_flwcupkp_cm1_definition_panel(array $detail): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [get_string('field', 'local_flwcupkp'), get_string('value', 'local_flwcupkp')];
    foreach ($detail['definition'] as $field => $value) {
        $table->data[] = [
            s(get_string('field_' . $field, 'local_flwcupkp')),
            html_writer::tag('div', s(local_flwcupkp_cm1_value($value)), ['class' => 'local-flwcupkp-cm1-longtext']),
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('none'), ''];
    }
    return local_flwcupkp_cm1_panel(get_string('definition', 'local_flwcupkp'), '', html_writer::table($table));
}

/**
 * Edge table panel.
 */
function local_flwcupkp_cm1_edges_panel(string $heading, array $edges): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('relationship', 'local_flwcupkp'),
        get_string('source', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('type', 'local_flwcupkp'),
        get_string('foundationdetails', 'local_flwcupkp'),
    ];
    foreach ($edges as $edge) {
        $table->data[] = [
            local_flwcupkp_cm1_badge((string)($edge['relation'] ?? '')),
            s(local_flwcupkp_cm1_node_label((string)($edge['source_type'] ?? ''), (int)($edge['source_id'] ?? 0))),
            s(local_flwcupkp_cm1_node_label((string)($edge['target_type'] ?? ''), (int)($edge['target_id'] ?? 0))),
            s((string)($edge['mappingtype'] ?? '')),
            !empty($edge['hard_prerequisite']) ? get_string('hardprerequisite', 'local_flwcupkp') : '',
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('none'), '', '', '', ''];
    }
    return local_flwcupkp_cm1_panel($heading, '', html_writer::table($table));
}

/**
 * Content usage table.
 */
function local_flwcupkp_cm1_content_panel(array $rows): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('learningobject', 'local_flwcupkp'),
        get_string('unit', 'local_flwcupkp'),
        get_string('lesson', 'local_flwcupkp'),
        get_string('activity', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('role', 'local_flwcupkp'),
    ];
    foreach ($rows as $row) {
        $activity = !empty($row->cmid) ? 'CMID ' . (int)$row->cmid : get_string('notlinked', 'local_flwcupkp');
        $table->data[] = [
            html_writer::tag('code', s((string)$row->externalid)) .
                html_writer::tag('div', s((string)$row->title), ['class' => 'local-flwcupkp-muted']),
            s((string)$row->unitcode),
            s((string)$row->lesson),
            s($activity),
            s((string)$row->targettype . ':' . (string)$row->targetid),
            s((string)$row->maprole),
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('nofoundationmappings', 'local_flwcupkp'), '', '', '', '', ''];
    }
    return local_flwcupkp_cm1_panel(get_string('contentusage', 'local_flwcupkp'), '', html_writer::table($table));
}

/**
 * Evidence coverage.
 */
function local_flwcupkp_cm1_evidence_panel(array $coverage): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [get_string('metric', 'local_flwcupkp'), get_string('value', 'local_flwcupkp')];
    $latest = !empty($coverage['latest_evidence']) ? userdate((int)$coverage['latest_evidence']) : get_string('none');
    $table->data = [
        [get_string('evidencerows', 'local_flwcupkp'), (int)($coverage['evidence_rows'] ?? 0)],
        [get_string('learnerstatereferences', 'local_flwcupkp'), (int)($coverage['learner_state_rows'] ?? 0)],
        [get_string('latestevidence', 'local_flwcupkp'), s($latest)],
    ];
    return local_flwcupkp_cm1_panel(get_string('evidencecoverage', 'local_flwcupkp'), '', html_writer::table($table));
}

/**
 * Validation checks.
 */
function local_flwcupkp_cm1_validation_panel(array $validation): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('check', 'local_flwcupkp'),
        get_string('status'),
        get_string('foundationdetails', 'local_flwcupkp'),
    ];
    foreach ($validation['checks'] as $name => $check) {
        $details = array_merge($check['errors'] ?? [], $check['warnings'] ?? []);
        $table->data[] = [
            s(local_flwcupkp_cm1_human($name)),
            local_flwcupkp_cm1_badge(!empty($check['valid']) ? 'valid' : 'invalid'),
            s(implode('; ', $details)),
        ];
    }
    return local_flwcupkp_cm1_panel(get_string('validation', 'local_flwcupkp'), '', html_writer::table($table));
}

/**
 * Audit history.
 */
function local_flwcupkp_cm1_history_panel(array $rows): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('time', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
        get_string('user'),
        get_string('foundationdetails', 'local_flwcupkp'),
    ];
    foreach ($rows as $row) {
        $table->data[] = [
            userdate((int)$row->timecreated),
            s((string)$row->action),
            s((string)$row->userid),
            html_writer::tag('pre', s((string)$row->detailsjson), ['class' => 'local-flwcupkp-cm1-history-json']),
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('nohistory', 'local_flwcupkp'), '', '', ''];
    }
    return local_flwcupkp_cm1_panel(get_string('history', 'local_flwcupkp'), '', html_writer::table($table));
}

/**
 * Standard CM1 panel.
 */
function local_flwcupkp_cm1_panel(string $heading, string $intro, string $body): string {
    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel']);
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
function local_flwcupkp_cm1_badge(string $status): string {
    $status = trim($status) !== '' ? $status : 'unknown';
    $class = 'local-flwcupkp-foundation-badge local-flwcupkp-foundation-badge-' .
        clean_param(strtolower($status), PARAM_ALPHANUMEXT);
    return html_writer::tag('span', s(local_flwcupkp_cm1_human($status)), ['class' => $class]);
}

/**
 * Entity label.
 */
function local_flwcupkp_cm1_entity_label(string $type): string {
    if ($type === 'competency') {
        return get_string('competency', 'local_flwcupkp');
    }
    if ($type === 'up') {
        return get_string('usepoint', 'local_flwcupkp');
    }
    if ($type === 'kp') {
        return get_string('knowledgepoint', 'local_flwcupkp');
    }
    if ($type === 'object') {
        return get_string('learningobject', 'local_flwcupkp');
    }
    return get_string('framework', 'local_flwcupkp');
}

/**
 * Human graph node label.
 */
function local_flwcupkp_cm1_node_label(string $type, int $id): string {
    global $DB;

    if ($id <= 0 || $type === '') {
        return '';
    }
    if ($type === 'object') {
        $table = 'flwcupkp_object';
    } else if ($type === 'competency') {
        $table = 'flwcupkp_comp';
    } else if ($type === 'up') {
        $table = 'flwcupkp_up';
    } else if ($type === 'kp') {
        $table = 'flwcupkp_kp';
    } else {
        return $type . ':' . $id;
    }
    $record = $DB->get_record($table, ['id' => $id], 'id, externalid, title', IGNORE_MISSING);
    if (!$record) {
        return $type . ':' . $id;
    }
    return $type . ' ' . $record->externalid . ' - ' . $record->title;
}

/**
 * Display any scalar/JSON-ish value.
 */
function local_flwcupkp_cm1_value($value): string {
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    return (string)$value;
}

/**
 * Human-readable machine label.
 */
function local_flwcupkp_cm1_human(string $label): string {
    return ucwords(str_replace(['_', '-'], ' ', strtolower(trim($label))));
}

/**
 * Human-readable CM2 label.
 */
function local_flwcupkp_cm2_entity_human(string $label): string {
    return ucwords(str_replace(['_', '-'], ' ', strtolower(trim($label))));
}

<?php
// Read-only Foundation Inspector for Program 3 Gate C5B.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$limit = local_flwcupkp_foundation_limit(optional_param('limit', 100, PARAM_INT));

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:manageframeworks', $context);

$url = local_flwcupkp_foundation_url($courseid, $unitcode, $frameworkid, $limit);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('foundationinspector', 'local_flwcupkp'));
$PAGE->set_heading(get_string('foundationinspector', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\foundation_v1_contract::foundation_status($courseid, $unitcode, $frameworkid, $limit);
$contract = $status['contract'];
$courseoptions = local_flwcupkp_foundation_course_options();
$unitoptions = ['' => get_string('allunits', 'local_flwcupkp')] +
    \local_flwcupkp\local\curriculum_manager::unit_options();
$frameworkoptions = [0 => get_string('allframeworks', 'local_flwcupkp')] +
    \local_flwcupkp\local\curriculum_manager::framework_options();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('foundationinspector', 'local_flwcupkp'));

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-shell']);
echo html_writer::tag('p', get_string('foundationintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-foundation-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/curriculum.php', [
    'frameworkid' => $frameworkid,
    'unitcode' => $unitcode,
]), get_string('curriculummanager', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/trace.php', [
    'frameworkid' => $frameworkid,
    'unitcode' => $unitcode,
]), get_string('traceabilityreport', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo local_flwcupkp_foundation_filter_form($url, $courseoptions, $courseid, $unitoptions, $unitcode,
    $frameworkoptions, $frameworkid, $limit);
echo local_flwcupkp_foundation_status_cards($status);
echo local_flwcupkp_foundation_dependency_panel($status['checks']);
echo local_flwcupkp_foundation_version_panel($status['versions']);
echo local_flwcupkp_foundation_migration_panel($status['migration_readiness']);
echo local_flwcupkp_foundation_findings_panel($status['findings']);
echo local_flwcupkp_foundation_entity_panel($frameworkid, $courseid, $unitcode, $limit);
echo local_flwcupkp_foundation_relation_panel($frameworkid, $limit);
echo local_flwcupkp_foundation_mapping_panel($frameworkid, $courseid, $unitcode, $limit);
echo local_flwcupkp_foundation_implementation_panel($status['checks']['authoritative_implementations']);
echo local_flwcupkp_foundation_api_panel($contract['adaptive_api_contract']);
echo local_flwcupkp_foundation_contract_json_panel($contract);

echo html_writer::end_tag('div');
echo $OUTPUT->footer();

/**
 * Stable page URL.
 */
function local_flwcupkp_foundation_url(int $courseid, string $unitcode, int $frameworkid, int $limit): moodle_url {
    $params = ['limit' => $limit];
    if ($courseid > 0) {
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $params['unitcode'] = $unitcode;
    }
    if ($frameworkid > 0) {
        $params['frameworkid'] = $frameworkid;
    }
    return new moodle_url('/local/flwcupkp/foundation.php', $params);
}

/**
 * Normalize row limit.
 */
function local_flwcupkp_foundation_limit(int $limit): int {
    $allowed = [50, 100, 200, 500];
    return in_array($limit, $allowed, true) ? $limit : 100;
}

/**
 * Course options from imported learning objects.
 */
function local_flwcupkp_foundation_course_options(): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT DISTINCT c.id, c.fullname, c.shortname
           FROM {flwcupkp_object} o
           JOIN {course} c ON c.id = o.courseid
          WHERE o.courseid IS NOT NULL
            AND o.courseid > 0
       ORDER BY c.fullname ASC, c.shortname ASC"
    );

    $options = [0 => get_string('allcourses', 'local_flwcupkp')];
    foreach ($records as $record) {
        $label = format_string($record->fullname);
        if ((string)$record->shortname !== '') {
            $label .= ' (' . format_string($record->shortname) . ')';
        }
        $options[(int)$record->id] = $label;
    }
    return $options;
}

/**
 * Render GET filters.
 */
function local_flwcupkp_foundation_filter_form(moodle_url $url, array $courseoptions, int $courseid,
        array $unitoptions, string $unitcode, array $frameworkoptions, int $frameworkid, int $limit): string {
    $limitoptions = [50 => '50', 100 => '100', 200 => '200', 500 => '500'];
    $html = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $url->out_omit_querystring(),
        'class' => 'local-flwcupkp-foundation-filters',
    ]);
    $html .= local_flwcupkp_foundation_select('courseid', get_string('course'), $courseoptions, $courseid);
    $html .= local_flwcupkp_foundation_select('unitcode', get_string('field_unitcode', 'local_flwcupkp'),
        $unitoptions, $unitcode);
    $html .= local_flwcupkp_foundation_select('frameworkid', get_string('framework', 'local_flwcupkp'),
        $frameworkoptions, $frameworkid);
    $html .= local_flwcupkp_foundation_select('limit', get_string('rows', 'local_flwcupkp'), $limitoptions, $limit);
    $html .= html_writer::tag('button', get_string('applyfilters', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    $html .= html_writer::link(new moodle_url('/local/flwcupkp/foundation.php'), get_string('reset'), [
        'class' => 'btn btn-secondary',
    ]);
    $html .= html_writer::end_tag('form');
    return $html;
}

/**
 * Render one select filter.
 */
function local_flwcupkp_foundation_select(string $name, string $label, array $options, $selected): string {
    $id = 'local-flwcupkp-foundation-' . $name;
    $html = html_writer::start_tag('label', ['for' => $id, 'class' => 'local-flwcupkp-filter']);
    $html .= html_writer::tag('span', s($label));
    $html .= html_writer::select($options, $name, $selected, false, [
        'id' => $id,
        'class' => 'custom-select',
    ]);
    $html .= html_writer::end_tag('label');
    return $html;
}

/**
 * Render top status cards.
 */
function local_flwcupkp_foundation_status_cards(array $status): string {
    $blocked = (int)($status['unresolved_blocker_high_count'] ?? 0);
    $history = (string)($status['checks']['history_v1']['status'] ?? 'unknown');
    $next = 'CM1';
    $cards = [
        [
            'label' => get_string('foundationstatus', 'local_flwcupkp'),
            'value' => strtoupper((string)$status['status']),
            'detail' => (string)($status['versions']['foundation_contract_version'] ?? ''),
            'state' => (string)$status['status'] === 'frozen' ? 'ok' : 'attention',
        ],
        [
            'label' => get_string('foundationblockerhigh', 'local_flwcupkp'),
            'value' => (string)$blocked,
            'detail' => get_string('foundationblockerhighdetail', 'local_flwcupkp'),
            'state' => $blocked === 0 ? 'ok' : 'attention',
        ],
        [
            'label' => get_string('foundationhistoryrule', 'local_flwcupkp'),
            'value' => strtoupper($history),
            'detail' => (string)($status['normal_source_rule'] ?? ''),
            'state' => $history === 'ready' ? 'ok' : 'pending',
        ],
        [
            'label' => get_string('foundationnextgate', 'local_flwcupkp'),
            'value' => $next,
            'detail' => get_string('foundationnextgatedetail', 'local_flwcupkp'),
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
 * Dependency checks panel.
 */
function local_flwcupkp_foundation_dependency_panel(array $checks): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('foundationcomponent', 'local_flwcupkp'),
        get_string('status'),
        get_string('version', 'local_flwcupkp'),
        get_string('foundationallowedstatuses', 'local_flwcupkp'),
        get_string('foundationfindings', 'local_flwcupkp'),
    ];

    foreach ($checks as $key => $check) {
        if ($key === 'authoritative_implementations') {
            continue;
        }
        $version = (string)($check['contract']['version'] ?? $check['requiredcontract'] ?? '');
        $table->data[] = [
            s(local_flwcupkp_foundation_human($key)),
            local_flwcupkp_foundation_status_badge((string)($check['status'] ?? 'valid')),
            s($version),
            s(implode(', ', $check['allowed_statuses'] ?? [])),
            s((string)count($check['findings'] ?? [])),
        ];
    }

    return local_flwcupkp_foundation_panel(get_string('foundationdependencies', 'local_flwcupkp'),
        get_string('foundationdependenciesintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * Version identifiers panel.
 */
function local_flwcupkp_foundation_version_panel(array $versions): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [get_string('identifier', 'local_flwcupkp'), get_string('value', 'local_flwcupkp')];
    foreach ($versions as $key => $value) {
        $table->data[] = [html_writer::tag('code', s($key)), html_writer::tag('code', s((string)$value))];
    }
    return local_flwcupkp_foundation_panel(get_string('foundationversions', 'local_flwcupkp'), '',
        html_writer::table($table));
}

/**
 * Migration readiness panel.
 */
function local_flwcupkp_foundation_migration_panel(array $readiness): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('check', 'local_flwcupkp'),
        get_string('status'),
        get_string('foundationdetails', 'local_flwcupkp'),
    ];
    foreach (($readiness['checks'] ?? []) as $check) {
        $details = [];
        foreach ($check as $key => $value) {
            if (in_array($key, ['code', 'status'], true)) {
                continue;
            }
            if (is_scalar($value)) {
                $details[] = local_flwcupkp_foundation_human($key) . ': ' . (string)$value;
            }
        }
        $table->data[] = [
            s(local_flwcupkp_foundation_human((string)($check['code'] ?? ''))),
            local_flwcupkp_foundation_status_badge((string)($check['status'] ?? 'unknown')),
            s(implode('; ', $details)),
        ];
    }
    return local_flwcupkp_foundation_panel(get_string('foundationmigration', 'local_flwcupkp'),
        get_string('foundationmigrationintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * Findings panel.
 */
function local_flwcupkp_foundation_findings_panel(array $findings): string {
    if (!$findings) {
        return local_flwcupkp_foundation_panel(get_string('foundationfindings', 'local_flwcupkp'), '',
            html_writer::tag('p', get_string('foundationnofindings', 'local_flwcupkp'), [
                'class' => 'local-flwcupkp-muted',
            ]));
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('foundationseverity', 'local_flwcupkp'),
        get_string('source', 'local_flwcupkp'),
        get_string('code', 'local_flwcupkp'),
        get_string('message', 'local_flwcupkp'),
    ];
    foreach ($findings as $finding) {
        $table->data[] = [
            local_flwcupkp_foundation_severity_badge((string)($finding['severity'] ?? 'INFO')),
            s((string)($finding['source'] ?? '')),
            html_writer::tag('code', s((string)($finding['code'] ?? ''))),
            s((string)($finding['message'] ?? '')),
        ];
    }
    return local_flwcupkp_foundation_panel(get_string('foundationfindings', 'local_flwcupkp'),
        get_string('foundationfindingsintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * Render C/UP/KP entity rows.
 */
function local_flwcupkp_foundation_entity_panel(int $frameworkid, int $courseid, string $unitcode, int $limit): string {
    $html = '';
    foreach (['competency', 'up', 'kp'] as $type) {
        $rows = local_flwcupkp_foundation_entity_rows($type, $frameworkid, $limit);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
        $table->head = [
            get_string('code', 'local_flwcupkp'),
            get_string('title', 'local_flwcupkp'),
            get_string('field_description', 'local_flwcupkp'),
            get_string('version', 'local_flwcupkp'),
            get_string('status'),
            get_string('wheremapped', 'local_flwcupkp'),
        ];
        foreach ($rows as $row) {
            $table->data[] = [
                html_writer::tag('code', s((string)$row->externalid)),
                s((string)$row->title),
                s(local_flwcupkp_foundation_entity_description($type, $row)),
                s((string)($row->version ?? '')),
                local_flwcupkp_foundation_status_badge((string)($row->status ?? '')),
                s(local_flwcupkp_foundation_where_mapped($type, (int)$row->id, $courseid, $unitcode)),
            ];
        }
        if (!$table->data) {
            $table->data[] = [get_string('nofoundationentities', 'local_flwcupkp'), '', '', '', '', ''];
        }
        $html .= html_writer::tag('h4', s(local_flwcupkp_foundation_entity_heading($type)));
        $html .= html_writer::table($table);
    }

    return local_flwcupkp_foundation_panel(get_string('foundationentities', 'local_flwcupkp'),
        get_string('foundationentitiesintro', 'local_flwcupkp'), $html);
}

/**
 * Entity rows for a type.
 */
function local_flwcupkp_foundation_entity_rows(string $type, int $frameworkid, int $limit): array {
    global $DB;

    $table = $type === 'competency' ? 'flwcupkp_comp' : ($type === 'up' ? 'flwcupkp_up' : 'flwcupkp_kp');
    $where = '1=1';
    $params = [];
    if ($frameworkid > 0) {
        $where = 'frameworkid = :frameworkid';
        $params['frameworkid'] = $frameworkid;
    }
    return $DB->get_records_select($table, $where, $params, 'externalid ASC', '*', 0, $limit);
}

/**
 * Description field for one entity row.
 */
function local_flwcupkp_foundation_entity_description(string $type, \stdClass $row): string {
    if ($type === 'competency') {
        return trim((string)($row->cando ?? '')) !== '' ? (string)$row->cando : (string)($row->description ?? '');
    }
    if ($type === 'up') {
        foreach (['actionstatement', 'observableaction', 'intention', 'successcriteria'] as $field) {
            if (trim((string)($row->$field ?? '')) !== '') {
                return (string)$row->$field;
            }
        }
        return '';
    }
    foreach (['description', 'formtext', 'meaningfunction', 'usageconstraints'] as $field) {
        if (trim((string)($row->$field ?? '')) !== '') {
            return (string)$row->$field;
        }
    }
    return '';
}

/**
 * Entity heading.
 */
function local_flwcupkp_foundation_entity_heading(string $type): string {
    if ($type === 'competency') {
        return get_string('competencies', 'local_flwcupkp');
    }
    if ($type === 'up') {
        return get_string('usepoints', 'local_flwcupkp');
    }
    return get_string('knowledgepoints', 'local_flwcupkp');
}

/**
 * Mapping count summary for a target.
 */
function local_flwcupkp_foundation_where_mapped(string $targettype, int $targetid, int $courseid, string $unitcode): string {
    global $DB;

    $where = 'om.targettype = :targettype AND om.targetid = :targetid';
    $params = ['targettype' => $targettype, 'targetid' => $targetid];
    if ($courseid > 0) {
        $where .= ' AND o.courseid = :courseid';
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $where .= ' AND o.unitcode = :unitcode';
        $params['unitcode'] = $unitcode;
    }
    $records = $DB->get_records_sql(
        "SELECT om.id, o.externalid, o.unitcode, o.lesson
           FROM {flwcupkp_object_map} om
           JOIN {flwcupkp_object} o ON o.id = om.objectid
          WHERE {$where}
       ORDER BY o.unitcode ASC, o.lesson ASC, o.externalid ASC",
        $params,
        0,
        4
    );
    $count = $DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {flwcupkp_object_map} om
           JOIN {flwcupkp_object} o ON o.id = om.objectid
          WHERE {$where}",
        $params
    );
    if ($count <= 0) {
        return get_string('notmapped', 'local_flwcupkp');
    }
    $labels = [];
    foreach ($records as $record) {
        $labels[] = trim((string)$record->unitcode . ' ' . (string)$record->lesson . ' ' . (string)$record->externalid);
    }
    if ($count > count($records)) {
        $labels[] = get_string('foundationmoremappings', 'local_flwcupkp', $count - count($records));
    }
    return implode('; ', $labels);
}

/**
 * Render graph relation rows from the frozen C2 API.
 */
function local_flwcupkp_foundation_relation_panel(int $frameworkid, int $limit): string {
    $edges = \local_flwcupkp\local\relationship_graph_contract::adjacency($frameworkid, ['limit' => $limit]);
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
            local_flwcupkp_foundation_status_badge((string)($edge['relation'] ?? '')),
            s(local_flwcupkp_foundation_node_label((string)$edge['source_type'], (int)$edge['source_id'])),
            s(local_flwcupkp_foundation_node_label((string)$edge['target_type'], (int)$edge['target_id'])),
            s((string)($edge['mappingtype'] ?? '')),
            s(local_flwcupkp_foundation_edge_detail($edge)),
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('nofoundationrelations', 'local_flwcupkp'), '', '', '', ''];
    }

    return local_flwcupkp_foundation_panel(get_string('foundationrelations', 'local_flwcupkp'),
        get_string('foundationrelationsintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * Human graph node label.
 */
function local_flwcupkp_foundation_node_label(string $type, int $id): string {
    global $DB;

    if ($type === 'object') {
        $record = $DB->get_record('flwcupkp_object', ['id' => $id], 'id, externalid, title', IGNORE_MISSING);
    } else {
        try {
            $table = \local_flwcupkp\local\evidence_guard::target_table($type);
        } catch (invalid_parameter_exception $e) {
            return $type . ':' . $id;
        }
        $record = $DB->get_record($table, ['id' => $id], 'id, externalid, title', IGNORE_MISSING);
    }
    if (!$record) {
        return $type . ':' . $id;
    }
    return $type . ' ' . $record->externalid . ' - ' . $record->title;
}

/**
 * Edge detail from raw row data returned by C2.
 */
function local_flwcupkp_foundation_edge_detail(array $edge): string {
    $row = is_array($edge['row'] ?? null) ? $edge['row'] : [];
    $parts = [];
    foreach (['role', 'relationshiptype', 'requirement', 'evidencestrength'] as $field) {
        if (isset($row[$field]) && trim((string)$row[$field]) !== '') {
            $parts[] = local_flwcupkp_foundation_human($field) . ': ' . (string)$row[$field];
        }
    }
    if (!empty($edge['hard_prerequisite'])) {
        $parts[] = get_string('hardprerequisite', 'local_flwcupkp');
    }
    return implode('; ', $parts);
}

/**
 * Render content/evidence mappings.
 */
function local_flwcupkp_foundation_mapping_panel(int $frameworkid, int $courseid, string $unitcode, int $limit): string {
    $rows = local_flwcupkp_foundation_mapping_rows($frameworkid, $courseid, $unitcode, $limit);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('learningobject', 'local_flwcupkp'),
        get_string('activity', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('role', 'local_flwcupkp'),
        get_string('evidencestrength', 'local_flwcupkp'),
        get_string('evidence', 'local_flwcupkp'),
    ];
    foreach ($rows as $row) {
        $activity = get_string('notlinked', 'local_flwcupkp');
        if (!empty($row->cmid) && !empty($row->modname)) {
            $activity = html_writer::link(new moodle_url('/mod/' . $row->modname . '/view.php', ['id' => (int)$row->cmid]),
                'CMID ' . (int)$row->cmid);
        } else if (!empty($row->cmid)) {
            $activity = 'CMID ' . (int)$row->cmid;
        }
        $table->data[] = [
            html_writer::tag('code', s((string)$row->externalid)) .
                html_writer::tag('div', s((string)$row->title), ['class' => 'local-flwcupkp-muted']) .
                html_writer::tag('small', s(trim((string)$row->unitcode . ' ' . (string)$row->lesson . ' ' .
                    (string)$row->objecttype)), ['class' => 'local-flwcupkp-muted']),
            $activity,
            s(local_flwcupkp_foundation_node_label((string)$row->targettype, (int)$row->targetid)),
            s((string)$row->maprole),
            s((string)$row->mapevidencestrength),
            s((string)local_flwcupkp_foundation_evidence_count((int)$row->objectid,
                (string)$row->targettype, (int)$row->targetid)),
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('nofoundationmappings', 'local_flwcupkp'), '', '', '', '', ''];
    }

    return local_flwcupkp_foundation_panel(get_string('foundationmappings', 'local_flwcupkp'),
        get_string('foundationmappingsintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * Object mapping rows for selected scope.
 */
function local_flwcupkp_foundation_mapping_rows(int $frameworkid, int $courseid, string $unitcode, int $limit): array {
    global $DB;

    $where = [];
    $params = [];
    if ($frameworkid > 0) {
        $where[] = 'o.frameworkid = :frameworkid';
        $params['frameworkid'] = $frameworkid;
    }
    if ($courseid > 0) {
        $where[] = 'o.courseid = :courseid';
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $where[] = 'o.unitcode = :unitcode';
        $params['unitcode'] = $unitcode;
    }
    $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    return $DB->get_records_sql(
        "SELECT om.id, om.objectid, om.targettype, om.targetid, om.role AS maprole,
                om.evidencestrength AS mapevidencestrength, o.externalid, o.title, o.courseid,
                o.unitcode, o.lesson, o.objecttype, o.cmid, o.frameworkid, m.name AS modname
           FROM {flwcupkp_object_map} om
           JOIN {flwcupkp_object} o ON o.id = om.objectid
      LEFT JOIN {course_modules} cm ON cm.id = o.cmid
      LEFT JOIN {modules} m ON m.id = cm.module
          {$wheresql}
       ORDER BY o.unitcode ASC, o.lesson ASC, o.externalid ASC, om.targettype ASC, om.targetid ASC",
        $params,
        0,
        $limit
    );
}

/**
 * Evidence count for one object-target mapping.
 */
function local_flwcupkp_foundation_evidence_count(int $objectid, string $targettype, int $targetid): int {
    global $DB;

    return (int)$DB->count_records('flwcupkp_evidence', [
        'objectid' => $objectid,
        'targettype' => $targettype,
        'targetid' => $targetid,
    ]);
}

/**
 * Authoritative implementation panel.
 */
function local_flwcupkp_foundation_implementation_panel(array $implementation): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('foundationimplementationarea', 'local_flwcupkp'),
        get_string('foundationclass', 'local_flwcupkp'),
        get_string('foundationtables', 'local_flwcupkp'),
        get_string('foundationmethods', 'local_flwcupkp'),
        get_string('status'),
    ];
    foreach (($implementation['areas'] ?? []) as $area => $row) {
        $table->data[] = [
            s(local_flwcupkp_foundation_human($area)),
            html_writer::tag('code', s(local_flwcupkp_foundation_short_class((string)$row['class']))),
            local_flwcupkp_foundation_chiplist(array_keys($row['tables'] ?? [])),
            local_flwcupkp_foundation_chiplist(array_keys($row['methods'] ?? [])),
            local_flwcupkp_foundation_status_badge(!empty($row['valid']) ? 'valid' : 'invalid'),
        ];
    }
    return local_flwcupkp_foundation_panel(get_string('foundationimplementations', 'local_flwcupkp'),
        get_string('foundationimplementationsintro', 'local_flwcupkp'), html_writer::table($table));
}

/**
 * API contract panel.
 */
function local_flwcupkp_foundation_api_panel(array $api): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-api-grid']);
    $html .= local_flwcupkp_foundation_api_list(get_string('foundationmayrelyon', 'local_flwcupkp'),
        $api['may_rely_on'] ?? []);
    $html .= local_flwcupkp_foundation_api_list(get_string('foundationallowedapis', 'local_flwcupkp'),
        $api['allowed_read_apis'] ?? []);
    $html .= local_flwcupkp_foundation_api_list(get_string('foundationforbiddenapis', 'local_flwcupkp'),
        $api['forbidden_until_later_gates'] ?? []);
    $html .= html_writer::end_tag('div');

    return local_flwcupkp_foundation_panel(get_string('foundationapis', 'local_flwcupkp'),
        get_string('foundationapisintro', 'local_flwcupkp'), $html);
}

/**
 * One API list.
 */
function local_flwcupkp_foundation_api_list(string $heading, array $items): string {
    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-api-list']);
    $html .= html_writer::tag('h4', s($heading));
    $html .= html_writer::start_tag('ul');
    foreach ($items as $item) {
        $html .= html_writer::tag('li', html_writer::tag('code', s((string)$item)));
    }
    $html .= html_writer::end_tag('ul');
    $html .= html_writer::end_tag('section');
    return $html;
}

/**
 * Raw contract JSON panel.
 */
function local_flwcupkp_foundation_contract_json_panel(array $contract): string {
    $json = json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $html = html_writer::start_tag('details', ['class' => 'local-flwcupkp-detail-panel']);
    $html .= html_writer::tag('summary', get_string('foundationcontractjson', 'local_flwcupkp'));
    $html .= html_writer::tag('pre', s($json), ['class' => 'local-flwcupkp-json-result']);
    $html .= html_writer::end_tag('details');
    return $html;
}

/**
 * Standard page panel.
 */
function local_flwcupkp_foundation_panel(string $heading, string $intro, string $body): string {
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
 * Status badge.
 */
function local_flwcupkp_foundation_status_badge(string $status): string {
    $status = trim($status) !== '' ? $status : 'unknown';
    $class = 'local-flwcupkp-foundation-badge local-flwcupkp-foundation-badge-' .
        clean_param(strtolower($status), PARAM_ALPHANUMEXT);
    return html_writer::tag('span', s(local_flwcupkp_foundation_human($status)), ['class' => $class]);
}

/**
 * Severity badge.
 */
function local_flwcupkp_foundation_severity_badge(string $severity): string {
    $severity = strtoupper(trim($severity) !== '' ? $severity : 'INFO');
    return html_writer::tag('span', s($severity), [
        'class' => 'local-flwcupkp-foundation-severity local-flwcupkp-foundation-severity-' .
            clean_param(strtolower($severity), PARAM_ALPHANUMEXT),
    ]);
}

/**
 * Chip list.
 */
function local_flwcupkp_foundation_chiplist(array $items): string {
    if (!$items) {
        return '-';
    }
    $chips = [];
    foreach ($items as $item) {
        $chips[] = html_writer::tag('code', s((string)$item), ['class' => 'local-flwcupkp-chip']);
    }
    return implode('', $chips);
}

/**
 * Shorten plugin local class names.
 */
function local_flwcupkp_foundation_short_class(string $class): string {
    return str_replace('\\local_flwcupkp\\local\\', '', $class);
}

/**
 * Human-readable machine label.
 */
function local_flwcupkp_foundation_human(string $label): string {
    $label = trim($label);
    if ($label === '') {
        return '';
    }
    return ucwords(str_replace(['_', '-'], ' ', strtolower($label)));
}

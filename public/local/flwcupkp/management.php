<?php
// Program 3 Gate CM4 Management V1 freeze inspector.

require_once(__DIR__ . '/../../config.php');

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$limit = optional_param('limit', 100, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

$baseparams = [];
if ($frameworkid > 0) {
    $baseparams['frameworkid'] = $frameworkid;
}
if ($courseid > 0) {
    $baseparams['courseid'] = $courseid;
}
if ($unitcode !== '') {
    $baseparams['unitcode'] = $unitcode;
}
$baseparams['limit'] = max(1, min(300, $limit));

$url = new moodle_url('/local/flwcupkp/management.php', $baseparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('cm4management', 'local_flwcupkp'));
$PAGE->set_heading(get_string('cm4management', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\management_v1_contract::management_status(
    $courseid,
    $unitcode,
    $frameworkid,
    $limit
);
$snapshot = \local_flwcupkp\local\management_v1_contract::consumer_snapshot(
    $courseid,
    $unitcode,
    $frameworkid,
    $limit
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cm4management', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('cm4managementintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', [
    'frameworkid' => $frameworkid,
    'unitcode' => $unitcode,
]), get_string('curriculummanager', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/mappings.php', [
    'frameworkid' => $frameworkid,
]), get_string('mappingmanager', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/governance.php', $baseparams),
    get_string('cm3governance', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/foundation.php', [
    'frameworkid' => $frameworkid,
    'courseid' => $courseid,
    'unitcode' => $unitcode,
]), get_string('foundationinspector', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/history_evidence.php', [
    'frameworkid' => $frameworkid,
    'courseid' => $courseid,
    'unitcode' => $unitcode,
]), get_string('historyevidenceadapter', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell']);
local_flwcupkp_cm4_render_status($status);
local_flwcupkp_cm4_render_filters($frameworkid, $courseid, $unitcode, $limit);
local_flwcupkp_cm4_render_criteria($status['criteria']);
local_flwcupkp_cm4_render_dependencies($status['dependencies']);
local_flwcupkp_cm4_render_coverage($snapshot['coverage']);
local_flwcupkp_cm4_render_consumer_contract($snapshot);
local_flwcupkp_cm4_render_findings($status['findings']);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render top-level CM4 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_cm4_render_status(array $status): void {
    $summary = $status['criteria_summary'];
    $cards = [
        get_string('cm4status', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm4_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('cm4criteria', 'local_flwcupkp') => [
            'value' => s($summary['passed'] . '/' . $summary['total']),
            'detail' => get_string('cm4criteriadetail', 'local_flwcupkp', (object)[
                'failed' => $summary['failed'],
                'warnings' => $summary['warnings'],
            ]),
        ],
        get_string('cm4historyinput', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm4_badge('History V1'),
            'detail' => $status['contract']['normal_source_rule'] ?? '',
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm4_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('cm4nextgatedetail', 'local_flwcupkp'),
        ],
    ];

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-cardgrid local-flwcupkp-cm4-cardgrid']);
    foreach ($cards as $label => $card) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
        echo html_writer::tag('span', s($label));
        echo html_writer::tag('strong', $card['value']);
        echo html_writer::tag('em', s((string)$card['detail']));
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

/**
 * Render scope filters.
 *
 * @param int $frameworkid
 * @param int $courseid
 * @param string $unitcode
 * @param int $limit
 */
function local_flwcupkp_cm4_render_filters(int $frameworkid, int $courseid, string $unitcode, int $limit): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/management.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters',
    ]);
    echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] + local_flwcupkp_cm4_course_options(),
            'courseid', $courseid, false), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
        html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('limit', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'limit',
            'value' => max(1, min(300, $limit)),
            'min' => 1,
            'max' => 300,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-limit-filter']);
    echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/management.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render CM4 pass criteria.
 *
 * @param array $criteria
 */
function local_flwcupkp_cm4_render_criteria(array $criteria): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm4criteria', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm4criteriaintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    $table = new html_table();
    $table->head = [
        get_string('criterion', 'local_flwcupkp'),
        get_string('status'),
        get_string('detail', 'local_flwcupkp'),
        get_string('evidence', 'local_flwcupkp'),
    ];
    foreach ($criteria as $criterion) {
        $table->data[] = [
            html_writer::tag('strong', s($criterion['code'])),
            local_flwcupkp_cm4_badge($criterion['status']),
            s($criterion['message']),
            local_flwcupkp_cm4_evidence_summary($criterion['evidence'] ?? []),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render dependency status summary.
 *
 * @param array $dependencies
 */
function local_flwcupkp_cm4_render_dependencies(array $dependencies): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm4dependencies', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm4dependenciesintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    $table = new html_table();
    $table->head = [
        get_string('dependency', 'local_flwcupkp'),
        get_string('status'),
        get_string('contract', 'local_flwcupkp'),
        get_string('findings', 'local_flwcupkp'),
    ];
    foreach ($dependencies as $key => $dependency) {
        $table->data[] = [
            s($key),
            local_flwcupkp_cm4_badge($dependency['status'] ?? 'unknown'),
            s((string)($dependency['contract'] ?? '')),
            (int)($dependency['findings'] ?? 0),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render coverage summary from the production snapshot.
 *
 * @param array $coverage
 */
function local_flwcupkp_cm4_render_coverage(array $coverage): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm4coveragesnapshot', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm4coveragesnapshotintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-coverage-grid']);
    foreach ($coverage['categories'] as $category) {
        $percent = (float)($category['percent'] ?? 0);
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-coverage-card']);
        echo html_writer::tag('strong', s($category['label'] ?? $category['key'] ?? ''));
        echo html_writer::tag('span', s($percent . '%'));
        echo html_writer::tag('em', s((string)($category['detail'] ?? '')));
        echo html_writer::tag('div', html_writer::tag('span', '', [
            'style' => 'width:' . max(0, min(100, $percent)) . '%',
        ]), ['class' => 'local-flwcupkp-cm4-meter']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');

    echo html_writer::tag('p', get_string('cm4openfindings', 'local_flwcupkp', (int)($coverage['open_findings'] ?? 0)), [
        'class' => 'local-flwcupkp-muted',
    ]);
    echo html_writer::end_tag('section');
}

/**
 * Render consumer handoff contract.
 *
 * @param array $snapshot
 */
function local_flwcupkp_cm4_render_consumer_contract(array $snapshot): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm4consumercontract', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm4consumercontractintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-contract-grid']);
    echo local_flwcupkp_cm4_list_block(get_string('allowedreadapis', 'local_flwcupkp'),
        $snapshot['allowed_read_apis']);
    echo local_flwcupkp_cm4_list_block(get_string('allowedwritesurfaces', 'local_flwcupkp'),
        $snapshot['allowed_write_surfaces']);
    echo local_flwcupkp_cm4_list_block(get_string('forbiddenuntile1', 'local_flwcupkp'),
        $snapshot['forbidden_until_e1_or_later']);
    echo html_writer::end_tag('div');

    echo html_writer::tag('pre', s(json_encode([
        'contract' => $snapshot['contract'],
        'management_status' => $snapshot['management_status'],
        'next_allowed_gate' => $snapshot['handoff']['next_allowed_gate'],
        'normal_source_rule' => $snapshot['normal_source_rule'],
        'state_changes_allowed' => $snapshot['state_changes_allowed'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)), ['class' => 'local-flwcupkp-cm4-json']);
    echo html_writer::end_tag('section');
}

/**
 * Render findings.
 *
 * @param array $findings
 */
function local_flwcupkp_cm4_render_findings(array $findings): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('findings', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm4findingsintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    if (!$findings) {
        echo html_writer::tag('p', get_string('cm4nofindings', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted',
        ]);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('severity', 'local_flwcupkp'),
        get_string('source', 'local_flwcupkp'),
        get_string('code', 'local_flwcupkp'),
        get_string('message', 'local_flwcupkp'),
    ];
    foreach ($findings as $finding) {
        $table->data[] = [
            local_flwcupkp_cm4_badge($finding['severity'] ?? 'unknown'),
            s((string)($finding['source'] ?? '')),
            s((string)($finding['code'] ?? '')),
            s((string)($finding['message'] ?? '')),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render one list block.
 *
 * @param string $title
 * @param array $items
 * @return string
 */
function local_flwcupkp_cm4_list_block(string $title, array $items): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-contract-card']);
    $html .= html_writer::tag('h4', s($title));
    $html .= html_writer::start_tag('ul');
    foreach ($items as $item) {
        $html .= html_writer::tag('li', s((string)$item));
    }
    $html .= html_writer::end_tag('ul');
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Render compact criterion evidence details.
 *
 * @param array $evidence
 * @return string
 */
function local_flwcupkp_cm4_evidence_summary(array $evidence): string {
    if (!$evidence) {
        return '';
    }
    $items = [];
    foreach ($evidence as $key => $value) {
        if (is_array($value)) {
            $value = array_is_list($value) ? implode(', ', array_slice($value, 0, 8)) : json_encode($value);
        } else if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $items[] = html_writer::tag('span', s($key . ': ' . (string)$value));
    }
    return html_writer::tag('div', implode('', $items), ['class' => 'local-flwcupkp-cm4-evidence']);
}

/**
 * Render a status badge.
 *
 * @param string $status
 * @return string
 */
function local_flwcupkp_cm4_badge(string $status): string {
    $normalized = strtolower(str_replace(' ', '-', $status));
    return html_writer::tag('span', s($status), [
        'class' => 'badge local-flwcupkp-status-badge local-flwcupkp-status-' . $normalized,
    ]);
}

/**
 * Course options keyed by ID.
 *
 * @return array
 */
function local_flwcupkp_cm4_course_options(): array {
    global $DB;

    $records = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC',
        'id, fullname, shortname');
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = format_string($record->fullname) . ' (' . format_string($record->shortname) . ')';
    }
    return $options;
}

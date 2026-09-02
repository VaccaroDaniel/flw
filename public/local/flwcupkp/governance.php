<?php
// C-UP-KP Program 3 Gate CM3 coverage, bulk management, and governance UI.

require_once(__DIR__ . '/../../config.php');

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$download = optional_param('download', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);
$canmanage = has_capability('local/flwcupkp:manageframeworks', $context);
$canimport = has_capability('local/flwcupkp:import', $context);

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

if ($download === 'json') {
    require_capability('local/flwcupkp:import', $context);
    $export = \local_flwcupkp\local\coverage_bulk_governance_manager::export_bulk_package($frameworkid);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
    echo $export['json'];
    exit;
}

$url = new moodle_url('/local/flwcupkp/governance.php', $baseparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('cm3governance', 'local_flwcupkp'));
$PAGE->set_heading(get_string('cm3governance', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$preview = null;
$applyresult = null;
$rollbackpreview = null;
$rollbackresult = null;
$posterror = '';
$lastcontent = '';
$lastformat = 'json';
$lastcsvtype = 'activity_mappings';
$lastsourcefile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    require_capability('local/flwcupkp:manageframeworks', $context);

    $action = required_param('action', PARAM_ALPHANUMEXT);
    try {
        if (in_array($action, ['previewimport', 'confirmimport'], true)) {
            require_capability('local/flwcupkp:import', $context);
            $lastformat = optional_param('importformat', 'json', PARAM_ALPHANUMEXT);
            $lastcsvtype = optional_param('csvtype', 'activity_mappings', PARAM_ALPHANUMEXT);
            $lastsourcefile = optional_param('sourcefile', '', PARAM_PATH);
            $lastcontent = optional_param('importcontent', '', PARAM_RAW);
            if (trim($lastcontent) === '' && $lastsourcefile !== '') {
                [$lastcontent, $lastsourcefile] = \local_flwcupkp\local\unit_setup_service::read_import_source($lastsourcefile);
            }
            if ($action === 'previewimport') {
                $preview = \local_flwcupkp\local\coverage_bulk_governance_manager::preview_bulk_import(
                    $lastcontent,
                    $lastformat,
                    $lastcsvtype,
                    $lastsourcefile
                );
            } else {
                $applyresult = \local_flwcupkp\local\coverage_bulk_governance_manager::apply_bulk_import(
                    $lastcontent,
                    $lastformat,
                    $lastcsvtype,
                    $lastsourcefile
                );
            }
        } else if ($action === 'previewrollback') {
            $importid = required_param('importid', PARAM_INT);
            $rollbackpreview = \local_flwcupkp\local\coverage_bulk_governance_manager::rollback_preview($importid);
        } else if ($action === 'confirmrollback') {
            $importid = required_param('importid', PARAM_INT);
            $reason = optional_param('rollbackreason', '', PARAM_TEXT);
            $rollbackresult = \local_flwcupkp\local\coverage_bulk_governance_manager::request_rollback($importid, $reason);
        } else {
            throw new invalid_parameter_exception('Unknown CM3 governance action.');
        }
    } catch (Throwable $e) {
        $posterror = $e->getMessage();
    }
}

$cm3status = \local_flwcupkp\local\coverage_bulk_governance_manager::status($courseid, $unitcode, $frameworkid, 200);
$coverage = \local_flwcupkp\local\coverage_bulk_governance_manager::coverage_matrix($frameworkid, $courseid, $unitcode, 300);
$governance = \local_flwcupkp\local\coverage_bulk_governance_manager::governance_dashboard(
    $frameworkid,
    $courseid,
    $unitcode,
    300
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cm3governance', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('cm3governanceintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm3-intro',
]);

if ($posterror !== '') {
    echo $OUTPUT->notification(s($posterror), 'error');
}
if ($applyresult !== null) {
    echo $OUTPUT->notification(get_string('cm3importapplied', 'local_flwcupkp'), 'success');
}
if ($rollbackresult !== null) {
    echo $OUTPUT->notification(get_string('cm3rollbackrequested', 'local_flwcupkp'), 'success');
}

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
echo html_writer::link(new moodle_url('/local/flwcupkp/foundation.php', [
    'frameworkid' => $frameworkid,
    'courseid' => $courseid,
    'unitcode' => $unitcode,
]), get_string('foundationinspector', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/management.php', $baseparams),
    get_string('cm4management', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
if ($canimport) {
    echo html_writer::link(new moodle_url('/local/flwcupkp/governance.php', $baseparams + ['download' => 'json']),
        get_string('cm3exportjson', 'local_flwcupkp'), ['class' => 'btn btn-primary']);
}
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm3-shell']);
local_flwcupkp_cm3_render_status($cm3status);
local_flwcupkp_cm3_render_filters($frameworkid, $courseid, $unitcode);
local_flwcupkp_cm3_render_coverage($coverage);
local_flwcupkp_cm3_render_findings($coverage['findings']);
local_flwcupkp_cm3_render_governance($governance);
if ($canmanage || $canimport) {
    local_flwcupkp_cm3_render_bulk_import($preview, $applyresult, $lastcontent, $lastformat, $lastcsvtype, $lastsourcefile,
        $canimport);
    local_flwcupkp_cm3_render_import_history($governance['recent_imports'], $rollbackpreview, $rollbackresult);
}
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render CM3 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_cm3_render_status(array $status): void {
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-cardgrid local-flwcupkp-cm3-cardgrid']);
    foreach ([
        get_string('cm3status', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm3_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('foundationstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm3_badge($status['foundation']['status'] ?? 'unknown'),
            'detail' => $status['foundation']['next_allowed_gate'] ?? '',
        ],
        get_string('cm1status', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm3_badge($status['cm1']['status'] ?? 'unknown'),
            'detail' => $status['cm1']['contract'] ?? '',
        ],
        get_string('cm2status', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm3_badge($status['cm2']['status'] ?? 'unknown'),
            'detail' => $status['cm2']['contract'] ?? '',
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_cm3_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('cm3nextgatedetail', 'local_flwcupkp'),
        ],
    ] as $label => $card) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
        echo html_writer::tag('span', s($label));
        echo html_writer::tag('strong', $card['value']);
        echo html_writer::tag('em', s((string)$card['detail']));
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

/**
 * Render filters.
 *
 * @param int $frameworkid
 * @param int $courseid
 * @param string $unitcode
 */
function local_flwcupkp_cm3_render_filters(int $frameworkid, int $courseid, string $unitcode): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/governance.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm3-filters',
    ]);
    echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] + local_flwcupkp_cm3_course_options(),
            'courseid', $courseid, false), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
        html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/governance.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render coverage categories.
 *
 * @param array $coverage
 */
function local_flwcupkp_cm3_render_coverage(array $coverage): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm3-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm3coverage', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm3coverageintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm3-coverage-grid']);
    foreach ($coverage['categories'] as $category) {
        $percent = (float)$category['percent'];
        $style = '--flwcupkp-ring-value:' . max(0, min(100, $percent)) . '%;';
        echo html_writer::start_tag('article', ['class' => 'local-flwcupkp-cm3-coverage-card']);
        echo html_writer::tag('div', html_writer::tag('strong', format_float($percent, 1) . '%'), [
            'class' => 'local-flwcupkp-cm3-ring',
            'style' => $style,
        ]);
        echo html_writer::tag('h4', s((string)$category['label']));
        echo html_writer::tag('p', s((string)$category['detail']));
        echo html_writer::tag('span', s((int)$category['covered'] . '/' . (int)$category['total']),
            ['class' => 'local-flwcupkp-cm3-cardmeta']);
        echo html_writer::end_tag('article');
    }
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('section');
}

/**
 * Render coverage findings.
 *
 * @param array $findings
 */
function local_flwcupkp_cm3_render_findings(array $findings): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm3-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm3findings', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm3findingsintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');
    if (!$findings) {
        echo html_writer::tag('p', get_string('cm3nofindings', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table local-flwcupkp-cm3-table';
    $table->head = [
        get_string('foundationseverity', 'local_flwcupkp'),
        get_string('code', 'local_flwcupkp'),
        get_string('value', 'local_flwcupkp'),
        get_string('foundationdetails', 'local_flwcupkp'),
    ];
    foreach ($findings as $finding) {
        $samples = implode('; ', array_slice($finding['samples'] ?? [], 0, 6));
        $table->data[] = [
            local_flwcupkp_cm3_badge((string)($finding['severity'] ?? 'warning')),
            s((string)($finding['code'] ?? '')),
            (int)($finding['count'] ?? 0),
            s((string)($finding['message'] ?? '') . ($samples !== '' ? ' ' . $samples : '')),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render lifecycle governance.
 *
 * @param array $governance
 */
function local_flwcupkp_cm3_render_governance(array $governance): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm3-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm3governancedashboard', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm3governancedashboardintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm3-summary-grid']);
    foreach ($governance['summary'] as $key => $value) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
        echo html_writer::tag('span', s(local_flwcupkp_cm3_human($key)));
        echo html_writer::tag('strong', s((string)$value));
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
    $table->head = [
        get_string('entitytype', 'local_flwcupkp'),
        get_string('draft', 'local_flwcupkp'),
        get_string('review', 'local_flwcupkp'),
        get_string('approved', 'local_flwcupkp'),
        get_string('published', 'local_flwcupkp'),
        get_string('deprecated', 'local_flwcupkp'),
        get_string('archived', 'local_flwcupkp'),
    ];
    foreach ($governance['lifecycle_counts'] as $type => $counts) {
        $table->data[] = [
            s(local_flwcupkp_cm3_human($type)),
            (int)($counts['draft'] ?? 0),
            (int)($counts['review'] ?? 0),
            (int)($counts['approved'] ?? 0),
            (int)($counts['published'] ?? 0),
            (int)($counts['deprecated'] ?? 0),
            (int)($counts['archived'] ?? 0),
        ];
    }
    echo html_writer::table($table);

    if (!empty($governance['replacement_edges'])) {
        echo html_writer::tag('h4', get_string('cm3replacements', 'local_flwcupkp'));
        $replacementtable = new html_table();
        $replacementtable->attributes['class'] = 'generaltable local-flwcupkp-foundation-table';
        $replacementtable->head = [
            get_string('idnumber'),
            get_string('source', 'local_flwcupkp'),
            get_string('replacement', 'local_flwcupkp'),
            get_string('type', 'local_flwcupkp'),
        ];
        foreach ($governance['replacement_edges'] as $edge) {
            $replacementtable->data[] = [
                (int)$edge['id'],
                (int)$edge['source_kpid'],
                (int)$edge['replacement_kpid'],
                s((string)$edge['relationshiptype']),
            ];
        }
        echo html_writer::table($replacementtable);
    }
    echo html_writer::end_tag('section');
}

/**
 * Render dry-run/apply import controls.
 *
 * @param array|null $preview
 * @param array|null $applyresult
 * @param string $content
 * @param string $format
 * @param string $csvtype
 * @param string $sourcefile
 * @param bool $canimport
 */
function local_flwcupkp_cm3_render_bulk_import(?array $preview, ?array $applyresult, string $content, string $format,
        string $csvtype, string $sourcefile, bool $canimport): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm3-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm3bulkimportexport', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm3bulkimportexportintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    if ($preview !== null) {
        local_flwcupkp_cm3_render_preview_result($preview, $content, $format, $csvtype, $sourcefile, $canimport);
    }
    if ($applyresult !== null) {
        echo html_writer::tag('pre', s(json_encode($applyresult['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            ['class' => 'local-flwcupkp-json-result local-flwcupkp-cm3-json']);
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/governance.php'),
        'class' => 'local-flwcupkp-editform local-flwcupkp-cm3-importform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'previewimport']);
    echo html_writer::tag('label', get_string('cm3format', 'local_flwcupkp') .
        html_writer::select([
            'json' => 'JSON',
            'csv' => 'CSV',
        ], 'importformat', $format, false), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('cm3csvtype', 'local_flwcupkp') .
        html_writer::select([
            'activity_mappings' => get_string('csvactivitymappings', 'local_flwcupkp'),
            'quiz_kp_mappings' => get_string('csvquizkpmappings', 'local_flwcupkp'),
        ], 'csvtype', $csvtype, false), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('serverfilepath', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'sourcefile',
            'value' => s($sourcefile),
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-cm3-wide']);
    echo html_writer::tag('label', get_string('cm3importcontent', 'local_flwcupkp') .
        html_writer::tag('textarea', s($content), ['name' => 'importcontent', 'rows' => 12]),
        ['class' => 'local-flwcupkp-filter local-flwcupkp-cm3-wide']);
    echo html_writer::tag('button', get_string('cm3previewimport', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'disabled' => $canimport ? null : 'disabled',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('section');
}

/**
 * Render import preview result.
 *
 * @param array $preview
 * @param string $content
 * @param string $format
 * @param string $csvtype
 * @param string $sourcefile
 * @param bool $canimport
 */
function local_flwcupkp_cm3_render_preview_result(array $preview, string $content, string $format, string $csvtype,
        string $sourcefile, bool $canimport): void {
    $class = $preview['valid'] ? 'success' : 'warning';
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm3-preview local-flwcupkp-cm3-preview-' . $class]);
    echo html_writer::tag('strong', $preview['valid'] ?
        get_string('cm3dryrunvalid', 'local_flwcupkp') :
        get_string('cm3dryrunblocked', 'local_flwcupkp'));
    if (!empty($preview['duplicate'])) {
        echo html_writer::tag('p', get_string('cm3duplicatedetected', 'local_flwcupkp'));
    }
    if (!empty($preview['errors'])) {
        echo html_writer::alist(array_map('s', $preview['errors']), ['class' => 'local-flwcupkp-cm3-errors']);
    }
    if (!empty($preview['warnings'])) {
        echo html_writer::alist(array_map('s', $preview['warnings']), ['class' => 'local-flwcupkp-cm3-warnings']);
    }
    echo html_writer::tag('pre', s(json_encode([
        'counts' => $preview['counts'],
        'checksum' => $preview['checksum'],
        'transactional' => $preview['transactional'],
        'would_write' => $preview['would_write'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)), ['class' => 'local-flwcupkp-json-result local-flwcupkp-cm3-json']);

    if ($preview['valid'] && $canimport) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/governance.php'),
            'class' => 'local-flwcupkp-cm3-confirm',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirmimport']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'importformat', 'value' => $format]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'csvtype', 'value' => $csvtype]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sourcefile', 'value' => $sourcefile]);
        echo html_writer::tag('textarea', s($content), [
            'name' => 'importcontent',
            'class' => 'local-flwcupkp-cm3-hiddenfield',
        ]);
        echo html_writer::tag('button', get_string('cm3confirmimport', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-primary',
        ]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_tag('div');
}

/**
 * Render import history and rollback request controls.
 *
 * @param array $imports
 * @param array|null $rollbackpreview
 * @param array|null $rollbackresult
 */
function local_flwcupkp_cm3_render_import_history(array $imports, ?array $rollbackpreview,
        ?array $rollbackresult): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm3-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('cm3repairhistory', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('cm3repairhistoryintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    if ($rollbackpreview !== null) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm3-preview local-flwcupkp-cm3-preview-warning']);
        echo html_writer::tag('strong', get_string('cm3rollbackpreview', 'local_flwcupkp'));
        echo html_writer::tag('p', s($rollbackpreview['reason']));
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/governance.php'),
            'class' => 'local-flwcupkp-cm3-confirm',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirmrollback']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'importid', 'value' => (int)$rollbackpreview['importid']]);
        echo html_writer::tag('label', get_string('reason') .
            html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'rollbackreason',
                'value' => s($rollbackpreview['reason']),
            ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-cm3-wide']);
        echo html_writer::tag('button', get_string('cm3confirmrollback', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-secondary',
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_tag('div');
    }

    if (!$imports) {
        echo html_writer::tag('p', get_string('nohistory', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-foundation-table local-flwcupkp-cm3-table';
    $table->head = [
        get_string('idnumber'),
        get_string('time'),
        get_string('type', 'local_flwcupkp'),
        get_string('status'),
        get_string('cm3rollback', 'local_flwcupkp'),
        get_string('source', 'local_flwcupkp'),
        get_string('actions'),
    ];
    foreach ($imports as $import) {
        $previewform = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/governance.php'),
            'class' => 'local-flwcupkp-inlineform',
        ]);
        $previewform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $previewform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'previewrollback']);
        $previewform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'importid', 'value' => (int)$import['id']]);
        $previewform .= html_writer::tag('button', get_string('cm3previewrollback', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-secondary btn-sm',
        ]);
        $previewform .= html_writer::end_tag('form');
        $table->data[] = [
            (int)$import['id'],
            userdate((int)$import['timecreated']),
            s((string)$import['schemaversion']),
            local_flwcupkp_cm3_badge((string)$import['validationstatus']),
            local_flwcupkp_cm3_badge((string)$import['rollbackstatus']),
            s((string)$import['sourcefile']),
            $previewform,
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Course options for scoped C-UP-KP objects.
 *
 * @return array
 */
function local_flwcupkp_cm3_course_options(): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT DISTINCT c.id, c.fullname, c.shortname
           FROM {flwcupkp_object} o
           JOIN {course} c ON c.id = o.courseid
          WHERE o.courseid IS NOT NULL
            AND o.courseid > 0
       ORDER BY c.fullname ASC, c.shortname ASC"
    );
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = format_string($record->fullname) . ' (' . format_string($record->shortname) . ')';
    }
    return $options;
}

/**
 * Status badge.
 *
 * @param string $status
 * @return string
 */
function local_flwcupkp_cm3_badge(string $status): string {
    $status = trim($status) !== '' ? $status : 'unknown';
    $class = 'local-flwcupkp-foundation-badge local-flwcupkp-foundation-badge-' .
        clean_param(strtolower($status), PARAM_ALPHANUMEXT);
    return html_writer::tag('span', s(local_flwcupkp_cm3_human($status)), ['class' => $class]);
}

/**
 * Human-readable machine label.
 *
 * @param string $label
 * @return string
 */
function local_flwcupkp_cm3_human(string $label): string {
    return ucwords(str_replace(['_', '-'], ' ', strtolower(trim($label))));
}

<?php
// Evidence calibration report for C-UP-KP.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$targettype = optional_param('targettype', '', PARAM_ALPHANUMEXT);
$download = optional_param('download', '', PARAM_ALPHA);
$snapshotid = optional_param('snapshotid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHANUMEXT);
    if ($action === 'savesnapshot') {
        $name = optional_param('snapshotname', '', PARAM_TEXT);
        $note = optional_param('snapshotnote', '', PARAM_TEXT);
        $savedid = \local_flwcupkp\local\calibration_report::save_snapshot($courseid, $unitcode, $targettype, $name,
            $note);
        redirect(local_flwcupkp_calibration_url($courseid, $unitcode, $targettype, [
            'status' => 'snapshotsaved',
            'saved' => $savedid,
        ]));
    }
}

if ($download !== '') {
    if (!in_array($download, ['json', 'csv'], true)) {
        throw new invalid_parameter_exception('Unsupported calibration export format.');
    }
    if ($snapshotid > 0) {
        $snapshot = \local_flwcupkp\local\calibration_report::snapshot($snapshotid);
        if (!$snapshot) {
            throw new moodle_exception('invalidrecord', 'error', '', $snapshotid);
        }
        $payload = \local_flwcupkp\local\calibration_report::snapshot_payload($snapshot);
        $filename = 'flw-cupkp-calibration-snapshot-' . $snapshotid . '.' . $download;
    } else {
        $payload = \local_flwcupkp\local\calibration_report::export_payload($courseid, $unitcode, $targettype);
        $scope = ($unitcode !== '' ? $unitcode : 'all') . ($targettype !== '' ? '-' . $targettype : '');
        $filename = 'flw-cupkp-calibration-' . $scope . '-' . date('Ymd-His') . '.' . $download;
    }
    local_flwcupkp_calibration_send_download($payload, $download, $filename);
}

$url = new moodle_url('/local/flwcupkp/calibration.php');
if ($courseid > 0) {
    $url->param('courseid', $courseid);
}
if ($unitcode !== '') {
    $url->param('unitcode', $unitcode);
}
if ($targettype !== '') {
    $url->param('targettype', $targettype);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('calibrationreport', 'local_flwcupkp'));
$PAGE->set_heading(get_string('calibrationreport', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$courseoptions = [0 => get_string('all', 'local_flwcupkp')] +
    \local_flwcupkp\local\calibration_report::course_options();
$unitoptions = ['' => get_string('all', 'local_flwcupkp')] +
    \local_flwcupkp\local\calibration_report::unit_options();
$targetoptions = [
    '' => get_string('all', 'local_flwcupkp'),
    'kp' => get_string('knowledgepoint', 'local_flwcupkp'),
    'up' => get_string('usepoint', 'local_flwcupkp'),
    'competency' => get_string('competency', 'local_flwcupkp'),
];
$report = \local_flwcupkp\local\calibration_report::report($courseid, $unitcode, $targettype);
$snapshots = \local_flwcupkp\local\calibration_report::snapshots($courseid, $unitcode, $targettype);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('calibrationreport', 'local_flwcupkp'));

if ($status === 'snapshotsaved') {
    echo $OUTPUT->notification(get_string('calibrationsnapshotsaved', 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'), get_string('pluginname', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('curriculummanager',
    'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/calibration_proposal.php'),
    get_string('thresholdproposals', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(local_flwcupkp_calibration_url($courseid, $unitcode, $targettype, ['download' => 'json']),
    get_string('exportjson', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(local_flwcupkp_calibration_url($courseid, $unitcode, $targettype, ['download' => 'csv']),
    get_string('exportcsv', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/calibration.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::tag('label', get_string('course', 'core') .
    html_writer::select($courseoptions, 'courseid', $courseid, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
    html_writer::select($unitoptions, 'unitcode', $unitcode, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('targettype', 'local_flwcupkp') .
    html_writer::select($targetoptions, 'targettype', $targettype, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/calibration.php'), get_string('reset'),
    ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo html_writer::tag('p', get_string('calibrationintro', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);

echo local_flwcupkp_calibration_snapshot_form($courseid, $unitcode, $targettype);
echo local_flwcupkp_calibration_snapshot_comparison($report['summary'], $snapshots[0] ?? null);
echo local_flwcupkp_calibration_summary($report['summary']);
echo local_flwcupkp_calibration_rules($report['rules']);
echo local_flwcupkp_calibration_distribution_tables($report);
echo local_flwcupkp_calibration_state_table($report['state_outcomes']);
echo local_flwcupkp_calibration_edge_cases($report['edge_cases']);
echo local_flwcupkp_calibration_snapshots($snapshots, $courseid, $unitcode, $targettype);

echo $OUTPUT->footer();

/**
 * Build a calibration URL that preserves report scope.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param string $targettype
 * @param array $extra
 * @return moodle_url
 */
function local_flwcupkp_calibration_url(int $courseid, string $unitcode, string $targettype, array $extra = []): moodle_url {
    $params = [];
    if ($courseid > 0) {
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $params['unitcode'] = $unitcode;
    }
    if ($targettype !== '') {
        $params['targettype'] = $targettype;
    }
    return new moodle_url('/local/flwcupkp/calibration.php', $params + $extra);
}

/**
 * Send a JSON or CSV calibration download.
 *
 * @param array $payload
 * @param string $format
 * @param string $filename
 */
function local_flwcupkp_calibration_send_download(array $payload, string $format, string $filename): void {
    if (!$payload) {
        throw new moodle_exception('invalidrecord', 'error');
    }

    $filename = clean_filename($filename);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo \local_flwcupkp\local\calibration_report::csv($payload);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Render snapshot save controls.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param string $targettype
 * @return string
 */
function local_flwcupkp_calibration_snapshot_form(int $courseid, string $unitcode, string $targettype): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-calibration-snapshot']);
    $html .= html_writer::tag('h3', get_string('savecalibrationsnapshot', 'local_flwcupkp'));
    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => local_flwcupkp_calibration_url($courseid, $unitcode, $targettype),
        'class' => 'local-flwcupkp-actionform',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savesnapshot']);
    $html .= html_writer::tag('label', get_string('snapshotname', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'snapshotname',
            'maxlength' => 120,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter']);
    $html .= html_writer::tag('label', get_string('snapshotnote', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'snapshotnote',
            'maxlength' => 255,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter']);
    $html .= html_writer::tag('button', get_string('savesnapshot', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Compare current summary metrics with the latest saved snapshot in scope.
 *
 * @param array $summary
 * @param \stdClass|null $snapshot
 * @return string
 */
function local_flwcupkp_calibration_snapshot_comparison(array $summary, ?\stdClass $snapshot): string {
    if (!$snapshot) {
        return '';
    }

    $previous = json_decode((string)$snapshot->summaryjson, true);
    if (!is_array($previous)) {
        return '';
    }

    $labels = [
        'evidence_total' => get_string('evidencetotal', 'local_flwcupkp'),
        'learner_count' => get_string('learners', 'local_flwcupkp'),
        'targets_with_evidence' => get_string('targets', 'local_flwcupkp'),
        'state_total' => get_string('masterystates', 'local_flwcupkp'),
        'performance_evidence' => get_string('performanceevidence', 'local_flwcupkp'),
        'manual_overrides' => get_string('manualoverride', 'local_flwcupkp'),
        'low_confidence_states' => get_string('lowconfidencestates', 'local_flwcupkp'),
        'review_due_states' => get_string('reviewduestates', 'local_flwcupkp'),
    ];

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('metric', 'local_flwcupkp'),
        get_string('currentvalue', 'local_flwcupkp'),
        get_string('snapshotvalue', 'local_flwcupkp'),
        get_string('delta', 'local_flwcupkp'),
    ];

    foreach ($labels as $key => $label) {
        $current = (int)($summary[$key] ?? 0);
        $old = (int)($previous[$key] ?? 0);
        $delta = $current - $old;
        $table->data[] = [
            s($label),
            $current,
            $old,
            ($delta > 0 ? '+' : '') . $delta,
        ];
    }

    return html_writer::tag('h3', get_string('latestsnapshotcomparison', 'local_flwcupkp') . ': ' .
            s($snapshot->name)) . html_writer::table($table);
}

/**
 * Render saved snapshot history.
 *
 * @param array $snapshots
 * @param int $courseid
 * @param string $unitcode
 * @param string $targettype
 * @return string
 */
function local_flwcupkp_calibration_snapshots(array $snapshots, int $courseid, string $unitcode,
        string $targettype): string {
    $html = html_writer::tag('h3', get_string('savedcalibrationsnapshots', 'local_flwcupkp'));
    if (!$snapshots) {
        return $html . html_writer::tag('p', get_string('nosavedsnapshots', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('snapshotname', 'local_flwcupkp'),
        get_string('targettype', 'local_flwcupkp'),
        get_string('unit', 'local_flwcupkp'),
        get_string('courseid', 'local_flwcupkp'),
        get_string('evidencetotal', 'local_flwcupkp'),
        get_string('masterystates', 'local_flwcupkp'),
        get_string('calibrationedgecases', 'local_flwcupkp'),
        get_string('timecreated', 'local_flwcupkp'),
        get_string('downloads', 'local_flwcupkp'),
    ];

    foreach ($snapshots as $snapshot) {
        $payload = \local_flwcupkp\local\calibration_report::snapshot_payload($snapshot);
        $summary = $payload['report']['summary'] ?? [];
        $edgecases = $payload['report']['edge_cases'] ?? [];
        $downloads = html_writer::link(local_flwcupkp_calibration_url($courseid, $unitcode, $targettype, [
                'download' => 'json',
                'snapshotid' => (int)$snapshot->id,
            ]), get_string('exportjson', 'local_flwcupkp')) . ' ' .
            html_writer::link(local_flwcupkp_calibration_url($courseid, $unitcode, $targettype, [
                'download' => 'csv',
                'snapshotid' => (int)$snapshot->id,
            ]), get_string('exportcsv', 'local_flwcupkp'));
        $proposaltype = $snapshot->targettype ?: ($targettype !== '' ? $targettype : 'kp');
        $downloads .= ' ' . html_writer::link(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
            'snapshotid' => (int)$snapshot->id,
            'targettype' => $proposaltype,
        ]), get_string('proposethresholds', 'local_flwcupkp'));

        $table->data[] = [
            s($snapshot->name) . (!empty($snapshot->note) ?
                html_writer::tag('div', s($snapshot->note), ['class' => 'local-flwcupkp-muted']) : ''),
            s($snapshot->targettype ?: get_string('all', 'local_flwcupkp')),
            s($snapshot->unitcode ?: get_string('all', 'local_flwcupkp')),
            $snapshot->courseid ? (int)$snapshot->courseid : get_string('all', 'local_flwcupkp'),
            (int)($summary['evidence_total'] ?? 0),
            (int)($summary['state_total'] ?? 0),
            count($edgecases),
            userdate((int)$snapshot->timecreated),
            $downloads,
        ];
    }

    return $html . html_writer::table($table);
}

/**
 * Render top-line metrics.
 *
 * @param array $summary
 * @return string
 */
function local_flwcupkp_calibration_summary(array $summary): string {
    $labels = [
        'evidence_total' => get_string('evidencetotal', 'local_flwcupkp'),
        'learner_count' => get_string('learners', 'local_flwcupkp'),
        'targets_with_evidence' => get_string('targets', 'local_flwcupkp'),
        'state_total' => get_string('masterystates', 'local_flwcupkp'),
        'performance_evidence' => get_string('performanceevidence', 'local_flwcupkp'),
        'manual_overrides' => get_string('manualoverride', 'local_flwcupkp'),
        'low_confidence_states' => get_string('lowconfidencestates', 'local_flwcupkp'),
        'review_due_states' => get_string('reviewduestates', 'local_flwcupkp'),
    ];

    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-overview-grid']);
    foreach ($labels as $key => $label) {
        $html .= html_writer::tag('span',
            html_writer::tag('strong', (string)(int)($summary[$key] ?? 0)) .
            html_writer::tag('em', s($label)),
            ['class' => 'local-flwcupkp-course-overview-stat']
        );
    }
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Render rule calibration table.
 *
 * @param array $rules
 * @return string
 */
function local_flwcupkp_calibration_rules(array $rules): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('rule', 'local_flwcupkp'),
        get_string('type', 'local_flwcupkp'),
        get_string('version', 'local_flwcupkp'),
        get_string('calibrationstatus', 'local_flwcupkp'),
        get_string('thresholds', 'local_flwcupkp'),
    ];
    foreach ($rules as $rule) {
        $table->data[] = [
            s($rule['name']),
            s($rule['ruletype']),
            s($rule['version']),
            s($rule['calibration_status']),
            s($rule['thresholds']),
        ];
    }

    return html_writer::tag('h3', get_string('calibrationrules', 'local_flwcupkp')) .
        html_writer::table($table);
}

/**
 * Render evidence distribution tables.
 *
 * @param array $report
 * @return string
 */
function local_flwcupkp_calibration_distribution_tables(array $report): string {
    $html = html_writer::tag('h3', get_string('evidencedistribution', 'local_flwcupkp'));
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-calibration-grid']);
    $html .= local_flwcupkp_calibration_count_table(get_string('targettype', 'local_flwcupkp'),
        $report['evidence_by_type']);
    $html .= local_flwcupkp_calibration_count_table(get_string('evidencestrength', 'local_flwcupkp'),
        $report['evidence_by_strength']);
    $html .= local_flwcupkp_calibration_count_table(get_string('source', 'local_flwcupkp'),
        $report['evidence_by_source']);
    $html .= html_writer::end_tag('div');

    $bandtable = new html_table();
    $bandtable->attributes['class'] = 'generaltable local-flwcupkp-table';
    $bandtable->head = [
        get_string('targettype', 'local_flwcupkp'),
        get_string('scoreband', 'local_flwcupkp'),
        get_string('count', 'local_flwcupkp'),
    ];
    foreach ($report['score_bands'] as $row) {
        $bandtable->data[] = [s($row['targettype']), s($row['band']), (int)$row['count']];
    }
    $html .= html_writer::table($bandtable);

    return $html;
}

/**
 * Render one count/average table.
 *
 * @param string $label
 * @param array $rows
 * @return string
 */
function local_flwcupkp_calibration_count_table(string $label, array $rows): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [$label, get_string('count', 'local_flwcupkp'), get_string('averagescore', 'local_flwcupkp')];
    foreach ($rows as $row) {
        $table->data[] = [
            s($row['label']),
            (int)$row['evidence_count'],
            format_float((float)$row['average_score'], 3),
        ];
    }
    if (!$table->data) {
        $table->data[] = [get_string('noevidenceyet', 'local_flwcupkp'), 0, format_float(0, 3)];
    }
    return html_writer::tag('div', html_writer::table($table), ['class' => 'local-flwcupkp-calibration-panel']);
}

/**
 * Render state outcome table.
 *
 * @param array $rows
 * @return string
 */
function local_flwcupkp_calibration_state_table(array $rows): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('targettype', 'local_flwcupkp'),
        get_string('state', 'local_flwcupkp'),
        get_string('count', 'local_flwcupkp'),
        get_string('averagescore', 'local_flwcupkp'),
        get_string('averageconfidence', 'local_flwcupkp'),
        get_string('averageevidencecount', 'local_flwcupkp'),
    ];
    foreach ($rows as $row) {
        $table->data[] = [
            s($row['targettype']),
            s($row['state']),
            (int)$row['count'],
            format_float((float)$row['average_score'], 3),
            format_float((float)$row['average_confidence'], 3),
            format_float((float)$row['average_evidence_count'], 2),
        ];
    }
    return html_writer::tag('h3', get_string('masteryoutcomes', 'local_flwcupkp')) .
        html_writer::table($table);
}

/**
 * Render edge-case table.
 *
 * @param array $rows
 * @return string
 */
function local_flwcupkp_calibration_edge_cases(array $rows): string {
    $html = html_writer::tag('h3', get_string('calibrationedgecases', 'local_flwcupkp'));
    if (!$rows) {
        return $html . html_writer::tag('p', get_string('noedgecases', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-queue-complete']);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('priority', 'local_flwcupkp'),
        get_string('type', 'local_flwcupkp'),
        get_string('learner', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('state', 'local_flwcupkp'),
        get_string('score', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
        get_string('evidence', 'local_flwcupkp'),
        get_string('explanation', 'local_flwcupkp'),
    ];
    foreach ($rows as $row) {
        $evidence = $row['latest_evidenceid'] ?
            '#' . (int)$row['latest_evidenceid'] . ' ' . s($row['latest_evidencetype']) .
            html_writer::tag('div', s($row['latest_strength']), ['class' => 'local-flwcupkp-muted']) :
            get_string('noevidenceyet', 'local_flwcupkp');
        $table->data[] = [
            s($row['priority']),
            s($row['kind']),
            (int)$row['userid'],
            s($row['targettype'] . ':' . $row['targetexternalid']),
            s($row['state']),
            format_float((float)$row['score'], 3),
            format_float((float)$row['confidence'], 3),
            $evidence,
            s($row['message']),
        ];
    }
    $html .= html_writer::table($table);
    return $html;
}

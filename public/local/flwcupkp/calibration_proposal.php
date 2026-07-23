<?php
// Threshold calibration proposals for C-UP-KP.

require_once(__DIR__ . '/../../config.php');

$snapshotid = optional_param('snapshotid', 0, PARAM_INT);
$proposalid = optional_param('proposalid', 0, PARAM_INT);
$targettype = optional_param('targettype', '', PARAM_ALPHANUMEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

$proposal = null;
if ($proposalid > 0) {
    $proposal = \local_flwcupkp\local\calibration_proposal::proposal($proposalid);
    if (!$proposal) {
        throw new moodle_exception('invalidrecord', 'error', '', $proposalid);
    }
    $snapshotid = (int)$proposal->snapshotid;
    $targettype = (string)$proposal->targettype;
}

$snapshot = $snapshotid > 0 ? \local_flwcupkp\local\calibration_report::snapshot($snapshotid) : null;
if ($snapshotid > 0 && !$snapshot) {
    throw new moodle_exception('invalidrecord', 'error', '', $snapshotid);
}

$targettypes = \local_flwcupkp\local\calibration_proposal::target_types();
if ($targettype === '' && $snapshot && !empty($snapshot->targettype) && in_array((string)$snapshot->targettype,
        $targettypes, true)) {
    $targettype = (string)$snapshot->targettype;
}
if ($targettype === '' || !in_array($targettype, $targettypes, true)) {
    $targettype = 'kp';
}

$thresholds = $proposal ?
    \local_flwcupkp\local\calibration_proposal::proposal_thresholds($proposal) :
    \local_flwcupkp\local\calibration_proposal::current_thresholds($targettype);
$preview = $proposal ? \local_flwcupkp\local\calibration_proposal::proposal_preview($proposal) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHANUMEXT);

    if ($action === 'preview' || $action === 'saveproposal') {
        if (!$snapshot) {
            throw new invalid_parameter_exception('Snapshot is required.');
        }
        $thresholds = local_flwcupkp_calproposal_thresholds_from_request($targettype);
        $preview = \local_flwcupkp\local\calibration_proposal::preview($snapshot, $targettype, $thresholds);

        if ($action === 'saveproposal') {
            $name = optional_param('proposalname', '', PARAM_TEXT);
            $note = optional_param('proposalnote', '', PARAM_TEXT);
            $newid = \local_flwcupkp\local\calibration_proposal::save((int)$snapshot->id, $targettype, $name, $note,
                $thresholds);
            redirect(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
                'snapshotid' => (int)$snapshot->id,
                'targettype' => $targettype,
                'proposalid' => $newid,
                'status' => 'proposalsaved',
            ]));
        }
    } else if ($action === 'activateproposal') {
        require_capability('local/flwcupkp:synccompetencies', $context);
        $activateid = required_param('proposalid', PARAM_INT);
        $confirm = optional_param('confirmactivation', 0, PARAM_BOOL);
        if (!$confirm) {
            redirect(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
                'snapshotid' => $snapshotid,
                'targettype' => $targettype,
                'proposalid' => $activateid,
                'status' => 'activationunchecked',
            ]));
        }
        \local_flwcupkp\local\calibration_proposal::activate($activateid);
        redirect(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
            'snapshotid' => $snapshotid,
            'targettype' => $targettype,
            'proposalid' => $activateid,
            'status' => 'proposalactivated',
        ]));
    }
}

$url = new moodle_url('/local/flwcupkp/calibration_proposal.php');
if ($snapshotid > 0) {
    $url->param('snapshotid', $snapshotid);
}
if ($targettype !== '') {
    $url->param('targettype', $targettype);
}
if ($proposalid > 0) {
    $url->param('proposalid', $proposalid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('thresholdproposals', 'local_flwcupkp'));
$PAGE->set_heading(get_string('thresholdproposals', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('thresholdproposals', 'local_flwcupkp'));

if ($status === 'proposalsaved') {
    echo $OUTPUT->notification(get_string('calibrationproposalsaved', 'local_flwcupkp'), 'success');
} else if ($status === 'proposalactivated') {
    echo $OUTPUT->notification(get_string('calibrationproposalactivated', 'local_flwcupkp'), 'success');
} else if ($status === 'activationunchecked') {
    echo $OUTPUT->notification(get_string('calibrationactivationunchecked', 'local_flwcupkp'), 'warning');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/calibration.php'), get_string('calibrationreport',
    'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'), get_string('pluginname', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

if (!$snapshot) {
    echo html_writer::tag('p', get_string('choosecalibrationsnapshot', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']);
    echo local_flwcupkp_calproposal_snapshot_picker();
    echo $OUTPUT->footer();
    exit;
}

echo local_flwcupkp_calproposal_snapshot_summary($snapshot);
echo local_flwcupkp_calproposal_form($snapshot, $targettype, $thresholds, $preview, $proposal);
if ($preview) {
    echo local_flwcupkp_calproposal_preview($preview);
}
echo local_flwcupkp_calproposal_saved_table((int)$snapshot->id, $targettype);

echo $OUTPUT->footer();

/**
 * Collect threshold values from a proposal form.
 *
 * @param string $targettype
 * @return array
 */
function local_flwcupkp_calproposal_thresholds_from_request(string $targettype): array {
    $values = [];
    foreach (\local_flwcupkp\local\calibration_proposal::fields($targettype) as $field) {
        $values[$field] = required_param($field, PARAM_FLOAT);
    }
    return \local_flwcupkp\local\calibration_proposal::normalize_thresholds($targettype, $values);
}

/**
 * Render recent snapshots for proposal selection.
 *
 * @return string
 */
function local_flwcupkp_calproposal_snapshot_picker(): string {
    $snapshots = \local_flwcupkp\local\calibration_report::snapshots(0, '', '', 25);
    if (!$snapshots) {
        return html_writer::tag('p', get_string('nosavedsnapshots', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('snapshotname', 'local_flwcupkp'),
        get_string('unit', 'local_flwcupkp'),
        get_string('targettype', 'local_flwcupkp'),
        get_string('timecreated', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
    ];
    foreach ($snapshots as $snapshot) {
        $targettype = !empty($snapshot->targettype) ? (string)$snapshot->targettype : 'kp';
        $table->data[] = [
            s($snapshot->name),
            s($snapshot->unitcode ?: get_string('all', 'local_flwcupkp')),
            s($snapshot->targettype ?: get_string('all', 'local_flwcupkp')),
            userdate((int)$snapshot->timecreated),
            html_writer::link(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
                'snapshotid' => (int)$snapshot->id,
                'targettype' => $targettype,
            ]), get_string('openproposalworkflow', 'local_flwcupkp')),
        ];
    }

    return html_writer::table($table);
}

/**
 * Render selected snapshot summary.
 *
 * @param \stdClass $snapshot
 * @return string
 */
function local_flwcupkp_calproposal_snapshot_summary(\stdClass $snapshot): string {
    $summary = json_decode((string)$snapshot->summaryjson, true);
    if (!is_array($summary)) {
        $summary = [];
    }

    $html = html_writer::tag('h3', get_string('selectedsnapshot', 'local_flwcupkp') . ': ' . s($snapshot->name));
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-overview-grid']);
    foreach ([
        'evidence_total' => get_string('evidencetotal', 'local_flwcupkp'),
        'state_total' => get_string('masterystates', 'local_flwcupkp'),
        'performance_evidence' => get_string('performanceevidence', 'local_flwcupkp'),
        'low_confidence_states' => get_string('lowconfidencestates', 'local_flwcupkp'),
    ] as $key => $label) {
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
 * Render proposal threshold form.
 *
 * @param \stdClass $snapshot
 * @param string $targettype
 * @param array $thresholds
 * @param array|null $preview
 * @param \stdClass|null $proposal
 * @return string
 */
function local_flwcupkp_calproposal_form(\stdClass $snapshot, string $targettype, array $thresholds, ?array $preview,
        ?\stdClass $proposal): string {
    $html = html_writer::tag('h3', get_string('draftthresholds', 'local_flwcupkp'));
    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/calibration_proposal.php', [
            'snapshotid' => (int)$snapshot->id,
            'targettype' => $targettype,
            'proposalid' => $proposal ? (int)$proposal->id : 0,
        ]),
        'class' => 'local-flwcupkp-editform',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    $html .= html_writer::tag('strong', get_string('targettype', 'local_flwcupkp') . ': ' . s($targettype));
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-chiprow']);
    foreach (\local_flwcupkp\local\calibration_proposal::target_types() as $type) {
        $html .= html_writer::link(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
            'snapshotid' => (int)$snapshot->id,
            'targettype' => $type,
        ]), s($type), ['class' => 'local-flwcupkp-chip']);
    }
    $html .= html_writer::end_tag('div');
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targettype', 'value' => $targettype]);
    $html .= html_writer::end_tag('div');

    foreach (\local_flwcupkp\local\calibration_proposal::fields($targettype) as $field) {
        $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
        $html .= html_writer::label(s($field), 'id_' . $field);
        $html .= html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => $field,
            'id' => 'id_' . $field,
            'min' => 0,
            'max' => 1,
            'step' => '0.01',
            'value' => format_float((float)($thresholds[$field] ?? 0), 2),
        ]);
        $html .= html_writer::end_tag('div');
    }

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    $html .= html_writer::label(get_string('proposalname', 'local_flwcupkp'), 'id_proposalname');
    $html .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'proposalname',
        'id' => 'id_proposalname',
        'maxlength' => 120,
        'value' => $proposal ? s($proposal->name) : '',
    ]);
    $html .= html_writer::end_tag('div');

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    $html .= html_writer::label(get_string('proposalnote', 'local_flwcupkp'), 'id_proposalnote');
    $html .= html_writer::tag('textarea', $proposal ? s($proposal->note) : '', [
        'name' => 'proposalnote',
        'id' => 'id_proposalnote',
        'rows' => 3,
    ]);
    $html .= html_writer::end_tag('div');

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
    $html .= html_writer::tag('button', get_string('previewproposal', 'local_flwcupkp'), [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'preview',
        'class' => 'btn btn-secondary',
    ]);
    $html .= html_writer::tag('button', get_string('saveproposal', 'local_flwcupkp'), [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'saveproposal',
        'class' => 'btn btn-primary',
    ]);
    $html .= html_writer::end_tag('div');
    $html .= html_writer::end_tag('form');

    if ($proposal && (string)$proposal->status !== 'activated' && has_capability('local/flwcupkp:synccompetencies',
            context_system::instance())) {
        $html .= local_flwcupkp_calproposal_activation_form($proposal);
    } else if ($proposal && (string)$proposal->status === 'activated') {
        $html .= html_writer::tag('p', get_string('proposalalreadyactivated', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-queue-complete']);
    }

    return $html;
}

/**
 * Render proposal activation controls.
 *
 * @param \stdClass $proposal
 * @return string
 */
function local_flwcupkp_calproposal_activation_form(\stdClass $proposal): string {
    $html = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/calibration_proposal.php', [
            'snapshotid' => (int)$proposal->snapshotid,
            'targettype' => (string)$proposal->targettype,
            'proposalid' => (int)$proposal->id,
        ]),
        'class' => 'local-flwcupkp-actionform local-flwcupkp-calibration-activation',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'proposalid', 'value' => (int)$proposal->id]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'activateproposal']);
    $html .= html_writer::tag('label',
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'confirmactivation', 'value' => 1]) . ' ' .
        get_string('confirmproposalreview', 'local_flwcupkp'));
    $html .= html_writer::tag('button', get_string('activateproposal', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-danger',
    ]);
    $html .= html_writer::end_tag('form');
    return $html;
}

/**
 * Render preview summary and transition table.
 *
 * @param array $preview
 * @return string
 */
function local_flwcupkp_calproposal_preview(array $preview): string {
    $html = html_writer::tag('h3', get_string('proposalpreview', 'local_flwcupkp'));
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-overview-grid']);
    foreach ([
        'total_states' => get_string('masterystates', 'local_flwcupkp'),
        'changed_states' => get_string('changedstates', 'local_flwcupkp'),
        'strong_current' => get_string('currentstrongstates', 'local_flwcupkp'),
        'strong_proposed' => get_string('proposedstrongstates', 'local_flwcupkp'),
        'strong_delta' => get_string('delta', 'local_flwcupkp'),
    ] as $key => $label) {
        $html .= html_writer::tag('span',
            html_writer::tag('strong', (string)(int)($preview[$key] ?? 0)) .
            html_writer::tag('em', s($label)),
            ['class' => 'local-flwcupkp-course-overview-stat']
        );
    }
    $html .= html_writer::end_tag('div');
    $html .= local_flwcupkp_calproposal_count_table(get_string('currentoutcomes', 'local_flwcupkp'),
        $preview['current_outcomes'] ?? []);
    $html .= local_flwcupkp_calproposal_count_table(get_string('proposedoutcomes', 'local_flwcupkp'),
        $preview['proposed_outcomes'] ?? []);
    $html .= local_flwcupkp_calproposal_count_table(get_string('statetransitions', 'local_flwcupkp'),
        $preview['transitions'] ?? []);
    return $html;
}

/**
 * Render a simple key/count table.
 *
 * @param string $heading
 * @param array $counts
 * @return string
 */
function local_flwcupkp_calproposal_count_table(string $heading, array $counts): string {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [$heading, get_string('count', 'local_flwcupkp')];
    foreach ($counts as $label => $count) {
        $table->data[] = [s((string)$label), (int)$count];
    }
    if (!$table->data) {
        $table->data[] = [get_string('noevidenceyet', 'local_flwcupkp'), 0];
    }
    return html_writer::table($table);
}

/**
 * Render saved proposals for the selected snapshot.
 *
 * @param int $snapshotid
 * @param string $targettype
 * @return string
 */
function local_flwcupkp_calproposal_saved_table(int $snapshotid, string $targettype): string {
    $proposals = \local_flwcupkp\local\calibration_proposal::proposals_for_snapshot($snapshotid);
    $html = html_writer::tag('h3', get_string('savedthresholdproposals', 'local_flwcupkp'));
    if (!$proposals) {
        return $html . html_writer::tag('p', get_string('nothresholdproposals', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('proposalname', 'local_flwcupkp'),
        get_string('targettype', 'local_flwcupkp'),
        get_string('version', 'local_flwcupkp'),
        get_string('state', 'local_flwcupkp'),
        get_string('changedstates', 'local_flwcupkp'),
        get_string('timecreated', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
    ];
    foreach ($proposals as $proposal) {
        $preview = \local_flwcupkp\local\calibration_proposal::proposal_preview($proposal);
        $table->data[] = [
            s($proposal->name),
            s($proposal->targettype),
            s($proposal->version),
            s($proposal->status),
            (int)($preview['changed_states'] ?? 0),
            userdate((int)$proposal->timecreated),
            html_writer::link(new moodle_url('/local/flwcupkp/calibration_proposal.php', [
                'snapshotid' => $snapshotid,
                'targettype' => $targettype,
                'proposalid' => (int)$proposal->id,
            ]), get_string('openproposalworkflow', 'local_flwcupkp')),
        ];
    }
    return $html . html_writer::table($table);
}

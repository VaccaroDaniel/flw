<?php
// Learner evaluation page for local_flwcupkp.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$periodid = optional_param('periodid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = $courseid > 0 ? context_course::instance($courseid) : context_system::instance();
require_login($courseid > 0 ? $courseid : null);

$canviewreports = $courseid > 0 && has_capability('local/flwcupkp:viewreports', $context);
$learners = $canviewreports ? \local_flwcupkp\local\learner_evaluation::course_learners($courseid, $unitcode) : [];

if ($userid <= 0) {
    if ($canviewreports && !empty($learners)) {
        $firstlearner = reset($learners);
        $userid = (int)$firstlearner->id;
    } else {
        $userid = (int)$USER->id;
    }
}
if ((int)$USER->id === $userid) {
    require_capability('local/flwcupkp:viewlearnerpath', $context);
} else {
    require_capability('local/flwcupkp:viewreports', $context);
}

$url = new moodle_url('/local/flwcupkp/evaluation.php', [
    'courseid' => $courseid,
    'userid' => $userid,
    'periodid' => $periodid,
    'unitcode' => $unitcode,
]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('learnerevaluation', 'local_flwcupkp'));
$PAGE->set_heading(get_string('learnerevaluation', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    if ($action === 'snapshot') {
        require_capability('local/flwcupkp:viewlearnerpath', $context);
        \local_flwcupkp\local\learner_evaluation::create_snapshot($userid, $courseid, 0, $periodid, 'unit', 0,
            $unitcode);
        redirect($url, get_string('evaluationsnapshotcreated', 'local_flwcupkp'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'selfeval') {
        if ((int)$USER->id !== $userid && !has_capability('local/flwcupkp:override', $context)) {
            throw new required_capability_exception($context, 'local/flwcupkp:override', 'nopermissions', '');
        }
        $targetkey = required_param('targetkey', PARAM_TEXT);
        [$targettype, $targetid] = array_pad(explode(':', $targetkey, 2), 2, '');
        \local_flwcupkp\local\learner_evaluation::record_self_evaluation(
            $userid,
            $courseid,
            $periodid,
            $targettype,
            (int)$targetid,
            optional_param('selfrating', 0, PARAM_FLOAT),
            optional_param('reflection', '', PARAM_RAW_TRIMMED)
        );
        redirect($url, get_string('selfevaluationsaved', 'local_flwcupkp'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$profile = \local_flwcupkp\local\learner_evaluation::profile($userid, $courseid, $periodid, $unitcode);
$periods = \local_flwcupkp\local\learner_evaluation::periods($courseid, 0, $unitcode, '');
$learner = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('learnerevaluation', 'local_flwcupkp'));

$showperformance = $courseid > 0 && $unitcode !== '' && has_capability('local/flwcupkp:override', $context) &&
    \local_flwcupkp\local\performance_service::has_tasks($courseid, $unitcode);
echo \local_flwcupkp\local\visuals::unit_nav($courseid, $unitcode, $userid, $canviewreports, $showperformance);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'), get_string('pluginname', 'local_flwcupkp'), [
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-flwcupkp-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
if ($learners) {
    $options = [];
    foreach ($learners as $row) {
        $options[(int)$row->id] = fullname($row);
    }
    echo html_writer::label(get_string('learner', 'local_flwcupkp'), 'id_userid', false, ['class' => 'local-flwcupkp-filter']);
    echo html_writer::select($options, 'userid', $userid, false, ['id' => 'id_userid']);
}
if ($periods) {
    $periodoptions = [0 => get_string('liveprofile', 'local_flwcupkp')];
    foreach ($periods as $period) {
        $periodoptions[(int)$period->id] = (string)$period->name;
    }
    echo html_writer::label(get_string('evaluationperiod', 'local_flwcupkp'), 'id_periodid', false,
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::select($periodoptions, 'periodid', $periodid, false, ['id' => 'id_periodid']);
}
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-secondary',
    'value' => get_string('refreshstatus', 'local_flwcupkp'),
]);
echo html_writer::end_tag('form');

$summary = $profile['summary'];
echo html_writer::start_tag('div', [
    'class' => 'local-flwcupkp-evaluation-hero',
    'id' => 'local-flwcupkp-evaluation-summary',
]);
echo html_writer::tag('h3', s(fullname($learner)));
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('learningpoints', 'local_flwcupkp') . ': ' .
    (int)$summary['kp_mastered'] . '/' . (int)$summary['kp_total']);
echo html_writer::tag('span', get_string('upsdemonstrated', 'local_flwcupkp') . ': ' .
    (int)$summary['up_demonstrated'] . '/' . (int)$summary['up_total']);
echo html_writer::tag('span', get_string('competenciesachieved', 'local_flwcupkp') . ': ' .
    (int)$summary['competency_achieved'] . '/' . (int)$summary['competency_total']);
echo html_writer::tag('span', get_string('diagnostics', 'local_flwcupkp') . ': ' .
    (int)$summary['diagnostic_count']);
echo html_writer::tag('span', get_string('averageconfidence', 'local_flwcupkp') . ': ' .
    round((float)$summary['average_confidence'], 2));
echo html_writer::end_tag('div');
if (!empty($summary['cefr_interpretation'])) {
    echo html_writer::tag('p', s($summary['cefr_interpretation']), ['class' => 'local-flwcupkp-muted']);
}
echo html_writer::end_tag('div');

echo \local_flwcupkp\local\visuals::evaluation_next_action($profile, $courseid, $unitcode, $userid);
echo \local_flwcupkp\local\visuals::evaluation_rings($summary);
echo \local_flwcupkp\local\visuals::diagnostic_chart($profile['diagnostics']);
echo \local_flwcupkp\local\visuals::diagnostic_cards($profile['diagnostics']);
if ($courseid > 0 && $unitcode !== '') {
    echo \local_flwcupkp\local\visuals::hierarchy_map($courseid, $unitcode, $userid);
}
echo \local_flwcupkp\local\visuals::evaluation_timeline($profile);

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-flwcupkp-inlineform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'periodid', 'value' => $periodid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'snapshot']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('createevaluationsnapshot', 'local_flwcupkp'),
]);
echo html_writer::end_tag('form');

if (!empty($profile['latest_snapshot'])) {
    $snapshot = $profile['latest_snapshot'];
    echo html_writer::tag('p', get_string('latestevaluationsnapshot', 'local_flwcupkp') . ': #' .
        (int)$snapshot['id'] . ' ' . userdate((int)$snapshot['timecreated']) . ' ' .
        s((string)$snapshot['checksum']), ['class' => 'local-flwcupkp-muted']);
}

$targetoptions = \local_flwcupkp\local\learner_evaluation::target_options($userid, $courseid, 0, $unitcode);
if ($targetoptions) {
    $selfevalhtml = html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-flwcupkp-evaluation-selfeval']);
    $selfevalhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $selfevalhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    $selfevalhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
    $selfevalhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'periodid', 'value' => $periodid]);
    $selfevalhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
    $selfevalhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'selfeval']);
    $selfevalhtml .= html_writer::label(get_string('target', 'local_flwcupkp'), 'id_targetkey');
    $selfevalhtml .= html_writer::select($targetoptions, 'targetkey', key($targetoptions), false, ['id' => 'id_targetkey']);
    $selfevalhtml .= html_writer::label(get_string('selfrating', 'local_flwcupkp'), 'id_selfrating');
    $selfevalhtml .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'selfrating',
        'id' => 'id_selfrating',
        'min' => '0',
        'max' => '1',
        'step' => '0.05',
        'value' => '0.75',
    ]);
    $selfevalhtml .= html_writer::label(get_string('reflection', 'local_flwcupkp'), 'id_reflection');
    $selfevalhtml .= html_writer::tag('textarea', '', ['name' => 'reflection', 'id' => 'id_reflection', 'rows' => 2]);
    $selfevalhtml .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
        'value' => get_string('recordselfevaluation', 'local_flwcupkp'),
    ]);
    $selfevalhtml .= html_writer::end_tag('form');

    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('selfevaluation', 'local_flwcupkp'),
        $selfevalhtml,
        (int)$summary['self_eval_count'] === 0,
        'local-flwcupkp-selfeval-panel'
    );
}

if (empty($profile['diagnostics'])) {
    echo html_writer::tag('p', get_string('nodiagnostics', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
} else {
    echo html_writer::span('', 'local-flwcupkp-anchor', ['id' => 'local-flwcupkp-diagnostics']);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('target', 'local_flwcupkp'),
        get_string('category', 'local_flwcupkp'),
        get_string('reason', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
    ];
    foreach ($profile['diagnostics'] as $diagnostic) {
        $table->data[] = [
            s(\local_flwcupkp\local\visuals::target_label((string)$diagnostic->targettype, (int)$diagnostic->targetid)),
            s(\local_flwcupkp\local\visuals::diagnostic_label((string)$diagnostic->gapcategory)),
            s($diagnostic->diagnosticreason),
            round((float)$diagnostic->confidence, 2),
        ];
    }
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('diagnostics', 'local_flwcupkp') . ' (' . count($profile['diagnostics']) . ')',
        html_writer::table($table)
    );
}

if (empty($profile['recommendations'])) {
    echo html_writer::tag('p', get_string('norecommendations', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
} else {
    echo html_writer::span('', 'local-flwcupkp-anchor', ['id' => 'local-flwcupkp-recommendations']);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('target', 'local_flwcupkp'),
        get_string('reason', 'local_flwcupkp'),
        get_string('expectedbenefit', 'local_flwcupkp'),
    ];
    foreach ($profile['recommendations'] as $recommendation) {
        $table->data[] = [
            s(\local_flwcupkp\local\visuals::target_label((string)($recommendation->targettype ?? ''),
                (int)($recommendation->targetid ?? 0))),
            s($recommendation->reason ?? ''),
            s($recommendation->expectedbenefit ?? ''),
        ];
    }
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('recommendations', 'local_flwcupkp') . ' (' . count($profile['recommendations']) . ')',
        html_writer::table($table)
    );
}

echo $OUTPUT->footer();

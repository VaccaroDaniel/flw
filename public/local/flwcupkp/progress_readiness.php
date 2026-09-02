<?php
// Program 3 Gate A5C Progress and Goal Readiness page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = max(1, min(300, optional_param('limit', 100, PARAM_INT)));

$course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
require_login($course ?: null, false);
if (isguestuser()) {
    $wantsurl = new moodle_url('/local/flwcupkp/progress_readiness.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'frameworkid' => $frameworkid,
        'userid' => $userid,
        'limit' => $limit,
    ]);
    redirect(new moodle_url('/login/index.php', ['wantsurl' => $wantsurl->out(false)]));
}

global $USER;

$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
$canreport = has_capability('local/flwcupkp:viewreports', $context);
$canviewpath = has_capability('local/flwcupkp:viewlearnerpath', $context);
if (!$canreport && !$canviewpath) {
    require_capability('local/flwcupkp:viewlearnerpath', $context);
}
$targetuserid = $canreport ? $userid : (int)$USER->id;

$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
    'limit' => $limit,
];
$PAGE->set_url(new moodle_url('/local/flwcupkp/progress_readiness.php', $baseparams));
$PAGE->set_context($context);
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_title(get_string('progressreadinessa5c', 'local_flwcupkp'));
$PAGE->set_heading(get_string('progressreadinessa5c', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\progress_goal_readiness_service::status($courseid, $unitcode, $frameworkid);
$learner = null;
$learnererror = null;
if ($targetuserid > 0) {
    try {
        $learner = \local_flwcupkp\local\progress_goal_readiness_service::learner_progress(
            $targetuserid, $courseid, $unitcode, $frameworkid, $limit
        );
    } catch (Throwable $e) {
        $learnererror = $e->getMessage();
    }
}
$classsummary = null;
if ($courseid > 0 && $canreport && $targetuserid <= 0) {
    $classsummary = \local_flwcupkp\local\progress_goal_readiness_service::class_summary(
        $courseid, $unitcode, $frameworkid, $limit
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('progressreadinessa5c', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('progressreadinessa5cintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/adaptive_path.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('adaptivepatha5', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/trajectory_simulation.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('trajectorysimulationa5b', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

$statusclass = ($status['status'] ?? '') === 'ready' ? 'local-flwcupkp-badge-ok' : 'local-flwcupkp-badge-warning';
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-metric-grid']);
echo html_writer::div(
    html_writer::tag('strong', get_string('progresscontractstatus', 'local_flwcupkp')) .
    html_writer::div(s((string)$status['status']), 'local-flwcupkp-metric-value ' . $statusclass) .
    html_writer::tag('span', get_string('progresscriteriadetail', 'local_flwcupkp',
        (object)$status['criteria_summary']), ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('separatemetrics', 'local_flwcupkp')) .
    html_writer::div('4', 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('separatemetricsdetail', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('progresspolicyversion', 'local_flwcupkp')) .
    html_writer::div(s((string)$status['policy']['version']), 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('semanticpercentage', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('nextgate', 'local_flwcupkp')) .
    html_writer::div(s((string)$status['next_allowed_gate']), 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('progressnextgatedetail', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::end_tag('div');

if ($learnererror !== null) {
    echo $OUTPUT->notification($learnererror, \core\output\notification::NOTIFY_WARNING);
} else if ($learner) {
    $progress = $learner['progress'];
    $preferred = $progress['preferred_learner_metric'];
    $achievement = $progress['goal_achievement'];
    echo $OUTPUT->heading(get_string('learnerprogressreadiness', 'local_flwcupkp'), 3);
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-panel mb-4']);
    echo html_writer::tag('h4', get_string('preferredlearnermetric', 'local_flwcupkp'));
    $preferredvalue = $preferred['percentage'] === null ? (string)$preferred['milestone'] :
        format_float((float)$preferred['percentage'], 1) . '%';
    echo html_writer::tag('p', s($preferredvalue), ['class' => 'local-flwcupkp-metric-value']);
    echo html_writer::tag('p', get_string('goalachievementstate', 'local_flwcupkp', (object)[
        'state' => $achievement['achieved'] ? get_string('yes') : get_string('no'),
        'milestone' => $achievement['milestone'],
    ]), ['class' => 'local-flwcupkp-muted']);
    echo html_writer::end_tag('section');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-metric-grid']);
    foreach ($progress['metrics'] as $code => $metric) {
        $value = $metric['percentage'] === null ? get_string('qualitativeonly', 'local_flwcupkp') :
            format_float((float)$metric['percentage'], 1) . '%';
        echo html_writer::div(
            html_writer::tag('strong', get_string('metric_' . $code, 'local_flwcupkp')) .
            html_writer::div(s($value), 'local-flwcupkp-metric-value') .
            html_writer::tag('span', get_string('metricfraction', 'local_flwcupkp', (object)[
                'numerator' => format_float((float)$metric['numerator'], 2),
                'denominator' => format_float((float)$metric['denominator'], 2),
                'gaps' => count($metric['mandatory_gaps']),
            ]), ['class' => 'local-flwcupkp-muted']),
            'local-flwcupkp-metric-card'
        );
    }
    echo html_writer::end_tag('div');

    echo $OUTPUT->heading(get_string('goalachievementconditions', 'local_flwcupkp'), 3);
    $conditiontable = new html_table();
    $conditiontable->head = [get_string('condition', 'local_flwcupkp'), get_string('status')];
    foreach ($achievement['conditions'] as $condition => $pass) {
        $conditiontable->data[] = [
            s(str_replace('_', ' ', $condition)),
            $pass ? get_string('pass', 'local_flwcupkp') : get_string('fail', 'local_flwcupkp'),
        ];
    }
    echo html_writer::table($conditiontable);

    echo $OUTPUT->heading(get_string('requirementdetails', 'local_flwcupkp'), 3);
    $requirementtable = new html_table();
    $requirementtable->head = [
        get_string('target', 'local_flwcupkp'),
        get_string('status'),
        get_string('mastery', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
        get_string('evidence', 'local_flwcupkp'),
        get_string('retention', 'local_flwcupkp'),
        get_string('goalreadiness', 'local_flwcupkp'),
    ];
    foreach ($progress['requirements']['details'] as $row) {
        $target = $row['target'];
        $label = trim((string)($target['externalid'] ?? '') . ' ' . (string)($target['title'] ?? ''));
        if ($label === '') {
            $label = $target['type'] . ':' . $target['id'];
        }
        $requirementtable->data[] = [
            s($label),
            s((string)$row['gap_status']),
            format_float((float)$row['mastery_score'] * 100, 1) . '%',
            format_float((float)$row['confidence'] * 100, 1) . '%',
            (string)$row['evidence_count'],
            s((string)$row['retention_state']),
            format_float((float)$row['readiness'] * 100, 1) . '%',
        ];
    }
    echo html_writer::table($requirementtable);
} else if ($classsummary) {
    $summary = $classsummary['summary'];
    echo $OUTPUT->heading(get_string('classprogressreadiness', 'local_flwcupkp'), 3);
    echo html_writer::tag('p', get_string('classprogressreadinessdetail', 'local_flwcupkp', (object)$summary), [
        'class' => 'local-flwcupkp-muted',
    ]);
    $table = new html_table();
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('preferredlearnermetric', 'local_flwcupkp'),
        get_string('milestone', 'local_flwcupkp'),
        get_string('goalachieved', 'local_flwcupkp'),
        get_string('actions'),
    ];
    foreach ($classsummary['learners'] as $row) {
        if (!empty($row['error'])) {
            $table->data[] = [s((string)$row['learner']['fullname']), s((string)$row['error']), '', '', ''];
            continue;
        }
        $preferred = $row['preferred_metric'];
        $value = $preferred['percentage'] === null ? get_string('qualitativeonly', 'local_flwcupkp') :
            format_float((float)$preferred['percentage'], 1) . '%';
        $viewurl = new moodle_url('/local/flwcupkp/progress_readiness.php', array_merge($baseparams, [
            'userid' => $row['userid'],
        ]));
        $table->data[] = [
            s((string)$row['learner']['fullname']),
            s($value),
            s((string)$row['goal_achievement']['milestone']),
            $row['goal_achievement']['achieved'] ? get_string('yes') : get_string('no'),
            html_writer::link($viewurl, get_string('view')),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('progresschooselearner', 'local_flwcupkp'),
        \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();

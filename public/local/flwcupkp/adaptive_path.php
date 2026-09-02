<?php
// Program 3 Gate A5 Continuous Adaptive Path Engine page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
require_login($course ?: null, false);
if (isguestuser()) {
    $wantsurl = new moodle_url('/local/flwcupkp/adaptive_path.php', [
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
$canapply = has_capability('local/flwcupkp:override', $context) ||
    has_capability('local/flwcupkp:manageframeworks', $systemcontext);
if (!$canreport && !$canviewpath) {
    require_capability('local/flwcupkp:viewlearnerpath', $context);
}

$targetuserid = $canreport ? $userid : (int)$USER->id;
$limit = max(1, min(300, $limit));
$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
    'limit' => $limit,
];

$PAGE->set_url(new moodle_url('/local/flwcupkp/adaptive_path.php', $baseparams));
$PAGE->set_context($context);
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_title(get_string('adaptivepatha5', 'local_flwcupkp'));
$PAGE->set_heading(get_string('adaptivepatha5', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$notice = null;
$noticeclass = \core\output\notification::NOTIFY_SUCCESS;
if ($action !== '') {
    require_sesskey();
    if (!$canapply) {
        require_capability('local/flwcupkp:override', $context);
    }
    try {
        if ($action === 'apply' && $targetuserid > 0) {
            $result = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
                $targetuserid, $courseid, $unitcode, $frameworkid, $limit,
                get_string('adaptivepathuiapplyreason', 'local_flwcupkp')
            );
            $notice = get_string('adaptivepathapplyresult', 'local_flwcupkp', (object)[
                'status' => $result['status'],
                'id' => $result['recommendationid'],
                'superseded' => $result['superseded'],
            ]);
        } else if ($action === 'applyclass' && $courseid > 0 && $canreport) {
            $result = \local_flwcupkp\local\adaptive_path_engine_service::apply_class_paths(
                $courseid, $unitcode, $frameworkid, $limit,
                get_string('adaptivepathuiclassapplyreason', 'local_flwcupkp')
            );
            $notice = get_string('adaptivepathclassapplyresult', 'local_flwcupkp', (object)$result['summary']);
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
        $noticeclass = \core\output\notification::NOTIFY_ERROR;
    }
}

$status = \local_flwcupkp\local\adaptive_path_engine_service::status(
    $courseid, $unitcode, $frameworkid, $limit
);
$path = null;
$patherror = null;
if ($targetuserid > 0) {
    try {
        $path = \local_flwcupkp\local\adaptive_path_engine_service::learner_path(
            $targetuserid, $courseid, $unitcode, $frameworkid, $limit
        );
    } catch (Throwable $e) {
        $patherror = $e->getMessage();
    }
}
$classsummary = $courseid > 0 && $canreport ?
    \local_flwcupkp\local\adaptive_path_engine_service::class_summary(
        $courseid, $unitcode, $frameworkid, min(100, $limit)
    ) : null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adaptivepatha5', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('adaptivepatha5intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/activity_resolution.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('activityresolutiona4b', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/initial_path.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('initialpatha4', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

if ($notice !== null) {
    echo $OUTPUT->notification($notice, $noticeclass);
}

$statusclass = ($status['status'] ?? '') === 'ready' ? 'local-flwcupkp-badge-ok' : 'local-flwcupkp-badge-warning';
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-metric-grid']);
echo html_writer::div(
    html_writer::tag('strong', get_string('adaptivepathstatus', 'local_flwcupkp')) .
    html_writer::div(s((string)$status['status']), 'local-flwcupkp-metric-value ' . $statusclass) .
    html_writer::tag('span', get_string('adaptivepathcriteriadetail', 'local_flwcupkp',
        (object)$status['criteria_summary']), ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('adaptivepathcurrent', 'local_flwcupkp')) .
    html_writer::div((string)$status['recommendations']['current'], 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('adaptivepathhistorydetail', 'local_flwcupkp',
        (object)$status['recommendations']), ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('adaptivepathwriteboundary', 'local_flwcupkp')) .
    html_writer::div('2', 'local-flwcupkp-metric-value') .
    html_writer::tag('span', implode(', ', $status['write_boundary']), ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('nextgate', 'local_flwcupkp')) .
    html_writer::div(s((string)$status['next_allowed_gate']), 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('adaptivepathnextgatedetail', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::end_tag('div');

if ($patherror !== null) {
    echo $OUTPUT->notification($patherror, \core\output\notification::NOTIFY_ERROR);
} else if ($path) {
    $recommendation = $path['recommendation'];
    $activity = $recommendation['selected_activity'];
    $target = $recommendation['selected_target'];
    echo $OUTPUT->heading(get_string('currentadaptivepath', 'local_flwcupkp'), 3);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-grid local-flwcupkp-grid-2']);
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-panel']);
    echo html_writer::tag('h4', s((string)$recommendation['action']));
    echo html_writer::tag('p', s((string)$recommendation['reason']));
    echo html_writer::tag('p', get_string('adaptivepathpersistencestate', 'local_flwcupkp',
        s((string)$path['recommendation_status'])), ['class' => 'local-flwcupkp-muted']);
    if ($target) {
        echo html_writer::tag('p', get_string('adaptivepathselectedtarget', 'local_flwcupkp',
            s((string)($target['title'] ?? $target['externalid'] ?? ''))));
    }
    if ($activity) {
        $label = get_string('adaptivepathselectedactivity', 'local_flwcupkp',
            s((string)($activity['title'] ?? '')));
        echo !empty($activity['url']) ? html_writer::link($activity['url'], $label,
            ['class' => 'btn btn-primary']) : html_writer::tag('p', $label);
    } else {
        echo $OUTPUT->notification(get_string('adaptivepathdiagnosticrequired', 'local_flwcupkp'),
            \core\output\notification::NOTIFY_WARNING);
    }
    if ($canapply) {
        $formurl = new moodle_url('/local/flwcupkp/adaptive_path.php', array_merge($baseparams, [
            'action' => 'apply',
            'sesskey' => sesskey(),
        ]));
        $buttonlabel = $path['recommendation_status'] === 'current' ?
            get_string('adaptivepathconfirmcurrent', 'local_flwcupkp') :
            get_string('adaptivepathapply', 'local_flwcupkp');
        echo html_writer::div($OUTPUT->single_button($formurl, $buttonlabel, 'post'), 'mt-3');
    }
    echo html_writer::end_tag('section');

    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-panel']);
    echo html_writer::tag('h4', get_string('adaptivepathexplainability', 'local_flwcupkp'));
    $explain = new html_table();
    $explain->head = [get_string('field', 'local_flwcupkp'), get_string('value', 'local_flwcupkp')];
    $explain->data = [
        [get_string('adaptivepathsourcehash', 'local_flwcupkp'), s((string)$recommendation['sourcehash'])],
        [get_string('adaptivepathdecisioncode', 'local_flwcupkp'), s((string)$recommendation['decision_code'])],
        [get_string('adaptivepathreasoncodes', 'local_flwcupkp'), s(implode(', ', $recommendation['reason_codes']))],
        [get_string('adaptivepathresolutionhash', 'local_flwcupkp'),
            s((string)($recommendation['candidate_summary']['resolution_hash'] ?? ''))],
    ];
    echo html_writer::table($explain);
    echo html_writer::end_tag('section');
    echo html_writer::end_tag('div');
} else {
    echo $OUTPUT->notification(get_string('adaptivepathchooselearner', 'local_flwcupkp'),
        \core\output\notification::NOTIFY_INFO);
}

if ($classsummary) {
    echo $OUTPUT->heading(get_string('classadaptivepathsummary', 'local_flwcupkp'), 3);
    echo html_writer::tag('p', get_string('classadaptivepathsummaryintro', 'local_flwcupkp',
        (object)$classsummary['summary']), ['class' => 'local-flwcupkp-muted']);
    if ($canapply && !empty($classsummary['learners'])) {
        $classurl = new moodle_url('/local/flwcupkp/adaptive_path.php', array_merge($baseparams, [
            'userid' => 0,
            'action' => 'applyclass',
            'sesskey' => sesskey(),
        ]));
        echo html_writer::div($OUTPUT->single_button($classurl,
            get_string('adaptivepathapplyclass', 'local_flwcupkp'), 'post'), 'mb-3');
    }
    $table = new html_table();
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('status'),
        get_string('adaptivepathaction', 'local_flwcupkp'),
        get_string('adaptivepathnext', 'local_flwcupkp'),
        get_string('actions'),
    ];
    foreach ($classsummary['learners'] as $row) {
        $identity = $row['learner'] ?? [];
        $name = (string)($identity['fullname'] ?? $identity['name'] ?? ('#' . $row['userid']));
        $next = !empty($row['selected_activity']['title']) ? (string)$row['selected_activity']['title'] :
            get_string('adaptivepathdiagnosticshort', 'local_flwcupkp');
        $viewurl = new moodle_url('/local/flwcupkp/adaptive_path.php',
            array_merge($baseparams, ['userid' => $row['userid']]));
        $table->data[] = [
            s($name),
            s((string)$row['recommendation_status']),
            s((string)($row['action'] ?? '')),
            s($next),
            html_writer::link($viewurl, get_string('view')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

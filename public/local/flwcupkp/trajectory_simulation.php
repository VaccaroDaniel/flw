<?php
// Program 3 Gate A5B Trajectory Simulation and Invariant Testing page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$seed = optional_param('seed', 'flw-cupkp-a5b-v1', PARAM_TEXT);
$trajectorycount = optional_param('trajectories', 512, PARAM_INT);
$steps = optional_param('steps', 24, PARAM_INT);
$scenario = optional_param('scenario', 'all', PARAM_ALPHANUMEXT);
$samplelimit = optional_param('samplelimit', 8, PARAM_INT);

$trajectorycount = max(1, min(2000, $trajectorycount));
$steps = max(4, min(100, $steps));
$samplelimit = max(1, min(20, $samplelimit));
$course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
require_login($course ?: null, false);
if (isguestuser()) {
    $wantsurl = new moodle_url('/local/flwcupkp/trajectory_simulation.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'frameworkid' => $frameworkid,
        'userid' => $userid,
        'seed' => $seed,
        'trajectories' => $trajectorycount,
        'steps' => $steps,
        'scenario' => $scenario,
        'samplelimit' => $samplelimit,
    ]);
    redirect(new moodle_url('/login/index.php', ['wantsurl' => $wantsurl->out(false)]));
}

$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
require_capability('local/flwcupkp:viewreports', $context);

$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
    'seed' => $seed,
    'trajectories' => $trajectorycount,
    'steps' => $steps,
    'scenario' => $scenario,
    'samplelimit' => $samplelimit,
];
$PAGE->set_url(new moodle_url('/local/flwcupkp/trajectory_simulation.php', $baseparams));
$PAGE->set_context($context);
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_title(get_string('trajectorysimulationa5b', 'local_flwcupkp'));
$PAGE->set_heading(get_string('trajectorysimulationa5b', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$scenarios = $scenario === 'all' ? [] : [$scenario];
$status = \local_flwcupkp\local\trajectory_invariant_service::status($courseid, $unitcode, $frameworkid);
$suite = \local_flwcupkp\local\trajectory_invariant_service::simulate_suite(
    $seed, $trajectorycount, $steps, $scenarios, $samplelimit
);
$projection = null;
$projectionerror = null;
if ($userid > 0) {
    try {
        $projection = \local_flwcupkp\local\trajectory_invariant_service::learner_projection(
            $userid, $courseid, $unitcode, $frameworkid, $seed, $steps
        );
    } catch (Throwable $e) {
        $projectionerror = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('trajectorysimulationa5b', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('trajectorysimulationa5bintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/adaptive_path.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
]), get_string('adaptivepatha5', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/flwcupkp/trajectory_simulation.php'))->out(false),
    'class' => 'local-flwcupkp-panel mb-4',
]);
foreach (['courseid' => $courseid, 'unitcode' => $unitcode, 'frameworkid' => $frameworkid, 'userid' => $userid]
        as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-grid local-flwcupkp-grid-2']);
echo html_writer::div(
    html_writer::tag('label', get_string('simulationseed', 'local_flwcupkp'), ['for' => 'id_seed']) .
    html_writer::empty_tag('input', [
        'id' => 'id_seed', 'name' => 'seed', 'value' => $seed, 'type' => 'text', 'class' => 'form-control',
    ])
);
$scenariooptions = ['all' => get_string('allscenarios', 'local_flwcupkp')];
foreach (\local_flwcupkp\local\trajectory_invariant_service::SCENARIOS as $option) {
    $scenariooptions[$option] = str_replace('_', ' ', ucfirst($option));
}
echo html_writer::div(
    html_writer::tag('label', get_string('scenario', 'local_flwcupkp'), ['for' => 'id_scenario']) .
    html_writer::select($scenariooptions, 'scenario', $scenario, false, ['id' => 'id_scenario', 'class' => 'form-control'])
);
echo html_writer::div(
    html_writer::tag('label', get_string('trajectories', 'local_flwcupkp'), ['for' => 'id_trajectories']) .
    html_writer::empty_tag('input', [
        'id' => 'id_trajectories', 'name' => 'trajectories', 'value' => $trajectorycount,
        'type' => 'number', 'min' => 1, 'max' => 2000, 'class' => 'form-control',
    ])
);
echo html_writer::div(
    html_writer::tag('label', get_string('stepspertrajectory', 'local_flwcupkp'), ['for' => 'id_steps']) .
    html_writer::empty_tag('input', [
        'id' => 'id_steps', 'name' => 'steps', 'value' => $steps,
        'type' => 'number', 'min' => 4, 'max' => 100, 'class' => 'form-control',
    ])
);
echo html_writer::end_tag('div');
echo html_writer::div(html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('runsimulation', 'local_flwcupkp'), 'class' => 'btn btn-primary',
]), 'mt-3');
echo html_writer::end_tag('form');

$summary = $suite['summary'];
$statusclass = $suite['global_invariants_passed'] ? 'local-flwcupkp-badge-ok' : 'local-flwcupkp-badge-warning';
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-metric-grid']);
echo html_writer::div(
    html_writer::tag('strong', get_string('globalinvariants', 'local_flwcupkp')) .
    html_writer::div(s((string)$suite['status']), 'local-flwcupkp-metric-value ' . $statusclass) .
    html_writer::tag('span', get_string('simulationpassdetail', 'local_flwcupkp', (object)$summary),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('detectors', 'local_flwcupkp')) .
    html_writer::div((string)$suite['detector_self_test']['passed'] . '/' .
        (string)$suite['detector_self_test']['total'], 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('detectorselftestdetail', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('determinism', 'local_flwcupkp')) .
    html_writer::div($suite['deterministic'] ? get_string('yes') : get_string('no'),
        'local-flwcupkp-metric-value') .
    html_writer::tag('span', s(substr((string)$suite['suite_hash'], 0, 16)), ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::div(
    html_writer::tag('strong', get_string('writeboundary', 'local_flwcupkp')) .
    html_writer::div(get_string('readonly', 'local_flwcupkp'), 'local-flwcupkp-metric-value') .
    html_writer::tag('span', get_string('trajectoryreadonlydetail', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']),
    'local-flwcupkp-metric-card'
);
echo html_writer::end_tag('div');

echo $OUTPUT->heading(get_string('invariantdetectors', 'local_flwcupkp'), 3);
$detectortable = new html_table();
$detectortable->head = [get_string('detector', 'local_flwcupkp'), get_string('status'), get_string('incidents', 'local_flwcupkp')];
foreach ($suite['detector_self_test']['detectors'] as $code => $result) {
    $detectortable->data[] = [
        s(str_replace('_', ' ', $code)),
        $result['pass'] ? get_string('pass', 'local_flwcupkp') : get_string('fail', 'local_flwcupkp'),
        (string)count($result['incidents']),
    ];
}
echo html_writer::table($detectortable);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-grid local-flwcupkp-grid-2']);
foreach ([
    get_string('scenariocoverage', 'local_flwcupkp') => $summary['scenarios'],
    get_string('actiondistribution', 'local_flwcupkp') => $summary['actions'],
    get_string('modalitydistribution', 'local_flwcupkp') => $summary['modalities'],
] as $title => $rows) {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-panel']);
    echo html_writer::tag('h4', $title);
    $table = new html_table();
    $table->head = [get_string('value', 'local_flwcupkp'), get_string('count', 'local_flwcupkp')];
    foreach ($rows as $key => $count) {
        $table->data[] = [s(str_replace('_', ' ', (string)$key)), (string)$count];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}
echo html_writer::end_tag('div');

if ($projectionerror !== null) {
    echo $OUTPUT->notification($projectionerror, \core\output\notification::NOTIFY_WARNING);
} else if ($projection) {
    echo $OUTPUT->heading(get_string('learnerprojection', 'local_flwcupkp'), 3);
    echo html_writer::tag('p', get_string('learnerprojectiondetail', 'local_flwcupkp', (object)[
        'userid' => $userid,
        'scenario' => str_replace('_', ' ', (string)$projection['selected_scenario']),
        'status' => $projection['invariants']['pass'] ? get_string('pass', 'local_flwcupkp') :
            get_string('fail', 'local_flwcupkp'),
    ]), ['class' => 'local-flwcupkp-muted']);
}

echo $OUTPUT->heading(get_string('sampletrajectories', 'local_flwcupkp'), 3);
$sampletable = new html_table();
$sampletable->head = [
    get_string('trajectory', 'local_flwcupkp'),
    get_string('scenario', 'local_flwcupkp'),
    get_string('status'),
    get_string('masterychange', 'local_flwcupkp'),
    get_string('confidencechange', 'local_flwcupkp'),
    get_string('hash', 'local_flwcupkp'),
];
foreach ($suite['samples'] as $sample) {
    $row = $sample['summary'];
    $sampletable->data[] = [
        s((string)$sample['trajectory_id']),
        s(str_replace('_', ' ', (string)$sample['scenario'])),
        $sample['pass'] ? get_string('pass', 'local_flwcupkp') : get_string('fail', 'local_flwcupkp'),
        s((string)$row['start_mastery'] . ' -> ' . (string)$row['end_mastery']),
        s((string)$row['start_confidence'] . ' -> ' . (string)$row['end_confidence']),
        s(substr((string)$sample['trajectory_hash'], 0, 16)),
    ];
}
echo html_writer::table($sampletable);

if (($status['status'] ?? '') !== 'ready') {
    echo $OUTPUT->notification(get_string('trajectorygateblocked', 'local_flwcupkp'),
        \core\output\notification::NOTIFY_WARNING);
}

echo $OUTPUT->footer();

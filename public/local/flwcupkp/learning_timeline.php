<?php
// Program 3 Gate UX1 Past, Present, and Future dashboard.

require_once(__DIR__ . '/../../config.php');

use local_flwcupkp\local\learner_experience_renderer;
use local_flwcupkp\local\learner_experience_service;

$courseid = required_param('courseid', PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$requesteduserid = optional_param('userid', 0, PARAM_INT);
$limit = max(1, min(50, optional_param('limit', 10, PARAM_INT)));
$attemptoffset = max(0, optional_param('attemptoffset', 0, PARAM_INT));
$gradeoffset = max(0, optional_param('gradeoffset', 0, PARAM_INT));
$historyoffset = max(0, optional_param('historyoffset', 0, PARAM_INT));
$activityoffset = max(0, optional_param('activityoffset', 0, PARAM_INT));

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$canreport = has_capability('local/flwcupkp:viewreports', $context);

$urlparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'limit' => $limit,
    'attemptoffset' => $attemptoffset,
    'gradeoffset' => $gradeoffset,
    'historyoffset' => $historyoffset,
    'activityoffset' => $activityoffset,
];
if ($requesteduserid > 0) {
    $urlparams['userid'] = $requesteduserid;
}
$url = new moodle_url('/local/flwcupkp/learning_timeline.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('learnerexperiencetitle', 'local_flwcupkp'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/flwcupkp/styles.css'));

echo $OUTPUT->header();

if ($canreport) {
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('course'), ['class' => 'btn btn-secondary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
        get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/progress_readiness.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'frameworkid' => $frameworkid,
        'userid' => $requesteduserid,
    ]), get_string('openprogressreadiness', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
    echo html_writer::link(new moodle_url('/local/flwhistory/dashboard.php', [
        'courseid' => $courseid,
        'userid' => $requesteduserid,
    ]), get_string('openfullhistory', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('div');
} else {
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('backtocourse', 'local_flwcupkp'), ['class' => 'local-flwcupkp-ux2-back']);
}

if ($canreport && $requesteduserid <= 0) {
    $status = learner_experience_service::status($courseid, $unitcode, $frameworkid);
    echo $OUTPUT->heading(get_string('learnerexperiencestafftitle', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('learnerexperiencestaffintro', 'local_flwcupkp'), [
        'class' => 'local-flwcupkp-muted',
    ]);
    $class = ($status['status'] ?? '') === 'ready' ? 'local-flwcupkp-badge-ok' :
        'local-flwcupkp-badge-warning';
    echo html_writer::tag('p', s((string)$status['status']), ['class' => $class]);
    $learners = \local_flwcupkp\local\unit_report::learners($courseid, $unitcode);
    if (!$learners) {
        echo $OUTPUT->notification(get_string('learningtimelinenolearners', 'local_flwcupkp'),
            \core\output\notification::NOTIFY_INFO);
    } else {
        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => (new moodle_url('/local/flwcupkp/learning_timeline.php'))->out(false),
            'class' => 'local-flwcupkp-ux1-chooser',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frameworkid', 'value' => $frameworkid]);
        echo html_writer::label(get_string('chooselearner', 'local_flwcupkp'), 'local-flwcupkp-ux1-userid');
        $options = [];
        foreach ($learners as $learner) {
            $options[(int)$learner->id] = fullname($learner);
        }
        echo html_writer::select($options, 'userid', '', ['' => get_string('select')], [
            'id' => 'local-flwcupkp-ux1-userid',
            'required' => 'required',
        ]);
        echo html_writer::tag('button', get_string('view'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
    echo $OUTPUT->footer();
    exit;
}

$historyservice = '\\local_flwhistory\\local\\dashboard_service';
if (!class_exists($historyservice) || !method_exists($historyservice, 'require_learner_access')) {
    throw new moodle_exception('timelinehistoryunavailable', 'local_flwcupkp');
}
$targetuserid = $historyservice::require_learner_access($courseid, $requesteduserid);
if ($targetuserid === (int)$USER->id) {
    require_capability('local/flwcupkp:viewlearnerpath', $context);
} else {
    require_capability('local/flwcupkp:viewreports', $context);
}

$pagination = [
    'limit' => $limit,
    'attemptoffset' => $attemptoffset,
    'gradeoffset' => $gradeoffset,
    'historyoffset' => $historyoffset,
    'activityoffset' => $activityoffset,
];
$view = learner_experience_service::learner_experience(
    $targetuserid,
    $courseid,
    $unitcode,
    $frameworkid,
    $limit,
    $pagination
);
echo learner_experience_renderer::render($view);
echo $OUTPUT->footer();

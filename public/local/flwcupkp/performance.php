<?php
// Teacher performance assessment page for generic C-UP-KP unit evidence.

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$unitcode = optional_param('unitcode', 'U038', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$mapid = optional_param('mapid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/flwcupkp:override', $context);

$tasks = \local_flwcupkp\local\performance_service::tasks($courseid, $unitcode);
$learners = \local_flwcupkp\local\performance_service::learners($courseid, $unitcode);

if ($userid <= 0 && $learners) {
    $firstlearner = reset($learners);
    $userid = (int)$firstlearner->id;
}
if ($mapid <= 0 && $tasks) {
    $firsttask = reset($tasks);
    $mapid = (int)$firsttask->mapid;
}

$url = new moodle_url('/local/flwcupkp/performance.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
if ($userid > 0) {
    $url->param('userid', $userid);
}
if ($mapid > 0) {
    $url->param('mapid', $mapid);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    $userid = required_param('targetuserid', PARAM_INT);
    $mapid = required_param('mapid', PARAM_INT);
    $note = optional_param('note', '', PARAM_TEXT);
    $task = \local_flwcupkp\local\performance_service::task($courseid, $unitcode, $mapid);
    $scores = [];
    foreach (\local_flwcupkp\local\performance_service::rubric_for_task($task) as $criterion) {
        $scores[$criterion['key']] = required_param('score_' . $criterion['key'], PARAM_FLOAT);
    }

    \local_flwcupkp\local\performance_service::record($courseid, $unitcode, $userid, $mapid, $scores, $note);
    redirect(new moodle_url('/local/flwcupkp/performance.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'userid' => $userid,
        'mapid' => $mapid,
        'status' => 'saved',
    ]));
}

$pagetitle = get_string('performanceunit', 'local_flwcupkp', $unitcode);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
echo html_writer::tag('p', s($course->fullname), ['class' => 'local-flwcupkp-muted']);

if ($status !== '') {
    echo $OUTPUT->notification(get_string('performance' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
if ($unitcode === 'U038') {
    echo html_writer::link(new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]),
        get_string('courseverificationlinku038', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid,
            'userid' => $userid]),
        get_string('courseprogresslinku038', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
} else {
    echo html_writer::link(new moodle_url('/local/flwcupkp/teacher.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]),
        get_string('courseteacherlinkunit', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-secondary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/student.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
        ]),
        get_string('courseprogresslinkunit', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_tag('div');

if (!$tasks || !$learners) {
    echo $OUTPUT->notification(get_string('performancenotready', 'local_flwcupkp', $unitcode), 'info');
    echo $OUTPUT->footer();
    exit;
}

$learneroptions = [];
foreach ($learners as $learner) {
    $learneroptions[(int)$learner->id] = fullname($learner) . ' (' . $learner->email . ')';
}

$taskoptions = [];
foreach ($tasks as $taskrecord) {
    $taskoptions[(int)$taskrecord->mapid] = 'L' . $taskrecord->lesson . ' ' .
        $taskrecord->objectexternalid . ' -> ' . $taskrecord->targettype . ':' .
        $taskrecord->targetexternalid;
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/performance.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
echo html_writer::tag('label', get_string('learner', 'local_flwcupkp') .
    html_writer::select($learneroptions, 'userid', $userid, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('performancetask', 'local_flwcupkp') .
    html_writer::select($taskoptions, 'mapid', $mapid, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

$selectedtask = \local_flwcupkp\local\performance_service::task($courseid, $unitcode, $mapid);
$selectedlearner = $learners[$userid] ?? $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
$latest = \local_flwcupkp\local\performance_service::latest_evidence($userid, $selectedtask);
$state = \local_flwcupkp\local\performance_service::state($userid, $selectedtask);
$activityurl = (!empty($selectedtask->cmid) && !empty($selectedtask->modname)) ?
    new moodle_url('/mod/' . $selectedtask->modname . '/view.php', ['id' => (int)$selectedtask->cmid]) : null;

echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-performance-panel']);
echo html_writer::tag('div', get_string('performancetask', 'local_flwcupkp'),
    ['class' => 'local-flwcupkp-course-next-label']);
echo html_writer::tag('h3', s($selectedtask->objecttitle));
echo html_writer::tag('p', s($selectedtask->targettype . ':' . $selectedtask->targetexternalid . ' - ' .
    $selectedtask->targettitle), ['class' => 'local-flwcupkp-muted']);
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('learner', 'local_flwcupkp') . ': ' .
    ($selectedlearner ? fullname($selectedlearner) : $userid));
echo html_writer::tag('span', get_string('unit', 'local_flwcupkp') . ': ' . s($unitcode));
echo html_writer::tag('span', get_string('lesson', 'local_flwcupkp') . ': ' . s($selectedtask->lesson));
echo html_writer::tag('span', get_string('type', 'local_flwcupkp') . ': ' . s($selectedtask->objecttype));
echo html_writer::tag('span', get_string('evidencestrength', 'local_flwcupkp') . ': ' .
    s($selectedtask->evidencestrength));
if ($activityurl) {
    echo html_writer::tag('span', html_writer::link($activityurl, 'CMID ' . (int)$selectedtask->cmid));
}
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-performance-status']);
echo local_flwcupkp_performance_status_card(get_string('state', 'local_flwcupkp'), $state ?
    s($state->masterystate) . html_writer::tag('div', get_string('mastery', 'local_flwcupkp') . ' ' .
    format_float((float)$state->masteryscore, 2), ['class' => 'local-flwcupkp-muted']) :
    get_string('noevidenceyet', 'local_flwcupkp'));
echo local_flwcupkp_performance_status_card(get_string('evidence', 'local_flwcupkp'), $latest ?
    get_string('score', 'local_flwcupkp') . ' ' . format_float((float)$latest->normalizedscore, 2) .
    html_writer::tag('div', userdate((int)$latest->timecreated), ['class' => 'local-flwcupkp-muted']) :
    get_string('noevidenceyet', 'local_flwcupkp'));
echo html_writer::end_tag('div');
echo html_writer::end_tag('section');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url,
    'class' => 'local-flwcupkp-editform local-flwcupkp-performance-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $userid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mapid', 'value' => $mapid]);

echo html_writer::tag('h3', get_string('rubricscores', 'local_flwcupkp'));
foreach (\local_flwcupkp\local\performance_service::rubric_for_task($selectedtask) as $criterion) {
    $inputid = 'id_score_' . $criterion['key'];
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($criterion['label'], $inputid);
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'score_' . $criterion['key'],
        'id' => $inputid,
        'min' => '0',
        'max' => '1',
        'step' => '0.01',
        'value' => '0.85',
        'required' => 'required',
    ]);
    echo html_writer::end_tag('div');
}
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
echo html_writer::label(get_string('note', 'local_flwcupkp'), 'id_note');
echo html_writer::tag('textarea', '', ['name' => 'note', 'id' => 'id_note', 'rows' => 3]);
echo html_writer::end_tag('div');
echo html_writer::tag('button', get_string('recordperformance', 'local_flwcupkp'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

/**
 * Render one compact status card.
 *
 * @param string $label
 * @param string $content
 * @return string
 */
function local_flwcupkp_performance_status_card(string $label, string $content): string {
    return html_writer::tag('div',
        html_writer::tag('strong', $label) . html_writer::tag('div', $content),
        ['class' => 'local-flwcupkp-performance-status-card']
    );
}

<?php
// Program 3 Gate UX3 teacher/admin explainability and controlled overrides page.

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course, false);
if (isguestuser()) {
    $wantsurl = new moodle_url('/local/flwcupkp/staff_intelligence.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'frameworkid' => $frameworkid,
        'userid' => $userid,
    ]);
    redirect(new moodle_url('/login/index.php', ['wantsurl' => $wantsurl->out(false)]));
}

$context = context_course::instance($courseid);
require_capability('local/flwcupkp:viewreports', $context);
$canoverride = has_capability('local/flwcupkp:override', $context);
$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
];
$baseurl = new moodle_url('/local/flwcupkp/staff_intelligence.php', $baseparams);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('staffintelligenceux3', 'local_flwcupkp'));
$PAGE->set_heading(get_string('staffintelligenceux3', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

if ($action !== '') {
    require_sesskey();
    if (!$canoverride) {
        require_capability('local/flwcupkp:override', $context);
    }
    try {
        if ($action === 'apply') {
            $targetchoice = optional_param('target', '', PARAM_RAW_TRIMMED);
            $activitychoice = optional_param('activitychoice', '', PARAM_RAW_TRIMMED);
            [$targettype, $targetid] = local_flwcupkp_ux3_pair($targetchoice);
            [$objectid, $cmid] = local_flwcupkp_ux3_pair($activitychoice, true);
            $data = [
                'targettype' => $targettype,
                'targetid' => $targetid,
                'objectid' => $objectid,
                'cmid' => $cmid,
                'actioncode' => optional_param('actioncode', '', PARAM_ALPHA),
                'score' => optional_param('score', 0, PARAM_FLOAT),
                'confidence' => optional_param('confidence', 0.75, PARAM_FLOAT),
                'title' => optional_param('goaltitle', '', PARAM_TEXT),
                'purpose' => optional_param('goalpurpose', '', PARAM_TEXT),
                'note' => optional_param('observationnote', '', PARAM_TEXT),
            ];
            $result = \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
                $userid,
                $courseid,
                $unitcode,
                $frameworkid,
                required_param('interventiontype', PARAM_ALPHANUMEXT),
                $data,
                required_param('reason', PARAM_TEXT)
            );
            redirect($baseurl, get_string('staffinterventionapplied', 'local_flwcupkp', (object)[
                'type' => ucwords(str_replace('_', ' ', (string)$result['intervention']['interventiontype'])),
                'version' => (int)$result['intervention']['version'],
            ]), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if ($action === 'release') {
            $result = \local_flwcupkp\local\staff_intelligence_service::release_intervention(
                required_param('interventionid', PARAM_INT),
                required_param('releasereason', PARAM_TEXT),
                $frameworkid
            );
            redirect($baseurl, get_string('staffinterventionreleased', 'local_flwcupkp', (object)[
                'version' => (int)$result['intervention']['version'],
            ]), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $e) {
        redirect($baseurl, get_string('staffinterventionfailed', 'local_flwcupkp', $e->getMessage()), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

$learners = \local_flwcupkp\local\unit_report::learners($courseid, $unitcode);
$learneroptions = [];
foreach ($learners as $learner) {
    $learneroptions[(int)$learner->id] = fullname($learner);
}
if ($userid > 0 && !isset($learneroptions[$userid])) {
    throw new required_capability_exception($context, 'local/flwcupkp:viewreports', 'nopermissions', '');
}

$status = \local_flwcupkp\local\staff_intelligence_service::status($courseid, $unitcode, $frameworkid);
$view = null;
$viewerror = null;
if ($userid > 0) {
    try {
        $view = \local_flwcupkp\local\staff_intelligence_service::learner_intelligence(
            $userid, $courseid, $unitcode, $frameworkid, 100
        );
    } catch (Throwable $e) {
        $viewerror = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('staffintelligenceux3', 'local_flwcupkp'));

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('backtocourse', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
if ($userid > 0) {
    echo html_writer::link(new moodle_url('/local/flwcupkp/learning_timeline.php', $baseparams),
        get_string('openlearnerexperience', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
    echo html_writer::link(new moodle_url('/local/flwcupkp/adaptive_path.php', $baseparams),
        get_string('openadaptivepath', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-status']);
echo html_writer::tag('strong', get_string('staffintelligencestatus', 'local_flwcupkp'));
echo html_writer::tag('span', s((string)$status['status']), [
    'class' => ($status['status'] ?? '') === 'ready' ?
        'local-flwcupkp-badge-ok' : 'local-flwcupkp-badge-warning',
]);
echo html_writer::tag('span', get_string('staffintelligencecriteria', 'local_flwcupkp',
    (object)$status['criteria_summary']));
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/flwcupkp/staff_intelligence.php'))->out(false),
    'class' => 'local-flwcupkp-ux3-learner-picker',
]);
foreach (['courseid' => $courseid, 'unitcode' => $unitcode, 'frameworkid' => $frameworkid] as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::label(get_string('chooselearner', 'local_flwcupkp'), 'local-flwcupkp-ux3-userid');
echo html_writer::select($learneroptions, 'userid', $userid, ['' => get_string('select')], [
    'id' => 'local-flwcupkp-ux3-userid',
]);
echo html_writer::tag('button', get_string('view'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

if ($viewerror !== null) {
    echo $OUTPUT->notification($viewerror, \core\output\notification::NOTIFY_ERROR);
} else if ($view) {
    echo \local_flwcupkp\local\staff_intelligence_renderer::render($view, $canoverride, $baseurl);
} else {
    echo $OUTPUT->notification(get_string('staffchooselearnertoview', 'local_flwcupkp'),
        \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();

/**
 * Parse a type/id or object/cmid pair from a form control.
 *
 * @param string $value
 * @param bool $numericfirst
 * @return array
 */
function local_flwcupkp_ux3_pair(string $value, bool $numericfirst = false): array {
    if ($value === '' || strpos($value, ':') === false) {
        return $numericfirst ? [0, 0] : ['', 0];
    }
    [$first, $second] = explode(':', $value, 2);
    return $numericfirst ? [max(0, (int)$first), max(0, (int)$second)] :
        [clean_param($first, PARAM_ALPHA), max(0, (int)$second)];
}

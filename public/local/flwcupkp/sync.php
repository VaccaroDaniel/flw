<?php
// Moodle competency sync review controls for C-UP-KP.

require_once(__DIR__ . '/../../config.php');

$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:synccompetencies', $context);

$url = new moodle_url('/local/flwcupkp/sync.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHANUMEXT);
    if ($action === 'dryrun') {
        \local_flwcupkp\local\repository::audit('competency_sync_reviewed', null, null, [
            'dryrun' => true,
            'writeenabled' => (bool)get_config('local_flwcupkp', 'enablesyncwrites'),
            'summary' => local_flwcupkp_sync_summary(),
        ]);
        redirect(new moodle_url('/local/flwcupkp/sync.php', ['status' => 'checked']));
    }
    if ($action === 'togglewrites') {
        $enabled = optional_param('enabled', 0, PARAM_BOOL);
        $summary = \local_flwcupkp\local\curriculum_manager::sync_readiness();
        if ($enabled && empty($summary['readyforwrites'])) {
            \local_flwcupkp\local\repository::audit('competency_sync_write_mode_blocked', null, null, [
                'enabled' => false,
                'summary' => $summary,
            ]);
            redirect(new moodle_url('/local/flwcupkp/sync.php', ['status' => 'blocked']));
        }
        set_config('enablesyncwrites', $enabled ? 1 : 0, 'local_flwcupkp');
        \local_flwcupkp\local\repository::audit('competency_sync_write_mode_changed', null, null, [
            'enabled' => $enabled,
            'summary' => $summary,
        ]);
        redirect(new moodle_url('/local/flwcupkp/sync.php', ['status' => 'saved']));
    }
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('competencysync', 'local_flwcupkp'));
$PAGE->set_heading(get_string('competencysync', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$summary = local_flwcupkp_sync_summary();
$writeenabled = (bool)get_config('local_flwcupkp', 'enablesyncwrites');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('competencysync', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), $status === 'blocked' ? 'warning' : 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('backtocurriculum', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('frameworks', 'local_flwcupkp') . ': ' . $summary['frameworks']);
echo html_writer::tag('span', get_string('competencies', 'local_flwcupkp') . ': ' . $summary['competencies']);
echo html_writer::tag('span', get_string('moodlelinkedframeworks', 'local_flwcupkp') . ': ' . $summary['linkedframeworks']);
echo html_writer::tag('span', get_string('moodlelinkedcompetencies', 'local_flwcupkp') . ': ' . $summary['linkedcompetencies']);
echo html_writer::tag('span', get_string('unlinkedframeworks', 'local_flwcupkp') . ': ' . $summary['unlinkedframeworks']);
echo html_writer::tag('span', get_string('unlinkedcompetencies', 'local_flwcupkp') . ': ' . $summary['unlinkedcompetencies']);
echo html_writer::tag('span', get_string('writemode', 'local_flwcupkp') . ': ' .
    ($writeenabled ? get_string('enabled', 'local_flwcupkp') : get_string('disabled', 'local_flwcupkp')));
echo html_writer::end_tag('div');

if (empty($summary['readyforwrites'])) {
    echo $OUTPUT->notification(get_string('syncnotreadyforwrites', 'local_flwcupkp'), 'warning');
}
if ($summary['frameworks'] > 0 && $summary['unlinkedframeworks'] > 0) {
    echo $OUTPUT->notification(get_string('syncmissingframeworklink', 'local_flwcupkp'), 'warning');
}
if ($summary['competencies'] > 0 && $summary['unlinkedcompetencies'] > 0) {
    echo $OUTPUT->notification(get_string('syncmissingcompetencylinks', 'local_flwcupkp'), 'warning');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-actionform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('rundryrun', 'local_flwcupkp'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'dryrun',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-actionform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'enabled', 'value' => $writeenabled ? 0 : 1]);
echo html_writer::tag('button', $writeenabled ? get_string('disablewrites', 'local_flwcupkp') : get_string('enablewrites', 'local_flwcupkp'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'togglewrites',
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

echo html_writer::tag('p', get_string('syncwritewarning', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);

echo $OUTPUT->footer();

/**
 * Summarize native Moodle competency sync readiness.
 */
function local_flwcupkp_sync_summary(): array {
    return \local_flwcupkp\local\curriculum_manager::sync_readiness();
}

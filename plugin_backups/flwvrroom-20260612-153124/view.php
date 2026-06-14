<?php
// View page for FLW VR Room activity.

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/flwvrroom/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module id.
$n  = optional_param('n', 0, PARAM_INT);  // Activity instance id.

if ($id) {
    $cm = get_coursemodule_from_id('flwvrroom', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $flwvrroom = $DB->get_record('flwvrroom', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $flwvrroom->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('flwvrroom', $flwvrroom->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flwvrroom:view', $context);

$PAGE->set_url('/mod/flwvrroom/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($flwvrroom->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/mod/flwvrroom/styles.css'));

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$kps = preg_split('/\r\n|\r|\n/', trim((string)$flwvrroom->knowledgepoints));
$kps = array_values(array_filter(array_map('trim', $kps)));

$config = [
    'containerid' => 'flwvrroom-app-' . $cm->id,
    'cmid' => $cm->id,
    'instanceid' => $flwvrroom->id,
    'cefrlevel' => $flwvrroom->cefrlevel,
    'scenario' => $flwvrroom->scenario,
    'passinggrade' => (int)$flwvrroom->passinggrade,
    'maxscore' => (int)$flwvrroom->grade,
    'knowledgepoints' => $kps,
];

$PAGE->requires->js_call_amd('mod_flwvrroom/room', 'init', [$config]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($flwvrroom->name));

if (trim(strip_tags($flwvrroom->intro))) {
    echo $OUTPUT->box(format_module_intro('flwvrroom', $flwvrroom, $cm->id), 'generalbox mod_introbox', 'flwvrroomintro');
}

echo $OUTPUT->render_from_template('mod_flwvrroom/room', [
    'containerid' => $config['containerid'],
    'title' => format_string($flwvrroom->name),
    'cefrlevel' => s($flwvrroom->cefrlevel),
    'scenario' => s($flwvrroom->scenario),
    'passinggrade' => (int)$flwvrroom->passinggrade,
    'knowledgepoints' => array_map(function($kp) { return ['code' => s($kp)]; }, $kps),
]);

echo $OUTPUT->footer();

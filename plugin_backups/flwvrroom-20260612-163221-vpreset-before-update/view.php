<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('flwvrroom', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/flwvrroom:view', $context);

$PAGE->set_url('/mod/flwvrroom/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($flwvrroom->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/flwvrroom/styles.css');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$bestgrade = flwvrroom_get_user_grade($flwvrroom, $USER->id);
$bestscore = $bestgrade ? (int) $bestgrade->rawgrade : 0;

$kpcodes = preg_split('/\R+/', trim((string) $flwvrroom->kpcodes));
$kpcodes = array_values(array_filter(array_map('trim', $kpcodes)));

$templatecontext = [
    'uniqid' => 'flwvrroom-' . $cm->id,
    'name' => format_string($flwvrroom->name),
    'intro' => format_module_intro('flwvrroom', $flwvrroom, $cm->id),
    'cefrlevel' => s($flwvrroom->cefrlevel),
    'scenario' => s($flwvrroom->scenario),
    'passinggrade' => (int) $flwvrroom->passinggrade,
    'bestscore' => $bestscore,
    'kpcodes' => array_map(static function($code) {
        return ['code' => s($code)];
    }, $kpcodes),
];

$config = [
    'cmid' => $cm->id,
    'passinggrade' => (int) $flwvrroom->passinggrade,
    'maxgrade' => (int) $flwvrroom->grade,
    'rootid' => $templatecontext['uniqid'],
    'strings' => [
        'saved' => get_string('attemptsaved', 'flwvrroom'),
        'savefailed' => get_string('savefailed', 'flwvrroom'),
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_flwvrroom/room', $templatecontext);
$PAGE->requires->js_call_amd('mod_flwvrroom/room', 'init', [$config]);
echo $OUTPUT->footer();

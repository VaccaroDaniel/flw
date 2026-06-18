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

$preset = flwvrroom_apply_custom_room(flwvrroom_get_scenario_preset($flwvrroom->scenario), $flwvrroom);
$roommode = in_array(($flwvrroom->roommode ?? 'panorama'), ['panorama', 'builtin3d', 'uploaded3d'], true)
    ? $flwvrroom->roommode
    : 'panorama';
$model3durl = flwvrroom_get_model3d_url($context);

$kpcodetext = trim((string) ($flwvrroom->kpcodes ?? ''));
$kpcodes = preg_split('/\R+/', $kpcodetext);
$kpcodes = array_values(array_filter(array_map('trim', $kpcodes)));
if (empty($kpcodes)) {
    $kpcodes = $preset['kpcodes'] ?? [];
}

$answers = [];
foreach ($preset['answers'] as $index => $answer) {
    $answers[] = [
        'id' => 'answer-' . $index,
        'text' => $answer['text'],
        'score' => (int) $answer['score'],
    ];
}

$hotspots = [];
$builtinpositions = flwvrroom_get_builtin3d_positions($preset['key']);
foreach ($preset['hotspots'] as $hotspot) {
    if (isset($hotspot['objectx'], $hotspot['objecty'], $hotspot['objectz'])) {
        $position = [
            'x' => (float) $hotspot['objectx'],
            'y' => (float) $hotspot['objecty'],
            'z' => (float) $hotspot['objectz'],
        ];
    } else {
        $position = $builtinpositions[$hotspot['key']] ?? [
        'x' => ((float) $hotspot['x'] - 50) / 12.5,
        'y' => 1.25 + (50 - (float) $hotspot['y']) / 80,
        'z' => -2.5,
        ];
    }

    $hotspots[] = [
        'key' => $hotspot['key'],
        'label' => $hotspot['label'],
        'description' => s($hotspot['description'] ?? ''),
        'audiourl' => !empty($hotspot['audiourl']) ? clean_param($hotspot['audiourl'], PARAM_URL) : '',
        'hasaudio' => !empty($hotspot['audiourl']),
        'score' => (int) $hotspot['score'],
        'x' => (float) $hotspot['x'],
        'y' => (float) $hotspot['y'],
        'posx' => (float) $position['x'],
        'posy' => (float) $position['y'],
        'posz' => (float) $position['z'],
        'style' => 'left: ' . (float) $hotspot['x'] . '%; top: ' . (float) $hotspot['y'] . '%;',
    ];
}

$templatecontext = [
    'uniqid' => 'flwvrroom-' . $cm->id,
    'name' => format_string($flwvrroom->name),
    'intro' => format_module_intro('flwvrroom', $flwvrroom, $cm->id),
    'cefrlevel' => s($flwvrroom->cefrlevel),
    'scenario' => s($flwvrroom->scenario),
    'roommode' => $roommode,
    'roommodelabel' => get_string('roommode_' . $roommode, 'flwvrroom'),
    'is3d' => $roommode === 'builtin3d' || $roommode === 'uploaded3d',
    'model3dmissing' => $roommode === 'uploaded3d' && !$model3durl,
    'caneditroom' => has_capability('moodle/course:manageactivities', $context),
    'canviewreports' => has_capability('mod/flwvrroom:viewreports', $context),
    'reporturl' => (new moodle_url('/mod/flwvrroom/report.php', ['id' => $cm->id]))->out(false),
    'scenariokey' => $preset['key'],
    'missiontitle' => $preset['title'],
    'missiontext' => $preset['mission'],
    'roomaria' => $preset['aria'],
    'quizquestion' => $preset['prompt'],
    'answers' => $answers,
    'hotspots' => $hotspots,
    'backgroundurl' => $preset['backgroundurl'] ?? (new moodle_url('/mod/flwvrroom/pix/scenarios/' . $preset['key'] . '.png'))->out(false),
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
    'roommode' => $roommode,
    'kpcodes' => $kpcodes,
    'quizquestion' => $preset['prompt'],
    'speakingscoringurl' => trim((string) ($flwvrroom->speakingscoringurl ?? '')) ?: 'http://127.0.0.1:8000',
    'threeurl' => (new moodle_url('/mod/flwvrroom/js/three.module.min.js'))->out(false),
    'gltfloaderurl' => (new moodle_url('/mod/flwvrroom/js/GLTFLoader.js'))->out(false),
    'model3durl' => $model3durl ? $model3durl->out(false) : '',
    'strings' => [
        'saved' => get_string('attemptsaved', 'flwvrroom'),
        'savefailed' => get_string('savefailed', 'flwvrroom'),
        'positionhelperidle' => get_string('positionhelperidle', 'flwvrroom'),
        'positionhelperactive' => get_string('positionhelperactive', 'flwvrroom'),
        'positionhelpercopied' => get_string('positionhelpercopied', 'flwvrroom', '{$a}'),
        'positionhelpercopied3d' => get_string('positionhelpercopied3d', 'flwvrroom', '{$a}'),
        'recordspeaking' => get_string('recordspeaking', 'flwvrroom'),
        'stopspeaking' => get_string('stopspeaking', 'flwvrroom'),
        'speakingempty' => get_string('speakingempty', 'flwvrroom'),
        'speakingrecording' => get_string('speakingrecording', 'flwvrroom'),
        'speakingscoring' => get_string('speakingscoring', 'flwvrroom'),
        'speakingfailed' => get_string('speakingfailed', 'flwvrroom'),
        'nospeechdetected' => get_string('nospeechdetected', 'flwvrroom'),
        'recordingunsupported' => get_string('recordingunsupported', 'flwvrroom'),
        'recordingfailed' => get_string('recordingfailed', 'flwvrroom'),
    ],
];
$templatecontext['configjson'] = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_flwvrroom/room', $templatecontext);
$PAGE->requires->js_call_amd('mod_flwvrroom/room', 'init', [$templatecontext['uniqid']]);
echo $OUTPUT->footer();

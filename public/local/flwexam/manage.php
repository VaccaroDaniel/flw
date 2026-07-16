<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

require_login();

$context = context_system::instance();
require_capability('local/flwexam:manageexams', $context);

$selectedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
$selectedtrack = optional_param('track', '', PARAM_ALPHANUMEXT);
$selectedlevel = optional_param('cefr', 'A1', PARAM_ALPHANUMEXT);
$editid = optional_param('edit', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 12, PARAM_INT);
$page = max(0, $page);
$perpage = min(50, max(5, $perpage));

$url = new moodle_url('/local/flwexam/manage.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('manageexams', 'local_flwexam'));
$PAGE->set_heading(get_string('manageexams', 'local_flwexam'));

$output = $PAGE->get_renderer('core');
echo $output->header();
echo html_writer::start_div('flwexam-page');

if (!$DB->get_manager()->table_exists('local_flwexam_exams')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwexam'), 'warning');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

$filteroptions = exam_service::get_exam_filter_options();
if ($selectedlanguage === '' && $filteroptions['languages']) {
    $selectedlanguage = array_key_first($filteroptions['languages']);
}
if ($selectedlanguage !== '' && !isset($filteroptions['languages'][$selectedlanguage])) {
    $selectedlanguage = array_key_first($filteroptions['languages']) ?: '';
}
$trackoptions = $selectedlanguage !== '' ? exam_service::get_track_options_for_language($selectedlanguage) : [];
if ($selectedtrack === '' && $trackoptions) {
    $selectedtrack = array_key_first($trackoptions);
}
if ($selectedtrack !== '' && !isset($trackoptions[$selectedtrack])) {
    $selectedtrack = array_key_first($trackoptions) ?: '';
}
if (!isset($filteroptions['levels'][$selectedlevel])) {
    $selectedlevel = 'A1';
}

$alltrackoptions = [];
foreach (array_keys($filteroptions['languages']) as $languagecode) {
    $alltrackoptions[$languagecode] = exam_service::get_track_options_for_language($languagecode);
}

$editingexam = null;
if ($editid > 0) {
    $editingexam = $DB->get_record('local_flwexam_exams', ['id' => $editid], '*', MUST_EXIST);
    $selectedlanguage = $editingexam->language;
    $selectedtrack = $editingexam->learningcoursecategory;
    $selectedlevel = $editingexam->cefrlevel;
    $trackoptions = exam_service::get_track_options_for_language($selectedlanguage);
}

if (data_submitted() && confirm_sesskey()) {
    $submittedid = optional_param('id', 0, PARAM_INT);
    $code = core_text::strtoupper(required_param('code', PARAM_ALPHANUMEXT));
    $name = required_param('name', PARAM_TEXT);
    $language = required_param('language', PARAM_ALPHANUMEXT);
    $track = required_param('track', PARAM_ALPHANUMEXT);
    $cefr = required_param('cefr', PARAM_ALPHANUMEXT);
    $threshold = optional_param('requiredthreshold', 70, PARAM_FLOAT);
    $skillfloor = optional_param('requiredskillfloor', 60, PARAM_FLOAT);
    $moderationrequired = optional_param('moderationrequired', 0, PARAM_BOOL);
    $visible = optional_param('visible', 0, PARAM_BOOL);

    $languageoptions = exam_service::get_learning_language_options();
    $validtracks = exam_service::get_track_options_for_language($language);
    $leveloptions = exam_service::get_cefr_level_options();
    if (!isset($languageoptions[$language]) || !isset($validtracks[$track]) || !isset($leveloptions[$cefr])) {
        throw new moodle_exception('invalidexamprofile', 'local_flwexam');
    }
    $duplicate = $DB->get_record('local_flwexam_exams', ['code' => $code], 'id', IGNORE_MISSING);
    if ($duplicate && (int)$duplicate->id !== $submittedid) {
        throw new moodle_exception('duplicateexamcode', 'local_flwexam', '', s($code));
    }

    $now = time();
    $record = (object)[
        'code' => $code,
        'name' => $name,
        'language' => $language,
        'learningcoursecategory' => $track,
        'cefrlevel' => $cefr,
        'requiredthreshold' => max(0, min(100, $threshold)),
        'requiredskillfloor' => max(0, min(100, $skillfloor)),
        'moderationrequired' => $moderationrequired ? 1 : 0,
        'criticalkpjson' => json_encode([]),
        'profilejson' => json_encode([
            'description' => '',
            'skills' => ['listening', 'speaking', 'reading', 'writing'],
        ]),
        'visible' => $visible ? 1 : 0,
        'timemodified' => $now,
    ];

    if ($submittedid > 0) {
        $existing = $DB->get_record('local_flwexam_exams', ['id' => $submittedid], 'id,timecreated', MUST_EXIST);
        $record->id = (int)$existing->id;
        $record->timecreated = (int)$existing->timecreated;
        $DB->update_record('local_flwexam_exams', $record);
        redirect($url, get_string('examupdated', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $record->timecreated = $now;
    $DB->insert_record('local_flwexam_exams', $record);

    redirect($url, get_string('examcreated', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('manageexams', 'local_flwexam'),
    get_string('manageexamsintro', 'local_flwexam'),
    [
        html_writer::link(
            new moodle_url('/local/flwexam/take.php'),
            get_string('takeexam', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
    ],
    [
        get_string('language', 'local_flwexam') => $selectedlanguage !== '' ? $selectedlanguage : get_string('selectlanguage', 'local_flwexam'),
        get_string('cefrlevel', 'local_flwexam') => $selectedlevel,
    ]
);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
    'class' => 'flwexam-filter-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
if ($editingexam) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$editingexam->id]);
}
echo html_writer::tag('h3', $editingexam ? get_string('editexam', 'local_flwexam') : get_string('addexam', 'local_flwexam'));
echo html_writer::start_div('flwexam-filter-grid');

$fields = [
    ['code', get_string('examcode', 'local_flwexam'), 'text', $editingexam->code ?? ''],
    ['name', get_string('examname', 'local_flwexam'), 'text', $editingexam->name ?? ''],
];
foreach ($fields as [$name, $label, $type, $value]) {
    echo html_writer::start_div('form-group');
    echo html_writer::label($label, 'flwexam-' . $name);
    echo html_writer::empty_tag('input', [
        'type' => $type,
        'name' => $name,
        'id' => 'flwexam-' . $name,
        'class' => 'form-control',
        'value' => $value,
        'required' => 'required',
    ]);
    echo html_writer::end_div();
}

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('chooselanguage', 'local_flwexam'), 'flwexam-language');
echo html_writer::select(
    $filteroptions['languages'],
    'language',
    $selectedlanguage,
    false,
    ['id' => 'flwexam-language', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('choosetrack', 'local_flwexam'), 'flwexam-track');
echo html_writer::select(
    $trackoptions,
    'track',
    $selectedtrack,
    false,
    ['id' => 'flwexam-track', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('choosecefrlevel', 'local_flwexam'), 'flwexam-cefr');
echo html_writer::select(
    $filteroptions['levels'],
    'cefr',
    $selectedlevel,
    false,
    ['id' => 'flwexam-cefr', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();

$numericfields = [
    ['requiredthreshold', get_string('requiredthreshold', 'local_flwexam'), $editingexam->requiredthreshold ?? 70],
    ['requiredskillfloor', get_string('requiredskillfloor', 'local_flwexam'), $editingexam->requiredskillfloor ?? 60],
];
foreach ($numericfields as [$name, $label, $value]) {
    echo html_writer::start_div('form-group');
    echo html_writer::label($label, 'flwexam-' . $name);
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => $name,
        'id' => 'flwexam-' . $name,
        'class' => 'form-control',
        'value' => $value,
        'min' => 0,
        'max' => 100,
        'step' => '0.1',
        'required' => 'required',
    ]);
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::start_div('flwexam-action-row');
echo html_writer::tag('label',
    html_writer::checkbox('moderationrequired', 1, $editingexam ? (bool)$editingexam->moderationrequired : true, get_string('moderationrequired', 'local_flwexam')),
    ['class' => 'form-check-label']
);
echo html_writer::tag('label',
    html_writer::checkbox('visible', 1, $editingexam ? (bool)$editingexam->visible : true, get_string('examvisible', 'local_flwexam')),
    ['class' => 'form-check-label']
);
echo html_writer::end_div();
if ($editingexam) {
    echo html_writer::start_div('flwexam-action-row');
    echo html_writer::link(new moodle_url('/local/flwexam/manage.php', [
        'search' => $search,
        'page' => $page,
        'perpage' => $perpage,
    ]), get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();
}
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary flwexam-main-action',
    'value' => $editingexam ? get_string('updateexam', 'local_flwexam') : get_string('addexam', 'local_flwexam'),
]);
echo html_writer::end_tag('form');

echo html_writer::script(
    '(function() {' .
    'var tracksByLanguage = ' . json_encode($alltrackoptions) . ';' .
    'var languageSelect = document.getElementById("flwexam-language");' .
    'var trackSelect = document.getElementById("flwexam-track");' .
    'if (!languageSelect || !trackSelect) { return; }' .
    'function refreshTracks() {' .
    'var selectedTrack = trackSelect.value;' .
    'var tracks = tracksByLanguage[languageSelect.value] || {};' .
    'trackSelect.innerHTML = "";' .
    'Object.keys(tracks).forEach(function(value) {' .
    'var option = document.createElement("option");' .
    'option.value = value;' .
    'option.textContent = tracks[value];' .
    'if (value === selectedTrack) { option.selected = true; }' .
    'trackSelect.appendChild(option);' .
    '});' .
    'if (!tracks[selectedTrack]) { trackSelect.value = Object.keys(tracks)[0] || ""; }' .
    '}' .
    'languageSelect.addEventListener("change", refreshTracks);' .
    '})();'
);

echo html_writer::tag('h3', get_string('existingexams', 'local_flwexam'));
$listurl = new moodle_url('/local/flwexam/manage.php', [
    'search' => $search,
    'perpage' => $perpage,
]);
$searchparams = [];
$searchwhere = '';
if ($search !== '') {
    $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    $likes = [];
    foreach (['code', 'name', 'language', 'learningcoursecategory', 'cefrlevel'] as $field) {
        $param = 'search' . $field;
        $likes[] = $DB->sql_like($field, ':' . $param, false);
        $searchparams[$param] = $searchparam;
    }
    $searchwhere = ' WHERE ' . implode(' OR ', $likes);
}
$totalrecords = $DB->count_records_sql(
    'SELECT COUNT(1) FROM {local_flwexam_exams}' . $searchwhere,
    $searchparams
);
$records = $DB->get_records_sql(
    'SELECT *
       FROM {local_flwexam_exams}' . $searchwhere . '
   ORDER BY language ASC, learningcoursecategory ASC, cefrlevel ASC, name ASC',
    $searchparams,
    $page * $perpage,
    $perpage
);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/flwexam/manage.php'))->out(false),
    'class' => 'flwexam-search-form',
]);
echo html_writer::label(get_string('searchexams', 'local_flwexam'), 'flwexam-search');
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'search',
    'id' => 'flwexam-search',
    'class' => 'form-control',
    'value' => $search,
    'placeholder' => get_string('searchexamsplaceholder', 'local_flwexam'),
]);
echo html_writer::select(
    [5 => 5, 10 => 10, 12 => 12, 20 => 20, 50 => 50],
    'perpage',
    $perpage,
    false,
    ['class' => 'form-control flwexam-perpage']
);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-secondary',
    'value' => get_string('search'),
]);
if ($search !== '') {
    echo html_writer::link(new moodle_url('/local/flwexam/manage.php'), get_string('clearsearch', 'local_flwexam'), [
        'class' => 'btn btn-link',
    ]);
}
echo html_writer::end_tag('form');
echo html_writer::div(
    get_string('examsearchsummary', 'local_flwexam', (object)[
        'shown' => count($records),
        'total' => $totalrecords,
    ]),
    'flwexam-muted'
);

if (!$records) {
    echo html_writer::div(get_string('noavailableexams', 'local_flwexam'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable flwexam-table';
    $table->head = [
        get_string('edit'),
        get_string('examcode', 'local_flwexam'),
        get_string('examname', 'local_flwexam'),
        get_string('language', 'local_flwexam'),
        get_string('track', 'local_flwexam'),
        get_string('cefrlevel', 'local_flwexam'),
        get_string('questions', 'local_flwexam'),
        get_string('status', 'local_flwexam'),
        get_string('actions', 'local_flwexam'),
    ];
    foreach ($records as $record) {
        $questioncount = $DB->count_records('local_flwexam_questions', [
            'examid' => (int)$record->id,
            'visible' => 1,
        ]);
        $editurl = new moodle_url('/local/flwexam/manage.php', [
            'edit' => (int)$record->id,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        $table->data[] = [
            html_writer::link(
                $editurl,
                get_string('edit'),
                ['class' => 'btn btn-primary btn-sm']
            ),
            html_writer::link($editurl, s($record->code)),
            s($record->name),
            s(exam_service::language_label($record->language)),
            s(exam_service::track_label($record->learningcoursecategory)),
            s($record->cefrlevel),
            $questioncount,
            $record->visible ? get_string('visible') : get_string('hidden'),
            html_writer::link(
                new moodle_url('/local/flwexam/questions.php', ['examid' => (int)$record->id]),
                get_string('editquestions', 'local_flwexam'),
                ['class' => 'btn btn-secondary btn-sm']
            ),
        ];
    }
    echo html_writer::table($table);
    echo $output->paging_bar($totalrecords, $page, $perpage, $listurl);
}

echo html_writer::end_div();
echo $output->footer();

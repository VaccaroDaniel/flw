<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

require_login();

$context = context_system::instance();
require_capability('local/flwexam:manageexams', $context);

$selectedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
$selectedlevel = optional_param('cefr', 'A1', PARAM_ALPHANUMEXT);
$editid = optional_param('edit', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));
$filterlanguage = optional_param('filterlanguage', '', PARAM_ALPHANUMEXT);
$filtercefr = optional_param('filtercefr', '', PARAM_ALPHANUMEXT);
$filtersource = optional_param('filtersource', '', PARAM_ALPHA);
$filtervisibility = optional_param('filtervisibility', '', PARAM_ALPHA);
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
local_flwexam_require_styles();

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
if (!isset($filteroptions['levels'][$selectedlevel])) {
    $selectedlevel = 'A1';
}
$filterlanguage = isset($filteroptions['languages'][$filterlanguage]) ? $filterlanguage : '';
$filtercefr = isset($filteroptions['levels'][$filtercefr]) ? $filtercefr : '';
$filtersource = in_array($filtersource, ['moodlequiz', 'internal'], true) ? $filtersource : '';
$filtervisibility = in_array($filtervisibility, ['visible', 'hidden'], true) ? $filtervisibility : '';

$quizoptions = [0 => get_string('internalquestions', 'local_flwexam')] + exam_service::get_quiz_options();
$listfilters = [
    'search' => $search,
    'filterlanguage' => $filterlanguage,
    'filtercefr' => $filtercefr,
    'filtersource' => $filtersource,
    'filtervisibility' => $filtervisibility,
    'perpage' => $perpage,
];

$editingexam = null;
if ($editid > 0) {
    $editingexam = $DB->get_record('local_flwexam_exams', ['id' => $editid], '*', MUST_EXIST);
    $selectedlanguage = $editingexam->language;
    $selectedlevel = $editingexam->cefrlevel;
}

if (data_submitted() && confirm_sesskey()) {
    $submittedid = optional_param('id', 0, PARAM_INT);
    $code = core_text::strtoupper(required_param('code', PARAM_ALPHANUMEXT));
    $name = required_param('name', PARAM_TEXT);
    $language = required_param('language', PARAM_ALPHANUMEXT);
    $cefr = required_param('cefr', PARAM_ALPHANUMEXT);
    $threshold = optional_param('requiredthreshold', 70, PARAM_FLOAT);
    $skillfloor = optional_param('requiredskillfloor', 60, PARAM_FLOAT);
    $quizid = optional_param('quizid', 0, PARAM_INT);
    $moderationrequired = optional_param('moderationrequired', 0, PARAM_BOOL);
    $visible = optional_param('visible', 0, PARAM_BOOL);

    $languageoptions = exam_service::get_learning_language_options();
    $leveloptions = exam_service::get_cefr_level_options();
    if (!isset($languageoptions[$language]) || !isset($leveloptions[$cefr])) {
        throw new moodle_exception('invalidexamprofile', 'local_flwexam');
    }
    $duplicate = $DB->get_record('local_flwexam_exams', ['code' => $code], 'id', IGNORE_MISSING);
    if ($duplicate && (int)$duplicate->id !== $submittedid) {
        throw new moodle_exception('duplicateexamcode', 'local_flwexam', '', s($code));
    }
    if ($quizid > 0 && !exam_service::quiz_exists($quizid)) {
        throw new moodle_exception('invalidquizsource', 'local_flwexam');
    }
    $existingexamforupdate = null;
    $internalcategory = 'exam';
    if ($submittedid > 0) {
        $existingexamforupdate = $DB->get_record(
            'local_flwexam_exams',
            ['id' => $submittedid],
            'id,timecreated,learningcoursecategory',
            MUST_EXIST
        );
        $internalcategory = $existingexamforupdate->learningcoursecategory ?: $internalcategory;
    }

    $now = time();
    $record = (object)[
        'code' => $code,
        'name' => $name,
        'language' => $language,
        'learningcoursecategory' => $internalcategory,
        'cefrlevel' => $cefr,
        'requiredthreshold' => max(0, min(100, $threshold)),
        'requiredskillfloor' => max(0, min(100, $skillfloor)),
        'moderationrequired' => $moderationrequired ? 1 : 0,
        'criticalkpjson' => json_encode([]),
        'profilejson' => json_encode([
            'description' => '',
            'skills' => ['listening', 'speaking', 'reading', 'writing'],
        ]),
        'quizid' => $quizid,
        'visible' => $visible ? 1 : 0,
        'timemodified' => $now,
    ];

    if ($submittedid > 0) {
        $record->id = (int)$existingexamforupdate->id;
        $record->timecreated = (int)$existingexamforupdate->timecreated;
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
            new moodle_url('/local/flwexam/index.php', ['view' => 'available']),
            get_string('examcenter', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
        html_writer::link(
            new moodle_url('/local/flwexam/sessions.php'),
            get_string('manageexamsessions', 'local_flwexam'),
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
    'class' => 'flwexam-filter-form flwexam-manage-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
if ($editingexam) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$editingexam->id]);
}
echo html_writer::tag('h3', $editingexam ? get_string('editexam', 'local_flwexam') : get_string('addexam', 'local_flwexam'));
echo html_writer::start_div('flwexam-manage-body');
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
echo html_writer::label(get_string('choosecefrlevel', 'local_flwexam'), 'flwexam-cefr');
echo html_writer::select(
    $filteroptions['levels'],
    'cefr',
    $selectedlevel,
    false,
    ['id' => 'flwexam-cefr', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();

echo html_writer::start_div('form-group flwexam-quiz-source-group');
echo html_writer::start_div('flwexam-label-row');
echo html_writer::label(get_string('moodlequizsource', 'local_flwexam'), 'flwexam-quizid');
echo html_writer::tag('button', '?', [
    'type' => 'button',
    'class' => 'flwexam-help-button',
    'aria-label' => get_string('quizsourcehelpbutton', 'local_flwexam'),
    'aria-describedby' => 'flwexam-quiz-source-help',
    'aria-expanded' => 'false',
    'aria-controls' => 'flwexam-quiz-source-help',
    'data-flwexam-help-toggle' => 'flwexam-quiz-source-help',
]);
echo html_writer::div(get_string('quizsourcehelp', 'local_flwexam'), 'flwexam-help-popover', [
    'id' => 'flwexam-quiz-source-help',
    'role' => 'tooltip',
    'hidden' => 'hidden',
]);
echo html_writer::end_div();
echo html_writer::select(
    $quizoptions,
    'quizid',
    (int)($editingexam->quizid ?? 0),
    false,
    ['id' => 'flwexam-quizid', 'class' => 'form-control']
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
    echo html_writer::link(new moodle_url('/local/flwexam/manage.php', $listfilters + [
        'page' => $page,
    ]), get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();
}
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary flwexam-main-action',
    'value' => $editingexam ? get_string('updateexam', 'local_flwexam') : get_string('addexam', 'local_flwexam'),
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::script(
    '(function() {' .
    'function closeAll(except) {' .
    'Array.prototype.slice.call(document.querySelectorAll("[data-flwexam-help-toggle]")).forEach(function(button) {' .
    'if (except && button === except) { return; }' .
    'var target = document.getElementById(button.getAttribute("data-flwexam-help-toggle"));' .
    'button.setAttribute("aria-expanded", "false");' .
    'if (target) { target.hidden = true; }' .
    '});' .
    '}' .
    'document.addEventListener("click", function(event) {' .
    'var button = event.target.closest("[data-flwexam-help-toggle]");' .
    'if (!button) { closeAll(); return; }' .
    'event.preventDefault();' .
    'var target = document.getElementById(button.getAttribute("data-flwexam-help-toggle"));' .
    'if (!target) { return; }' .
    'var expanded = button.getAttribute("aria-expanded") === "true";' .
    'closeAll(button);' .
    'button.setAttribute("aria-expanded", expanded ? "false" : "true");' .
    'target.hidden = expanded;' .
    '});' .
    'document.addEventListener("keydown", function(event) {' .
    'if (event.key === "Escape") { closeAll(); }' .
    '});' .
    '})();'
);

echo html_writer::start_div('flwexam-exam-card flwexam-list-panel');
echo html_writer::tag('h3', get_string('existingexams', 'local_flwexam'));
$listurl = new moodle_url('/local/flwexam/manage.php', $listfilters);
$searchparams = [];
$where = [];
if ($search !== '') {
    $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    $likes = [];
    foreach (['code', 'name', 'language', 'cefrlevel'] as $field) {
        $param = 'search' . $field;
        $likes[] = $DB->sql_like($field, ':' . $param, false);
        $searchparams[$param] = $searchparam;
    }
    $where[] = '(' . implode(' OR ', $likes) . ')';
}
if ($filterlanguage !== '') {
    $where[] = 'language = :filterlanguage';
    $searchparams['filterlanguage'] = $filterlanguage;
}
if ($filtercefr !== '') {
    $where[] = 'cefrlevel = :filtercefr';
    $searchparams['filtercefr'] = $filtercefr;
}
if ($filtersource === 'moodlequiz') {
    $where[] = 'quizid > 0';
} else if ($filtersource === 'internal') {
    $where[] = 'quizid = 0';
}
if ($filtervisibility === 'visible') {
    $where[] = 'visible = 1';
} else if ($filtervisibility === 'hidden') {
    $where[] = 'visible = 0';
}
$searchwhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$totalrecords = $DB->count_records_sql(
    'SELECT COUNT(1) FROM {local_flwexam_exams}' . $searchwhere,
    $searchparams
);
$records = $DB->get_records_sql(
    'SELECT *
       FROM {local_flwexam_exams}' . $searchwhere . '
   ORDER BY language ASC, cefrlevel ASC, name ASC',
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
    ['' => get_string('alllanguages', 'local_flwexam')] + $filteroptions['languages'],
    'filterlanguage',
    $filterlanguage,
    false,
    ['class' => 'form-control']
);
echo html_writer::select(
    ['' => get_string('alllevels', 'local_flwexam')] + $filteroptions['levels'],
    'filtercefr',
    $filtercefr,
    false,
    ['class' => 'form-control']
);
echo html_writer::select(
    [
        '' => get_string('allsources', 'local_flwexam'),
        'moodlequiz' => get_string('moodlequiz', 'local_flwexam'),
        'internal' => get_string('flwinternalquestions', 'local_flwexam'),
    ],
    'filtersource',
    $filtersource,
    false,
    ['class' => 'form-control']
);
echo html_writer::select(
    [
        '' => get_string('allstatuses', 'local_flwexam'),
        'visible' => get_string('visibleexam', 'local_flwexam'),
        'hidden' => get_string('hiddenexam', 'local_flwexam'),
    ],
    'filtervisibility',
    $filtervisibility,
    false,
    ['class' => 'form-control']
);
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
if ($search !== '' || $filterlanguage !== '' || $filtercefr !== '' || $filtersource !== '' || $filtervisibility !== '') {
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
        get_string('cefrlevel', 'local_flwexam'),
        get_string('questions', 'local_flwexam'),
        get_string('question_source', 'local_flwexam'),
        get_string('status', 'local_flwexam'),
        get_string('actions', 'local_flwexam'),
    ];
    foreach ($records as $record) {
        $questioncount = exam_service::get_exam_question_count($record);
        $source = !empty($record->quizid) ? get_string('moodlequiz', 'local_flwexam') : get_string('flwinternalquestions', 'local_flwexam');
        $actions = [];
        if (!empty($record->quizid)) {
            $quizinfo = exam_service::get_linked_quiz_info((int)$record->quizid);
            if ($quizinfo) {
                $actions[] = html_writer::link(
                    $quizinfo['url'],
                    get_string('openmoodlequiz', 'local_flwexam'),
                    ['class' => 'btn btn-secondary btn-sm']
                );
            }
        } else {
            $actions[] = html_writer::link(
                new moodle_url('/local/flwexam/questions.php', ['examid' => (int)$record->id]),
                get_string('editquestions', 'local_flwexam'),
                ['class' => 'btn btn-secondary btn-sm']
            );
        }
        $editurl = new moodle_url('/local/flwexam/manage.php', [
            'edit' => (int)$record->id,
            'page' => $page,
        ] + $listfilters);
        $table->data[] = [
            html_writer::link(
                $editurl,
                get_string('edit'),
                ['class' => 'btn btn-primary btn-sm']
            ),
            html_writer::link($editurl, s($record->code)),
            s($record->name),
            s(exam_service::language_label($record->language)),
            s($record->cefrlevel),
            $questioncount,
            $source,
            $record->visible ? get_string('visibleexam', 'local_flwexam') : get_string('hiddenexam', 'local_flwexam'),
            implode(' ', $actions),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'flwexam-table-wrap');
    echo $output->paging_bar($totalrecords, $page, $perpage, $listurl);
}
echo html_writer::end_div();

echo html_writer::end_div();
echo $output->footer();

<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

$examid = required_param('examid', PARAM_INT);
$questionid = optional_param('qid', 0, PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/flwexam:manageexams', $context);

$exam = $DB->get_record('local_flwexam_exams', ['id' => $examid], '*', MUST_EXIST);
$url = new moodle_url('/local/flwexam/questions.php', ['examid' => $examid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('managequestionsfor', 'local_flwexam', format_string($exam->name)));
$PAGE->set_heading(get_string('managequestionsfor', 'local_flwexam', format_string($exam->name)));

$editingquestion = null;
if ($questionid > 0) {
    $editingquestion = $DB->get_record('local_flwexam_questions', [
        'id' => $questionid,
        'examid' => $examid,
    ], '*', MUST_EXIST);
}

if (data_submitted() && confirm_sesskey()) {
    $submittedquestionid = optional_param('qid', 0, PARAM_INT);
    $qtype = required_param('qtype', PARAM_ALPHANUMEXT);
    $questiontext = required_param('questiontext', PARAM_TEXT);
    $skill = required_param('skill', PARAM_ALPHA);
    $kpcode = required_param('kpcode', PARAM_ALPHANUMEXT);
    $weight = optional_param('weight', 1, PARAM_FLOAT);
    $sortorder = optional_param('sortorder', 0, PARAM_INT);
    $visible = optional_param('visible', 0, PARAM_BOOL);
    $skills = local_flwexam_question_skill_options();
    $qtypes = local_flwexam_question_type_options();
    $answerkeys = ['a', 'b', 'c', 'd'];
    if (!isset($skills[$skill]) || !isset($qtypes[$qtype])) {
        throw new moodle_exception('invalidquestionprofile', 'local_flwexam');
    }

    $options = [];
    if ($qtype === 'multichoice') {
        $correctanswer = required_param('correctanswer_mc', PARAM_ALPHA);
        if (!in_array($correctanswer, $answerkeys, true)) {
            throw new moodle_exception('invalidquestionprofile', 'local_flwexam');
        }
        foreach ($answerkeys as $key) {
            $optiontext = required_param('option_' . $key, PARAM_TEXT);
            if (trim($optiontext) === '') {
                throw new moodle_exception('missingquestionoptions', 'local_flwexam');
            }
            $options[] = [
                'key' => $key,
                'text' => $optiontext,
            ];
        }
    } else if ($qtype === 'truefalse') {
        $correctanswer = required_param('correctanswer_tf', PARAM_ALPHA);
        if (!in_array($correctanswer, ['true', 'false'], true)) {
            throw new moodle_exception('invalidquestionprofile', 'local_flwexam');
        }
        $options[] = [
            'key' => 'true',
            'text' => get_string('optiontrue', 'local_flwexam'),
        ];
        $options[] = [
            'key' => 'false',
            'text' => get_string('optionfalse', 'local_flwexam'),
        ];
    } else {
        $correctanswer = trim(required_param('correctanswer_short', PARAM_TEXT));
        if ($correctanswer === '') {
            throw new moodle_exception('missingquestionoptions', 'local_flwexam');
        }
    }

    $now = time();
    $record = (object)[
        'examid' => $examid,
        'qtype' => $qtype,
        'questiontext' => $questiontext,
        'optionsjson' => json_encode($options),
        'correctanswer' => $correctanswer,
        'skill' => $skill,
        'kpcode' => $kpcode,
        'weight' => max(0.1, $weight),
        'sortorder' => $sortorder > 0 ? $sortorder : local_flwexam_next_question_sortorder($examid),
        'visible' => $visible ? 1 : 0,
        'timemodified' => $now,
    ];

    if ($submittedquestionid > 0) {
        $existing = $DB->get_record('local_flwexam_questions', [
            'id' => $submittedquestionid,
            'examid' => $examid,
        ], 'id', MUST_EXIST);
        $record->id = $existing->id;
        $DB->update_record('local_flwexam_questions', $record);
        redirect($url, get_string('questionupdated', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $record->timecreated = $now;
    $DB->insert_record('local_flwexam_questions', $record);
    redirect($url, get_string('questioncreated', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$output = $PAGE->get_renderer('core');
echo $output->header();
echo html_writer::start_div('flwexam-page');

echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('managequestionsfor', 'local_flwexam', format_string($exam->name)),
    get_string('manageexamsintro', 'local_flwexam'),
    [
        html_writer::link(new moodle_url('/local/flwexam/manage.php'), get_string('backtomanageexams', 'local_flwexam'), [
            'class' => 'btn btn-secondary',
        ]),
        html_writer::link(new moodle_url('/local/flwexam/take.php'), get_string('takeexam', 'local_flwexam'), [
            'class' => 'btn btn-secondary',
        ]),
    ],
    [
        get_string('examcode', 'local_flwexam') => $exam->code,
        get_string('cefrlevel', 'local_flwexam') => $exam->cefrlevel,
    ]
);

echo html_writer::start_div('flwexam-result-summary');
$details = [
    get_string('examcode', 'local_flwexam') => $exam->code,
    get_string('language', 'local_flwexam') => exam_service::language_label($exam->language),
    get_string('track', 'local_flwexam') => exam_service::track_label($exam->learningcoursecategory),
    get_string('cefrlevel', 'local_flwexam') => $exam->cefrlevel,
];
echo html_writer::start_div('flwexam-summary-grid');
foreach ($details as $label => $value) {
    echo html_writer::div(
        html_writer::span(s($label), 'flwexam-card-label') .
        html_writer::tag('strong', s($value)),
        'flwexam-mini-card'
    );
}
echo html_writer::end_div();
echo html_writer::end_div();

$formtitle = $editingquestion ? get_string('editquestion', 'local_flwexam') : get_string('addquestion', 'local_flwexam');
$defaults = local_flwexam_question_defaults($editingquestion, $examid);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
    'class' => 'flwexam-filter-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
if ($editingquestion) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'qid', 'value' => (int)$editingquestion->id]);
}
echo html_writer::tag('h3', $formtitle);
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('questiontext', 'local_flwexam'), 'flwexam-questiontext');
echo html_writer::tag('textarea', s($defaults['questiontext']), [
    'name' => 'questiontext',
    'id' => 'flwexam-questiontext',
    'class' => 'form-control',
    'rows' => 4,
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('flwexam-filter-grid');
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('questiontype', 'local_flwexam'), 'flwexam-qtype');
echo html_writer::select(local_flwexam_question_type_options(), 'qtype', $defaults['qtype'], false, [
    'id' => 'flwexam-qtype',
    'class' => 'form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('skill', 'local_flwexam'), 'flwexam-skill');
echo html_writer::select(local_flwexam_question_skill_options(), 'skill', $defaults['skill'], false, [
    'id' => 'flwexam-skill',
    'class' => 'form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('kpcode', 'local_flwexam'), 'flwexam-kpcode');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'kpcode',
    'id' => 'flwexam-kpcode',
    'class' => 'form-control',
    'value' => $defaults['kpcode'],
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('weight', 'local_flwexam'), 'flwexam-weight');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'weight',
    'id' => 'flwexam-weight',
    'class' => 'form-control',
    'value' => $defaults['weight'],
    'min' => '0.1',
    'step' => '0.1',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('sortorder', 'local_flwexam'), 'flwexam-sortorder');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'sortorder',
    'id' => 'flwexam-sortorder',
    'class' => 'form-control',
    'value' => $defaults['sortorder'],
    'min' => 1,
    'step' => 1,
    'required' => 'required',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group flwexam-qtype-panel flwexam-qtype-panel-multichoice');
echo html_writer::label(get_string('correctanswer', 'local_flwexam'), 'flwexam-correctanswer-mc');
echo html_writer::select(local_flwexam_answer_key_options(), 'correctanswer_mc', $defaults['correctanswer'], false, [
    'id' => 'flwexam-correctanswer-mc',
    'class' => 'form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group flwexam-qtype-panel flwexam-qtype-panel-truefalse');
echo html_writer::label(get_string('correctanswer', 'local_flwexam'), 'flwexam-correctanswer-tf');
echo html_writer::select(local_flwexam_truefalse_answer_options(), 'correctanswer_tf', $defaults['correctanswer'], false, [
    'id' => 'flwexam-correctanswer-tf',
    'class' => 'form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group flwexam-qtype-panel flwexam-qtype-panel-shortanswer');
echo html_writer::label(get_string('correctanswer', 'local_flwexam'), 'flwexam-correctanswer-short');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'correctanswer_short',
    'id' => 'flwexam-correctanswer-short',
    'class' => 'form-control',
    'value' => $defaults['correctanswer'],
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('flwexam-options-grid flwexam-qtype-panel flwexam-qtype-panel-multichoice');
foreach (local_flwexam_answer_key_options() as $key => $label) {
    echo html_writer::start_div('form-group');
    echo html_writer::label($label, 'flwexam-option-' . $key);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'option_' . $key,
        'id' => 'flwexam-option-' . $key,
        'class' => 'form-control',
        'value' => $defaults['options'][$key] ?? '',
        'required' => 'required',
    ]);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::script(
    '(function() {' .
    'var qtype = document.getElementById("flwexam-qtype");' .
    'if (!qtype) { return; }' .
    'function refresh() {' .
    'var value = qtype.value;' .
    'Array.prototype.slice.call(document.querySelectorAll(".flwexam-qtype-panel")).forEach(function(panel) {' .
    'var active = panel.classList.contains("flwexam-qtype-panel-" + value);' .
    'panel.hidden = !active;' .
    'Array.prototype.slice.call(panel.querySelectorAll("input, select, textarea")).forEach(function(field) {' .
    'field.disabled = !active;' .
    '});' .
    '});' .
    '}' .
    'qtype.addEventListener("change", refresh);' .
    'refresh();' .
    '})();'
);

echo html_writer::start_div('flwexam-action-row');
echo html_writer::tag('label',
    html_writer::checkbox('visible', 1, !empty($defaults['visible']), get_string('questionvisible', 'local_flwexam')),
    ['class' => 'form-check-label']
);
echo html_writer::end_div();

echo html_writer::start_div('flwexam-action-row');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => $editingquestion ? get_string('updatequestion', 'local_flwexam') : get_string('addquestion', 'local_flwexam'),
]);
if ($editingquestion) {
    echo html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();
echo html_writer::end_tag('form');

$questions = $DB->get_records('local_flwexam_questions', ['examid' => $examid], 'sortorder ASC, id ASC');
echo html_writer::tag('h3', get_string('existingquestions', 'local_flwexam'));
if (!$questions) {
    echo html_writer::div(get_string('noquestions', 'local_flwexam'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable flwexam-table';
    $table->head = [
        get_string('sortorder', 'local_flwexam'),
        get_string('questiontype', 'local_flwexam'),
        get_string('questiontext', 'local_flwexam'),
        get_string('skill', 'local_flwexam'),
        get_string('kpcode', 'local_flwexam'),
        get_string('correctanswer', 'local_flwexam'),
        get_string('status', 'local_flwexam'),
        get_string('actions', 'local_flwexam'),
    ];
    foreach ($questions as $question) {
        $table->data[] = [
            (int)$question->sortorder,
            s(local_flwexam_question_type_options()[$question->qtype] ?? $question->qtype),
            s(shorten_text($question->questiontext, 120)),
            s(ucfirst($question->skill)),
            s($question->kpcode),
            s(core_text::strtoupper($question->correctanswer)),
            $question->visible ? get_string('visible') : get_string('hidden'),
            html_writer::link(
                new moodle_url('/local/flwexam/questions.php', ['examid' => $examid, 'qid' => (int)$question->id]),
                get_string('editquestion', 'local_flwexam'),
                ['class' => 'btn btn-secondary btn-sm']
            ),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::end_div();
echo $output->footer();

/**
 * Question type options.
 *
 * @return array
 */
function local_flwexam_question_type_options(): array {
    return [
        'multichoice' => get_string('qtypemultichoice', 'local_flwexam'),
        'truefalse' => get_string('qtypetruefalse', 'local_flwexam'),
        'shortanswer' => get_string('qtypeshortanswer', 'local_flwexam'),
    ];
}

/**
 * Question skill options.
 *
 * @return array
 */
function local_flwexam_question_skill_options(): array {
    return [
        'listening' => get_string('listening', 'local_flwexam'),
        'speaking' => get_string('speaking', 'local_flwexam'),
        'reading' => get_string('reading', 'local_flwexam'),
        'writing' => get_string('writing', 'local_flwexam'),
    ];
}

/**
 * Answer key options.
 *
 * @return array
 */
function local_flwexam_answer_key_options(): array {
    return [
        'a' => get_string('optiona', 'local_flwexam'),
        'b' => get_string('optionb', 'local_flwexam'),
        'c' => get_string('optionc', 'local_flwexam'),
        'd' => get_string('optiond', 'local_flwexam'),
    ];
}

/**
 * True/false answer options.
 *
 * @return array
 */
function local_flwexam_truefalse_answer_options(): array {
    return [
        'true' => get_string('optiontrue', 'local_flwexam'),
        'false' => get_string('optionfalse', 'local_flwexam'),
    ];
}

/**
 * Next question sort order.
 *
 * @param int $examid
 * @return int
 */
function local_flwexam_next_question_sortorder(int $examid): int {
    global $DB;

    $max = $DB->get_field_sql(
        'SELECT MAX(sortorder) FROM {local_flwexam_questions} WHERE examid = :examid',
        ['examid' => $examid]
    );
    return ((int)$max) + 1;
}

/**
 * Form defaults for adding or editing a question.
 *
 * @param stdClass|null $question
 * @param int $examid
 * @return array
 */
function local_flwexam_question_defaults(?stdClass $question, int $examid): array {
    $options = ['a' => '', 'b' => '', 'c' => '', 'd' => ''];
    if ($question) {
        foreach (json_decode($question->optionsjson ?? '[]', true) ?: [] as $option) {
            if (!empty($option['key'])) {
                $options[$option['key']] = $option['text'] ?? '';
            }
        }
        return [
            'qtype' => $question->qtype,
            'questiontext' => $question->questiontext,
            'skill' => $question->skill,
            'kpcode' => $question->kpcode,
            'correctanswer' => $question->correctanswer,
            'weight' => format_float((float)$question->weight, 1),
            'sortorder' => (int)$question->sortorder,
            'visible' => (int)$question->visible,
            'options' => $options,
        ];
    }

    return [
        'qtype' => 'multichoice',
        'questiontext' => '',
        'skill' => 'listening',
        'kpcode' => '',
        'correctanswer' => 'a',
        'weight' => '1.0',
        'sortorder' => local_flwexam_next_question_sortorder($examid),
        'visible' => 1,
        'options' => $options,
    ];
}

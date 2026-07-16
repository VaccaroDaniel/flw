<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$language = optional_param('language', '', PARAM_ALPHANUMEXT);
if ($language === '') {
    $language = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
}
$language = \local_flwmedia\manager::normalize_language($language);
$tab = optional_param('tab', 'entries', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$context = local_flwmedia_require_practice_access('local/flwmedia:manage');
$url = new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => $tab]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('managepractice', 'local_flwmedia'));
$PAGE->set_heading(get_string('managepractice', 'local_flwmedia'));

if (data_submitted() && confirm_sesskey()) {
    if ($action === 'savecategory') {
        local_flwmedia_save_category_from_request($language);
        redirect(new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'categories']));
    } else if ($action === 'deletecategory' && $id > 0) {
        $DB->delete_records('local_flwmedia_categories', ['id' => $id, 'lang' => $language]);
        redirect(new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'categories']));
    } else if ($action === 'saveitem') {
        local_flwmedia_save_item_from_request($language);
        redirect(new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'entries']));
    } else if ($action === 'deleteitem' && $id > 0) {
        $DB->delete_records('local_flwmedia_items', ['id' => $id, 'lang' => $language]);
        redirect(new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'entries']));
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('flwmedia-manage');
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/flwmedia/index.php', ['language' => $language]),
        get_string('backtopractice', 'local_flwmedia'),
        ['class' => 'btn btn-secondary']
    ),
    'mb-3'
);
echo local_flwmedia_language_form($language, $tab);

$tabs = [
    new tabobject('entries', new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'entries']), get_string('entries', 'local_flwmedia')),
    new tabobject('categories', new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'categories']), get_string('categories', 'local_flwmedia')),
];
echo $OUTPUT->tabtree($tabs, $tab);

if ($tab === 'categories') {
    local_flwmedia_render_category_manager($language, $id);
} else {
    local_flwmedia_render_entry_manager($language, $id);
}

echo html_writer::end_div();
echo $OUTPUT->footer();

/**
 * Render language selector.
 *
 * @param string $language Language code.
 * @param string $tab Current tab.
 * @return string
 */
function local_flwmedia_language_form(string $language, string $tab): string {
    $out = html_writer::start_tag('form', ['method' => 'get', 'class' => 'card card-body mb-3']);
    $out .= html_writer::tag('h3', get_string('selectlanguage', 'local_flwmedia'), ['class' => 'h5 mb-2']);
    $out .= html_writer::start_div('form-inline');
    $out .= html_writer::label(get_string('language', 'local_flwmedia'), 'flwmedia-language', false, ['class' => 'mr-2']);
    $out .= html_writer::select(local_flwmedia_language_options($language), 'language', $language, false, [
        'id' => 'flwmedia-language',
        'class' => 'form-control mr-2',
    ]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
    $out .= html_writer::tag('button', get_string('apply'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    $out .= html_writer::end_div();
    $out .= html_writer::end_tag('form');
    return $out;
}

/**
 * Get languages available for Practice management.
 *
 * @param string $current Current language.
 * @return array
 */
function local_flwmedia_language_options(string $current): array {
    global $DB;

    $options = [
        'en' => 'English (en)',
        'ja' => 'Japanese (ja)',
        'zh' => 'Chinese (zh)',
        'ru' => 'Russian (ru)',
        'de' => 'German (de)',
        'fr' => 'French (fr)',
        'es' => 'Spanish (es)',
    ];

    foreach (['local_flwmedia_items', 'local_flwmedia_categories'] as $table) {
        if (!$DB->get_manager()->table_exists($table)) {
            continue;
        }
        $records = $DB->get_records_sql("SELECT DISTINCT lang FROM {{$table}} WHERE lang <> '' ORDER BY lang ASC");
        foreach ($records as $record) {
            if (!isset($options[$record->lang])) {
                $options[$record->lang] = strtoupper($record->lang) . ' (' . $record->lang . ')';
            }
        }
    }

    if (!isset($options[$current])) {
        $options[$current] = strtoupper($current) . ' (' . $current . ')';
    }

    return $options;
}

/**
 * Render category management.
 *
 * @param string $language Language code.
 * @param int $editid Category id to edit.
 */
function local_flwmedia_render_category_manager(string $language, int $editid): void {
    global $DB, $OUTPUT;

    $category = $editid > 0 ? $DB->get_record('local_flwmedia_categories', ['id' => $editid, 'lang' => $language], '*', IGNORE_MISSING) : null;
    echo $OUTPUT->heading($category ? get_string('editcategory', 'local_flwmedia') : get_string('addcategory', 'local_flwmedia'), 3);
    echo local_flwmedia_category_form($language, $category);

    $records = $DB->get_records('local_flwmedia_categories', ['lang' => $language], 'sortorder ASC, name ASC');
    $table = new html_table();
    $table->head = [
        get_string('categorykey', 'local_flwmedia'),
        get_string('name'),
        get_string('mode', 'local_flwmedia'),
        get_string('sortorder', 'local_flwmedia'),
        get_string('visible'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $editurl = new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'categories', 'id' => $record->id]);
        $delete = local_flwmedia_delete_button('deletecategory', $record->id, $language, 'categories');
        $table->data[] = [
            s($record->categorykey),
            s($record->name),
            s($record->mode),
            (int)$record->sortorder,
            $record->visible ? get_string('yes') : get_string('no'),
            html_writer::link($editurl, get_string('edit')) . ' ' . $delete,
        ];
    }
    echo html_writer::table($table);
}

/**
 * Category edit form.
 *
 * @param string $language Language.
 * @param stdClass|null $category Category.
 * @return string
 */
function local_flwmedia_category_form(string $language, ?stdClass $category): string {
    $category = $category ?? (object)[
        'id' => 0,
        'categorykey' => '',
        'name' => '',
        'mode' => '',
        'description' => '',
        'sortorder' => 0,
        'visible' => 1,
    ];

    $out = html_writer::start_tag('form', ['method' => 'post', 'class' => 'card card-body mb-4']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savecategory']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'language', 'value' => $language]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'categories']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$category->id]);
    $out .= local_flwmedia_text_input('categorykey', get_string('categorykey', 'local_flwmedia'), $category->categorykey, true);
    $out .= local_flwmedia_text_input('name', get_string('name'), $category->name, true);
    $out .= local_flwmedia_select('mode', get_string('mode', 'local_flwmedia'), $category->mode, ['' => get_string('allmodes', 'local_flwmedia')] + array_combine(\local_flwmedia\manager::MODES, \local_flwmedia\manager::MODES));
    $out .= local_flwmedia_textarea('description', get_string('description'), $category->description);
    $out .= local_flwmedia_text_input('sortorder', get_string('sortorder', 'local_flwmedia'), (string)$category->sortorder);
    $out .= local_flwmedia_checkbox('visible', get_string('visible'), (int)$category->visible);
    $out .= html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    $out .= html_writer::end_tag('form');
    return $out;
}

/**
 * Render entry management.
 *
 * @param string $language Language code.
 * @param int $editid Entry id to edit.
 */
function local_flwmedia_render_entry_manager(string $language, int $editid): void {
    global $DB, $OUTPUT;

    $entry = $editid > 0 ? $DB->get_record('local_flwmedia_items', ['id' => $editid, 'lang' => $language], '*', IGNORE_MISSING) : null;
    echo $OUTPUT->heading($entry ? get_string('editentry', 'local_flwmedia') : get_string('addentry', 'local_flwmedia'), 3);
    echo local_flwmedia_entry_form($language, $entry);

    $records = $DB->get_records('local_flwmedia_items', ['lang' => $language], 'mode ASC, sortorder ASC, title ASC', '*', 0, 200);
    $table = new html_table();
    $table->head = [
        get_string('title'),
        get_string('mode', 'local_flwmedia'),
        get_string('category', 'local_flwmedia'),
        get_string('cefr', 'local_flwmedia'),
        get_string('visible'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $editurl = new moodle_url('/local/flwmedia/manage.php', ['language' => $language, 'tab' => 'entries', 'id' => $record->id]);
        $delete = local_flwmedia_delete_button('deleteitem', $record->id, $language, 'entries');
        $table->data[] = [
            s($record->title),
            s($record->mode),
            s($record->category),
            s($record->cefr),
            $record->visible ? get_string('yes') : get_string('no'),
            html_writer::link($editurl, get_string('edit')) . ' ' . $delete,
        ];
    }
    echo html_writer::table($table);
}

/**
 * Entry edit form.
 *
 * @param string $language Language.
 * @param stdClass|null $entry Entry.
 * @return string
 */
function local_flwmedia_entry_form(string $language, ?stdClass $entry): string {
    global $DB;

    $entry = $entry ?? (object)[
        'id' => 0,
        'courseid' => SITEID,
        'unitcode' => '',
        'lessoncode' => '',
        'mode' => 'watch',
        'category' => '',
        'title' => '',
        'description' => '',
        'mediaurl' => '',
        'posterurl' => '',
        'subtitleurl' => '',
        'transcript' => '',
        'readtext' => '',
        'expectedtext' => '',
        'duration' => 0,
        'cefr' => '',
        'kptags' => '',
        'sortorder' => 0,
        'visible' => 1,
    ];
    $categoryoptions = [];
    foreach (\local_flwmedia\manager::get_categories($language) as $category) {
        $categoryoptions[$category['key']] = $category['label'];
    }
    if ($entry->category !== '' && !isset($categoryoptions[$entry->category])) {
        $categoryoptions[$entry->category] = $entry->category;
    }

    $out = html_writer::start_tag('form', ['method' => 'post', 'class' => 'card card-body mb-4']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveitem']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'language', 'value' => $language]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'entries']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$entry->id]);
    $out .= local_flwmedia_text_input('title', get_string('title'), $entry->title, true);
    $out .= local_flwmedia_select('mode', get_string('mode', 'local_flwmedia'), $entry->mode, array_combine(\local_flwmedia\manager::MODES, \local_flwmedia\manager::MODES));
    $out .= local_flwmedia_select('category', get_string('category', 'local_flwmedia'), $entry->category, ['' => get_string('choose')] + $categoryoptions);
    $out .= local_flwmedia_text_input('unitcode', get_string('unitcode', 'local_flwmedia'), $entry->unitcode);
    $out .= local_flwmedia_text_input('lessoncode', get_string('lessoncode', 'local_flwmedia'), $entry->lessoncode);
    $out .= local_flwmedia_textarea('description', get_string('description'), $entry->description);
    $out .= local_flwmedia_text_input('mediaurl', get_string('mediaurl', 'local_flwmedia'), $entry->mediaurl);
    $out .= local_flwmedia_text_input('posterurl', get_string('posterurl', 'local_flwmedia'), $entry->posterurl);
    $out .= local_flwmedia_text_input('subtitleurl', get_string('subtitleurl', 'local_flwmedia'), $entry->subtitleurl);
    $out .= local_flwmedia_textarea('transcript', get_string('transcript', 'local_flwmedia'), $entry->transcript);
    $out .= local_flwmedia_textarea('readtext', get_string('readtext', 'local_flwmedia'), $entry->readtext);
    $out .= local_flwmedia_textarea('expectedtext', get_string('expectedtext', 'local_flwmedia'), $entry->expectedtext);
    $out .= local_flwmedia_text_input('duration', get_string('duration', 'local_flwmedia'), (string)$entry->duration);
    $out .= local_flwmedia_text_input('cefr', get_string('cefr', 'local_flwmedia'), $entry->cefr);
    $out .= local_flwmedia_text_input('kptags', get_string('kptags', 'local_flwmedia'), $entry->kptags);
    $out .= local_flwmedia_text_input('sortorder', get_string('sortorder', 'local_flwmedia'), (string)$entry->sortorder);
    $out .= local_flwmedia_checkbox('visible', get_string('visible'), (int)$entry->visible);
    $out .= html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    $out .= html_writer::end_tag('form');
    return $out;
}

/**
 * Save a category from request parameters.
 *
 * @param string $language Language.
 */
function local_flwmedia_save_category_from_request(string $language): void {
    global $DB;

    $id = optional_param('id', 0, PARAM_INT);
    $now = time();
    $record = (object)[
        'lang' => $language,
        'categorykey' => clean_param(required_param('categorykey', PARAM_ALPHANUMEXT), PARAM_ALPHANUMEXT),
        'name' => required_param('name', PARAM_TEXT),
        'mode' => optional_param('mode', '', PARAM_ALPHA),
        'description' => optional_param('description', '', PARAM_RAW),
        'sortorder' => optional_param('sortorder', 0, PARAM_INT),
        'visible' => optional_param('visible', 0, PARAM_BOOL) ? 1 : 0,
        'timemodified' => $now,
    ];
    if ($id > 0 && $existing = $DB->get_record('local_flwmedia_categories', ['id' => $id, 'lang' => $language])) {
        $record->id = $existing->id;
        $record->timecreated = $existing->timecreated;
        $DB->update_record('local_flwmedia_categories', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_flwmedia_categories', $record);
    }
}

/**
 * Save an entry from request parameters.
 *
 * @param string $language Language.
 */
function local_flwmedia_save_item_from_request(string $language): void {
    global $DB;

    $id = optional_param('id', 0, PARAM_INT);
    $now = time();
    $record = (object)[
        'courseid' => SITEID,
        'unitcode' => optional_param('unitcode', '', PARAM_ALPHANUMEXT),
        'lessoncode' => optional_param('lessoncode', '', PARAM_ALPHANUMEXT),
        'mode' => required_param('mode', PARAM_ALPHA),
        'category' => optional_param('category', '', PARAM_ALPHANUMEXT),
        'title' => required_param('title', PARAM_TEXT),
        'description' => optional_param('description', '', PARAM_RAW),
        'mediaurl' => optional_param('mediaurl', '', PARAM_RAW),
        'posterurl' => optional_param('posterurl', '', PARAM_RAW),
        'subtitleurl' => optional_param('subtitleurl', '', PARAM_RAW),
        'transcript' => optional_param('transcript', '', PARAM_RAW),
        'readtext' => optional_param('readtext', '', PARAM_RAW),
        'expectedtext' => optional_param('expectedtext', '', PARAM_RAW),
        'duration' => optional_param('duration', 0, PARAM_INT),
        'lang' => $language,
        'cefr' => optional_param('cefr', '', PARAM_ALPHANUMEXT),
        'kptags' => optional_param('kptags', '', PARAM_TEXT),
        'sortorder' => optional_param('sortorder', 0, PARAM_INT),
        'visible' => optional_param('visible', 0, PARAM_BOOL) ? 1 : 0,
        'timemodified' => $now,
    ];
    if (!in_array($record->mode, \local_flwmedia\manager::MODES, true)) {
        throw new invalid_parameter_exception('Invalid practice mode.');
    }
    if ($id > 0 && $existing = $DB->get_record('local_flwmedia_items', ['id' => $id, 'lang' => $language])) {
        $record->id = $existing->id;
        $record->timecreated = $existing->timecreated;
        $DB->update_record('local_flwmedia_items', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_flwmedia_items', $record);
    }
}

/**
 * Render a text input.
 *
 * @param string $name Name.
 * @param string $label Label.
 * @param string $value Value.
 * @param bool $required Required flag.
 * @return string
 */
function local_flwmedia_text_input(string $name, string $label, string $value, bool $required = false): string {
    $attrs = ['name' => $name, 'value' => $value, 'class' => 'form-control'];
    if ($required) {
        $attrs['required'] = 'required';
    }
    return html_writer::div(
        html_writer::label($label, 'id_' . $name) .
        html_writer::empty_tag('input', ['id' => 'id_' . $name] + $attrs),
        'form-group'
    );
}

/**
 * Render a textarea.
 *
 * @param string $name Name.
 * @param string $label Label.
 * @param string $value Value.
 * @return string
 */
function local_flwmedia_textarea(string $name, string $label, string $value): string {
    return html_writer::div(
        html_writer::label($label, 'id_' . $name) .
        html_writer::tag('textarea', s($value), ['id' => 'id_' . $name, 'name' => $name, 'class' => 'form-control', 'rows' => 3]),
        'form-group'
    );
}

/**
 * Render a select.
 *
 * @param string $name Name.
 * @param string $label Label.
 * @param string $selected Selected value.
 * @param array $options Options.
 * @return string
 */
function local_flwmedia_select(string $name, string $label, string $selected, array $options): string {
    return html_writer::div(
        html_writer::label($label, 'id_' . $name) .
        html_writer::select($options, $name, $selected, false, ['id' => 'id_' . $name, 'class' => 'form-control']),
        'form-group'
    );
}

/**
 * Render a checkbox.
 *
 * @param string $name Name.
 * @param string $label Label.
 * @param int $checked Checked flag.
 * @return string
 */
function local_flwmedia_checkbox(string $name, string $label, int $checked): string {
    return html_writer::div(
        html_writer::checkbox($name, 1, (bool)$checked, $label),
        'form-group'
    );
}

/**
 * Render a delete button.
 *
 * @param string $action Action.
 * @param int $id Row id.
 * @param string $language Language.
 * @param string $tab Tab.
 * @return string
 */
function local_flwmedia_delete_button(string $action, int $id, string $language, string $tab): string {
    $out = html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'language', 'value' => $language]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
    $out .= html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-link p-0 ml-2']);
    $out .= html_writer::end_tag('form');
    return $out;
}

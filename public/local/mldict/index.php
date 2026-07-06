<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_mldict\local\dictionary;

require_login();
$context = context_system::instance();
require_capability('local/mldict:view', $context);

$q = optional_param('q', '', PARAM_TEXT);
$lang = optional_param('lang', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$page = max(0, $page);
$perpage = 100;

$url = new moodle_url('/local/mldict/index.php', ['q' => $q, 'lang' => $lang]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_mldict'));
$PAGE->set_heading(get_string('pluginname', 'local_mldict'));
$PAGE->requires->css('/local/mldict/styles.css');

$totalentries = dictionary::count_entries($q, $lang);
$entries = dictionary::search_entries($q, $lang, $perpage, $page * $perpage);

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::start_div('local-mldict-actions');
if (has_capability('local/mldict:manage', $context)) {
    echo html_writer::link(new moodle_url('/local/mldict/edit.php'), get_string('addentry', 'local_mldict'), ['class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link(new moodle_url('/local/mldict/import.php'), get_string('importcsv', 'local_mldict'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

$langoptions = ['' => get_string('alllanguages', 'local_mldict')] + dictionary::lang_options();
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'local-mldict-search']);
echo html_writer::label(get_string('searchdictionary', 'local_mldict'), 'local-mldict-q', false, ['class' => 'accesshide']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'q', 'id' => 'local-mldict-q', 'value' => s($q), 'placeholder' => get_string('searchdictionary', 'local_mldict')]);
echo html_writer::select($langoptions, 'lang', $lang, false);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (!$entries) {
    echo $output->notification(get_string('noentries', 'local_mldict'), 'info');
} else {
    if ($totalentries > $perpage) {
        echo $output->render(new paging_bar($totalentries, $page, $perpage, $url));
    }

    $table = new html_table();
    $table->head = [
        get_string('headword', 'local_mldict'),
        get_string('sourcelang', 'local_mldict'),
        get_string('partofspeech', 'local_mldict'),
        get_string('cefrlevel', 'local_mldict'),
        '',
    ];
    foreach ($entries as $entry) {
        $actions = html_writer::link(new moodle_url('/local/mldict/view.php', ['id' => $entry->id]), get_string('view'));
        if (has_capability('local/mldict:manage', $context)) {
            $actions .= ' | ' . html_writer::link(new moodle_url('/local/mldict/edit.php', ['id' => $entry->id]), get_string('edit'));
            $actions .= ' | ' . html_writer::link(new moodle_url('/local/mldict/delete.php', ['id' => $entry->id]), get_string('delete'));
        }
        $table->data[] = [
            html_writer::link(new moodle_url('/local/mldict/view.php', ['id' => $entry->id]), format_string($entry->headword)),
            s(dictionary::lang_label($entry->sourcelang)),
            s($entry->partofspeech),
            s($entry->cefrlevel),
            $actions,
        ];
    }
    echo html_writer::table($table);

    if ($totalentries > $perpage) {
        echo $output->render(new paging_bar($totalentries, $page, $perpage, $url));
    }
}

echo $output->footer();

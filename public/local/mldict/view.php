<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_mldict\local\dictionary;

$id = required_param('id', PARAM_INT);
require_login();
$context = context_system::instance();
require_capability('local/mldict:view', $context);

$entry = dictionary::get_full_entry($id);
$url = new moodle_url('/local/mldict/view.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($entry->headword));
$PAGE->set_heading(get_string('pluginname', 'local_mldict'));
$PAGE->requires->css('/local/mldict/styles.css');

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::div(
    html_writer::link(new moodle_url('/local/mldict/index.php'), get_string('backtodictionary', 'local_mldict'), ['class' => 'btn btn-secondary']) .
    (has_capability('local/mldict:manage', $context) ? ' ' . html_writer::link(new moodle_url('/local/mldict/edit.php', ['id' => $id]), get_string('editentry', 'local_mldict'), ['class' => 'btn btn-primary']) : ''),
    'local-mldict-actions'
);

echo dictionary::render_entry_html($entry);

echo $output->footer();

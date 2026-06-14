<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_mldict\local\dictionary;

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('local/mldict:manage', $context);

$entry = dictionary::get_entry($id);
$url = new moodle_url('/local/mldict/delete.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('deleteentry', 'local_mldict'));
$PAGE->set_heading(get_string('pluginname', 'local_mldict'));

if ($confirm) {
    require_sesskey();
    dictionary::delete_entry($id);
    redirect(new moodle_url('/local/mldict/index.php'), get_string('entrydeleted', 'local_mldict'));
}

$output = $PAGE->get_renderer('core');
echo $output->header();
echo $output->confirm(
    get_string('confirmdelete', 'local_mldict') . ' ' . format_string($entry->headword),
    new moodle_url('/local/mldict/delete.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
    new moodle_url('/local/mldict/view.php', ['id' => $id])
);
echo $output->footer();

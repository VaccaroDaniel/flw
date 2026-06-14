<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_mldict\form\entry_form;
use local_mldict\local\dictionary;

$id = optional_param('id', 0, PARAM_INT);
require_login();
$context = context_system::instance();
require_capability('local/mldict:manage', $context);

$url = new moodle_url('/local/mldict/edit.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($id ? get_string('editentry', 'local_mldict') : get_string('addentry', 'local_mldict'));
$PAGE->set_heading(get_string('pluginname', 'local_mldict'));
$PAGE->requires->css('/local/mldict/styles.css');

$mform = new entry_form($url);
if ($id) {
    $entry = dictionary::get_entry($id);
    $mform->set_data(dictionary::form_data_from_entry($entry));
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/mldict/index.php'));
} else if ($data = $mform->get_data()) {
    $entryid = dictionary::save_form_data($data);
    redirect(new moodle_url('/local/mldict/view.php', ['id' => $entryid]), get_string('entrysaved', 'local_mldict'));
}

$output = $PAGE->get_renderer('core');
echo $output->header();
$mform->display();
echo $output->footer();

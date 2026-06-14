<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_mldict\local\dictionary;

class local_mldict_import_form extends moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('textarea', 'csvtext', get_string('csvtext', 'local_mldict'), ['rows' => 16, 'cols' => 110]);
        $mform->setType('csvtext', PARAM_RAW);
        $mform->addRule('csvtext', null, 'required', null, 'client');
        $mform->addHelpButton('csvtext', 'csvtext', 'local_mldict');
        $this->add_action_buttons(true, get_string('importcsv', 'local_mldict'));
    }
}

require_login();
$context = context_system::instance();
require_capability('local/mldict:manage', $context);

$url = new moodle_url('/local/mldict/import.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('importcsv', 'local_mldict'));
$PAGE->set_heading(get_string('pluginname', 'local_mldict'));
$PAGE->requires->css('/local/mldict/styles.css');

$mform = new local_mldict_import_form($url);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/mldict/index.php'));
} else if ($data = $mform->get_data()) {
    $count = dictionary::import_csv_text($data->csvtext);
    redirect(new moodle_url('/local/mldict/index.php'), get_string('importedcount', 'local_mldict', $count));
}

$output = $PAGE->get_renderer('core');
echo $output->header();
echo html_writer::tag('p', 'CSV columns: headword, sourcelang, partofspeech, cefrlevel, definition, translations, examples');
echo html_writer::tag('pre', s('study,en,verb,A1,"to learn about a subject","es=estudiar|fr=étudier|de=lernen|ja=勉強する","en=I study English every day."'));
$mform->display();
echo $output->footer();

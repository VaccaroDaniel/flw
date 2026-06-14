<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mldict\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_mldict\local\dictionary;
use moodleform;

class entry_form extends moodleform {
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'headword', get_string('headword', 'local_mldict'), ['size' => 60]);
        $mform->setType('headword', PARAM_TEXT);
        $mform->addRule('headword', null, 'required', null, 'client');

        $mform->addElement('select', 'sourcelang', get_string('sourcelang', 'local_mldict'), dictionary::lang_options());
        $mform->setType('sourcelang', PARAM_ALPHANUMEXT);
        $defaultlang = get_config('local_mldict', 'defaultsourcelang') ?: 'en';
        $mform->setDefault('sourcelang', $defaultlang);

        $mform->addElement('select', 'partofspeech', get_string('partofspeech', 'local_mldict'), dictionary::pos_options());
        $mform->setType('partofspeech', PARAM_ALPHANUMEXT);

        $mform->addElement('select', 'cefrlevel', get_string('cefrlevel', 'local_mldict'), dictionary::cefr_options());
        $mform->setType('cefrlevel', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'pronunciation', get_string('pronunciation', 'local_mldict'), ['size' => 60]);
        $mform->setType('pronunciation', PARAM_TEXT);

        $mform->addElement('text', 'phonetic', get_string('phonetic', 'local_mldict'), ['size' => 60]);
        $mform->setType('phonetic', PARAM_TEXT);

        $mform->addElement('textarea', 'definition', get_string('definition', 'local_mldict'), ['rows' => 4, 'cols' => 80]);
        $mform->setType('definition', PARAM_TEXT);

        $mform->addElement('textarea', 'translations', get_string('translations', 'local_mldict'), ['rows' => 8, 'cols' => 80]);
        $mform->setType('translations', PARAM_TEXT);
        $mform->addHelpButton('translations', 'translations', 'local_mldict');

        $mform->addElement('textarea', 'examples', get_string('examples', 'local_mldict'), ['rows' => 8, 'cols' => 80]);
        $mform->setType('examples', PARAM_TEXT);
        $mform->addHelpButton('examples', 'examples', 'local_mldict');

        $mform->addElement('textarea', 'notes', get_string('notes', 'local_mldict'), ['rows' => 4, 'cols' => 80]);
        $mform->setType('notes', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('saveentry', 'local_mldict'));
    }
}

<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form.
 */
class mod_flwaispeaking_mod_form extends moodleform_mod {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('flwaispeakingname', 'flwaispeaking'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'flwsettings', get_string('flwsettings', 'flwaispeaking'));

        $tasktypes = [
            'topic' => get_string('tasktype_topic', 'flwaispeaking'),
            'readaloud' => get_string('tasktype_readaloud', 'flwaispeaking'),
        ];
        $mform->addElement('select', 'tasktype', get_string('tasktype', 'flwaispeaking'), $tasktypes);
        $mform->setDefault('tasktype', 'topic');
        $mform->addHelpButton('tasktype', 'tasktype', 'flwaispeaking');

        $mform->addElement('textarea', 'prompttext', get_string('prompttext', 'flwaispeaking'), ['rows' => 4, 'cols' => 80]);
        $mform->setType('prompttext', PARAM_TEXT);
        $mform->addHelpButton('prompttext', 'prompttext', 'flwaispeaking');

        $mform->addElement('textarea', 'targettext', get_string('targettext', 'flwaispeaking'), ['rows' => 5, 'cols' => 80]);
        $mform->setType('targettext', PARAM_TEXT);
        $mform->addHelpButton('targettext', 'targettext', 'flwaispeaking');
        $mform->hideIf('targettext', 'tasktype', 'neq', 'readaloud');

        $mform->addElement('text', 'referenceaudiourl', get_string('referenceaudiourl', 'flwaispeaking'), ['size' => '80']);
        $mform->setType('referenceaudiourl', PARAM_URL);
        $mform->addHelpButton('referenceaudiourl', 'referenceaudiourl', 'flwaispeaking');
        $mform->hideIf('referenceaudiourl', 'tasktype', 'neq', 'readaloud');

        $levels = [
            'A1' => 'A1',
            'A2' => 'A2',
            'B1' => 'B1',
            'B2' => 'B2',
            'C1' => 'C1',
            'C2' => 'C2',
        ];
        $mform->addElement('select', 'cefrlevel', get_string('cefrlevel', 'flwaispeaking'), $levels);
        $mform->setDefault('cefrlevel', 'A1');

        $mform->addElement('textarea', 'kpcodes', get_string('kpcodes', 'flwaispeaking'), ['rows' => 5, 'cols' => 80]);
        $mform->setType('kpcodes', PARAM_TEXT);
        $mform->addHelpButton('kpcodes', 'kpcodes', 'flwaispeaking');

        $submissionmodes = [
            'transcript' => get_string('submissionmode_transcript', 'flwaispeaking'),
            'audio' => get_string('submissionmode_audio', 'flwaispeaking'),
            'both' => get_string('submissionmode_both', 'flwaispeaking'),
        ];
        $mform->addElement('select', 'submissionmode', get_string('submissionmode', 'flwaispeaking'), $submissionmodes);
        $mform->setDefault('submissionmode', 'transcript');
        $mform->addHelpButton('submissionmode', 'submissionmode', 'flwaispeaking');

        $mform->addElement('text', 'maxattempts', get_string('maxattempts', 'flwaispeaking'), ['size' => '6']);
        $mform->setType('maxattempts', PARAM_INT);
        $mform->setDefault('maxattempts', 0);
        $mform->addRule('maxattempts', null, 'numeric', null, 'client');
        $mform->addHelpButton('maxattempts', 'maxattempts', 'flwaispeaking');

        $mform->addElement('advcheckbox', 'instantprocess', get_string('instantprocess', 'flwaispeaking'));
        $mform->setDefault('instantprocess', 1);
        $mform->addHelpButton('instantprocess', 'instantprocess', 'flwaispeaking');

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'flwaispeaking'), ['size' => '6']);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 20);
        $mform->addRule('grade', null, 'numeric', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}

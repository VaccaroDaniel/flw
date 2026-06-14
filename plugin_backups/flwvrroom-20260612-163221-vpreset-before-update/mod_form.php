<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form.
 */
class mod_flwvrroom_mod_form extends moodleform_mod {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('flwvrroomname', 'flwvrroom'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('intro', 'flwvrroom'));

        $mform->addElement('header', 'flwsettings', get_string('flwsettings', 'flwvrroom'));

        $levels = [
            'A1' => 'A1',
            'A2' => 'A2',
            'B1' => 'B1',
            'B2' => 'B2',
            'C1' => 'C1',
            'C2' => 'C2',
        ];
        $mform->addElement('select', 'cefrlevel', get_string('cefrlevel', 'flwvrroom'), $levels);
        $mform->setDefault('cefrlevel', 'A1');

        $scenarios = [
            'At the Cafe' => get_string('scenario_cafe', 'flwvrroom'),
            'In the Classroom' => get_string('scenario_classroom', 'flwvrroom'),
            'At the Hotel' => get_string('scenario_hotel', 'flwvrroom'),
            'At the Airport' => get_string('scenario_airport', 'flwvrroom'),
            'At the Supermarket' => get_string('scenario_supermarket', 'flwvrroom'),
        ];
        $mform->addElement('select', 'scenario', get_string('scenario', 'flwvrroom'), $scenarios);
        $mform->setDefault('scenario', 'At the Cafe');

        $mform->addElement('textarea', 'kpcodes', get_string('kpcodes', 'flwvrroom'), ['rows' => 6, 'cols' => 64]);
        $mform->setType('kpcodes', PARAM_TEXT);
        $mform->setDefault('kpcodes', "A1-VOC-FOOD-001\nA1-FUNC-ORDER-001\nA1-LIS-QUESTION-001\nA1-SPK-REPLY-001");
        $mform->addHelpButton('kpcodes', 'kpcodes', 'flwvrroom');

        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'flwvrroom'), ['size' => '6']);
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);
        $mform->addRule('passinggrade', null, 'numeric', null, 'client');

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'flwvrroom'), ['size' => '6']);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->addRule('grade', null, 'numeric', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}

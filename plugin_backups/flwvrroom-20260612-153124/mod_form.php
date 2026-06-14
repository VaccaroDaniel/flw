<?php
// Settings form for FLW VR Room activity.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_flwvrroom_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'flwvrroom'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $levels = [
            'A1' => 'A1', 'A2' => 'A2', 'B1' => 'B1', 'B2' => 'B2', 'C1' => 'C1', 'C2' => 'C2',
        ];
        $mform->addElement('select', 'cefrlevel', get_string('cefrlevel', 'flwvrroom'), $levels);
        $mform->setDefault('cefrlevel', 'A1');

        $scenarios = [
            'cafe' => get_string('scenario_cafe', 'flwvrroom'),
        ];
        $mform->addElement('select', 'scenario', get_string('scenario', 'flwvrroom'), $scenarios);
        $mform->setDefault('scenario', 'cafe');

        $mform->addElement('textarea', 'knowledgepoints', get_string('knowledgepoints', 'flwvrroom'), 'wrap="virtual" rows="6" cols="60"');
        $mform->setType('knowledgepoints', PARAM_TEXT);
        $mform->addHelpButton('knowledgepoints', 'knowledgepoints', 'flwvrroom');
        $mform->setDefault('knowledgepoints', "A1-VOC-FOOD-001\nA1-FUNC-ORDER-001\nA1-LIS-QUESTION-001\nA1-SPK-REPLY-001");

        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'flwvrroom'), ['size' => '5']);
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);
        $mform->addRule('passinggrade', null, 'numeric', null, 'client');

        $mform->addElement('text', 'grade', get_string('grade', 'flwvrroom'), ['size' => '5']);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->addRule('grade', null, 'numeric', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}

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
        $mform->setDefault('kpcodes', '');
        $mform->addHelpButton('kpcodes', 'kpcodes', 'flwvrroom');

        $mform->addElement('header', 'customroomsettings', get_string('customroomsettings', 'flwvrroom'));

        $mform->addElement('advcheckbox', 'customsceneenabled', get_string('customsceneenabled', 'flwvrroom'));
        $mform->setDefault('customsceneenabled', 0);
        $mform->addHelpButton('customsceneenabled', 'customsceneenabled', 'flwvrroom');

        $mform->addElement('text', 'custombackgroundurl', get_string('custombackgroundurl', 'flwvrroom'), ['size' => '80']);
        $mform->setType('custombackgroundurl', PARAM_URL);
        $mform->addHelpButton('custombackgroundurl', 'custombackgroundurl', 'flwvrroom');
        $mform->hideIf('custombackgroundurl', 'customsceneenabled', 'notchecked');

        $mform->addElement('text', 'custommissiontitle', get_string('custommissiontitle', 'flwvrroom'), ['size' => '64']);
        $mform->setType('custommissiontitle', PARAM_TEXT);
        $mform->hideIf('custommissiontitle', 'customsceneenabled', 'notchecked');

        $mform->addElement('textarea', 'custommissiontext', get_string('custommissiontext', 'flwvrroom'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('custommissiontext', PARAM_TEXT);
        $mform->hideIf('custommissiontext', 'customsceneenabled', 'notchecked');

        $mform->addElement('textarea', 'customquizquestion', get_string('customquizquestion', 'flwvrroom'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('customquizquestion', PARAM_TEXT);
        $mform->hideIf('customquizquestion', 'customsceneenabled', 'notchecked');

        $mform->addElement('textarea', 'customanswers', get_string('customanswers', 'flwvrroom'), ['rows' => 5, 'cols' => 80]);
        $mform->setType('customanswers', PARAM_TEXT);
        $mform->addHelpButton('customanswers', 'customanswers', 'flwvrroom');
        $mform->hideIf('customanswers', 'customsceneenabled', 'notchecked');

        $mform->addElement('textarea', 'customhotspots', get_string('customhotspots', 'flwvrroom'), ['rows' => 8, 'cols' => 80]);
        $mform->setType('customhotspots', PARAM_TEXT);
        $mform->addHelpButton('customhotspots', 'customhotspots', 'flwvrroom');
        $mform->hideIf('customhotspots', 'customsceneenabled', 'notchecked');

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

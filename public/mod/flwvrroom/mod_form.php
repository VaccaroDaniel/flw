<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(__DIR__ . '/lib.php');

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

        $roommodes = [
            'panorama' => get_string('roommode_panorama', 'flwvrroom'),
            'builtin3d' => get_string('roommode_builtin3d', 'flwvrroom'),
            'uploaded3d' => get_string('roommode_uploaded3d', 'flwvrroom'),
        ];
        $mform->addElement('select', 'roommode', get_string('roommode', 'flwvrroom'), $roommodes);
        $mform->setDefault('roommode', 'panorama');
        $mform->addHelpButton('roommode', 'roommode', 'flwvrroom');

        $mform->addElement('header', 'model3dsettings', get_string('model3dsettings', 'flwvrroom'));
        $mform->addElement(
            'filemanager',
            'model3dfiles',
            get_string('model3dfiles', 'flwvrroom'),
            null,
            flwvrroom_model3d_filemanager_options($this->get_course())
        );
        $mform->addHelpButton('model3dfiles', 'model3dfiles', 'flwvrroom');
        $mform->hideIf('model3dfiles', 'roommode', 'neq', 'uploaded3d');

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

        $mform->addElement('header', 'speakingsettings', get_string('speakingsettings', 'flwvrroom'));
        $mform->addElement('text', 'speakingscoringurl', get_string('speakingscoringurl', 'flwvrroom'), ['size' => '80']);
        $mform->setType('speakingscoringurl', PARAM_URL);
        $mform->setDefault('speakingscoringurl', 'http://127.0.0.1:8000');
        $mform->addHelpButton('speakingscoringurl', 'speakingscoringurl', 'flwvrroom');

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

    /**
     * Prepare stored model files for editing.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($this->current->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('model3dfiles');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_flwvrroom',
                'model3d',
                0,
                flwvrroom_model3d_filemanager_options($this->get_course())
            );
            $defaultvalues['model3dfiles'] = $draftitemid;
        }
    }

    /**
     * Validate uploaded 3D model resources after Moodle accepts the draft files.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['roommode'] ?? '') !== 'uploaded3d') {
            return $errors;
        }

        $draftitemid = $data['model3dfiles'] ?? 0;
        $draftfiles = $draftitemid ? file_get_all_files_in_draftarea($draftitemid) : [];
        $allowed = ['glb', 'gltf', 'bin', 'png', 'jpg', 'jpeg', 'webp'];
        $hasmodel = false;
        $wrongfiles = [];

        foreach ($draftfiles as $file) {
            $filename = $file->filename ?? '';
            if ($filename === '' || $filename === '.') {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true)) {
                $wrongfiles[] = $filename;
                continue;
            }

            if ($extension === 'glb' || $extension === 'gltf') {
                $hasmodel = true;
            }
        }

        if (!empty($wrongfiles)) {
            $errors['model3dfiles'] = get_string('model3dfiles_invalid', 'flwvrroom', implode(', ', $wrongfiles));
        } else if (!$hasmodel) {
            $errors['model3dfiles'] = get_string('model3dfiles_required', 'flwvrroom');
        }

        return $errors;
    }
}

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

        $mform->addElement('header', 'rolecharactersettings', get_string('rolecharactersettings', 'flwvrroom'));
        $mform->addElement('advcheckbox', 'rolecharacterenabled', get_string('rolecharacterenabled', 'flwvrroom'));
        $mform->setDefault('rolecharacterenabled', 1);
        $mform->addHelpButton('rolecharacterenabled', 'rolecharacterenabled', 'flwvrroom');

        $mform->addElement('text', 'rolecharactername', get_string('rolecharactername', 'flwvrroom'), ['size' => '64']);
        $mform->setType('rolecharactername', PARAM_TEXT);
        $mform->setDefault('rolecharactername', 'Mina');
        $mform->hideIf('rolecharactername', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('text', 'rolecharacterrole', get_string('rolecharacterrole', 'flwvrroom'), ['size' => '80']);
        $mform->setType('rolecharacterrole', PARAM_TEXT);
        $mform->setDefault('rolecharacterrole', 'Cafe waiter');
        $mform->hideIf('rolecharacterrole', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('textarea', 'rolecharacterline', get_string('rolecharacterline', 'flwvrroom'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('rolecharacterline', PARAM_TEXT);
        $mform->setDefault('rolecharacterline', 'Good morning. Welcome to FLW Cafe. What would you like?');
        $mform->hideIf('rolecharacterline', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('textarea', 'roleexpectedanswer', get_string('roleexpectedanswer', 'flwvrroom'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('roleexpectedanswer', PARAM_TEXT);
        $mform->setDefault('roleexpectedanswer', 'I would like a coffee, please.');
        $mform->addHelpButton('roleexpectedanswer', 'roleexpectedanswer', 'flwvrroom');
        $mform->hideIf('roleexpectedanswer', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('textarea', 'rolekpcodes', get_string('rolekpcodes', 'flwvrroom'), ['rows' => 3, 'cols' => 64]);
        $mform->setType('rolekpcodes', PARAM_TEXT);
        $mform->setDefault('rolekpcodes', 'A1-FUNC-ORDER-001');
        $mform->addHelpButton('rolekpcodes', 'rolekpcodes', 'flwvrroom');
        $mform->hideIf('rolekpcodes', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('text', 'rolescore', get_string('rolescore', 'flwvrroom'), ['size' => '6']);
        $mform->setType('rolescore', PARAM_INT);
        $mform->setDefault('rolescore', 20);
        $mform->addRule('rolescore', null, 'numeric', null, 'client');
        $mform->hideIf('rolescore', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('text', 'rolecharacterposition', get_string('rolecharacterposition', 'flwvrroom'), ['size' => '32']);
        $mform->setType('rolecharacterposition', PARAM_TEXT);
        $mform->setDefault('rolecharacterposition', '-2.20|0.00|-2.60');
        $mform->addHelpButton('rolecharacterposition', 'rolecharacterposition', 'flwvrroom');
        $mform->hideIf('rolecharacterposition', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('textarea', 'roleturns', get_string('roleturns', 'flwvrroom'), ['rows' => 5, 'cols' => 80]);
        $mform->setType('roleturns', PARAM_TEXT);
        $mform->setDefault(
            'roleturns',
            "Good morning. Welcome to FLW Cafe. What would you like?|I would like a coffee, please.|20|A1-FUNC-ORDER-001,A1-SPK-REPLY-001\n" .
            "Sure. Would you like it hot or iced?|Hot, please.|20|A1-LIS-QUESTION-001,A1-SPK-POLITE-REPLY-001\n" .
            "Would you like milk or sugar?|Milk, please.|20|A1-VOC-FOOD-001,A1-SPK-POLITE-REPLY-001\n" .
            "That is three dollars. Anything else?|No, thank you.|20|A1-FUNC-CHECKOUT-001,A1-SPK-POLITE-REPLY-001"
        );
        $mform->addHelpButton('roleturns', 'roleturns', 'flwvrroom');
        $mform->hideIf('roleturns', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('advcheckbox', 'roleaienabled', get_string('roleaienabled', 'flwvrroom'));
        $mform->setDefault('roleaienabled', 1);
        $mform->addHelpButton('roleaienabled', 'roleaienabled', 'flwvrroom');
        $mform->hideIf('roleaienabled', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('text', 'roleaiturns', get_string('roleaiturns', 'flwvrroom'), ['size' => '6']);
        $mform->setType('roleaiturns', PARAM_INT);
        $mform->setDefault('roleaiturns', 4);
        $mform->addRule('roleaiturns', null, 'numeric', null, 'client');
        $mform->hideIf('roleaiturns', 'rolecharacterenabled', 'notchecked');
        $mform->hideIf('roleaiturns', 'roleaienabled', 'notchecked');

        $mform->addElement('textarea', 'roleaipersonality', get_string('roleaipersonality', 'flwvrroom'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('roleaipersonality', PARAM_TEXT);
        $mform->setDefault('roleaipersonality', 'Friendly, patient, short replies, suitable for beginner English learners.');
        $mform->hideIf('roleaipersonality', 'rolecharacterenabled', 'notchecked');
        $mform->hideIf('roleaipersonality', 'roleaienabled', 'notchecked');

        $difficulties = [
            'friendly' => get_string('roleaidifficulty_friendly', 'flwvrroom'),
            'standard' => get_string('roleaidifficulty_standard', 'flwvrroom'),
            'challenge' => get_string('roleaidifficulty_challenge', 'flwvrroom'),
        ];
        $mform->addElement('select', 'roleaidifficulty', get_string('roleaidifficulty', 'flwvrroom'), $difficulties);
        $mform->setDefault('roleaidifficulty', 'friendly');
        $mform->hideIf('roleaidifficulty', 'rolecharacterenabled', 'notchecked');
        $mform->hideIf('roleaidifficulty', 'roleaienabled', 'notchecked');

        $mform->addElement('textarea', 'roleaitargetpattern', get_string('roleaitargetpattern', 'flwvrroom'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('roleaitargetpattern', PARAM_TEXT);
        $mform->setDefault('roleaitargetpattern', 'Practice polite ordering and short clarification questions.');
        $mform->hideIf('roleaitargetpattern', 'rolecharacterenabled', 'notchecked');
        $mform->hideIf('roleaitargetpattern', 'roleaienabled', 'notchecked');

        $mform->addElement('text', 'roleaimaxretries', get_string('roleaimaxretries', 'flwvrroom'), ['size' => '6']);
        $mform->setType('roleaimaxretries', PARAM_INT);
        $mform->setDefault('roleaimaxretries', 1);
        $mform->addRule('roleaimaxretries', null, 'numeric', null, 'client');
        $mform->hideIf('roleaimaxretries', 'rolecharacterenabled', 'notchecked');
        $mform->hideIf('roleaimaxretries', 'roleaienabled', 'notchecked');

        $mform->addElement(
            'filemanager',
            'rolecharacterfiles',
            get_string('rolecharacterfiles', 'flwvrroom'),
            null,
            flwvrroom_rolecharacter_filemanager_options($this->get_course())
        );
        $mform->addHelpButton('rolecharacterfiles', 'rolecharacterfiles', 'flwvrroom');
        $mform->hideIf('rolecharacterfiles', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'flwvrroom'), ['size' => '6']);
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);
        $mform->addRule('passinggrade', null, 'numeric', null, 'client');

        $mform->addElement('header', 'completionrulesettings', get_string('completionrulesettings', 'flwvrroom'));
        $mform->addElement('advcheckbox', 'completionrequirehotspots', get_string('completionrequirehotspots', 'flwvrroom'));
        $mform->setDefault('completionrequirehotspots', 1);

        $mform->addElement('advcheckbox', 'completionrequirespeaking', get_string('completionrequirespeaking', 'flwvrroom'));
        $mform->setDefault('completionrequirespeaking', 1);

        $mform->addElement('advcheckbox', 'completionrequirerole', get_string('completionrequirerole', 'flwvrroom'));
        $mform->setDefault('completionrequirerole', 0);
        $mform->hideIf('completionrequirerole', 'rolecharacterenabled', 'notchecked');

        $mform->addElement('text', 'completionminscore', get_string('completionminscore', 'flwvrroom'), ['size' => '6']);
        $mform->setType('completionminscore', PARAM_INT);
        $mform->setDefault('completionminscore', 70);
        $mform->addRule('completionminscore', null, 'numeric', null, 'client');

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

            $rolecharacterdraftitemid = file_get_submitted_draft_itemid('rolecharacterfiles');
            file_prepare_draft_area(
                $rolecharacterdraftitemid,
                $this->context->id,
                'mod_flwvrroom',
                'rolecharacter3d',
                0,
                flwvrroom_rolecharacter_filemanager_options($this->get_course())
            );
            $defaultvalues['rolecharacterfiles'] = $rolecharacterdraftitemid;
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
        $allowed = ['glb', 'gltf', 'bin', 'png', 'jpg', 'jpeg', 'webp'];

        if (!empty($data['rolecharacterenabled'])) {
            $rolecharacterdraftitemid = $data['rolecharacterfiles'] ?? 0;
            $rolecharacterdraftfiles = $rolecharacterdraftitemid ? file_get_all_files_in_draftarea($rolecharacterdraftitemid) : [];
            $rolecharacterhasfiles = false;
            $rolecharacterhasmodel = false;
            $rolecharacterwrongfiles = [];

            foreach ($rolecharacterdraftfiles as $file) {
                $filename = $file->filename ?? '';
                if ($filename === '' || $filename === '.') {
                    continue;
                }

                $rolecharacterhasfiles = true;
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowed, true)) {
                    $rolecharacterwrongfiles[] = $filename;
                    continue;
                }

                if ($extension === 'glb' || $extension === 'gltf') {
                    $rolecharacterhasmodel = true;
                }
            }

            if (!empty($rolecharacterwrongfiles)) {
                $errors['rolecharacterfiles'] = get_string(
                    'rolecharacterfiles_invalid',
                    'flwvrroom',
                    implode(', ', $rolecharacterwrongfiles)
                );
            } else if ($rolecharacterhasfiles && !$rolecharacterhasmodel) {
                $errors['rolecharacterfiles'] = get_string('rolecharacterfiles_missingmodel', 'flwvrroom');
            }
        }

        if (($data['roommode'] ?? '') !== 'uploaded3d') {
            return $errors;
        }

        $draftitemid = $data['model3dfiles'] ?? 0;
        $draftfiles = $draftitemid ? file_get_all_files_in_draftarea($draftitemid) : [];
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

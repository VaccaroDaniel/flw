<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Extended class of elements for Outro. Only visible when the user reached the end of module content.
 *
 * @package   cdelement_outro
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_outro;

use html_writer;
use mod_contentdesigner\editor;
use moodle_url;

/**
 * Outro element instance, inherit the methods from elmenets base class.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'outro';

    /**
     * Outro button disabled option.
     */
    const OUTRO_BUTTON_DISABLED = 0;

    /**
     * Outro button custom option.
     */
    const OUTRO_BUTTON_CUSTOM = 1;

    /**
     * Outro button next option.
     */
    const OUTRO_BUTTON_NEXT = 2;

    /**
     * Outro button back ot course option.
     */
    const OUTRO_BUTTON_BACKTOCOURSE = 3;

    /**
     * Outro button back ot section option.
     */
    const OUTRO_BUTTON_BACKTOSECTION = 4;

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_outro');
    }

    /**
     * Element shortname which is used as identical purpose.
     *
     * @return string
     */
    public function element_shortname() {
        return self::SHORTNAME;
    }

    /**
     * Outro doesn't supports multiple instance for one course module. It added automatically for module.
     *
     * @return bool
     */
    public function supports_multiple_instance() {
        return false;
    }

    /**
     * List of areafiles which is used the mod_contentdesigner as component.
     *
     * @return array
     */
    public function areafiles() {
        return ['outroimage', 'outrocontent'];
    }

    /**
     * Search area definition.
     *
     * @return array Table and fields to search.
     */
    public function search_area_list(): array {
        return ['cdelement_outro' => 'outrocontent, outrocontentformat'];
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {

        $options = ['maxfiles' => 1, 'accepted_types' => ['image']];
        $mform->addElement('filemanager', 'image', get_string('strimage', 'mod_contentdesigner'), null, $options);
        $mform->addHelpButton('image', 'strimage', 'mod_contentdesigner');

        $editoroptions = $this->editor_options($formobj->_customdata['context']);
        $mform->addElement('editor', 'outrocontent_editor', get_string('content', 'mod_contentdesigner'), null, $editoroptions);
        $mform->setType('outrocontent_editor', PARAM_RAW);
        $mform->addHelpButton('outrocontent_editor', 'content', 'mod_contentdesigner');

        // Primary Button.
        $options = [
            self::OUTRO_BUTTON_DISABLED => get_string('disable'),
            self::OUTRO_BUTTON_CUSTOM => get_string('outro:btncustom', 'mod_contentdesigner'),
            self::OUTRO_BUTTON_NEXT => get_string('outro:btnnext', 'mod_contentdesigner'),
            self::OUTRO_BUTTON_BACKTOCOURSE => get_string('outro:btnbacktocourse', 'mod_contentdesigner'),
            self::OUTRO_BUTTON_BACKTOSECTION => get_string('outro:btnbacktosection', 'mod_contentdesigner'),
        ];
        $mform->addElement('select', 'primarybutton', get_string('primarybutton', 'mod_contentdesigner'), $options);
        $mform->setDefault('primarybutton', self::OUTRO_BUTTON_DISABLED);
        $mform->addHelpButton('primarybutton', 'primarybutton', 'mod_contentdesigner');

        $mform->addElement('text', 'primarytext', get_string('primarybuttontext', 'mod_contentdesigner'),
        'maxlength="100" size="30"');
        $mform->setType('primarytext', PARAM_NOTAGS);
        $mform->addHelpButton('primarytext', 'primarybuttontext', 'mod_contentdesigner');
        $mform->hideIf('primarytext', 'primarybutton', 'neq', self::OUTRO_BUTTON_CUSTOM);

        $mform->addElement('text', 'primaryurl', get_string('primarybuttonurl', 'mod_contentdesigner'), ['size' => "60"]);
        $mform->setType('primaryurl', PARAM_URL);
        $mform->addHelpButton('primaryurl', 'primarybuttonurl', 'mod_contentdesigner');
        $mform->hideIf('primaryurl', 'primarybutton', 'neq', self::OUTRO_BUTTON_CUSTOM);

        // Secondray Button.
        $mform->addElement('select', 'secondarybutton', get_string('secondarybutton', 'mod_contentdesigner'), $options);
        $mform->setDefault('secondarybutton', self::OUTRO_BUTTON_DISABLED);
        $mform->addHelpButton('secondarybutton', 'secondarybutton', 'mod_contentdesigner');

        $mform->addElement('text', 'secondarytext', get_string('secondarybuttontext', 'mod_contentdesigner'),
        'maxlength="100" size="30"');
        $mform->setType('secondarytext', PARAM_NOTAGS);
        $mform->addHelpButton('secondarytext', 'secondarybuttontext', 'mod_contentdesigner');
        $mform->hideIf('secondarytext', 'secondarybutton', 'neq', self::OUTRO_BUTTON_CUSTOM);

        $mform->addElement('text', 'secondaryurl', get_string('secondarybuttonurl', 'mod_contentdesigner'), ['size' => "60"]);
        $mform->setType('secondaryurl', PARAM_URL);
        $mform->addHelpButton('secondaryurl', 'secondarybuttonurl', 'mod_contentdesigner');
        $mform->hideIf('secondaryurl', 'secondarybutton', 'neq', self::OUTRO_BUTTON_CUSTOM);
    }

    /**
     * Initiate the element js for the view page.
     *
     * @return void
     */
    public function initiate_js() {
        global $PAGE;
        $cmdata = $this->cm->get_custom_data();
        $completionendreach = (isset($cmdata['customcompletionrules']['completionendreach'])
            && $cmdata['customcompletionrules']['completionendreach'] == 1) ? true : false;

        if ($completionendreach) {
            $PAGE->requires->js_amd_inline("
                require(['mod_contentdesigner/elements'], function(Elements) {
                    var updateCompletion = false;
                    document.querySelector('#page').addEventListener('scroll', () => {
                        outroCompletion();
                    });
                    window.addEventListener('scroll', () => {
                        outroCompletion();
                    });
                    var modalBody = document.querySelector('.path-course-view .modal-dialog-scrollable .modal-body');
                    if (modalBody !== null) {
                        modalBody.addEventListener('scroll', function(e) {
                            outroCompletion();
                        });
                    }
                    function outroCompletion() {
                        var outroElement = document.querySelector('#outro-reached');
                        if (!updateCompletion && outroElement != null && Elements.inView(outroElement)) {
                            Elements.updateCompletion('completionendreach');
                            updateCompletion = true;
                        }
                    }
                    outroCompletion();
                })"
            );
        }
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $data
     * @return string
     */
    public function render($data) {
        if (!isset($data->id)) {
            return '';
        }

        $file = editor::get_editor($data->cmid)->get_element_areafiles('cdelement_outro_outroimage', $data->id);

        // Outro Content.
        $context = $this->get_context();
        $outrocontent = file_rewrite_pluginfile_urls(
            $data->outrocontent,
            'pluginfile.php',
            $context->id,
            'mod_contentdesigner',
            'cdelement_outro_outrocontent',
            $data->instance
        );
        $outrocontent = format_text($outrocontent, $data->outrocontentformat, ['context' => $context->id]);

        $html = html_writer::start_div('element-outro');
        $html .= html_writer::div('', 'complete-module', ['id' => 'outro-reached']);
        $html .= ($file) ? html_writer::img($file, 'completed', ['class' => 'completion-img img-fluid']) : ''; // Outro image.

        $html .= html_writer::div(html_writer::div($outrocontent, 'outro-content'), 'outro-content-block'); // Outro content block.

        $html .= html_writer::start_div('element-button'); // Outro buttons.
        if (!empty($data->primarybutton)) {
            [$primarybtntext, $primarybtnurl] = $this->get_button_data($data->primarybutton, 'primary', $data);
            if (!empty($primarybtntext)) {
                $html .= html_writer::link($primarybtnurl, $primarybtntext, ['class' => 'btn btn-primary']); // Primary button.
            }
        }
        if (!empty($data->secondarybutton)) {
            [$secondarybtntext, $secondarybtnurl] = $this->get_button_data($data->secondarybutton, 'secondary', $data);
            if (!empty($secondarybtntext)) {
                // Secondary button.
                $html .= html_writer::link($secondarybtnurl, $secondarybtntext, ['class' => 'btn btn-secondary']);
            }
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Save the area files data after the element instance moodle_form submittted.
     *
     * @param stdclas $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {
        global $DB;
        parent::save_areafiles($data);
        file_save_draft_area_files(
            $data->image,
            $data->contextid,
            'mod_contentdesigner',
            'cdelement_outro_outroimage',
            $data->instance
        );

        if (isset($data->contextid)) {
            $context = \context::instance_by_id($data->contextid, MUST_EXIST);
        }
        $editoroptions = $this->editor_options($context);
        if (isset($data->instance)) {
            $itemid = $data->outrocontent_editor['itemid'];
            $data->contentformat = $data->outrocontent_editor['format'];
            $data = file_postupdate_standard_editor(
                $data,
                'outrocontent',
                $editoroptions,
                $context,
                'mod_contentdesigner',
                'cdelement_outro_outrocontent',
                $data->instance
            );
            $updatedata = (object) ['id' => $data->instance, 'outrocontentformat' => $data->outrocontentformat,
                'outrocontent' => $data->outrocontent];
            $DB->update_record('cdelement_outro', $updatedata);
        }
    }

    /**
     * Prepare the form editor elements file data before render the elemnent form.
     *
     * @param stdclass $formdata
     * @return stdclass
     */
    public function prepare_standard_file_editor(&$formdata) {
        // Always call parent.
        $formdata = parent::prepare_standard_file_editor($formdata);
        if (isset($formdata->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('image');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_contentdesigner', 'cdelement_outro_outroimage',
            $formdata->instance, ['subdirs' => 0, 'maxfiles' => 1]);
            $formdata->image = $draftitemid;
        }

        $context = \context_module::instance($this->cmid);
        $editoroptions = $this->editor_options($context);

        if (!isset($formdata->id)) {
            $formdata->id = null;
            $formdata->outrocontentformat = FORMAT_HTML;
            $formdata->outrocontent = '';
        }
        file_prepare_standard_editor(
            $formdata,
            'outrocontent',
            $editoroptions,
            $context,
            'mod_contentdesigner',
            'cdelement_outro_outrocontent',
            $formdata->id
        );

        return $formdata;
    }

    /**
     * Process the update of element instance and genreal options.
     *
     * @param stdclass $data Submitted element moodle form data
     * @return int
     */
    public function update_instance($data) {
        global $DB;
        $formdata = clone $data;
        $formdata->outrocontentformat = $formdata->outrocontent_editor['format'];
        $formdata->outrocontent = $formdata->outrocontent_editor['text'];
        if ($formdata->instanceid == false) {
            $formdata->timemodified = time();
            $formdata->timecreated = time();
            return $DB->insert_record($this->tablename, $formdata);
        } else {
            $formdata->timecreated = time();
            $formdata->id = $formdata->instanceid;
            if ($DB->update_record($this->tablename, $formdata)) {
                return $formdata->id;
            }
        }
    }

    /**
     * Options used in the editor defined.
     *
     * @param context_module $context
     * @return array Filemanager options.
     */
    public function editor_options($context) {
        global $CFG;
        return [
            'subdirs' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => '*',
            'context' => $context,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
        ];
    }

    /**
     * Get the pre defined outro buttons data.
     *
     * @param int $button Button.
     * @param string $type Type of the button
     * @param stdclass $data instance data.
     *
     * @return array
     */
    public function get_button_data($button, $type, $data) {
        global $PAGE, $DB, $CFG;

        $buttontext = '';
        $buttonurl = '';
        $context = $this->get_context();

        switch ($button) {
            case self::OUTRO_BUTTON_NEXT:
                $render = $PAGE->get_renderer('mod_contentdesigner');
                $buttontext = get_string('outro:btnnext', 'mod_contentdesigner');
                $buttonurl = $render->activity_navigation($this->cm->instance, $context)->nextlink->url ?? "javascript:void(0);";
                break;
            case self::OUTRO_BUTTON_CUSTOM:
                if ($type == 'primary') {
                    $buttontext = !empty($data->primarytext) ? $data->primarytext : '';
                    $buttonurl = new moodle_url(!empty($data->primaryurl) ? $data->primaryurl : '');
                } else if ($type == 'secondary') {
                    $buttontext = !empty($data->secondarytext) ? $data->secondarytext : '';
                    $buttonurl = new moodle_url(!empty($data->secondaryurl) ? $data->secondaryurl : '');
                }
                break;
            case self::OUTRO_BUTTON_BACKTOCOURSE:
                $buttontext = get_string('outro:btnbacktocourse', 'mod_contentdesigner');
                $buttonurl = new moodle_url('/course/view.php', ['id' => $this->course->id]);
                break;
            case self::OUTRO_BUTTON_BACKTOSECTION:
                $buttontext = get_string('outro:btnbacktosection', 'mod_contentdesigner');
                $section = $DB->get_record('course_sections', ['id' => $this->cm->section]);
                if ($section->id) {
                    if ($CFG->branch >= 404) {
                        $buttonurl = new moodle_url('/course/section.php', ['id' => $section->id]);
                    } else {
                        $buttonurl = new moodle_url('/course/view.php', ['id' => $this->course->id]);
                        $sectionno = $section->section;
                        if ($sectionno != 0) {
                            $buttonurl->param('section', $sectionno);
                        } else {
                            if (empty($CFG->linkcoursesections)) {
                                return null;
                            }
                            $buttonurl->set_anchor('section-' . $sectionno);
                        }
                    }
                }
                break;
        }
        return [$buttontext, $buttonurl];
    }
}

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
 * Extended class of elements for Richtext.
 *
 * @package   cdelement_richtext
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_richtext;

use html_writer;

/**
 * Richtext element instance extend the contentdesigner/elements base.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'richtext';

    /**
     * Hard-coded value for the 'maxfiles' option.
     */
    const EDITOR_UNLIMITED_FILES = -1;

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_richtext');
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
     * Icon of the element.
     *
     * @param renderer $output
     * @return string HTML fragment
     */
    public function icon($output) {
        return $output->pix_icon('e/source_code', get_string('pluginname', 'cdelement_richtext'));
    }

    /**
     * List of areafiles which is used the mod_contentdesigner as component.
     *
     * @return array
     */
    public function areafiles() {
        return ['content'];
    }

    /**
     * Search area definition.
     *
     * @return array Table and fields to search.
     */
    public function search_area_list(): array {
        return ['cdelement_richtext' => 'content, contentformat'];
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {
        $editoroptions = $this->editor_options($formobj->_customdata['context']);
        $mform->addElement('editor', 'content_editor', get_string('richtext', 'mod_contentdesigner'), null, $editoroptions);
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', null, 'required');
        $mform->addHelpButton('content_editor', 'richtext', 'mod_contentdesigner');
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $data
     * @return string HTML
     */
    public function render($data) {
        $context = $this->get_context();
        $content = file_rewrite_pluginfile_urls(
            $data->content,
            'pluginfile.php',
            $context->id,
            'mod_contentdesigner',
            'cdelement_richtext_content',
            $data->instance
        );
        $content = format_text($content, $data->contentformat, ['context' => $context->id]);
        return html_writer::div(html_writer::div($content, 'richtext-content'), 'richtet-content-block');
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
        $formdata->contentformat = $formdata->content_editor['format'];
        $formdata->content = $formdata->content_editor['text'];
        $formdata->timemodified = time();
        if ($formdata->instanceid == false) {
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
     * Save the area files data after the element instance moodle_form submittted.
     *
     * @param stdclas $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {
        global $DB;
        parent::save_areafiles($data);
        if (isset($data->contextid)) {
            $context = \context::instance_by_id($data->contextid, MUST_EXIST);
        }
        $editoroptions = $this->editor_options($context);
        if (isset($data->instance)) {
            $data->contentformat = $data->content_editor['format'];
            $data = file_postupdate_standard_editor(
                $data,
                'content',
                $editoroptions,
                $context,
                'mod_contentdesigner',
                'cdelement_richtext_content',
                $data->instance
            );
            $updatedata = (object) ['id' => $data->instance, 'contentformat' => $data->contentformat, 'content' => $data->content];
            $DB->update_record('cdelement_richtext', $updatedata);
        }
    }

    /**
     * Prepare the form editor elements file data before render the elemnent form.
     *
     * @param stdclass $data
     * @return stdclass
     */
    public function prepare_standard_file_editor(&$data) {
        $data = parent::prepare_standard_file_editor($data);
        $context = \context_module::instance($this->cmid);
        $editoroptions = $this->editor_options($context);

        if (!isset($data->id)) {
            $data->id = null;
            $data->contentformat = FORMAT_HTML;
            $data->content = '';
        }
        file_prepare_standard_editor(
            $data,
            'content',
            $editoroptions,
            $context,
            'mod_contentdesigner',
            'cdelement_richtext_content',
            $data->id
        );
        return $data;
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
            'maxfiles' => self::EDITOR_UNLIMITED_FILES,
        ];
    }

    /**
     * Prepare data for the duplicate element.
     *
     * @param stdClass $record
     * @return stdClass
     */
    public function prepare_duplicatedata($record) {
        $record->content_editor['format'] = $record->contentformat;
        $record->content_editor['text'] = $record->content;
        return $record;
    }
}

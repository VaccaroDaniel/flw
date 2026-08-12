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
 * Extended class of elements for chapter. it contains major part of editor element content
 *
 * @package    cdelement_h5p
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_h5p;

use mod_contentdesigner\editor;

/**
 * Element h5p definition.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'h5p';

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_h5p');
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
        global $CFG;
        return (file_exists($CFG->dirroot . '/mod/h5pactivity/pix/monologo.png'))
            ? $output->pix_icon('monologo', '', 'mod_h5pactivity', ['class' => 'icon pluginicon'])
            : $output->pix_icon('icon', '', 'mod_h5pactivity', ['class' => 'icon pluginicon']);
    }

    /**
     * List of areafiles which is used the mod_contentdesigner as component.
     *
     * @return array
     */
    public function areafiles() {
        return ['package'];
    }

    /**
     * Save the area files data after the element instance moodle_form submittted.
     *
     * @param stdclas $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {
        parent::save_areafiles($data);
        file_save_draft_area_files($data->package, $data->contextid, 'cdelement_h5p', 'package', $data->instance);
    }

    /**
     * Prepare the form editor elements file data before render the elemnent form.
     *
     * @param stdclass $formdata
     * @return stdclass
     */
    public function prepare_standard_file_editor(&$formdata) {
        $formdata = parent::prepare_standard_file_editor($formdata);

        if (isset($formdata->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('package');
            file_prepare_draft_area($draftitemid, $this->context->id, 'cdelement_h5p', 'package', $formdata->instance,
            ['subdirs' => 0, 'maxfiles' => 1]);
            $formdata->package = $draftitemid;
        }
        return $formdata;
    }

    /**
     * Analyze the H5P is mantory to view upcoming then check the instance is attempted.
     *
     * @param stdclass $instance Instance data of the element.
     * @return bool True if need to stop the next instance Otherwise false if render of next elements.
     */
    public function prevent_nextelements($instance): bool {
        global $USER, $DB;
        if (isset($instance->mandatory) && $instance->mandatory) {
            return !$DB->record_exists('cdelement_h5p_completion', [
                'instance' => $instance->id, 'userid' => $USER->id, 'completion' => true,
            ]);
        }
        return false;
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {
        $options = [
            'accepted_types' => ['.h5p'],
            'maxbytes' => 0,
            'maxfiles' => 1,
            'subdirs' => 0,
        ];

        $mform->addElement('filemanager', 'package', get_string('package', 'mod_h5pactivity'), null, $options);
        $mform->addHelpButton('package', 'package', 'mod_h5pactivity');
        $mform->addRule('package', null, 'required');

        $options = [
            0 => get_string('no'),
            1 => get_string('yes'),
        ];
        $default = get_config('cdelement_h5p', 'mandatory');
        $mform->addElement('select', 'mandatory', get_string('mandatory', 'mod_contentdesigner'), $options);
        $mform->addHelpButton('mandatory', 'mandatory', 'mod_contentdesigner');
        $mform->setDefault('mandatory', $default ?: 0);
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $data
     * @return string
     */
    public function render($data) {
        global $PAGE;
        if (!isset($data->id)) {
            return '';
        }
        $file = editor::get_editor($data->cmid)->get_element_areafiles('package', $data->id, 'cdelement_h5p');
        $PAGE->requires->js_call_amd('cdelement_h5p/h5p', 'init', ['instance' => $data->instance]);
        $completiontable = $this->generate_completion_table($data);
        return \html_writer::div(
            format_text($file), 'h5p-element-instance', ['data-instanceid' => $data->instance]
        ) . $completiontable;
    }

    /**
     * Generate the result table to display the user atempts to user. It display the highest grade of the user attempt.
     *
     * @param stdclass $data Instance data of the element.
     * @return string
     */
    public function generate_completion_table($data) {
        global $USER, $DB, $OUTPUT;
        $instance = isset($data->instance) ? $data->instance : '';
        $params = ['instance' => $instance];
        if (!has_capability('cdelement/h5p:viewstudentrecords', $this->get_context())) {
            $params['userid'] = $USER->id;
        }
        $results = $DB->get_records('cdelement_h5p_completion', $params);
        if (!empty($results)) {
            $strings = (array) get_strings(['score', 'maxscore', 'completion'], 'mod_h5pactivity');
            $table = new \html_table();
            $table->head = array_merge(['#', get_string('date')], $strings, [get_string('success')]);
            foreach ($results as $record) {
                $table->data[] = [
                    $record->id,
                    userdate($record->timecreated, get_string('strftimedatefullshort', 'core_langconfig')),
                    $record->score,
                    json_decode($record->scoredata)->max,
                    ($record->completion ? $OUTPUT->pix_icon('e/tick', 'core') : $OUTPUT->pix_icon('t/dockclose', 'core')),
                    ($record->success ? $OUTPUT->pix_icon('e/tick', 'core') : $OUTPUT->pix_icon('t/dockclose', 'core') ),
                ];
            }
            return \html_writer::tag('h3', get_string('highestgrade', 'cdelement_h5p')) . \html_writer::table($table);
        }
    }

    /**
     * Delete the element settings.
     *
     * @param int $instanceid
     * @return bool $status
     */
    public function delete_element($instanceid) {
        global $DB;
        try {
            $transaction = $DB->start_delegated_transaction();

            // Delete the element settings.
            if ($this->get_instance($instanceid)) {
                $DB->delete_records($this->tablename(), ['id' => $instanceid]);
                $DB->delete_records('cdelement_h5p_completion', ['instance' => $instanceid]);
            }

            if ($this->get_instance_options($instanceid)) {
                // Delete the element general settings.
                $DB->delete_records('contentdesigner_options', ['element' => $this->element_id(),
                    'instance' => $instanceid]);
            }

            $transaction->allow_commit();
        } catch (\Exception $e) {
            // Extra cleanup steps.
            $transaction->rollback($e); // Rethrows exception.
            throw new \moodle_exception('h5pnotdeleted', 'cdelement_h5p');
        }
        return true;
    }
}

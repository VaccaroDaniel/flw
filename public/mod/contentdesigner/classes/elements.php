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
 * Base class for Content designer elements. Commonly used elements methods are defined here.
 *
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner;

/**
 * Base class for Content designer elements.
 */
abstract class elements {
    /**
     * Hard-coded value for the 'maxfiles' option.
     */
    const EDITOR_UNLIMITED_FILES = -1;

    /**
     * Context data for the current module.
     *
     * @var context_module
     */
    public $context;

    /**
     * Course module ID.
     *
     * @var int $cmid
     */
    public $cmid;

    /**
     * Elment Short name.
     *
     * @var string
     */
    public $shortname;

    /**
     * Elment table name.
     *
     * @var string
     */
    public $tablename;

    /**
     * Element ID
     *
     * @var int $elementid
     */
    public $elementid;

    /**
     * Course module
     *
     * @var cm_info $cm course module data
     */
    public $cm;

    /**
     * Course Objecet
     *
     * @var mixed $course Course object
     */
    public $course;

    /**
     * This element is not mandatory.
     */
    public const ENABLE_MANDATORY = 1;

    /**
     * Disable the mandatory for this elemnet.
     */
    public const DISBLE_MANDATORY = 0;

    /**
     * Constructor method, Setup the element basic information and context.
     *
     * @param int $cmid
     */
    public function __construct($cmid) {
        $this->cmid = $cmid;
        $this->shortname = $this->element_shortname();
        $this->tablename = 'cdelement_' . $this->shortname;
        $this->elementid = $this->element_id();
        if ($cmid) {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid);
            $this->cm = $cm;
            $this->course = $course;
            $this->context = $this->get_context();
        }
    }

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    abstract public function element_name();

    /**
     * Element shortname which is used as identical purpose.
     *
     * @return string
     */
    abstract public function element_shortname();

    /**
     * Element form element definition.
     *
     * @param moodle_form $mfrom
     * @param genreal_element_form $formobj
     * @return void
     */
    abstract public function element_form(&$mfrom, $formobj);

    /**
     * Render the view of the element instance which is displayed to the users.
     *
     * @param stdclass $instance
     * @return void
     */
    abstract public function render($instance);

    /**
     * Specify the searchable fields for the element, if search is supported.
     *
     * Return format:
     * ['tablename' => 'field1, contentformatfield']
     *
     * @return array
     */
    public function search_area_list(): array {
        return [];
    }

    /**
     * Verify the elements the standard general options list.
     *
     * @return bool
     */
    public function supports_standard_elements() {
        // By default all the elmenets will supports the standard options.
        return true;
    }

    /**
     * Verify the element is supports the content render method.
     *
     * @return bool
     */
    public function supports_content() {
        return true;
    }

    /**
     * Is the element supports the multiple instance for one activity instance. ie(element_outro)
     *
     * @return bool
     */
    public function supports_multiple_instance() {
        return true;
    }

    /**
     * Icon of the element.
     *
     * @param renderer $output
     * @return string HTML fragment
     */
    public function icon($output) {
        global $CFG;
        $icon = ($CFG->branch >= 405) ? 't/index_drawer' : 't/viewdetails';
        return $output->pix_icon($icon, get_string('plugin'));
    }

    /**
     * Element description, By default description tried from plugin strings list.
     *
     * @return string
     */
    public function element_description() {
        return (get_string_manager()->string_exists('elementdescription',  'cdelement_' . $this->element_shortname()))
            ? get_string('elementdescription', 'cdelement_' . $this->element_shortname()) : '';
    }

    /**
     * Save the area files data after the element instance moodle_form submittted.
     * If the element override the method then should call the parent to save the baackgroung image files.
     *
     * @param stdclas $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {
        if (isset($data->bgimage)) {
            file_save_draft_area_files($data->bgimage, $data->contextid,
            'mod_contentdesigner', $data->elementshortname . 'elementbg', $data->instance
            );
        }

        if (isset($data->description_editor)) {
            $editoroptions = $this->editor_options(\context_module::instance($data->cmid));
            $data = file_postupdate_standard_editor(
                $data, 'description', $editoroptions, \context_module::instance($data->cmid),
                'mod_contentdesigner', $this->element_shortname().'description', $data->instance
            );
        }
    }

    /**
     * Prepare the form editor elements file data before render the elemnent form.
     *
     * @param stdclass $formdata
     * @return stdclass
     */
    public function prepare_standard_file_editor(&$formdata) {
        if (isset($formdata->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('bgimage');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_contentdesigner',
            $this->element_shortname() . 'elementbg', $formdata->instance, ['subdirs' => 0, 'maxfiles' => 1]);
            $formdata->bgimage = $draftitemid;

            // Description editor.
            $editoroptions = $this->editor_options($this->context);
            file_prepare_standard_editor(
                $formdata, 'description', $editoroptions, $this->context, 'mod_contentdesigner',
                $this->element_shortname().'description', $formdata->instance
            );
        }
        return $formdata;
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
     * Get the context instance for the course module.
     *
     * @return \context_module
     */
    public function get_context() {
        $context = \context_module::instance($this->cmid);
        return $context;
    }

    /**
     * Simple information about the element. Used in the element box.
     *
     * @return object
     */
    public function info() {
        global $OUTPUT;
        return (object) [
            'elementid' => $this->element_id(),
            'name' => $this->element_name(),
            'shortname' => $this->shortname,
            'icon' => $this->icon($OUTPUT),
            'description' => $this->element_description(),
        ];
    }

    /**
     * Fetch the record of the cotnent designer module instance.
     *
     * @return stdclass
     */
    public function get_contentdesigner() {
        global $DB;
        $cm = get_coursemodule_from_id('contentdesigner', $this->cmid);
        return $DB->get_record('contentdesigner', ['id' => $cm->instance]);
    }

    /**
     * Get the course module data from the module instance.
     *
     * @param int $contentdesignerid
     * @return cminfo Course module record.
     */
    public function get_cm_from_modinstance($contentdesignerid) {
        $cm = get_coursemodule_from_instance('contentdesigner', $contentdesignerid);
        return $cm;
    }

    /**
     * Vertify the element instance is prevents the loading of next element instance.
     * For example please check the cdelement_h5p
     *
     * @param stdclass $instance Instance data of the element.
     * @return bool True if need to stop the next instance Otherwise false if render of next elements.
     */
    public function prevent_nextelements($instance): bool {
        return false;
    }

    /**
     * Replace the element on refersh the content. Some elements may need to update the content on refresh the elmenet.
     */
    public function supports_replace_onrefresh(): bool {
        return false;
    }

    /**
     * Initiate the element js for the view page.
     *
     * @return void
     */
    public function initiate_js() {
        global $PAGE;
        $data = [
            'cmid' => $this->cmid,
            'contextid' => \context_module::instance($this->cmid)->id,
            'contentdesignerid' => $this->cm->instance,
        ];
        $PAGE->requires->js_call_amd('mod_contentdesigner/elements', 'contentDesignerElementsData', $data);
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $instance
     * @return array
     */
    public function render_element($instance) {
        $data = $this->prepare_formdata($instance->id);
        $html = $this->render($data);
        return ['elementcontent' => $html, 'general' => $data];
    }

    /**
     * Load the element title into inline editable method.
     *
     * @param stdclass $instance Element Instance data.
     * @return string
     */
    public function title_editable($instance) {
        global $OUTPUT;
        $title = $instance->title ?: $this->info()->name;
        $name = 'instance_title[' . $this->shortname . '][' . $instance->id . ']';
        // Todo: Need to implement capability in place of true 4th param.
        $tmpl = new \core\output\inplace_editable('mod_contentdesigner', $name, $this->elementid . $instance->id,
        true, format_string($title), $title, get_string('titleeditable', 'mod_contentdesigner'),
        get_string('newvalue', 'mod_contentdesigner') . format_string($title));

        return $OUTPUT->render($tmpl);
    }

    /**
     * Add elements in DB. During the plugin installtion elements will inserted and created id for elements.
     * Elmenets instance will identified usign the element id.
     *
     * @param string $shortname Shortname of the element.
     * @return bool
     */
    public static function insertelement(string $shortname) {
        global $DB;
        $record = ['shortname' => $shortname, 'timemodified' => time()];
        if (!$DB->record_exists('contentdesigner_elements', ['shortname' => $shortname])) {
            return $DB->insert_record('contentdesigner_elements', $record);
        }
        return true;
    }

    /**
     * Get the Id of the element in the list of available elements list, this id created during the element installation.
     *
     * @return int ID of the element.
     */
    public function element_id() {
        global $DB;
        return $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $this->element_shortname()]);
    }

    /**
     * Get the table name of the current element. By default its the shortname followed by keyword (element_SHORTNAME)
     *
     * @return string Name of the table.
     */
    public function tablename() {
        return 'cdelement_' . $this->element_shortname();
    }

    /**
     * Verify the element table is exists in the DB.
     *
     * @return bool
     */
    public function is_table_exists() {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table($this->tablename);
        return ($dbman->table_exists($table)) ? true : false;
    }

    /**
     * Create the basic instance for the element. Override this function if need to add custom changes.
     *
     * @param int $contentdesignerid Contnet deisnger instance id.
     * @return int Element instance id.
     */
    public function create_basic_instance($contentdesignerid) {
        global $DB;

        $record = [
            'contentdesignerid' => $contentdesignerid,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        if ($this->is_table_exists()) {
            if ($this->tablename = 'cdelement_outro') {
                // Outro general settigns.
                $content = get_config('cdelement_outro', 'outro_content');
                $primarybutton = get_config('cdelement_outro', 'primarybutton');
                $secondarybutton = get_config('cdelement_outro', 'secondarybutton');
                $outrocontenthtml = file_rewrite_pluginfile_urls($content, 'pluginfile.php',
                $this->context->id, 'mod_contentdesigner', 'outrocontent', 0);
                $outrocontenthtml = format_text($outrocontenthtml, FORMAT_HTML, ['trusted' => true, 'noclean' => true]);

                $record['outrocontent'] = $outrocontenthtml ?? '';
                $record['outrocontentformat'] = FORMAT_HTML;
                $record['primarybutton'] = $primarybutton ?? 0;
                $record['secondarybutton'] = $secondarybutton ?? 0;
            }

            $result = $DB->insert_record($this->tablename, $record);
            if ($result) {
                $data = [];
                $fields = $this->get_options_fields();
                foreach ($fields as $field) {
                    $globalvalues = get_config('mod_contentdesigner', $field);
                    $data[$field] = $globalvalues ?? '';
                }

                $data['element'] = $this->elementid;
                $data['instance'] = $result;
                $data['timecreated'] = time();
                $data['timemodified'] = time();
                if (!$DB->record_exists('contentdesigner_options', ['instance' => $result, 'element' => $this->elementid])) {
                    $DB->insert_record('contentdesigner_options', $data);
                }
            }
            return $result;
        } else {
            throw new \moodle_exception('tablenotfound', 'contentdesigner');
        }
    }

    /**
     * Get the element instance data from the give isntanceid. Filter by the visible status.
     *
     * @param int $instanceid Instance id.
     * @param bool $visible Filter by visibility.
     * @return stdclass|bool Instance record
     */
    public function get_instance(int $instanceid, $visible = false) {
        global $DB;

        if ($this->is_table_exists()) {
            $params = ['id' => $instanceid, 'elementid' => $this->elementid];
            $sql = 'SELECT ee.id, co.id as optionid, co.*, ee.* FROM {'.$this->tablename.'} ee
            LEFT JOIN {contentdesigner_options} co ON ee.id = co.instance AND co.element=:elementid
            WHERE ee.id = :id';
            if ($visible) {
                $sql .= ' AND ee.visible = :visible ';
                $params += ['visible' => 1];
            }
            $sql .= ' GROUP BY ee.id, ee.visible, co.id';
            if ($record = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE)) {
                if ($record->description) {
                    $record->description = file_rewrite_pluginfile_urls(
                        $record->description, 'pluginfile.php', $this->context->id,
                        'mod_contentdesigner', $this->element_shortname().'description', $instanceid
                    );
                    $record->description = format_text($record->description, FORMAT_HTML, ['context' => $this->context]);
                }
                return $record;
            }
        }
        return false;
    }

    /**
     * Get the content id of the elemnet instance.
     *
     * @param int $instanceid Element instance id.
     * @return int|bool ID of content.
     */
    public function get_instance_contentid(int $instanceid) {
        global $DB;
        $contentid = $DB->get_field('contentdesigner_content', 'id', ['element' => $this->elementid, 'instance' => $instanceid]);
        return ($contentid) ? $contentid : false;
    }

    /**
     * Get the element instance general options.
     *
     * @param int $instanceid Element instance id.
     * @return stdclass Record of the general options for the elemnent isntnace.
     */
    public function get_instance_options($instanceid): array {
        global $DB;

        return (array) ($DB->get_record('contentdesigner_options', [
            'instance' => $instanceid, 'element' => $this->elementid,
        ], '*', IGNORE_MULTIPLE) ?: []);
    }

    /**
     * Prepare data for the element moodle form.
     *
     * @param int $instanceid Element instance id.
     * @return object
     */
    public function prepare_formdata($instanceid) {
        $instancedata = (array) $this->get_instance($instanceid);
        $instancedata['cmid'] = $this->cmid;
        return (object) ($instancedata);
    }

    /**
     * Process the update of element instance and genreal options.
     * If element doesn't have any chapters then create new default chpater and inserted into the chapter.
     *
     * @param stdclass $data Submitted element moodle form data
     * @return void
     */
    public function update_element($data) {
        global $DB;

        try {
            $transaction = $DB->start_delegated_transaction();

            $instanceid = $this->update_instance($data);
            // Setup instanceid if the elment is not inserted before.
            $data->instance = ($data->instanceid) ?: $instanceid;
            if ($this->supports_content() && $this->supports_multiple_instance()) {
                $editor = editor::get_editor($this->cmid);
                if (!isset($data->chapterid) || $data->chapterid == null) {
                    $data->chapterid = $editor->chapter->get_default($this->cm->instance, true);
                }
                $position = (isset($data->position) && $data->position == 'top') ? 1 : 0;
                // Insert element in content section.
                $content = $editor->add_module_element($this, $data->instance, $data->chapterid, $position);

                $data->contentid = $content->id;
                // Add the content id of the element in chapter sequence.
                $editor->set_elements_inchapter($data->chapterid, $content->id);
            }

            $this->save_areafiles($data);

            // Update the element general options.
            $this->update_options($data);

            $transaction->allow_commit();
        } catch (\Exception $e) {
            // Extra cleanup steps.
            $transaction->rollback($e); // Rethrows exception.
        }
    }

    /**
     * Update the general options of the element instance.
     *
     * @param stdclass $data
     * @return void
     */
    public function update_options($data) {
        global $DB;

        if (empty($data->element)) {
            return false;
        }

        $data->timemodified = time();
        if ($options = $this->get_instance_options($data->instance)) {
            $optiondata = $data;
            $optiondata->id = $options['id'];
            $DB->update_record('contentdesigner_options', $optiondata);
        } else {
            // Insert new record.
            $DB->insert_record('contentdesigner_options', $data);
        }
    }

    /**
     * Update the element instance. Override the function in elements element class to add custom rules.
     *
     * @param stdclass $data
     * @return void
     */
    public function update_instance($data) {
        global $DB;

        $data->timemodified = time();
        if ($data->instanceid == false) {
            $data->timecreated = time();
            return $DB->insert_record($this->tablename, $data);
        } else {
            $data->id = $data->instanceid;
            if ($DB->update_record($this->tablename, $data)) {
                return $data->id;
            }
        }
    }

    /**
     * Delete the element settings.
     *
     * @param int $instanceid
     * @return bool status.
     */
    public function delete_element($instanceid) {
        global $DB;
        try {
            $transaction = $DB->start_delegated_transaction();
            // Delete the element settings.
            if ($this->get_instance($instanceid)) {
                $DB->delete_records($this->tablename(), ['id' => $instanceid]);
            }
            $DB->delete_records('contentdesigner_content', ['element' => $this->element_id(),
            'instance' => $instanceid]);
            if ($this->get_instance_options($instanceid)) {
                // Delete the element general settings.
                $DB->delete_records('contentdesigner_options', ['element' => $this->element_id(),
                    'instance' => $instanceid]);
            }
            $transaction->allow_commit();
            return true;
        } catch (\Exception $e) {
            // Extra cleanup steps.
            $transaction->rollback($e); // Rethrows exception.
            throw new \moodle_exception('chapternotdeleted', 'cdelement_chapter');
        }
    }

    /**
     * Update the visibility of the elements instance.
     *
     * @param int $instanceid Element instance id.
     * @param int $visibility Status of the element visibility
     * @return bool Result of the DB update
     */
    public function update_visibility($instanceid, $visibility) {
        global $DB;
        $instance = $this->get_instance($instanceid);
        if ($instance) {
            $instance->visible = $visibility;
            $instance->timemodified = time();
            return $DB->update_record($this->tablename, $instance);
        }
    }

    /**
     * Generate the classes based on the genenral options.
     *
     * @param stdclass $instance Element instance data
     * @param stdclass $option element general options data.
     * @return stdclass $instance Instance data.
     */
    public function load_option_classes($instance, $option) {
        $class[] = ($instance->animation) ? 'animation' : '';
        $instance->entranceanimation = json_encode([
            'animation' => $instance->animation,
            'duration' => $instance->duration,
            'delay' => $instance->delay ? $instance->delay : '',
        ]);

        $class[] = $instance->hidedesktop ? 'd-lg-none' : 'd-lg-block';
        $class[] = $instance->hidetablet ? 'd-md-none' : 'd-md-block';
        $class[] = $instance->hidemobile ? 'd-none' : 'd-block';

        $style[] = sprintf('margin: %s;', $instance->margin);
        $style[] = sprintf('padding: %s;', $instance->padding);
        $class[] = !empty($option->backimage) ? 'backimage' : '';
        $class[] = (!empty($option->abovecolorbg) || !empty($option->belowcolorbg)) ? 'backcolor' : '';
        $instance->classes = implode(' ', $class);
        $instance->style = implode('', $style);

        $instance->abovecolorbg = !empty($option->abovecolorbg) ? sprintf('background: %s;', $option->abovecolorbg) : '';
        $instance->backimage = !empty($option->backimage) ? sprintf('background-image: url(%s);', $option->backimage) : '';
        $instance->belowcolorbg = !empty($option->belowcolorbg) ? sprintf('background: %s;', $option->belowcolorbg) : '';
        $scrolldata = [
            "start" => $instance->viewport,
            "direction" => $instance->direction,
            "speed" => $instance->speed ? $instance->speed : 0,
        ];
        if ($instance->direction) {
            $instance->scrolleffect = json_encode($scrolldata);
        }

        return $instance;
    }

    /**
     * Load the classes from elements, If need to add classes in parent div then use this method
     * Otherwise use the render function to add classes.
     *
     * @param stdclass $instance
     * @param stdclass $option
     * @return void
     */
    public function generate_element_classes(&$instance, $option) {
        $instance = $this->load_option_classes($instance, $option);
    }

    /**
     * Get the completion for all mandatory elements in the content designer.
     *
     * @return bool
     */
    public function get_mandatory_completion() {
        global $DB;

        $sql  = 'SELECT cc.*, ce.id as elementid, ce.shortname as elementname
        FROM {contentdesigner_content} cc
        JOIN {contentdesigner_elements} ce ON ce.id = cc.element
        WHERE cc.contentdesignerid = ?';

        $params = [$this->cm->instance];
        $contents = $DB->get_records_sql($sql, $params);
        $completemandatory = false;

        // Assume all mandatory elements are complete until proven otherwise.
        $completemandatory = true;
        $hasmandatory = false;

        foreach ($contents as $content) {
            $cm = $this->get_cm_from_modinstance($content->contentdesignerid);
            $element = \mod_contentdesigner\editor::get_element($content->elementname, $cm->id);
            $instance = $element->get_instance($content->instance);

            // Check if this element is mandatory.
            if (isset($instance->mandatory) && !empty($instance->mandatory)) {
                $hasmandatory = true; // At least one mandatory element exists.

                // If any mandatory element is incomplete, set completion to false and break.
                if ($element->prevent_nextelements($instance)) {
                    $completemandatory = false;
                    break;
                }
            }
        }

        // If no mandatory elements were found, set completemandatory to false.
        if (!$hasmandatory) {
            $completemandatory = false;
        }

        return $completemandatory;
    }

    /**
     * Get the options fields for the elements.
     * @return array
     */
    public function get_options_fields() {
        return [
            'element', 'instance', 'margin', 'padding', 'abovecolorbg', 'belowcolorbg',
            'animation', 'duration', 'delay', 'direction', 'speed', 'viewport', 'hidedesktop', 'hidetablet',
            'hidemobile', 'timecreated', 'timemodified', 'description', 'showdescription',
        ];
    }
}

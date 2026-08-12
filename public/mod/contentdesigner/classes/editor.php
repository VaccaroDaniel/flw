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
 * Content designer editor page helps to  manage element.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner;

use html_writer;

/**
 * Mod contnet designer editor class.
 */
class editor {
    /**
     * Coursemodule instance
     *
     * @var cminfo
     */
    public $cm;

    /**
     * Course record object
     *
     * @var stdclass
     */
    public $course;

    /**
     * Course module context object.
     *
     * @var context_module
     */
    public $cmcontext;

    /**
     * Chapter element instance.
     *
     * @var cdelement_chapter\element
     */
    public $chapter;

    /**
     * Constructor, setup the class variables and course module objects.
     *
     * @param stdclass $cm Course Moudle instance record.
     * @param stdclass $course Course record object.
     */
    public function __construct($cm, $course) {
        $this->cm = $cm;
        $this->course = $course;
        $this->cmcontext = \context_module::instance($cm->id);
        $this->chapter = new \cdelement_chapter\element($this->cm->id);
    }

    /**
     * Display the available elements list to manage in the editor view.
     *
     * @return string HTML of the available elments elementbox.
     */
    public function display() {
        global $OUTPUT;

        $data = [
            'cm' => $this->cm,
            'course' => $this->course,
            'chapters' => $this->chapter->get_chapters_data(),
            'outro' => $this->get_module_outro(),
        ];

        // Hide the add element option after outro.
        $data['chapterscount'] = count($data['chapters']);
        return $OUTPUT->render_from_template('mod_contentdesigner/editor', $data);
    }

    /**
     * Render the module elements for student view.
     * @param int $chapterafter Load the chapters after the given chapter.
     * @return string HTML of the elements.
     */
    public function render_elements($chapterafter = false) {
        global $OUTPUT;

        $data = [
            'cm' => $this->cm,
            'course' => $this->course,
            'progressbar' => $this->chapter->build_progress(),
            'chapters' => $this->chapter->get_chapters_data(true, true, $chapterafter),
            'outro' => $this->render_module_outro(),
            'cmdetails' => $this->cm_details(),
        ];

        $end = (!empty($data['chapters'])) ? end($data['chapters']) : '';
        if (!empty($end)) {
            $data['prevent'] = $end['prevent'] ? true : false;
            $data['chapterprevent'] = $end['chapterprevent'] ? true : false;
        }

        return $OUTPUT->render_from_template('mod_contentdesigner/content', $data);
    }

    /**
     * Send the course module details as hidden input, data will fetched in the Element.js file to prevent the global value issue.
     *
     * @return string
     */
    public function cm_details() {
        $data = [
            'cmid' => $this->cm->id,
            'contextid' => \context_module::instance($this->cm->id)->id,
            'contentdesignerid' => $this->cm->instance,
        ];
        return html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => 'contentdesigner_cm_details', 'value' => json_encode($data),
        ]);
    }

    /**
     * Initialize the javascript modules from the available elements.
     *
     * Note: if you want to add js for each instance then insert your module call on render function insteed of here.
     *
     * @return void
     */
    public function initiate_js() {
        $plugins = \core_plugin_manager::instance()->get_installed_plugins('cdelement');
        foreach ($plugins as $plugin => $version) {
            $elementobj = self::get_element($plugin, $this->cm->id);
            $elementobj->initiate_js();
        }
    }

    /**
     * Create the instance of the editor class.
     *
     * @param int $cmid Course Module id.
     * @return editor Mod_contentdeisnger/editor class instance.
     */
    public static function get_editor($cmid) {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        return new self($cm, $course);
    }

    /**
     * Get list of available elements for the modal to insert.
     *
     * @param int $cmid course module id.
     * @return string HTML of the elements list.
     */
    public static function get_elements_list(int $cmid) {

        $plugins = \core_plugin_manager::instance()->get_installed_plugins('cdelement');

        $li = [];
        foreach ($plugins as $plugin => $version) {
            $elementobj = self::get_element($plugin, $cmid);
            if (!$elementobj->supports_multiple_instance()) {
                continue;
            }
            $info = $elementobj->info();
            $description = html_writer::span($info->description, 'element-description');
            $name = html_writer::span($info->name, 'element-name');

            $li[] = html_writer::tag('li', $info->icon . $name . $description,
                ['data-element' => $info->shortname, 'class' => 'element-item']
            );
        }

        return html_writer::tag('ul', implode('', $li), ['class' => 'elements-list']);
    }

    /**
     * Returns the given elements class instance object.
     *
     * @param int|string $element
     * @param int|null $cmid
     * @return \elements
     */
    public static function get_element($element, $cmid = null) {
        global $DB;
        if (is_number($element)) {
            $element = $DB->get_field('contentdesigner_elements', 'shortname', ['id' => $element]);
        }
        $class = 'cdelement_' . $element . '\element';
        if (class_exists($class)) {
            return new $class($cmid);
        } else {
            throw new \moodle_exception('elementnotfound', 'mod_contentdesigner');
        }
    }

    /**
     * Fetch list of installed elements.
     *
     * @return array List of elements.
     */
    public static function get_elements() {
        $plugins = \core_plugin_manager::instance()->get_installed_plugins('cdelement');
        return $plugins;
    }

    /**
     * Fetch the file areas from the elements. Fetch the fileareas and concat the element component name with filearea.
     * Use this function and define the fileareas Which is uses the mod_contentdesigner as the component for storing the files.
     *
     * @param int $cmid course module id
     * @return array List of filearea.
     */
    public static function get_elements_areafiles($cmid) {
        $plugins = self::get_elements();
        $files = [];
        foreach ($plugins as $plugin => $version) {
            $elementobj = self::get_element($plugin, $cmid);
            $areafiles = (method_exists($elementobj, 'areafiles')) ? $elementobj->areafiles() : [];
            array_walk($areafiles, function (&$areafile) use ($plugin) {
                $areafile = "cdelement_" . $plugin . "_" . $areafile;
            });
            $files = array_merge($files, $areafiles);
        }

        return $files;
    }

    /**
     * Get the default outro element for the module. if not available then creates the new one.
     * Outro only created automatically, can't have option to create manaully.
     *
     * @return string Rendered element box view of the outro.
     */
    public function get_module_outro() {
        global $OUTPUT, $DB;

        $element = self::get_element('outro', $this->cm->id);
        $instance = $DB->get_field('cdelement_outro', 'id', ['contentdesignerid' => $this->cm->instance]);

        if (!$instance) {
            $instance = $element->create_basic_instance($this->cm->instance);
        }
        $instancedata = $element->get_instance($instance);

        $editurl = new \moodle_url('/mod/contentdesigner/element.php', [
            'cmid' => $this->cm->id,
            'element' => $element->shortname,
            'id' => $instancedata->id,
            'sesskey' => sesskey(),
        ]);
        return $OUTPUT->render_from_template('mod_contentdesigner/elementbox', [
            'info' => $element->info(),
            'instancedata' => $instancedata,
            'editurl' => $editurl,
            'hidemove' => true,
            'hidevisible' => true,
            'hidedelete' => true,
            'hideduplicate' => true,
        ]);
    }

    /**
     * Render the module outro element.
     *
     * @return array Rendered element box view of the outro.
     */
    public function render_module_outro() {
        global $DB;
        $element = self::get_element('outro', $this->cm->id);
        $editor = self::get_editor($this->cm->id);
        $instance = $DB->get_record('cdelement_outro', ['contentdesignerid' => $this->cm->instance]);
        if ($instance) {
            $instance = $element->get_instance($instance->id, $instance->visible);
            $option = $editor->get_option($instance->id, $element->elementid);
            $element->load_option_classes($instance, $option);
            $instancedata = $element->prepare_formdata($instance->id);
            $data = [
                'contents' => $element->render($instancedata),
                'instancedata' => $instance,
                'element' => $element->elementid,
                'info' => $element->info(),
            ];
            return $data;
        }
        return [];
    }

    /**
     * Fetch the genernal options as records.
     *
     * @param int $instanceid Element instance id
     * @param int $elementid Element list id.
     * @return mixed
     */
    public function get_option($instanceid, $elementid) {
        global $DB;
        $record = $DB->get_record('contentdesigner_options', [
        'instance' => $instanceid, 'element' => $elementid], '*', IGNORE_MULTIPLE);
        if (!empty($record)) {
            $element = self::get_element($record->element, $this->cm->id);
            $record->backimage = $this->get_element_areafiles($element->shortname . "elementbg", $instanceid);
        }
        return $record;
    }

    /**
     * Fetch the files from for the filearea.
     *
     * @param string $filearea Name of the filearea.
     * @param int $itemid Id for the filearea.
     * @param string $component Plugin component name.
     * @param context_module $context Course module instance object.
     * @return string File Path of the given fileareas, If not false.
     */
    public function get_element_areafiles($filearea, $itemid = 0, $component = 'mod_contentdesigner', $context = null) {
        $context = ($context === null) ? \context_module::instance($this->cm->id) : $context;
        $files = get_file_storage()->get_area_files($context->id, $component, $filearea, $itemid,
        'itemid, filepath, filename', false);
        if (empty($files) ) {
            return '';
        }
        $file = current($files);
        $fileurl = \moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        false);
        return $fileurl->out(false);
    }

    /**
     * Create the element instance and add to the module chapter.
     * It creates the elements basic instance, works when the elment insert works using ajax.
     *
     * @param int $elementid
     * @param int|null $chapterid
     * @return string HTML of the element to insert to editor.
     */
    public function insert_element($elementid, $chapterid = null) {
        global $OUTPUT, $DB;

        $data = (object) ['cm' => $this->cm->id, 'course' => $this->course->id];
        $element = self::get_element($elementid, $this->cm->id);
        $data->info = $element->info();

        try {
            $transaction = $DB->start_delegated_transaction();
            // Create basic instance for element EX:elemnet_h5p.
            $data->instance = $element->create_basic_instance($this->cm->instance);
            $data->instancedata = $element->get_instance($data->instance);
            if ($element->supports_content()) {
                if ($chapterid == null) {
                    $chapterid = $this->chapter->get_default($this->cm->instance, true);
                }
                // Insert element in content section.
                $content = $this->add_module_element($element, $data->instance, $chapterid);
                $data->id = $content->id;
                // Add the content id of the element in chapter sequence.
                $this->set_elements_inchapter($chapterid, $content->id);

                $settings = [
                    'element' => $elementid,
                    'instance' => $data->instance,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
                // Insert global options for element instance.
                if (!$DB->insert_record('contentdesigner_options', $settings)) {
                    throw new \moodle_exception('settingnotcreated', 'mod_contentdesigner');
                }
            }

            $transaction->allow_commit();
            $data->instancedata->title = ($element->supports_content())
                ? $element->title_editable($data->instancedata) : $element->element_name();

            // Todo: want to control multiple elements then implement new method in the abstract elements.
            if ($element->info()->shortname == 'chapter') {
                $elementsbox = $OUTPUT->render_from_template('mod_contentdesigner/chapter', $data);
                return html_writer::tag('li', $elementsbox, ['class' => 'chapters_list']);
            } else {
                $elementsbox = $OUTPUT->render_from_template('mod_contentdesigner/elementbox', $data);
                return html_writer::tag('li', $elementsbox, ['class' => 'elements_list']);
            }
        } catch (\Exception $e) {
            // Extra cleanup steps.
            $transaction->rollback($e); // Rethrows exception.
        }
    }

    /**
     * Add the element instance to the module contents table. which contains the list of instances.
     *
     * @param stdclass $element Element class instance.
     * @param int $instanceid Element instance ID.
     * @param int $chapter Chapter id.
     * @param bool $position Insert the element in top( means 1) of the chapter or bootom
     * @return object content data to insert.
     */
    public function add_module_element($element, $instanceid, $chapter, $position = 0) {
        global $DB;

        $content = (object) [
            'contentdesignerid' => $this->cm->instance,
            'element'           => $element->elementid,
            'instance'          => $instanceid,
            'chapter'           => $chapter,
            'timecreated'       => time(),
            'timemodified'       => time(),
        ];

        if ($contentid = $element->get_instance_contentid($instanceid)) {
            $content->id = $contentid;

            $content->id = $DB->update_record('contentdesigner_content', $content);
        } else {
            $lastelement = 0;
            if ($position) {
                $DB->execute('UPDATE {contentdesigner_content} SET position=position+1
                    WHERE contentdesignerid = ? AND chapter=?', [$this->cm->instance, $chapter]);
            } else {
                // Get the latest positions of the chapter element in element going to insert in bottom.
                $lastelement = (int) $DB->get_field_sql('SELECT max(position) from {contentdesigner_content}
                    WHERE contentdesignerid = ? AND chapter=?', [$this->cm->instance, $chapter]
                );
            }
            $content->position = $lastelement ? $lastelement + 1 : 1;
            $content->id = $DB->insert_record('contentdesigner_content', $content);
        }
        return $content;
    }

    /**
     * Set the element instances to the chapter.
     *
     * @param int $chapterid Chapter id need to insert.
     * @param int $contentid contentdesigner_content id of the element instance.
     * @return bool
     */
    public function set_elements_inchapter($chapterid, $contentid) {
        $chapter = new \cdelement_chapter\element($this->cm->id);
        return $chapter->set_elements($chapterid, $contentid);
    }

    /**
     * Duplicate an element instance within the editor.
     *
     * @param int $id The ID of the element instance to duplicate.
     * @param string $element Element shortname.
     * @param int $newchapterid The ID of the chapter to duplicate the element instance into.
     * @return void
     */
    public function duplicate($id, $element, $newchapterid = 0) {
        global $DB;
        $tablename = 'cdelement_' . $element;
        $context = \context_module::instance($this->cm->id);
        $elementobj = self::get_element($element, $this->cm->id);

        if ($record = $DB->get_record($tablename, ['id' => $id])) {
            $record = $elementobj->get_instance($record->id, $record->visible);

            $content = $DB->get_record('contentdesigner_content', ['element' => $elementobj->elementid, 'instance' => $id]);
            $chapter = isset($content->chapter) ? $content->chapter : 0;

            $record->instanceid = 0;
            $record->chapterid = !empty($newchapterid) ? $newchapterid : $chapter;
            $record->cmid = $this->cm->id;
            $record->contextid = $context->id;
            $record->course = $this->course->id;
            $record->element = $elementobj->elementid; // ID of the element in elements table.
            $record->contentdesignerid = $this->cm->instance;
            $record->elementshortname = $elementobj->shortname;
            $record->timecreated = time();

            if ($element == "richtext") {
                $record->content_editor['format'] = $record->contentformat;
                $record->content_editor['text'] = $record->content;
            }

            $elementobj->update_element($record);
        }
    }

    /**
     * Duplicate a chapter, including all of its associated elements.
     *
     * @param int $id The ID of the chapter to duplicate.
     * @return void
     */
    public function chapter_duplicate($id) {
        global $DB;

        // Retrieve the original chapter record.
        $chapter = $DB->get_record('cdelement_chapter', ['id' => $id], '*', MUST_EXIST);
        if ($chapter) {
            // Get the element object for the chapter element.
            $elementobj = self::get_element('chapter', $this->cm->id);

            // Retrieve the chapter instance data.
            $record = $elementobj->get_instance($chapter->id, $chapter->visible);

            // Set the new chapter data.
            $record->instanceid = 0;
            $record->contents = null;
            $record->chapterid = $chapter->id;
            $record->contentdesignerid = $this->cm->instance;
            $record->timecreated = time();

            // Duplicate the chapter.
            $newchapterid = $elementobj->update_instance($record);
            $record->instance = $newchapterid;

            $elementobj->save_areafiles($record);

            // Update the element general options.
            $elementobj->update_options($record);

            // Retrieve all elements associated with the original chapter.
            $sql = 'SELECT cc.*, ce.id as elementid, ce.shortname as elementname
                    FROM {contentdesigner_content} cc
                    JOIN {contentdesigner_elements} ce ON ce.id = cc.element
                    WHERE cc.chapter = ? ORDER BY position ASC';
            $params = [$chapter->id];
            $contents = $DB->get_records_sql($sql, $params);

            // Duplicate each element and associate it with the new chapter.
            foreach ($contents as $content) {
                // Duplicate the element.
                $this->duplicate($content->instance, $content->elementname, $newchapterid);
            }
        }
    }
}

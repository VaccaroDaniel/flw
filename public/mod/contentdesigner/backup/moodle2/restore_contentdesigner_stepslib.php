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
 * Definition restore structure steps.
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_contentdesigner\editor;

/**
 * Define all the restore steps that will be used by the restore_contentdesigner_activity_task
 */

/**
 * Structure step to restore contentdesigner activity.
 */
class restore_contentdesigner_activity_structure_step extends restore_activity_structure_step {
    /**
     * Restore steps structure definition.
     */
    protected function define_structure() {
        $paths = [];

        $userinfo = $this->get_setting_value('userinfo');

        // Restore path.
        $element = new restore_path_element('contentdesigner', '/activity/contentdesigner');
        $paths[] = $element;

        // Restore elements.
        $elements = new restore_path_element('contentdesigner_elements',
        '/activity/contentdesigner/elements/contentdesigner_elements');
        $paths[] = $elements;

        $this->add_subplugin_structure('cdelement', $element);

        $paths[] = new restore_path_element('contentdesigner_content',
        '/activity/contentdesigner/content/contentdesigner_content');

        // Restor general options of element instance.
        $paths[] = new restore_path_element('contentdesigner_options',
        '/activity/contentdesigner/contentdesigneropitons/contentdesigner_options');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process activity contentdesigner restore.
     * @param mixed $data restore contentdesigner table data.
     */
    protected function process_contentdesigner($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        // Insert instance into Database.
        $newitemid = $DB->insert_record('contentdesigner', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process contentdesigner users records.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_contentdesigner_elements($data) {
        global $DB;

        $data = (object) $data;

        // If already element inserted during the plugin installation then use the current element id.
        $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $data->shortname]);
        if (!$elementid) {
            // If not inserted then create new one.
            $elementid = $DB->insert_record('contentdesigner_elements', $data);
        }
        $this->set_mapping('elements', $data->id, $elementid);
    }

    /**
     * Process contentdesigner users records.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_contentdesigner_content($data) {
        global $DB;

        $data = (object) $data;

        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');

        $elementid = $this->get_mappingid('elements', $data->element);
        $data->element = $elementid;
        $elementname = $DB->get_field('contentdesigner_elements', 'shortname', ['id' => $data->element]);

        // Update the new content and chapter instance.
        $data->instance = $this->get_mappingid($elementname . "_instanceid", $data->instance);
        $data->chapter = $this->get_mappingid("chapterid", $data->chapter);

        $data->timemodified = time();
        // Insert the content with new chapter and instance.
        $contentid = $DB->insert_record('contentdesigner_content', $data);
        $contents = $DB->get_field('cdelement_chapter', 'contents', ['id' => $data->chapter]);
        $contents = explode(',', $contents);
        array_push($contents, $contentid);

        // Add the latest cotnent id in chapter.
        $content = (object) ['id' => $data->chapter, 'contents' => implode(',', $contents)];
        $DB->update_record('cdelement_chapter', $content);
    }

    /**
     * Process contentdesigner general options records.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_contentdesigner_options($data) {
        global $DB;

        $data = (object) $data;

        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');

        // Update the new content and chapter instance.
        $elementid = $this->get_mappingid('elements', $data->element);
        $data->element = $elementid;

        $elementname = $DB->get_field('contentdesigner_elements', 'shortname', ['id' => $data->element]);
        $data->instance = $this->get_mappingid($elementname . "_instanceid", $data->instance);
        $data->timemodified = time();
        // Insert the general options for the element instance.
        $DB->insert_record('contentdesigner_options', $data);
    }

    /**
     * Update the files of editors after restore execution.
     *
     * @return void
     */
    protected function after_execute() {
        // Add contentdesigner related files.
        $this->add_related_files('mod_contentdesigner', 'intro', null);
    }
}

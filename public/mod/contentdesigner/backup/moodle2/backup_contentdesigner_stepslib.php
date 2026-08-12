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
 * Definition backup-steps
 *
 * @package   mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_contentdesigner\editor;

/**
 * Define the complete contentdesigner structure for backup, with file and id annotations.
 */
class backup_contentdesigner_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define backup steps structure.
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated - table fields.
        $contentdesigner = new backup_nested_element('contentdesigner', ['id'], [
            'course', 'name', 'intro', 'introformat', 'timecreated',
            'timemodified']);

        $this->add_subplugin_structure('cdelement', $contentdesigner, true);

        $elements = new backup_nested_element('elements');
        $contentdesignerelements = new backup_nested_element('contentdesigner_elements', ['id'], [
            'shortname', 'visible', 'timecreated']);

        $content = new backup_nested_element('content');
        $contentdesignercontent = new backup_nested_element('contentdesigner_content', ['id'], [
            'contentdesignerid', 'element', 'instance', 'chapter', 'position', 'timecreated', 'timemodified',
        ]);

        $options = new backup_nested_element('contentdesigneropitons');
        $contentdesigneroptions = new backup_nested_element('contentdesigner_options', ['id'], [
            'element', 'instance', 'margin', 'padding', 'abovecolorbg', 'abovegradientbg', 'bgimage', 'belowcolorbg',
            'belowgradientbg', 'animation', 'duration', 'delay', 'direction', 'speed', 'viewport', 'hidedesktop', 'hidetablet',
            'hidemobile', 'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $contentdesigner->add_child($elements);
        $elements->add_child($contentdesignerelements);

        $contentdesigner->add_child($content);
        $content->add_child($contentdesignercontent);

        $contentdesigner->add_child($options);
        $options->add_child($contentdesigneroptions);

        // Define sources.
        // Define source to backup.
        $contentdesigner->set_source_table('contentdesigner', ['id' => backup::VAR_ACTIVITYID]);
        $contentdesignerelements->set_source_sql('SELECT * FROM {contentdesigner_elements}', []); // Get all records.
        $contentdesignercontent->set_source_table('contentdesigner_content', ['contentdesignerid' => backup::VAR_PARENTID]);

        $sql = 'SELECT co.* FROM {contentdesigner_content} cc
        JOIN {contentdesigner_options} co ON co.element=cc.element AND co.instance=cc.instance
        WHERE cc.contentdesignerid=:contentdesignerid';

        $contentdesigneroptions->set_source_sql($sql, ['contentdesignerid' => backup::VAR_PARENTID]);

        // Define file annotations.
        $contentdesigner->annotate_files('mod_contentdesigner', 'intro', null);

        $plugins = editor::get_elements();
        foreach ($plugins as $plugin => $version) {
            $filearea = $plugin . 'elementbg';
            $contentdesigneroptions->annotate_files('mod_contentdesigner', $filearea, null);
        }

        // Return the root element (data), wrapped into standard activity structure.
        return $this->prepare_activity_structure($contentdesigner);
    }
}

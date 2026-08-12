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
 * This file contains the backup code for the cdelement_chapter plugin.
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup chapter elements.
 */
class backup_cdelement_chapter_subplugin extends backup_subplugin {
    /**
     * Returns the subplugin information to attach to chapter element
     * @return backup_subplugin_element
     */
    protected function define_contentdesigner_subplugin_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginelement = new backup_nested_element('cdelement_chapter', ['id'], [
            'contentdesignerid', 'title', 'visible', 'learningtools', 'contents', 'position', 'titlestatus',
            'timecreated', 'timemodified' ]);

        $chaptercompletion = new backup_nested_element('elementchapter_completion');
        $chaptercompletionelement = new backup_nested_element('cdelement_chapter_completion', ['id'], [
            'instance', 'userid', 'completion', 'titlestatus', 'timecreated', 'timemodified',
        ]);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginelement);

        $subplugin->add_child($chaptercompletion);
        $chaptercompletion->add_child($chaptercompletionelement);

        // Set source to populate the data.
        $subpluginelement->set_source_table('cdelement_chapter', ['contentdesignerid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $sql = 'SELECT * FROM {cdelement_chapter_completion} WHERE instance IN (
                SELECT id FROM {cdelement_chapter} WHERE contentdesignerid=:contentdesignerid
            )';
            $chaptercompletionelement->set_source_sql($sql, ['contentdesignerid' => backup::VAR_PARENTID]);
            $chaptercompletionelement->annotate_ids('user', 'userid');
        }

        return $subplugin;
    }
}

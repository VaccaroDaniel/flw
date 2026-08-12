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
 * This file contains the backup code for the cdelement_h5p plugin.
 *
 * @package    cdelement_h5p
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup feedback files.
 *
 * This just adds its filearea to the annotations and records the number of files.
 */
class backup_cdelement_h5p_subplugin extends backup_subplugin {
    /**
     * Returns the subplugin information to attach to h5p element
     * @return backup_subplugin_element
     */
    protected function define_contentdesigner_subplugin_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginelement = new backup_nested_element('cdelement_h5p', ['id'], [
            'contentdesignerid', 'title', 'visible', 'package', 'mandatory', 'timecreated', 'timemodified',
        ]);

        $h5pcompletion = new backup_nested_element('elementh5p_completion');
        $h5pcompletionelement = new backup_nested_element('cdelement_h5p_completion', ['id'], [
            'instance', 'userid', 'completion', 'success', 'score', 'scoredata', 'response', 'timecreated', 'timemodified',
        ]);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginelement);

        $subplugin->add_child($h5pcompletion);
        $h5pcompletion->add_child($h5pcompletionelement);

        // Set source to populate the data.
        $subpluginelement->set_source_table('cdelement_h5p', ['contentdesignerid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $sql = 'SELECT * FROM {cdelement_h5p_completion} WHERE instance IN (
                SELECT id FROM {cdelement_h5p} WHERE contentdesignerid=:contentdesignerid
            )';
            $h5pcompletionelement->set_source_sql($sql, ['contentdesignerid' => backup::VAR_PARENTID]);
            $h5pcompletionelement->annotate_ids('user', 'userid');
        }

        $subpluginelement->annotate_files('cdelement_h5p', 'package', null);
        $subpluginelement->annotate_files('mod_contentdesigner', 'h5pelementbg', null);

        return $subplugin;
    }
}

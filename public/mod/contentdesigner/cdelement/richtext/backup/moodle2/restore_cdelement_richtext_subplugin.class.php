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
 * This file contains the restore code for the cdelement_richtext plugin.
 *
 * @package   cdelement_richtext
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore subplugin class.
 *
 * Provides the necessary information needed to restore cdelement_richtext.
 */
class restore_cdelement_richtext_subplugin extends restore_subplugin {
    /**
     * Returns the paths to be handled by the subplugin.
     * @return array
     */
    protected function define_contentdesigner_subplugin_structure() {

        $paths = [];

        $elename = $this->get_namefor('instance');
        // We used get_recommended_name() so this works.
        $elepath = $this->get_pathfor('/cdelement_richtext');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Processes one cdelement_richtext element
     * @param array $data
     */
    public function process_cdelement_richtext_instance($data) {
        global $DB;

        $data = (object)$data;

        $oldinstance = $data->id;
        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');

        $newinstance = $DB->insert_record('cdelement_richtext', $data);
        $this->set_mapping('richtext_instanceid', $oldinstance, $newinstance, true);
        $this->add_related_files('mod_contentdesigner', 'richtextelementbg', 'richtext_instanceid', null, $oldinstance);
    }

    /**
     * Restore the editor images after the instance executed.
     *
     * @return void
     */
    public function after_execute_contentdesigner() {
        $this->add_related_files('mod_contentdesigner', 'cdelement_richtext_content', 'richtext_instanceid');
    }
}

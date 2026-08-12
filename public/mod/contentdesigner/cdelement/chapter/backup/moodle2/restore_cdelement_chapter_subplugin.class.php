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
 * This file contains the restore code for the cdelement_chapter plugin.
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore subplugin class.
 *
 * Provides the necessary information needed to restore chapter element subplugin.
 */
class restore_cdelement_chapter_subplugin extends restore_subplugin {
    /**
     * Returns the paths to be handled by the subplugin.
     * @return array
     */
    protected function define_contentdesigner_subplugin_structure() {

        $paths = [];

        $elename = $this->get_namefor('instance');
        // We used get_recommended_name() so this works.
        $elepath = $this->get_pathfor('/cdelement_chapter');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Processes one chapter element instance
     * @param mixed $data
     */
    public function process_cdelement_chapter_instance($data) {
        global $DB;

        $data = (object)$data;

        $oldchapterid = $data->id;
        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');
        // Make the chapter empty, content will be added during the contentdesigner_content restore.
        $data->contents = '';
        $data->timemodified = time();
        $newchapterid = $DB->insert_record('cdelement_chapter', $data);
        $this->set_mapping('chapterid', $oldchapterid, $newchapterid);
    }
}

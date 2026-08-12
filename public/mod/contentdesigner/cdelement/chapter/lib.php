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
 * Chapter element libarary methods defined.
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/contentdesigner/classes/editor.php');

 /**
  * Update the completion of chapter and generate the progress bar for the current module contents.
  *
  * @param array $args list of parameters such as context and chapter details.
  * @return string Html of progress bar.
  */
function cdelement_chapter_output_fragment_update_progressbar($args) {
    if (isset($args['cmid'])) {
        $cmid = $args['cmid'];
        $element = mod_contentdesigner\editor::get_element('chapter', $cmid);
        return $element->build_progress();
    }
}

/**
 * Check if the learning tools plugin is installed.
 *
 * @return bool True if the learning tools plugin is installed, false otherwise.
 */
function cdelement_chapter_has_learningtools() {
    global $DB;
    $exists = array_key_exists('learningtools', \core_component::get_plugin_list('local'));
    if ($exists) {
        $dbman = $DB->get_manager();
        $fieldsexists = $dbman->table_exists('ltool_bookmarks_data') && $dbman->field_exists('ltool_bookmarks_data', 'itemid');
        return $fieldsexists;
    }
    return false;
}

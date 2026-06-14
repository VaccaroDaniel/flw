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
 * @package    block_sitenews
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v4 or later
 * @copyright  2019 innoCampus, TU Berlin
 */

defined("MOODLE_INTERNAL") || die();

function xmldb_block_sitenews_uninstall() {
    global $DB;

    // The block instances should be deleted by Moodle automatically. However, we need to delete the user preferences.
    $DB->delete_records('user_preferences', ['name' => 'sitenews_itemsnumber']);

    return true;
}

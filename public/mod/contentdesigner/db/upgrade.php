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
 * Content designer upgrade code.
 *
 * @package    mod_contentdesigner
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles the upgrade process for the mod_contentdesigner plugin in Moodle.
 *
 * @param int $oldversion The version of the plugin before the upgrade.
 * @return bool True on successful upgrade.
 */
function xmldb_contentdesigner_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024110807) {
        $optionstable = new xmldb_table('contentdesigner_options');
        $delay = new xmldb_field('delay', XMLDB_TYPE_CHAR, '10', null, null, null, '0', 'duration');
        if ($dbman->field_exists($optionstable, $delay)) {
            $dbman->change_field_type($optionstable, $delay);
        }

        upgrade_mod_savepoint(true, 2024110807, 'contentdesigner');
    }

    if ($oldversion < 2025051001) {
        // Add description field.
        $table = new xmldb_table('contentdesigner_options');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'instance');
        if (!$dbman->field_exists($table, 'description')) {
            $dbman->add_field($table, $field);
        }

        // Add descriptionformat field.
        $field = new xmldb_field('descriptionformat', XMLDB_TYPE_INTEGER, '4', null, null, null, '1', 'description');
        if (!$dbman->field_exists($table, 'descriptionformat')) {
            $dbman->add_field($table, $field);
        }

        // Add showdescription field.
        $field = new xmldb_field('showdescription', XMLDB_TYPE_INTEGER, '4', null, null, null, '1', 'descriptionformat');
        if (!$dbman->field_exists($table, 'showdescription')) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025051001, 'contentdesigner');
    }

    return true;

}

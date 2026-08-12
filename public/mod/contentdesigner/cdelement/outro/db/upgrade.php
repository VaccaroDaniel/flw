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
 * Element outro upgrade code defined.
 *
 * @package   cdelement_outro
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles the upgrade process for the cdelement_outro plugin in Moodle.
 *
 * @param int $oldversion The version of the plugin before the upgrade.
 * @return bool True on successful upgrade.
 */
function xmldb_cdelement_outro_upgrade($oldversion) {
    global $DB;

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    $dbman = $DB->get_manager();

    if ($oldversion < 1) {

        // Rename the table element_outro to cdelement_outro.
        $table = new xmldb_table('element_outro');
        $cdelementtable = new xmldb_table('cdelement_outro');
        if ($dbman->table_exists($table)) {
            if (!$dbman->table_exists($cdelementtable)) {
                // Rename the existing table.
                $dbman->rename_table($table, 'cdelement_outro');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdelementtable);
                // Rename the existing table.
                $dbman->rename_table($table, 'cdelement_outro');
            }
        }

        // Rename admin configuration settings.
        mod_contentdesigner\plugininfo\cdelement::update_plugins_config('element_outro', 'cdelement_outro', [
            'outroimage', 'outrocontent']);
    }

    if ($oldversion < 2024110801 && $oldversion) {
        // Element outro table.
        $table = new xmldb_table('cdelement_outro');
        // Outrocontent.
        $outrocontent = new xmldb_field('outrocontent', XMLDB_TYPE_TEXT, null, null, null, null, null, 'secondaryurl');
        // Outrocontent format.
        $outrocontentformat = new xmldb_field('outrocontentformat', XMLDB_TYPE_INTEGER, '2', null,
        null, null, null, 'outrocontent');
        // Primary button.
        $primarybutton = new xmldb_field('primarybutton', XMLDB_TYPE_INTEGER, '9', null, null, null, '0', 'outrocontentformat');
        // Secondary button.
        $secondarybutton = new xmldb_field('secondarybutton', XMLDB_TYPE_INTEGER, '9', null, null, null, '0', 'primarybutton');

        if (!$dbman->field_exists($table, $outrocontent)) {
            $dbman->add_field($table, $outrocontent);
        }

        if (!$dbman->field_exists($table, $outrocontentformat)) {
            $dbman->add_field($table, $outrocontentformat);
        }

        if (!$dbman->field_exists($table, $primarybutton)) {
            $dbman->add_field($table, $primarybutton);
        }

        if (!$dbman->field_exists($table, $secondarybutton)) {
            $dbman->add_field($table, $secondarybutton);
        }

        upgrade_plugin_savepoint(true, 2024110801, 'cdelement', 'outro');
    }

    return true;
}

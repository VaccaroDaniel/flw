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
 * Element chapter upgrade code defined.
 *
 * @package   cdelement_chapter
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles the upgrade process for the cdelement_chapter plugin in Moodle.
 *
 * @param int $oldversion The version of the plugin before the upgrade.
 * @return bool True on successful upgrade.
 */
function xmldb_cdelement_chapter_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 1) {
        // Rename the table element_chapter to cdelement_chapter.
        $table = new xmldb_table('element_chapter');
        $cdchaptertable = new xmldb_table('cdelement_chapter');

        if ($dbman->table_exists($table)) {
            if (!$dbman->table_exists($cdchaptertable)) {
                // Rename the existing table.
                $dbman->rename_table($table, 'cdelement_chapter');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdchaptertable);
                // Rename the existing table.
                $dbman->rename_table($table, 'cdelement_chapter');
            }
        }

        // Rename the table element_chapter_completion to cdelement_chapter_completion.
        $completiontable = new xmldb_table('element_chapter_completion');
        $cdcompletiontable = new xmldb_table('cdelement_chapter_completion');

        if ($dbman->table_exists($completiontable)) {
            if (!$dbman->table_exists($cdcompletiontable)) {
                // Rename the existing table.
                $dbman->rename_table($completiontable, 'cdelement_chapter_completion');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdcompletiontable);
                // Rename the existing table.
                $dbman->rename_table($completiontable, 'cdelement_chapter_completion');
            }
        }

        // Rename admin configuration settings.
        mod_contentdesigner\plugininfo\cdelement::update_plugins_config('element_chapter', 'cdelement_chapter');
    }

    if ($oldversion < 2024110801 && $oldversion) {
        // Element chapter table.
        $table = new xmldb_table('cdelement_chapter');
        // Title status.
        $titlestatus = new xmldb_field('titlestatus', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'position');
        if (!$dbman->field_exists($table, $titlestatus)) {
            $dbman->add_field($table, $titlestatus);
        }
        upgrade_plugin_savepoint(true, 2024110801, 'cdelement', 'chapter');
    }

    if ($oldversion < 2025041500 && $oldversion) {
        // Element chapter table.
        $table = new xmldb_table('cdelement_chapter');
        // Define field learningtools to be added to cdelement_chapter.
        $field = new xmldb_field('learningtools', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'visible');
        // Add field if it doesn't already exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Set the default value for existing records.
        $DB->set_field('cdelement_chapter', 'learningtools', 0);

        // Save new version.
        upgrade_plugin_savepoint(true, 2025041500, 'cdelement', 'chapter');
    }

    return true;
}

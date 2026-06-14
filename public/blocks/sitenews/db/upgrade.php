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
 * @author     Jan Eberhardt <eberhardt@math.tu-berlin.de>
 */

defined("MOODLE_INTERNAL") || die();

function xmldb_block_sitenews_upgrade($oldversion = 0)
{
    global $DB, $OUTPUT;

    if ($oldversion < 2014072201) {
        if ($blockinstances = $DB->get_records("block_instances", array("blockname" => "sitenews"))) {
            // Remove all instances of the block.
            foreach ($blockinstances as $instance) {
                blocks_delete_instance($instance);
            }
            echo $OUTPUT->notification(get_string("obsoleteinstancesdeleted", "block_sitenews"), "notifysuccess");
        }

        // There are no block instances of sitenews (anymore), so add the major one.
        // Use xmldb_block_sitenews_install(), because it would be exact the same code.
        require_once __DIR__ . "/install.php";
        xmldb_block_sitenews_install();

        upgrade_plugin_savepoint(true, 2014072201, "block", "sitenews");
    }

    return true;
}
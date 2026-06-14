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

function xmldb_block_sitenews_install() {
    // The next lines are mostly copied from block_manager::add_block() in order to add the block to my-index.
    // Since the instructions may be confusing, I decided to add the instance with the "sticky" options after the installation.
    // Default weight has to be set, after installation (now -1).

    global $DB;

    $blockinstance = new stdClass;
    $blockinstance->blockname = "sitenews";
    $blockinstance->parentcontextid = 1;
    $blockinstance->showinsubcontexts = 1;
    $blockinstance->pagetypepattern = "my-index";
    $blockinstance->subpagepattern = NULL;
    $blockinstance->defaultregion = "content";
    $blockinstance->defaultweight = -1;
    $blockinstance->configdata = '';
    $blockinstance->requiredbytheme = 0;
    $blockinstance->timemodified = time();
    $blockinstance->timecreated = time();
    $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

    // Ensure the block context is created.
    context_block::instance($blockinstance->id);
}

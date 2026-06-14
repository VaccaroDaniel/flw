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

class block_sitenews_edit_form extends block_edit_form {

    /**
     * Extend block configuration
     *
     * @see block_edit_form::definition()
     */
    public function definition() {
        global $DB;

        parent::definition(); // first, create the orginal form

        // then, alter some options
        if (!$DB->record_exists("block_instances", array("id" => $this->block->instance->id, "defaultregion" => "content")) // default isn't "content", so it won't show up in the drop down list
                && $this->_form->elementExists("bui_defaultregion")) { // check, if some later releases won't crash
            $this->_form->getElement("bui_defaultregion")->addOption("Content", "content"); // I'm aware, that it would be "content", but with capital-C it's easier to recognize
        }
    }

}


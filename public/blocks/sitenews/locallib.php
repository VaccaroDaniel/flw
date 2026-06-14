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

/**
 * Returns user setting or default 1
 *
 * @return number
 */
function block_sitenews_get_itemsnumber() {
    return intval(get_user_preferences('sitenews_itemsnumber', 0));
}

/**
 * Stores new user settings
 *
 * @param int $value
 */
function block_sitenews_update_itemsnumber($value) {
    $value = clean_param($value, PARAM_INT);
    set_user_preference('sitenews_itemsnumber', $value);
}
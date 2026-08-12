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
 * H5p element settings.
 *
 * @package   cdelement_h5p
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Manadatory.
$name = 'cdelement_h5p/mandatory';
$title = get_string('mandatory', 'mod_contentdesigner');
$description = get_string('mandatory_help', 'mod_contentdesigner');
$options = [
    0 => get_string('no'),
    1 => get_string('yes'),
];
$setting = new admin_setting_configselect($name, $title, $description, 0, $options);
$page->add($setting);

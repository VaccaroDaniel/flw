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
 * element plugin "paragraph" - Settings file.
 *
 * @package   cdelement_paragraph
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Horizontal Align.
$name = 'cdelement_paragraph/horizontal';
$title = get_string('horizontalalign', 'mod_contentdesigner');
$description = get_string('horizontalalign_help', 'mod_contentdesigner');
$horizontalalign = [
    'left' => get_string('strleft', 'mod_contentdesigner'),
    'center' => get_string('strcenter', 'mod_contentdesigner'),
    'right' => get_string('strright', 'mod_contentdesigner'),
];
$setting = new admin_setting_configselect($name, $title, $description, 'left', $horizontalalign);
$page->add($setting);

// Vertical Align.
$name = 'cdelement_paragraph/vertical';
$title = get_string('verticalalign', 'mod_contentdesigner');
$description = get_string('verticalalign_help', 'mod_contentdesigner');
$verticalalign = [
    'top' => get_string('strtop', 'mod_contentdesigner'),
    'middle' => get_string('strmiddle', 'mod_contentdesigner'),
    'bottom' => get_string('strbottom', 'mod_contentdesigner'),
];
$setting = new admin_setting_configselect($name, $title, $description, 'top', $verticalalign);
$page->add($setting);

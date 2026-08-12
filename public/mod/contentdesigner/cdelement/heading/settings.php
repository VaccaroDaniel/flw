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
 * element plugin "Heading" - Settings file.
 *
 * @package   cdelement_heading
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Heading.
$name = 'cdelement_heading/headingtype';
$title = get_string('strheading', 'mod_contentdesigner');
$description = get_string('strheading_help', 'mod_contentdesigner');
$headings = [
    'h2' => get_string('mainheading', 'mod_contentdesigner'),
    'h3' => get_string('subheading', 'mod_contentdesigner'),
];
$setting = new admin_setting_configselect($name, $title, $description, 'h2', $headings);
$page->add($setting);

// Target.
$name = 'cdelement_heading/target';
$title = get_string('target', 'mod_contentdesigner');
$description = get_string('target_help', 'mod_contentdesigner');
$targets = [
    '_blank' => get_string('strblank', 'mod_contentdesigner'),
    '_self' => get_string('strself', 'mod_contentdesigner'),
];
$setting = new admin_setting_configselect($name, $title, $description, '_blank', $targets);
$page->add($setting);

// Horizontal Align.
$name = 'cdelement_heading/horizontal';
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
$name = 'cdelement_heading/vertical';
$title = get_string('verticalalign', 'mod_contentdesigner');
$description = get_string('verticalalign_help', 'mod_contentdesigner');
$verticalalign = [
    'top' => get_string('strtop', 'mod_contentdesigner'),
    'middle' => get_string('strmiddle', 'mod_contentdesigner'),
    'bottom' => get_string('strbottom', 'mod_contentdesigner'),
];
$setting = new admin_setting_configselect($name, $title, $description, 'top', $verticalalign);
$page->add($setting);

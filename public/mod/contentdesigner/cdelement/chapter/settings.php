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
 * Chapter element settings.
 *
 * @package   cdelement_chapter
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
global $CFG;

require_once($CFG->dirroot . '/mod/contentdesigner/cdelement/chapter/lib.php');

// Chapter visibility.
$name = 'cdelement_chapter/visibility';
$title = get_string('visibility', 'mod_contentdesigner');
$description = get_string('visibility_help', 'mod_contentdesigner');
$visibilityoptions = [
    1 => get_string('visibility', 'mod_contentdesigner'),
    0 => get_string('hidden', 'mod_contentdesigner'),
];
$setting = new admin_setting_configselect($name, $title, $description, 1, $visibilityoptions);
$page->add($setting);

// Chapter title status.
$name = 'cdelement_chapter/chaptertitlestatus';
$title = get_string('titlestatus', 'mod_contentdesigner');
$description = get_string('titlestatus_help', 'mod_contentdesigner');
$setting = new admin_setting_configcheckbox($name, $title, $description, 0);
$page->add($setting);

if (cdelement_chapter_has_learningtools()) {
    // Learning Tools setting.
    $name = 'cdelement_chapter/learningtools';
    $title = get_string('learningtools', 'mod_contentdesigner');
    $description = get_string('learningtools_help', 'mod_contentdesigner');
    $learningtoolsoptions = [
        0 => get_string('disabled', 'mod_contentdesigner'),
        1 => get_string('enabled', 'mod_contentdesigner'),
    ];
    $setting = new admin_setting_configselect($name, $title, $description, 0, $learningtoolsoptions);
    $page->add($setting);
}

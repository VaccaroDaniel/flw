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
 * element plugin "Outro" - Settings file.
 *
 * @package   cdelement_outro
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use cdelement_outro\element as outro;

// Outro content.
$name = 'cdelement_outro/outro_content';
$title = get_string('content', 'mod_contentdesigner');
$description = get_string('content_help', 'mod_contentdesigner');
$setting = new admin_setting_confightmleditor($name, $title, $description, '');
$page->add($setting);

// Outro primary button.
$options = [
    outro::OUTRO_BUTTON_DISABLED => get_string('disable'),
    outro::OUTRO_BUTTON_CUSTOM => get_string('outro:btncustom', 'mod_contentdesigner'),
    outro::OUTRO_BUTTON_NEXT => get_string('outro:btnnext', 'mod_contentdesigner'),
    outro::OUTRO_BUTTON_BACKTOCOURSE => get_string('outro:btnbacktocourse', 'mod_contentdesigner'),
    outro::OUTRO_BUTTON_BACKTOSECTION => get_string('outro:btnbacktosection', 'mod_contentdesigner'),
];
$name = 'cdelement_outro/primarybutton';
$title = get_string('primarybutton', 'mod_contentdesigner');
$description = get_string('primarybutton_help', 'mod_contentdesigner');
$setting = new admin_setting_configselect($name, $title, $description, outro::OUTRO_BUTTON_DISABLED, $options);
$page->add($setting);

// Outro secondary button.
$name = 'cdelement_outro/secondarybutton';
$title = get_string('secondarybutton', 'mod_contentdesigner');
$description = get_string('secondarybutton_help', 'mod_contentdesigner');
$setting = new admin_setting_configselect($name, $title, $description, outro::OUTRO_BUTTON_DISABLED, $options);
$page->add($setting);

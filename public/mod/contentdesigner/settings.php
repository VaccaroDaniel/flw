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
 * Contentdesigner module settings.
 *
 * @package   mod_contentdesigner
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

$ADMIN->add('modsettings', new admin_category('modcontentdesigner', new lang_string('pluginname', 'mod_contentdesigner')));

$elementsettings = new admin_settingpage(
    'elementgeneralsettings',
    get_string('elementstrgeneralsettings', 'mod_contentdesigner'),
    'moodle/site:config',
    false
);

if ($ADMIN->fulltree) {
    $visibilityoptions = [
        1 => get_string('visibility', 'mod_contentdesigner'),
        0 => get_string('hidden', 'mod_contentdesigner'),
    ];
    $elementsettings->add(new admin_setting_configselect('mod_contentdesigner/visible',
        get_string('visibility', 'mod_contentdesigner'),
        get_string('visibility_help', 'mod_contentdesigner'),
        1,
        $visibilityoptions
    ));

    $elementsettings->add(new admin_setting_configtext('mod_contentdesigner/margin',
        get_string('margin', 'mod_contentdesigner'),
        get_string('margin_help', 'mod_contentdesigner'),
        ''
    ));

    $elementsettings->add(new admin_setting_configtext('mod_contentdesigner/padding',
        get_string('padding', 'mod_contentdesigner'),
        get_string('padding_help', 'mod_contentdesigner'),
        ''
    ));

    $elementsettings->add(new admin_setting_configtext('mod_contentdesigner/abovecolorbg',
        get_string('abovecolorbg', 'mod_contentdesigner'),
        get_string('abovecolorbg_help', 'mod_contentdesigner'),
        ''
    ));

    $elementsettings->add(new admin_setting_configtext('mod_contentdesigner/belowcolorbg',
        get_string('belowcolorbg', 'mod_contentdesigner'),
        get_string('belowcolorbg_help', 'mod_contentdesigner'),
        ''
    ));

    $elementsettings->add(new admin_setting_configstoredfile('mod_contentdesigner/bgimage',
        get_string('elementbgimage', 'mod_contentdesigner'),
        get_string('elementbgimage_help', 'mod_contentdesigner'),
        'elementbg'
    ));

    $animationtype = [
        0 => get_string('none'),
        'fadeIn' => get_string('fadein', 'mod_contentdesigner'),
        'slideInRight' => get_string('slidefromright', 'mod_contentdesigner'),
        'slideInLeft' => get_string('slidefromleft', 'mod_contentdesigner'),
    ];
    $elementsettings->add(new admin_setting_configselect('mod_contentdesigner/animation',
        get_string('stranimation', 'mod_contentdesigner'),
        get_string('stranimation_help', 'mod_contentdesigner'),
        0,
        $animationtype
    ));

    $durations = [
        'slow' => get_string('strslow', 'mod_contentdesigner'),
        'normal' => get_string('strnormal', 'mod_contentdesigner'),
        'fast' => get_string('strfast', 'mod_contentdesigner'),
    ];
    $elementsettings->add(new admin_setting_configselect('mod_contentdesigner/duration',
        get_string('strduration', 'mod_contentdesigner'),
        get_string('strduration_help', 'mod_contentdesigner'),
        'slow',
        $durations
    ));

    $elementsettings->add(new admin_setting_configtext('mod_contentdesigner/delay',
        get_string('strdelay', 'mod_contentdesigner'),
        get_string('strdelay_help', 'mod_contentdesigner'),
        ''
    ));

    $scrolldirections = [
        0 => get_string('none'),
        'left' => get_string('toleft', 'mod_contentdesigner'),
        'right' => get_string('toright', 'mod_contentdesigner'),
    ];
    $elementsettings->add(new admin_setting_configselect('mod_contentdesigner/direction',
        get_string('strdirection', 'mod_contentdesigner'),
        get_string('strdirection_help', 'mod_contentdesigner'),
        0,
        $scrolldirections
    ));

    $elementsettings->add(new admin_setting_configselect('mod_contentdesigner/speed',
        get_string('speed', 'mod_contentdesigner'),
        get_string('speed_help', 'mod_contentdesigner'),
        0,
        range(0, 10)
    ));

    $elementsettings->add(new admin_setting_configtext('mod_contentdesigner/viewport',
        get_string('viewport', 'mod_contentdesigner'),
        get_string('viewport_help', 'mod_contentdesigner'),
        ''
    ));

    $elementsettings->add(new admin_setting_configcheckbox('mod_contentdesigner/hidedesktop',
        get_string('hideondesktop', 'mod_contentdesigner'),
        get_string('hideondesktop_help', 'mod_contentdesigner'),
        0
    ));

    $elementsettings->add(new admin_setting_configcheckbox('mod_contentdesigner/hidetablet',
        get_string('hideontablet', 'mod_contentdesigner'),
        get_string('hideontablet_help', 'mod_contentdesigner'),
        0
    ));

    $elementsettings->add(new admin_setting_configcheckbox('mod_contentdesigner/hidemobile',
        get_string('hideonmobile', 'mod_contentdesigner'),
        get_string('hideonmobile_help', 'mod_contentdesigner'),
        0
    ));
}

$ADMIN->add('modcontentdesigner', $elementsettings);

foreach (\core_plugin_manager::instance()->get_plugins_of_type('cdelement') as $plugin) {
    // Load all the element plugins settings pages.
    $plugin->load_settings($ADMIN, 'modcontentdesigner', $hassiteconfig);
}

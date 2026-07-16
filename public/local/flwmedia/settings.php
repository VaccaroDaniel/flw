<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_flwmedia', get_string('pluginname', 'local_flwmedia'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_flwmedia/mediaserverbase',
        get_string('mediaserverbase', 'local_flwmedia'),
        get_string('mediaserverbase_desc', 'local_flwmedia'),
        'https://media.example.com/flw',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_flwmedia/defaultperpage',
        get_string('defaultperpage', 'local_flwmedia'),
        get_string('defaultperpage_desc', 'local_flwmedia'),
        12,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_flwmedia/enablespeak',
        get_string('enablespeak', 'local_flwmedia'),
        get_string('enablespeak_desc', 'local_flwmedia'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_flwmedia/enableread',
        get_string('enableread', 'local_flwmedia'),
        get_string('enableread_desc', 'local_flwmedia'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_flwmedia/enabledictate',
        get_string('enabledictate', 'local_flwmedia'),
        get_string('enabledictate_desc', 'local_flwmedia'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_flwmedia/securemedia',
        get_string('securemedia', 'local_flwmedia'),
        get_string('securemedia_desc', 'local_flwmedia'),
        0
    ));
}

<?php
// Admin settings for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_flwcupkp', get_string('pluginname', 'local_flwcupkp'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_flwcupkp/enablesyncwrites',
        get_string('enablesyncwrites', 'local_flwcupkp'),
        get_string('enablesyncwrites_desc', 'local_flwcupkp'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_flwcupkp/sttendpoint',
        get_string('sttendpoint', 'local_flwcupkp'),
        get_string('sttendpoint_desc', 'local_flwcupkp'),
        'http://127.0.0.1:8765/api/stt/check',
        PARAM_URL
    ));
}

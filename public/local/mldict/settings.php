<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mldict', get_string('pluginname', 'local_mldict'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_mldict/defaultsourcelang',
        get_string('defaultsourcelang', 'local_mldict'),
        get_string('defaultsourcelang_desc', 'local_mldict'),
        'en',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_mldict/enabledlanguages',
        get_string('enabledlanguages', 'local_mldict'),
        get_string('enabledlanguages_desc', 'local_mldict'),
        'en,es,fr,de,ja',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_mldict_links',
        get_string('quicklinks', 'local_mldict'),
        html_writer::link(new moodle_url('/local/mldict/index.php'), get_string('opendictionary', 'local_mldict')) . ' | ' .
        html_writer::link(new moodle_url('/local/mldict/import.php'), get_string('importcsv', 'local_mldict'))
    ));
}

<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_flwaiassessment', get_string('pluginname', 'local_flwaiassessment'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_flwaiassessment/enableprocessing',
        get_string('enableprocessing', 'local_flwaiassessment'),
        get_string('enableprocessing_desc', 'local_flwaiassessment'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_flwaiassessment/apiurl',
        get_string('apiurl', 'local_flwaiassessment'),
        get_string('apiurl_desc', 'local_flwaiassessment'),
        'http://127.0.0.1:8000',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_flwaiassessment/modelname',
        get_string('modelname', 'local_flwaiassessment'),
        get_string('modelname_desc', 'local_flwaiassessment'),
        'local-cefr-estimator',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_flwaiassessment/requesttimeout',
        get_string('requesttimeout', 'local_flwaiassessment'),
        get_string('requesttimeout_desc', 'local_flwaiassessment'),
        60,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_flwaiassessment_links',
        get_string('quicklinks', 'local_flwaiassessment'),
        html_writer::link(new moodle_url('/local/flwaiassessment/index.php'), get_string('openreview', 'local_flwaiassessment')) . ' | ' .
        html_writer::link(new moodle_url('/local/flwaiassessment/submit.php'), get_string('opensubmit', 'local_flwaiassessment'))
    ));
}

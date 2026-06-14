<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'filter_mldict/maxterms',
        get_string('maxterms', 'filter_mldict'),
        get_string('maxterms_desc', 'filter_mldict'),
        500,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'filter_mldict/casesensitive',
        get_string('casesensitive', 'filter_mldict'),
        get_string('casesensitive_desc', 'filter_mldict'),
        0
    ));
}

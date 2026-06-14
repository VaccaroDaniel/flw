<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'theme_flwacademy/generalheading',
        get_string('generalsettings', 'theme_flwacademy'),
        get_string('generalsettings_desc', 'theme_flwacademy')
    ));
    $settings->add(new admin_setting_configcolourpicker('theme_flwacademy/emerald', get_string('emerald', 'theme_flwacademy'), '', '#0F9D7A'));
    $settings->add(new admin_setting_configcolourpicker('theme_flwacademy/orange', get_string('orange', 'theme_flwacademy'), '', '#FF8A00'));
    $settings->add(new admin_setting_configcolourpicker('theme_flwacademy/purple', get_string('purple', 'theme_flwacademy'), '', '#7B4DFF'));
    $settings->add(new admin_setting_configcolourpicker('theme_flwacademy/pink', get_string('pink', 'theme_flwacademy'), '', '#E05280'));
    $settings->add(new admin_setting_configcolourpicker('theme_flwacademy/cream', get_string('cream', 'theme_flwacademy'), '', '#FFFDF8'));
    $settings->add(new admin_setting_configtext('theme_flwacademy/radius', get_string('radius', 'theme_flwacademy'), '', '1.1rem', PARAM_TEXT));
    $settings->add(new admin_setting_configtextarea('theme_flwacademy/extrascss', get_string('extrascss', 'theme_flwacademy'), get_string('extrascss_desc', 'theme_flwacademy'), '', PARAM_RAW));
}

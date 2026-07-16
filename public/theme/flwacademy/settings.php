<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingflwacademy', get_string('configtitle', 'theme_flwacademy'));
    $page = new admin_settingpage('theme_flwacademy_general', get_string('generalsettings', 'theme_flwacademy'));

    $page->add(new admin_setting_heading(
        'theme_flwacademy/generalheading',
        get_string('generalsettings', 'theme_flwacademy'),
        get_string('generalsettings_desc', 'theme_flwacademy')
    ));

    $colours = [
        ['emerald', '#0a4be8'],
        ['orange', '#FF8A00'],
        ['purple', '#7B4DFF'],
        ['pink', '#E05280'],
        ['cream', '#FFFDF8'],
    ];
    foreach ($colours as [$name, $default]) {
        $setting = new admin_setting_configcolourpicker(
            'theme_flwacademy/' . $name,
            get_string($name, 'theme_flwacademy'),
            '',
            $default
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $page->add($setting);
    }

    $setting = new admin_setting_configtext(
        'theme_flwacademy/radius',
        get_string('radius', 'theme_flwacademy'),
        '',
        '1.1rem',
        PARAM_TEXT
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_scsscode(
        'theme_flwacademy/extrascss',
        get_string('extrascss', 'theme_flwacademy'),
        get_string('extrascss_desc', 'theme_flwacademy'),
        '',
        PARAM_RAW
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}

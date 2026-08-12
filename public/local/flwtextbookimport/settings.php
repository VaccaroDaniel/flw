<?php
// Admin settings for local_flwtextbookimport.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_flwtextbookimport', get_string('pluginname', 'local_flwtextbookimport'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_flwtextbookimport/defaultpackagepath',
        get_string('defaultpackagepath', 'local_flwtextbookimport'),
        get_string('defaultpackagepath_desc', 'local_flwtextbookimport'),
        'C:\\Users\\com\\Documents\\Estimation Speaking\\flw-moodle-importer-pilot\\output\\moodle_dry_run\\ckla_g2_u2_moodle_dry_run.json',
        PARAM_RAW_TRIMMED
    ));
}

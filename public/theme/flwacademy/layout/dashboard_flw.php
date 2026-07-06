<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$extraclasses = ['flw-dashboard-page'];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = theme_flwacademy_prepare_primary_navigation($primary->export_for_template($renderer));

ob_start();
echo $OUTPUT->main_content();
$maincontent = ob_get_clean();
$learninglanguages = theme_flwacademy_export_learning_languages();
$defaultlanguageurl = $learninglanguages[0]['categoryurl'] ?? (new moodle_url('/course/index.php'))->out(false);
$defaultschoolurl = $learninglanguages[0]['schoolcategoryurl'] ?? $defaultlanguageurl;
$defaultselfstudyurl = $learninglanguages[0]['selfstudycategoryurl'] ?? $defaultlanguageurl;
$defaultplacementtesturl = $learninglanguages[0]['placementtesturl'] ?? (new moodle_url('/local/flwplacement/index.php', [
    'language' => $learninglanguages[0]['code'] ?? 'en',
]))->out(false);
$defaultpracticeurl = $learninglanguages[0]['practicecategoryurl'] ?? $defaultlanguageurl;
$defaultexamurl = $learninglanguages[0]['examcategoryurl'] ?? $defaultlanguageurl;
$defaultpracticewatchurl = $learninglanguages[0]['practicewatchurl'] ?? $defaultpracticeurl;
$defaultpracticelistenurl = $learninglanguages[0]['practicelistenurl'] ?? $defaultpracticeurl;
$defaultpracticespeakurl = $learninglanguages[0]['practicespeakurl'] ?? $defaultpracticeurl;
$defaultpracticereadurl = $learninglanguages[0]['practicereadurl'] ?? $defaultpracticeurl;
$defaultpracticedictateurl = $learninglanguages[0]['practicedictateurl'] ?? $defaultpracticeurl;
$defaultexamlinks = [];
for ($i = 1; $i <= 6; $i++) {
    $defaultexamlinks[] = [
        'index' => $i,
        'label' => $learninglanguages[0]['exam' . $i . 'label'] ?? '',
        'url' => $learninglanguages[0]['exam' . $i . 'url'] ?? $defaultexamurl,
    ];
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, [
        'context' => context_course::instance(SITEID),
        'escape' => false,
    ]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'haslearninglanguages' => !empty($learninglanguages),
    'learninglanguages' => $learninglanguages,
    'defaultlanguageurl' => $defaultlanguageurl,
    'defaultschoolurl' => $defaultschoolurl,
    'defaultselfstudyurl' => $defaultselfstudyurl,
    'defaultplacementtesturl' => $defaultplacementtesturl,
    'defaultpracticeurl' => $defaultpracticeurl,
    'defaultexamurl' => $defaultexamurl,
    'defaultpracticewatchurl' => $defaultpracticewatchurl,
    'defaultpracticelistenurl' => $defaultpracticelistenurl,
    'defaultpracticespeakurl' => $defaultpracticespeakurl,
    'defaultpracticereadurl' => $defaultpracticereadurl,
    'defaultpracticedictateurl' => $defaultpracticedictateurl,
    'defaultexamlinks' => $defaultexamlinks,
    'heroimageurl' => $OUTPUT->image_url('dashboard/home', 'theme_flwacademy')->out(false),
    'maincontent' => $maincontent,
];

echo $OUTPUT->render_from_template('theme_flwacademy/dashboard', $templatecontext);

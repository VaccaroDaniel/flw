<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$extraclasses = ['flw-dashboard-page'];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$primarymoremenu = $primarymenu['moremenu'];
$primarymoremenu['nodearray'] = [];
$primarylinks = [
    [
        'key' => 'home',
        'text' => get_string('home'),
        'url' => (new moodle_url('/'))->out(false),
        'isactive' => false,
    ],
    [
        'key' => 'myhome',
        'text' => get_string('myhome'),
        'url' => (new moodle_url('/my/'))->out(false),
        'isactive' => true,
    ],
    [
        'key' => 'mycourses',
        'text' => get_string('mycourses'),
        'url' => (new moodle_url('/my/courses.php'))->out(false),
        'isactive' => false,
    ],
];
$systemcontext = context_system::instance();
if (is_siteadmin() || has_capability('moodle/site:config', $systemcontext)) {
    $primarylinks[] = [
        'key' => 'administrationsite',
        'text' => get_string('administrationsite'),
        'url' => (new moodle_url('/admin/search.php'))->out(false),
        'isactive' => false,
    ];
}
foreach ($primarylinks as $link) {
    $primarymoremenu['nodearray'][] = [
        'key' => $link['key'],
        'text' => $link['text'],
        'title' => $link['text'],
        'url' => $link['url'],
        'isactive' => $link['isactive'],
        'children' => [],
        'haschildren' => false,
        'moremenuid' => $primarymoremenu['moremenuid'] ?? 'flw-dashboard',
    ];
}

ob_start();
echo $OUTPUT->main_content();
$maincontent = ob_get_clean();
$learninglanguages = theme_flwacademy_export_learning_languages();
$defaultlanguageurl = $learninglanguages[0]['categoryurl'] ?? (new moodle_url('/course/index.php'))->out(false);
$defaultschoolurl = $learninglanguages[0]['schoolcategoryurl'] ?? $defaultlanguageurl;
$defaultselfstudyurl = $learninglanguages[0]['selfstudycategoryurl'] ?? $defaultlanguageurl;
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
    'primarymoremenu' => $primarymoremenu,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'haslearninglanguages' => !empty($learninglanguages),
    'learninglanguages' => $learninglanguages,
    'defaultlanguageurl' => $defaultlanguageurl,
    'defaultschoolurl' => $defaultschoolurl,
    'defaultselfstudyurl' => $defaultselfstudyurl,
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

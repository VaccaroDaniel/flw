<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

global $CFG, $USER;

$extraclasses = ['flw-dashboard-page'];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = theme_flwacademy_prepare_primary_navigation($primary->export_for_template($renderer));
$flwtopnav = theme_flwacademy_export_topnav_context($OUTPUT, $primarymenu, ['activekey' => 'myhome']);
$dashboardurl = new moodle_url('/my/');

ob_start();
echo $OUTPUT->main_content();
$maincontent = ob_get_clean();
$learninglanguages = theme_flwacademy_export_learning_languages();
$dashboarddata = theme_flwacademy_export_dashboard_data($OUTPUT, $learninglanguages);
$currentlanguagecode = $dashboarddata['currentlanguagecode'] ?? ($learninglanguages[0]['code'] ?? 'en');
$learninglanguages = array_map(static function(array $language) use ($currentlanguagecode): array {
    $language['isdefault'] = ($language['code'] ?? '') === $currentlanguagecode;
    return $language;
}, $learninglanguages);
$selectedlanguage = theme_flwacademy_get_selected_learning_language($learninglanguages);
$defaultlanguageurl = $selectedlanguage['categoryurl'] ?? (new moodle_url('/course/index.php'))->out(false);
$defaultschoolurl = $selectedlanguage['schoolcategoryurl'] ?? $defaultlanguageurl;
$defaultselfstudyurl = $selectedlanguage['selfstudycategoryurl'] ?? $defaultlanguageurl;
$defaultplacementtesturl = $selectedlanguage['placementtesturl'] ?? (new moodle_url('/local/flwplacement/index.php', [
    'language' => $selectedlanguage['code'] ?? 'en',
]))->out(false);
$defaultpracticeurl = $selectedlanguage['practicecategoryurl'] ?? $defaultlanguageurl;
$defaultexamurl = $selectedlanguage['examcategoryurl'] ?? $defaultlanguageurl;
$defaultpracticewatchurl = $selectedlanguage['practicewatchurl'] ?? $defaultpracticeurl;
$defaultpracticelistenurl = $selectedlanguage['practicelistenurl'] ?? $defaultpracticeurl;
$defaultpracticespeakurl = $selectedlanguage['practicespeakurl'] ?? $defaultpracticeurl;
$defaultpracticereadurl = $selectedlanguage['practicereadurl'] ?? $defaultpracticeurl;
$defaultpracticedictateurl = $selectedlanguage['practicedictateurl'] ?? $defaultpracticeurl;
$defaultexamlinks = [];
for ($i = 1; $i <= 6; $i++) {
    $defaultexamlinks[] = [
        'index' => $i,
        'label' => $selectedlanguage['exam' . $i . 'label'] ?? '',
        'url' => $selectedlanguage['exam' . $i . 'url'] ?? $defaultexamurl,
    ];
}
$flwasset = static function(string $name) use ($OUTPUT): string {
    return $OUTPUT->image_url('redesign/' . $name, 'theme_flwacademy')->out(false);
};
$userfullname = isloggedin() && !isguestuser() ? fullname($USER) : 'FLW Learner';
$userpicture = isloggedin() && !isguestuser()
    ? $OUTPUT->user_picture($USER, ['size' => 45, 'link' => false, 'class' => 'flw-dash-avatar'])
    : html_writer::empty_tag('img', [
        'class' => 'flw-dash-avatar',
        'src' => $flwasset('dash-avatar'),
        'alt' => 'FLW learner',
    ]);
$profileurl = new moodle_url('/user/profile.php', ['id' => $USER->id ?? 0]);
$logouturl = new moodle_url('/login/logout.php', ['sesskey' => sesskey()]);
$frontpageurl = new moodle_url('/', ['redirect' => 0]);
$dictionaryurl = is_readable($CFG->dirroot . '/local/mldict/index.php')
    ? (new moodle_url('/local/mldict/index.php'))->out(false)
    : $dashboardurl->out(false) . '#flw-dictionary';
$demourl = theme_flwacademy_get_demo_category_url() ?: (new moodle_url('/course/index.php'))->out(false);
$hasadminsite = is_siteadmin() || has_capability('moodle/site:config', context_system::instance());

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
    'langmenu' => $primarymenu['lang'],
    'flwtopnav' => $flwtopnav,
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
    'currentlanguagecode' => $currentlanguagecode,
    'currentlanguagelabel' => $dashboarddata['currentlanguagelabel'] ?? ($selectedlanguage['label'] ?? 'English'),
    'currentworldname' => $dashboarddata['currentworldname'] ?? (($selectedlanguage['label'] ?? 'English') . ' World V2'),
    'currentworldcresturl' => $dashboarddata['currentworldcresturl'] ?? $OUTPUT->image_url('redesign/crest-real', 'theme_flwacademy')->out(false),
    'currentcourse' => $dashboarddata['currentcourse'],
    'todayitems' => $dashboarddata['todayitems'],
    'unitnodes' => $dashboarddata['unitnodes'],
    'journey' => $dashboarddata['journey'],
    'skillrows' => $dashboarddata['skillrows'],
    'vocab' => $dashboarddata['vocab'],
    'checkpoint' => $dashboarddata['checkpoint'],
    'portfolio' => $dashboarddata['portfolio'],
    'rank' => $dashboarddata['rank'],
    'heroimageurl' => $OUTPUT->image_url('dashboard/home', 'theme_flwacademy')->out(false),
    'redesignchinesecresturl' => $flwasset('dash-chinese-crest'),
    'redesigncheckpointcresturl' => $flwasset('dash-checkpoint-crest'),
    'redesignpagodaurl' => $flwasset('dash-pagoda'),
    'redesigntrophyurl' => $flwasset('dash-trophy'),
    'userfullname' => $userfullname,
    'userpicturehtml' => $userpicture,
    'profileurl' => $profileurl->out(false),
    'logouturl' => $logouturl->out(false),
    'frontpageurl' => $frontpageurl->out(false),
    'dashboardurl' => $dashboardurl->out(false),
    'dictionaryurl' => $dictionaryurl,
    'demourl' => $demourl,
    'hasadminsite' => $hasadminsite,
    'adminsiteurl' => (new moodle_url('/admin/search.php'))->out(false),
    'adminsitetext' => get_string('administrationsite'),
    'maincontent' => $maincontent,
];

$templatecontext = theme_flwacademy_extend_tools_context($templatecontext);
echo $OUTPUT->render_from_template('theme_flwacademy/dashboard', $templatecontext);

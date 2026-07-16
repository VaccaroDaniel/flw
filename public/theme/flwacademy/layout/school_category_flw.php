<?php
defined('MOODLE_INTERNAL') || die();

global $CFG, $DB, $OUTPUT, $PAGE;

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$category = $categoryid ? $DB->get_record('course_categories', ['id' => $categoryid], '*', IGNORE_MISSING) : null;

$isschoolcategory = $category && theme_flwacademy_is_school_category($category);
$isselfstudycategory = $category && theme_flwacademy_is_selfstudy_category($category);
$isdemocategory = $category && theme_flwacademy_is_demo_category($category);
$isactivitycategory = $category && theme_flwacademy_resolve_activity_category($category);

if (!$category || (!$isschoolcategory && !$isselfstudycategory && !$isdemocategory && !$isactivitycategory)) {
    require($CFG->dirroot . '/theme/flwacademy/layout/drawers.php');
    return;
}

$extraclasses = ['flw-school-category-page', 'uses-drawers'];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = theme_flwacademy_prepare_primary_navigation($primary->export_for_template($renderer));
$learninglanguages = theme_flwacademy_export_learning_languages();

ob_start();
echo $OUTPUT->main_content();
$maincontent = ob_get_clean();

if ($isschoolcategory) {
    $templatecontext = theme_flwacademy_export_school_category_page($categoryid, $OUTPUT);
    $currentcategorytype = 'school';
    $activekey = 'flw-school';
} else if ($isselfstudycategory) {
    $templatecontext = theme_flwacademy_export_selfstudy_category_page($categoryid, $OUTPUT);
    $currentcategorytype = 'selfstudy';
    $activekey = 'flw-selfstudy';
} else if ($isdemocategory) {
    $templatecontext = theme_flwacademy_export_demo_category_page($categoryid, $OUTPUT);
    $currentcategorytype = 'demo';
    $activekey = 'flw-demo';
} else {
    $templatecontext = theme_flwacademy_export_activity_category_page($categoryid, $OUTPUT);
    $currentcategorytype = $templatecontext['area'] ?? '';
    $activekey = $currentcategorytype === 'exam' ? 'flw-exam' : ($currentcategorytype === 'practice' ? 'flw-practice' : '');
}
$currentlanguagecode = $templatecontext['languagecode'] ?? '';
$selectedlanguagecode = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
$selectedlanguagecode = strtolower($selectedlanguagecode) === 'zh_cn' ? 'zh' : strtolower($selectedlanguagecode);
if ($isselfstudycategory && $selectedlanguagecode !== '' && $currentlanguagecode !== '' && $selectedlanguagecode !== $currentlanguagecode) {
    foreach ($learninglanguages as $language) {
        if (($language['code'] ?? '') === $selectedlanguagecode && !empty($language['selfstudycategoryurl'])) {
            redirect(new moodle_url($language['selfstudycategoryurl']));
        }
    }
}
if ($currentlanguagecode !== '') {
    $learninglanguages = array_map(static function(array $language) use ($currentlanguagecode): array {
        $language['isdefault'] = $language['code'] === $currentlanguagecode;
        return $language;
    }, $learninglanguages);
}
$templatecontext += [
    'sitename' => format_string($SITE->shortname, true, [
        'context' => context_course::instance(SITEID),
        'escape' => false,
    ]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'maincontent' => $maincontent,
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'flwtopnav' => theme_flwacademy_export_topnav_context($OUTPUT, $primarymenu, ['activekey' => $activekey]),
    'haslearninglanguages' => !empty($learninglanguages),
    'learninglanguages' => $learninglanguages,
    'currentlanguagecode' => $currentlanguagecode,
    'currentcategorytype' => $currentcategorytype,
];

$templatecontext = theme_flwacademy_extend_tools_context($templatecontext);
$templatename = 'theme_flwacademy/flw_activity_category';
if ($isschoolcategory) {
    $templatename = 'theme_flwacademy/flw_school_category';
} else if ($isselfstudycategory) {
    $templatename = 'theme_flwacademy/flw_selfstudy_category';
} else if ($isdemocategory) {
    $templatename = 'theme_flwacademy/flw_demo_category';
}

echo $OUTPUT->render_from_template($templatename, $templatecontext);

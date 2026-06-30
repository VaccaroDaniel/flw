<?php
defined('MOODLE_INTERNAL') || die();

global $CFG, $DB, $OUTPUT, $PAGE;

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$category = $categoryid ? $DB->get_record('course_categories', ['id' => $categoryid], '*', IGNORE_MISSING) : null;

$isschoolcategory = $category && theme_flwacademy_is_school_category($category);
$isselfstudycategory = $category && theme_flwacademy_is_selfstudy_category($category);
$isactivitycategory = $category && theme_flwacademy_resolve_activity_category($category);

if (!$category || (!$isschoolcategory && !$isselfstudycategory && !$isactivitycategory)) {
    require($CFG->dirroot . '/theme/boost/layout/drawers.php');
    return;
}

$extraclasses = ['flw-school-category-page', 'uses-drawers'];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

ob_start();
echo $OUTPUT->main_content();
$maincontent = ob_get_clean();

if ($isschoolcategory) {
    $templatecontext = theme_flwacademy_export_school_category_page($categoryid, $OUTPUT);
} else if ($isselfstudycategory) {
    $templatecontext = theme_flwacademy_export_selfstudy_category_page($categoryid, $OUTPUT);
} else {
    $templatecontext = theme_flwacademy_export_activity_category_page($categoryid, $OUTPUT);
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
];

$templatename = 'theme_flwacademy/flw_activity_category';
if ($isschoolcategory) {
    $templatename = 'theme_flwacademy/flw_school_category';
} else if ($isselfstudycategory) {
    $templatename = 'theme_flwacademy/flw_selfstudy_category';
}

echo $OUTPUT->render_from_template($templatename, $templatecontext);

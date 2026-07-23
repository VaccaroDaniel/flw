<?php
defined('MOODLE_INTERNAL') || die();

if (!function_exists('theme_flwacademy_prepare_primary_navigation')) {
    require_once($CFG->dirroot . '/theme/flwacademy/lib.php');
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = strpos($blockshtml, 'data-block=') !== false;
$bodyattributes = $OUTPUT->body_attributes();
$renderer = $PAGE->get_renderer('core');

$primary = new core\navigation\output\primary($PAGE);
$primarymenu = theme_flwacademy_prepare_primary_navigation($primary->export_for_template($renderer));
$flwtopnav = theme_flwacademy_export_topnav_context($OUTPUT, $primarymenu);
$flwexamquiz = theme_flwacademy_get_current_flw_exam_quiz();
$flwplacementquiz = theme_flwacademy_get_current_flw_placement_quiz();

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, [
        'context' => context_course::instance(SITEID),
        'escape' => false,
    ]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'flwtopnav' => $flwtopnav,
];

if ($flwexamquiz || $flwplacementquiz) {
    $flwquiztitle = format_string(($flwplacementquiz ?: $flwexamquiz)->name, true, [
        'context' => $PAGE->context,
        'escape' => true,
    ]);
    $flwquizkicker = $flwplacementquiz
        ? get_string('placementtest', 'local_flwplacement')
        : get_string('exam', 'local_flwexam');
    $templatecontext['flwquizheading'] = html_writer::div(
        html_writer::div($flwquizkicker, 'flw-exam-quiz-kicker') .
            html_writer::tag('h1', $flwquiztitle),
        'flw-exam-quiz-heading'
    );
}

if (empty($PAGE->layout_options['noactivityheader'])) {
    $header = $PAGE->activityheader;
    $templatecontext['headercontent'] = $header->export_for_template($renderer);
}

echo $OUTPUT->render_from_template('theme_flwacademy/secure', $templatecontext);

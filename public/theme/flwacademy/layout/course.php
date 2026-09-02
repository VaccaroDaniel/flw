<?php
// This file is part of Moodle - http://moodle.org/

/**
 * FLW Clean Theme v3 course layout.
 *
 * Keeps Moodle and Boost compatibility while applying one shared reader shell
 * to student and teacher non-editing course views.
 *
 * @package    theme_flwacademy
 * @copyright  2026 Foreign Language World
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

$flwmapfile = $CFG->dirroot . '/flwcontent/native_course_assets.json';
if (!empty($PAGE->course->id) && is_file($flwmapfile)) {
    $flwmap = json_decode(file_get_contents($flwmapfile), true);
    $flwcoursekey = (string)(int)$PAGE->course->id;
    if (is_array($flwmap) && !empty($flwmap[$flwcoursekey]) && is_array($flwmap[$flwcoursekey])) {
        foreach (['css' => 'css', 'js' => 'js'] as $flwtype => $flwmethod) {
            if (empty($flwmap[$flwcoursekey][$flwtype])) {
                continue;
            }
            $flwrelative = '/' . ltrim($flwmap[$flwcoursekey][$flwtype], '/');
            $flwfullpath = $CFG->dirroot . $flwrelative;
            if (!is_file($flwfullpath) || strpos($flwrelative, '/flwcontent/') !== 0) {
                continue;
            }
            $flwversion = filemtime($flwfullpath);
            if ($flwmethod === 'css') {
                $PAGE->requires->css(new moodle_url($flwrelative, ['v' => $flwversion]));
            } else {
                $PAGE->requires->js(new moodle_url($flwrelative, ['v' => $flwversion]));
            }
        }
    }
}

$flwreadingmode = theme_flwacademy_is_reading_mode();
$flwscormlaunchurls = theme_flwacademy_get_scorm_direct_launch_urls();
theme_flwacademy_require_scorm_direct_launch($flwscormlaunchurls);
if ($flwreadingmode) {
    $PAGE->requires->js_call_amd('theme_flwacademy/flw_video_lazy', 'init');
    $PAGE->requires->js_call_amd('theme_flwacademy/flw_pagination', 'init');
    $PAGE->requires->js_call_amd('theme_flwacademy/flw_accordion', 'init');
    $PAGE->requires->js_init_code("
        (function() {
            function openAllCourseSections() {
                Array.prototype.slice.call(document.querySelectorAll('.course-content-item-content.collapse')).forEach(function(sectionContent) {
                    sectionContent.classList.add('show');
                    sectionContent.style.display = 'block';
                    sectionContent.style.height = 'auto';
                    sectionContent.style.visibility = 'visible';
                });
                Array.prototype.slice.call(document.querySelectorAll('.course-section-header .icons-collapse-expand, [data-bs-toggle=\"collapse\"][href^=\"#coursecontentcollapseid\"]')).forEach(function(toggle) {
                    toggle.classList.remove('collapsed');
                    toggle.setAttribute('aria-expanded', 'true');
                });
                Array.prototype.slice.call(document.querySelectorAll('.course-content .section-summary')).forEach(function(section) {
                    section.classList.remove('section-summary');
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openAllCourseSections, {once: true});
            } else {
                openAllCourseSections();
            }
            window.setTimeout(openAllCourseSections, 250);
        })();
    ");
}

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($flwreadingmode) {
    $extraclasses[] = 'flw-reading-course-mode';
    $courseindexopen = false;
    $blockdraweropen = false;
} else if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $flwreadingmode ? '' : $OUTPUT->blocks('side-pre');
$hasblocks = !$flwreadingmode && (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$flwreadingtoc = [];
$flwreadingtoclabel = '';
$flwshowcontentshell = $flwreadingmode;

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $flwreadingmode ? false : $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if (!$flwreadingmode && $PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = theme_flwacademy_prepare_primary_navigation($primary->export_for_template($renderer));
$flwtopnav = theme_flwacademy_export_topnav_context($OUTPUT, $primarymenu);
$buildregionmainsettings = !$flwreadingmode
    && !$PAGE->include_region_main_settings_in_header_actions()
    && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$headercontent = false;
if (!$flwreadingmode) {
    $header = $PAGE->activityheader;
    $headercontent = $header->export_for_template($renderer);
}

$learninglanguages = theme_flwacademy_export_learning_languages();
$currentlanguagecode = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
if ($currentlanguagecode !== '') {
    $learninglanguages = array_map(static function(array $language) use ($currentlanguagecode): array {
        $language['isdefault'] = $language['code'] === $currentlanguagecode;
        return $language;
    }, $learninglanguages);
}
$currentlanguagelabel = $learninglanguages[0]['label'] ?? 'English';
foreach ($learninglanguages as $language) {
    if (!empty($language['isdefault'])) {
        $currentlanguagelabel = $language['label'];
        break;
    }
}
$flwdictionaryurl = is_readable($CFG->dirroot . '/local/mldict/index.php')
    ? (new moodle_url('/local/mldict/index.php'))->out(false)
    : (new moodle_url('/my/'))->out(false) . '#flw-dictionary';
$flwcoursetitle = $flwreadingmode
    ? format_string($PAGE->course->fullname, true, [
        'context' => context_course::instance((int)$PAGE->course->id),
        'escape' => true,
    ])
    : '';

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'flwtopnav' => $flwtopnav,
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $flwreadingmode ? '' : $addblockbutton,
    'flwreadingmode' => $flwreadingmode,
    'flwshowcontentshell' => $flwshowcontentshell,
    'flwcoursetitle' => $flwcoursetitle,
    'flwshowactivitynavigation' => !$flwreadingmode,
    'hasflwreadingtoc' => !empty($flwreadingtoc),
    'flwtoolsshowcourseindex' => false,
    'flwreadingtoc' => $flwreadingtoc,
    'flwreadingtoclabel' => $flwreadingtoclabel,
    'haslearninglanguages' => !empty($learninglanguages),
    'learninglanguages' => $learninglanguages,
    'currentlanguagecode' => $currentlanguagecode,
    'currentlanguagelabel' => $currentlanguagelabel,
    'hasflwdictionary' => $flwdictionaryurl !== '',
    'flwdictionaryurl' => $flwdictionaryurl,
    'currentcategorytype' => '',
];

$templatecontext = theme_flwacademy_extend_tools_context($templatecontext);
$pagehtml = $OUTPUT->render_from_template('theme_flwacademy/flw_app_shell', $templatecontext);
echo $pagehtml;

<?php
// This file is part of Moodle - http://moodle.org/

/**
 * FLW Clean Theme v3 course layout.
 *
 * Keeps Moodle and Boost compatibility while applying the clean student shell
 * only to non-editing course views where the user cannot update the course.
 *
 * @package    theme_flwacademy
 * @copyright  2026 Foreign Language World
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

if (strpos($PAGE->pagetype, 'course-view-section-') === 0 && !$PAGE->user_is_editing()) {
    $flwsectionid = optional_param('id', 0, PARAM_INT);
    if ($flwsectionid) {
        $flwsection = $DB->get_record('course_sections', ['id' => $flwsectionid], 'id, course, section', IGNORE_MISSING);
        if ($flwsection && (int)$flwsection->course !== SITEID) {
            $flwcourseurl = new moodle_url('/course/view.php', ['id' => $flwsection->course]);
            $flwcourseurl->set_anchor('section-' . $flwsection->section);
            redirect($flwcourseurl, '', 0);
        }
    }
}

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

if (!empty($PAGE->course->id) && (int)$PAGE->course->id !== SITEID && course_format_uses_sections($PAGE->course->format)) {
    $flwsections = $DB->get_records(
        'course_sections',
        ['course' => $PAGE->course->id],
        'section ASC',
        'id, section'
    );
    $flwsectionmap = [];
    foreach ($flwsections as $flwsection) {
        $flwsectionmap[(int)$flwsection->id] = (int)$flwsection->section;
    }
    if ($flwsectionmap) {
        $flwsectionmapjson = json_encode($flwsectionmap);
        $flwcourseurljson = json_encode((new moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false));
        $PAGE->requires->js_init_code("
            (function() {
                var sectionMap = {$flwsectionmapjson};
                var courseUrl = {$flwcourseurljson};
                Array.prototype.slice.call(document.querySelectorAll('a[href*=\"/course/section.php?id=\"]')).forEach(function(link) {
                    try {
                        var url = new URL(link.getAttribute('href'), window.location.origin);
                        var sectionId = url.searchParams.get('id');
                        var sectionNumber = sectionMap[sectionId];
                        if (sectionNumber !== undefined) {
                            link.setAttribute('href', courseUrl + '#section-' + sectionNumber);
                        }
                    } catch (error) {
                        // Leave Moodle's original URL in place if the browser cannot parse it.
                    }
                });
            })();
        ");
    }
}

$flwcleanmode = theme_flwacademy_is_clean_mode();
if ($flwcleanmode) {
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
if ($flwcleanmode) {
    $extraclasses[] = 'flw-clean-mode';
    $courseindexopen = false;
    $blockdraweropen = false;
} else if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $flwcleanmode ? '' : $OUTPUT->blocks('side-pre');
$hasblocks = !$flwcleanmode && (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

$courseindex = $flwcleanmode ? false : core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$flwreadingtoc = [];
$flwreadingtoclabel = '';
$flwshowreadingtoc = !$PAGE->user_is_editing() && course_format_uses_sections($PAGE->course->format);
if ($flwshowreadingtoc) {
    $coursecontext = context_course::instance((int)$PAGE->course->id);
    $flwreadingtoclabel = format_string($PAGE->course->fullname, true, ['context' => $coursecontext]);
    $modinfo = get_fast_modinfo($PAGE->course);
    foreach ($modinfo->get_section_info_all() as $sectionnumber => $sectioninfo) {
        if ((int)$sectionnumber === 0 || empty($sectioninfo->uservisible)) {
            continue;
        }
        $sectionname = trim(get_section_name($PAGE->course, $sectioninfo));
        if ($sectionname === '') {
            $sectionname = 'Section ' . (int)$sectionnumber;
        }
        $flwreadingtoc[] = [
            'number' => (int)$sectionnumber,
            'title' => format_string($sectionname, true, ['context' => $coursecontext]),
            'url' => '#section-' . (int)$sectionnumber,
            'current' => count($flwreadingtoc) === 0,
            'hasactivities' => false,
            'activities' => [],
        ];
        $flwsectionindex = count($flwreadingtoc) - 1;
        foreach ($modinfo->sections[$sectionnumber] ?? [] as $cmid) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            if (empty($cm->uservisible) || !$cm->is_visible_on_course_page()) {
                continue;
            }
            $flwreadingtoc[$flwsectionindex]['activities'][] = [
                'title' => format_string($cm->name, true, ['context' => $cm->context]),
                'url' => $cm->url ? $cm->url->out(false) : '#module-' . (int)$cm->id,
                'modname' => $cm->modname,
            ];
        }
        $flwreadingtoc[$flwsectionindex]['hasactivities'] = !empty($flwreadingtoc[$flwsectionindex]['activities']);
    }
}
$flwshowcontentshell = $flwcleanmode || !empty($flwreadingtoc);
if ($flwshowcontentshell) {
    $extraclasses[] = 'flw-reading-course-mode';
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $flwcleanmode ? false : $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if (!$flwcleanmode && $PAGE->has_secondary_navigation()) {
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
$buildregionmainsettings = !$flwcleanmode
    && !$PAGE->include_region_main_settings_in_header_actions()
    && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$headercontent = false;
if (!$flwcleanmode) {
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
    'addblockbutton' => $flwcleanmode ? '' : $addblockbutton,
    'flwcleanmode' => $flwcleanmode,
    'flwshowcontentshell' => $flwshowcontentshell,
    'flwshowactivitynavigation' => !$flwcleanmode,
    'hasflwreadingtoc' => !empty($flwreadingtoc),
    'flwtoolsshowcourseindex' => !$flwshowcontentshell && !empty($courseindex),
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

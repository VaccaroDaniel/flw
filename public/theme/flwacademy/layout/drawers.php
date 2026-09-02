<?php
// This file is part of Moodle - http://moodle.org/

/**
 * FLW Academy drawer layout.
 *
 * Keeps Boost compatibility while loading FLW native course assets and
 * replacing the primary navigation with FLW learning routes.
 *
 * @package    theme_flwacademy
 * @copyright  2026 Foreign Language World
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');

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

// Quiz detection is required both for course-index cleanup and for taking-page
// cleanup rules later in this layout.
$flwexamquiz = theme_flwacademy_get_current_flw_exam_quiz();
$flwplacementquiz = theme_flwacademy_get_current_flw_placement_quiz();
$flwquizqueryplacement = optional_param('flwplacement', false, PARAM_BOOL)
    || optional_param('autostart', false, PARAM_BOOL)
    || optional_param('flwautostart', false, PARAM_BOOL)
    || optional_param('flwskippreflight', false, PARAM_BOOL);
$flwquizcleanquery = (strpos($PAGE->pagetype, 'mod-quiz-') === 0) &&
    (optional_param('flwskippreflight', false, PARAM_BOOL) || optional_param('flwautostart', false, PARAM_BOOL) || optional_param('autostart', false, PARAM_BOOL));
$flwquizcleanpage = $flwexamquiz || $flwplacementquiz || $flwquizqueryplacement || $flwquizcleanquery;

$flwscormcleanpage = $PAGE->pagetype === 'mod-scorm-player' && !$PAGE->user_is_editing();
$flwscormscomap = [];
if ($flwscormcleanpage && !empty($PAGE->cm->id) && !empty($PAGE->cm->instance)) {
    $flwscormcurrentorg = (string)($PAGE->url->get_param('currentorg') ?? '');
    $flwscormmode = (string)($PAGE->url->get_param('mode') ?? '');
    $flwscormscoes = $DB->get_records(
        'scorm_scoes',
        ['scorm' => (int)$PAGE->cm->instance],
        'sortorder ASC, id ASC',
        'id,identifier,scormtype'
    );
    foreach ($flwscormscoes as $flwscormsco) {
        if ($flwscormsco->scormtype !== 'sco' || $flwscormsco->identifier === '') {
            continue;
        }
        $flwscormparams = [
            'cm' => (int)$PAGE->cm->id,
            'scoid' => (int)$flwscormsco->id,
        ];
        if ($flwscormcurrentorg !== '') {
            $flwscormparams['currentorg'] = $flwscormcurrentorg;
        }
        if ($flwscormmode !== '' && $flwscormmode !== 'normal') {
            $flwscormparams['mode'] = $flwscormmode;
        }
        $flwscormscomap[$flwscormsco->identifier] = [
            'scoid' => (int)$flwscormsco->id,
            'url' => http_build_query($flwscormparams, '', '&', PHP_QUERY_RFC3986),
        ];
    }
    if ($flwscormscomap) {
        // FLW SCORM packages read this map from their parent player before a lesson change.
        $PAGE->requires->data_for_js('FLW_MOODLE_SCO_MAP', $flwscormscomap, true);
    }
}
$flwscormlaunchurls = theme_flwacademy_get_scorm_direct_launch_urls();
theme_flwacademy_require_scorm_direct_launch($flwscormlaunchurls);
$flwscormredirectpage = $PAGE->pagetype === 'mod-scorm-view'
    && !empty($PAGE->cm->id)
    && !empty($flwscormlaunchurls['cm'][(string)(int)$PAGE->cm->id]);
$flwpracticepage = $PAGE->pagetype === 'local-flwmedia-index';
if ($flwquizcleanpage) {
    $PAGE->requires->js_init_code("
        (function() {
            function isPlacementQuizPage() {
                return location.pathname.indexOf('/mod/quiz/') !== -1;
            }

            function isProtectedNavigation(node) {
                return !!(node && node.closest && node.closest('header.navbar, .navbar.fixed-top, .flw-topnav, #usernavigation'));
            }

            function isRequiredQuizAction(node) {
                if (!node || !node.closest) {
                    return false;
                }
                if (node.closest('.modal, .submitbtns')) {
                    return true;
                }
                if (node.id === 'secureclosebutton') {
                    return true;
                }

                var text = ((node.textContent || '') + ' ' + (node.value || '')).replace(/\\s+/g, ' ').trim().toLowerCase();
                return text === 'finish review' ||
                    text === 'next page' ||
                    text === 'previous page' ||
                    text === 'return to attempt' ||
                    text === 'submit all and finish' ||
                    text === 'end test' ||
                    text === 'cancel' ||
                    text === 'ok';
            }

            function hide(node) {
                if (!node || !node.style) {
                    return;
                }
                if (isProtectedNavigation(node)) {
                    return;
                }
                if (isRequiredQuizAction(node)) {
                    return;
                }
                if (node.dataset && node.dataset.flwPlacementQuizHidden) {
                    return;
                }
                if (node.dataset) {
                    node.dataset.flwPlacementQuizHidden = '1';
                }
                node.style.setProperty('display', 'none', 'important');
                node.style.setProperty('visibility', 'hidden', 'important');
                node.setAttribute('aria-hidden', 'true');
            }

            function isCandidate(node) {
                if (!node || node.id === 'page-header' || node.classList.contains('main-inner') || isProtectedNavigation(node)) {
                    return false;
                }
                var text = (node.textContent || '').replace(/\\s+/g, ' ').trim().toLowerCase();
                var label = (node.getAttribute('aria-label') || '').toLowerCase();
                var role = (node.getAttribute('role') || '').toLowerCase();
                var title = (node.getAttribute('title') || '').toLowerCase();
                var name = (node.getAttribute('name') || '').toLowerCase();
                return text === 'activity' ||
                    text === 'quiz' ||
                    text === 'activities' ||
                    text === 'attempt quiz' ||
                    text === 'attempt' ||
                    text.indexOf(' activity') !== -1 ||
                    text.indexOf('quiz') !== -1 ||
                    text.indexOf('quiz ') !== -1 ||
                    label.indexOf('activity') !== -1 ||
                    label.indexOf('quiz') !== -1 ||
                    title.indexOf('activity') !== -1 ||
                    title.indexOf('quiz') !== -1 ||
                    name === 'activity' ||
                    name === 'quiz' ||
                    role === 'menu' ||
                    role === 'menuitem';
            }

            function cleanPlacementUi() {
                if (!isPlacementQuizPage()) {
                    return;
                }
                var selectors = [
                    '#page-header .singlebutton',
                    '#page-header .dropdown',
                    '#page-header .dropdown-toggle',
                    '#page-header .action-menu',
                    '#page-header .action-menu-trigger',
                    '#page-header .activity-actions',
                    '#page-header .activity-action',
                    '.activity-actions',
                    '.activity-action',
                    '.path-mod-quiz-attempt .activityheader',
                    '.path-mod-quiz-view .activityheader',
                    '.mod_quiz-view-page .singlebutton',
                    '.page-header-headings .btn',
                    '.path-mod-quiz-attempt .btn[href*=\"/mod/quiz/edit.php\"]',
                    '.path-mod-quiz-attempt .action-menu',
                    '.path-mod-quiz-attempt .action-menu-toggle',
                    '.path-mod-quiz-attempt .dropdown',
                    '.path-mod-quiz-attempt .dropdown-toggle',
                    '.path-mod-quiz-attempt .singlebutton',
                    '.path-mod-quiz-attempt .tertiary-navigation',
                    '.path-mod-quiz-attempt .activity-actions',
                    '.path-mod-quiz-attempt .activity-action',
                    '.path-mod-quiz-attempt .action-menu-trigger',
                    '.path-mod-quiz-attempt .action-menu-toggle',
                    '.path-mod-quiz-attempt .context-header-actions',
                    '.path-mod-quiz-attempt .context-header-settings-menu',
                    '.path-mod-quiz-view .action-menu',
                    '.path-mod-quiz-view .action-menu-trigger',
                    '.path-mod-quiz-view .action-menu-toggle',
                    '.path-mod-quiz-attempt [data-region=\"activity-actions\"]',
                    '.path-mod-quiz-attempt [data-region=\"activity-navigation\"]',
                    '.path-mod-quiz-view [data-region=\"activity-actions\"]',
                    '.path-mod-quiz-view [data-region=\"activity-navigation\"]',
                    '[href*=\"/mod/quiz/report.php\"]',
                    '[href*=\"/mod/quiz/preview.php\"]',
                    '.path-mod-quiz [aria-label=\"Activity\"]',
                    '.path-mod-quiz [title=\"Activity\"]',
                    '.path-mod-quiz [aria-label=\"Quiz\"]',
                    '.path-mod-quiz [title=\"Quiz\"]',
                    '.path-mod-quiz [aria-label=\"Activity\"]',
                    '.path-mod-quiz [aria-label*=\"activity\" i]',
                    '.path-mod-quiz [title*=\"activity\" i]',
                    '.path-mod-quiz [aria-label*=\"quiz\" i]',
                    '.path-mod-quiz [title*=\"quiz\" i]',
                    '.path-mod-quiz [role=\"menuitem\"][aria-label*=\"activity\" i]',
                    '.path-mod-quiz [role=\"menuitem\"][title*=\"activity\" i]',
                    '.path-mod-quiz [role=\"menuitem\"][aria-label*=\"quiz\" i]',
                    '.path-mod-quiz [role=\"menuitem\"][title*=\"quiz\" i]',
                    '[data-action=\"toggle-fullscreen\"]',
                    '.path-mod-quiz [href*=\"/course/view.php\"] [title=\"Activity\" i]',
                    '[id*=\"action-menu-toggle-\"]'
                ];
                selectors.forEach(function(selector) {
                    Array.prototype.slice.call(document.querySelectorAll(selector)).forEach(hide);
                });

                var candidates = Array.prototype.slice.call(document.querySelectorAll(
                    'button, a, [role=\"menuitem\"], [role=\"button\"], .singlebutton button, .singlebutton a, .action-menu-trigger, .dropdown-toggle, .menu-action-text'
                ));
                candidates.forEach(function(candidate) {
                    if (isCandidate(candidate)) {
                        hide(candidate);
                    }
                });
            }

            function startPlacementCleanup() {
                cleanPlacementUi();
                var interval = window.setInterval(function() {
                    cleanPlacementUi();
                }, 500);
                window.setTimeout(function() {
                    window.clearInterval(interval);
                }, 4000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startPlacementCleanup);
            } else {
                startPlacementCleanup();
            }
        })();
    ");
}

$addblockbutton = $flwpracticepage ? '' : $OUTPUT->addblockbutton();

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
if ($flwpracticepage) {
    $extraclasses[] = 'flw-practice-page';
}
if ($flwscormredirectpage) {
    $extraclasses[] = 'flw-scorm-launch-redirect';
}
if ($flwscormcleanpage) {
    $extraclasses[] = 'flw-scorm-clean-mode';
    $courseindexopen = false;
    $blockdraweropen = false;
}
if ($flwexamquiz || $flwplacementquiz || $flwquizqueryplacement) {
    $extraclasses[] = 'flw-exam-quiz-page';
}
if ($flwplacementquiz || $flwquizqueryplacement) {
    $extraclasses[] = 'flw-placement-quiz-page';
}
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = ($flwscormcleanpage || $flwpracticepage) ? '' : $OUTPUT->blocks('side-pre');
$hasblocks = !$flwscormcleanpage && !$flwpracticepage
    && (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = $flwscormcleanpage ? false : core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = ($flwscormcleanpage || $flwpracticepage) ? false : $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if (!$flwscormcleanpage && $PAGE->has_secondary_navigation()) {
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
$learninglanguages = theme_flwacademy_export_learning_languages();
$currentlanguagecode = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
if ($currentlanguagecode !== '') {
    $learninglanguages = array_map(static function(array $language) use ($currentlanguagecode): array {
        $language['isdefault'] = $language['code'] === $currentlanguagecode;
        return $language;
    }, $learninglanguages);
}
$buildregionmainsettings = !$flwscormcleanpage
    && !$PAGE->include_region_main_settings_in_header_actions()
    && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$headercontent = false;
if (!$flwscormcleanpage) {
    $header = $PAGE->activityheader;
    $headercontent = $header->export_for_template($renderer);
}

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
    'addblockbutton' => $flwscormcleanpage ? '' : $addblockbutton,
    'haslearninglanguages' => !empty($learninglanguages),
    'learninglanguages' => $learninglanguages,
    'currentlanguagecode' => $currentlanguagecode,
    'currentcategorytype' => '',
];

$toolscontext = theme_flwacademy_extend_tools_context([
    'output' => $OUTPUT,
    'haslearninglanguages' => !empty($learninglanguages),
    'learninglanguages' => $learninglanguages,
    'currentlanguagecode' => $currentlanguagecode,
    'currentcategorytype' => '',
    'courseindex' => $courseindex,
]);
$pagehtml = $OUTPUT->render_from_template('theme_boost/drawers', $templatecontext);
if ($flwscormcleanpage && !empty($PAGE->cm->instance) &&
        strpos($pagehtml, '<div id="region-main">') !== false) {
    $flwscormname = $DB->get_field('scorm', 'name', ['id' => (int)$PAGE->cm->instance]);
    if ($flwscormname !== false && trim((string)$flwscormname) !== '') {
        $flwscormtitle = format_string($flwscormname, true, [
            'context' => $PAGE->context,
            'escape' => true,
        ]);
        $flwscormheading = html_writer::tag(
            'header',
            html_writer::tag('h1', $flwscormtitle, ['title' => trim(strip_tags($flwscormtitle))]),
            ['class' => 'flw-scorm-player-heading']
        );
        $regionmainmarker = '<div id="region-main">';
        $pagehtml = substr_replace(
            $pagehtml,
            $regionmainmarker . $flwscormheading,
            strpos($pagehtml, $regionmainmarker),
            strlen($regionmainmarker)
        );
    }
}
if (($flwexamquiz || $flwplacementquiz) && strpos($pagehtml, '<div id="region-main">') !== false) {
    $flwquiztitle = format_string(($flwplacementquiz ?: $flwexamquiz)->name, true, [
        'context' => $PAGE->context,
        'escape' => true,
    ]);
    $flwquizkicker = $flwplacementquiz
        ? get_string('placementtest', 'local_flwplacement')
        : get_string('exam', 'local_flwexam');
    $flwquizheading = html_writer::div(
        html_writer::div($flwquizkicker, 'flw-exam-quiz-kicker') .
            html_writer::tag('h1', $flwquiztitle),
        'flw-exam-quiz-heading'
    );
    $regionmainmarker = '<div id="region-main">';
    $pagehtml = substr_replace(
        $pagehtml,
        $regionmainmarker . $flwquizheading,
        strpos($pagehtml, $regionmainmarker),
        strlen($regionmainmarker)
    );
}
$toolsgroup = $OUTPUT->render_from_template('theme_flwacademy/flw_tools_group', $toolscontext);
if ($toolsgroup !== '' && strpos($pagehtml, '</body>') !== false) {
    $pagehtml = str_replace('</body>', $toolsgroup . "\n</body>", $pagehtml);
} else {
    $pagehtml .= $toolsgroup;
}
echo $pagehtml;

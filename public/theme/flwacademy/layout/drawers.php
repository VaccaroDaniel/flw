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

if ($PAGE->pagetype === 'mod-scorm-player' && !$PAGE->user_is_editing()) {
    $flwscormcmid = !empty($PAGE->cm->id) ? (int)$PAGE->cm->id : 0;
    $flwscormcourseid = !empty($PAGE->course->id) ? (int)$PAGE->course->id : 0;
    $flwscormcourseurl = json_encode((new moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false));
    $flwscormcmidjson = json_encode($flwscormcmid);
    $flwscormcourseidjson = json_encode($flwscormcourseid);
    $PAGE->requires->js_init_code("
        (function() {
            var courseUrl = {$flwscormcourseurl};
            var cmid = {$flwscormcmidjson};
            var courseId = {$flwscormcourseidjson};

            function getExitUrl() {
                try {
                    if (document.referrer) {
                        var referrerUrl = new URL(document.referrer, window.location.origin);
                        var currentUrl = new URL(window.location.href);
                        if (referrerUrl.origin === currentUrl.origin && referrerUrl.href !== currentUrl.href) {
                            return referrerUrl.href;
                        }
                    }
                } catch (error) {
                    // Fall back to the course page if the browser cannot parse the referrer.
                }
                return courseUrl;
            }

            function addToolbar() {
                var existingToolbar = document.getElementById('flw-scorm-actions');
                if (existingToolbar) {
                    Array.prototype.slice.call(existingToolbar.querySelectorAll('span')).forEach(function(span) {
                        span.remove();
                    });
                    if (existingToolbar.querySelector('.flw-scorm-toggle-toc')) {
                        return;
                    }
                    existingToolbar.remove();
                }
                var scormPage = document.getElementById('scormpage');
                if (!scormPage || !scormPage.parentNode) {
                    return;
                }

                var toolbar = document.createElement('nav');
                toolbar.id = 'flw-scorm-actions';
                toolbar.className = 'flw-scorm-actions';
                toolbar.setAttribute('aria-label', 'Course actions');
                toolbar.innerHTML =
                    '<button type=\"button\" class=\"flw-scorm-action flw-scorm-toggle-toc\" title=\"Toggle contents\" aria-label=\"Toggle contents\">' +
                    '<svg class=\"flw-toc-icon-horizontal\" viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M4 6h16\"></path><path d=\"M4 12h16\"></path><path d=\"M4 18h16\"></path></svg>' +
                    '<svg class=\"flw-toc-icon-vertical\" viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M7 4v16\"></path><path d=\"M12 4v16\"></path><path d=\"M17 4v16\"></path></svg></button>' +
                    '<button type=\"button\" class=\"flw-scorm-action flw-scorm-done\" title=\"Mark course done\" aria-label=\"Mark course done\">' +
                    '<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M20 6 9 17l-5-5\"></path></svg></button>' +
                    '<a class=\"flw-scorm-action flw-scorm-exit\" title=\"Exit activity\" aria-label=\"Exit activity\" href=\"' + getExitUrl() + '\">' +
                    '<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M10 17l-5-5 5-5\"></path><path d=\"M5 12h14\"></path></svg></a>';
                scormPage.parentNode.insertBefore(toolbar, scormPage);

                var tocButton = toolbar.querySelector('.flw-scorm-toggle-toc');
                function updateTocButtonState() {
                    var toc = document.getElementById('scorm_toc');
                    tocButton.classList.toggle('is-active', toc && !toc.classList.contains('disabled'));
                }
                updateTocButtonState();
                tocButton.addEventListener('click', function() {
                    var moodleToggle = document.getElementById('scorm_toc_toggle_btn');
                    var toc = document.getElementById('scorm_toc');
                    if (moodleToggle) {
                        moodleToggle.click();
                    } else if (toc) {
                        toc.classList.toggle('disabled');
                    }
                    window.setTimeout(updateTocButtonState, 30);
                });

                var exitButton = toolbar.querySelector('.flw-scorm-exit');
                exitButton.addEventListener('click', function(event) {
                    if (window.history.length > 1) {
                        event.preventDefault();
                        window.history.back();
                        return;
                    }
                    exitButton.setAttribute('href', getExitUrl());
                });

                var doneButton = toolbar.querySelector('.flw-scorm-done');
                doneButton.addEventListener('click', function() {
                    if (!cmid) {
                        doneButton.classList.add('is-error');
                        return;
                    }
                    doneButton.disabled = true;
                    doneButton.classList.add('is-loading');
                    require(['core/ajax'], function(Ajax) {
                        Ajax.call([{
                            methodname: 'core_completion_update_activity_completion_status_manually',
                            args: {cmid: cmid, completed: true}
                        }])[0].then(function() {
                            if (!courseId) {
                                return true;
                            }
                            return Ajax.call([{
                                methodname: 'core_completion_mark_course_self_completed',
                                args: {courseid: courseId}
                            }])[0].catch(function(error) {
                                if (error && error.errorcode === 'useralreadymarkedcomplete') {
                                    return true;
                                }
                                // The SCORM activity is complete; some courses do not enable self-completion criteria.
                                return true;
                            });
                        }).then(function() {
                            doneButton.classList.remove('is-loading');
                            doneButton.classList.add('is-done');
                        }).catch(function() {
                            doneButton.disabled = false;
                            doneButton.classList.remove('is-loading');
                            doneButton.classList.add('is-error');
                        });
                    });
                });
            }

            function collapseScormChromeElement(element) {
                if (!element) {
                    return;
                }
                element.classList.add('flw-scorm-hidden-chrome');
                element.style.setProperty('display', 'none', 'important');
                element.style.setProperty('height', '0', 'important');
                element.style.setProperty('min-height', '0', 'important');
                element.style.setProperty('max-height', '0', 'important');
                element.style.setProperty('margin', '0', 'important');
                element.style.setProperty('padding', '0', 'important');
                element.style.setProperty('border', '0', 'important');
                element.style.setProperty('overflow', 'hidden', 'important');
            }

            function hideScormChrome(root) {
                if (!root || !root.querySelectorAll) {
                    return;
                }
                Array.prototype.slice.call(root.querySelectorAll('#scorm-topbar, .scorm-topbar')).forEach(function(element) {
                    collapseScormChromeElement(element);
                });
                normalizeScormContentTop(root);
                Array.prototype.slice.call(root.querySelectorAll([
                    'h1',
                    'h2',
                    'h3',
                    'h4',
                    'h5',
                    'h6',
                    '.page-header-headings',
                    '.activitytitle',
                    '.instancename',
                    '.unit-title',
                    '.scorm-title',
                    '.flw-scorm-title',
                    '.title',
                    '[class*=\"title\"]',
                    '[class*=\"heading\"]',
                    '#region-main > *'
                ].join(','))).forEach(function(element) {
                    if ((element.textContent || '').replace(/\\s+/g, ' ').trim() === 'Real English World Unit 35') {
                        var target = element.closest('.activity-header, .page-context-header, #page-header, .page-header-headings, .activitytitle') || element;
                        collapseScormChromeElement(target);
                    }
                });
            }

            function normalizeScormContentTop(root) {
                if (!root || !root.querySelector || !root.body) {
                    return;
                }
                var packageTopbar = root.querySelector('.scorm-topbar, #scorm-topbar');
                var packageMain = root.querySelector('main.wrap');
                if (!packageTopbar && !packageMain) {
                    return;
                }
                if (packageMain) {
                    packageMain.style.setProperty('padding-top', '.75rem', 'important');
                    packageMain.style.setProperty('margin-top', '0', 'important');
                }
                if (root.documentElement && !root.documentElement.dataset.flwScormTopNormalized) {
                    root.documentElement.dataset.flwScormTopNormalized = '1';
                    try {
                        root.defaultView.scrollTo(0, 0);
                    } catch (error) {
                        // Some embedded documents do not expose scrollTo.
                    }
                    root.documentElement.scrollTop = 0;
                    root.body.scrollTop = 0;
                }
            }

            function hideReachableScormChrome() {
                document.documentElement.classList.add('flw-scorm-player-page');
                hideScormChrome(document);
                updateTocScrollHeight(document);
                Array.prototype.slice.call(document.querySelectorAll('iframe')).forEach(function(frame) {
                    try {
                        var framedocument = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
                        hideScormChrome(framedocument);
                    } catch (error) {
                        // Cross-origin frames are left untouched.
                    }
                    if (!frame.dataset.flwScormChromeHooked) {
                        frame.dataset.flwScormChromeHooked = '1';
                        frame.addEventListener('load', function() {
                            window.setTimeout(hideReachableScormChrome, 50);
                            window.setTimeout(hideReachableScormChrome, 500);
                        });
                    }
                });
            }

            function updateTocScrollHeight(root) {
                if (!root || !root.querySelector) {
                    return;
                }
                var toc = root.querySelector('#scorm_toc');
                var tree = root.querySelector('#scorm_tree');
                var layout = root.querySelector('#scorm_layout');
                var scormPage = root.querySelector('#scormpage');
                if (!toc || !tree || !toc.getBoundingClientRect) {
                    return;
                }
                if (layout && layout.getBoundingClientRect) {
                    var heightAnchor = scormPage && scormPage.getBoundingClientRect ? scormPage : layout;
                    var layoutTop = heightAnchor.getBoundingClientRect().top;
                    var layoutBottomGap = window.matchMedia('(max-width: 680px)').matches ? 10 : 14;
                    var layoutMinimum = window.matchMedia('(max-width: 680px)').matches ? 420 : 520;
                    var layoutHeight = Math.max(layoutMinimum, window.innerHeight - layoutTop - layoutBottomGap);
                    layout.style.setProperty('--flw-scorm-layout-height', layoutHeight + 'px');
                }
                var tocTop = toc.getBoundingClientRect().top;
                var title = root.querySelector('#scorm_toc_title');
                var titleHeight = title && title.getBoundingClientRect ? title.getBoundingClientRect().height : 0;
                var bottomGap = window.matchMedia('(max-width: 680px)').matches ? 18 : 20;
                var tocHeight = Math.max(180, window.innerHeight - tocTop - bottomGap);
                var treeHeight = Math.max(140, tocHeight - titleHeight - 22);
                toc.style.setProperty('--flw-scorm-toc-max-height', tocHeight + 'px');
                tree.style.setProperty('--flw-scorm-tree-max-height', treeHeight + 'px');
            }

            function startCleanMode() {
                hideReachableScormChrome();
                window.addEventListener('resize', hideReachableScormChrome);
                if (window.MutationObserver && document.body) {
                    var observer = new MutationObserver(function() {
                        hideReachableScormChrome();
                    });
                    observer.observe(document.body, {childList: true, subtree: true});
                }
                window.setTimeout(function() {
                    hideReachableScormChrome();
                }, 250);
                window.setTimeout(function() {
                    hideReachableScormChrome();
                }, 1500);
                var attempts = 0;
                var interval = window.setInterval(function() {
                    attempts += 1;
                    hideReachableScormChrome();
                    if (attempts >= 20) {
                        window.clearInterval(interval);
                    }
                }, 500);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startCleanMode);
            } else {
                startCleanMode();
            }
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
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
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
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

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
    'addblockbutton' => $addblockbutton,
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
$toolsgroup = $OUTPUT->render_from_template('theme_flwacademy/flw_tools_group', $toolscontext);
if ($toolsgroup !== '' && strpos($pagehtml, '</body>') !== false) {
    $pagehtml = str_replace('</body>', $toolsgroup . "\n</body>", $pagehtml);
} else {
    $pagehtml .= $toolsgroup;
}
echo $pagehtml;

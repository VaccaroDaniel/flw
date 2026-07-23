<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script displays a particular page of a quiz attempt that is in progress.
 *
 * @package   mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\output\navigation_panel_attempt;
use mod_quiz\output\renderer;
use mod_quiz\quiz_attempt;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
if (is_readable($CFG->dirroot . '/local/flwplacement/locallib.php')) {
    require_once($CFG->dirroot . '/local/flwplacement/locallib.php');
}

// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
    $isplacementrequest = false;
    $placementquizids = [];
    $placementlanguagecodes = ['en', 'ru', 'zh', 'de', 'ja', 'fr', 'es'];
    foreach ($placementlanguagecodes as $code) {
        $placementquizid = (int)get_config('local_flwplacement', 'quizid_' . $code);
        if ($placementquizid > 0) {
            $placementquizids[] = $placementquizid;
        }
    }
    if (!empty($placementquizids) && $id > 0) {
        if ($cm = get_coursemodule_from_id('quiz', $id, 0, false, IGNORE_MISSING)) {
            $isplacementrequest = in_array((int)$cm->instance, $placementquizids, true);
        }
    }
    $starturl = new moodle_url('/mod/quiz/startattempt.php', [
        'cmid' => $id,
        'sesskey' => sesskey(),
        'flwplacement' => (int)$isplacementrequest,
        'flwautostart' => (int)$isplacementrequest,
        'flwskippreflight' => (int)$isplacementrequest,
        'autostart' => (int)$isplacementrequest,
    ]);
    if (!$isplacementrequest) {
        $starturl->param('redirectto', '1');
    }
    redirect($starturl);
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
    if (!$cm = get_coursemodule_from_instance('quiz', $qid)) {
        throw new \moodle_exception('invalidquizid', 'quiz');
    }
    $isplacementrequest = false;
    $placementquizids = [];
    $placementlanguagecodes = ['en', 'ru', 'zh', 'de', 'ja', 'fr', 'es'];
    foreach ($placementlanguagecodes as $code) {
        $placementquizid = (int)get_config('local_flwplacement', 'quizid_' . $code);
        if ($placementquizid > 0) {
            $placementquizids[] = $placementquizid;
        }
    }
    if (!empty($placementquizids)) {
        $isplacementrequest = in_array((int)$qid, $placementquizids, true);
    }
    redirect(new moodle_url('/mod/quiz/startattempt.php', [
        'cmid' => (int)$cm->id,
        'sesskey' => sesskey(),
        'flwplacement' => (int)$isplacementrequest,
        'flwautostart' => (int)$isplacementrequest,
        'flwskippreflight' => (int)$isplacementrequest,
        'autostart' => (int)$isplacementrequest,
    ]));
}

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);
$flwautostart = optional_param('flwautostart', false, PARAM_BOOL);
$flwplacement = optional_param('flwplacement', false, PARAM_BOOL);
$autostart = optional_param('autostart', false, PARAM_BOOL);
$skippreflight = optional_param('flwskippreflight', false, PARAM_BOOL) || $flwautostart;

$attemptobj = quiz_create_attempt_handling_errors($attemptid, $cmid);
$quizid = $attemptobj->get_quizid();
$isplacementbyquiz = !empty($quizid)
    && function_exists('local_flwplacement_get_quiz_language_for_quiz_id')
    && !empty(local_flwplacement_get_quiz_language_for_quiz_id((int)$quizid));
$isplacementquiz = $flwplacement || $autostart || $flwautostart || $isplacementbyquiz;
$skippreflight = $skippreflight || $isplacementquiz;
$page = $attemptobj->force_page_number_into_range($page);
$PAGE->set_url($attemptobj->attempt_url(null, $page));
// During quiz attempts, the browser back/forwards buttons should force a reload.
$PAGE->set_cacheable(false);

$PAGE->set_secondary_active_tab("modulepage");

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

if ($skippreflight || $isplacementquiz) {
    $PAGE->add_body_class('flw-exam-quiz-page');
    if ($isplacementquiz) {
        $PAGE->add_body_class('flw-placement-quiz-page');
        $PAGE->activityheader->disable();
    }
}

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/quiz:viewreports')) {
        redirect($attemptobj->review_url(null, $page));
    } else {
        throw new moodle_exception('notyourattempt', 'quiz', $attemptobj->view_url());
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
    if (empty($attemptobj->get_quiz()->showblocks)) {
        $PAGE->blocks->show_only_fake_blocks();
    }

} else {
    navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(null, $page));
} else if ($attemptobj->get_state() == quiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
/** @var renderer $output */
$output = $PAGE->get_renderer('mod_quiz');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    throw new \moodle_exception('attempterror', 'quiz', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    if (!$skippreflight) {
        redirect($attemptobj->start_attempt_url(null, $page));
    }
    $accessmanager->notify_preflight_check_passed($attemptobj->get_attemptid());
}

// Set up auto-save if required.
$autosaveperiod = get_config('quiz', 'autosaveperiod');
if ($autosaveperiod) {
    $PAGE->requires->string_for_js('strftimedatetimeshortaccurate', 'langconfig');
    $PAGE->requires->string_for_js('lastautosave', 'quiz');
    $PAGE->requires->yui_module('moodle-mod_quiz-autosave',
            'M.mod_quiz.autosave.init', [$autosaveperiod]);
}

// Log this page view.
$attemptobj->fire_attempt_viewed_event();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_exception('noquestionsfound', 'quiz', $attemptobj->view_url());
}

// Update attempt page, redirecting the user if $page is not valid.
if (!$attemptobj->set_currentpage($page)) {
    redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
}

if ($attemptobj->is_own_preview()) {
    $attemptobj->update_questions_to_new_version_if_changed();
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, quiz_get_js_module());
\core\session\manager::keepalive(); // Try to prevent sessions expiring during quiz attempts.

// Arrange for the navigation to be displayed in the first region on the page.
$navbc = $attemptobj->get_navigation_panel($output, navigation_panel_attempt::class, $page);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$attemptobj->setup_attempt_layout();

$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title($attemptobj->attempt_page_title($page));
$PAGE->set_heading($attemptobj->get_course()->fullname);

if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}

echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);

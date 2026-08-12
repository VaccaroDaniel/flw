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
 * Content designer module content view page.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (!$cm = get_coursemodule_from_id('contentdesigner', $id)) {
    // NOTE this is invalid use of print_error, must be a lang string id.
    throw new moodle_exception('invalidcoursemodule');
}

$PAGE->set_url('/mod/contentdesigner/view.php', ['id' => $cm->id]);

if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
    // Thorw error if the given moulde coure is exists.
    throw new moodle_exception('invalidcourse');
}
require_course_login($course, false, $cm);
if (!$data = $DB->get_record('contentdesigner', ['id' => $cm->instance])) {
    // NOTE As above.
    throw new moodle_exception('course module is incorrect');
}
$context = \context_module::instance($cm->id);

require_capability('mod/contentdesigner:view', $context);
$PAGE->set_title($course->shortname . ': ' . $data->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($data);
$PAGE->add_body_class('limitedwidth');

// Add animation of given elements.
$PAGE->requires->css('/mod/contentdesigner/style/animate.css');

// Completion and trigger events.
contentdesigner_view($data, $course, $cm, $context);

echo $OUTPUT->header();
// Render the page view of the elements.
$editor = new mod_contentdesigner\editor($cm, $course);
$editor->initiate_js();

echo $editor->render_elements();
$PAGE->requires->js_call_amd('mod_contentdesigner/elements', 'animateElements', []);

echo $OUTPUT->footer();

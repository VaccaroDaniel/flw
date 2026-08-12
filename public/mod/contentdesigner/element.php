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
 * Content Designer elements add / edit instance form.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . "/mod/contentdesigner/lib.php");

$id = optional_param('id', 0, PARAM_INT); // Element instance id.
$cmid = required_param('cmid', PARAM_INT); // Course module id.
$element = required_param('element', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);
$chapter = optional_param('chapter', 0, PARAM_INT);
$position = optional_param('position', 'bottom', PARAM_ALPHA);

// Check element exist or not.
$elements = contentdesigner_get_element_pluginnames();
if (!in_array($element, $elements)) {
    throw new moodle_exception('invaildelement', 'mod_contentdesigner');
}

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'contentdesigner');
$context = \context_module::instance($cm->id);

$elementobj = mod_contentdesigner\editor::get_element($element, $cmid);

if ($id) {
    $elementrecord = $DB->get_record("cdelement_" . $element, ['id' => $id]);
    if (!$elementrecord) {
        throw new moodle_exception('invaildrecord', 'mod_contentdesigner');
    }
    $content = $DB->get_record('contentdesigner_content', ['element' => $elementobj->elementid, 'instance' => $id]);
    $chapter = isset($content->chapter) ? $content->chapter : 0;
}

require_login($course, true, $cm);

require_sesskey();

require_capability('mod/contentdesigner:viewcontenteditor', $context);

$record = new stdClass();
$record->course = $course->id;
$record->cmid = $cmid;
$record->element = $element;
$record->contentdesignerid = $cm->instance;

$urlparams = [
    'id' => $id,
    'action' => $action,
    'cmid' => $cmid,
    'element' => $element,
    'sesskey' => sesskey(),
];
$url = new moodle_url('/mod/contentdesigner/element.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($course->shortname . ': ' . get_string('createnewelement', 'contentdesigner'));

$mform = new \mod_contentdesigner\form\general_element_form($PAGE->url->out(false), [
    'element' => $element,
    'context' => $context,
    'instanceid' => $id,
    'cmid' => $cmid,
    'chapterid' => $chapter,
    'position' => $position,
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/contentdesigner/editor.php', ['id' => $cmid, 'sesskey' => sesskey()]));
} else if ($formdata = $mform->get_data()) {
    $formdata->course = $course->id;
    $formdata->cmid = $cm->id;
    $formdata->element = $elementobj->elementid; // ID of the element in elements table.
    $formdata->contextid = $context->id;
    $formdata->instanceid = isset($elementrecord) ? $elementrecord->id : 0;
    $formdata->contentdesignerid = $cm->instance;
    $formdata->elementshortname = $elementobj->shortname;

    $elementobj->update_element($formdata);
    $editorurl = new moodle_url('/mod/contentdesigner/editor.php', ['id' => $cmid, 'sesskey' => sesskey()]);
    redirect($editorurl, get_string('savechanges'), null, \core\output\notification::NOTIFY_INFO);
}

$data = (object) $elementobj->prepare_formdata($id);
$data = $elementobj->prepare_standard_file_editor($data);

$mform->set_data($data);
// PAGE header.
echo $OUTPUT->header();
// Render and Display the add elemet instance form contents.
echo $mform->display();
// Page footer.
echo $OUTPUT->footer();

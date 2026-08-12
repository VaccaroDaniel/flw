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
 * Define for lib functions.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_contentdesigner\editor;
require_once($CFG->dirroot . '/mod/contentdesigner/classes/editor.php');

/**
 * Chapter element shortname.
 */
define('CONTENTDESIGNER_CHAPTER', 'chapter');

/**
 * Add contentdesigner instance.
 * @param stdClass $data
 * @param mod_contentdesigner_mod_form $mform
 * @return int instance id
 */
function contentdesigner_add_instance($data, $mform = null) {
    global$DB;
    contentdesigner_process_pre_save($data);
    $moduleid = $DB->insert_record('contentdesigner', $data);
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'contentdesigner', $moduleid, $completiontimeexpected);
    return $moduleid;
}

/**
 * Runs any processes that must run before a contentdesigner insert/update
 *
 * @param object $data content form data
 * @return void
 **/
function contentdesigner_process_pre_save(&$data) {
    // Whether id exist or not.
    if (!isset($data->id)) {
        $data->timecreated = time();
    }
    $data->timemodified = time();
}

/**
 * Update page instance.
 *
 * @param stdClass $data
 * @param mod_contentdesigner_mod_form $mform
 * @return bool true
 */
function contentdesigner_update_instance($data, $mform) {
    global $DB;
    $data->id = $data->instance;
    contentdesigner_process_pre_save($data);
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'contentdesigner', $data->id, $completiontimeexpected);
    $DB->update_record('contentdesigner', $data);
    return true;
}

/**
 * Delete page instance.
 * @param int $id
 * @return bool true
 */
function contentdesigner_delete_instance($id) {
    global $DB;

    $cm = get_coursemodule_from_instance('contentdesigner', $id);
    if (!$record = $DB->get_record('contentdesigner', ['id' => $cm->instance])) {
        return false;
    }

    \core_completion\api::update_completion_date_event($cm->id, 'contentdesigner', $record->id, null);
    $DB->delete_records('contentdesigner', ['id' => $record->id]);

    $contents = $DB->get_records('contentdesigner_content', ['contentdesignerid' => $record->id]);
    foreach ($contents as $content) {
        $elementobj = editor::get_element($content->element, $cm->id);
        $elementobj->delete_element($content->instance);

        if ($elementobj->get_instance_options($content->instance)) {
            $DB->delete_records('contentdesigner_options', ['element' => $content->element,
                    'instance' => $content->instance]);
        }
    }

    $celements = $DB->get_records('contentdesigner_elements', ['visible' => 1]);
    foreach ($celements as $celement) {
        if ($elementdata = $DB->get_records('cdelement_' . $celement->shortname, ['contentdesignerid' => $record->id])) {
            foreach ($elementdata as $element) {
                $elementobj = editor::get_element($celement->id, $cm->id);
                $elementobj->delete_element($element->id);
                $DB->delete_records('cdelement_' . $celement->shortname, ['contentdesignerid' => $element->contentdesignerid]);
            }
        }
    }

    $DB->delete_records('contentdesigner_content', ['contentdesignerid' => $record->id]);

    return true;
}

/**
 * List of features supported in contentdesigner module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function contentdesigner_supports($feature) {

    // Add for FEATURE_MOD_PURPOSE.
    if (defined('FEATURE_MOD_PURPOSE') && $feature === FEATURE_MOD_PURPOSE) {
        return MOD_PURPOSE_CONTENT;
    }

    switch ($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $data       data object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function contentdesigner_view($data, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $data->id,
    ];

    $event = \mod_contentdesigner\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('contentdesigner', $data);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param object $event
 * @param object $factory
 * @param int $userid
 * @return object|null
 */
function mod_contentdesigner_core_calendar_provide_event_action($event, $factory, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['contentdesigner'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/contentdesigner/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function contentdesigner_reset_userdata($data) {
    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.
    return [];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function contentdesigner_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function contentdesigner_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Get the element plugins.
 *
 * @return array elements
 */
function contentdesigner_get_element_pluginnames() {
    $plugins = core_plugin_manager::instance()->get_plugins_of_type('cdelement');
    return array_keys($plugins);
}

/**
 * Fragment output to load the list of elements to insert.
 *
 * @param array $args Context and cmid.
 * @return string
 */
function contentdesigner_output_fragment_get_elements_list($args) {
    if ($args['cmid']) {
        return \mod_contentdesigner\editor::get_elements_list($args['cmid']);
    }
    throw new moodle_exception('invalidcoursemodule', 'contentdesigner');
}

/**
 * Fragment create instance for element in module content and return the results.
 *
 * @param array $args Context and cmid.
 * @return string
 */
function contentdesigner_output_fragment_insert_element($args) {
    [$course, $cm] = get_course_and_cm_from_cmid($args['cmid'], 'contentdesigner');
    $editor = new mod_contentdesigner\editor($cm, $course);
    $chapter = $args['chapter'] ?? 0;
    return $editor->insert_element($args['elementID'], $chapter);
}

/**
 * Fragment output to load the rendered fill elemenents.
 *
 * @param array $args Context and cmid.
 * @return string
 */
function contentdesigner_output_fragment_load_elements($args) {
    [$course, $cm] = get_course_and_cm_from_cmid($args['cmid'], 'contentdesigner');
    $editor = new mod_contentdesigner\editor($cm, $course);
    return $editor->render_elements();
}

/**
 * Prepare the next available chapters to users view after the chapter completed.
 *
 * @param array $args
 * @return bool|string
 */
function contentdesigner_output_fragment_load_next_chapters($args) {
    [$course, $cm] = get_course_and_cm_from_cmid($args['cmid'], 'contentdesigner');
    $completedchapter = $args['chapter'];
    $editor = new mod_contentdesigner\editor($cm, $course);
    if ($editor->chapter->is_chaptercompleted($completedchapter)) {
        return $editor->render_elements($completedchapter);
    }
    return false;
}

/**
 * Fragment output to load the list of elements to insert.
 *
 * @param array $args Context and cmid.
 * @return string|bool
 */
function contentdesigner_output_fragment_edit_element($args) {
    $elementid = $args['elementid'];
    $instanceid = $args['instanceid'];
    $cmid = $args['cmid'];
    $elementobj = mod_contentdesigner\editor::get_element($elementid, $cmid);
    if ($args['action'] == 'delete') {
        return ($elementobj->delete_element($instanceid)) ? "" : false;
    }
}

// Todo: Need to implement the capabilities for all fragment tests.
/**
 * Fragment output to load the list of elements to insert.
 *
 * @param array $args Context and cmid.
 * @return string
 */
function contentdesigner_output_fragment_move_element($args) {
    if (isset($args['context']) && !empty($args['chapterid'])) {
        $editor = editor::get_editor($args['cmid']);
        if ($editor->chapter->update_postion($args['chapterid'], $args['contents'])) {
            return $editor->display();
        }
    }
}

/**
 * Fragment output to load the list of elements to insert.
 *
 * @param array $args Context and cmid.
 * @return string
 */
function contentdesigner_output_fragment_move_chapter($args) {
    if (isset($args['context']) && !empty($args['cmid'])) {
        $editor = editor::get_editor($args['cmid']);
        if ($editor->chapter->move_chapter($args['chapters'])) {
            return $editor->display();
        }
    }
}

/**
 * Fragment output to load the list of elements to insert.
 *
 * @param array $args Context and cmid.
 * @return string
 */
function contentdesigner_output_fragment_update_visibility($args) {
    if (isset($args['context']) && !empty($args['cmid'])) {
        $elementobj = editor::get_element($args['element'], $args['cmid']);
        $elementobj->update_visibility($args['instance'], $args['status']);
    }
}

/**
 * Update the edited title of the elements in the editor page to the respected elements instance.
 *
 * @param string $itemtype Shortname of the element.
 * @param int $itemid Id of the element
 * @param string $itemvalue Updated value
 * @return string Rendered title.
 */
function mod_contentdesigner_inplace_editable($itemtype, $itemid, $itemvalue) {
    global $DB, $PAGE, $CFG;
    require_once($CFG->libdir . '/externallib.php');

    if (strpos($itemtype, 'instance_title') !== false) {
        $element = str_replace(']', '', explode('[', $itemtype)[1]);
        $instanceid = str_replace(']', '', explode('[', $itemtype)[2]);

        if ($DB->get_manager()->table_exists('cdelement_' . $element)) {
            $instance = $DB->get_record('cdelement_' . $element, ['id' => $instanceid]);
            $cm = get_coursemodule_from_instance('contentdesigner', $instance->contentdesignerid);
        }
        if (!isset($cm) || empty($cm)) {
            throw new moodle_exception('elementtablenotexists', 'contentdesigner');
        }

        $element = mod_contentdesigner\editor::get_element($element, $cm->id);
        $record = $element->get_instance($instanceid);

        $PAGE->set_context(context_module::instance($cm->id));

        \external_api::validate_context(context_system::instance());
        // Todo: Need to check capability and table exists.
        $record->title = clean_param($itemvalue, PARAM_NOTAGS);
        $record->timemodified = time();
        $DB->update_record($element->tablename, $record);

        return new \core\output\inplace_editable(
            'mod_contentdesigner',
            $itemtype,
            $element->elementid . $record->id,
            true,
            format_string($record->title),
            $record->title,
            get_string('titleeditable', 'mod_contentdesigner'),
            get_string('newvalue', 'mod_contentdesigner') . format_string($record->title)
        );
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to add module settings to
 */
function contentdesigner_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    global $PAGE;
    if (has_capability('mod/contentdesigner:viewcontenteditor', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/contentdesigner/editor.php', ['id' => $PAGE->cm->id, 'sesskey' => sesskey()]);
        $node->add(
            get_string('contenteditor', 'mod_contentdesigner'), $url, navigation_node::TYPE_SETTING, null, 'editorelement', null
        );
    }
}

/**
 * Serves file from image.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context Context used in the file.
 * @param string $filearea Filearea the file stored
 * @param array $args Arguments
 * @param bool $forcedownload Force download the file instead of display.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function contentdesigner_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    require_login();
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    $areas = contentdesigner_get_element_pluginnames();
    $areas = array_map(function($area) {
        return $area . "elementbg";
    }, $areas);

    // Merge sub elements area files.
    $areas = array_merge($areas, mod_contentdesigner\editor::get_elements_areafiles($context->instanceid));

    if (!in_array($filearea, $areas)) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_contentdesigner', $filearea, $args[0], '/', $args[1]);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, 0, $options);
}

/**
 * Add a get_coursemodule_info function in case any pulse type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function contentdesigner_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, timecreated, timemodified';
    if (!$contentdesigner = $DB->get_record('contentdesigner', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $contentdesigner->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('contentdesigner', $contentdesigner, $coursemodule->id, false);
    }

    return $result;
}

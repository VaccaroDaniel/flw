<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Supported feature list.
 *
 * @param string $feature
 * @return mixed
 */
function flwvrroom_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

/**
 * Add a new FLW VR Room activity.
 *
 * @param stdClass $data
 * @param mod_fLWvrroom_mod_form|null $mform
 * @return int
 */
function flwvrroom_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('flwvrroom', $data);

    flwvrroom_grade_item_update($data);

    return $data->id;
}

/**
 * Update an FLW VR Room activity.
 *
 * @param stdClass $data
 * @param mod_fLWvrroom_mod_form|null $mform
 * @return bool
 */
function flwvrroom_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $DB->update_record('flwvrroom', $data);
    flwvrroom_grade_item_update($data);

    return true;
}

/**
 * Delete an FLW VR Room activity.
 *
 * @param int $id
 * @return bool
 */
function flwvrroom_delete_instance($id) {
    global $DB;

    if (!$flwvrroom = $DB->get_record('flwvrroom', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('flwvrroom_attempts', ['flwvrroomid' => $flwvrroom->id]);
    $DB->delete_records('flwvrroom', ['id' => $flwvrroom->id]);
    flwvrroom_grade_item_delete($flwvrroom);

    return true;
}

/**
 * Returns grade information.
 *
 * @param stdClass $flwvrroom
 * @param int $userid
 * @return stdClass|null
 */
function flwvrroom_get_user_grade($flwvrroom, $userid) {
    global $DB;

    $record = $DB->get_record_sql(
        'SELECT MAX(score) AS score
           FROM {flwvrroom_attempts}
          WHERE flwvrroomid = :flwvrroomid AND userid = :userid',
        ['flwvrroomid' => $flwvrroom->id, 'userid' => $userid]
    );

    if (!$record || $record->score === null) {
        return null;
    }

    return (object) [
        'userid' => $userid,
        'rawgrade' => (float) $record->score,
    ];
}

/**
 * Update Moodle gradebook item or grades.
 *
 * @param stdClass $flwvrroom
 * @param stdClass|array|null $grades
 * @return int
 */
function flwvrroom_grade_item_update($flwvrroom, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $gradeitem = [
        'itemname' => $flwvrroom->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => empty($flwvrroom->grade) ? 100 : $flwvrroom->grade,
        'grademin' => 0,
    ];

    return grade_update('mod/flwvrroom', $flwvrroom->course, 'mod', 'flwvrroom', $flwvrroom->id, 0, $grades, $gradeitem);
}

/**
 * Delete the gradebook item.
 *
 * @param stdClass $flwvrroom
 * @return int
 */
function flwvrroom_grade_item_delete($flwvrroom) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/flwvrroom', $flwvrroom->course, 'mod', 'flwvrroom', $flwvrroom->id, 0, null, ['deleted' => 1]);
}

/**
 * Get completion state for a user.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param int $userid
 * @param bool $type
 * @return bool
 */
function flwvrroom_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
    $bestscore = $DB->get_field_sql(
        'SELECT MAX(score)
           FROM {flwvrroom_attempts}
          WHERE flwvrroomid = :flwvrroomid AND userid = :userid',
        ['flwvrroomid' => $flwvrroom->id, 'userid' => $userid]
    );

    if ($bestscore === false || $bestscore === null) {
        return false;
    }

    return ((int) $bestscore) >= ((int) $flwvrroom->passinggrade);
}

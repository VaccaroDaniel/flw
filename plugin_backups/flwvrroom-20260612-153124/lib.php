<?php
// Core callbacks for FLW VR Room activity module.

defined('MOODLE_INTERNAL') || die();

function flwvrroom_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

function flwvrroom_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    if (empty($data->knowledgepoints)) {
        $data->knowledgepoints = "A1-VOC-FOOD-001\nA1-FUNC-ORDER-001\nA1-LIS-QUESTION-001\nA1-SPK-REPLY-001";
    }
    $id = $DB->insert_record('flwvrroom', $data);
    $data->id = $id;
    flwvrroom_grade_item_update($data);
    return $id;
}

function flwvrroom_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('flwvrroom', $data);
    flwvrroom_grade_item_update($data);
    return true;
}

function flwvrroom_delete_instance($id) {
    global $DB;

    if (!$flwvrroom = $DB->get_record('flwvrroom', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('flwvrroom_attempts', ['flwvrroomid' => $id]);
    $DB->delete_records('flwvrroom', ['id' => $id]);
    flwvrroom_grade_item_delete($flwvrroom);
    return true;
}

function flwvrroom_grade_item_update($flwvrroom, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $flwvrroom->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => isset($flwvrroom->grade) ? (float)$flwvrroom->grade : 100,
        'grademin' => 0,
    ];

    return grade_update('mod/flwvrroom', $flwvrroom->course, 'mod', 'flwvrroom', $flwvrroom->id, 0, $grades, $params);
}

function flwvrroom_grade_item_delete($flwvrroom) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/flwvrroom', $flwvrroom->course, 'mod', 'flwvrroom', $flwvrroom->id, 0, null, ['deleted' => 1]);
}

function flwvrroom_update_grades($flwvrroom, $userid = 0, $nullifnone = true) {
    global $DB;

    $params = ['flwvrroomid' => $flwvrroom->id];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $records = $DB->get_records_sql(
        'SELECT userid, MAX(score) AS score
           FROM {flwvrroom_attempts}
          WHERE flwvrroomid = :flwvrroomid' . $usersql . '
       GROUP BY userid',
        $params
    );

    $grades = [];
    foreach ($records as $record) {
        $grade = new stdClass();
        $grade->userid = $record->userid;
        $grade->rawgrade = $record->score;
        $grades[$record->userid] = $grade;
    }

    if ($userid && empty($grades) && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        $grades[$userid] = $grade;
    }

    flwvrroom_grade_item_update($flwvrroom, $grades);
}

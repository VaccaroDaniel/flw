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

/**
 * Return the built-in room presets for FLW situation practice.
 *
 * @return array
 */
function flwvrroom_get_scenario_presets() {
    return [
        'At the Cafe' => [
            'key' => 'cafe',
            'title' => 'Cafe mission',
            'mission' => 'Find the important cafe objects, listen to the waiter, and choose the best reply.',
            'aria' => 'Interactive cafe practice room',
            'prompt' => 'The waiter asks: "Good morning. What would you like?"',
            'answers' => [
                ['text' => 'I am twelve years old.', 'score' => 0],
                ['text' => 'I would like a coffee, please.', 'score' => 20],
                ['text' => 'The station is on the left.', 'score' => 0],
            ],
            'hotspots' => [
                ['key' => 'waiter', 'label' => 'Waiter', 'score' => 20, 'x' => 72, 'y' => 25],
                ['key' => 'menu', 'label' => 'Menu', 'score' => 15, 'x' => 45, 'y' => 23],
                ['key' => 'cashier', 'label' => 'Cashier', 'score' => 15, 'x' => 64, 'y' => 43],
                ['key' => 'cup', 'label' => 'Cup', 'score' => 15, 'x' => 24, 'y' => 67],
                ['key' => 'table', 'label' => 'Table', 'score' => 15, 'x' => 47, 'y' => 78],
            ],
            'kpcodes' => [
                'A1-VOC-FOOD-001',
                'A1-FUNC-ORDER-001',
                'A1-LIS-QUESTION-001',
                'A1-SPK-REPLY-001',
            ],
        ],
        'In the Classroom' => [
            'key' => 'classroom',
            'title' => 'Classroom mission',
            'mission' => 'Find the classroom objects and choose the best response to the teacher.',
            'aria' => 'Interactive classroom practice room',
            'prompt' => 'The teacher says: "Open your book, please."',
            'answers' => [
                ['text' => 'Here is my passport.', 'score' => 0],
                ['text' => 'Sure. I will open my book.', 'score' => 20],
                ['text' => 'I would like some bread.', 'score' => 0],
            ],
            'hotspots' => [
                ['key' => 'teacher', 'label' => 'Teacher', 'score' => 20, 'x' => 70, 'y' => 27],
                ['key' => 'board', 'label' => 'Board', 'score' => 15, 'x' => 34, 'y' => 24],
                ['key' => 'book', 'label' => 'Book', 'score' => 15, 'x' => 30, 'y' => 69],
                ['key' => 'desk', 'label' => 'Desk', 'score' => 15, 'x' => 53, 'y' => 78],
                ['key' => 'pencil', 'label' => 'Pencil', 'score' => 15, 'x' => 76, 'y' => 68],
            ],
            'kpcodes' => [
                'A1-VOC-CLASSROOM-001',
                'A1-FUNC-INSTRUCTION-001',
                'A1-LIS-CLASSROOM-001',
                'A1-SPK-REPLY-002',
            ],
        ],
        'At the Hotel' => [
            'key' => 'hotel',
            'title' => 'Hotel mission',
            'mission' => 'Check in at the hotel by finding the key objects and choosing the polite reply.',
            'aria' => 'Interactive hotel practice room',
            'prompt' => 'The receptionist asks: "May I have your name, please?"',
            'answers' => [
                ['text' => 'Yes. My name is Maria Tanaka.', 'score' => 20],
                ['text' => 'Two apples, please.', 'score' => 0],
                ['text' => 'The gate is closing.', 'score' => 0],
            ],
            'hotspots' => [
                ['key' => 'receptionist', 'label' => 'Receptionist', 'score' => 20, 'x' => 67, 'y' => 28],
                ['key' => 'passport', 'label' => 'Passport', 'score' => 15, 'x' => 31, 'y' => 66],
                ['key' => 'keycard', 'label' => 'Key card', 'score' => 15, 'x' => 53, 'y' => 61],
                ['key' => 'elevator', 'label' => 'Elevator', 'score' => 15, 'x' => 26, 'y' => 30],
                ['key' => 'room', 'label' => 'Room', 'score' => 15, 'x' => 78, 'y' => 69],
            ],
            'kpcodes' => [
                'A1-VOC-HOTEL-001',
                'A1-FUNC-CHECKIN-001',
                'A1-LIS-PERSONALINFO-001',
                'A1-SPK-NAME-001',
            ],
        ],
        'At the Airport' => [
            'key' => 'airport',
            'title' => 'Airport mission',
            'mission' => 'Prepare for a flight by finding airport objects and answering the check-in question.',
            'aria' => 'Interactive airport practice room',
            'prompt' => 'The staff asks: "Can I see your passport and ticket?"',
            'answers' => [
                ['text' => 'Yes, here they are.', 'score' => 20],
                ['text' => 'I would like a coffee.', 'score' => 0],
                ['text' => 'This is my classroom.', 'score' => 0],
            ],
            'hotspots' => [
                ['key' => 'staff', 'label' => 'Staff', 'score' => 20, 'x' => 68, 'y' => 31],
                ['key' => 'ticket', 'label' => 'Ticket', 'score' => 15, 'x' => 39, 'y' => 66],
                ['key' => 'passport', 'label' => 'Passport', 'score' => 15, 'x' => 25, 'y' => 68],
                ['key' => 'gate', 'label' => 'Gate', 'score' => 15, 'x' => 46, 'y' => 24],
                ['key' => 'luggage', 'label' => 'Luggage', 'score' => 15, 'x' => 74, 'y' => 78],
            ],
            'kpcodes' => [
                'A1-VOC-AIRPORT-001',
                'A1-FUNC-CHECKIN-002',
                'A1-LIS-REQUEST-001',
                'A1-SPK-PRESENTDOCS-001',
            ],
        ],
        'At the Supermarket' => [
            'key' => 'supermarket',
            'title' => 'Supermarket mission',
            'mission' => 'Shop for simple items by finding supermarket objects and choosing the best checkout reply.',
            'aria' => 'Interactive supermarket practice room',
            'prompt' => 'The cashier asks: "Do you need a bag?"',
            'answers' => [
                ['text' => 'Yes, please.', 'score' => 20],
                ['text' => 'My room number is 305.', 'score' => 0],
                ['text' => 'Open your book.', 'score' => 0],
            ],
            'hotspots' => [
                ['key' => 'cashier', 'label' => 'Cashier', 'score' => 20, 'x' => 70, 'y' => 29],
                ['key' => 'basket', 'label' => 'Basket', 'score' => 15, 'x' => 38, 'y' => 72],
                ['key' => 'apple', 'label' => 'Apple', 'score' => 15, 'x' => 28, 'y' => 56],
                ['key' => 'pricetag', 'label' => 'Price tag', 'score' => 15, 'x' => 54, 'y' => 55],
                ['key' => 'shelf', 'label' => 'Shelf', 'score' => 15, 'x' => 26, 'y' => 28],
            ],
            'kpcodes' => [
                'A1-VOC-SHOPPING-001',
                'A1-FUNC-CHECKOUT-001',
                'A1-LIS-YESNO-001',
                'A1-SPK-POLITE-REPLY-001',
            ],
        ],
    ];
}

/**
 * Return one preset, falling back to cafe.
 *
 * @param string $scenario
 * @return array
 */
function flwvrroom_get_scenario_preset($scenario) {
    $presets = flwvrroom_get_scenario_presets();
    if (isset($presets[$scenario])) {
        return $presets[$scenario];
    }
    return $presets['At the Cafe'];
}

/**
 * Create a stable key from teacher-entered hotspot text.
 *
 * @param string $text
 * @return string
 */
function flwvrroom_clean_key($text) {
    $key = strtolower(trim($text));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key);
    $key = trim($key, '-');
    return $key !== '' ? $key : 'hotspot';
}

/**
 * Parse teacher-entered answer lines.
 *
 * Format: answer text|score. A leading * also marks the answer as correct.
 *
 * @param string $text
 * @return array
 */
function flwvrroom_parse_custom_answers($text) {
    $answers = [];
    $lines = preg_split('/\R+/', trim((string) $text));

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $iscorrect = false;
        if (strpos($line, '*') === 0) {
            $iscorrect = true;
            $line = trim(substr($line, 1));
        }

        $parts = array_map('trim', explode('|', $line));
        $answertext = $parts[0] ?? '';
        if ($answertext === '') {
            continue;
        }

        $score = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : ($iscorrect ? 20 : 0);
        $answers[] = [
            'text' => $answertext,
            'score' => max(0, min(100, $score)),
        ];
    }

    return $answers;
}

/**
 * Parse teacher-entered hotspot lines.
 *
 * Format: key|label|score|x|y.
 *
 * @param string $text
 * @return array
 */
function flwvrroom_parse_custom_hotspots($text) {
    $hotspots = [];
    $lines = preg_split('/\R+/', trim((string) $text));

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 5) {
            continue;
        }

        $key = flwvrroom_clean_key($parts[0]);
        $label = $parts[1] !== '' ? $parts[1] : $key;
        $score = is_numeric($parts[2]) ? (int) $parts[2] : 10;
        $x = is_numeric($parts[3]) ? (float) $parts[3] : 50;
        $y = is_numeric($parts[4]) ? (float) $parts[4] : 50;

        $hotspots[] = [
            'key' => $key,
            'label' => $label,
            'score' => max(0, min(100, $score)),
            'x' => max(0, min(100, $x)),
            'y' => max(0, min(100, $y)),
        ];
    }

    return $hotspots;
}

/**
 * Apply teacher-entered custom room overrides to a preset.
 *
 * @param array $preset
 * @param stdClass $flwvrroom
 * @return array
 */
function flwvrroom_apply_custom_room(array $preset, stdClass $flwvrroom) {
    if (empty($flwvrroom->customsceneenabled)) {
        return $preset;
    }

    $title = trim((string) ($flwvrroom->custommissiontitle ?? ''));
    if ($title !== '') {
        $preset['title'] = $title;
    }

    $mission = trim((string) ($flwvrroom->custommissiontext ?? ''));
    if ($mission !== '') {
        $preset['mission'] = $mission;
    }

    $prompt = trim((string) ($flwvrroom->customquizquestion ?? ''));
    if ($prompt !== '') {
        $preset['prompt'] = $prompt;
    }

    $answers = flwvrroom_parse_custom_answers($flwvrroom->customanswers ?? '');
    if (!empty($answers)) {
        $preset['answers'] = $answers;
    }

    $hotspots = flwvrroom_parse_custom_hotspots($flwvrroom->customhotspots ?? '');
    if (!empty($hotspots)) {
        $preset['hotspots'] = $hotspots;
    }

    $backgroundurl = trim((string) ($flwvrroom->custombackgroundurl ?? ''));
    if ($backgroundurl !== '') {
        $preset['backgroundurl'] = clean_param($backgroundurl, PARAM_URL);
    }

    return $preset;
}

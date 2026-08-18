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
 * File manager options for uploaded 3D models.
 *
 * @param stdClass|null $course
 * @return array
 */
function flwvrroom_model3d_filemanager_options($course = null) {
    global $CFG;

    return [
        'subdirs' => true,
        'maxbytes' => $course->maxbytes ?? $CFG->maxbytes,
        'maxfiles' => -1,
        'accepted_types' => '*',
    ];
}

/**
 * File manager options for uploaded role character 3D models.
 *
 * @param stdClass|null $course
 * @return array
 */
function flwvrroom_rolecharacter_filemanager_options($course = null) {
    return flwvrroom_model3d_filemanager_options($course);
}

/**
 * Save uploaded files from a form draft area.
 *
 * @param stdClass $data
 * @param string $formfield
 * @param string $filearea
 */
function flwvrroom_save_filearea_files(stdClass $data, $formfield, $filearea) {
    if (!isset($data->{$formfield}) || empty($data->coursemodule)) {
        return;
    }

    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files(
        $data->{$formfield},
        $context->id,
        'mod_flwvrroom',
        $filearea,
        0,
        flwvrroom_model3d_filemanager_options()
    );
}

/**
 * Save uploaded 3D model files from a form draft area.
 *
 * @param stdClass $data
 */
function flwvrroom_save_model3d_files(stdClass $data) {
    flwvrroom_save_filearea_files($data, 'model3dfiles', 'model3d');
}

/**
 * Save uploaded role character 3D model files from a form draft area.
 *
 * @param stdClass $data
 */
function flwvrroom_save_rolecharacter_files(stdClass $data) {
    flwvrroom_save_filearea_files($data, 'rolecharacterfiles', 'rolecharacter3d');
}

/**
 * Return the first uploaded GLB/GLTF model URL for a file area.
 *
 * @param context_module $context
 * @param string $filearea
 * @return moodle_url|null
 */
function flwvrroom_get_filearea_model_url(context_module $context, $filearea) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_flwvrroom', $filearea, 0, 'filepath, filename', false);

    foreach ($files as $file) {
        $filename = $file->get_filename();
        if (!preg_match('/\.(glb|gltf)$/i', $filename)) {
            continue;
        }

        return moodle_url::make_pluginfile_url(
            $context->id,
            'mod_flwvrroom',
            $filearea,
            0,
            $file->get_filepath(),
            $filename
        );
    }

    return null;
}

/**
 * Return the first uploaded GLB/GLTF model URL for the room.
 *
 * @param context_module $context
 * @return moodle_url|null
 */
function flwvrroom_get_model3d_url(context_module $context) {
    return flwvrroom_get_filearea_model_url($context, 'model3d');
}

/**
 * Return the first uploaded GLB/GLTF model URL for the role character.
 *
 * @param context_module $context
 * @return moodle_url|null
 */
function flwvrroom_get_rolecharacter_model_url(context_module $context) {
    return flwvrroom_get_filearea_model_url($context, 'rolecharacter3d');
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
    flwvrroom_save_model3d_files($data);
    flwvrroom_save_rolecharacter_files($data);

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
    flwvrroom_save_model3d_files($data);
    flwvrroom_save_rolecharacter_files($data);
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

    $cm = get_coursemodule_from_instance('flwvrroom', $flwvrroom->id, 0, false, IGNORE_MISSING);
    if ($cm) {
        $fs = get_file_storage();
        $context = context_module::instance($cm->id);
        $fs->delete_area_files($context->id, 'mod_flwvrroom', 'model3d');
        $fs->delete_area_files($context->id, 'mod_flwvrroom', 'rolecharacter3d');
    }

    $DB->delete_records('flwvrroom_attempts', ['flwvrroomid' => $flwvrroom->id]);
    $DB->delete_records('flwvrroom', ['id' => $flwvrroom->id]);
    flwvrroom_grade_item_delete($flwvrroom);

    return true;
}

/**
 * Serve uploaded 3D model files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function flwvrroom_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    if (!has_capability('mod/flwvrroom:view', $context)) {
        return false;
    }

    if (!in_array($filearea, ['model3d', 'rolecharacter3d'], true)) {
        return false;
    }

    $itemid = array_shift($args);
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/mod_flwvrroom/{$filearea}/{$itemid}/{$relativepath}";

    $fs = get_file_storage();
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
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
            'title' => 'AI Waiter Cafe Mission',
            'mission' => 'Order a drink from the waiter, answer follow-up questions, and use polite cafe language.',
            'aria' => 'Interactive cafe practice room',
            'prompt' => 'The waiter asks: "Good morning. What would you like?"',
            'answers' => [
                ['text' => 'I am twelve years old.', 'score' => 0],
                ['text' => 'I would like a coffee, please.', 'score' => 20],
                ['text' => 'The station is on the left.', 'score' => 0],
            ],
            'hotspots' => [
                ['key' => 'waiter', 'label' => 'Waiter', 'score' => 20, 'x' => 72, 'y' => 25, 'description' => 'Listen for a polite ordering question and answer with a simple request.'],
                ['key' => 'menu', 'label' => 'Menu', 'score' => 15, 'x' => 45, 'y' => 23, 'description' => 'Use food and drink words to choose what you want.'],
                ['key' => 'cashier', 'label' => 'Cashier', 'score' => 15, 'x' => 64, 'y' => 43, 'description' => 'Practice short checkout phrases such as please and thank you.'],
                ['key' => 'cup', 'label' => 'Cup', 'score' => 15, 'x' => 24, 'y' => 67, 'description' => 'Connect the object with cafe drink vocabulary.'],
                ['key' => 'table', 'label' => 'Table', 'score' => 15, 'x' => 47, 'y' => 78, 'description' => 'Notice the cafe setting and prepare a simple sentence about where you are.'],
            ],
            'kpcodes' => [
                'A1-VOC-FOOD-001',
                'A1-FUNC-ORDER-001',
                'A1-LIS-QUESTION-001',
                'A1-SPK-REPLY-001',
            ],
            'rolecharacter' => [
                'name' => 'Mina',
                'role' => 'Cafe waiter',
                'line' => 'Good morning. Welcome to FLW Cafe. What would you like?',
                'expectedanswer' => 'I would like a coffee, please.',
                'score' => 20,
                'position' => '-2.20|0.00|-2.60',
                'aiturns' => 4,
                'turns' => [
                    'Good morning. Welcome to FLW Cafe. What would you like?|I would like a coffee, please.|20|A1-FUNC-ORDER-001,A1-SPK-REPLY-001',
                    'Sure. Would you like it hot or iced?|Hot, please.|20|A1-LIS-QUESTION-001,A1-SPK-POLITE-REPLY-001',
                    'Would you like milk or sugar?|Milk, please.|20|A1-VOC-FOOD-001,A1-SPK-POLITE-REPLY-001',
                    'That is three dollars. Anything else?|No, thank you.|20|A1-FUNC-CHECKOUT-001,A1-SPK-POLITE-REPLY-001',
                ],
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
                ['key' => 'teacher', 'label' => 'Teacher', 'score' => 20, 'x' => 70, 'y' => 27, 'description' => 'Listen for a classroom instruction and respond politely.'],
                ['key' => 'board', 'label' => 'Board', 'score' => 15, 'x' => 34, 'y' => 24, 'description' => 'Review common classroom objects and locations.'],
                ['key' => 'book', 'label' => 'Book', 'score' => 15, 'x' => 30, 'y' => 69, 'description' => 'Practice understanding open your book and similar commands.'],
                ['key' => 'desk', 'label' => 'Desk', 'score' => 15, 'x' => 53, 'y' => 78, 'description' => 'Connect school furniture words with the room scene.'],
                ['key' => 'pencil', 'label' => 'Pencil', 'score' => 15, 'x' => 76, 'y' => 68, 'description' => 'Practice classroom supply vocabulary.'],
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
                ['key' => 'receptionist', 'label' => 'Receptionist', 'score' => 20, 'x' => 67, 'y' => 28, 'description' => 'Practice giving your name during hotel check-in.'],
                ['key' => 'passport', 'label' => 'Passport', 'score' => 15, 'x' => 31, 'y' => 66, 'description' => 'Connect travel document vocabulary with check-in questions.'],
                ['key' => 'keycard', 'label' => 'Key card', 'score' => 15, 'x' => 53, 'y' => 61, 'description' => 'Learn a common hotel object used after check-in.'],
                ['key' => 'elevator', 'label' => 'Elevator', 'score' => 15, 'x' => 26, 'y' => 30, 'description' => 'Practice simple hotel direction words.'],
                ['key' => 'room', 'label' => 'Room', 'score' => 15, 'x' => 78, 'y' => 69, 'description' => 'Prepare short phrases about room numbers and hotel rooms.'],
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
                ['key' => 'staff', 'label' => 'Staff', 'score' => 20, 'x' => 68, 'y' => 31, 'description' => 'Listen for a document request and answer with here they are.'],
                ['key' => 'ticket', 'label' => 'Ticket', 'score' => 15, 'x' => 39, 'y' => 66, 'description' => 'Practice travel ticket vocabulary.'],
                ['key' => 'passport', 'label' => 'Passport', 'score' => 15, 'x' => 25, 'y' => 68, 'description' => 'Connect passport vocabulary with airport check-in.'],
                ['key' => 'gate', 'label' => 'Gate', 'score' => 15, 'x' => 46, 'y' => 24, 'description' => 'Review airport place words and simple directions.'],
                ['key' => 'luggage', 'label' => 'Luggage', 'score' => 15, 'x' => 74, 'y' => 78, 'description' => 'Practice baggage and travel item vocabulary.'],
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
                ['key' => 'cashier', 'label' => 'Cashier', 'score' => 20, 'x' => 70, 'y' => 29, 'description' => 'Practice answering a yes/no checkout question politely.'],
                ['key' => 'basket', 'label' => 'Basket', 'score' => 15, 'x' => 38, 'y' => 72, 'description' => 'Connect shopping container vocabulary with the scene.'],
                ['key' => 'apple', 'label' => 'Apple', 'score' => 15, 'x' => 28, 'y' => 56, 'description' => 'Review simple food vocabulary.'],
                ['key' => 'pricetag', 'label' => 'Price tag', 'score' => 15, 'x' => 54, 'y' => 55, 'description' => 'Practice price and shopping language.'],
                ['key' => 'shelf', 'label' => 'Shelf', 'score' => 15, 'x' => 26, 'y' => 28, 'description' => 'Notice where items are in a shop and practice location words.'],
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
 * Return first-pass built-in 3D object positions for a scenario.
 *
 * @param string $scenariokey
 * @return array
 */
function flwvrroom_get_builtin3d_positions($scenariokey) {
    $positions = [
        'cafe' => [
            'waiter' => ['x' => -2.2, 'y' => 1.45, 'z' => -2.6],
            'menu' => ['x' => 0.75, 'y' => 1.08, 'z' => -1.15],
            'cashier' => ['x' => 2.6, 'y' => 1.2, 'z' => -2.8],
            'cup' => ['x' => -0.45, 'y' => 1.12, 'z' => -1.05],
            'table' => ['x' => 0, 'y' => 0.8, 'z' => -1.05],
        ],
    ];

    return $positions[$scenariokey] ?? [];
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
 * Format: key|label|score|x|y|description|audio URL|objectX|objectY|objectZ|KP codes|object reference.
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
        $description = $parts[5] ?? '';
        $audiourl = isset($parts[6]) ? clean_param($parts[6], PARAM_URL) : '';
        $objectx = isset($parts[7]) && is_numeric($parts[7]) ? (float) $parts[7] : null;
        $objecty = isset($parts[8]) && is_numeric($parts[8]) ? (float) $parts[8] : null;
        $objectz = isset($parts[9]) && is_numeric($parts[9]) ? (float) $parts[9] : null;
        $kpcodes = [];
        if (!empty($parts[10])) {
            $kpcodes = preg_split('/\s*,\s*/', $parts[10]);
            $kpcodes = array_values(array_filter(array_map('trim', $kpcodes)));
        }
        $objectref = isset($parts[11]) ? clean_param($parts[11], PARAM_TEXT) : '';

        $hotspot = [
            'key' => $key,
            'label' => $label,
            'score' => max(0, min(100, $score)),
            'x' => max(0, min(100, $x)),
            'y' => max(0, min(100, $y)),
            'description' => $description,
            'audiourl' => $audiourl,
            'kpcodes' => $kpcodes,
            'objectref' => $objectref,
        ];

        if ($objectx !== null && $objecty !== null && $objectz !== null) {
            $hotspot['objectx'] = $objectx;
            $hotspot['objecty'] = $objecty;
            $hotspot['objectz'] = $objectz;
        }

        $hotspots[] = $hotspot;
    }

    return $hotspots;
}

/**
 * Parse teacher-entered role-play turns.
 *
 * Format: character line|expected learner answer|score|KP codes
 *
 * @param string $text
 * @param string $fallbackline
 * @param string $fallbackanswer
 * @param int $fallbackscore
 * @param array $fallbackkpcodes
 * @return array
 */
function flwvrroom_parse_role_turns($text, $fallbackline, $fallbackanswer, $fallbackscore, array $fallbackkpcodes) {
    $turns = [];
    $lines = preg_split('/\R+/', trim((string) $text));

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        $characterline = $parts[0] ?? '';
        if ($characterline === '') {
            continue;
        }

        $expectedanswer = $parts[1] ?? $fallbackanswer;
        $score = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : $fallbackscore;
        $kpcodes = [];
        if (!empty($parts[3])) {
            $kpcodes = preg_split('/\s*,\s*/', $parts[3]);
            $kpcodes = array_values(array_filter(array_map('trim', $kpcodes)));
        }
        if (empty($kpcodes)) {
            $kpcodes = $fallbackkpcodes;
        }

        $turns[] = [
            'line' => $characterline,
            'expectedanswer' => $expectedanswer,
            'score' => max(0, $score),
            'kpcodes' => $kpcodes,
        ];
    }

    if (empty($turns)) {
        $turns[] = [
            'line' => $fallbackline,
            'expectedanswer' => $fallbackanswer,
            'score' => max(0, $fallbackscore),
            'kpcodes' => $fallbackkpcodes,
        ];
    }

    return $turns;
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

<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'questions' => 1008,
        'random-slots' => 72,
        'placement-random-slots' => 30,
        'trim-random-slots' => false,
        'language' => '',
        'only' => 'all',
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    cli_error("Unknown option(s):\n  " . implode("\n  ", $unrecognized));
}

if (!empty($options['help'])) {
    echo "Build Moodle Quiz source courses for FLW Placement and Exam.\n\n";
    echo "Each generated course uses the single activity course format with one Quiz activity.\n";
    echo "The question bank is filled with Moodle multichoice questions, and the Quiz\n";
    echo "uses random slots so learners receive a manageable sampled attempt.\n\n";
    echo "Options:\n";
    echo "  --questions=N       Questions to keep in each source bank. Must be > 1000. Default: 1008.\n";
    echo "  --random-slots=N    Random questions shown in each Exam Quiz attempt. Default: 72.\n";
    echo "  --placement-random-slots=N Random questions shown in each Placement Test Quiz attempt. Default: 30.\n";
    echo "  --trim-random-slots Remove extra existing random slots beyond the target count.\n";
    echo "  --language=CODE     Limit to one language: en, ru, zh, de, ja, fr, es.\n";
    echo "  --only=TYPE         all, placement, or exam. Default: all.\n";
    echo "  --help, -h          Show this help.\n";
    exit(0);
}

if (!$DB->get_manager()->table_exists('quiz')) {
    cli_error('The Moodle Quiz module table is not available.');
}

$questiontarget = (int)$options['questions'];
if ($questiontarget <= 1000) {
    cli_error('--questions must be greater than 1000.');
}

$randomslots = (int)$options['random-slots'];
if ($randomslots < 1) {
    cli_error('--random-slots must be at least 1.');
}
$placementrandomslots = (int)$options['placement-random-slots'];
if ($placementrandomslots < 1) {
    cli_error('--placement-random-slots must be at least 1.');
}
$trimrandomslots = !empty($options['trim-random-slots']);

$only = clean_param((string)$options['only'], PARAM_ALPHA);
if (!in_array($only, ['all', 'placement', 'exam'], true)) {
    cli_error('--only must be all, placement, or exam.');
}

$languages = local_flwexam_quizsource_languages();
$languagefilter = clean_param((string)$options['language'], PARAM_ALPHANUMEXT);
if ($languagefilter !== '') {
    if (!isset($languages[$languagefilter])) {
        cli_error('--language must be one of: ' . implode(', ', array_keys($languages)));
    }
    $languages = [$languagefilter => $languages[$languagefilter]];
}

$admin = get_admin();
\core\session\manager::set_user($admin);
$PAGE->set_context(context_system::instance());

$summary = [
    'categoriescreated' => 0,
    'coursescreated' => 0,
    'coursesupdated' => 0,
    'quizzescreated' => 0,
    'quizzesupdated' => 0,
    'questionscreated' => 0,
    'randomslotscreated' => 0,
    'randomslotstrimmed' => 0,
    'placementconfigs' => 0,
    'examrecordscreated' => 0,
    'examrecordsupdated' => 0,
];

$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

foreach ($languages as $languagecode => $language) {
    cli_writeln('Processing ' . $language['label'] . '...');
    $topcategoryid = local_flwexam_quizsource_ensure_language_category($language, $summary);

    if ($only === 'all' || $only === 'placement') {
        $placementcategoryid = local_flwexam_quizsource_ensure_course_category(
            'Placement',
            $topcategoryid,
            'flw-placement-' . $languagecode,
            $summary
        );
        $shortname = 'FLW-PLACEMENT-' . strtoupper($languagecode);
        $fullname = $language['label'] . ' Placement Test';
        $course = local_flwexam_quizsource_ensure_course(
            $placementcategoryid,
            $shortname,
            $fullname,
            'Placement quiz-source course for ' . $language['label'] . '.',
            $summary
        );
        $quiz = local_flwexam_quizsource_ensure_quiz(
            $course,
            $fullname . ' Quiz',
            'Moodle Quiz source for the FLW Placement Test.',
            $summary
        );

        $questiondata = local_flwexam_quizsource_seed_placement_questions(
            $quiz,
            $languagecode,
            $language,
            $levels,
            $questiontarget,
            $summary
        );
        if ($trimrandomslots) {
            $summary['randomslotstrimmed'] += local_flwexam_quizsource_trim_placement_random_slots(
                $quiz,
                $questiondata,
                $levels,
                $placementrandomslots
            );
        }
        $createdslots = local_flwexam_quizsource_seed_placement_random_slots(
            $quiz,
            $questiondata,
            $levels,
            $placementrandomslots
        );
        $summary['randomslotscreated'] += $createdslots;
        local_flwexam_quizsource_refresh_quiz_grade($quiz);
        set_config('quizid_' . $languagecode, $quiz->id, 'local_flwplacement');
        $summary['placementconfigs']++;
    }

    if ($only === 'all' || $only === 'exam') {
        $examcategoryid = local_flwexam_quizsource_ensure_course_category(
            'Exam',
            $topcategoryid,
            'flw-exam-' . $languagecode,
            $summary
        );
        foreach ($levels as $level) {
            $levelcategoryid = local_flwexam_quizsource_ensure_course_category(
                $level,
                $examcategoryid,
                'flw-exam-' . $languagecode . '-' . strtolower($level),
                $summary
            );
            $shortname = 'FLW-EXAM-' . strtoupper($languagecode) . '-' . $level;
            $fullname = $language['label'] . ' Exam ' . $level;
            $course = local_flwexam_quizsource_ensure_course(
                $levelcategoryid,
                $shortname,
                $fullname,
                'Exam quiz-source course for ' . $language['label'] . ' ' . $level . '.',
                $summary
            );
            $quiz = local_flwexam_quizsource_ensure_quiz(
                $course,
                $fullname . ' Quiz',
                'Moodle Quiz source for the FLW Exam ' . $level . '.',
                $summary
            );

            $questioncategory = local_flwexam_quizsource_seed_exam_questions(
                $quiz,
                $languagecode,
                $language,
                $level,
                $questiontarget,
                $summary
            );
            if ($trimrandomslots) {
                $summary['randomslotstrimmed'] += local_flwexam_quizsource_trim_random_slots(
                    $quiz,
                    $questioncategory,
                    $randomslots
                );
            }
            $createdslots = local_flwexam_quizsource_seed_random_slots(
                $quiz,
                $questioncategory,
                $randomslots
            );
            $summary['randomslotscreated'] += $createdslots;
            local_flwexam_quizsource_refresh_quiz_grade($quiz);
            local_flwexam_quizsource_upsert_exam_record($quiz, $languagecode, $language, $level, $summary);
        }
    }
}

cli_writeln('');
cli_writeln('FLW quiz source build complete.');
foreach ($summary as $key => $value) {
    cli_writeln('  ' . $key . ': ' . $value);
}

/**
 * Return supported FLW language metadata.
 *
 * @return array
 */
function local_flwexam_quizsource_languages(): array {
    return [
        'en' => ['label' => 'English', 'topnames' => ['English']],
        'ru' => ['label' => 'Russian', 'topnames' => ['Russian']],
        'zh' => ['label' => 'Chinese', 'topnames' => ['Chinese', '汉语']],
        'de' => ['label' => 'German', 'topnames' => ['German']],
        'ja' => ['label' => 'Japanese', 'topnames' => ['Japanese']],
        'fr' => ['label' => 'French', 'topnames' => ['French']],
        'es' => ['label' => 'Spanish', 'topnames' => ['Spanish']],
    ];
}

/**
 * Ensure the top-level language course category exists.
 *
 * @param array $language
 * @param array $summary
 * @return int
 */
function local_flwexam_quizsource_ensure_language_category(array $language, array &$summary): int {
    global $DB;

    [$insql, $params] = $DB->get_in_or_equal($language['topnames'], SQL_PARAMS_NAMED);
    $params['parent'] = 0;
    $category = $DB->get_record_sql(
        "SELECT *
           FROM {course_categories}
          WHERE parent = :parent AND name {$insql}
       ORDER BY id ASC",
        $params,
        IGNORE_MULTIPLE
    );
    if ($category) {
        return (int)$category->id;
    }

    $category = core_course_category::create((object)[
        'name' => $language['topnames'][0],
        'parent' => 0,
        'visible' => 1,
    ]);
    $summary['categoriescreated']++;
    return (int)$category->id;
}

/**
 * Ensure a child course category exists.
 *
 * @param string $name
 * @param int $parentid
 * @param string $idnumber
 * @param array $summary
 * @return int
 */
function local_flwexam_quizsource_ensure_course_category(
    string $name,
    int $parentid,
    string $idnumber,
    array &$summary
): int {
    global $DB;

    $category = $DB->get_record('course_categories', [
        'parent' => $parentid,
        'idnumber' => $idnumber,
    ], '*', IGNORE_MISSING);
    if (!$category) {
        $category = $DB->get_record('course_categories', [
            'parent' => $parentid,
            'name' => $name,
        ], '*', IGNORE_MISSING);
    }
    if ($category) {
        return (int)$category->id;
    }

    $category = core_course_category::create((object)[
        'name' => $name,
        'parent' => $parentid,
        'idnumber' => $idnumber,
        'visible' => 1,
    ]);
    $summary['categoriescreated']++;
    return (int)$category->id;
}

/**
 * Ensure a generated single-activity Quiz course exists.
 *
 * @param int $categoryid
 * @param string $shortname
 * @param string $fullname
 * @param string $summarytext
 * @param array $summary
 * @return stdClass
 */
function local_flwexam_quizsource_ensure_course(
    int $categoryid,
    string $shortname,
    string $fullname,
    string $summarytext,
    array &$summary
): stdClass {
    global $DB;

    $course = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);
    if (!$course) {
        $course = create_course((object)[
            'category' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
            'idnumber' => strtolower($shortname),
            'summary' => '<p>' . s($summarytext) . '</p>',
            'summaryformat' => FORMAT_HTML,
            'format' => 'singleactivity',
            'activitytype' => 'quiz',
            'showgrades' => 1,
            'newsitems' => 0,
            'visible' => 1,
            'enablecompletion' => 1,
            'showcompletionconditions' => 1,
            'startdate' => usergetmidnight(time()),
            'enddate' => 0,
        ]);
        $summary['coursescreated']++;
    } else {
        $update = (object)['id' => (int)$course->id];
        $changed = false;
        foreach ([
            'category' => $categoryid,
            'fullname' => $fullname,
            'summary' => '<p>' . s($summarytext) . '</p>',
            'summaryformat' => FORMAT_HTML,
            'format' => 'singleactivity',
            'showgrades' => 1,
            'newsitems' => 0,
            'visible' => 1,
            'enablecompletion' => 1,
        ] as $field => $value) {
            if ((string)($course->$field ?? '') !== (string)$value) {
                $update->$field = $value;
                $changed = true;
            }
        }
        if ($changed) {
            $update->timemodified = time();
            $DB->update_record('course', $update);
            $summary['coursesupdated']++;
            $course = $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);
        }
    }

    local_flwexam_quizsource_set_singleactivity_quiz((int)$course->id);
    rebuild_course_cache((int)$course->id, true);
    return $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);
}

/**
 * Force a course to use singleactivity/quiz options.
 *
 * @param int $courseid
 */
function local_flwexam_quizsource_set_singleactivity_quiz(int $courseid): void {
    global $DB;

    $DB->set_field('course', 'format', 'singleactivity', ['id' => $courseid]);
    $record = $DB->get_record('course_format_options', [
        'courseid' => $courseid,
        'format' => 'singleactivity',
        'sectionid' => 0,
        'name' => 'activitytype',
    ], '*', IGNORE_MISSING);

    if ($record) {
        if ($record->value !== 'quiz') {
            $record->value = 'quiz';
            $DB->update_record('course_format_options', $record);
        }
        return;
    }

    $DB->insert_record('course_format_options', (object)[
        'courseid' => $courseid,
        'format' => 'singleactivity',
        'sectionid' => 0,
        'name' => 'activitytype',
        'value' => 'quiz',
    ]);
}

/**
 * Ensure a Quiz activity exists in the generated course.
 *
 * @param stdClass $course
 * @param string $quizname
 * @param string $intro
 * @param array $summary
 * @return stdClass
 */
function local_flwexam_quizsource_ensure_quiz(
    stdClass $course,
    string $quizname,
    string $intro,
    array &$summary
): stdClass {
    global $DB, $PAGE;

    $quiz = $DB->get_record('quiz', [
        'course' => (int)$course->id,
        'name' => $quizname,
    ], '*', IGNORE_MISSING);

    if (!$quiz) {
        $existing = $DB->get_records('quiz', ['course' => (int)$course->id], 'id ASC', '*', 0, 1);
        if ($existing) {
            $quiz = reset($existing);
            $quiz->name = $quizname;
            $quiz->intro = '<p>' . s($intro) . '</p>';
            $quiz->introformat = FORMAT_HTML;
            $quiz->questionsperpage = 12;
            $quiz->shuffleanswers = 1;
            $quiz->preferredbehaviour = 'deferredfeedback';
            $quiz->timemodified = time();
            $DB->update_record('quiz', $quiz);
            $summary['quizzesupdated']++;
            return $DB->get_record('quiz', ['id' => (int)$quiz->id], '*', MUST_EXIST);
        }
    }

    if ($quiz) {
        return $quiz;
    }

    $PAGE->set_context(context_course::instance((int)$course->id));
    $PAGE->set_course($course);

    $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
    $moduleinfo = (object)array_merge(local_flwexam_quizsource_quiz_defaults(), [
        'modulename' => 'quiz',
        'module' => (int)$module->id,
        'section' => 0,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'showdescription' => 0,
        'cmidnumber' => '',
        'name' => $quizname,
        'introeditor' => [
            'text' => '<p>' . s($intro) . '</p>',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ],
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionunlocked' => 1,
        'completionusegrade' => 1,
        'completionpassgrade' => 0,
        'completionattemptsexhausted' => 0,
        'completionminattemptsenabled' => 0,
        'completionminattempts' => 0,
    ]);
    $moduleinfo = add_moduleinfo($moduleinfo, $course);
    $summary['quizzescreated']++;

    rebuild_course_cache((int)$course->id, true);
    return $DB->get_record('quiz', ['id' => (int)$moduleinfo->instance], '*', MUST_EXIST);
}

/**
 * Quiz settings based on Moodle's test generator defaults.
 *
 * @return array
 */
function local_flwexam_quizsource_quiz_defaults(): array {
    return [
        'timeopen' => 0,
        'timeclose' => 0,
        'timelimit' => 0,
        'overduehandling' => 'autosubmit',
        'graceperiod' => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'canredoquestions' => 0,
        'attempts' => 0,
        'attemptonlast' => 0,
        'grademethod' => QUIZ_GRADEHIGHEST,
        'decimalpoints' => 2,
        'questiondecimalpoints' => -1,
        'attemptduring' => 1,
        'correctnessduring' => 1,
        'maxmarksduring' => 1,
        'marksduring' => 1,
        'specificfeedbackduring' => 1,
        'generalfeedbackduring' => 1,
        'rightanswerduring' => 1,
        'overallfeedbackduring' => 0,
        'attemptimmediately' => 1,
        'correctnessimmediately' => 1,
        'maxmarksimmediately' => 1,
        'marksimmediately' => 1,
        'specificfeedbackimmediately' => 1,
        'generalfeedbackimmediately' => 1,
        'rightanswerimmediately' => 1,
        'overallfeedbackimmediately' => 1,
        'attemptopen' => 1,
        'correctnessopen' => 1,
        'maxmarksopen' => 1,
        'marksopen' => 1,
        'specificfeedbackopen' => 1,
        'generalfeedbackopen' => 1,
        'rightansweropen' => 1,
        'overallfeedbackopen' => 1,
        'attemptclosed' => 1,
        'correctnessclosed' => 1,
        'maxmarksclosed' => 1,
        'marksclosed' => 1,
        'specificfeedbackclosed' => 1,
        'generalfeedbackclosed' => 1,
        'rightanswerclosed' => 1,
        'overallfeedbackclosed' => 1,
        'questionsperpage' => 12,
        'shuffleanswers' => 1,
        'sumgrades' => 0,
        'grade' => 100,
        'quizpassword' => '',
        'subnet' => '',
        'browsersecurity' => '',
        'delay1' => 0,
        'delay2' => 0,
        'showuserpicture' => 0,
        'showblocks' => 0,
        'navmethod' => QUIZ_NAVMETHOD_FREE,
        'allowofflineattempts' => 0,
    ];
}

/**
 * Seed placement questions spread across all CEFR levels.
 *
 * @param stdClass $quiz
 * @param string $languagecode
 * @param array $language
 * @param array $levels
 * @param int $target
 * @param array $summary
 * @return array
 */
function local_flwexam_quizsource_seed_placement_questions(
    stdClass $quiz,
    string $languagecode,
    array $language,
    array $levels,
    int $target,
    array &$summary
): array {
    $parentcategory = local_flwexam_quizsource_ensure_question_category(
        $quiz,
        'FLW Placement Question Bank',
        'flw-placement-' . $languagecode
    );

    $perlevel = intdiv($target, count($levels));
    $remainder = $target % count($levels);
    $result = ['parent' => $parentcategory, 'levels' => []];

    foreach ($levels as $index => $level) {
        $leveltarget = $perlevel + ($index < $remainder ? 1 : 0);
        $category = local_flwexam_quizsource_ensure_question_category(
            $quiz,
            'FLW Placement ' . $level,
            'flw-placement-' . $languagecode . '-' . strtolower($level),
            $parentcategory
        );
        $created = local_flwexam_quizsource_seed_questions_in_category(
            $category,
            'placement',
            $languagecode,
            $language,
            $level,
            $leveltarget
        );
        $summary['questionscreated'] += $created;
        $result['levels'][$level] = $category;
    }

    return $result;
}

/**
 * Seed one exam question bank.
 *
 * @param stdClass $quiz
 * @param string $languagecode
 * @param array $language
 * @param string $level
 * @param int $target
 * @param array $summary
 * @return int
 */
function local_flwexam_quizsource_seed_exam_questions(
    stdClass $quiz,
    string $languagecode,
    array $language,
    string $level,
    int $target,
    array &$summary
): int {
    $category = local_flwexam_quizsource_ensure_question_category(
        $quiz,
        'FLW Exam ' . $level . ' Question Bank',
        'flw-exam-' . $languagecode . '-' . strtolower($level)
    );
    $created = local_flwexam_quizsource_seed_questions_in_category(
        $category,
        'exam',
        $languagecode,
        $language,
        $level,
        $target
    );
    $summary['questionscreated'] += $created;
    return $category;
}

/**
 * Ensure a module-level question category exists.
 *
 * @param stdClass $quiz
 * @param string $name
 * @param string $idnumber
 * @param int|null $parentid
 * @return int
 */
function local_flwexam_quizsource_ensure_question_category(
    stdClass $quiz,
    string $name,
    string $idnumber,
    ?int $parentid = null
): int {
    global $DB;

    $cm = get_coursemodule_from_instance('quiz', (int)$quiz->id, (int)$quiz->course, false, MUST_EXIST);
    $context = context_module::instance((int)$cm->id);
    $top = $DB->get_record('question_categories', [
        'contextid' => (int)$context->id,
        'parent' => 0,
        'name' => 'top',
    ], '*', IGNORE_MISSING);
    if (!$top) {
        $top = (object)[
            'name' => 'top',
            'contextid' => (int)$context->id,
            'info' => '',
            'infoformat' => FORMAT_MOODLE,
            'stamp' => make_unique_id_code(),
            'parent' => 0,
            'sortorder' => 999,
            'idnumber' => null,
        ];
        $top->id = $DB->insert_record('question_categories', $top);
    }

    $parentid = $parentid ?? (int)$top->id;
    $category = $DB->get_record('question_categories', [
        'contextid' => (int)$context->id,
        'idnumber' => $idnumber,
    ], '*', IGNORE_MISSING);
    if (!$category) {
        $category = $DB->get_record('question_categories', [
            'contextid' => (int)$context->id,
            'parent' => $parentid,
            'name' => $name,
        ], '*', IGNORE_MISSING);
    }
    if ($category) {
        return (int)$category->id;
    }

    return (int)$DB->insert_record('question_categories', (object)[
        'name' => $name,
        'contextid' => (int)$context->id,
        'info' => '<p>Generated FLW Moodle Quiz source questions.</p>',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'parent' => $parentid,
        'sortorder' => 999,
        'idnumber' => $idnumber,
    ]);
}

/**
 * Seed generated multichoice questions up to the target count.
 *
 * @param int $categoryid
 * @param string $purpose
 * @param string $languagecode
 * @param array $language
 * @param string $level
 * @param int $target
 * @return int
 */
function local_flwexam_quizsource_seed_questions_in_category(
    int $categoryid,
    string $purpose,
    string $languagecode,
    array $language,
    string $level,
    int $target
): int {
    global $DB;

    $existing = local_flwexam_quizsource_question_count_in_category($categoryid);
    if ($existing >= $target) {
        return 0;
    }

    $created = 0;
    $transaction = $DB->start_delegated_transaction();
    for ($number = $existing + 1; $number <= $target; $number++) {
        $blueprint = local_flwexam_quizsource_question_blueprint($language, $level, $number, $purpose);
        $idnumber = 'flw-' . $purpose . '-' . $languagecode . '-' . strtolower($level) . '-' .
            sprintf('%04d', $number);
        local_flwexam_quizsource_create_multichoice_question($categoryid, $idnumber, $blueprint);
        $created++;
    }
    $transaction->allow_commit();
    return $created;
}

/**
 * Count ready questions in one question category.
 *
 * @param int $categoryid
 * @return int
 */
function local_flwexam_quizsource_question_count_in_category(int $categoryid): int {
    global $DB;

    return (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT qbe.id)
           FROM {question_bank_entries} qbe
           JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
           JOIN {question} q ON q.id = qv.questionid
          WHERE qbe.questioncategoryid = :categoryid
            AND qv.status = :status
            AND q.parent = 0",
        [
            'categoryid' => $categoryid,
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        ]
    );
}

/**
 * Create one multichoice question if the question-bank idnumber is absent.
 *
 * @param int $categoryid
 * @param string $idnumber
 * @param array $blueprint
 * @return int
 */
function local_flwexam_quizsource_create_multichoice_question(
    int $categoryid,
    string $idnumber,
    array $blueprint
): int {
    global $DB, $USER;

    $existingid = $DB->get_field_sql(
        "SELECT q.id
           FROM {question_bank_entries} qbe
           JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
           JOIN {question} q ON q.id = qv.questionid
          WHERE qbe.questioncategoryid = :categoryid
            AND qbe.idnumber = :idnumber
            AND qv.status = :status
       ORDER BY qv.version DESC",
        [
            'categoryid' => $categoryid,
            'idnumber' => $idnumber,
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        ],
        IGNORE_MULTIPLE
    );
    if ($existingid) {
        return (int)$existingid;
    }

    $now = time();
    $question = (object)[
        'parent' => 0,
        'name' => shorten_text($blueprint['name'], 255),
        'questiontext' => '<p>' . s($blueprint['text']) . '</p>',
        'questiontextformat' => FORMAT_HTML,
        'generalfeedback' => '<p>' . s($blueprint['feedback']) . '</p>',
        'generalfeedbackformat' => FORMAT_HTML,
        'defaultmark' => 1,
        'penalty' => 0.3333333,
        'qtype' => 'multichoice',
        'length' => 1,
        'stamp' => make_unique_id_code(),
        'timecreated' => $now,
        'timemodified' => $now,
        'createdby' => (int)$USER->id,
        'modifiedby' => (int)$USER->id,
    ];
    $question->id = $DB->insert_record('question', $question);

    $qbe = (object)[
        'questioncategoryid' => $categoryid,
        'idnumber' => $idnumber,
        'ownerid' => (int)$USER->id,
        'nextversion' => 2,
    ];
    $qbe->id = $DB->insert_record('question_bank_entries', $qbe);

    $DB->insert_record('question_versions', (object)[
        'questionbankentryid' => (int)$qbe->id,
        'version' => 1,
        'questionid' => (int)$question->id,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
    ]);

    $DB->insert_record('qtype_multichoice_options', (object)[
        'questionid' => (int)$question->id,
        'layout' => 0,
        'single' => 1,
        'shuffleanswers' => 1,
        'correctfeedback' => '<p>Correct.</p>',
        'correctfeedbackformat' => FORMAT_HTML,
        'partiallycorrectfeedback' => '<p>Partly correct.</p>',
        'partiallycorrectfeedbackformat' => FORMAT_HTML,
        'incorrectfeedback' => '<p>Review the level descriptor and try again.</p>',
        'incorrectfeedbackformat' => FORMAT_HTML,
        'answernumbering' => 'abc',
        'shownumcorrect' => 0,
        'showstandardinstruction' => 0,
    ]);

    foreach ($blueprint['answers'] as $answer) {
        $DB->insert_record('question_answers', (object)[
            'question' => (int)$question->id,
            'answer' => '<p>' . s($answer['text']) . '</p>',
            'answerformat' => FORMAT_HTML,
            'fraction' => $answer['fraction'],
            'feedback' => '<p>' . s($answer['feedback']) . '</p>',
            'feedbackformat' => FORMAT_HTML,
        ]);
    }

    return (int)$question->id;
}

/**
 * Build one generated question blueprint.
 *
 * @param array $language
 * @param string $level
 * @param int $number
 * @param string $purpose
 * @return array
 */
function local_flwexam_quizsource_question_blueprint(
    array $language,
    string $level,
    int $number,
    string $purpose
): array {
    $skills = ['listening', 'speaking', 'reading', 'writing', 'grammar', 'vocabulary'];
    $contexts = [
        'classroom', 'travel', 'family', 'food', 'shopping', 'school', 'work',
        'health', 'community', 'technology', 'transport', 'housing',
    ];
    $functions = [
        'identify the main idea',
        'choose a natural response',
        'recognize key details',
        'organize a short message',
        'use the correct form',
        'match meaning to context',
    ];
    $descriptors = [
        'A1' => 'basic words, short phrases, and familiar daily situations',
        'A2' => 'simple sentences, routine exchanges, and predictable details',
        'B1' => 'connected ideas, reasons, and common real-life communication',
        'B2' => 'extended meaning, viewpoint, and accurate communication choices',
        'C1' => 'implicit meaning, nuance, and flexible academic or professional language',
        'C2' => 'precise meaning, register, and complex communication choices',
    ];

    $skill = $skills[($number - 1) % count($skills)];
    $context = $contexts[(int)floor(($number - 1) / count($skills)) % count($contexts)];
    $function = $functions[(int)floor(($number - 1) / (count($skills) * count($contexts))) % count($functions)];
    $label = $language['label'];
    $descriptor = $descriptors[$level] ?? $descriptors['A1'];
    $purposeword = $purpose === 'placement' ? 'placement' : 'exam';

    return [
        'name' => 'FLW ' . ucfirst($purposeword) . ' ' . $label . ' ' . $level . ' ' .
            ucfirst($skill) . ' ' . sprintf('%04d', $number),
        'text' => "For {$label} {$level} {$skill}, choose the best action to {$function} in a {$context} situation.",
        'feedback' => "{$label} {$level} checks {$descriptor}.",
        'answers' => [
            [
                'text' => "Use {$descriptor} to {$function}.",
                'fraction' => 1,
                'feedback' => 'This matches the CEFR level and the skill target.',
            ],
            [
                'text' => 'Choose the longest option without checking the context.',
                'fraction' => 0,
                'feedback' => 'Length alone does not show the correct language function.',
            ],
            [
                'text' => 'Ignore the skill and answer from memory only.',
                'fraction' => 0,
                'feedback' => 'The answer must match the skill and situation.',
            ],
            [
                'text' => 'Use advanced language that does not fit the learner level.',
                'fraction' => 0,
                'feedback' => 'The answer must fit the target CEFR level.',
            ],
        ],
    ];
}

/**
 * Seed random slots across placement level categories.
 *
 * @param stdClass $quiz
 * @param array $questiondata
 * @param array $levels
 * @param int $randomslots
 * @return int
 */
function local_flwexam_quizsource_seed_placement_random_slots(
    stdClass $quiz,
    array $questiondata,
    array $levels,
    int $randomslots
): int {
    $created = 0;
    $perlevel = intdiv($randomslots, count($levels));
    $remainder = $randomslots % count($levels);
    foreach ($levels as $index => $level) {
        $target = $perlevel + ($index < $remainder ? 1 : 0);
        $created += local_flwexam_quizsource_seed_random_slots(
            $quiz,
            (int)$questiondata['levels'][$level],
            $target
        );
    }
    return $created;
}

/**
 * Remove extra placement random slots across level categories.
 *
 * @param stdClass $quiz
 * @param array $questiondata
 * @param array $levels
 * @param int $randomslots
 * @return int
 */
function local_flwexam_quizsource_trim_placement_random_slots(
    stdClass $quiz,
    array $questiondata,
    array $levels,
    int $randomslots
): int {
    $trimmed = 0;
    $perlevel = intdiv($randomslots, count($levels));
    $remainder = $randomslots % count($levels);
    foreach ($levels as $index => $level) {
        $target = $perlevel + ($index < $remainder ? 1 : 0);
        $trimmed += local_flwexam_quizsource_trim_random_slots(
            $quiz,
            (int)$questiondata['levels'][$level],
            $target
        );
    }
    return $trimmed;
}

/**
 * Add random slots for a quiz/category up to a target number.
 *
 * @param stdClass $quiz
 * @param int $categoryid
 * @param int $target
 * @return int
 */
function local_flwexam_quizsource_seed_random_slots(stdClass $quiz, int $categoryid, int $target): int {
    if ($target < 1) {
        return 0;
    }

    $existing = local_flwexam_quizsource_count_random_slots($quiz, $categoryid);
    if ($existing >= $target) {
        return 0;
    }

    $needed = $target - $existing;
    $quizobj = \mod_quiz\quiz_settings::create((int)$quiz->id);
    $structure = $quizobj->get_structure();
    $structure->add_random_questions(0, $needed, [
        'filter' => [
            'category' => [
                'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
                'values' => [$categoryid],
                'filteroptions' => ['includesubcategories' => false],
            ],
        ],
    ]);
    return $needed;
}

/**
 * Remove extra random slots for a quiz/category beyond a target number.
 *
 * @param stdClass $quiz
 * @param int $categoryid
 * @param int $target
 * @return int
 */
function local_flwexam_quizsource_trim_random_slots(stdClass $quiz, int $categoryid, int $target): int {
    global $DB;

    $matches = local_flwexam_quizsource_get_random_slot_refs($quiz, $categoryid);
    if (count($matches) <= $target) {
        return 0;
    }

    $remove = array_slice($matches, $target);
    $transaction = $DB->start_delegated_transaction();
    foreach ($remove as $slotref) {
        $DB->delete_records('question_set_references', [
            'id' => (int)$slotref->referenceid,
        ]);
        $DB->delete_records('quiz_slots', [
            'id' => (int)$slotref->slotid,
        ]);
    }
    local_flwexam_quizsource_renumber_quiz_slots((int)$quiz->id);
    quiz_repaginate_questions((int)$quiz->id, (int)($quiz->questionsperpage ?? 12));
    $transaction->allow_commit();

    return count($remove);
}

/**
 * Count random slots already drawing from one category.
 *
 * @param stdClass $quiz
 * @param int $categoryid
 * @return int
 */
function local_flwexam_quizsource_count_random_slots(stdClass $quiz, int $categoryid): int {
    return count(local_flwexam_quizsource_get_random_slot_refs($quiz, $categoryid));
}

/**
 * Get random slot references already drawing from one category.
 *
 * @param stdClass $quiz
 * @param int $categoryid
 * @return array
 */
function local_flwexam_quizsource_get_random_slot_refs(stdClass $quiz, int $categoryid): array {
    global $DB;

    $cm = get_coursemodule_from_instance('quiz', (int)$quiz->id, (int)$quiz->course, false, MUST_EXIST);
    $context = context_module::instance((int)$cm->id);
    $refs = $DB->get_records_sql(
        "SELECT qsr.id, qsr.id AS referenceid, qsr.filtercondition, qs.id AS slotid, qs.slot, qs.page
           FROM {quiz_slots} qs
           JOIN {question_set_references} qsr
             ON qsr.itemid = qs.id
            AND qsr.component = :component
            AND qsr.questionarea = :questionarea
          WHERE qs.quizid = :quizid
            AND qsr.usingcontextid = :contextid
       ORDER BY qs.slot ASC, qs.id ASC",
        [
            'component' => 'mod_quiz',
            'questionarea' => 'slot',
            'quizid' => (int)$quiz->id,
            'contextid' => (int)$context->id,
        ]
    );

    $matches = [];
    foreach ($refs as $ref) {
        $filter = json_decode($ref->filtercondition ?? '[]', true) ?: [];
        $values = $filter['filter']['category']['values'] ?? [];
        if (in_array($categoryid, array_map('intval', $values), true)) {
            $matches[] = $ref;
        }
    }
    return $matches;
}

/**
 * Renumber quiz slots after deleting slots.
 *
 * @param int $quizid
 */
function local_flwexam_quizsource_renumber_quiz_slots(int $quizid): void {
    global $DB;

    $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot ASC, id ASC');
    $slotnumber = 1;
    foreach ($slots as $slot) {
        if ((int)$slot->slot !== $slotnumber) {
            $slot->slot = $slotnumber;
            $DB->update_record('quiz_slots', $slot);
        }
        $slotnumber++;
    }

    $sections = $DB->get_records('quiz_sections', ['quizid' => $quizid], 'firstslot ASC, id ASC');
    $first = true;
    foreach ($sections as $section) {
        if ($first || (int)$section->firstslot < 1 || (int)$section->firstslot >= $slotnumber) {
            $section->firstslot = 1;
            $DB->update_record('quiz_sections', $section);
        }
        $first = false;
    }
}

/**
 * Refresh quiz sumgrades after random slots are added.
 *
 * @param stdClass $quiz
 */
function local_flwexam_quizsource_refresh_quiz_grade(stdClass $quiz): void {
    quiz_delete_previews($quiz);
    \mod_quiz\quiz_settings::create((int)$quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
}

/**
 * Create or update a visible FLW Exam record linked to the Moodle Quiz.
 *
 * @param stdClass $quiz
 * @param string $languagecode
 * @param array $language
 * @param string $level
 * @param array $summary
 */
function local_flwexam_quizsource_upsert_exam_record(
    stdClass $quiz,
    string $languagecode,
    array $language,
    string $level,
    array &$summary
): void {
    global $DB;

    if (!$DB->get_manager()->table_exists('local_flwexam_exams')) {
        return;
    }

    [$threshold, $skillfloor] = local_flwexam_quizsource_thresholds($level);
    $now = time();
    $code = 'FLW-EXAM-' . strtoupper($languagecode) . '-' . $level;
    $record = $DB->get_record('local_flwexam_exams', ['code' => $code], '*', IGNORE_MISSING);
    $data = [
        'code' => $code,
        'name' => $language['label'] . ' Exam ' . $level,
        'language' => $languagecode,
        'learningcoursecategory' => 'exam',
        'cefrlevel' => $level,
        'requiredthreshold' => $threshold,
        'requiredskillfloor' => $skillfloor,
        'moderationrequired' => 0,
        'criticalkpjson' => json_encode([]),
        'profilejson' => json_encode([
            'description' => $language['label'] . ' ' . $level . ' Moodle Quiz exam source.',
            'skills' => ['listening', 'speaking', 'reading', 'writing'],
            'language' => $languagecode,
            'level' => $level,
            'source' => 'moodle_quiz',
        ]),
        'quizid' => (int)$quiz->id,
        'visible' => 1,
        'timemodified' => $now,
    ];

    if ($record) {
        $data['id'] = (int)$record->id;
        $data['timecreated'] = (int)$record->timecreated;
        $DB->update_record('local_flwexam_exams', (object)$data);
        $summary['examrecordsupdated']++;
        return;
    }

    $data['timecreated'] = $now;
    $DB->insert_record('local_flwexam_exams', (object)$data);
    $summary['examrecordscreated']++;
}

/**
 * Return exam threshold settings by level.
 *
 * @param string $level
 * @return array
 */
function local_flwexam_quizsource_thresholds(string $level): array {
    if (in_array($level, ['C1', 'C2'], true)) {
        return [75, 65];
    }
    return [70, 60];
}

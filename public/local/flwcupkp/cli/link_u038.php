<?php
// Discover and link REW U038 C-UP-KP objects to Moodle activities.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

global $USER;
if (!is_siteadmin()) {
    $USER = get_admin();
    \core\session\manager::set_user($USER);
}

[$options] = cli_get_params([
    'discover' => false,
    'create-shell' => false,
    'link' => false,
    'evidence-test' => false,
    'real-quiz-submission-test' => false,
    'status' => false,
    'courseid' => 0,
    'cmid' => 1889,
    'zip' => 'D:/WinPro.Delta/Projects/C-UP-KP/REW3_U038_V31_text_image_moodle_package.zip',
    'userid' => 0,
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || (!$options['discover'] && !$options['create-shell'] && !$options['link'] && !$options['evidence-test'] && !$options['real-quiz-submission-test'] && !$options['status'])) {
    echo "Discover/link REW U038 C-UP-KP records.\n";
    echo "Usage:\n";
    echo "  php local/flwcupkp/cli/link_u038.php --discover [--courseid=ID]\n";
    echo "  php local/flwcupkp/cli/link_u038.php --create-shell [--zip=/path/unit.zip]\n";
    echo "  php local/flwcupkp/cli/link_u038.php --link --courseid=ID\n";
    echo "  php local/flwcupkp/cli/link_u038.php --evidence-test --courseid=ID [--userid=ID]\n";
    echo "  php local/flwcupkp/cli/link_u038.php --real-quiz-submission-test --courseid=ID [--cmid=1889]\n";
    echo "  php local/flwcupkp/cli/link_u038.php --status\n";
    exit(0);
}

if ($options['create-shell']) {
    echo json_encode(create_u038_shell((string)$options['zip']), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['discover']) {
    echo json_encode([
        'candidate_courses' => candidate_courses((int)$options['courseid']),
        'activity_hits' => activity_hits(),
        'question_hits' => global_question_hits(),
        'cupkp_objects' => cupkp_objects(),
    ], JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['link']) {
    $courseid = require_courseid($options);
    echo json_encode(link_course($courseid), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['evidence-test']) {
    $courseid = require_courseid($options);
    echo json_encode(run_evidence_test($courseid, (int)$options['userid']), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['real-quiz-submission-test']) {
    $courseid = require_courseid($options);
    echo json_encode(run_real_quiz_submission_test($courseid, (int)$options['cmid']), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['status']) {
    echo json_encode(status_report(), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

/**
 * Require courseid option.
 *
 * @param array $options
 * @return int
 */
function require_courseid(array $options): int {
    $courseid = (int)$options['courseid'];
    if ($courseid <= 0) {
        cli_error('--courseid is required for this action');
    }
    return $courseid;
}

/**
 * Find candidate courses and modules.
 *
 * @param int $courseid
 * @return array
 */
function candidate_courses(int $courseid = 0): array {
    global $DB;

    if ($courseid > 0) {
        $courses = $DB->get_records('course', ['id' => $courseid]);
    } else {
        $sql = "SELECT id, shortname, fullname, idnumber, visible
                  FROM {course}
                 WHERE LOWER(shortname) LIKE :u038a
                    OR LOWER(fullname) LIKE :u038b
                    OR LOWER(idnumber) LIKE :u038c
                    OR LOWER(shortname) LIKE :rewa
                    OR LOWER(fullname) LIKE :problem
              ORDER BY id";
        $courses = $DB->get_records_sql($sql, [
            'u038a' => '%u038%',
            'u038b' => '%u038%',
            'u038c' => '%u038%',
            'rewa' => '%rew%',
            'problem' => '%problem solving%',
        ]);
    }

    $out = [];
    foreach ($courses as $course) {
        $out[] = [
            'course' => $course,
            'modules' => course_modules_summary((int)$course->id),
            'questions' => question_summary((int)$course->id),
        ];
    }
    return $out;
}

/**
 * Summarize course modules.
 *
 * @param int $courseid
 * @return array
 */
function course_modules_summary(int $courseid): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT cm.id AS cmid, cm.course, cm.module, cm.instance, cm.section, cm.visible, m.name AS modname
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course = :courseid
       ORDER BY cm.section, cm.id",
        ['courseid' => $courseid]
    );

    $out = [];
    foreach ($records as $record) {
        $name = activity_name($record->modname, (int)$record->instance);
        $out[] = [
            'cmid' => (int)$record->cmid,
            'modname' => $record->modname,
            'instance' => (int)$record->instance,
            'section' => (int)$record->section,
            'visible' => (int)$record->visible,
            'name' => $name,
        ];
    }
    return $out;
}

/**
 * Get activity name if the module table has a name field.
 *
 * @param string $modname
 * @param int $instance
 * @return string
 */
function activity_name(string $modname, int $instance): string {
    global $DB;

    try {
        if ($DB->get_manager()->table_exists($modname) && $DB->get_manager()->field_exists($modname, 'name')) {
            return (string)$DB->get_field($modname, 'name', ['id' => $instance], IGNORE_MISSING);
        }
    } catch (Throwable $e) {
        return '';
    }
    return '';
}

/**
 * Summarize questions available in course contexts.
 *
 * @param int $courseid
 * @return array
 */
function question_summary(int $courseid): array {
    global $DB;

    $sql = "SELECT q.id, q.name, q.questiontext, qc.name AS category
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              JOIN {context} ctx ON ctx.id = qc.contextid
             WHERE (ctx.contextlevel = :courselevel AND ctx.instanceid = :courseid)
                OR (ctx.contextlevel = :modulelevel AND ctx.instanceid IN (
                    SELECT cm.id FROM {course_modules} cm WHERE cm.course = :courseid2
                ))
          ORDER BY q.id";

    $questions = $DB->get_records_sql($sql, [
        'courselevel' => CONTEXT_COURSE,
        'courseid' => $courseid,
        'modulelevel' => CONTEXT_MODULE,
        'courseid2' => $courseid,
    ], 0, 80);

    $out = [];
    foreach ($questions as $question) {
        $out[] = [
            'id' => (int)$question->id,
            'name' => $question->name,
            'category' => $question->category,
        ];
    }
    return $out;
}

/**
 * Search common activity tables globally for U038/problem-solving names.
 *
 * @return array
 */
function activity_hits(): array {
    global $DB;

    $hits = [];
    foreach (['scorm', 'quiz', 'page', 'assign', 'lesson', 'book', 'resource', 'url', 'h5pactivity'] as $table) {
        if (!$DB->get_manager()->table_exists($table) || !$DB->get_manager()->field_exists($table, 'name')) {
            continue;
        }
        $sql = "SELECT id, course, name
                  FROM {{$table}}
                 WHERE LOWER(name) LIKE :u038
                    OR LOWER(name) LIKE :unit38
                    OR LOWER(name) LIKE :problem
              ORDER BY id";
        $records = $DB->get_records_sql($sql, [
            'u038' => '%u038%',
            'unit38' => '%unit 38%',
            'problem' => '%problem solving%',
        ], 0, 50);
        foreach ($records as $record) {
            $cmid = $DB->get_field_sql(
                "SELECT cm.id
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module
                  WHERE cm.course = :course
                    AND cm.instance = :instance
                    AND m.name = :modname",
                ['course' => $record->course, 'instance' => $record->id, 'modname' => $table],
                IGNORE_MULTIPLE
            );
            $hits[] = [
                'table' => $table,
                'id' => (int)$record->id,
                'course' => (int)$record->course,
                'cmid' => $cmid ? (int)$cmid : null,
                'name' => $record->name,
            ];
        }
    }
    return $hits;
}

/**
 * Search question names globally.
 *
 * @return array
 */
function global_question_hits(): array {
    global $DB;

    $sql = "SELECT q.id, q.name, qc.name AS category
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE LOWER(q.name) LIKE :u038
                OR LOWER(q.name) LIKE :q001
                OR LOWER(q.name) LIKE :problem
          ORDER BY q.id";
    $questions = $DB->get_records_sql($sql, [
        'u038' => '%u038%',
        'q001' => '%q001%',
        'problem' => '%problem solving%',
    ], 0, 80);

    $out = [];
    foreach ($questions as $question) {
        $out[] = [
            'id' => (int)$question->id,
            'name' => $question->name,
            'category' => $question->category,
        ];
    }
    return $out;
}

/**
 * Imported C-UP-KP objects for U038.
 *
 * @return array
 */
function cupkp_objects(): array {
    global $DB;

    return array_values($DB->get_records_select(
        'flwcupkp_object',
        $DB->sql_like('externalid', ':pattern', false),
        ['pattern' => 'REW-U038-%'],
        'externalid ASC',
        'id, externalid, title, unitcode, lesson, objecttype, courseid, cmid, sourceid, metadatajson'
    ));
}

/**
 * Link imported objects to course modules.
 *
 * @param int $courseid
 * @return array
 */
function link_course(int $courseid): array {
    global $DB;

    $modules = course_modules_summary($courseid);
    $objects = cupkp_objects();
    $updates = [];
    $unmatched = [];

    foreach ($objects as $object) {
        $match = best_module_match($object, $modules);
        if ($match === null) {
            $unmatched[] = $object->externalid;
            continue;
        }

        $record = (object)[
            'id' => $object->id,
            'courseid' => $courseid,
            'cmid' => $match['cmid'],
            'metadatajson' => json_encode(array_merge(json_decode((string)($object->metadatajson ?? ''), true) ?: [], [
                'linked_by' => 'local_flwcupkp cli/link_u038.php',
                'linked_at' => time(),
                'matched_activity_name' => $match['name'],
                'matched_modname' => $match['modname'],
                'matched_instance' => $match['instance'],
                'question_ids' => question_ids_for_cmid((int)$match['cmid']),
            ])),
        ];
        $DB->update_record('flwcupkp_object', $record);
        $updates[] = [
            'object_externalid' => $object->externalid,
            'object_title' => $object->title,
            'cmid' => $match['cmid'],
            'modname' => $match['modname'],
            'activity_name' => $match['name'],
        ];
    }

    \local_flwcupkp\local\repository::audit('u038_objects_linked', 'course', $courseid, [
        'updates' => count($updates),
        'unmatched' => $unmatched,
    ]);

    return ['courseid' => $courseid, 'updates' => $updates, 'unmatched' => $unmatched];
}

/**
 * Create a Moodle-native shell course for U038 from the reference package.
 *
 * @param string $zippath
 * @return array
 */
function create_u038_shell(string $zippath): array {
    global $DB;

    if (!is_readable($zippath)) {
        cli_error('Unit ZIP is not readable: ' . $zippath);
    }

    $profile = json_decode(zip_entry_text($zippath, 'data/unit_profile.json'), true);
    if (!is_array($profile)) {
        cli_error('Could not read data/unit_profile.json from ZIP.');
    }

    $course = get_or_create_u038_course($profile);
    course_create_sections_if_missing($course, range(0, 8));

    $lessons = [
        1 => ['name' => 'Lesson 1 - Words for Problems and Solutions', 'type' => 'quiz', 'csvlesson' => 'Vocabulary'],
        2 => ['name' => 'Lesson 2 - Cause, Advice, and Options', 'type' => 'quiz', 'csvlesson' => ['Grammar', 'Use of English']],
        3 => ['name' => 'Lesson 3 - Read the Solution Path', 'type' => 'quiz', 'csvlesson' => 'Reading'],
        4 => ['name' => 'Lesson 4 - Listen for Problem, Option, and Next Step', 'type' => 'page'],
        5 => ['name' => 'Lesson 5 - Discuss Options Without Blaming', 'type' => 'page'],
        6 => ['name' => 'Lesson 6 - Write a Solution Note', 'type' => 'page'],
        7 => ['name' => 'Lesson 7 - Problem-Solving Project', 'type' => 'page'],
    ];

    $quizrows = csv_rows(zip_entry_text($zippath, 'data/U038_Quiz_Corpus_Traceable.csv'));
    $created = [];

    foreach ($lessons as $sectionnum => $lesson) {
        update_u038_section($course, $sectionnum, $lesson['name']);
        $existing = find_cmid_by_activity_name((int)$course->id, $lesson['name']);
        if ($existing) {
            $created[] = ['section' => $sectionnum, 'name' => $lesson['name'], 'cmid' => $existing, 'status' => 'existing'];
            continue;
        }

        if ($lesson['type'] === 'quiz') {
            $selected = filter_quiz_rows($quizrows, $lesson['csvlesson'], 12);
            $quizxml = make_temp_directory('flwcupkp') . '/u038_lesson_' . $sectionnum . '_quiz.xml';
            build_shortanswer_quiz_xml($selected, $quizxml);
            $quiz = add_u038_quiz($course, $sectionnum, $lesson['name'], '<p>C-UP-KP traceable quiz generated from U038 corpus.</p>');
            $questionids = import_quiz_xml_to_u038_quiz($course, $quiz['quiz'], $quizxml);
            $created[] = [
                'section' => $sectionnum,
                'name' => $lesson['name'],
                'cmid' => $quiz['cmid'],
                'status' => 'created',
                'questionids' => $questionids,
            ];
        } else {
            $content = '<h3>' . s($lesson['name']) . '</h3><p>' . s($profile['unit_aim'] ?? '') . '</p>';
            $cmid = add_u038_page($course, $sectionnum, $lesson['name'], $content);
            $created[] = ['section' => $sectionnum, 'name' => $lesson['name'], 'cmid' => $cmid, 'status' => 'created'];
        }
    }

    $link = link_course((int)$course->id);
    rebuild_course_cache((int)$course->id, true);

    return [
        'courseid' => (int)$course->id,
        'shortname' => $course->shortname,
        'activities' => $created,
        'link_result' => $link,
    ];
}

/**
 * Get or create the U038 course.
 *
 * @param array $profile
 * @return stdClass
 */
function get_or_create_u038_course(array $profile): stdClass {
    global $DB;

    $shortname = 'FLW-REW-U038-CUPKP';
    if ($course = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING)) {
        return $course;
    }

    $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
    if ($english = $DB->get_record_select('course_categories', $DB->sql_like('name', ':name', false), ['name' => '%English%'], 'id', IGNORE_MULTIPLE)) {
        $categoryid = (int)$english->id;
    }

    $course = (object)[
        'fullname' => 'Real English World - Unit 38 Problem Solving',
        'shortname' => $shortname,
        'idnumber' => 'REW-U038-CUPKP',
        'category' => $categoryid,
        'summary' => '<p>' . s($profile['unit_aim'] ?? 'Problem Solving') . '</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 8,
        'startdate' => time(),
        'visible' => 1,
    ];
    return create_course($course);
}

/**
 * Update section name.
 *
 * @param stdClass $course
 * @param int $sectionnum
 * @param string $name
 */
function update_u038_section(stdClass $course, int $sectionnum, string $name): void {
    global $DB;

    course_create_sections_if_missing($course, [$sectionnum]);
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
    $section->name = $name;
    $section->summary = '';
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 1;
    $DB->update_record('course_sections', $section);
}

/**
 * Add a page module.
 *
 * @param stdClass $course
 * @param int $section
 * @param string $name
 * @param string $content
 * @return int
 */
function add_u038_page(stdClass $course, int $section, string $name, string $content): int {
    global $DB;

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'page';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->name = $name;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->intro = '<p>C-UP-KP mapped U038 learning object.</p>';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->content = $content;
    $moduleinfo->contentformat = FORMAT_HTML;
    $moduleinfo->display = RESOURCELIB_DISPLAY_OPEN;
    $moduleinfo->printintro = 0;
    $moduleinfo->printlastmodified = 0;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    $moduleinfo->completion = 0;
    $moduleinfo->completionview = 0;
    $moduleinfo->completionexpected = 0;
    $moduleinfo->completionunlocked = 1;
    $cm = add_moduleinfo($moduleinfo, $course);
    return (int)$cm->coursemodule;
}

/**
 * Add a quiz module.
 *
 * @param stdClass $course
 * @param int $section
 * @param string $name
 * @param string $intro
 * @return array
 */
function add_u038_quiz(stdClass $course, int $section, string $name, string $intro): array {
    global $DB;

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->name = $name;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->intro = $intro;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->timelimit = 0;
    $moduleinfo->overduehandling = 'autosubmit';
    $moduleinfo->graceperiod = 0;
    $moduleinfo->grade = 12;
    $moduleinfo->decimalpoints = 2;
    $moduleinfo->questiondecimalpoints = -1;
    $moduleinfo->attempts = 0;
    $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
    $moduleinfo->questionsperpage = 4;
    $moduleinfo->navmethod = QUIZ_NAVMETHOD_FREE;
    $moduleinfo->shuffleanswers = 0;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->browsersecurity = '-';
    $moduleinfo->quizpassword = '';
    $moduleinfo->subnet = '';
    $moduleinfo->allowofflineattempts = 0;
    foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $field) {
        $moduleinfo->{$field . 'during'} = 0;
        $moduleinfo->{$field . 'immediately'} = 1;
        $moduleinfo->{$field . 'open'} = 1;
        $moduleinfo->{$field . 'closed'} = 1;
    }
    $moduleinfo->attemptduring = 1;
    $moduleinfo->overallfeedbackduring = 0;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    $moduleinfo->completion = 0;
    $moduleinfo->completionview = 0;
    $moduleinfo->completionexpected = 0;
    $moduleinfo->completiongradeitemnumber = null;
    $moduleinfo->completionunlocked = 1;
    $moduleinfo->completionusegrade = 0;
    $moduleinfo->completionpassgrade = 0;
    $moduleinfo->completionattemptsexhausted = 0;
    $moduleinfo->completionminattemptsenabled = 0;
    $moduleinfo->completionminattempts = 0;
    $cm = add_moduleinfo($moduleinfo, $course);
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $quiz->cmid = (int)$cm->coursemodule;
    return ['cmid' => (int)$cm->coursemodule, 'quiz' => $quiz];
}

/**
 * Import quiz XML into quiz and return question IDs.
 *
 * @param stdClass $course
 * @param stdClass $quiz
 * @param string $xmlfile
 * @return array
 */
function import_quiz_xml_to_u038_quiz(stdClass $course, stdClass $quiz, string $xmlfile): array {
    $context = context_module::instance($quiz->cmid);
    $category = question_get_default_category($context->id, true);
    $qformat = new qformat_xml();
    $qformat->setCategory($category);
    $qformat->setContexts([$context]);
    $qformat->setCourse($course);
    $qformat->setFilename($xmlfile);
    $qformat->setRealfilename(basename($xmlfile));
    $qformat->setMatchgrades('nearest');
    $qformat->setCatfromfile(false);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);
    $qformat->set_display_progress(false);
    if (!$qformat->importpreprocess() || !$qformat->importprocess() || !$qformat->importpostprocess()) {
        cli_error('Could not import generated U038 quiz XML.');
    }
    $page = 1;
    foreach ($qformat->questionids as $questionid) {
        quiz_add_quiz_question($questionid, $quiz, $page, 1);
        $page++;
    }
    return array_map('intval', $qformat->questionids);
}

/**
 * Find a module by visible activity name.
 *
 * @param int $courseid
 * @param string $name
 * @return int|null
 */
function find_cmid_by_activity_name(int $courseid, string $name): ?int {
    foreach (course_modules_summary($courseid) as $module) {
        if (($module['name'] ?? '') === $name) {
            return (int)$module['cmid'];
        }
    }
    return null;
}

/**
 * Get question IDs used by a quiz cmid.
 *
 * @param int $cmid
 * @return array
 */
function question_ids_for_cmid(int $cmid): array {
    global $DB;

    $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', IGNORE_MISSING);
    if (!$cm) {
        return [];
    }
    $modname = $DB->get_field('modules', 'name', ['id' => $cm->module], IGNORE_MISSING);
    if ($modname !== 'quiz') {
        return [];
    }
    $sql = "SELECT q.id
              FROM {quiz_slots} qs
              JOIN {question_references} qr ON qr.itemid = qs.id
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qv.questionid
             WHERE qs.quizid = :quizid
               AND qr.component = 'mod_quiz'
               AND qr.questionarea = 'slot'
          ORDER BY qs.slot";
    return array_map('intval', $DB->get_fieldset_sql($sql, ['quizid' => $cm->instance]));
}

/**
 * Read a text file from a ZIP.
 *
 * @param string $zippath
 * @param string $entryname
 * @return string
 */
function zip_entry_text(string $zippath, string $entryname): string {
    $zip = new ZipArchive();
    if ($zip->open($zippath) !== true) {
        cli_error('Could not open ZIP: ' . $zippath);
    }
    $content = $zip->getFromName($entryname);
    $zip->close();
    if ($content === false) {
        cli_error('Missing ZIP entry: ' . $entryname);
    }
    return $content;
}

/**
 * Parse CSV text.
 *
 * @param string $csv
 * @return array
 */
function csv_rows(string $csv): array {
    $lines = preg_split('/\r\n|\n|\r/', trim($csv));
    if (!$lines) {
        return [];
    }
    $headers = str_getcsv(array_shift($lines));
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $values = str_getcsv($line);
        $row = [];
        foreach ($headers as $idx => $header) {
            $row[$header] = $values[$idx] ?? '';
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Filter quiz CSV rows.
 *
 * @param array $rows
 * @param string|array $lessons
 * @param int $limit
 * @return array
 */
function filter_quiz_rows(array $rows, $lessons, int $limit): array {
    $lessons = (array)$lessons;
    $out = [];
    foreach ($rows as $row) {
        if (in_array($row['lesson'] ?? '', $lessons, true)) {
            $out[] = $row;
        }
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Build simple shortanswer Moodle XML.
 *
 * @param array $rows
 * @param string $targetfile
 */
function build_shortanswer_quiz_xml(array $rows, string $targetfile): void {
    $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<quiz>'];
    foreach ($rows as $idx => $row) {
        $qid = $row['item_id'] ?? ('Q' . ($idx + 1));
        $name = $qid . ' ' . ($row['lesson'] ?? 'U038');
        $prompt = $row['prompt'] ?? '';
        $answer = $row['correct_answer'] ?? '';
        if ($prompt === '' || $answer === '') {
            continue;
        }
        $xml[] = '<question type="shortanswer">';
        $xml[] = '<name>' . cdata_text($name) . '</name>';
        $xml[] = '<questiontext format="html">' . cdata_text($prompt) . '</questiontext>';
        $xml[] = '<generalfeedback format="html">' . cdata_text($row['audit_rule'] ?? 'Traceable U038 item.') . '</generalfeedback>';
        $xml[] = '<defaultgrade>1.0000000</defaultgrade>';
        $xml[] = '<penalty>0.3333333</penalty>';
        $xml[] = '<hidden>0</hidden>';
        $xml[] = '<idnumber>' . htmlspecialchars($qid, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</idnumber>';
        $xml[] = '<usecase>0</usecase>';
        $xml[] = '<answer fraction="100" format="moodle_auto_format">' . cdata_text($answer) . '<feedback format="html">' . cdata_text('Correct.') . '</feedback></answer>';
        $xml[] = '</question>';
    }
    $xml[] = '</quiz>';
    file_put_contents($targetfile, implode(PHP_EOL, $xml) . PHP_EOL);
}

/**
 * CDATA helper.
 *
 * @param string $text
 * @return string
 */
function cdata_text(string $text): string {
    return '<text><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $text) . ']]></text>';
}

/**
 * Find best module match for an object.
 *
 * @param stdClass $object
 * @param array $modules
 * @return array|null
 */
function best_module_match(stdClass $object, array $modules): ?array {
    $lesson = (string)$object->lesson;
    $title = strtolower((string)$object->title);
    $type = strtolower((string)$object->objecttype);

    $patterns = [
        '1' => ['lesson 1', 'l1', 'vocab', 'words for problems', 'vocabulary'],
        '2' => ['lesson 2', 'l2', 'grammar', 'cause', 'advice', 'options'],
        '3' => ['lesson 3', 'l3', 'reading', 'read the solution'],
        '4' => ['lesson 4', 'l4', 'listening', 'listen for problem'],
        '5' => ['lesson 5', 'l5', 'speaking', 'discuss options'],
        '6' => ['lesson 6', 'l6', 'writing', 'write a solution'],
        '7' => ['lesson 7', 'l7', 'project', 'problem-solving project'],
    ];

    $best = null;
    $bestscore = 0;
    foreach ($modules as $module) {
        $haystack = strtolower(($module['name'] ?? '') . ' ' . ($module['modname'] ?? ''));
        $score = 0;
        foreach ($patterns[$lesson] ?? [] as $pattern) {
            if (strpos($haystack, $pattern) !== false) {
                $score += 3;
            }
        }
        foreach (preg_split('/[^a-z0-9]+/', $title) as $token) {
            if (strlen($token) >= 5 && strpos($haystack, $token) !== false) {
                $score += 1;
            }
        }
        if ($type === 'reading_quiz' && strpos($haystack, 'quiz') !== false) {
            $score += 1;
        }
        if ($type === 'listening_quiz' && strpos($haystack, 'quiz') !== false) {
            $score += 1;
        }
        if ($score > $bestscore) {
            $bestscore = $score;
            $best = $module;
        }
    }

    return $bestscore >= 3 ? $best : null;
}

/**
 * Run one end-to-end evidence test against a mapped U038 object.
 *
 * @param int $courseid
 * @param int $userid
 * @return array
 */
function run_evidence_test(int $courseid, int $userid = 0): array {
    global $DB;

    if ($userid <= 0) {
        $userid = (int)$DB->get_field_sql(
            "SELECT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
           ORDER BY ue.userid",
            ['courseid' => $courseid],
            IGNORE_MULTIPLE
        );
    }
    if ($userid <= 0) {
        $userid = (int)$DB->get_field('user', 'id', ['deleted' => 0, 'suspended' => 0], IGNORE_MULTIPLE);
    }

    $quizobject = $DB->get_record('flwcupkp_object', ['externalid' => 'REW-U038-L3-READING'], '*', MUST_EXIST);
    $readingkp = $DB->get_record('flwcupkp_kp', ['externalid' => 'FLW-EN-B1-READ-038-001'], '*', MUST_EXIST);
    $quizresult = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
        'userid' => $userid,
        'courseid' => $courseid,
        'unitcode' => 'U038',
        'objectid' => $quizobject->id,
        'sourceattempt' => 'cli-u038-quiz-' . time(),
        'evidencetype' => 'quiz_question_response',
        'targettype' => 'kp',
        'targetid' => $readingkp->id,
        'rawscore' => 0.92,
        'normalizedscore' => 0.92,
        'rubricjson' => json_encode([
            'question_ids' => question_ids_for_cmid((int)$quizobject->cmid),
            'source' => 'Lesson 3 reading quiz linked to C-UP-KP object.',
        ]),
        'assessortype' => 'system_test',
        'confidence' => 0.78,
        'evidencestrength' => 'recognition',
        'provenance' => 'local_flwcupkp_cli',
        'sourceref' => 'REW-U038-L3-READING',
    ]);

    $object = $DB->get_record('flwcupkp_object', ['externalid' => 'REW-U038-L7-PROJECT'], '*', MUST_EXIST);
    $competency = $DB->get_record('flwcupkp_comp', ['externalid' => 'FLW-REW-B1-C-038'], '*', MUST_EXIST);

    $projectresult = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
        'userid' => $userid,
        'courseid' => $courseid,
        'unitcode' => 'U038',
        'objectid' => $object->id,
        'sourceattempt' => 'cli-u038-end-to-end-' . time(),
        'evidencetype' => 'project_assessment',
        'targettype' => 'competency',
        'targetid' => $competency->id,
        'rawscore' => 0.86,
        'normalizedscore' => 0.86,
        'rubricjson' => json_encode([
            'problem_clarity' => 0.85,
            'cause_explanation' => 0.86,
            'option_comparison' => 0.84,
            'next_step_note' => 0.89,
        ]),
        'assessortype' => 'system_test',
        'confidence' => 0.82,
        'evidencestrength' => 'independent_performance',
        'provenance' => 'local_flwcupkp_cli',
        'sourceref' => 'REW-U038-L7-PROJECT',
    ]);

    $recommendations = \local_flwcupkp\local\recommendation_engine::generate($userid, $courseid, 3);
    $state = $DB->get_record('flwcupkp_state', [
        'userid' => $userid,
        'targettype' => 'competency',
        'targetid' => $competency->id,
    ]);

    return [
        'userid' => $userid,
        'courseid' => $courseid,
        'quiz_object_externalid' => $quizobject->externalid,
        'quiz_object_cmid' => $quizobject->cmid,
        'reading_kp_externalid' => $readingkp->externalid,
        'quiz_evidence_result' => $quizresult,
        'object_externalid' => $object->externalid,
        'object_cmid' => $object->cmid,
        'competency_externalid' => $competency->externalid,
        'project_evidence_result' => $projectresult,
        'stored_state' => $state,
        'recommendation_count' => count($recommendations),
    ];
}

/**
 * Submit a real Moodle quiz attempt and let the observer create evidence.
 *
 * @param int $courseid
 * @param int $cmid
 * @return array
 */
function run_real_quiz_submission_test(int $courseid, int $cmid): array {
    global $DB, $USER;

    $cm = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $courseid], '*', MUST_EXIST);
    $modname = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
    if ($modname !== 'quiz') {
        cli_error('CMID ' . $cmid . ' is not a quiz.');
    }

    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $student = get_or_create_u038_student($courseid);
    $USER = $DB->get_record('user', ['id' => $student->id], '*', MUST_EXIST);
    \core\session\manager::set_user($USER);

    $before = $DB->count_records('flwcupkp_evidence', ['userid' => $student->id, 'objectid' => linked_object_id_for_cmid($cmid)]);

    $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
    $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
    $quiz = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
    $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
    $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
    $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

    $attemptnumber = ((int)$DB->get_field_sql(
        'SELECT MAX(attempt) FROM {quiz_attempts} WHERE quiz = :quiz AND userid = :userid',
        ['quiz' => $quiz->id, 'userid' => $student->id]
    )) + 1;
    $timenow = time();
    $attempt = quiz_create_attempt($quizobj, $attemptnumber, false, $timenow, false, $student->id);
    quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow);
    quiz_attempt_save_started($quizobj, $quba, $attempt);

    $responses = correct_shortanswer_responses_for_quiz($quiz->id);
    $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
    $attemptobj->process_submitted_actions($timenow, false, $responses);
    $attemptobj->process_submit($timenow + 1, false);
    $attemptobj->process_grade_submission($timenow + 1);

    $after = $DB->count_records('flwcupkp_evidence', ['userid' => $student->id, 'objectid' => linked_object_id_for_cmid($cmid)]);
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
    $object = $DB->get_record('flwcupkp_object', ['cmid' => $cmid], '*', MUST_EXIST);

    $states = $DB->get_records_sql(
        "SELECT s.*
           FROM {flwcupkp_state} s
           JOIN {flwcupkp_object_map} om ON om.targettype = s.targettype AND om.targetid = s.targetid
          WHERE s.userid = :userid
            AND om.objectid = :objectid",
        ['userid' => $student->id, 'objectid' => $object->id]
    );

    return [
        'courseid' => $courseid,
        'cmid' => $cmid,
        'quizid' => (int)$quiz->id,
        'student_userid' => (int)$student->id,
        'attemptid' => (int)$attempt->id,
        'attempt_state' => $attempt->state,
        'sumgrades' => $attempt->sumgrades,
        'quiz_sumgrades' => $quiz->sumgrades,
        'responses_submitted' => count($responses),
        'evidence_before' => $before,
        'evidence_after' => $after,
        'new_evidence_count' => $after - $before,
        'mapped_object' => [
            'id' => (int)$object->id,
            'externalid' => $object->externalid,
            'title' => $object->title,
        ],
        'states' => array_values($states),
    ];
}

/**
 * Create or reuse a C-UP-KP U038 test student and enrol them.
 *
 * @param int $courseid
 * @return stdClass
 */
function get_or_create_u038_student(int $courseid): stdClass {
    global $DB;

    $username = 'flwcupkp_u038_student';
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', IGNORE_MISSING);
    if (!$user) {
        $user = (object)[
            'username' => $username,
            'password' => 'Temp#12345',
            'firstname' => 'FLW',
            'lastname' => 'CUPKP Student',
            'email' => 'flwcupkp_u038_student@example.local',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => 1,
        ];
        $user->id = user_create_user($user, true, false);
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
    $plugin = enrol_get_plugin('manual');
    if (!$plugin) {
        cli_error('Manual enrolment plugin is not available.');
    }

    $manualinstance = null;
    foreach (enrol_get_instances($courseid, true) as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }
    if (!$manualinstance) {
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $plugin->add_default_instance($course);
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
    }
    $plugin->enrol_user($manualinstance, $user->id, $role->id, time(), 0, ENROL_USER_ACTIVE);
    return $user;
}

/**
 * Build correct short-answer responses keyed by quiz slot.
 *
 * @param int $quizid
 * @return array
 */
function correct_shortanswer_responses_for_quiz(int $quizid): array {
    global $DB;

    $sql = "SELECT qs.slot, q.id AS questionid
              FROM {quiz_slots} qs
              JOIN {question_references} qr ON qr.itemid = qs.id
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qv.questionid
             WHERE qs.quizid = :quizid
               AND qr.component = 'mod_quiz'
               AND qr.questionarea = 'slot'
          ORDER BY qs.slot";
    $slots = $DB->get_records_sql($sql, ['quizid' => $quizid]);
    $responses = [];
    foreach ($slots as $slot) {
        $answer = $DB->get_field_select(
            'question_answers',
            'answer',
            'question = :questionid AND fraction > :fraction',
            ['questionid' => $slot->questionid, 'fraction' => 0.999],
            IGNORE_MULTIPLE
        );
        if ($answer !== false) {
            $responses[(int)$slot->slot] = ['answer' => $answer];
        }
    }
    return $responses;
}

/**
 * Get linked C-UP-KP object ID for a course module.
 *
 * @param int $cmid
 * @return int
 */
function linked_object_id_for_cmid(int $cmid): int {
    global $DB;
    return (int)$DB->get_field('flwcupkp_object', 'id', ['cmid' => $cmid], MUST_EXIST);
}

/**
 * Status report for linked U038 objects.
 *
 * @return array
 */
function status_report(): array {
    global $DB;

    $objects = cupkp_objects();
    $linked = [];
    foreach ($objects as $object) {
        $linked[] = [
            'externalid' => $object->externalid,
            'title' => $object->title,
            'courseid' => (int)$object->courseid,
            'cmid' => (int)$object->cmid,
        ];
    }

    return [
        'u038_objects' => $linked,
        'u038_competency_states' => array_values($DB->get_records_sql(
            "SELECT s.*
               FROM {flwcupkp_state} s
               JOIN {flwcupkp_comp} c ON c.id = s.targetid
              WHERE s.targettype = 'competency'
                AND c.externalid = :externalid",
            ['externalid' => 'FLW-REW-B1-C-038']
        )),
    ];
}

<?php
// Create/link a generic C-UP-KP unit shell in Moodle.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

global $USER;
if (!is_siteadmin()) {
    $USER = get_admin();
    \core\session\manager::set_user($USER);
}

[$options] = cli_get_params([
    'create-shell' => false,
    'link' => false,
    'status' => false,
    'unitcode' => '',
    'courseid' => 0,
    'shortname' => '',
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || (!$options['create-shell'] && !$options['link'] && !$options['status'])) {
    echo "Create/link a generic C-UP-KP unit course shell.\n";
    echo "Usage:\n";
    echo "  php local/flwcupkp/cli/link_unit.php --create-shell --unitcode=U037 [--shortname=SHORT]\n";
    echo "  php local/flwcupkp/cli/link_unit.php --link --unitcode=U037 --courseid=ID\n";
    echo "  php local/flwcupkp/cli/link_unit.php --status --unitcode=U037\n";
    exit(0);
}

$unitcode = clean_param((string)$options['unitcode'], PARAM_ALPHANUMEXT);
if ($unitcode === '') {
    cli_error('--unitcode is required.');
}

if ($options['create-shell']) {
    echo json_encode(create_unit_shell($unitcode, (string)$options['shortname']), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['link']) {
    $courseid = (int)$options['courseid'];
    if ($courseid <= 0) {
        cli_error('--courseid is required for --link.');
    }
    echo json_encode(link_unit_course($unitcode, $courseid), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['status']) {
    echo json_encode(unit_status($unitcode), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

/**
 * Create/reuse a course shell, page activities, object links, and unit test users.
 *
 * @param string $unitcode
 * @param string $shortname
 * @return array
 */
function create_unit_shell(string $unitcode, string $shortname = ''): array {
    $objects = unit_objects($unitcode);
    if (!$objects) {
        cli_error('No imported C-UP-KP learning objects found for unit ' . $unitcode . '.');
    }

    $course = get_or_create_unit_course($unitcode, $objects, $shortname);
    course_create_sections_if_missing($course, unit_sections($objects));
    update_course_object_pages($course, $objects);
    $linkresult = link_unit_course($unitcode, (int)$course->id);
    $users = ensure_unit_users((int)$course->id, $unitcode);
    rebuild_course_cache((int)$course->id, true);

    return [
        'unitcode' => $unitcode,
        'courseid' => (int)$course->id,
        'shortname' => $course->shortname,
        'users' => $users,
        'link_result' => $linkresult,
    ];
}

/**
 * Link imported unit objects to existing activities by generated activity name.
 *
 * @param string $unitcode
 * @param int $courseid
 * @return array
 */
function link_unit_course(string $unitcode, int $courseid): array {
    global $DB;

    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $objects = unit_objects($unitcode);
    $linked = [];
    foreach ($objects as $object) {
        $name = unit_activity_name($object);
        $cmid = find_cmid_by_activity_name((int)$course->id, $name);
        if (!$cmid) {
            $linked[] = [
                'externalid' => $object->externalid,
                'name' => $name,
                'status' => 'missing_activity',
            ];
            continue;
        }

        $object->courseid = (int)$course->id;
        $object->cmid = $cmid;
        $DB->update_record('flwcupkp_object', $object);
        $linked[] = [
            'externalid' => $object->externalid,
            'name' => $name,
            'courseid' => (int)$course->id,
            'cmid' => $cmid,
            'status' => 'linked',
        ];
    }

    rebuild_course_cache((int)$course->id, true);

    return [
        'unitcode' => $unitcode,
        'courseid' => (int)$course->id,
        'linked' => $linked,
    ];
}

/**
 * Current link status for one unit.
 *
 * @param string $unitcode
 * @return array
 */
function unit_status(string $unitcode): array {
    $objects = unit_objects($unitcode);
    $status = [];
    foreach ($objects as $object) {
        $status[] = [
            'externalid' => $object->externalid,
            'title' => $object->title,
            'lesson' => $object->lesson,
            'objecttype' => $object->objecttype,
            'courseid' => $object->courseid ? (int)$object->courseid : null,
            'cmid' => $object->cmid ? (int)$object->cmid : null,
            'activity_name' => unit_activity_name($object),
        ];
    }

    return [
        'unitcode' => $unitcode,
        'objects' => $status,
    ];
}

/**
 * Imported learning objects for one unit.
 *
 * @param string $unitcode
 * @return array
 */
function unit_objects(string $unitcode): array {
    global $DB;

    return $DB->get_records('flwcupkp_object', ['unitcode' => $unitcode],
        'lesson ASC, externalid ASC',
        'id, frameworkid, externalid, title, unitcode, lesson, objecttype, courseid, cmid, sourceid, purpose, ' .
        'evidencestrength, difficulty, role, metadatajson');
}

/**
 * Get/create the Moodle course for a unit.
 *
 * @param string $unitcode
 * @param array $objects
 * @param string $shortname
 * @return stdClass
 */
function get_or_create_unit_course(string $unitcode, array $objects, string $shortname = ''): stdClass {
    global $DB;

    $first = reset($objects);
    $framework = $DB->get_record('flwcupkp_framework', ['id' => (int)$first->frameworkid], '*', IGNORE_MISSING);
    $coursecode = $framework ? (string)$framework->coursecode : 'FLW';
    $shortname = $shortname !== '' ? $shortname : 'FLW-' . $coursecode . '-' . $unitcode . '-CUPKP';
    if ($course = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING)) {
        return $course;
    }

    $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
    if ($english = $DB->get_record_select('course_categories', $DB->sql_like('name', ':name', false),
            ['name' => '%English%'], 'id', IGNORE_MULTIPLE)) {
        $categoryid = (int)$english->id;
    }

    $fullname = ($framework ? (string)$framework->name : 'FLW C-UP-KP') . ' - ' . $unitcode;
    $course = (object)[
        'fullname' => $fullname,
        'shortname' => $shortname,
        'idnumber' => $coursecode . '-' . $unitcode . '-CUPKP',
        'category' => $categoryid,
        'summary' => '<p>C-UP-KP shell course for ' . s($unitcode) . '.</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => max(unit_sections($objects)),
        'startdate' => time(),
        'visible' => 1,
        'enablecompletion' => 1,
    ];

    return create_course($course);
}

/**
 * Create/reuse page modules for every imported object.
 *
 * @param stdClass $course
 * @param array $objects
 */
function update_course_object_pages(stdClass $course, array $objects): void {
    foreach ($objects as $object) {
        $section = unit_section_number($object, $objects);
        course_create_sections_if_missing($course, [$section]);
        update_unit_section($course, $section, unit_section_name($object));

        $name = unit_activity_name($object);
        if (find_cmid_by_activity_name((int)$course->id, $name)) {
            continue;
        }

        add_unit_page($course, $section, $name, unit_page_content($object));
    }
}

/**
 * Update a section heading.
 *
 * @param stdClass $course
 * @param int $sectionnum
 * @param string $name
 */
function update_unit_section(stdClass $course, int $sectionnum, string $name): void {
    global $DB;

    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum],
        '*', MUST_EXIST);
    $section->name = $name;
    $section->summary = '';
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 1;
    $DB->update_record('course_sections', $section);
}

/**
 * Add one page module.
 *
 * @param stdClass $course
 * @param int $section
 * @param string $name
 * @param string $content
 * @return int
 */
function add_unit_page(stdClass $course, int $section, string $name, string $content): int {
    global $DB;

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'page';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->name = $name;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->intro = '<p>C-UP-KP mapped learning object.</p>';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->content = $content;
    $moduleinfo->contentformat = FORMAT_HTML;
    $moduleinfo->display = 0;
    $moduleinfo->printintro = 0;
    $moduleinfo->printlastmodified = 0;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    $moduleinfo->completion = 2;
    $moduleinfo->completionview = 1;
    $moduleinfo->completionexpected = 0;
    $moduleinfo->completionunlocked = 1;
    $cm = add_moduleinfo($moduleinfo, $course);

    return (int)$cm->coursemodule;
}

/**
 * Create/enrol deterministic test users for a unit.
 *
 * @param int $courseid
 * @param string $unitcode
 * @return array
 */
function ensure_unit_users(int $courseid, string $unitcode): array {
    $student = ensure_unit_user($courseid, $unitcode, 'student');
    $teacher = ensure_unit_user($courseid, $unitcode, 'editingteacher');

    return [
        'student' => [
            'id' => (int)$student->id,
            'username' => $student->username,
        ],
        'teacher' => [
            'id' => (int)$teacher->id,
            'username' => $teacher->username,
        ],
    ];
}

/**
 * Create/reuse and enrol one user.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param string $roleshortname
 * @return stdClass
 */
function ensure_unit_user(int $courseid, string $unitcode, string $roleshortname): stdClass {
    global $DB;

    $isstudent = $roleshortname === 'student';
    $username = 'flwcupkp_' . strtolower($unitcode) . '_' . ($isstudent ? 'student' : 'teacher');
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', IGNORE_MISSING);
    if (!$user) {
        $user = (object)[
            'username' => $username,
            'password' => 'Temp#12345',
            'firstname' => 'FLW',
            'lastname' => $unitcode . ($isstudent ? ' Student' : ' Teacher'),
            'email' => $username . '@example.local',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => 1,
        ];
        $user->id = user_create_user($user, true, false);
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
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
 * Activity name generated for a learning object.
 *
 * @param stdClass $object
 * @return string
 */
function unit_activity_name(stdClass $object): string {
    $lesson = trim((string)$object->lesson);
    $prefix = ctype_digit($lesson) ? 'Lesson ' . $lesson : $lesson;
    return trim($prefix . ' - ' . $object->title);
}

/**
 * Section heading generated for a learning object.
 *
 * @param stdClass $object
 * @return string
 */
function unit_section_name(stdClass $object): string {
    $lesson = trim((string)$object->lesson);
    return ctype_digit($lesson) ? 'Lesson ' . $lesson : $lesson;
}

/**
 * Simple page content for generated shell activities.
 *
 * @param stdClass $object
 * @return string
 */
function unit_page_content(stdClass $object): string {
    return '<h3>' . s($object->title) . '</h3>' .
        '<p><strong>C-UP-KP object:</strong> ' . s($object->externalid) . '</p>' .
        '<p><strong>Type:</strong> ' . s($object->objecttype) . '</p>' .
        '<p>This generated Moodle page is linked to the imported C-UP-KP unit map.</p>';
}

/**
 * Section numbers needed by a set of objects.
 *
 * @param array $objects
 * @return array
 */
function unit_sections(array $objects): array {
    $sections = [0];
    foreach ($objects as $object) {
        $sections[] = unit_section_number($object, $objects);
    }
    sort($sections);
    return array_values(array_unique($sections));
}

/**
 * Section number for a learning object.
 *
 * @param stdClass $object
 * @param array $objects
 * @return int
 */
function unit_section_number(stdClass $object, array $objects): int {
    $lesson = trim((string)$object->lesson);
    if (ctype_digit($lesson)) {
        return max(1, (int)$lesson);
    }

    $maxnumeric = 0;
    $nonnumeric = [];
    foreach ($objects as $candidate) {
        $candidatelesson = trim((string)$candidate->lesson);
        if (ctype_digit($candidatelesson)) {
            $maxnumeric = max($maxnumeric, (int)$candidatelesson);
        } else if (!in_array($candidatelesson, $nonnumeric, true)) {
            $nonnumeric[] = $candidatelesson;
        }
    }

    $offset = array_search($lesson, $nonnumeric, true);
    return $maxnumeric + 1 + ($offset === false ? 0 : (int)$offset);
}

/**
 * Find a module by visible activity name.
 *
 * @param int $courseid
 * @param string $name
 * @return int|null
 */
function find_cmid_by_activity_name(int $courseid, string $name): ?int {
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->name === $name) {
            return (int)$cm->id;
        }
    }
    return null;
}

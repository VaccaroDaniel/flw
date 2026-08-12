<?php
// Unit setup helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates, links, and summarizes Moodle course shells for imported C-UP-KP units.
 */
final class unit_setup_service {
    /**
     * Create/reuse a course shell, page activities, and object links for a unit.
     *
     * @param string $unitcode
     * @param string $shortname
     * @param bool $createusers
     * @return array
     */
    public static function create_shell(string $unitcode, string $shortname = '', bool $createusers = false): array {
        self::ensure_course_helpers_loaded();
        $unitcode = self::clean_unit_code($unitcode);
        $objects = self::unit_objects($unitcode);
        if (!$objects) {
            throw new \invalid_parameter_exception('No imported C-UP-KP learning objects found for unit ' . $unitcode . '.');
        }

        $course = self::get_or_create_unit_course($unitcode, $objects, $shortname);
        self::course_create_sections_if_missing($course, self::unit_sections($objects));
        self::update_course_object_pages($course, $objects);
        $linkresult = self::link_course($unitcode, (int)$course->id);
        $users = $createusers ? self::ensure_unit_users((int)$course->id, $unitcode) : [];
        rebuild_course_cache((int)$course->id, true);

        repository::audit('unit_shell_created', 'unit', null, [
            'unitcode' => $unitcode,
            'courseid' => (int)$course->id,
            'shortname' => $course->shortname,
            'createdusers' => $createusers,
        ]);

        return [
            'unitcode' => $unitcode,
            'courseid' => (int)$course->id,
            'shortname' => $course->shortname,
            'users' => $users,
            'link_result' => $linkresult,
            'status' => self::status($unitcode, (int)$course->id),
        ];
    }

    /**
     * Link imported unit objects to existing Moodle activities by generated activity name.
     *
     * @param string $unitcode
     * @param int $courseid
     * @return array
     */
    public static function link_course(string $unitcode, int $courseid): array {
        global $DB;

        self::ensure_course_helpers_loaded();
        $unitcode = self::clean_unit_code($unitcode);
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('A Moodle course is required.');
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $objects = self::unit_objects($unitcode);
        if (!$objects) {
            throw new \invalid_parameter_exception('No imported C-UP-KP learning objects found for unit ' . $unitcode . '.');
        }

        $linked = [];
        foreach ($objects as $object) {
            $name = self::unit_activity_name($object);
            $cmid = self::find_cmid_by_activity_name((int)$course->id, $name);
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
        repository::audit('unit_course_linked', 'unit', null, [
            'unitcode' => $unitcode,
            'courseid' => (int)$course->id,
            'linked' => count(array_filter($linked, static function(array $row): bool {
                return $row['status'] === 'linked';
            })),
            'missing' => count(array_filter($linked, static function(array $row): bool {
                return $row['status'] !== 'linked';
            })),
        ]);

        return [
            'unitcode' => $unitcode,
            'courseid' => (int)$course->id,
            'linked' => $linked,
            'status' => self::status($unitcode, (int)$course->id),
        ];
    }

    /**
     * Current setup and activation status for one unit.
     *
     * @param string $unitcode
     * @param int $courseid
     * @return array
     */
    public static function status(string $unitcode, int $courseid = 0): array {
        global $DB;

        self::ensure_course_helpers_loaded();
        $unitcode = self::clean_unit_code($unitcode);
        $objects = self::unit_objects($unitcode);
        $courseids = [];
        foreach ($objects as $object) {
            if (!empty($object->courseid)) {
                $courseids[(int)$object->courseid] = true;
            }
        }
        if ($courseid <= 0 && count($courseids) === 1) {
            $courseid = (int)array_key_first($courseids);
        }

        $rows = [];
        $counts = [
            'linked' => 0,
            'ready_to_link' => 0,
            'missing_activity' => 0,
            'linked_to_other_course' => 0,
            'not_linked' => 0,
        ];
        foreach ($objects as $object) {
            $name = self::unit_activity_name($object);
            $currentcourseid = !empty($object->courseid) ? (int)$object->courseid : 0;
            $currentcmid = !empty($object->cmid) ? (int)$object->cmid : 0;
            $matchedcmid = $courseid > 0 ? self::find_cmid_by_activity_name($courseid, $name) : null;
            $linkstatus = 'not_linked';
            if ($courseid > 0 && $currentcourseid === $courseid && $currentcmid > 0) {
                $linkstatus = 'linked';
            } else if ($courseid > 0 && $currentcourseid > 0 && $currentcourseid !== $courseid) {
                $linkstatus = 'linked_to_other_course';
            } else if ($courseid > 0 && $matchedcmid) {
                $linkstatus = 'ready_to_link';
            } else if ($courseid > 0) {
                $linkstatus = 'missing_activity';
            } else if ($currentcourseid > 0 && $currentcmid > 0) {
                $linkstatus = 'linked';
            }
            $counts[$linkstatus]++;
            $rows[] = [
                'externalid' => $object->externalid,
                'title' => $object->title,
                'lesson' => $object->lesson,
                'objecttype' => $object->objecttype,
                'courseid' => $currentcourseid ?: null,
                'cmid' => $currentcmid ?: null,
                'matchedcmid' => $matchedcmid,
                'activity_name' => $name,
                'link_status' => $linkstatus,
            ];
        }

        $objectcount = count($objects);
        $mapcount = $objectcount > 0 ? (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_object_map} om
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE o.unitcode = :unitcode",
            ['unitcode' => $unitcode]
        ) : 0;
        $targetcounts = self::unit_target_counts($unitcode);
        $issues = [];
        if ($objectcount === 0) {
            $issues[] = get_string('setupissueimport', 'local_flwcupkp');
        }
        if ($courseid <= 0) {
            $issues[] = get_string('setupissuecourse', 'local_flwcupkp');
        }
        if ($objectcount > 0 && $counts['linked'] < $objectcount) {
            $issues[] = get_string('setupissuelinks', 'local_flwcupkp');
        }
        if ($mapcount === 0) {
            $issues[] = get_string('setupissuemappings', 'local_flwcupkp');
        }

        return [
            'unitcode' => $unitcode,
            'courseid' => $courseid,
            'courseids' => array_map('intval', array_keys($courseids)),
            'objectcount' => $objectcount,
            'objectmapcount' => $mapcount,
            'targetcounts' => $targetcounts,
            'counts' => $counts,
            'objects' => $rows,
            'activation' => [
                'ready' => $objectcount > 0 && $courseid > 0 && $counts['linked'] === $objectcount && $mapcount > 0,
                'issues' => $issues,
            ],
        ];
    }

    /**
     * Read a package from a safe plugin-relative import path.
     *
     * @param string $sourcefile
     * @return array
     */
    public static function read_import_source(string $sourcefile): array {
        global $CFG;

        $sourcefile = trim(str_replace('\\', '/', $sourcefile));
        if ($sourcefile === '') {
            return ['', ''];
        }
        if (preg_match('/^[A-Za-z]:|^\//', $sourcefile) || strpos($sourcefile, '..') !== false) {
            throw new \invalid_parameter_exception('Only plugin-relative C-UP-KP import paths are allowed.');
        }

        $allowedprefixes = [
            'local/flwcupkp/fixtures/',
            'local/flwcupkp/imports/',
            'local/flwcupkp/templates/',
        ];
        $allowed = false;
        foreach ($allowedprefixes as $prefix) {
            if (strpos($sourcefile, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new \invalid_parameter_exception('C-UP-KP import path must be under local/flwcupkp/fixtures, local/flwcupkp/imports, or local/flwcupkp/templates.');
        }

        $fullpath = $CFG->dirroot . '/' . $sourcefile;
        if (!is_readable($fullpath)) {
            throw new \moodle_exception('invalidfile', 'error', '', $sourcefile);
        }

        return [file_get_contents($fullpath), $sourcefile];
    }

    /**
     * Infer the unit code from a C-UP-KP package payload.
     *
     * @param array $package
     * @return string
     */
    public static function infer_unit_code_from_package(array $package): string {
        $candidates = [];
        if (!empty($package['unit_code'])) {
            $candidates[] = $package['unit_code'];
        }
        foreach (['learning_objects', 'lesson_mappings'] as $key) {
            foreach (($package[$key] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (!empty($row['unit_code'])) {
                    $candidates[] = $row['unit_code'];
                }
                if (!empty($row['unitcode'])) {
                    $candidates[] = $row['unitcode'];
                }
            }
        }
        foreach ($candidates as $candidate) {
            $unitcode = clean_param((string)$candidate, PARAM_ALPHANUMEXT);
            if ($unitcode !== '') {
                return $unitcode;
            }
        }
        return '';
    }

    /**
     * Imported learning objects for one unit.
     *
     * @param string $unitcode
     * @return array
     */
    private static function unit_objects(string $unitcode): array {
        global $DB;

        return $DB->get_records('flwcupkp_object', ['unitcode' => $unitcode],
            'lesson ASC, externalid ASC',
            'id, frameworkid, externalid, title, unitcode, lesson, objecttype, courseid, cmid, sourceid, purpose, ' .
            'evidencestrength, difficulty, role, metadatajson');
    }

    /**
     * Count mapped targets used by one unit.
     *
     * @param string $unitcode
     * @return array
     */
    private static function unit_target_counts(string $unitcode): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT om.targettype, COUNT(DISTINCT om.targetid) AS targetcount
               FROM {flwcupkp_object_map} om
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE o.unitcode = :unitcode
           GROUP BY om.targettype",
            ['unitcode' => $unitcode]
        );
        $counts = ['competency' => 0, 'up' => 0, 'kp' => 0];
        foreach ($records as $record) {
            if (isset($counts[$record->targettype])) {
                $counts[$record->targettype] = (int)$record->targetcount;
            }
        }
        return $counts;
    }

    /**
     * Get/create the Moodle course for a unit.
     *
     * @param string $unitcode
     * @param array $objects
     * @param string $shortname
     * @return \stdClass
     */
    private static function get_or_create_unit_course(string $unitcode, array $objects, string $shortname = ''): \stdClass {
        global $DB;

        $first = reset($objects);
        $framework = $DB->get_record('flwcupkp_framework', ['id' => (int)$first->frameworkid], '*', IGNORE_MISSING);
        $coursecode = $framework ? (string)$framework->coursecode : 'FLW';
        $shortname = trim($shortname) !== '' ? trim($shortname) : 'FLW-' . $coursecode . '-' . $unitcode . '-CUPKP';
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
            'numsections' => max(self::unit_sections($objects)),
            'startdate' => time(),
            'visible' => 1,
            'enablecompletion' => 1,
        ];

        return create_course($course);
    }

    /**
     * Create/reuse page modules for every imported object.
     *
     * @param \stdClass $course
     * @param array $objects
     */
    private static function update_course_object_pages(\stdClass $course, array $objects): void {
        foreach ($objects as $object) {
            $section = self::unit_section_number($object, $objects);
            self::course_create_sections_if_missing($course, [$section]);
            self::update_unit_section($course, $section, self::unit_section_name($object));

            $name = self::unit_activity_name($object);
            if (self::find_cmid_by_activity_name((int)$course->id, $name)) {
                continue;
            }

            self::add_unit_page($course, $section, $name, self::unit_page_content($object));
        }
    }

    /**
     * Update a section heading.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $name
     */
    private static function update_unit_section(\stdClass $course, int $sectionnum, string $name): void {
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
     * @param \stdClass $course
     * @param int $section
     * @param string $name
     * @param string $content
     * @return int
     */
    private static function add_unit_page(\stdClass $course, int $section, string $name, string $content): int {
        global $DB;

        $moduleinfo = new \stdClass();
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
     * Create/reuse and enrol deterministic test users for CLI-created shells.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function ensure_unit_users(int $courseid, string $unitcode): array {
        $student = self::ensure_unit_user($courseid, $unitcode, 'student');
        $teacher = self::ensure_unit_user($courseid, $unitcode, 'editingteacher');

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
     * @return \stdClass
     */
    private static function ensure_unit_user(int $courseid, string $unitcode, string $roleshortname): \stdClass {
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
            throw new \moodle_exception('Manual enrolment plugin is not available.');
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
     * @param \stdClass $object
     * @return string
     */
    private static function unit_activity_name(\stdClass $object): string {
        $lesson = trim((string)$object->lesson);
        $prefix = ctype_digit($lesson) ? 'Lesson ' . $lesson : $lesson;
        return trim($prefix . ' - ' . $object->title);
    }

    /**
     * Section heading generated for a learning object.
     *
     * @param \stdClass $object
     * @return string
     */
    private static function unit_section_name(\stdClass $object): string {
        $lesson = trim((string)$object->lesson);
        return ctype_digit($lesson) ? 'Lesson ' . $lesson : $lesson;
    }

    /**
     * Simple page content for generated shell activities.
     *
     * @param \stdClass $object
     * @return string
     */
    private static function unit_page_content(\stdClass $object): string {
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
    private static function unit_sections(array $objects): array {
        $sections = [0];
        foreach ($objects as $object) {
            $sections[] = self::unit_section_number($object, $objects);
        }
        sort($sections);
        return array_values(array_unique($sections));
    }

    /**
     * Section number for a learning object.
     *
     * @param \stdClass $object
     * @param array $objects
     * @return int
     */
    private static function unit_section_number(\stdClass $object, array $objects): int {
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
    private static function find_cmid_by_activity_name(int $courseid, string $name): ?int {
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->name === $name) {
                return (int)$cm->id;
            }
        }
        return null;
    }

    /**
     * Ensure required Moodle course APIs are loaded.
     */
    private static function ensure_course_helpers_loaded(): void {
        global $CFG;
        static $loaded = false;
        if ($loaded) {
            return;
        }
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/page/lib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        $loaded = true;
    }

    /**
     * Create course sections using whichever Moodle API is available.
     *
     * @param \stdClass $course
     * @param array $sections
     */
    private static function course_create_sections_if_missing(\stdClass $course, array $sections): void {
        course_create_sections_if_missing($course, $sections);
    }

    /**
     * Clean and require a unit code.
     *
     * @param string $unitcode
     * @return string
     */
    private static function clean_unit_code(string $unitcode): string {
        $unitcode = clean_param(trim($unitcode), PARAM_ALPHANUMEXT);
        if ($unitcode === '') {
            throw new \invalid_parameter_exception('Unit code is required.');
        }
        return $unitcode;
    }
}

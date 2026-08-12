<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_flwtextbookimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Imports FLW textbook dry-run packages into Moodle course shells.
 */
class importer {
    /** @var string Expected dry-run schema name. */
    private const SCHEMA_NAME = 'flw_moodle_textbook_import_dry_run';

    /** @var array Moodle modules this phase is allowed to create. */
    private const SUPPORTED_ACTIVITY_MODULES = ['page', 'assign'];

    /**
     * Load and validate a dry-run package.
     *
     * @param string $path
     * @return array
     */
    public static function load_package(string $path): array {
        if ($path === '') {
            throw new \moodle_exception('missinginput', 'local_flwtextbookimport');
        }
        if (!is_readable($path)) {
            throw new \moodle_exception('filenotreadable', 'local_flwtextbookimport', '', $path);
        }

        $json = file_get_contents($path);
        $package = json_decode($json, true);
        if (!is_array($package)) {
            throw new \moodle_exception('invalidjson', 'local_flwtextbookimport', '', json_last_error_msg());
        }

        self::validate_package($package);
        return $package;
    }

    /**
     * Build a preview summary without writing to Moodle.
     *
     * @param array $package
     * @return array
     */
    public static function preview(array $package): array {
        global $DB;

        self::validate_package($package);

        $course = $package['course'];
        $existingcourse = $DB->get_record('course', ['shortname' => $course['shortname']], 'id,fullname,shortname,visible', IGNORE_MISSING);
        $categorypath = self::category_path_parts($course['category_path']);
        $resolvedcategories = [];
        $parentid = 0;
        foreach ($categorypath as $part) {
            $category = self::find_category($part, $parentid);
            $resolvedcategories[] = [
                'name' => $part,
                'exists' => (bool)$category,
                'id' => $category ? (int)$category->id : null,
            ];
            $parentid = $category ? (int)$category->id : 0;
            if (!$category) {
                $parentid = -1;
            }
        }

        return [
            'mode' => 'dry_run',
            'course' => [
                'fullname' => $course['fullname'],
                'shortname' => $course['shortname'],
                'category_path' => $course['category_path'],
                'existing_course_id' => $existingcourse ? (int)$existingcourse->id : null,
            ],
            'counts' => [
                'sections' => count($package['sections']),
                'lesson_sections' => max(0, count($package['sections']) - 1),
                'activities_in_plan' => self::activity_count($package),
            ],
            'categories' => $resolvedcategories,
            'safety' => [
                'writes_to_moodle' => false,
                'execute_requires_flag' => true,
            ],
        ];
    }

    /**
     * Stable hash for the package plan that feeds the review table.
     *
     * @param array $package
     * @return string
     */
    public static function package_hash(array $package): string {
        return sha1(json_encode([
            'course' => $package['course'] ?? [],
            'sections' => $package['sections'] ?? [],
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sync dry-run package activities into the Moodle review table.
     *
     * Existing teacher edits are preserved while source metadata is refreshed.
     *
     * @param array $package
     * @return array
     */
    public static function sync_review_rows(array $package): array {
        global $DB, $USER;

        self::validate_package($package);

        $course = $DB->get_record('course', ['shortname' => $package['course']['shortname']], '*', MUST_EXIST);
        $packagehash = self::package_hash($package);
        $now = time();
        $userid = (int)($USER->id ?? 0);
        $inserted = 0;
        $updated = 0;

        foreach ($package['sections'] as $section) {
            $sectionnumber = (int)($section['section_number'] ?? 0);
            foreach (($section['activities'] ?? []) as $index => $activity) {
                $idnumber = self::activity_idnumber($package, $sectionnumber, $index, $activity);
                $record = (object)[
                    'packagehash' => $packagehash,
                    'courseid' => (int)$course->id,
                    'sectionnum' => $sectionnumber,
                    'activityindex' => (int)$index,
                    'activityidnumber' => $idnumber,
                    'name' => self::trim_text(self::clean_import_text((string)($activity['name'] ?? 'Activity')), 255),
                    'moodlemodule' => self::trim_text((string)($activity['moodle_module'] ?? ''), 40),
                    'activitytype' => self::trim_text((string)($activity['activity_type'] ?? ''), 80),
                    'sourcecomponent' => self::trim_text((string)($activity['source_component'] ?? ''), 80),
                    'sourcepdf' => self::trim_text((string)($activity['source_pdf'] ?? ''), 255),
                    'sourcerange' => json_encode($activity['source_range'] ?? [], JSON_UNESCAPED_SLASHES),
                    'reviewstatus' => self::trim_text((string)($activity['review_status'] ?? ''), 80),
                    'approved' => self::default_review_approved($activity),
                    'cefr' => self::infer_cefr($package, $activity),
                    'skill' => self::infer_skill($activity),
                    'kptags' => self::default_kp_tags($package, $sectionnumber, $activity),
                    'notes' => self::clean_import_text((string)($activity['notes'] ?? '')),
                    'timemodified' => $now,
                    'usermodified' => $userid,
                ];

                $existing = $DB->get_record('flwtbi_review', [
                    'courseid' => (int)$course->id,
                    'activityidnumber' => $idnumber,
                ], '*', IGNORE_MISSING);

                if ($existing) {
                    $record->id = (int)$existing->id;
                    $record->approved = (int)$existing->approved;
                    $record->cefr = (string)($existing->cefr ?? '') !== '' ? (string)$existing->cefr : $record->cefr;
                    $record->skill = (string)($existing->skill ?? '') !== '' ? (string)$existing->skill : $record->skill;
                    $record->kptags = (string)($existing->kptags ?? '') !== '' ? (string)$existing->kptags : $record->kptags;
                    $record->notes = (string)($existing->notes ?? '') !== '' ? (string)$existing->notes : $record->notes;
                    $DB->update_record('flwtbi_review', $record);
                    $updated++;
                } else {
                    $DB->insert_record('flwtbi_review', $record);
                    $inserted++;
                }
            }
        }

        return [
            'packagehash' => $packagehash,
            'courseid' => (int)$course->id,
            'inserted' => $inserted,
            'updated' => $updated,
        ];
    }

    /**
     * Save review edits posted by the Moodle review UI.
     *
     * @param array $rows
     * @return int Number of updated rows.
     */
    public static function save_review_rows(array $rows): int {
        global $DB, $USER;

        $updated = 0;
        $now = time();
        $userid = (int)($USER->id ?? 0);

        foreach ($rows as $id => $data) {
            if (!is_array($data)) {
                continue;
            }
            $id = clean_param((string)$id, PARAM_INT);
            if ($id <= 0 || !$DB->record_exists('flwtbi_review', ['id' => $id])) {
                continue;
            }

            $record = (object)[
                'id' => $id,
                'approved' => empty($data['approved']) ? 0 : 1,
                'cefr' => self::trim_text(clean_param((string)($data['cefr'] ?? ''), PARAM_ALPHANUMEXT), 20),
                'skill' => self::trim_text(clean_param((string)($data['skill'] ?? ''), PARAM_TEXT), 80),
                'kptags' => self::trim_text(clean_param((string)($data['kptags'] ?? ''), PARAM_TEXT), 2000),
                'notes' => self::trim_text(clean_param((string)($data['notes'] ?? ''), PARAM_TEXT), 4000),
                'timemodified' => $now,
                'usermodified' => $userid,
            ];
            $DB->update_record('flwtbi_review', $record);
            $updated++;
        }

        return $updated;
    }

    /**
     * Build the Moodle review and FLW handoff model for a dry-run package.
     *
     * @param array $package
     * @return array
     */
    public static function review_model(array $package): array {
        global $DB;

        self::validate_package($package);

        $course = $DB->get_record('course', ['shortname' => $package['course']['shortname']], '*', MUST_EXIST);
        $packagehash = self::package_hash($package);

        $records = $DB->get_records_sql(
            "SELECT r.*, cm.id AS cmid, cm.visible AS cmvisible, m.name AS existingmodule
               FROM {flwtbi_review} r
          LEFT JOIN {course_modules} cm
                 ON cm.course = r.courseid
                AND cm.idnumber = r.activityidnumber
          LEFT JOIN {modules} m
                 ON m.id = cm.module
              WHERE r.courseid = :courseid
                AND r.packagehash = :packagehash
           ORDER BY r.sectionnum ASC, r.activityindex ASC",
            ['courseid' => (int)$course->id, 'packagehash' => $packagehash]
        );

        $modulecounts = [];
        $modulerows = $DB->get_records_sql(
            "SELECT CONCAT(m.name, '-', cm.visible) AS rowkey,
                    m.name AS module,
                    cm.visible AS visible,
                    COUNT(1) AS total
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid
           GROUP BY m.name, cm.visible
           ORDER BY m.name ASC, cm.visible ASC",
            ['courseid' => (int)$course->id]
        );
        foreach ($modulerows as $row) {
            $modulecounts[] = [
                'module' => (string)$row->module,
                'visible' => (int)$row->visible,
                'total' => (int)$row->total,
            ];
        }

        $sectiontitles = [];
        foreach ($package['sections'] as $section) {
            $sectiontitles[(int)($section['section_number'] ?? 0)] = (string)($section['title'] ?? '');
        }

        $rows = [];
        $counts = [
            'sections' => count($package['sections']),
            'activities' => self::activity_count($package),
            'reviewrows' => 0,
            'approved' => 0,
            'imported' => 0,
            'visible' => 0,
            'kpready' => 0,
        ];

        foreach ($records as $record) {
            $counts['reviewrows']++;
            $approved = (int)$record->approved === 1;
            $imported = !empty($record->cmid);
            $visible = isset($record->cmvisible) && (int)$record->cmvisible === 1;
            if ($approved) {
                $counts['approved']++;
            }
            if ($imported) {
                $counts['imported']++;
            }
            if ($visible) {
                $counts['visible']++;
            }
            if (trim((string)$record->kptags) !== '') {
                $counts['kpready']++;
            }

            $range = json_decode((string)$record->sourcerange, true);
            $rows[] = [
                'id' => (int)$record->id,
                'sectionnum' => (int)$record->sectionnum,
                'sectiontitle' => $sectiontitles[(int)$record->sectionnum] ?? '',
                'activityindex' => (int)$record->activityindex,
                'activityidnumber' => (string)$record->activityidnumber,
                'name' => (string)$record->name,
                'moodlemodule' => (string)$record->moodlemodule,
                'activitytype' => (string)$record->activitytype,
                'sourcecomponent' => (string)$record->sourcecomponent,
                'sourcepdf' => (string)$record->sourcepdf,
                'sourcerange' => self::source_range_label(is_array($range) ? $range : []),
                'reviewstatus' => (string)$record->reviewstatus,
                'approved' => $approved,
                'cefr' => (string)$record->cefr,
                'skill' => (string)$record->skill,
                'kptags' => (string)$record->kptags,
                'notes' => (string)$record->notes,
                'cmid' => $imported ? (int)$record->cmid : 0,
                'cmvisible' => $visible,
                'existingmodule' => (string)($record->existingmodule ?? ''),
            ];
        }

        return [
            'course' => [
                'id' => (int)$course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'visible' => (int)$course->visible,
                'category_path' => (string)$package['course']['category_path'],
                'language' => (string)($package['course']['language'] ?? ''),
            ],
            'package' => [
                'hash' => $packagehash,
                'source' => $package['source'] ?? [],
                'license' => (string)($package['course']['source_license'] ?? ''),
            ],
            'counts' => $counts,
            'modulecounts' => $modulecounts,
            'rows' => $rows,
            'flw' => [
                'category_path' => (string)$package['course']['category_path'],
                'language' => (string)($package['course']['language'] ?? ''),
                'default_cefr' => self::infer_cefr($package, []),
                'kp_metadata_rows' => $counts['kpready'],
                'handoff_ready' => $counts['imported'] > 0 && $counts['kpready'] > 0,
            ],
        ];
    }

    /**
     * Create or update the course shell and section summaries.
     *
     * @param array $package
     * @param bool $reusecourse
     * @param bool $visible
     * @return array
     */
    public static function execute(array $package, bool $reusecourse = false, bool $visible = false): array {
        global $CFG, $DB, $PAGE;

        self::validate_package($package);

        require_once($CFG->dirroot . '/course/lib.php');

        \core\session\manager::set_user(get_admin());
        $PAGE->set_context(\context_system::instance());

        $summary = [
            'mode' => 'execute',
            'categoriescreated' => 0,
            'coursecreated' => 0,
            'courseupdated' => 0,
            'sectionscreated' => 0,
            'sectionsupdated' => 0,
            'activitiescreated' => 0,
            'activitiesleftasplan' => self::activity_count($package),
            'warnings' => [],
        ];

        $categoryid = self::ensure_category_path($package['course']['category_path'], $summary);
        $course = self::ensure_course($package, $categoryid, $reusecourse, $visible, $summary);

        $sectionnumbers = array_map(
            static fn(array $section): int => (int)$section['section_number'],
            $package['sections']
        );
        $beforesections = $DB->count_records('course_sections', ['course' => $course->id]);
        course_create_sections_if_missing($course, $sectionnumbers);
        $aftersections = $DB->count_records('course_sections', ['course' => $course->id]);
        $summary['sectionscreated'] = max(0, $aftersections - $beforesections);

        foreach ($package['sections'] as $sectiondata) {
            $sectionnumber = (int)$sectiondata['section_number'];
            $section = $DB->get_record(
                'course_sections',
                ['course' => $course->id, 'section' => $sectionnumber],
                '*',
                MUST_EXIST
            );
            $name = $sectionnumber === 0 ? null : self::trim_text((string)$sectiondata['title'], 255);
            $update = [
                'summary' => self::section_summary_html($sectiondata),
                'summaryformat' => FORMAT_HTML,
                'visible' => 1,
            ];
            if ($name !== null) {
                $update['name'] = $name;
            }
            course_update_section($course, $section, $update);
            $summary['sectionsupdated']++;
        }

        rebuild_course_cache($course->id, true);

        $summary['course'] = [
            'id' => (int)$course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'visible' => (int)$course->visible,
        ];
        $summary['warnings'][] = 'Activities were not created in this first execute step; use the dry-run activity CSV for review.';

        return $summary;
    }

    /**
     * Create hidden Moodle activities from a narrow reviewed subset of the dry-run package.
     *
     * @param array $package
     * @param int $sectionfilter Required section number to import.
     * @param array $modulefilters Moodle module names to allow.
     * @param array $reviewstatusfilters Review statuses to allow.
     * @param int $limit Maximum activities to create, 0 means no cap.
     * @param bool $reusemodules Skip existing modules with matching idnumber instead of failing.
     * @param bool $visible Whether new modules should be visible.
     * @return array
     */
    public static function create_activities(
        array $package,
        int $sectionfilter,
        array $modulefilters,
        array $reviewstatusfilters,
        int $limit = 0,
        bool $reusemodules = false,
        bool $visible = false
    ): array {
        global $CFG, $DB, $PAGE;

        self::validate_package($package);

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        \core\session\manager::set_user(get_admin());

        $course = $DB->get_record('course', ['shortname' => $package['course']['shortname']], '*', MUST_EXIST);
        $PAGE->set_context(\context_course::instance($course->id));

        $modulefilters = self::normalize_filters($modulefilters);
        $reviewstatusfilters = self::normalize_filters($reviewstatusfilters);

        $summary = [
            'mode' => 'create_activities',
            'course' => [
                'id' => (int)$course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'visible' => (int)$course->visible,
            ],
            'sectionfilter' => $sectionfilter,
            'modulefilters' => array_values($modulefilters),
            'reviewstatusfilters' => array_values($reviewstatusfilters),
            'limit' => $limit,
            'modulesvisible' => $visible,
            'activitiesconsidered' => 0,
            'activitiescreated' => 0,
            'activitiesupdated' => 0,
            'activitiesskipped' => 0,
            'activitiesfiltered' => 0,
            'activitiesunsupported' => 0,
            'created' => [],
            'skipped' => [],
            'warnings' => [],
        ];

        $section = self::find_package_section($package, $sectionfilter);
        if (!$section) {
            throw new \moodle_exception('sectionnotfound', 'local_flwtextbookimport', '', $sectionfilter);
        }

        $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => $sectionfilter],
            '*',
            MUST_EXIST
        );

        foreach ($section['activities'] as $index => $activity) {
            $summary['activitiesconsidered']++;
            $module = (string)($activity['moodle_module'] ?? '');
            $reviewstatus = (string)($activity['review_status'] ?? '');

            if (!in_array($module, self::SUPPORTED_ACTIVITY_MODULES, true)) {
                $summary['activitiesunsupported']++;
                continue;
            }
            if ($modulefilters && !in_array($module, $modulefilters, true)) {
                $summary['activitiesfiltered']++;
                continue;
            }
            if ($reviewstatusfilters && !in_array($reviewstatus, $reviewstatusfilters, true)) {
                $summary['activitiesfiltered']++;
                continue;
            }
            if ($limit > 0 && $summary['activitiescreated'] >= $limit) {
                $summary['activitiesfiltered']++;
                continue;
            }

            $idnumber = self::activity_idnumber($package, $sectionfilter, $index, $activity);
            $existingcm = self::find_course_module_by_idnumber((int)$course->id, $idnumber);
            if ($existingcm) {
                if (!$reusemodules) {
                    throw new \moodle_exception('activityidnumberexists', 'local_flwtextbookimport', '', $idnumber);
                }
                self::refresh_existing_activity($existingcm, $activity, $visible);
                $summary['activitiesupdated']++;
                $summary['activitiesskipped']++;
                $summary['skipped'][] = [
                    'idnumber' => $idnumber,
                    'name' => self::clean_import_text((string)$activity['name']),
                    'reason' => 'existing_module_refreshed',
                    'cmid' => (int)$existingcm->id,
                ];
                continue;
            }

            if ($module === 'page') {
                $created = self::create_page_activity($course, $sectionfilter, $activity, $idnumber, $visible);
            } else {
                $created = self::create_assign_activity($course, $sectionfilter, $activity, $idnumber, $visible);
            }

            $summary['activitiescreated']++;
            $summary['created'][] = $created;
        }

        rebuild_course_cache($course->id, true);
        if ($summary['activitiesunsupported'] > 0) {
            $summary['warnings'][] = 'Some planned activities were skipped because this phase only supports Page and Assignment modules.';
        }
        if ($summary['activitiesfiltered'] > 0) {
            $summary['warnings'][] = 'Some planned activities were skipped by section, module, status, or limit filters.';
        }

        return $summary;
    }

    /**
     * Replace imported placeholders with learner-ready lesson content.
     *
     * This composer is intentionally narrow while the pilot template is being
     * proven. It currently supports CKLA Grade 2 Unit 2 Lesson 1.
     *
     * @param array $package
     * @param int $sectionnumber
     * @param bool $visible Whether composed modules should be visible.
     * @return array
     */
    public static function compose_lesson_content(array $package, int $sectionnumber, bool $visible = false): array {
        global $CFG, $DB, $PAGE;

        self::validate_package($package);

        if ($sectionnumber !== 1) {
            throw new \moodle_exception('composersectionunsupported', 'local_flwtextbookimport', '', $sectionnumber);
        }

        require_once($CFG->dirroot . '/course/lib.php');

        \core\session\manager::set_user(get_admin());

        $course = $DB->get_record('course', ['shortname' => $package['course']['shortname']], '*', MUST_EXIST);
        $PAGE->set_context(\context_course::instance($course->id));

        $section = self::find_package_section($package, $sectionnumber);
        if (!$section) {
            throw new \moodle_exception('sectionnotfound', 'local_flwtextbookimport', '', $sectionnumber);
        }

        $summary = [
            'mode' => 'compose_lesson',
            'course' => [
                'id' => (int)$course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'visible' => (int)$course->visible,
            ],
            'section' => [
                'number' => $sectionnumber,
                'title' => (string)($section['title'] ?? ''),
            ],
            'modulesvisible' => $visible,
            'modulesconsidered' => 0,
            'modulesupdated' => 0,
            'modulesmissing' => 0,
            'modulesunsupported' => 0,
            'updated' => [],
            'missing' => [],
            'warnings' => [],
        ];

        $templates = self::lesson_one_templates($package, $section);

        foreach (($section['activities'] ?? []) as $index => $activity) {
            $idnumber = self::activity_idnumber($package, $sectionnumber, $index, $activity);
            if (!array_key_exists($idnumber, $templates)) {
                continue;
            }

            $summary['modulesconsidered']++;
            $template = $templates[$idnumber];
            $cm = self::find_course_module_by_idnumber((int)$course->id, $idnumber);
            if (!$cm) {
                $summary['modulesmissing']++;
                $summary['missing'][] = [
                    'idnumber' => $idnumber,
                    'name' => self::clean_import_text((string)($activity['name'] ?? '')),
                ];
                continue;
            }

            $modname = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
            if ($modname !== $template['module']) {
                $summary['modulesunsupported']++;
                continue;
            }

            $DB->set_field('course_modules', 'visible', $visible ? 1 : 0, ['id' => $cm->id]);
            $DB->set_field('course_modules', 'visibleold', $visible ? 1 : 0, ['id' => $cm->id]);

            if ($modname === 'page') {
                $DB->update_record('page', (object)[
                    'id' => (int)$cm->instance,
                    'name' => $template['name'],
                    'intro' => $template['intro'],
                    'introformat' => FORMAT_HTML,
                    'content' => $template['content'],
                    'contentformat' => FORMAT_HTML,
                    'timemodified' => time(),
                ]);
            } else if ($modname === 'assign') {
                $DB->update_record('assign', (object)[
                    'id' => (int)$cm->instance,
                    'name' => $template['name'],
                    'intro' => $template['intro'],
                    'introformat' => FORMAT_HTML,
                    'timemodified' => time(),
                ]);
            }

            $reviewrow = $DB->get_record('flwtbi_review', [
                'courseid' => (int)$course->id,
                'activityidnumber' => $idnumber,
            ], '*', IGNORE_MISSING);
            if ($reviewrow) {
                $reviewrow->approved = 1;
                $reviewrow->name = self::trim_text($template['name'], 255);
                $reviewrow->cefr = 'A1';
                $reviewrow->skill = $template['skill'];
                $reviewrow->kptags = $template['kptags'];
                $reviewrow->notes = self::trim_text($template['reviewnote'], 4000);
                $reviewrow->timemodified = time();
                $reviewrow->usermodified = (int)get_admin()->id;
                $DB->update_record('flwtbi_review', $reviewrow);
            }

            $summary['modulesupdated']++;
            $summary['updated'][] = [
                'cmid' => (int)$cm->id,
                'module' => $modname,
                'idnumber' => $idnumber,
                'name' => $template['name'],
                'visible' => $visible ? 1 : 0,
            ];
        }

        rebuild_course_cache($course->id, true);

        if ($summary['modulesmissing'] > 0) {
            $summary['warnings'][] = 'Some Lesson 1 placeholders are missing; run --create-activities for section 1 first.';
        }
        if ($summary['modulesunsupported'] > 0) {
            $summary['warnings'][] = 'Some Lesson 1 placeholders were skipped because their Moodle module type did not match the composer template.';
        }

        return $summary;
    }

    /**
     * Validate the dry-run package shape.
     *
     * @param array $package
     */
    private static function validate_package(array $package): void {
        if (($package['schema']['name'] ?? '') !== self::SCHEMA_NAME) {
            throw new \moodle_exception('invalidschema', 'local_flwtextbookimport', '', $package['schema']['name'] ?? '');
        }
        foreach (['course', 'source', 'validation', 'sections'] as $key) {
            if (!array_key_exists($key, $package)) {
                throw new \moodle_exception('missingpackagekey', 'local_flwtextbookimport', '', $key);
            }
        }
        foreach (['fullname', 'shortname', 'category_path'] as $key) {
            if (trim((string)($package['course'][$key] ?? '')) === '') {
                throw new \moodle_exception('missingcoursekey', 'local_flwtextbookimport', '', $key);
            }
        }
        if (!is_array($package['sections']) || count($package['sections']) === 0) {
            throw new \moodle_exception('missingsections', 'local_flwtextbookimport');
        }
        if (($package['validation']['live_moodle_write'] ?? null) !== false) {
            throw new \moodle_exception('unsafeinputpackage', 'local_flwtextbookimport');
        }
        if (($package['course']['writes_to_moodle'] ?? null) !== false) {
            throw new \moodle_exception('unsafeinputpackage', 'local_flwtextbookimport');
        }
    }

    /**
     * Find one section from package data.
     *
     * @param array $package
     * @param int $sectionnumber
     * @return array|null
     */
    private static function find_package_section(array $package, int $sectionnumber): ?array {
        foreach ($package['sections'] as $section) {
            if ((int)$section['section_number'] === $sectionnumber) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Ensure each category in a slash-separated path exists.
     *
     * @param string $categorypath
     * @param array $summary
     * @return int
     */
    private static function ensure_category_path(string $categorypath, array &$summary): int {
        global $DB;

        $parentid = 0;
        $parts = self::category_path_parts($categorypath);
        foreach ($parts as $index => $part) {
            $category = self::find_category($part, $parentid);
            if (!$category) {
                $idnumber = self::category_idnumber(array_slice($parts, 0, $index + 1));
                $data = [
                    'name' => $part,
                    'parent' => $parentid,
                    'visible' => 1,
                ];
                if (!$DB->record_exists('course_categories', ['idnumber' => $idnumber])) {
                    $data['idnumber'] = $idnumber;
                }
                $category = \core_course_category::create((object)$data);
                $summary['categoriescreated']++;
            }
            $parentid = (int)$category->id;
        }

        return $parentid;
    }

    /**
     * Create or update the target course shell.
     *
     * @param array $package
     * @param int $categoryid
     * @param bool $reusecourse
     * @param bool $visible
     * @param array $summary
     * @return \stdClass
     */
    private static function ensure_course(
        array $package,
        int $categoryid,
        bool $reusecourse,
        bool $visible,
        array &$summary
    ): \stdClass {
        global $DB;

        $courseinfo = $package['course'];
        $shortname = self::trim_text((string)$courseinfo['shortname'], 255);
        $fullname = self::trim_text((string)$courseinfo['fullname'], 254);
        $summaryhtml = self::course_summary_html($package);
        $existing = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);

        if ($existing && !$reusecourse) {
            throw new \moodle_exception('courseshortnameexists', 'local_flwtextbookimport', '', $shortname);
        }

        $idnumber = strtolower($shortname);
        if (!$existing && $DB->record_exists('course', ['idnumber' => $idnumber])) {
            $idnumber = '';
            $summary['warnings'][] = 'Course idnumber was left empty because the generated idnumber already exists.';
        }

        $data = [
            'category' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
            'idnumber' => $idnumber,
            'summary' => $summaryhtml,
            'summaryformat' => FORMAT_HTML,
            'format' => 'topics',
            'showgrades' => 1,
            'newsitems' => 0,
            'visible' => $visible ? 1 : 0,
            'enablecompletion' => 1,
            'showcompletionconditions' => 1,
            'startdate' => usergetmidnight(time()),
            'enddate' => 0,
        ];

        if (!$existing) {
            $course = create_course((object)$data);
            $summary['coursecreated']++;
            return $course;
        }

        $update = (object)['id' => (int)$existing->id];
        $changed = false;
        foreach ($data as $field => $value) {
            if ($field === 'shortname' || $field === 'idnumber') {
                continue;
            }
            if ((string)($existing->$field ?? '') !== (string)$value) {
                $update->$field = $value;
                $changed = true;
            }
        }
        if ($changed) {
            $update->timemodified = time();
            $DB->update_record('course', $update);
            $summary['courseupdated']++;
        }

        return $DB->get_record('course', ['id' => (int)$existing->id], '*', MUST_EXIST);
    }

    /**
     * Find a child category by parent and name.
     *
     * @param string $name
     * @param int $parentid
     * @return \stdClass|false
     */
    private static function find_category(string $name, int $parentid) {
        global $DB;

        if ($parentid < 0) {
            return false;
        }
        return $DB->get_record(
            'course_categories',
            ['parent' => $parentid, 'name' => $name],
            '*',
            IGNORE_MISSING
        );
    }

    /**
     * Split a category path such as "FLW / English / K-12".
     *
     * @param string $categorypath
     * @return array
     */
    private static function category_path_parts(string $categorypath): array {
        $parts = array_map('trim', explode('/', $categorypath));
        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
        if (!$parts) {
            throw new \moodle_exception('missingcategorypath', 'local_flwtextbookimport');
        }
        return $parts;
    }

    /**
     * Generate a stable category idnumber.
     *
     * @param array $parts
     * @return string
     */
    private static function category_idnumber(array $parts): string {
        $slug = strtolower(implode('-', array_map([self::class, 'slug'], $parts)));
        return self::trim_text('flw-tbi-' . $slug, 100);
    }

    /**
     * Build course summary HTML.
     *
     * @param array $package
     * @return string
     */
    private static function course_summary_html(array $package): string {
        $source = $package['source'];
        $validation = $package['validation'];
        $items = [
            'Source: ' . ($source['provider'] ?? 'Unknown') . ' - ' . ($source['curriculum'] ?? 'Unknown'),
            'Unit: ' . ($source['grade'] ?? '') . ' ' . ($source['unit'] ?? '') . ' - ' . ($source['unit_title'] ?? ''),
            'License: ' . ($source['license'] ?? 'Not specified'),
            'Sections planned: ' . ($validation['section_count'] ?? count($package['sections'])),
            'Activities left in review plan: ' . self::activity_count($package),
        ];
        return '<p>Imported by FLW textbook importer from a reviewed dry-run package.</p>' . self::html_list($items);
    }

    /**
     * Build section summary HTML.
     *
     * @param array $section
     * @return string
     */
    private static function section_summary_html(array $section): string {
        $items = [];
        if (!empty($section['source_range'])) {
            $range = $section['source_range'];
            $items[] = 'Teacher guide PDF pages: ' . $range['pdf_start_page'] . '-' . $range['pdf_end_page'];
            $items[] = 'Teacher guide printed pages: ' . $range['printed_start_page'] . '-' . $range['printed_end_page'];
        }
        $items[] = 'Planned activities: ' . count($section['activities']);
        $statuses = [];
        foreach ($section['activities'] as $activity) {
            $status = (string)($activity['review_status'] ?? 'unknown');
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }
        foreach ($statuses as $status => $count) {
            $items[] = $status . ': ' . $count;
        }

        $html = '<p>' . s($section['summary'] ?? 'Imported textbook section pending teacher review.') . '</p>';
        $html .= self::html_list($items);
        $html .= '<p><strong>Activity creation is intentionally deferred.</strong> Review the generated activity CSV before creating Moodle modules.</p>';
        return $html;
    }

    /**
     * Count planned activities.
     *
     * @param array $package
     * @return int
     */
    private static function activity_count(array $package): int {
        $count = 0;
        foreach ($package['sections'] as $section) {
            $count += count($section['activities'] ?? []);
        }
        return $count;
    }

    /**
     * Create a Moodle Page module from a planned activity.
     *
     * @param \stdClass $course
     * @param int $sectionnumber
     * @param array $activity
     * @param string $idnumber
     * @param bool $visible
     * @return array
     */
    private static function create_page_activity(
        \stdClass $course,
        int $sectionnumber,
        array $activity,
        string $idnumber,
        bool $visible
    ): array {
        global $DB;

        $module = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
        $moduleinfo = (object)[
            'modulename' => 'page',
            'module' => (int)$module->id,
            'section' => $sectionnumber,
            'name' => self::trim_text(self::clean_import_text((string)$activity['name']), 255),
            'intro' => self::activity_intro_html($activity),
            'introformat' => FORMAT_HTML,
            'content' => self::page_content_html($activity),
            'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_OPEN,
            'printintro' => 1,
            'printlastmodified' => 0,
            'visible' => $visible ? 1 : 0,
            'visibleoncoursepage' => 1,
            'showdescription' => 0,
            'cmidnumber' => $idnumber,
        ];

        $created = add_moduleinfo($moduleinfo, $course);
        return [
            'cmid' => (int)$created->coursemodule,
            'module' => 'page',
            'idnumber' => $idnumber,
            'name' => $moduleinfo->name,
            'visible' => $moduleinfo->visible,
        ];
    }

    /**
     * Create a Moodle Assignment module from a planned activity.
     *
     * @param \stdClass $course
     * @param int $sectionnumber
     * @param array $activity
     * @param string $idnumber
     * @param bool $visible
     * @return array
     */
    private static function create_assign_activity(
        \stdClass $course,
        int $sectionnumber,
        array $activity,
        string $idnumber,
        bool $visible
    ): array {
        global $CFG, $DB;

        $module = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);
        $moduleinfo = (object)[
            'modulename' => 'assign',
            'module' => (int)$module->id,
            'section' => $sectionnumber,
            'name' => self::trim_text(self::clean_import_text((string)$activity['name']), 255),
            'intro' => self::activity_intro_html($activity),
            'introformat' => FORMAT_HTML,
            'visible' => $visible ? 1 : 0,
            'visibleoncoursepage' => 1,
            'showdescription' => 0,
            'cmidnumber' => $idnumber,
            'alwaysshowdescription' => 1,
            'submissiondrafts' => 0,
            'requiresubmissionstatement' => 0,
            'sendnotifications' => 0,
            'sendlatenotifications' => 0,
            'sendstudentnotifications' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
            'allowsubmissionsfromdate' => 0,
            'grade' => 100,
            'completionsubmit' => 0,
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'teamsubmissiongroupingid' => 0,
            'blindmarking' => 0,
            'hidegrader' => 0,
            'maxattempts' => 1,
            'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_NONE,
            'preventsubmissionnotingroup' => 0,
            'markingworkflow' => 0,
            'markingallocation' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_onlinetext_wordlimit' => 0,
            'assignsubmission_onlinetext_wordlimit_enabled' => 0,
            'assignsubmission_file_enabled' => 0,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => (int)$CFG->maxbytes,
            'assignsubmission_file_filetypes' => '',
            'assignfeedback_comments_enabled' => 1,
            'assignfeedback_comments_commentinline' => 0,
        ];

        $created = add_moduleinfo($moduleinfo, $course);
        return [
            'cmid' => (int)$created->coursemodule,
            'module' => 'assign',
            'idnumber' => $idnumber,
            'name' => $moduleinfo->name,
            'visible' => $moduleinfo->visible,
        ];
    }

    /**
     * Refresh an existing generated module when --reuse-modules is supplied.
     *
     * @param \stdClass $cm
     * @param array $activity
     * @param bool $visible
     */
    private static function refresh_existing_activity(\stdClass $cm, array $activity, bool $visible): void {
        global $DB;

        $modname = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
        $name = self::trim_text(self::clean_import_text((string)$activity['name']), 255);
        $intro = self::activity_intro_html($activity);

        $DB->set_field('course_modules', 'visible', $visible ? 1 : 0, ['id' => $cm->id]);
        $DB->set_field('course_modules', 'visibleold', $visible ? 1 : 0, ['id' => $cm->id]);

        if ($modname === 'page') {
            $record = (object)[
                'id' => (int)$cm->instance,
                'name' => $name,
                'intro' => $intro,
                'introformat' => FORMAT_HTML,
                'content' => self::page_content_html($activity),
                'contentformat' => FORMAT_HTML,
                'timemodified' => time(),
            ];
            $DB->update_record('page', $record);
        } else if ($modname === 'assign') {
            $record = (object)[
                'id' => (int)$cm->instance,
                'name' => $name,
                'intro' => $intro,
                'introformat' => FORMAT_HTML,
                'timemodified' => time(),
            ];
            $DB->update_record('assign', $record);
        }
    }

    /**
     * Decide whether a planned activity should be approved by default in the review table.
     *
     * @param array $activity
     * @return int
     */
    private static function default_review_approved(array $activity): int {
        $module = strtolower((string)($activity['moodle_module'] ?? ''));
        $status = strtolower((string)($activity['review_status'] ?? ''));

        return in_array($module, self::SUPPORTED_ACTIVITY_MODULES, true) &&
            in_array($status, ['needs_teacher_review', 'needs_activity_review'], true) ? 1 : 0;
    }

    /**
     * Infer the CEFR level for the pilot row.
     *
     * @param array $package
     * @param array $activity
     * @return string
     */
    private static function infer_cefr(array $package, array $activity): string {
        $course = strtolower((string)($package['course']['shortname'] ?? '') . ' ' .
            ($package['source']['grade'] ?? '') . ' ' . ($package['course']['fullname'] ?? ''));

        if (strpos($course, 'grade 2') !== false || strpos($course, 'g2') !== false) {
            return 'A1';
        }

        return 'A1';
    }

    /**
     * Infer a broad FLW skill bucket from planned activity metadata.
     *
     * @param array $activity
     * @return string
     */
    private static function infer_skill(array $activity): string {
        $text = strtolower(implode(' ', [
            $activity['name'] ?? '',
            $activity['activity_type'] ?? '',
            $activity['source_component'] ?? '',
            $activity['notes'] ?? '',
        ]));

        if (strpos($text, 'teacher') !== false || strpos($text, 'lesson') !== false) {
            return 'teacher_plan';
        }
        if (strpos($text, 'reader') !== false || strpos($text, 'read') !== false) {
            return 'reading';
        }
        if (strpos($text, 'word') !== false || strpos($text, 'vocab') !== false ||
                strpos($text, 'tricky') !== false || strpos($text, 'code') !== false) {
            return 'phonics_vocabulary';
        }
        if (strpos($text, 'quiz') !== false || strpos($text, 'assessment') !== false) {
            return 'assessment';
        }
        if (strpos($text, 'workbook') !== false || strpos($text, 'worksheet') !== false ||
                strpos($text, 'writing') !== false) {
            return 'reading_writing';
        }

        return 'language_practice';
    }

    /**
     * Build editable starter KP tags for FLW review handoff.
     *
     * @param array $package
     * @param int $sectionnumber
     * @param array $activity
     * @return string
     */
    private static function default_kp_tags(array $package, int $sectionnumber, array $activity): string {
        $source = $package['source'] ?? [];
        $tags = [
            'language:' . self::slug((string)($package['course']['language'] ?? 'english')),
            'cefr:' . strtolower(self::infer_cefr($package, $activity)),
            'grade:' . self::slug((string)($source['grade'] ?? 'grade-2')),
            'unit:' . self::slug((string)($source['unit'] ?? 'unit-2')),
            'lesson:' . $sectionnumber,
            'skill:' . self::slug(self::infer_skill($activity)),
        ];

        $component = (string)($activity['source_component'] ?? '');
        if ($component !== '') {
            $tags[] = 'source:' . self::slug($component);
        }

        return implode(', ', $tags);
    }

    /**
     * Format stored source page metadata for the review table.
     *
     * @param array $range
     * @return string
     */
    private static function source_range_label(array $range): string {
        if (!$range) {
            return '';
        }

        $items = [];
        if (isset($range['pdf_start_page']) || isset($range['pdf_end_page'])) {
            $items[] = 'PDF ' . ($range['pdf_start_page'] ?? '?') . '-' . ($range['pdf_end_page'] ?? '?');
        }
        if (isset($range['printed_start_page']) || isset($range['printed_end_page'])) {
            $items[] = 'Printed ' . ($range['printed_start_page'] ?? '?') . '-' .
                ($range['printed_end_page'] ?? '?');
        }

        return implode('; ', $items);
    }

    /**
     * Find an existing course module by generated idnumber.
     *
     * @param int $courseid
     * @param string $idnumber
     * @return \stdClass|false
     */
    private static function find_course_module_by_idnumber(int $courseid, string $idnumber) {
        global $DB;

        return $DB->get_record(
            'course_modules',
            ['course' => $courseid, 'idnumber' => $idnumber],
            '*',
            IGNORE_MISSING
        );
    }

    /**
     * Generate a stable idnumber for a planned activity.
     *
     * @param array $package
     * @param int $sectionnumber
     * @param int $activityindex
     * @param array $activity
     * @return string
     */
    private static function activity_idnumber(array $package, int $sectionnumber, int $activityindex, array $activity): string {
        $base = implode('-', [
            strtolower((string)$package['course']['shortname']),
            's' . $sectionnumber,
            $activity['moodle_module'] ?? 'mod',
            $activityindex + 1,
            self::slug((string)($activity['name'] ?? 'activity')),
        ]);
        return self::trim_text($base, 100);
    }

    /**
     * Build module intro HTML from activity metadata.
     *
     * @param array $activity
     * @return string
     */
    private static function activity_intro_html(array $activity): string {
        $items = self::activity_source_items($activity);
        $html = '<p>Generated by FLW textbook importer for teacher review.</p>';
        $html .= self::html_list($items);
        if (!empty($activity['notes'])) {
            $html .= '<p><strong>Review note:</strong> ' . s((string)$activity['notes']) . '</p>';
        }
        return $html;
    }

    /**
     * Build Page content HTML from activity metadata.
     *
     * @param array $activity
     * @return string
     */
    private static function page_content_html(array $activity): string {
        $html = '<h3>Teacher Review Placeholder</h3>';
        $html .= '<p>This page was created from the dry-run textbook import plan. It intentionally contains source metadata, not copied textbook content.</p>';
        $html .= self::html_list(self::activity_source_items($activity));
        $html .= '<h3>Next Review Action</h3>';
        $html .= '<p>Open the source PDF range, approve the learner-visible wording, and replace this placeholder with the final FLW lesson content.</p>';
        return $html;
    }

    /**
     * Convert activity source metadata to readable list items.
     *
     * @param array $activity
     * @return array
     */
    private static function activity_source_items(array $activity): array {
        $items = [
            'Source component: ' . ($activity['source_component'] ?? 'unknown'),
            'Source PDF: ' . (($activity['source_pdf'] ?? '') !== '' ? $activity['source_pdf'] : 'derived'),
            'Review status: ' . ($activity['review_status'] ?? 'unknown'),
        ];
        if (!empty($activity['source_range'])) {
            $range = $activity['source_range'];
            $items[] = 'PDF pages: ' . ($range['pdf_start_page'] ?? '?') . '-' . ($range['pdf_end_page'] ?? '?');
            $items[] = 'Printed pages: ' . ($range['printed_start_page'] ?? '?') . '-' . ($range['printed_end_page'] ?? '?');
        }
        return $items;
    }

    /**
     * Remove common PDF extraction spacing artifacts from imported labels.
     *
     * @param string $value
     * @return string
     */
    private static function clean_import_text(string $value): string {
        $value = str_replace("\x00", '', $value);
        $value = preg_replace('/\bT\s+(ales|ricky|ricked|est|esting|elling|eacher|otal)\b/', 'T$1', $value);
        $value = preg_replace('/\bW\s+(ord|ords|orkbook|orksheet|orksheet|or)\b/', 'W$1', $value);
        $value = preg_replace('/\s+/', ' ', (string)$value);
        return trim((string)$value);
    }

    /**
     * Normalize comma-separated style filters.
     *
     * @param array $filters
     * @return array
     */
    private static function normalize_filters(array $filters): array {
        $normalized = [];
        foreach ($filters as $filter) {
            $filter = trim(strtolower((string)$filter));
            if ($filter !== '') {
                $normalized[] = $filter;
            }
        }
        return array_values(array_unique($normalized));
    }

    /**
     * Format a list as escaped HTML.
     *
     * @param array $items
     * @return string
     */
    private static function html_list(array $items): string {
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . s((string)$item) . '</li>';
        }
        return $html . '</ul>';
    }

    /**
     * Create a URL/id-safe slug.
     *
     * @param string $value
     * @return string
     */
    private static function slug(string $value): string {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        return trim((string)$value, '-') ?: 'item';
    }

    /**
     * Trim text safely for Moodle DB fields.
     *
     * @param string $value
     * @param int $length
     * @return string
     */
    private static function trim_text(string $value, int $length): string {
        return trim(\core_text::substr($value, 0, $length));
    }
}

<?php
// Output hook callbacks for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Output hook callbacks for C-UP-KP course-page helpers.
 */
final class output_hooks {
    /**
     * Adds course-page C-UP-KP styling and role-aware visibility rules.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $DB, $PAGE;

        $courseid = self::course_page_id();
        if ($courseid === null) {
            return;
        }

        if (!self::course_units($courseid)) {
            return;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        $hook->add_html(\html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => (new \moodle_url('/local/flwcupkp/styles.css'))->out(false),
        ]));

        $css = '.local-flwcupkp-course-links .local-flwcupkp-teacher-only{display:none!important;}';
        if (has_capability('local/flwcupkp:viewreports', $context)) {
            $css .= '.local-flwcupkp-course-links .local-flwcupkp-teacher-only{display:inline-block!important;}';
        }

        $hook->add_html(\html_writer::tag('style', $css));
    }

    /**
     * Injects role-aware C-UP-KP unit cards into the course page.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook,
    ): void {
        global $PAGE, $USER;

        $courseid = self::course_page_id();
        if ($courseid === null || isguestuser() || !isloggedin()) {
            return;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !has_capability('local/flwcupkp:viewlearnerpath', $context)) {
            return;
        }

        $units = self::course_units($courseid);
        if (!$units) {
            return;
        }

        if (has_capability('local/flwcupkp:viewreports', $context)) {
            $cardhtml = self::teacher_course_block($courseid, $units, $context);
            self::inject_course_block($hook, $cardhtml);
            return;
        }

        $cardhtml = self::student_course_block($courseid, $units, (int)$USER->id);
        self::inject_course_block($hook, $cardhtml);
    }

    /**
     * Resolve the current course page ID, or null outside mapped course pages.
     *
     * @return int|null
     */
    private static function course_page_id(): ?int {
        global $PAGE;

        if (empty($PAGE->course->id) || !str_starts_with((string)$PAGE->pagetype, 'course-view-')) {
            return null;
        }

        return (int)$PAGE->course->id;
    }

    /**
     * Unit codes with mapped objects in this course.
     *
     * @param int $courseid
     * @return array
     */
    private static function course_units(int $courseid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT unitcode
               FROM {flwcupkp_object}
              WHERE courseid = :courseid
                AND unitcode IS NOT NULL
                AND unitcode <> ''
           ORDER BY unitcode ASC",
            ['courseid' => $courseid]
        );

        $units = [];
        foreach ($records as $record) {
            $units[] = (string)$record->unitcode;
        }
        return $units;
    }

    /**
     * Replace the legacy/static course summary block with generated unit cards.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @param string $html
     */
    private static function inject_course_block(
        \core\hook\output\before_footer_html_generation $hook,
        string $html
    ): void {
        $encoded = json_encode($html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $hook->add_html(\html_writer::script(
            "(function(){var html={$encoded};" .
            "var target=document.querySelector('.local-flwcupkp-course-links');" .
            "if(target){target.outerHTML=html;return;}" .
            "var region=document.querySelector('#region-main')||document.querySelector('[role=\"main\"]')||document.body;" .
            "if(region){region.insertAdjacentHTML('afterbegin',html);}}());"
        ));
    }

    /**
     * Build the student-facing course block for all mapped units.
     *
     * @param int $courseid
     * @param array $units
     * @param int $userid
     * @return string
     */
    private static function student_course_block(int $courseid, array $units, int $userid): string {
        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-course-links',
            'data-flwcupkp-course-units' => '1',
        ]);
        $html .= \html_writer::tag('strong', get_string('coursecupkpunits', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-unit-grid']);
        foreach ($units as $unitcode) {
            $progress = $unitcode === 'U038' ?
                student_report::u038_progress($courseid, $userid) :
                unit_report::student_progress($courseid, $unitcode, $userid);
            $html .= self::student_unit_card($courseid, $unitcode, $progress);
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build the teacher-facing course block for all mapped units.
     *
     * @param int $courseid
     * @param array $units
     * @param \context_course $context
     * @return string
     */
    private static function teacher_course_block(int $courseid, array $units, \context_course $context): string {
        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-course-links',
            'data-flwcupkp-course-units' => '1',
        ]);
        $html .= \html_writer::tag('strong', get_string('coursecupkpunits', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-unit-grid']);
        foreach ($units as $unitcode) {
            $html .= $unitcode === 'U038' ?
                self::teacher_overview_card($courseid) :
                self::generic_teacher_overview_card($courseid, $unitcode);
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build one student next-action card for a mapped unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param array $progress
     * @return string
     */
    private static function student_unit_card(int $courseid, string $unitcode, array $progress): string {
        $summary = $progress['summary'];
        $next = $progress['next_recommendation'] ?? null;
        $progressurl = self::student_url($courseid, $unitcode);

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-course-next-card']);
        $html .= \html_writer::tag('div', get_string('coursenextactionunit', 'local_flwcupkp', $unitcode), [
            'class' => 'local-flwcupkp-course-next-label',
        ]);
        $html .= \html_writer::tag('div', get_string('unitprogress', 'local_flwcupkp') . ': ' .
            (int)$summary['percent'] . '%', ['class' => 'local-flwcupkp-course-next-title']);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-progressbar', 'aria-hidden' => 'true']);
        $html .= \html_writer::tag('span', '', ['style' => 'width: ' . (int)$summary['percent'] . '%']);
        $html .= \html_writer::end_tag('div');

        if ($next) {
            $externalid = (string)($next['kp_externalid'] ?? $next['externalid'] ?? '');
            $title = (string)($next['kp_title'] ?? $next['title'] ?? '');
            $html .= \html_writer::tag('div', s($externalid) . ' - ' . s($title), [
                'class' => 'local-flwcupkp-course-next-focus',
            ]);
            if (!empty($next['next_activity']['reason'])) {
                $html .= \html_writer::tag('p', s($next['next_activity']['reason']));
            }
            if (!empty($next['next_activity']['url'])) {
                $html .= \html_writer::link($next['next_activity']['url'], s($next['next_activity']['title']), [
                    'class' => 'btn btn-primary btn-sm',
                ]);
            }
        } else {
            $html .= \html_writer::tag('p', get_string('courseallmasteredunit', 'local_flwcupkp', $unitcode));
        }

        $html .= \html_writer::tag('div',
            get_string('mastered', 'local_flwcupkp') . ': ' . (int)$summary['mastered'] . ' / ' .
            (int)$summary['total'] . ' | ' . get_string('needpractice', 'local_flwcupkp') . ': ' .
            (int)$summary['gaps'],
            ['class' => 'local-flwcupkp-course-next-meta']
        );
        $html .= \html_writer::tag('div',
            \html_writer::link($progressurl, get_string('courseprogresslinkunit', 'local_flwcupkp', $unitcode), [
                'class' => 'btn btn-secondary btn-sm',
            ]),
            ['class' => 'local-flwcupkp-formactions']
        );
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build the generic teacher course-page overview card.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return string
     */
    private static function generic_teacher_overview_card(int $courseid, string $unitcode): string {
        $learners = unit_report::learners($courseid, $unitcode);
        $targets = unit_report::unit_targets($courseid, $unitcode);
        $overview = unit_report::mastery_overview($courseid, $unitcode);
        $queues = unit_report::parent_queue_summary($courseid, $unitcode);
        $strong = 0;
        $weak = 0;
        $evidence = 0;

        foreach ($targets as $target) {
            $stats = unit_report::target_stats($target, $learners);
            $strong += (int)$stats['strong'];
            $weak += (int)$stats['weak'];
            $evidence += (int)$stats['evidence'];
        }

        $totalrows = $strong + $weak;
        $percent = $totalrows > 0 ? round(($strong / $totalrows) * 100) : 0;
        $parentsummary = $overview['summary'];
        $competencygroup = (int)$parentsummary['competency_achieved'] < (int)$parentsummary['competency_total'] ?
            'notachieved' : 'achieved';
        $upgroup = (int)$parentsummary['up_demonstrated'] < (int)$parentsummary['up_total'] ?
            'notdemonstrated' : 'demonstrated';
        $competencyurl = self::unit_parent_overview_url($courseid, $unitcode, 'competency', $competencygroup,
            $overview['rows'], $competencygroup === 'notachieved');
        $upurl = self::unit_parent_overview_url($courseid, $unitcode, 'up', $upgroup, $overview['rows'],
            $upgroup === 'notdemonstrated');
        $competencyqueueurl = self::unit_parent_overview_url($courseid, $unitcode, 'competency', 'notachieved',
            $overview['rows'], true);
        $upqueueurl = self::unit_parent_overview_url($courseid, $unitcode, 'up', 'notdemonstrated',
            $overview['rows'], true);

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-course-teacher-card']);
        $html .= \html_writer::tag('div', get_string('courseteacherunitoverview', 'local_flwcupkp', $unitcode), [
            'class' => 'local-flwcupkp-course-next-label',
        ]);
        $html .= \html_writer::tag('div', get_string('classmastery', 'local_flwcupkp') . ': ' . $percent . '%', [
            'class' => 'local-flwcupkp-course-next-title',
        ]);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-progressbar', 'aria-hidden' => 'true']);
        $html .= \html_writer::tag('span', '', ['style' => 'width: ' . $percent . '%']);
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-overview-grid']);
        $html .= self::overview_stat(get_string('learners', 'local_flwcupkp'), count($learners));
        $html .= self::overview_stat(get_string('targets', 'local_flwcupkp'), count($targets));
        $html .= self::overview_ratio_stat(get_string('competenciesachieved', 'local_flwcupkp'),
            (int)$parentsummary['competency_achieved'], (int)$parentsummary['competency_total'], $competencyurl);
        $html .= self::overview_ratio_stat(get_string('upsdemonstrated', 'local_flwcupkp'),
            (int)$parentsummary['up_demonstrated'], (int)$parentsummary['up_total'], $upurl);
        $html .= self::overview_stat(get_string('competencyqueue', 'local_flwcupkp'),
            (int)$queues['competency']['count'], $competencyqueueurl);
        $html .= self::overview_stat(get_string('upqueue', 'local_flwcupkp'), (int)$queues['up']['count'], $upqueueurl);
        $html .= self::overview_stat(get_string('withevidence', 'local_flwcupkp'), $evidence);
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
        $html .= \html_writer::link(self::student_url($courseid, $unitcode),
            get_string('courseprogresslinkunit', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-secondary btn-sm']);
        $html .= \html_writer::link(self::teacher_url($courseid, $unitcode),
            get_string('courseteacherlinkunit', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-primary btn-sm']);
        if (performance_service::has_tasks($courseid, $unitcode)) {
            $html .= \html_writer::link(self::performance_url($courseid, $unitcode),
                get_string('unitperformancenav', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-secondary btn-sm']);
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Student progress URL for a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return \moodle_url
     */
    private static function student_url(int $courseid, string $unitcode): \moodle_url {
        return $unitcode === 'U038' ?
            new \moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]) :
            new \moodle_url('/local/flwcupkp/student.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
    }

    /**
     * Teacher overview URL for a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return \moodle_url
     */
    private static function teacher_url(int $courseid, string $unitcode): \moodle_url {
        return $unitcode === 'U038' ?
            new \moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]) :
            new \moodle_url('/local/flwcupkp/teacher.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
    }

    /**
     * Performance assessment URL for a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return \moodle_url
     */
    private static function performance_url(int $courseid, string $unitcode): \moodle_url {
        return new \moodle_url('/local/flwcupkp/performance.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]);
    }

    /**
     * Build a filtered generic parent overview URL.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @param string $stategroup
     * @param array $rows
     * @param bool $reviewonly
     * @return \moodle_url
     */
    private static function unit_parent_overview_url(int $courseid, string $unitcode, string $targettype,
            string $stategroup, array $rows, bool $reviewonly): \moodle_url {
        $url = self::teacher_url($courseid, $unitcode);
        $url->param('targettype', $targettype);
        $url->param('parentstate', $stategroup);
        if ($reviewonly) {
            $url->param('parentreview', 'review');
        }

        $anchor = self::first_unit_parent_row_anchor($rows, $targettype, $stategroup, $reviewonly);
        if ($anchor !== null) {
            $url->param('focus', $anchor);
            $url->set_anchor('flwcupkp-parent-row-' . $anchor);
        } else {
            $url->set_anchor($unitcode === 'U038' ? 'flwcupkp-u038-mastery-overview' : 'flwcupkp-unit-parent-overview');
        }

        return $url;
    }

    /**
     * Find the first parent row anchor matching a generic unit metric link.
     *
     * @param array $rows
     * @param string $targettype
     * @param string $stategroup
     * @param bool $reviewonly
     * @return string|null
     */
    private static function first_unit_parent_row_anchor(array $rows, string $targettype, string $stategroup,
            bool $reviewonly): ?string {
        foreach ($rows as $row) {
            if ((string)$row['targettype'] !== $targettype) {
                continue;
            }
            if (!unit_report::matches_parent_state_group($row, $stategroup)) {
                continue;
            }
            if ($reviewonly && !unit_report::matches_parent_review_filter($row, 'review')) {
                continue;
            }
            return unit_report::parent_row_anchor($row);
        }

        return null;
    }

    /**
     * Build the student next-action card for the course page.
     *
     * @param int $courseid
     * @param array $progress
     * @return string
     */
    private static function next_action_card(int $courseid, array $progress): string {
        $summary = $progress['summary'];
        $next = $progress['next_recommendation'];

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-course-next-card']);
        $html .= \html_writer::tag('div', get_string('coursenextactionu038', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-course-next-label',
        ]);
        $html .= \html_writer::tag('div', get_string('unitprogress', 'local_flwcupkp') . ': ' .
            (int)$summary['percent'] . '%', ['class' => 'local-flwcupkp-course-next-title']);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-progressbar', 'aria-hidden' => 'true']);
        $html .= \html_writer::tag('span', '', ['style' => 'width: ' . (int)$summary['percent'] . '%']);
        $html .= \html_writer::end_tag('div');

        if ($next) {
            $html .= \html_writer::tag('div', s($next['kp_externalid']) . ' - ' . s($next['kp_title']), [
                'class' => 'local-flwcupkp-course-next-focus',
            ]);
            if (!empty($next['next_activity']['reason'])) {
                $html .= \html_writer::tag('p', s($next['next_activity']['reason']));
            }
            if (!empty($next['next_activity']['url'])) {
                $html .= \html_writer::link($next['next_activity']['url'], s($next['next_activity']['title']), [
                    'class' => 'btn btn-primary btn-sm',
                ]);
            }
        } else {
            $html .= \html_writer::tag('p', get_string('courseallmasteredu038', 'local_flwcupkp'));
            $html .= \html_writer::link(new \moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]),
                get_string('courseprogresslinku038', 'local_flwcupkp'), ['class' => 'btn btn-primary btn-sm']);
        }

        $html .= \html_writer::tag('div',
            get_string('mastered', 'local_flwcupkp') . ': ' . (int)$summary['mastered'] . ' / ' .
            (int)$summary['total'] . ' | ' . get_string('needpractice', 'local_flwcupkp') . ': ' .
            (int)$summary['gaps'],
            ['class' => 'local-flwcupkp-course-next-meta']
        );
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build the teacher course-page overview card.
     *
     * @param int $courseid
     * @return string
     */
    private static function teacher_overview_card(int $courseid): string {
        $report = teacher_report::u038_report($courseid);
        $overview = teacher_report::u038_mastery_overview($courseid);
        $competencyqueue = teacher_report::u038_mastery_overview($courseid, [
            'targettype' => 'competency',
            'stategroup' => 'notachieved',
            'parentreview' => 'review',
        ]);
        $upqueue = teacher_report::u038_mastery_overview($courseid, [
            'targettype' => 'up',
            'stategroup' => 'notdemonstrated',
            'parentreview' => 'review',
        ]);
        $rows = $report['rows'];
        $learners = $report['learners'];
        $targets = $report['targets'];
        $parentsummary = $overview['summary'];
        $mastered = 0;
        $withevidence = 0;
        $verified = 0;
        $reviewanchor = null;

        foreach ($rows as $row) {
            if ($row['state'] === 'mastered') {
                $mastered++;
            }
            $hasevidence = !empty($row['evidence_id']);
            $isverified = !empty($row['verification']) &&
                    in_array($row['verification']['action'], ['teacher_evidence_approved', 'teacher_state_overridden'], true);

            if ($hasevidence) {
                $withevidence++;
            }
            if ($isverified) {
                $verified++;
            }
            if ($hasevidence && !$isverified && $reviewanchor === null) {
                $reviewanchor = self::teacher_row_anchor($row);
            }
        }

        $totalrows = count($rows);
        $review = max(0, $withevidence - $verified);
        $percent = $totalrows > 0 ? round(($mastered / $totalrows) * 100) : 0;
        $verificationurl = new \moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]);
        $competencygroup = (int)$parentsummary['competency_achieved'] < (int)$parentsummary['competency_total'] ?
            'notachieved' : 'achieved';
        $upgroup = (int)$parentsummary['up_demonstrated'] < (int)$parentsummary['up_total'] ?
            'notdemonstrated' : 'demonstrated';
        $competencyreview = $competencygroup === 'notachieved';
        $upreview = $upgroup === 'notdemonstrated';
        $competencyurl = self::parent_overview_url($courseid, 'competency', $competencygroup, $overview['rows'],
            $competencyreview);
        $upurl = self::parent_overview_url($courseid, 'up', $upgroup, $overview['rows'], $upreview);
        $competencyqueueurl = self::parent_overview_url($courseid, 'competency', 'notachieved', $overview['rows'],
            true);
        $upqueueurl = self::parent_overview_url($courseid, 'up', 'notdemonstrated', $overview['rows'], true);
        $withevidenceurl = new \moodle_url('/local/flwcupkp/teacher_u038.php', [
            'courseid' => $courseid,
            'evidence' => 'with',
        ]);
        $verifiedurl = new \moodle_url('/local/flwcupkp/teacher_u038.php', [
            'courseid' => $courseid,
            'evidence' => 'verified',
        ]);
        $performanceurl = self::performance_url($courseid, 'U038');
        $progressurl = new \moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]);
        $reviewurl = new \moodle_url('/local/flwcupkp/teacher_u038.php', [
            'courseid' => $courseid,
            'evidence' => 'review',
        ]);
        if ($reviewanchor !== null) {
            $reviewurl->param('focus', $reviewanchor);
            $reviewurl->set_anchor('flwcupkp-row-' . $reviewanchor);
        }

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-course-teacher-card']);
        $html .= \html_writer::tag('div', get_string('courseteacheroverviewu038', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-course-next-label',
        ]);
        $html .= \html_writer::tag('div', get_string('classmasteryu038', 'local_flwcupkp') . ': ' . $percent . '%', [
            'class' => 'local-flwcupkp-course-next-title',
        ]);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-progressbar', 'aria-hidden' => 'true']);
        $html .= \html_writer::tag('span', '', ['style' => 'width: ' . $percent . '%']);
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-course-overview-grid']);
        $html .= self::overview_stat(get_string('learners', 'local_flwcupkp'), count($learners));
        $html .= self::overview_stat(get_string('learningpoints', 'local_flwcupkp'), count($targets));
        $html .= self::overview_ratio_stat(get_string('competenciesachieved', 'local_flwcupkp'),
            (int)$parentsummary['competency_achieved'], (int)$parentsummary['competency_total'], $competencyurl);
        $html .= self::overview_ratio_stat(get_string('upsdemonstrated', 'local_flwcupkp'),
            (int)$parentsummary['up_demonstrated'], (int)$parentsummary['up_total'], $upurl);
        $html .= self::overview_stat(get_string('competencyqueueu038', 'local_flwcupkp'),
            count($competencyqueue['rows']), $competencyqueueurl);
        $html .= self::overview_stat(get_string('upqueueu038', 'local_flwcupkp'), count($upqueue['rows']), $upqueueurl);
        $html .= self::overview_stat(get_string('withevidence', 'local_flwcupkp'), $withevidence, $withevidenceurl);
        $html .= self::overview_stat(get_string('teacherverified', 'local_flwcupkp'), $verified, $verifiedurl);
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::tag('p',
            \html_writer::link($reviewurl, get_string('coursereviewcountu038', 'local_flwcupkp', $review)),
            ['class' => 'local-flwcupkp-course-next-meta']
        );
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
        $html .= \html_writer::link($progressurl, get_string('courseprogresslinku038', 'local_flwcupkp'),
            ['class' => 'btn btn-secondary btn-sm']);
        $html .= \html_writer::link($verificationurl, get_string('courseverificationlinku038', 'local_flwcupkp'),
            ['class' => 'btn btn-secondary btn-sm']);
        $html .= \html_writer::link($performanceurl, get_string('performanceu038', 'local_flwcupkp'),
            ['class' => 'btn btn-primary btn-sm']);
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build one compact overview stat.
     *
     * @param string $label
     * @param int $value
     * @param \moodle_url|null $url
     * @return string
     */
    private static function overview_stat(string $label, int $value, ?\moodle_url $url = null): string {
        $content = \html_writer::tag('strong', (string)$value) . \html_writer::tag('em', s($label));
        $attributes = ['class' => 'local-flwcupkp-course-overview-stat'];
        if ($url) {
            return \html_writer::link($url, $content, $attributes);
        }

        return \html_writer::tag('span', $content, $attributes);
    }

    /**
     * Build a filtered parent overview URL with a focused row when available.
     *
     * @param int $courseid
     * @param string $targettype
     * @param string $stategroup
     * @param array $rows
     * @param bool $reviewonly
     * @return \moodle_url
     */
    private static function parent_overview_url(int $courseid, string $targettype, string $stategroup,
            array $rows, bool $reviewonly): \moodle_url {
        $url = new \moodle_url('/local/flwcupkp/teacher_u038.php', [
            'courseid' => $courseid,
            'targettype' => $targettype,
            'parentstate' => $stategroup,
        ]);
        if ($reviewonly) {
            $url->param('parentreview', 'review');
        }
        $anchor = self::first_parent_row_anchor($rows, $targettype, $stategroup, $reviewonly);
        if ($anchor !== null) {
            $url->param('focus', $anchor);
            $url->set_anchor('flwcupkp-parent-row-' . $anchor);
        } else {
            $url->set_anchor('flwcupkp-u038-mastery-overview');
        }

        return $url;
    }

    /**
     * Find the first parent row anchor matching a course-card metric link.
     *
     * @param array $rows
     * @param string $targettype
     * @param string $stategroup
     * @param bool $reviewonly
     * @return string|null
     */
    private static function first_parent_row_anchor(array $rows, string $targettype, string $stategroup,
            bool $reviewonly): ?string {
        foreach ($rows as $row) {
            if ((string)$row['targettype'] !== $targettype) {
                continue;
            }
            if (!self::matches_parent_state_group($row, $stategroup)) {
                continue;
            }
            if ($reviewonly && !self::matches_parent_review_filter($row)) {
                continue;
            }

            return self::teacher_parent_row_anchor($row);
        }

        return null;
    }

    /**
     * Parent mastery state-group filter predicate.
     *
     * @param array $row
     * @param string $filter
     * @return bool
     */
    private static function matches_parent_state_group(array $row, string $filter): bool {
        if ($filter === '') {
            return true;
        }

        $achieved = in_array($row['state'], ['achieved', 'sustained', 'mastered'], true);
        $demonstrated = in_array($row['state'], ['demonstrated', 'stable', 'transfer_ready'], true);

        if ($filter === 'achieved') {
            return $row['targettype'] === 'competency' && $achieved;
        }
        if ($filter === 'notachieved') {
            return $row['targettype'] === 'competency' && !$achieved;
        }
        if ($filter === 'demonstrated') {
            return $row['targettype'] === 'up' && $demonstrated;
        }
        if ($filter === 'notdemonstrated') {
            return $row['targettype'] === 'up' && !$demonstrated;
        }
        if ($filter === 'attention') {
            return ($row['targettype'] === 'competency' && !$achieved) ||
                ($row['targettype'] === 'up' && !$demonstrated);
        }

        return true;
    }

    /**
     * Whether a parent row still needs a teacher decision.
     *
     * @param array $row
     * @return bool
     */
    private static function matches_parent_review_filter(array $row): bool {
        return self::parent_needs_attention($row) && empty($row['verification']);
    }

    /**
     * Whether a parent row needs teacher attention.
     *
     * @param array $row
     * @return bool
     */
    private static function parent_needs_attention(array $row): bool {
        $achieved = in_array($row['state'], ['achieved', 'sustained', 'mastered'], true);
        $demonstrated = in_array($row['state'], ['demonstrated', 'stable', 'transfer_ready'], true);

        return ($row['targettype'] === 'competency' && !$achieved) ||
            ($row['targettype'] === 'up' && !$demonstrated);
    }

    /**
     * Build one compact ratio overview stat.
     *
     * @param string $label
     * @param int $numerator
     * @param int $denominator
     * @param \moodle_url|null $url
     * @return string
     */
    private static function overview_ratio_stat(string $label, int $numerator, int $denominator,
            ?\moodle_url $url = null): string {
        $content = \html_writer::tag('strong', $numerator . ' / ' . $denominator) . \html_writer::tag('em', s($label));
        $attributes = ['class' => 'local-flwcupkp-course-overview-stat'];
        if ($url) {
            return \html_writer::link($url, $content, $attributes);
        }

        return \html_writer::tag('span', $content, $attributes);
    }

    /**
     * Build the verification-page row anchor for a report row.
     *
     * @param array $row
     * @return string
     */
    private static function teacher_row_anchor(array $row): string {
        return 'u' . (int)$row['userid'] . '-kp' . (int)$row['kp_id'];
    }

    /**
     * Build the verification-page parent row anchor for an overview row.
     *
     * @param array $row
     * @return string
     */
    private static function teacher_parent_row_anchor(array $row): string {
        $prefix = $row['targettype'] === 'competency' ? 'comp' : 'up';
        return 'u' . (int)$row['userid'] . '-' . $prefix . (int)$row['targetid'];
    }
}

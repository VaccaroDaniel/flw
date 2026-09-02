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
        global $PAGE, $USER;

        if (self::is_dashboard_page()) {
            if (!isguestuser() && isloggedin() && self::dashboard_units((int)$USER->id)) {
                self::add_plugin_stylesheet($hook);
            }
            return;
        }

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

        self::add_plugin_stylesheet($hook);

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

        if (self::is_dashboard_page()) {
            if (isguestuser() || !isloggedin()) {
                return;
            }

            $units = self::dashboard_units((int)$USER->id);
            if (!$units) {
                return;
            }

            $reportunits = self::dashboard_report_units($units, (int)$USER->id);
            $cardhtml = $reportunits ?
                self::teacher_dashboard_control_center($reportunits) :
                self::student_dashboard_control_center($units, (int)$USER->id);
            if ($cardhtml !== '') {
                self::inject_dashboard_block($hook, $cardhtml);
            }
            return;
        }

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
     * Whether the current page is the Moodle Dashboard.
     *
     * @return bool
     */
    private static function is_dashboard_page(): bool {
        global $PAGE;

        $pagetype = (string)$PAGE->pagetype;
        return $pagetype === 'my-index' || str_starts_with($pagetype, 'my-index-');
    }

    /**
     * Add the plugin stylesheet to a page head.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    private static function add_plugin_stylesheet(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        $hook->add_html(\html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => (new \moodle_url('/local/flwcupkp/styles.css'))->out(false),
        ]));
    }

    /**
     * Mapped C-UP-KP units available to a learner on the Dashboard.
     *
     * @param int $userid
     * @return array
     */
    private static function dashboard_units(int $userid): array {
        global $DB;

        if ($userid <= 0) {
            return [];
        }

        $rowid = $DB->sql_concat('o.courseid', "'-'", 'o.unitcode');
        $recordset = $DB->get_recordset_sql(
            "SELECT DISTINCT {$rowid} AS rowid,
                    o.courseid,
                    o.unitcode,
                    c.fullname AS coursefullname,
                    c.shortname AS courseshortname,
                    c.sortorder
               FROM {flwcupkp_object} o
               JOIN {course} c ON c.id = o.courseid
              WHERE o.courseid IS NOT NULL
                AND o.courseid > 0
                AND o.unitcode IS NOT NULL
                AND o.unitcode <> ''
           ORDER BY c.sortorder ASC, c.fullname ASC, o.unitcode ASC"
        );

        $units = [];
        foreach ($recordset as $record) {
            $courseid = (int)$record->courseid;
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $canviewreports = has_capability('local/flwcupkp:viewreports', $context, $userid);
            $canviewlearnerpath = has_capability('local/flwcupkp:viewlearnerpath', $context, $userid);
            if (!$canviewreports && !is_enrolled($context, $userid, '', true)) {
                continue;
            }
            if (!$canviewreports && !$canviewlearnerpath) {
                continue;
            }

            $units[] = [
                'courseid' => $courseid,
                'unitcode' => (string)$record->unitcode,
                'coursefullname' => format_string((string)$record->coursefullname, true, ['context' => $context]),
                'courseshortname' => format_string((string)$record->courseshortname, true, ['context' => $context]),
            ];
        }
        $recordset->close();

        return $units;
    }

    /**
     * Dashboard units where the user can view teacher reports.
     *
     * @param array $units
     * @param int $userid
     * @return array
     */
    private static function dashboard_report_units(array $units, int $userid): array {
        $reportunits = [];
        foreach ($units as $unit) {
            $context = \context_course::instance((int)$unit['courseid'], IGNORE_MISSING);
            if ($context && has_capability('local/flwcupkp:viewreports', $context, $userid)) {
                $reportunits[] = $unit;
            }
        }

        return $reportunits;
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
     * Inject the learner control center into the Moodle Dashboard.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @param string $html
     */
    private static function inject_dashboard_block(
        \core\hook\output\before_footer_html_generation $hook,
        string $html
    ): void {
        $encoded = json_encode($html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $hook->add_html(\html_writer::script(
            "(function(){var html={$encoded};" .
            "function visible(el){if(!el){return false;}" .
            "if(el.closest&&el.closest('.flw-hidden-maincontent')){return false;}" .
            "var style=window.getComputedStyle(el);var rect=el.getBoundingClientRect();" .
            "return style.display!=='none'&&style.visibility!=='hidden'&&rect.width>0&&rect.height>0;}" .
            "var target=document.querySelector('.local-flwcupkp-control-center');" .
            "var greeting=document.querySelector('.flw-dashboard-main .flw-dashboard-greeting');" .
            "if(visible(greeting)&&greeting.parentElement){if(target){target.remove();}" .
            "greeting.insertAdjacentHTML('afterend',html);return;}" .
            "if(target){target.outerHTML=html;return;}" .
            "var selectors=['.flw-dashboard-body-grid','.flw-dashboard-main','main#page','main','#region-main','[role=\"main\"]'];" .
            "var region=null;for(var i=0;i<selectors.length;i++){var nodes=document.querySelectorAll(selectors[i]);" .
            "for(var j=0;j<nodes.length;j++){if(visible(nodes[j])){region=nodes[j];break;}}if(region){break;}}" .
            "region=region||document.body;" .
            "if(region){region.insertAdjacentHTML('afterbegin',html);}}());"
        ));
    }

    /**
     * Build the Dashboard learner control center.
     *
     * @param array $units
     * @param int $userid
     * @return string
     */
    private static function student_dashboard_control_center(array $units, int $userid): string {
        $unitdata = [];
        foreach ($units as $unit) {
            $progress = self::progress_for_unit((int)$unit['courseid'], (string)$unit['unitcode'], $userid);
            if (!$progress || empty($progress['summary'])) {
                continue;
            }
            $unit['progress'] = $progress;
            $unitdata[] = $unit;
        }

        if (!$unitdata) {
            return '';
        }

        $primary = self::primary_dashboard_unit($unitdata);
        $courseid = (int)$primary['courseid'];
        $unitcode = (string)$primary['unitcode'];
        $rank = self::rank_summary($courseid, $unitcode, $userid);
        $streak = self::learning_streak($userid);
        $placement = self::placement_summary($courseid, $unitcode, $userid);
        $lastlesson = self::last_lesson_summary($courseid, $unitcode, $userid);
        $progressurl = self::student_url($courseid, $unitcode);
        $evaluationurl = self::evaluation_url($courseid, $unitcode);

        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-control-center',
            'data-flwcupkp-dashboard' => '1',
        ]);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-header']);
        $html .= \html_writer::tag('strong', get_string('learnercontrolcenter', 'local_flwcupkp'));
        $html .= \html_writer::tag('span', get_string('learnercontrolcenterintro', 'local_flwcupkp'));
        $html .= \html_writer::end_tag('div');

        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-grid']);
        $html .= self::dashboard_metric(get_string('rank', 'local_flwcupkp'), $rank['value'], $rank['detail'],
            self::anchored_url($progressurl, 'local-flwcupkp-progress-summary'));
        $html .= self::dashboard_metric(get_string('streak', 'local_flwcupkp'), $streak['value'], $streak['detail'],
            self::anchored_url($progressurl, 'local-flwcupkp-kp-evidence'));
        $html .= self::dashboard_metric(get_string('placementlevel', 'local_flwcupkp'),
            $placement['level'], $placement['detail'],
            self::anchored_url($evaluationurl, 'local-flwcupkp-evaluation-summary'));
        $html .= self::dashboard_metric(get_string('lastlesson', 'local_flwcupkp'),
            $lastlesson['value'], $lastlesson['detail'], $lastlesson['url']);
        $html .= \html_writer::end_tag('div');

        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-panels']);
        $html .= self::today_learning_panel($primary);
        $html .= self::unit_map_panel($unitdata, $primary);
        $html .= self::vocabulary_review_panel($courseid, $unitcode, $userid);
        $html .= self::exam_sync_panel($placement, self::evaluation_url($courseid, $unitcode));
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build the Dashboard teacher/admin control center.
     *
     * @param array $units
     * @return string
     */
    private static function teacher_dashboard_control_center(array $units): string {
        $summaries = [];
        foreach ($units as $unit) {
            $summary = self::teacher_dashboard_unit_summary($unit);
            if ($summary !== null) {
                $summaries[] = $summary;
            }
        }

        if (!$summaries) {
            return '';
        }

        $learners = 0;
        $review = 0;
        $parentqueue = 0;
        $masteryweight = 0;
        $learnerrows = 0;
        foreach ($summaries as $summary) {
            $learners += (int)$summary['learners'];
            $review += (int)$summary['review'];
            $parentqueue += (int)$summary['parentqueue'];
            $rows = max(1, (int)$summary['learnerrows']);
            $masteryweight += (int)$summary['percent'] * $rows;
            $learnerrows += $rows;
        }

        $average = $learnerrows > 0 ? round($masteryweight / $learnerrows) : 0;
        $first = reset($summaries);
        $reviewtarget = self::first_summary_url($summaries, 'review', 'teacherurl');
        $parenttarget = self::first_summary_url($summaries, 'parentqueue', 'teacherurl');
        $setupurl = new \moodle_url('/local/flwcupkp/setup.php', [
            'courseid' => (int)$first['courseid'],
            'unitcode' => (string)$first['unitcode'],
        ]);

        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-control-center local-flwcupkp-staff-control-center',
            'data-flwcupkp-dashboard' => '1',
        ]);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-header']);
        $html .= \html_writer::tag('strong', get_string('teachercontrolcenter', 'local_flwcupkp'));
        $html .= \html_writer::tag('span', get_string('teachercontrolcenterintro', 'local_flwcupkp'));
        $html .= \html_writer::end_tag('div');

        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-grid']);
        $html .= self::dashboard_metric(get_string('managedunits', 'local_flwcupkp'), (string)count($summaries),
            get_string('teacherdashboardmasterydetail', 'local_flwcupkp', $average), $setupurl);
        $html .= self::dashboard_metric(get_string('trackedlearners', 'local_flwcupkp'), (string)$learners,
            get_string('teacherdashboardlearnersdetail', 'local_flwcupkp', count($summaries)), $first['teacherurl']);
        $html .= self::dashboard_metric(get_string('evidencereviewqueue', 'local_flwcupkp'), (string)$review,
            $review > 0 ? get_string('teacherunitdetailreview', 'local_flwcupkp') :
                get_string('teacherunitdetailclear', 'local_flwcupkp'), $reviewtarget);
        $html .= self::dashboard_metric(get_string('parentdecisionqueue', 'local_flwcupkp'), (string)$parentqueue,
            $parentqueue > 0 ? get_string('parentqueuesummaryu038', 'local_flwcupkp') :
                get_string('parentqueueempty', 'local_flwcupkp'), $parenttarget);
        $html .= \html_writer::end_tag('div');

        $html .= self::teacher_dashboard_health_panel($summaries);

        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-panels']);
        foreach (array_slice($summaries, 0, 4) as $summary) {
            $html .= self::teacher_dashboard_unit_panel($summary);
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Build the staff Dashboard data freshness panel.
     *
     * @param array $summaries
     * @return string
     */
    private static function teacher_dashboard_health_panel(array $summaries): string {
        try {
            $items = self::teacher_dashboard_health_items($summaries);
        } catch (\Throwable $e) {
            debugging('local_flwcupkp teacher Dashboard health failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }

        if (!$items) {
            return '';
        }

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-health-panel']);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-health-head']);
        $html .= \html_writer::tag('strong', get_string('dashboardhealth', 'local_flwcupkp'));
        $html .= \html_writer::tag('span', get_string('dashboardhealthintro', 'local_flwcupkp'));
        $html .= \html_writer::end_tag('div');

        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-health-grid']);
        foreach ($items as $item) {
            $html .= self::dashboard_health_item($item);
        }
        $html .= \html_writer::end_tag('div');

        if (has_capability('local/flwcupkp:synccompetencies', \context_system::instance())) {
            $html .= self::dashboard_repair_history_panel($summaries);
        }
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Data freshness items for staff Dashboard status.
     *
     * @param array $summaries
     * @return array
     */
    private static function teacher_dashboard_health_items(array $summaries): array {
        return [
            self::quiz_evidence_health($summaries),
            self::mastery_rollup_health($summaries),
            self::moodle_competency_sync_health($summaries),
            self::recalculation_health(),
        ];
    }

    /**
     * Render one compact health item.
     *
     * @param array $item
     * @return string
     */
    private static function dashboard_health_item(array $item): string {
        $status = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($item['status'] ?? 'muted')));
        $classes = 'local-flwcupkp-health-item local-flwcupkp-health-' . $status;
        $content = \html_writer::tag('span', s((string)$item['label'])) .
            \html_writer::tag('strong', s((string)$item['value'])) .
            \html_writer::tag('em', s((string)$item['detail']));

        $actions = [];
        if (!empty($item['actions']) && is_array($item['actions'])) {
            $actions = $item['actions'];
        } else if (!empty($item['actionurl']) && $item['actionurl'] instanceof \moodle_url) {
            $actions[] = [
                'url' => $item['actionurl'],
                'label' => (string)($item['actionlabel'] ?? ''),
            ];
        }

        if ($actions) {
            $content .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-health-actions']);
            foreach ($actions as $action) {
                if (empty($action['url']) || !$action['url'] instanceof \moodle_url) {
                    continue;
                }
                $content .= \html_writer::start_tag('form', [
                    'method' => 'post',
                    'action' => $action['url']->out(false),
                    'class' => 'local-flwcupkp-health-action',
                ]);
                $content .= \html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 'sesskey',
                    'value' => sesskey(),
                ]);
                $content .= \html_writer::tag('button', s((string)($action['label'] ?? '')), [
                    'type' => 'submit',
                    'class' => 'btn btn-secondary btn-sm',
                ]);
                $content .= \html_writer::end_tag('form');
            }
            $content .= \html_writer::end_tag('div');
            return \html_writer::tag('div', $content, ['class' => $classes]);
        }

        if (!empty($item['url']) && $item['url'] instanceof \moodle_url) {
            return \html_writer::link($item['url'], $content, visuals::nav_attributes($item['url'], [
                'class' => $classes,
            ]));
        }

        return \html_writer::tag('div', $content, ['class' => $classes]);
    }

    /**
     * Render recent evidence repair history for admins.
     *
     * @param array $summaries
     * @return string
     */
    private static function dashboard_repair_history_panel(array $summaries): string {
        try {
            $history = evidence_sync_repair::recent_repair_history_for_scopes($summaries, 5);
        } catch (\Throwable $e) {
            debugging('local_flwcupkp repair history failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-repair-history']);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-repair-history-head']);
        $html .= \html_writer::tag('strong', get_string('repairhistorytitle', 'local_flwcupkp'));
        $html .= \html_writer::tag('span', get_string('repairhistoryintro', 'local_flwcupkp'));
        $healthurl = self::evidence_sync_health_url($summaries);
        if ($healthurl) {
            $html .= visuals::nav_link($healthurl, get_string('openevidencesynchealth', 'local_flwcupkp'), [
                'class' => 'btn btn-secondary btn-sm local-flwcupkp-repair-history-link',
            ]);
        }
        $html .= \html_writer::end_tag('div');

        if (!$history) {
            $html .= \html_writer::tag('p', get_string('repairhistoryempty', 'local_flwcupkp'));
            $html .= \html_writer::end_tag('section');
            return $html;
        }

        $html .= \html_writer::start_tag('ol');
        foreach ($history as $row) {
            $status = self::repair_history_status($row);
            $html .= \html_writer::start_tag('li', ['class' => 'local-flwcupkp-repair-history-' . $status]);
            $html .= \html_writer::tag('span', s(self::repair_history_badge($status)));
            $html .= \html_writer::start_tag('div');
            $html .= \html_writer::tag('strong', s(self::repair_history_title($row)));
            $html .= \html_writer::tag('em', s(self::repair_history_detail($row)));
            $html .= \html_writer::tag('small', s(self::repair_history_meta($row)));
            $html .= \html_writer::end_tag('div');
            $html .= \html_writer::end_tag('li');
        }
        $html .= \html_writer::end_tag('ol');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Repair history visual status.
     *
     * @param array $row
     * @return string
     */
    private static function repair_history_status(array $row): string {
        $action = (string)($row['action'] ?? '');
        if ($action === 'quiz_evidence_repair_failed') {
            return 'failed';
        }
        if ($action === 'quiz_evidence_repair_all_queued') {
            return 'queued';
        }
        $details = $row['details'] ?? [];
        if (!empty($details['failed'])) {
            return 'warning';
        }
        return 'completed';
    }

    /**
     * Repair history badge label.
     *
     * @param string $status
     * @return string
     */
    private static function repair_history_badge(string $status): string {
        if ($status === 'failed') {
            return get_string('repairhistorybadgefailed', 'local_flwcupkp');
        }
        if ($status === 'queued') {
            return get_string('repairhistorybadgequeued', 'local_flwcupkp');
        }
        if ($status === 'warning') {
            return get_string('repairhistorybadgewarning', 'local_flwcupkp');
        }
        return get_string('repairhistorybadgecompleted', 'local_flwcupkp');
    }

    /**
     * Repair history row title.
     *
     * @param array $row
     * @return string
     */
    private static function repair_history_title(array $row): string {
        $action = (string)($row['action'] ?? '');
        if ($action === 'quiz_evidence_repair_all_completed') {
            return get_string('repairhistorybulkcompleted', 'local_flwcupkp');
        }
        if ($action === 'quiz_evidence_repair_all_queued') {
            return get_string('repairhistorybulkqueued', 'local_flwcupkp');
        }
        if ($action === 'quiz_evidence_repair_failed') {
            return get_string('repairhistoryfailed', 'local_flwcupkp');
        }
        return get_string('repairhistoryattemptcompleted', 'local_flwcupkp', (int)($row['targetid'] ?? 0));
    }

    /**
     * Repair history row detail.
     *
     * @param array $row
     * @return string
     */
    private static function repair_history_detail(array $row): string {
        $action = (string)($row['action'] ?? '');
        $details = $row['details'] ?? [];
        if (!is_array($details)) {
            $details = [];
        }
        if ($action === 'quiz_evidence_repair_all_completed') {
            return get_string('repairhistorybulkdetail', 'local_flwcupkp', (object)[
                'created' => (int)($details['created'] ?? 0),
                'processed' => (int)($details['processed'] ?? 0),
                'found' => (int)($details['found'] ?? 0),
                'failed' => (int)($details['failed'] ?? 0),
            ]);
        }
        if ($action === 'quiz_evidence_repair_all_queued') {
            return get_string('repairhistoryqueuedetail', 'local_flwcupkp', (int)($details['pending'] ?? 0));
        }
        if ($action === 'quiz_evidence_repair_failed') {
            return get_string('repairhistoryfaileddetail', 'local_flwcupkp', (object)[
                'attemptid' => (int)($row['targetid'] ?? 0),
                'message' => (string)($details['message'] ?? get_string('unknown', 'moodle')),
            ]);
        }

        $adapter = $details['adapter_result'] ?? [];
        if (!is_array($adapter)) {
            $adapter = [];
        }
        return get_string('repairhistoryattemptdetail', 'local_flwcupkp', (object)[
            'count' => count($adapter['evidenceids'] ?? []),
            'unitcode' => (string)($details['unitcode'] ?? $row['unitcode'] ?? ''),
        ]);
    }

    /**
     * Repair history row metadata.
     *
     * @param array $row
     * @return string
     */
    private static function repair_history_meta(array $row): string {
        $time = self::health_time((int)($row['timecreated'] ?? 0));
        $name = trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? ''));
        if ($name === '') {
            $name = (string)($row['username'] ?? '');
        }
        if ($name === '') {
            $name = get_string('repairhistoryunknownuser', 'local_flwcupkp');
        }

        return get_string('repairhistorymeta', 'local_flwcupkp', (object)[
            'time' => $time,
            'user' => $name,
        ]);
    }

    /**
     * Normalize a health item array.
     *
     * @param string $label
     * @param string $value
     * @param string $detail
     * @param string $status
     * @param \moodle_url|null $url
     * @param \moodle_url|null $actionurl
     * @param string $actionlabel
     * @return array
     */
    private static function health_item(string $label, string $value, string $detail, string $status,
            ?\moodle_url $url = null, ?\moodle_url $actionurl = null, string $actionlabel = ''): array {
        return [
            'label' => $label,
            'value' => $value,
            'detail' => $detail,
            'status' => $status,
            'url' => $url,
            'actionurl' => $actionurl,
            'actionlabel' => $actionlabel,
        ];
    }

    /**
     * Quiz evidence ingestion freshness for mapped quiz attempts.
     *
     * @param array $summaries
     * @return array
     */
    private static function quiz_evidence_health(array $summaries): array {
        global $DB;

        $params = [
            'quizmod' => 'quiz',
            'finished' => 'finished',
        ];
        $scope = self::dashboard_scope_condition('o.courseid', 'o.unitcode', $summaries, $params, 'quiz');
        $base = "FROM {quiz_attempts} qa
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {modules} m ON m.name = :quizmod
                  JOIN {course_modules} cm ON cm.module = m.id
                       AND cm.instance = q.id
                       AND cm.course = q.course
                  JOIN {flwcupkp_object} o ON o.cmid = cm.id
                 WHERE {$scope}
                   AND qa.preview = 0
                   AND qa.state = :finished
                   AND qa.timefinish > 0";

        $attempts = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT qa.id) {$base}", $params);
        $latestattempt = (int)($DB->get_field_sql("SELECT MAX(qa.timefinish) {$base}", $params) ?: 0);

        $pending = evidence_sync_repair::pending_quiz_attempt_count_for_scopes($summaries);
        $pendingrows = $pending > 0 ? evidence_sync_repair::pending_quiz_attempts_for_scopes($summaries, 1) : [];
        $firstpending = $pendingrows ? reset($pendingrows) : null;
        $latestevidence = self::latest_evidence_time($summaries, 'quiz_attempt_submitted');
        $adminurl = self::evidence_sync_health_url($summaries);
        $url = $adminurl ?? self::first_summary_url($summaries, 'review', 'teacherurl');
        $repairurl = null;
        $bulkrepairurl = null;
        if ($firstpending) {
            $repairurl = new \moodle_url('/local/flwcupkp/repair_sync.php', [
                'action' => 'repair_quiz_attempt',
                'courseid' => (int)$firstpending->courseid,
                'unitcode' => (string)$firstpending->unitcode,
                'attemptid' => (int)$firstpending->attemptid,
                'returnurl' => ($adminurl ?? new \moodle_url('/my/'))->out(false),
            ]);
            if ($pending > 1 && has_capability('local/flwcupkp:synccompetencies', \context_system::instance())) {
                $bulkrepairurl = new \moodle_url('/local/flwcupkp/repair_sync.php', [
                    'action' => 'repair_pending_quiz_attempts',
                    'courseid' => (int)$firstpending->courseid,
                    'unitcode' => (string)$firstpending->unitcode,
                    'returnurl' => ($adminurl ?? new \moodle_url('/my/'))->out(false),
                ]);
            }
        }

        if ($pending > 0 || ($latestattempt > 0 && $latestevidence > 0 && $latestattempt > $latestevidence)) {
            $detail = $firstpending ?
                get_string('healthquizdetailpendingrepair', 'local_flwcupkp', (object)[
                    'pending' => max(1, $pending),
                    'attemptid' => (int)$firstpending->attemptid,
                ]) :
                get_string('healthquizdetailpending', 'local_flwcupkp', (object)['pending' => max(1, $pending)]);
            $item = self::health_item(
                get_string('healthquizevidence', 'local_flwcupkp'),
                get_string('healthneedssync', 'local_flwcupkp'),
                $detail,
                'attention',
                $url
            );
            if ($repairurl) {
                $item['actions'][] = [
                    'url' => $repairurl,
                    'label' => get_string('healthrepairquizsync', 'local_flwcupkp'),
                ];
            }
            if ($bulkrepairurl) {
                $item['actions'][] = [
                    'url' => $bulkrepairurl,
                    'label' => get_string('healthrepairallquizsync', 'local_flwcupkp'),
                ];
            }
            return $item;
        }

        if ($attempts === 0) {
            return self::health_item(
                get_string('healthquizevidence', 'local_flwcupkp'),
                get_string('healthnodata', 'local_flwcupkp'),
                get_string('healthquizdetailnone', 'local_flwcupkp'),
                'muted',
                $url
            );
        }

        return self::health_item(
            get_string('healthquizevidence', 'local_flwcupkp'),
            get_string('healthcurrent', 'local_flwcupkp'),
            get_string('healthquizdetailcurrent', 'local_flwcupkp', (object)[
                'attempts' => $attempts,
                'time' => self::health_time($latestevidence ?: $latestattempt),
            ]),
            'ok',
            $url
        );
    }

    /**
     * Mastery rollup freshness compared with latest scoped evidence.
     *
     * @param array $summaries
     * @return array
     */
    private static function mastery_rollup_health(array $summaries): array {
        global $DB;

        $latestevidence = self::latest_evidence_time($summaries);
        $params = [];
        $scope = self::dashboard_scope_condition('o.courseid', 'o.unitcode', $summaries, $params, 'roll');
        [$typesql, $typeparams] = $DB->get_in_or_equal(['up', 'competency'], SQL_PARAMS_NAMED, 'rolltype');
        $params += $typeparams;
        $latestrollup = (int)($DB->get_field_sql(
            "SELECT MAX(s.timemodified)
               FROM {flwcupkp_state} s
               JOIN {flwcupkp_object_map} om ON om.targettype = s.targettype
                    AND om.targetid = s.targetid
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE {$scope}
                AND s.targettype {$typesql}",
            $params
        ) ?: 0);
        $url = self::first_summary_url($summaries, 'parentqueue', 'teacherurl');

        if ($latestrollup === 0 && $latestevidence === 0) {
            return self::health_item(
                get_string('healthmasteryrollups', 'local_flwcupkp'),
                get_string('healthnodata', 'local_flwcupkp'),
                get_string('healthrollupdetailnone', 'local_flwcupkp'),
                'muted',
                $url
            );
        }

        if ($latestrollup === 0) {
            return self::health_item(
                get_string('healthmasteryrollups', 'local_flwcupkp'),
                get_string('healthpending', 'local_flwcupkp'),
                get_string('healthrollupdetailnone', 'local_flwcupkp'),
                'pending',
                $url
            );
        }

        if ($latestevidence > 0 && $latestrollup < $latestevidence) {
            return self::health_item(
                get_string('healthmasteryrollups', 'local_flwcupkp'),
                get_string('healthneedssync', 'local_flwcupkp'),
                get_string('healthrollupdetailstale', 'local_flwcupkp', (object)[
                    'rollup' => self::health_time($latestrollup),
                    'evidence' => self::health_time($latestevidence),
                ]),
                'attention',
                $url
            );
        }

        return self::health_item(
            get_string('healthmasteryrollups', 'local_flwcupkp'),
            get_string('healthcurrent', 'local_flwcupkp'),
            get_string('healthrollupdetailcurrent', 'local_flwcupkp', (object)[
                'rollup' => self::health_time($latestrollup),
                'evidence' => self::health_time($latestevidence),
            ]),
            'ok',
            $url
        );
    }

    /**
     * Native Moodle competency sync health.
     *
     * @param array $summaries
     * @return array
     */
    private static function moodle_competency_sync_health(array $summaries): array {
        $context = \context_system::instance();
        $url = has_capability('local/flwcupkp:synccompetencies', $context) ?
            new \moodle_url('/local/flwcupkp/sync.php') : null;
        $readiness = curriculum_manager::sync_readiness();
        $writesenabled = (bool)get_config('local_flwcupkp', 'enablesyncwrites');
        $latestok = self::latest_audit_time([
            'moodle_competency_rating_synced',
            'teacher_parent_moodle_sync_checked',
        ]);
        $latestfail = self::latest_audit_time(['moodle_competency_rating_sync_failed']);

        if (empty($readiness['readyforwrites'])) {
            return self::health_item(
                get_string('healthmoodlecompetencysync', 'local_flwcupkp'),
                get_string('healthnotready', 'local_flwcupkp'),
                get_string('healthsyncdetailnotready', 'local_flwcupkp', (object)[
                    'frameworks' => (int)($readiness['unlinkedframeworks'] ?? 0),
                    'competencies' => (int)($readiness['unlinkedcompetencies'] ?? 0),
                ]),
                'attention',
                $url
            );
        }

        if (!$writesenabled) {
            return self::health_item(
                get_string('healthmoodlecompetencysync', 'local_flwcupkp'),
                get_string('healthwritesoff', 'local_flwcupkp'),
                get_string('healthsyncdetailwritesoff', 'local_flwcupkp'),
                'pending',
                $url
            );
        }

        if ($latestfail > $latestok) {
            return self::health_item(
                get_string('healthmoodlecompetencysync', 'local_flwcupkp'),
                get_string('healthattention', 'local_flwcupkp'),
                get_string('healthsyncdetailfailed', 'local_flwcupkp', (object)[
                    'time' => self::health_time($latestfail),
                ]),
                'attention',
                $url
            );
        }

        if ($latestok === 0) {
            return self::health_item(
                get_string('healthmoodlecompetencysync', 'local_flwcupkp'),
                get_string('healthpending', 'local_flwcupkp'),
                get_string('healthsyncdetailnone', 'local_flwcupkp'),
                'pending',
                $url
            );
        }

        return self::health_item(
            get_string('healthmoodlecompetencysync', 'local_flwcupkp'),
            get_string('healthcurrent', 'local_flwcupkp'),
            get_string('healthsyncdetailready', 'local_flwcupkp', (object)[
                'time' => self::health_time($latestok),
                'competencies' => (int)($readiness['linkedcompetencies'] ?? 0),
            ]),
            'ok',
            $url
        );
    }

    /**
     * Controlled recalculation queue and latest run status.
     *
     * @return array
     */
    private static function recalculation_health(): array {
        global $DB;

        $context = \context_system::instance();
        $url = has_capability('local/flwcupkp:viewreports', $context) ?
            new \moodle_url('/local/flwcupkp/calibration_proposal.php') : null;
        [$statussql, $params] = $DB->get_in_or_equal(['queued', 'running'], SQL_PARAMS_NAMED, 'recalcstatus');
        $pending = (int)$DB->count_records_select('flwcupkp_calrecalc', "status {$statussql}", $params);
        $latest = $DB->get_record_sql(
            "SELECT *
               FROM {flwcupkp_calrecalc}
           ORDER BY timemodified DESC, id DESC",
            [],
            IGNORE_MULTIPLE
        );

        if ($pending > 0) {
            return self::health_item(
                get_string('healthlatestrecalculation', 'local_flwcupkp'),
                get_string('healthqueued', 'local_flwcupkp'),
                get_string('healthrecalcdetailrunning', 'local_flwcupkp', $pending),
                'pending',
                $url
            );
        }

        if (!$latest) {
            return self::health_item(
                get_string('healthlatestrecalculation', 'local_flwcupkp'),
                get_string('healthnoruns', 'local_flwcupkp'),
                get_string('healthrecalcdetailnone', 'local_flwcupkp'),
                'muted',
                $url
            );
        }

        $status = (string)$latest->status;
        $errors = json_decode((string)($latest->errorsjson ?? '[]'), true);
        if (!is_array($errors)) {
            $errors = [];
        }
        $time = (int)($latest->timecompleted ?: $latest->timemodified ?: $latest->timecreated);
        if ($status === 'failed' || $status === 'completed_with_errors' || $errors) {
            return self::health_item(
                get_string('healthlatestrecalculation', 'local_flwcupkp'),
                get_string('healthattention', 'local_flwcupkp'),
                get_string('healthrecalcdetailfailed', 'local_flwcupkp', (object)[
                    'status' => $status,
                    'time' => self::health_time($time),
                ]),
                'attention',
                $url
            );
        }

        return self::health_item(
            get_string('healthlatestrecalculation', 'local_flwcupkp'),
            get_string('healthcurrent', 'local_flwcupkp'),
            get_string('healthrecalcdetaildone', 'local_flwcupkp', (object)[
                'time' => self::health_time($time),
                'applied' => (int)$latest->applied,
                'skipped' => (int)$latest->skipped,
            ]),
            'ok',
            $url
        );
    }

    /**
     * Latest scoped evidence timestamp.
     *
     * @param array $summaries
     * @param string|null $evidencetype
     * @return int
     */
    private static function latest_evidence_time(array $summaries, ?string $evidencetype = null): int {
        global $DB;

        $params = [];
        $scope = self::dashboard_scope_condition('e.courseid', 'e.unitcode', $summaries, $params, 'ev');
        $where = [$scope];
        if ($evidencetype !== null) {
            $where[] = 'e.evidencetype = :evidencetype';
            $params['evidencetype'] = $evidencetype;
        }

        return (int)($DB->get_field_sql(
            "SELECT MAX(e.timecreated)
               FROM {flwcupkp_evidence} e
              WHERE " . implode(' AND ', $where),
            $params
        ) ?: 0);
    }

    /**
     * Latest audit timestamp for one of several actions.
     *
     * @param array $actions
     * @return int
     */
    private static function latest_audit_time(array $actions): int {
        global $DB;

        if (!$actions) {
            return 0;
        }

        [$actionsql, $params] = $DB->get_in_or_equal(array_values($actions), SQL_PARAMS_NAMED, 'auditaction');
        return (int)($DB->get_field_sql(
            "SELECT MAX(timecreated)
               FROM {flwcupkp_audit}
              WHERE action {$actionsql}",
            $params
        ) ?: 0);
    }

    /**
     * Course/unit scope SQL for Dashboard staff units.
     *
     * @param string $coursefield
     * @param string $unitfield
     * @param array $summaries
     * @param array $params
     * @param string $prefix
     * @return string
     */
    private static function dashboard_scope_condition(string $coursefield, string $unitfield, array $summaries,
            array &$params, string $prefix): string {
        $parts = [];
        $seen = [];
        $index = 0;
        foreach ($summaries as $summary) {
            $courseid = (int)($summary['courseid'] ?? 0);
            $unitcode = trim((string)($summary['unitcode'] ?? ''));
            if ($courseid <= 0 || $unitcode === '') {
                continue;
            }

            $key = $courseid . ':' . $unitcode;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $courseparam = $prefix . 'course' . $index;
            $unitparam = $prefix . 'unit' . $index;
            $params[$courseparam] = $courseid;
            $params[$unitparam] = $unitcode;
            $parts[] = "({$coursefield} = :{$courseparam} AND {$unitfield} = :{$unitparam})";
            $index++;
        }

        return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
    }

    /**
     * Display a health timestamp.
     *
     * @param int $time
     * @return string
     */
    private static function health_time(int $time): string {
        if ($time <= 0) {
            return get_string('healthnever', 'local_flwcupkp');
        }

        return userdate($time, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Return the first summary URL where a count is waiting, falling back to a normal unit URL.
     *
     * @param array $summaries
     * @param string $countkey
     * @param string $fallbackkey
     * @return \moodle_url
     */
    private static function first_summary_url(array $summaries, string $countkey, string $fallbackkey): \moodle_url {
        foreach ($summaries as $summary) {
            if ((int)($summary[$countkey] ?? 0) > 0 && !empty($summary[$countkey . 'url'])) {
                return $summary[$countkey . 'url'];
            }
        }

        $first = reset($summaries);
        return $first[$fallbackkey];
    }

    /**
     * Admin Evidence Sync Health URL for the first Dashboard scope.
     *
     * @param array $summaries
     * @return \moodle_url|null
     */
    private static function evidence_sync_health_url(array $summaries): ?\moodle_url {
        if (!has_capability('local/flwcupkp:synccompetencies', \context_system::instance())) {
            return null;
        }

        foreach ($summaries as $summary) {
            $courseid = (int)($summary['courseid'] ?? 0);
            if ($courseid <= 0) {
                continue;
            }

            $params = ['courseid' => $courseid];
            $unitcode = trim((string)($summary['unitcode'] ?? ''));
            if ($unitcode !== '') {
                $params['unitcode'] = $unitcode;
            }
            return new \moodle_url('/local/flwcupkp/evidence_sync.php', $params);
        }

        return new \moodle_url('/local/flwcupkp/evidence_sync.php');
    }

    /**
     * Build one teacher dashboard unit summary.
     *
     * @param array $unit
     * @return array|null
     */
    private static function teacher_dashboard_unit_summary(array $unit): ?array {
        try {
            return (string)$unit['unitcode'] === 'U038' ?
                self::teacher_dashboard_u038_summary($unit) :
                self::teacher_dashboard_generic_summary($unit);
        } catch (\Throwable $e) {
            debugging('local_flwcupkp teacher Dashboard summary failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Build the U038 teacher dashboard summary.
     *
     * @param array $unit
     * @return array
     */
    private static function teacher_dashboard_u038_summary(array $unit): array {
        $courseid = (int)$unit['courseid'];
        $report = teacher_report::u038_report($courseid);
        $overview = teacher_report::u038_mastery_overview($courseid);
        $parentsummary = $overview['summary'];

        $mastered = 0;
        $withevidence = 0;
        $verified = 0;
        $reviewanchor = null;
        foreach ($report['rows'] as $row) {
            $isstrong = in_array($row['state'], ['mastered', 'independent_use', 'controlled_use'], true);
            $hasevidence = !empty($row['evidence_id']);
            $isverified = !empty($row['verification']) &&
                in_array($row['verification']['action'], ['teacher_evidence_approved', 'teacher_state_overridden'], true);
            if ($isstrong) {
                $mastered++;
            }
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

        $parentqueue = 0;
        $parentfirst = null;
        foreach ($overview['rows'] as $row) {
            if (self::matches_parent_review_filter($row)) {
                $parentqueue++;
                $parentfirst = $parentfirst ?: $row;
            }
        }

        $teacherurl = self::teacher_url($courseid, 'U038');
        $reviewurl = new \moodle_url('/local/flwcupkp/teacher_u038.php', [
            'courseid' => $courseid,
            'evidence' => 'review',
        ]);
        if ($reviewanchor !== null) {
            $reviewurl->param('focus', $reviewanchor);
            $reviewurl->set_anchor('flwcupkp-row-' . $reviewanchor);
        }
        $parenturl = $parentfirst ?
            self::parent_overview_url($courseid, (string)$parentfirst['targettype'],
                (string)$parentfirst['targettype'] === 'competency' ? 'notachieved' : 'notdemonstrated',
                $overview['rows'], true) :
            self::anchored_url($teacherurl, 'flwcupkp-u038-mastery-overview');

        $totalrows = count($report['rows']);
        return self::teacher_dashboard_summary_record($unit, $teacherurl, $reviewurl, $parenturl, [
            'learners' => count($report['learners']),
            'targets' => count($report['targets']),
            'percent' => $totalrows > 0 ? round(($mastered / $totalrows) * 100) : 0,
            'learnerrows' => $totalrows,
            'review' => max(0, $withevidence - $verified),
            'parentqueue' => $parentqueue,
            'competency_achieved' => (int)$parentsummary['competency_achieved'],
            'competency_total' => (int)$parentsummary['competency_total'],
            'up_demonstrated' => (int)$parentsummary['up_demonstrated'],
            'up_total' => (int)$parentsummary['up_total'],
        ]);
    }

    /**
     * Build the generic unit teacher dashboard summary.
     *
     * @param array $unit
     * @return array
     */
    private static function teacher_dashboard_generic_summary(array $unit): array {
        $courseid = (int)$unit['courseid'];
        $unitcode = (string)$unit['unitcode'];
        $learners = unit_report::learners($courseid, $unitcode);
        $targets = unit_report::unit_targets($courseid, $unitcode);
        $report = unit_report::kp_report($courseid, $unitcode);
        $overview = unit_report::mastery_overview($courseid, $unitcode);
        $queues = unit_report::parent_queue_summary($courseid, $unitcode);

        $strong = 0;
        $weak = 0;
        foreach ($targets as $target) {
            $stats = unit_report::target_stats($target, $learners);
            $strong += (int)$stats['strong'];
            $weak += (int)$stats['weak'];
        }

        $withevidence = 0;
        $verified = 0;
        $reviewanchor = null;
        foreach ($report['rows'] as $row) {
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

        $teacherurl = self::teacher_url($courseid, $unitcode);
        $reviewurl = self::teacher_url($courseid, $unitcode);
        $reviewurl->param('evidence', 'review');
        if ($reviewanchor !== null) {
            $reviewurl->param('focus', $reviewanchor);
            $reviewurl->set_anchor('flwcupkp-row-' . $reviewanchor);
        }

        $parentfirst = $queues['competency']['first'] ?: $queues['up']['first'];
        $parenturl = $parentfirst ?
            self::unit_parent_overview_url($courseid, $unitcode, (string)$parentfirst['targettype'],
                (string)$parentfirst['targettype'] === 'competency' ? 'notachieved' : 'notdemonstrated',
                $overview['rows'], true) :
            self::anchored_url($teacherurl, 'flwcupkp-unit-parent-overview');
        $totalrows = $strong + $weak;
        $parentsummary = $overview['summary'];

        return self::teacher_dashboard_summary_record($unit, $teacherurl, $reviewurl, $parenturl, [
            'learners' => count($learners),
            'targets' => count($targets),
            'percent' => $totalrows > 0 ? round(($strong / $totalrows) * 100) : 0,
            'learnerrows' => $totalrows,
            'review' => max(0, $withevidence - $verified),
            'parentqueue' => (int)$queues['total'],
            'competency_achieved' => (int)$parentsummary['competency_achieved'],
            'competency_total' => (int)$parentsummary['competency_total'],
            'up_demonstrated' => (int)$parentsummary['up_demonstrated'],
            'up_total' => (int)$parentsummary['up_total'],
        ]);
    }

    /**
     * Normalize a teacher dashboard summary record.
     *
     * @param array $unit
     * @param \moodle_url $teacherurl
     * @param \moodle_url $reviewurl
     * @param \moodle_url $parenturl
     * @param array $metrics
     * @return array
     */
    private static function teacher_dashboard_summary_record(array $unit, \moodle_url $teacherurl,
            \moodle_url $reviewurl, \moodle_url $parenturl, array $metrics): array {
        return [
            'courseid' => (int)$unit['courseid'],
            'unitcode' => (string)$unit['unitcode'],
            'coursefullname' => (string)$unit['coursefullname'],
            'courseshortname' => (string)$unit['courseshortname'],
            'teacherurl' => $teacherurl,
            'reviewurl' => $reviewurl,
            'parentqueueurl' => $parenturl,
            'progressurl' => self::student_url((int)$unit['courseid'], (string)$unit['unitcode']),
            'setupurl' => new \moodle_url('/local/flwcupkp/setup.php', [
                'courseid' => (int)$unit['courseid'],
                'unitcode' => (string)$unit['unitcode'],
            ]),
        ] + $metrics;
    }

    /**
     * Render one unit panel on the staff Dashboard.
     *
     * @param array $summary
     * @return string
     */
    private static function teacher_dashboard_unit_panel(array $summary): string {
        $detail = get_string('teacherdashboardunitdetail', 'local_flwcupkp', (object)[
            'learners' => (int)$summary['learners'],
            'review' => (int)$summary['review'],
            'parent' => (int)$summary['parentqueue'],
        ]);

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-control-panel']);
        $html .= \html_writer::tag('h3', s((string)$summary['coursefullname']));
        $html .= \html_writer::tag('div',
            get_string('classmastery', 'local_flwcupkp') . ': ' . (int)$summary['percent'] . '%',
            ['class' => 'local-flwcupkp-control-panel-value']);
        $html .= \html_writer::start_tag('span', ['class' => 'local-flwcupkp-control-mapbar', 'aria-hidden' => 'true']);
        $html .= \html_writer::tag('span', '', ['style' => 'width: ' . (int)$summary['percent'] . '%']);
        $html .= \html_writer::end_tag('span');
        $html .= \html_writer::tag('p', s($detail));
        $html .= \html_writer::tag('p',
            get_string('competenciesachieved', 'local_flwcupkp') . ': ' .
            (int)$summary['competency_achieved'] . '/' . (int)$summary['competency_total'] . ' | ' .
            get_string('upsdemonstrated', 'local_flwcupkp') . ': ' .
            (int)$summary['up_demonstrated'] . '/' . (int)$summary['up_total']);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
        $html .= visuals::nav_link($summary['reviewurl'], get_string('openteacherreview', 'local_flwcupkp'), [
            'class' => 'btn btn-primary btn-sm',
        ]);
        $html .= visuals::nav_link($summary['parentqueueurl'], get_string('parentqueuesummaryu038', 'local_flwcupkp'), [
            'class' => 'btn btn-secondary btn-sm',
        ]);
        $html .= visuals::nav_link($summary['setupurl'], get_string('openunitsetup', 'local_flwcupkp'), [
            'class' => 'btn btn-secondary btn-sm',
        ]);
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Pick the unit that should drive the top Dashboard recommendation.
     *
     * @param array $units
     * @return array
     */
    private static function primary_dashboard_unit(array $units): array {
        usort($units, static function(array $left, array $right): int {
            $leftsummary = $left['progress']['summary'] ?? [];
            $rightsummary = $right['progress']['summary'] ?? [];
            $leftgaps = (int)($leftsummary['gaps'] ?? 0);
            $rightgaps = (int)($rightsummary['gaps'] ?? 0);
            if (($leftgaps > 0) !== ($rightgaps > 0)) {
                return $leftgaps > 0 ? -1 : 1;
            }

            $leftevidence = (int)($leftsummary['with_evidence'] ?? 0);
            $rightevidence = (int)($rightsummary['with_evidence'] ?? 0);
            if ($leftevidence !== $rightevidence) {
                return $rightevidence <=> $leftevidence;
            }

            return strcmp((string)$left['unitcode'], (string)$right['unitcode']);
        });

        return reset($units);
    }

    /**
     * Load progress for one mapped unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array|null
     */
    private static function progress_for_unit(int $courseid, string $unitcode, int $userid): ?array {
        try {
            return $unitcode === 'U038' ?
                student_report::u038_progress($courseid, $userid) :
                unit_report::student_progress($courseid, $unitcode, $userid);
        } catch (\Throwable $e) {
            debugging('local_flwcupkp Dashboard progress failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Build one compact Dashboard metric tile.
     *
     * @param string $label
     * @param string $value
     * @param string $detail
     * @param \moodle_url|null $url
     * @return string
     */
    private static function dashboard_metric(string $label, string $value, string $detail,
            ?\moodle_url $url = null): string {
        $content = \html_writer::tag('span', s($label)) .
            \html_writer::tag('strong', s($value)) .
            \html_writer::tag('em', s($detail));
        $attributes = ['class' => 'local-flwcupkp-control-metric'];

        if ($url) {
            return \html_writer::link($url, $content, visuals::nav_attributes($url, $attributes));
        }

        return \html_writer::tag('div', $content, $attributes);
    }

    /**
     * Class rank for the selected unit, derived from current mastery percentages.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    private static function rank_summary(int $courseid, string $unitcode, int $userid): array {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [
                'value' => '-',
                'detail' => get_string('rankunavailable', 'local_flwcupkp'),
            ];
        }

        $learners = unit_report::learners($courseid, $unitcode);
        $scores = [];
        foreach ($learners as $learner) {
            $learnerid = (int)$learner->id;
            if ($learnerid <= 0 || isguestuser($learner)) {
                continue;
            }
            if (!is_enrolled($context, $learnerid, '', true)) {
                continue;
            }

            $progress = self::progress_for_unit($courseid, $unitcode, $learnerid);
            if (!$progress) {
                continue;
            }
            $summary = $progress['summary'] ?? [];
            $scores[] = [
                'userid' => $learnerid,
                'percent' => (int)($summary['percent'] ?? 0),
                'mastered' => (int)($summary['mastered'] ?? 0),
                'gaps' => (int)($summary['gaps'] ?? 0),
            ];
        }

        if (!$scores) {
            return [
                'value' => '-',
                'detail' => get_string('rankunavailable', 'local_flwcupkp'),
            ];
        }

        usort($scores, static function(array $left, array $right): int {
            if ($left['percent'] !== $right['percent']) {
                return $right['percent'] <=> $left['percent'];
            }
            if ($left['mastered'] !== $right['mastered']) {
                return $right['mastered'] <=> $left['mastered'];
            }
            if ($left['gaps'] !== $right['gaps']) {
                return $left['gaps'] <=> $right['gaps'];
            }
            return $left['userid'] <=> $right['userid'];
        });

        $rank = null;
        $currentrank = 0;
        $previous = null;
        foreach ($scores as $index => $score) {
            $signature = $score['percent'] . ':' . $score['mastered'] . ':' . $score['gaps'];
            if ($signature !== $previous) {
                $currentrank = $index + 1;
                $previous = $signature;
            }
            if ((int)$score['userid'] === $userid) {
                $rank = $currentrank;
                break;
            }
        }

        if ($rank === null) {
            return [
                'value' => '-',
                'detail' => get_string('rankunavailable', 'local_flwcupkp'),
            ];
        }

        return [
            'value' => '#' . $rank,
            'detail' => get_string('rankdetail', 'local_flwcupkp', count($scores)),
        ];
    }

    /**
     * Consecutive learning days, derived from evidence dates.
     *
     * @param int $userid
     * @return array
     */
    private static function learning_streak(int $userid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT id, timecreated
               FROM {flwcupkp_evidence}
              WHERE userid = :userid
           ORDER BY timecreated DESC, id DESC",
            ['userid' => $userid],
            0,
            160
        );

        $days = [];
        foreach ($records as $record) {
            $day = date('Y-m-d', (int)$record->timecreated);
            if (!isset($days[$day])) {
                $days[$day] = true;
            }
        }
        $days = array_keys($days);

        if (!$days) {
            return [
                'value' => '0',
                'detail' => get_string('streakempty', 'local_flwcupkp'),
            ];
        }

        $streak = 0;
        $expected = strtotime($days[0] . ' 00:00:00');
        foreach ($days as $day) {
            if ($day !== date('Y-m-d', $expected)) {
                break;
            }
            $streak++;
            $expected -= DAYSECS;
        }

        return [
            'value' => (string)$streak,
            'detail' => get_string('streakdetail', 'local_flwcupkp',
                userdate(strtotime($days[0] . ' 00:00:00'), get_string('strftimedateshort', 'langconfig'))),
        ];
    }

    /**
     * Latest placement/evaluation snapshot for the selected unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    private static function placement_summary(int $courseid, string $unitcode, int $userid): array {
        try {
            $snapshot = learner_evaluation::latest_snapshot($userid, $courseid, 0, $unitcode);
        } catch (\Throwable $e) {
            $snapshot = null;
        }

        if (!$snapshot) {
            return [
                'level' => '-',
                'detail' => get_string('placementnosync', 'local_flwcupkp'),
                'synced' => false,
                'syncvalue' => get_string('notlinked', 'local_flwcupkp'),
                'syncdetail' => get_string('examplacementnosync', 'local_flwcupkp'),
            ];
        }

        $summary = json_decode((string)$snapshot->summaryjson, true);
        if (!is_array($summary)) {
            $summary = [];
        }
        $level = trim((string)$snapshot->cefrinterpretation);
        if ($level === '') {
            $level = trim((string)($summary['cefr_interpretation'] ?? $summary['cefr'] ?? ''));
        }
        if ($level === '') {
            $level = get_string('placementlevelunknown', 'local_flwcupkp');
        }

        $date = userdate((int)$snapshot->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        return [
            'level' => $level,
            'detail' => get_string('placementsynceddetail', 'local_flwcupkp', $date),
            'synced' => true,
            'syncvalue' => get_string('linked', 'local_flwcupkp'),
            'syncdetail' => get_string('examplacementsynced', 'local_flwcupkp', $date),
        ];
    }

    /**
     * Latest lesson/activity touched by the learner in a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    private static function last_lesson_summary(int $courseid, string $unitcode, int $userid): array {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT e.id,
                    e.timecreated,
                    o.lesson,
                    o.title,
                    o.cmid,
                    m.name AS modname
               FROM {flwcupkp_evidence} e
          LEFT JOIN {flwcupkp_object} o ON o.id = e.objectid
          LEFT JOIN {course_modules} cm ON cm.id = o.cmid
          LEFT JOIN {modules} m ON m.id = cm.module
              WHERE e.userid = :userid
                AND e.courseid = :courseid
                AND e.unitcode = :unitcode
           ORDER BY e.timecreated DESC, e.id DESC",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
            ],
            IGNORE_MULTIPLE
        );

        if (!$record) {
            return [
                'value' => '-',
                'detail' => get_string('nolastlesson', 'local_flwcupkp'),
                'url' => null,
            ];
        }

        $lesson = trim((string)($record->lesson ?? ''));
        $value = $lesson !== '' ? get_string('lesson', 'local_flwcupkp') . ' ' . $lesson :
            get_string('lastactivity', 'local_flwcupkp');
        $detail = trim((string)($record->title ?? ''));
        if ($detail === '') {
            $detail = userdate((int)$record->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        }

        $url = null;
        if (!empty($record->cmid) && !empty($record->modname)) {
            $url = new \moodle_url('/mod/' . $record->modname . '/view.php', ['id' => (int)$record->cmid]);
        }

        return [
            'value' => $value,
            'detail' => $detail,
            'url' => $url,
        ];
    }

    /**
     * Build the Dashboard "today's learning" panel.
     *
     * @param array $unit
     * @return string
     */
    private static function today_learning_panel(array $unit): string {
        $courseid = (int)$unit['courseid'];
        $unitcode = (string)$unit['unitcode'];
        $progress = $unit['progress'];
        $next = $progress['next_recommendation'] ?? null;
        $progressurl = self::student_url($courseid, $unitcode);

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-control-panel']);
        $html .= \html_writer::tag('h3', get_string('todayslearning', 'local_flwcupkp'));

        if ($next) {
            $externalid = self::next_externalid($next);
            $title = self::next_title($next);
            $focus = $externalid !== '' ? $externalid . ' - ' . $title : $title;
            $html .= \html_writer::tag('div', s($focus), ['class' => 'local-flwcupkp-control-focus']);
            if (!empty($next['next_activity']['reason'])) {
                $html .= \html_writer::tag('p', s((string)$next['next_activity']['reason']));
            }

            $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
            if (!empty($next['next_activity']['url'])) {
                $html .= \html_writer::link($next['next_activity']['url'],
                    s((string)$next['next_activity']['title']), ['class' => 'btn btn-primary btn-sm']);
            }
            $html .= visuals::nav_link($progressurl, get_string('openlearningpath', 'local_flwcupkp'),
                ['class' => 'btn btn-secondary btn-sm']);
            $html .= \html_writer::end_tag('div');
        } else {
            $html .= \html_writer::tag('p', get_string('todaylearningclear', 'local_flwcupkp'));
            $html .= \html_writer::tag('div',
                visuals::nav_link($progressurl, get_string('openlearningpath', 'local_flwcupkp'),
                    ['class' => 'btn btn-primary btn-sm']),
                ['class' => 'local-flwcupkp-formactions']
            );
        }

        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Build the Dashboard unit map panel.
     *
     * @param array $units
     * @param array $primary
     * @return string
     */
    private static function unit_map_panel(array $units, array $primary): string {
        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-control-panel']);
        $html .= \html_writer::tag('h3', get_string('unitmap', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-control-unit-list']);

        foreach ($units as $unit) {
            $summary = $unit['progress']['summary'] ?? [];
            $percent = max(0, min(100, (int)($summary['percent'] ?? 0)));
            $url = self::student_url((int)$unit['courseid'], (string)$unit['unitcode']);
            $current = (int)$unit['courseid'] === (int)$primary['courseid'] &&
                (string)$unit['unitcode'] === (string)$primary['unitcode'];
            $attributes = [
                'class' => 'local-flwcupkp-control-unit' . ($current ? ' local-flwcupkp-control-current' : ''),
            ];

            $content = \html_writer::start_tag('span', ['class' => 'local-flwcupkp-control-unit-copy']);
            $content .= \html_writer::tag('strong', s((string)$unit['coursefullname']));
            $content .= \html_writer::tag('em', get_string('unitmapdetail', 'local_flwcupkp', (object)[
                'percent' => $percent,
                'mastered' => (int)($summary['mastered'] ?? 0),
                'total' => (int)($summary['total'] ?? 0),
                'gaps' => (int)($summary['gaps'] ?? 0),
            ]));
            $content .= \html_writer::end_tag('span');
            $content .= \html_writer::start_tag('span', ['class' => 'local-flwcupkp-control-mapbar',
                'aria-hidden' => 'true']);
            $content .= \html_writer::tag('span', '', ['style' => 'width: ' . $percent . '%']);
            $content .= \html_writer::end_tag('span');

            $html .= \html_writer::link($url, $content, visuals::nav_attributes($url, $attributes));
        }

        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Build the Dashboard vocabulary review panel.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return string
     */
    private static function vocabulary_review_panel(int $courseid, string $unitcode, int $userid): string {
        $summary = self::vocabulary_review_summary($courseid, $unitcode, $userid);
        $url = self::student_url($courseid, $unitcode);

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-control-panel']);
        $html .= \html_writer::tag('h3', get_string('vocabularyreview', 'local_flwcupkp'));
        $html .= \html_writer::tag('div', s($summary['value']), ['class' => 'local-flwcupkp-control-panel-value']);
        $html .= \html_writer::tag('p', s($summary['detail']));
        $html .= \html_writer::tag('div',
            visuals::nav_link($url, get_string('openlearningpath', 'local_flwcupkp'),
                ['class' => 'btn btn-secondary btn-sm']),
            ['class' => 'local-flwcupkp-formactions']
        );
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Count vocabulary KPs that need practice or review.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    private static function vocabulary_review_summary(int $courseid, string $unitcode, int $userid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT kp.id, kp.externalid, kp.title
               FROM {flwcupkp_object} o
               JOIN {flwcupkp_object_map} om ON om.objectid = o.id
               JOIN {flwcupkp_kp} kp ON kp.id = om.targetid
          LEFT JOIN {flwcupkp_state} s ON s.userid = :userid
                    AND s.targettype = 'kp'
                    AND s.targetid = kp.id
              WHERE o.unitcode = :unitcode
                AND (:courseid = 0 OR o.courseid = :courseidmatch OR o.courseid IS NULL)
                AND om.targettype = 'kp'
                AND UPPER(kp.domain) IN ('LEX', 'VOCAB', 'VOCABULARY')
                AND (s.id IS NULL
                    OR s.masterystate <> :mastered
                    OR (s.nextreview IS NOT NULL AND s.nextreview > 0 AND s.nextreview <= :now))",
            [
                'userid' => $userid,
                'unitcode' => $unitcode,
                'courseid' => $courseid,
                'courseidmatch' => $courseid,
                'mastered' => 'mastered',
                'now' => time(),
            ]
        );

        $count = count($records);
        return [
            'value' => (string)$count,
            'detail' => $count > 0 ?
                get_string('vocabularyreviewdetail', 'local_flwcupkp', $count) :
                get_string('vocabularyclear', 'local_flwcupkp'),
        ];
    }

    /**
     * Build the Dashboard exam/placement sync panel.
     *
     * @param array $placement
     * @param \moodle_url $url
     * @return string
     */
    private static function exam_sync_panel(array $placement, \moodle_url $url): string {
        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-control-panel']);
        $html .= \html_writer::tag('h3', get_string('examplacementsync', 'local_flwcupkp'));
        $html .= \html_writer::tag('div', s((string)$placement['syncvalue']),
            ['class' => 'local-flwcupkp-control-panel-value']);
        $html .= \html_writer::tag('p', s((string)$placement['syncdetail']));
        $html .= \html_writer::tag('div',
            visuals::nav_link($url, get_string('openmylearningpath', 'local_flwcupkp'),
                ['class' => 'btn btn-secondary btn-sm']),
            ['class' => 'local-flwcupkp-formactions']
        );
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * External ID for a next recommendation row.
     *
     * @param array $row
     * @return string
     */
    private static function next_externalid(array $row): string {
        return (string)($row['kp_externalid'] ?? $row['externalid'] ?? '');
    }

    /**
     * Title for a next recommendation row.
     *
     * @param array $row
     * @return string
     */
    private static function next_title(array $row): string {
        return (string)($row['kp_title'] ?? $row['title'] ?? get_string('uxnextdefaulttitle', 'local_flwcupkp'));
    }

    /**
     * Learner evaluation URL for a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return \moodle_url
     */
    private static function evaluation_url(int $courseid, string $unitcode): \moodle_url {
        return new \moodle_url('/local/flwcupkp/evaluation.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]);
    }

    /**
     * Clone a Moodle URL and add an in-page anchor.
     *
     * @param \moodle_url $url
     * @param string $anchor
     * @return \moodle_url
     */
    private static function anchored_url(\moodle_url $url, string $anchor): \moodle_url {
        $anchored = clone $url;
        $anchored->set_anchor($anchor);
        return $anchored;
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
            visuals::nav_link($progressurl, get_string('courseprogresslinkunit', 'local_flwcupkp', $unitcode), [
                'class' => 'btn btn-secondary btn-sm',
            ]) . visuals::nav_link(self::learning_timeline_url($courseid, $unitcode),
                get_string('openlearningtimeline', 'local_flwcupkp'), ['class' => 'btn btn-primary btn-sm']),
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
        $html .= visuals::nav_link(self::student_url($courseid, $unitcode),
            get_string('courseprogresslinkunit', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link(self::learning_timeline_url($courseid, $unitcode),
            get_string('openlearningtimeline', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link(self::staff_intelligence_url($courseid, $unitcode),
            get_string('openstaffintelligence', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link(self::teacher_url($courseid, $unitcode),
            get_string('courseteacherlinkunit', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-primary btn-sm']);
        if (performance_service::has_tasks($courseid, $unitcode)) {
            $html .= visuals::nav_link(self::performance_url($courseid, $unitcode),
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
     * Integrated Past, Present, and Future URL for a unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return \moodle_url
     */
    private static function learning_timeline_url(int $courseid, string $unitcode): \moodle_url {
        return new \moodle_url('/local/flwcupkp/learning_timeline.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]);
    }

    /** Staff explainability and intervention URL for a unit. */
    private static function staff_intelligence_url(int $courseid, string $unitcode): \moodle_url {
        return new \moodle_url('/local/flwcupkp/staff_intelligence.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]);
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
        if ($unitcode === 'U038') {
            return new \moodle_url('/local/flwcupkp/performance_u038.php', ['courseid' => $courseid]);
        }

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
            $html .= visuals::nav_link(new \moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]),
                get_string('courseprogresslinku038', 'local_flwcupkp'), ['class' => 'btn btn-primary btn-sm']);
        }

        $html .= \html_writer::tag('div',
            get_string('mastered', 'local_flwcupkp') . ': ' . (int)$summary['mastered'] . ' / ' .
            (int)$summary['total'] . ' | ' . get_string('needpractice', 'local_flwcupkp') . ': ' .
            (int)$summary['gaps'],
            ['class' => 'local-flwcupkp-course-next-meta']
        );
        $html .= \html_writer::tag('div',
            visuals::nav_link(self::learning_timeline_url($courseid, 'U038'),
                get_string('openlearningtimeline', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']),
            ['class' => 'local-flwcupkp-formactions']
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
        $html .= visuals::nav_link($progressurl, get_string('courseprogresslinku038', 'local_flwcupkp'),
            ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link(self::learning_timeline_url($courseid, 'U038'),
            get_string('openlearningtimeline', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link(self::staff_intelligence_url($courseid, 'U038'),
            get_string('openstaffintelligence', 'local_flwcupkp'), ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link($verificationurl, get_string('courseverificationlinku038', 'local_flwcupkp'),
            ['class' => 'btn btn-secondary btn-sm']);
        $html .= visuals::nav_link($performanceurl, get_string('performanceu038', 'local_flwcupkp'),
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
            return \html_writer::link($url, $content, visuals::nav_attributes($url, $attributes));
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
            return \html_writer::link($url, $content, visuals::nav_attributes($url, $attributes));
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

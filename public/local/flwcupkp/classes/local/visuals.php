<?php
// Visual dashboard renderers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared HTML/CSS visualizations for C-UP-KP reports.
 */
final class visuals {
    /** @var array States that represent strong mastery. */
    private const STRONG_STATES = [
        'mastered',
        'demonstrated',
        'stable',
        'transfer_ready',
        'achieved',
        'sustained',
    ];

    /** @var array Empty states with no usable evidence yet. */
    private const EMPTY_STATES = [
        'not_observed',
        'not_introduced',
        'not_started',
    ];

    /** @var array Diagnostic categories ordered from most directly actionable to least. */
    private const DIAGNOSTIC_PRIORITY = [
        'missing_evidence' => 10,
        'review_due' => 20,
        'self_eval_mismatch' => 30,
        'low_confidence' => 40,
        'stale_evidence' => 50,
        'mastery_gap' => 60,
    ];

    /**
     * Render a learner-friendly state badge.
     *
     * @param string $state
     * @return string
     */
    public static function state_badge(string $state): string {
        $state = trim($state) !== '' ? $state : 'not_observed';
        return \html_writer::tag('span', s(self::state_label($state)), [
            'class' => 'local-flwcupkp-state local-flwcupkp-state-' . clean_param($state, PARAM_ALPHANUMEXT),
            'title' => $state,
        ]);
    }

    /**
     * Human-readable state label.
     *
     * @param string $state
     * @return string
     */
    public static function state_label(string $state): string {
        return self::string_or_human('state_' . self::identifier_suffix($state), $state);
    }

    /**
     * Human-readable diagnostic category label.
     *
     * @param string $category
     * @return string
     */
    public static function diagnostic_label(string $category): string {
        return self::string_or_human('diagnostic_' . self::identifier_suffix($category), $category);
    }

    /**
     * Human-readable target type label.
     *
     * @param string $targettype
     * @return string
     */
    public static function target_type_label(string $targettype): string {
        if ($targettype === 'kp') {
            return get_string('knowledgepoint', 'local_flwcupkp');
        }
        if ($targettype === 'up') {
            return get_string('usepoint', 'local_flwcupkp');
        }
        if ($targettype === 'competency') {
            return get_string('competency', 'local_flwcupkp');
        }
        return self::human_label($targettype);
    }

    /**
     * Human-readable target label by type and id.
     *
     * @param string $targettype
     * @param int $targetid
     * @return string
     */
    public static function target_label(string $targettype, int $targetid): string {
        global $DB;

        $table = null;
        if ($targettype === 'competency') {
            $table = 'flwcupkp_comp';
        } else if ($targettype === 'up') {
            $table = 'flwcupkp_up';
        } else if ($targettype === 'kp') {
            $table = 'flwcupkp_kp';
        }

        if ($table !== null && $targetid > 0) {
            $record = $DB->get_record($table, ['id' => $targetid], 'id, externalid, title', IGNORE_MISSING);
            if ($record) {
                $externalid = trim((string)$record->externalid);
                $title = trim((string)$record->title);
                if ($externalid !== '' && $title !== '') {
                    return $externalid . ' - ' . $title;
                }
                return $externalid !== '' ? $externalid : $title;
            }
        }

        return self::target_type_label($targettype) . ' #' . $targetid;
    }

    /**
     * Render a reusable collapsible detail panel.
     *
     * @param string $heading
     * @param string $content
     * @param bool $open
     * @param string $extraclass
     * @return string
     */
    public static function details_panel(string $heading, string $content, bool $open = false,
            string $extraclass = ''): string {
        $attributes = ['class' => trim('local-flwcupkp-detail-panel ' . $extraclass)];
        if ($open) {
            $attributes['open'] = 'open';
        }
        return \html_writer::tag('details', \html_writer::tag('summary', s($heading)) . $content, $attributes);
    }

    /**
     * Render a link that marks itself active when it points to the current plugin page.
     *
     * @param \moodle_url $url
     * @param string $label
     * @param array $attributes
     * @return string
     */
    public static function nav_link(\moodle_url $url, string $label, array $attributes = []): string {
        return \html_writer::link($url, s($label), self::nav_attributes($url, $attributes));
    }

    /**
     * Render the main per-unit navigation bar with active state.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param bool $showteacher
     * @param bool $showperformance
     * @return string
     */
    public static function unit_nav(int $courseid, string $unitcode, int $userid = 0, bool $showteacher = false,
            bool $showperformance = false): string {
        global $PAGE;

        if ($courseid <= 0 || $unitcode === '') {
            return '';
        }

        $progressurl = $unitcode === 'U038' ?
            new \moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]) :
            new \moodle_url('/local/flwcupkp/student.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
        if ($userid > 0) {
            $progressurl->param('userid', $userid);
        }

        $evaluationurl = new \moodle_url('/local/flwcupkp/evaluation.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]);
        if ($userid > 0) {
            $evaluationurl->param('userid', $userid);
        }

        $html = \html_writer::start_tag('nav', [
            'class' => 'local-flwcupkp-toolbar local-flwcupkp-mainnav',
            'aria-label' => get_string('mainnavigation', 'local_flwcupkp'),
        ]);
        $html .= self::nav_link(new \moodle_url('/course/view.php', ['id' => $courseid]), get_string('course'), [
            'class' => 'btn btn-secondary',
        ]);
        $html .= self::nav_link($progressurl, get_string('unitprogress', 'local_flwcupkp'), [
            'class' => 'btn btn-secondary',
        ]);
        $html .= self::nav_link($evaluationurl, get_string('learnerevaluation', 'local_flwcupkp'), [
            'class' => 'btn btn-secondary',
        ]);

        if ($showteacher) {
            $teacherurl = $unitcode === 'U038' ?
                new \moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]) :
                new \moodle_url('/local/flwcupkp/teacher.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
            $html .= self::nav_link($teacherurl, get_string('unitteachernav', 'local_flwcupkp', $unitcode), [
                'class' => 'btn btn-secondary',
            ]);
        }

        if ($showperformance) {
            $performancepath = $unitcode === 'U038' ?
                '/local/flwcupkp/performance_u038.php' :
                '/local/flwcupkp/performance.php';
            $performanceparams = ['courseid' => $courseid];
            if ($performancepath !== '/local/flwcupkp/performance_u038.php') {
                $performanceparams['unitcode'] = $unitcode;
            }
            $performanceurl = new \moodle_url($performancepath, $performanceparams);
            $html .= self::nav_link($performanceurl, get_string('unitperformancenav', 'local_flwcupkp', $unitcode), [
                'class' => 'btn btn-secondary',
            ]);
        }

        $html .= \html_writer::end_tag('nav');

        return $html;
    }

    /**
     * Add active/current attributes when a URL points to the current plugin page.
     *
     * @param \moodle_url $url
     * @param array $attributes
     * @return array
     */
    public static function nav_attributes(\moodle_url $url, array $attributes = []): array {
        if (!self::is_current_url($url)) {
            return $attributes;
        }

        $attributes['class'] = trim(($attributes['class'] ?? '') . ' active');
        $attributes['aria-current'] = 'page';
        return $attributes;
    }

    /**
     * Whether a URL points to the currently rendered page and scope.
     *
     * @param \moodle_url $url
     * @return bool
     */
    public static function is_current_url(\moodle_url $url): bool {
        global $PAGE;

        if (empty($PAGE)) {
            return false;
        }

        try {
            $current = $PAGE->url;
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_object($current) || !method_exists($current, 'get_path') || !method_exists($current, 'get_param')) {
            return false;
        }

        if ($current->get_path() !== $url->get_path()) {
            return false;
        }

        foreach (['courseid', 'id', 'unitcode'] as $param) {
            $expected = $url->get_param($param);
            if ($expected !== null && (string)$current->get_param($param) !== (string)$expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render a plain-language next action card for the learner evaluation page.
     *
     * @param array $profile
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return string
     */
    public static function evaluation_next_action(array $profile, int $courseid = 0, string $unitcode = '',
            int $userid = 0): string {
        $diagnostic = self::primary_diagnostic($profile['diagnostics'] ?? []);
        $recommendation = reset($profile['recommendations']) ?: null;
        $summary = $profile['summary'] ?? [];

        if ($diagnostic) {
            $category = (string)($diagnostic->gapcategory ?? '');
            $target = self::target_label((string)($diagnostic->targettype ?? ''), (int)($diagnostic->targetid ?? 0));
            $title = self::next_action_title($category);
            $detail = get_string('uxnextdiagnosticdetail', 'local_flwcupkp', (object)[
                'target' => $target,
                'category' => self::diagnostic_label($category),
            ]);
            $reason = (string)($diagnostic->diagnosticreason ?? '');
        } else if ($recommendation) {
            $title = get_string('uxnextrecommendationtitle', 'local_flwcupkp');
            $detail = (string)($recommendation->reason ?? get_string('recommendations', 'local_flwcupkp'));
            $reason = (string)($recommendation->expectedbenefit ?? '');
        } else {
            $title = get_string('uxnextreadytitle', 'local_flwcupkp');
            $detail = get_string('uxnextreadydetail', 'local_flwcupkp');
            $reason = get_string('uxnextreadysubdetail', 'local_flwcupkp', (object)[
                'evidence' => (int)($summary['evidence_count'] ?? 0),
                'states' => (int)($summary['state_count'] ?? 0),
            ]);
        }

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-ux-card local-flwcupkp-next-action']);
        $html .= \html_writer::tag('span', get_string('uxnextlabel', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-course-next-label',
        ]);
        $html .= \html_writer::tag('h3', s($title));
        $html .= \html_writer::tag('p', s($detail));
        if (trim($reason) !== '') {
            $html .= \html_writer::tag('p', s($reason), ['class' => 'local-flwcupkp-muted']);
        }

        $progressurl = self::progress_url($courseid, $unitcode, $userid);
        if ($progressurl) {
            $html .= \html_writer::link($progressurl, get_string('openprogressdetails', 'local_flwcupkp'), [
                'class' => 'btn btn-primary btn-sm',
            ]);
        }
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render the most important diagnostics as readable cards.
     *
     * @param array $diagnostics
     * @param int $limit
     * @return string
     */
    public static function diagnostic_cards(array $diagnostics, int $limit = 5): string {
        if (empty($diagnostics)) {
            return '';
        }

        $diagnostics = self::sort_diagnostics($diagnostics);
        $visible = array_slice($diagnostics, 0, $limit);
        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-visual-panel']);
        $html .= \html_writer::tag('h3', get_string('topdiagnostics', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-issue-list']);
        foreach ($visible as $diagnostic) {
            $category = (string)($diagnostic->gapcategory ?? '');
            $target = self::target_label((string)($diagnostic->targettype ?? ''), (int)($diagnostic->targetid ?? 0));
            $html .= \html_writer::tag('div',
                \html_writer::tag('strong', s(self::diagnostic_label($category))) .
                \html_writer::tag('span', s($target)) .
                \html_writer::tag('em', s((string)($diagnostic->diagnosticreason ?? ''))),
                ['class' => 'local-flwcupkp-issue-card']
            );
        }
        $remaining = count($diagnostics) - count($visible);
        if ($remaining > 0) {
            $html .= \html_writer::tag('p', get_string('additionaldiagnostics', 'local_flwcupkp', $remaining), [
                'class' => 'local-flwcupkp-muted',
            ]);
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render learner evaluation progress rings.
     *
     * @param array $summary
     * @return string
     */
    public static function evaluation_rings(array $summary): string {
        return self::progress_rings([
            [
                'label' => get_string('learningpoints', 'local_flwcupkp'),
                'value' => (int)($summary['kp_mastered'] ?? 0),
                'total' => (int)($summary['kp_total'] ?? 0),
            ],
            [
                'label' => get_string('usepoints', 'local_flwcupkp'),
                'value' => (int)($summary['up_demonstrated'] ?? 0),
                'total' => (int)($summary['up_total'] ?? 0),
            ],
            [
                'label' => get_string('competencies', 'local_flwcupkp'),
                'value' => (int)($summary['competency_achieved'] ?? 0),
                'total' => (int)($summary['competency_total'] ?? 0),
            ],
            [
                'label' => get_string('averageconfidence', 'local_flwcupkp'),
                'percent' => (float)($summary['average_confidence'] ?? 0) * 100,
                'detail' => format_float((float)($summary['average_confidence'] ?? 0), 2),
            ],
        ], get_string('visualprogressrings', 'local_flwcupkp'));
    }

    /**
     * Render student progress rings.
     *
     * @param array $summary
     * @param array $parentsummary
     * @return string
     */
    public static function student_progress_rings(array $summary, array $parentsummary): string {
        return self::progress_rings([
            [
                'label' => get_string('learningpoints', 'local_flwcupkp'),
                'value' => (int)($summary['mastered'] ?? 0),
                'total' => (int)($summary['total'] ?? 0),
            ],
            [
                'label' => get_string('withevidence', 'local_flwcupkp'),
                'value' => (int)($summary['with_evidence'] ?? 0),
                'total' => (int)($summary['total'] ?? 0),
            ],
            [
                'label' => get_string('usepoints', 'local_flwcupkp'),
                'value' => (int)($parentsummary['up_demonstrated'] ?? 0),
                'total' => (int)($parentsummary['up_total'] ?? 0),
            ],
            [
                'label' => get_string('competencies', 'local_flwcupkp'),
                'value' => (int)($parentsummary['competency_achieved'] ?? 0),
                'total' => (int)($parentsummary['competency_total'] ?? 0),
            ],
        ], get_string('visualprogressrings', 'local_flwcupkp'));
    }

    /**
     * Render a small ring dashboard.
     *
     * @param array $items
     * @param string $heading
     * @return string
     */
    public static function progress_rings(array $items, string $heading): string {
        if (empty($items)) {
            return '';
        }

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-visual-panel']);
        $html .= \html_writer::tag('h3', s($heading));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ring-grid']);
        foreach ($items as $item) {
            $percent = array_key_exists('percent', $item) ? (float)$item['percent'] :
                self::percent((float)($item['value'] ?? 0), (float)($item['total'] ?? 0));
            $percent = self::clamp_percent($percent);
            $detail = (string)($item['detail'] ?? ((int)($item['value'] ?? 0) . '/' . (int)($item['total'] ?? 0)));
            $html .= self::progress_ring((string)$item['label'], $percent, $detail);
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render diagnostic category breakdown.
     *
     * @param array $diagnostics
     * @return string
     */
    public static function diagnostic_chart(array $diagnostics): string {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            $category = (string)($diagnostic->gapcategory ?? 'unknown');
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }
        arsort($counts);

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-visual-panel']);
        $html .= \html_writer::tag('h3', get_string('diagnosticbreakdown', 'local_flwcupkp'));
        if (empty($counts)) {
            $html .= \html_writer::tag('p', get_string('nodiagnostics', 'local_flwcupkp'), [
                'class' => 'local-flwcupkp-muted',
            ]);
            $html .= \html_writer::end_tag('section');
            return $html;
        }

        $max = max($counts);
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-bar-chart']);
        foreach ($counts as $category => $count) {
            $width = $max > 0 ? self::clamp_percent(($count / $max) * 100) : 0;
            $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-bar-row']);
            $html .= \html_writer::tag('span', s(self::diagnostic_label($category)));
            $html .= \html_writer::tag('div',
                \html_writer::tag('span', '', ['style' => 'width: ' . $width . '%']),
                ['class' => 'local-flwcupkp-bar-track', 'aria-hidden' => 'true']
            );
            $html .= \html_writer::tag('strong', (string)(int)$count);
            $html .= \html_writer::end_tag('div');
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render an evaluation event timeline.
     *
     * @param array $profile
     * @return string
     */
    public static function evaluation_timeline(array $profile): string {
        $summary = $profile['summary'] ?? [];
        $snapshot = $profile['latest_snapshot'] ?? null;
        $steps = [
            [
                'label' => get_string('timelineactivity', 'local_flwcupkp'),
                'status' => ((int)($summary['evidence_count'] ?? 0) > 0) ? 'done' : 'todo',
                'detail' => get_string('timelineactivitydetail', 'local_flwcupkp',
                    (int)($summary['evidence_count'] ?? 0)),
            ],
            [
                'label' => get_string('timelinemastery', 'local_flwcupkp'),
                'status' => ((int)($summary['state_count'] ?? 0) > 0) ? 'done' : 'todo',
                'detail' => get_string('timelinemasterydetail', 'local_flwcupkp',
                    (int)($summary['state_count'] ?? 0)),
            ],
            [
                'label' => get_string('timelineselfeval', 'local_flwcupkp'),
                'status' => ((int)($summary['self_eval_count'] ?? 0) > 0) ? 'done' : 'todo',
                'detail' => get_string('timelineselfevaldetail', 'local_flwcupkp',
                    (int)($summary['self_eval_count'] ?? 0)),
            ],
            [
                'label' => get_string('timelinediagnostics', 'local_flwcupkp'),
                'status' => ((int)($summary['diagnostic_count'] ?? 0) > 0) ? 'attention' : 'done',
                'detail' => get_string('timelinediagnosticsdetail', 'local_flwcupkp',
                    (int)($summary['diagnostic_count'] ?? 0)),
            ],
            [
                'label' => get_string('timelinesnapshot', 'local_flwcupkp'),
                'status' => $snapshot ? 'done' : 'todo',
                'detail' => $snapshot ? userdate((int)$snapshot['timecreated']) :
                    get_string('timelinesnapshotempty', 'local_flwcupkp'),
            ],
        ];

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-visual-panel']);
        $html .= \html_writer::tag('h3', get_string('evaluationtimeline', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('ol', ['class' => 'local-flwcupkp-timeline']);
        foreach ($steps as $step) {
            $html .= \html_writer::tag('li',
                \html_writer::tag('strong', s($step['label'])) .
                \html_writer::tag('span', s($step['detail'])),
                ['class' => 'local-flwcupkp-timeline-' . clean_param($step['status'], PARAM_ALPHANUMEXT)]
            );
        }
        $html .= \html_writer::end_tag('ol');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render setup stepper.
     *
     * @param array|null $status
     * @param int $courseid
     * @param string $unitcode
     * @return string
     */
    public static function setup_stepper(?array $status, int $courseid, string $unitcode): string {
        global $DB;

        $objectcount = (int)($status['objectcount'] ?? 0);
        $linked = (int)($status['counts']['linked'] ?? 0);
        $ready = !empty($status['activation']['ready']);
        $evidencecount = 0;
        if ($courseid > 0 && $unitcode !== '') {
            $evidencecount = (int)$DB->count_records('flwcupkp_evidence', [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
            ]);
        }

        $steps = [
            [
                'label' => get_string('setupsteppercourse', 'local_flwcupkp'),
                'done' => $courseid > 0 && $unitcode !== '',
                'detail' => $unitcode !== '' ? $unitcode : get_string('setupnounitselected', 'local_flwcupkp'),
            ],
            [
                'label' => get_string('setupstepperimport', 'local_flwcupkp'),
                'done' => $objectcount > 0,
                'detail' => get_string('setupstepperobjectdetail', 'local_flwcupkp', $objectcount),
            ],
            [
                'label' => get_string('setupstepperlinks', 'local_flwcupkp'),
                'done' => $objectcount > 0 && $linked === $objectcount,
                'detail' => get_string('setupstepperlinkdetail', 'local_flwcupkp',
                    (object)['linked' => $linked, 'total' => $objectcount]),
            ],
            [
                'label' => get_string('setupstepperactivate', 'local_flwcupkp'),
                'done' => $ready,
                'detail' => $ready ? get_string('active', 'local_flwcupkp') : get_string('notready', 'local_flwcupkp'),
            ],
            [
                'label' => get_string('setupstepperevidence', 'local_flwcupkp'),
                'done' => $evidencecount > 0,
                'detail' => get_string('setupstepperevidencedetail', 'local_flwcupkp', $evidencecount),
            ],
        ];

        $html = \html_writer::start_tag('section', ['class' => 'local-flwcupkp-setup-stepper']);
        $html .= \html_writer::tag('h3', get_string('setupstepper', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('ol');
        foreach ($steps as $index => $step) {
            $class = $step['done'] ? 'done' : 'todo';
            if (!$step['done'] && self::is_first_unfinished($steps, $index)) {
                $class = 'current';
            }
            $html .= \html_writer::tag('li',
                \html_writer::tag('strong', s($step['label'])) .
                \html_writer::tag('span', s($step['detail'])),
                ['class' => 'local-flwcupkp-setup-step-' . $class]
            );
        }
        $html .= \html_writer::end_tag('ol');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render teacher class heatmap.
     *
     * @param array $report
     * @param \moodle_url $baseurl
     * @return string
     */
    public static function teacher_heatmap(array $report, \moodle_url $baseurl): string {
        if (empty($report['learners']) || empty($report['targets'])) {
            return '';
        }

        $rows = [];
        foreach ($report['rows'] as $row) {
            $rows[(int)$row['userid'] . ':' . (int)$row['kp_id']] = $row;
        }

        $targets = array_values($report['targets']);
        $learners = array_values($report['learners']);

        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-visual-panel local-flwcupkp-heatmap-panel',
        ]);
        $html .= \html_writer::tag('h3', get_string('classheatmap', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-heatmap-wrap']);
        $style = '--flwcupkp-heatmap-cols: ' . max(1, count($targets)) . ';';
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-heatmap', 'style' => $style]);
        $html .= \html_writer::tag('div', get_string('learner', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-heatmap-corner',
        ]);
        foreach ($targets as $target) {
            $html .= \html_writer::tag('div',
                \html_writer::tag('strong', s((string)$target->kpexternalid)) .
                \html_writer::tag('span', s((string)$target->lesson)),
                ['class' => 'local-flwcupkp-heatmap-head']
            );
        }

        foreach ($learners as $learner) {
            $html .= \html_writer::tag('div', s(fullname($learner)), ['class' => 'local-flwcupkp-heatmap-learner']);
            foreach ($targets as $target) {
                $key = (int)$learner->id . ':' . (int)$target->kpid;
                $row = $rows[$key] ?? null;
                $state = $row ? (string)$row['state'] : 'not_observed';
                $anchor = 'u' . (int)$learner->id . '-kp' . (int)$target->kpid;
                $url = clone $baseurl;
                $url->param('focus', $anchor);
                $url->set_anchor('flwcupkp-row-' . $anchor);
                $label = fullname($learner) . ' / ' . (string)$target->kpexternalid . ' / ' .
                    self::state_label($state);
                $score = $row && $row['mastery_score'] !== null ? format_float((float)$row['mastery_score'], 2) : '';
                $html .= \html_writer::link($url,
                    \html_writer::tag('span', s($score !== '' ? $score : self::state_short_label($state))) .
                    \html_writer::tag('em', s(self::state_label($state))),
                    [
                        'class' => 'local-flwcupkp-heatmap-cell ' . self::state_class($state),
                        'title' => $label,
                        'aria-label' => $label,
                    ]
                );
            }
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render a Competency -> UP -> KP -> Activity hierarchy map.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return string
     */
    public static function hierarchy_map(int $courseid, string $unitcode, int $userid): string {
        $data = self::hierarchy_data($courseid, $unitcode, $userid);
        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-visual-panel local-flwcupkp-hierarchy-panel',
        ]);
        $html .= \html_writer::tag('h3', get_string('cupkphierarchy', 'local_flwcupkp'));

        if (empty($data['competencies'])) {
            $html .= \html_writer::tag('p', get_string('nographrows', 'local_flwcupkp'), [
                'class' => 'local-flwcupkp-muted',
            ]);
            $html .= \html_writer::end_tag('section');
            return $html;
        }

        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-hierarchy']);
        foreach ($data['competencies'] as $competencyid => $competency) {
            $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-hierarchy-band']);
            $html .= self::hierarchy_node('competency', $competency, $data['states']['competency:' . $competencyid] ?? null);
            $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-hierarchy-paths']);

            foreach ($data['upsbycomp'][$competencyid] ?? [] as $upid => $up) {
                $kps = $data['kpsbyup'][$upid] ?? [];
                if (empty($kps)) {
                    $html .= self::hierarchy_path(
                        self::hierarchy_node('up', $up, $data['states']['up:' . $upid] ?? null),
                        \html_writer::tag('span', get_string('none'), ['class' => 'local-flwcupkp-muted']),
                        ''
                    );
                    continue;
                }
                foreach ($kps as $kpid => $kp) {
                    $objects = $data['objectsbykp'][$kpid] ?? [];
                    $objecthtml = self::activity_nodes($objects);
                    $html .= self::hierarchy_path(
                        self::hierarchy_node('up', $up, $data['states']['up:' . $upid] ?? null),
                        self::hierarchy_node('kp', $kp, $data['states']['kp:' . $kpid] ?? null),
                        $objecthtml
                    );
                }
            }
            $html .= \html_writer::end_tag('div');
            $html .= \html_writer::end_tag('div');
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('section');

        return $html;
    }

    /**
     * Render one progress ring.
     *
     * @param string $label
     * @param float $percent
     * @param string $detail
     * @return string
     */
    private static function progress_ring(string $label, float $percent, string $detail): string {
        $rounded = (int)round($percent);
        return \html_writer::tag('div',
            \html_writer::tag('div',
                \html_writer::tag('span', $rounded . '%'),
                [
                    'class' => 'local-flwcupkp-ring',
                    'style' => '--flwcupkp-ring-value: ' . $rounded . '%;',
                    'role' => 'img',
                    'aria-label' => $label . ': ' . $rounded . '%',
                ]
            ) .
            \html_writer::tag('strong', s($label)) .
            \html_writer::tag('em', s($detail)),
            ['class' => 'local-flwcupkp-ring-card']
        );
    }

    /**
     * Render one hierarchy path.
     *
     * @param string $uphtml
     * @param string $kphtml
     * @param string $objecthtml
     * @return string
     */
    private static function hierarchy_path(string $uphtml, string $kphtml, string $objecthtml): string {
        return \html_writer::tag('div',
            $uphtml .
            \html_writer::span('&rarr;', 'local-flwcupkp-hierarchy-arrow') .
            $kphtml .
            \html_writer::span('&rarr;', 'local-flwcupkp-hierarchy-arrow') .
            \html_writer::tag('div', $objecthtml, ['class' => 'local-flwcupkp-hierarchy-activities']),
            ['class' => 'local-flwcupkp-hierarchy-path']
        );
    }

    /**
     * Render one hierarchy node.
     *
     * @param string $type
     * @param \stdClass $record
     * @param \stdClass|null $state
     * @return string
     */
    private static function hierarchy_node(string $type, \stdClass $record, ?\stdClass $state): string {
        $label = (string)($record->externalid ?? $record->title ?? '');
        $title = (string)($record->title ?? '');
        $statevalue = $state ? (string)$state->masterystate : self::default_state($type);
        return \html_writer::tag('span',
            \html_writer::tag('strong', s($label)) .
            ($title !== '' && $title !== $label ? \html_writer::tag('small', s($title)) : '') .
            \html_writer::tag('em', s(self::state_label($statevalue))),
            [
                'class' => 'local-flwcupkp-hierarchy-node local-flwcupkp-hierarchy-' .
                    clean_param($type, PARAM_ALPHANUMEXT) . ' ' . self::state_class($statevalue),
            ]
        );
    }

    /**
     * Render activity chips.
     *
     * @param array $objects
     * @return string
     */
    private static function activity_nodes(array $objects): string {
        if (empty($objects)) {
            return \html_writer::tag('span', get_string('none'), ['class' => 'local-flwcupkp-muted']);
        }

        $html = '';
        $visible = array_slice($objects, 0, 4);
        foreach ($visible as $object) {
            $label = (string)$object->title;
            if (!empty($object->cmid) && !empty($object->modname)) {
                $content = \html_writer::link(new \moodle_url('/mod/' . $object->modname . '/view.php',
                    ['id' => (int)$object->cmid]), s($label));
            } else {
                $content = s($label);
            }
            $html .= \html_writer::tag('span', $content, ['class' => 'local-flwcupkp-activity-chip']);
        }
        if (count($objects) > count($visible)) {
            $html .= \html_writer::tag('span', '+' . (count($objects) - count($visible)), [
                'class' => 'local-flwcupkp-activity-chip',
            ]);
        }
        return $html;
    }

    /**
     * Fetch hierarchy data.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return array
     */
    private static function hierarchy_data(int $courseid, string $unitcode, int $userid): array {
        global $DB;

        $params = ['unitcode' => $unitcode];
        $coursesql = '';
        if ($courseid > 0) {
            $coursesql = 'AND (o.courseid = :courseid OR o.courseid IS NULL)';
            $params['courseid'] = $courseid;
        }

        $records = $DB->get_records_sql(
            "SELECT CONCAT(c.id, '-', u.id, '-', kp.id, '-', o.id) AS rowid,
                    c.id AS competencyid,
                    c.externalid AS competencyexternalid,
                    c.title AS competencytitle,
                    u.id AS upid,
                    u.externalid AS upexternalid,
                    u.title AS uptitle,
                    kp.id AS kpid,
                    kp.externalid AS kpexternalid,
                    kp.title AS kptitle,
                    o.id AS objectid,
                    o.externalid AS objectexternalid,
                    o.title AS objecttitle,
                    o.lesson,
                    o.cmid,
                    m.name AS modname
               FROM {flwcupkp_comp} c
               JOIN {flwcupkp_comp_up} cu ON cu.competencyid = c.id
               JOIN {flwcupkp_up} u ON u.id = cu.upid
               JOIN {flwcupkp_up_kp} uk ON uk.upid = u.id
               JOIN {flwcupkp_kp} kp ON kp.id = uk.kpid
               JOIN {flwcupkp_object_map} om ON om.targettype = 'kp' AND om.targetid = kp.id
               JOIN {flwcupkp_object} o ON o.id = om.objectid
          LEFT JOIN {course_modules} cm ON cm.id = o.cmid
          LEFT JOIN {modules} m ON m.id = cm.module
              WHERE o.unitcode = :unitcode
                    {$coursesql}
           ORDER BY c.externalid ASC, u.externalid ASC, kp.externalid ASC, CAST(o.lesson AS INT), o.externalid ASC",
            $params
        );

        $competencies = [];
        $upsbycomp = [];
        $kpsbyup = [];
        $objectsbykp = [];
        foreach ($records as $row) {
            $competencyid = (int)$row->competencyid;
            $upid = (int)$row->upid;
            $kpid = (int)$row->kpid;
            $objectid = (int)$row->objectid;
            $competencies[$competencyid] = (object)[
                'id' => $competencyid,
                'externalid' => (string)$row->competencyexternalid,
                'title' => (string)$row->competencytitle,
            ];
            $upsbycomp[$competencyid][$upid] = (object)[
                'id' => $upid,
                'externalid' => (string)$row->upexternalid,
                'title' => (string)$row->uptitle,
            ];
            $kpsbyup[$upid][$kpid] = (object)[
                'id' => $kpid,
                'externalid' => (string)$row->kpexternalid,
                'title' => (string)$row->kptitle,
            ];
            $objectsbykp[$kpid][$objectid] = (object)[
                'id' => $objectid,
                'externalid' => (string)$row->objectexternalid,
                'title' => (string)$row->objecttitle,
                'lesson' => (string)$row->lesson,
                'cmid' => (int)$row->cmid,
                'modname' => (string)$row->modname,
            ];
        }

        return [
            'competencies' => $competencies,
            'upsbycomp' => $upsbycomp,
            'kpsbyup' => $kpsbyup,
            'objectsbykp' => $objectsbykp,
            'states' => self::state_map($userid),
        ];
    }

    /**
     * Fetch learner states keyed by target type/id.
     *
     * @param int $userid
     * @return array
     */
    private static function state_map(int $userid): array {
        global $DB;

        if ($userid <= 0) {
            return [];
        }
        $states = [];
        $rows = $DB->get_records('flwcupkp_state', ['userid' => $userid]);
        foreach ($rows as $row) {
            $states[(string)$row->targettype . ':' . (int)$row->targetid] = $row;
        }
        return $states;
    }

    /**
     * Check whether this is the first unfinished setup step.
     *
     * @param array $steps
     * @param int $index
     * @return bool
     */
    private static function is_first_unfinished(array $steps, int $index): bool {
        if (!empty($steps[$index]['done'])) {
            return false;
        }
        for ($i = 0; $i < $index; $i++) {
            if (empty($steps[$i]['done'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Convert state to short heatmap label.
     *
     * @param string $state
     * @return string
     */
    private static function state_short_label(string $state): string {
        if (in_array($state, self::STRONG_STATES, true)) {
            return 'OK';
        }
        if (in_array($state, self::EMPTY_STATES, true)) {
            return '-';
        }
        if ($state === 'review_due') {
            return 'R';
        }
        return '!';
    }

    /**
     * CSS class for a state.
     *
     * @param string $state
     * @return string
     */
    private static function state_class(string $state): string {
        if (in_array($state, self::STRONG_STATES, true)) {
            return 'local-flwcupkp-visual-strong';
        }
        if (in_array($state, self::EMPTY_STATES, true)) {
            return 'local-flwcupkp-visual-empty';
        }
        if ($state === 'review_due') {
            return 'local-flwcupkp-visual-attention';
        }
        return 'local-flwcupkp-visual-developing';
    }

    /**
     * Pick the most actionable diagnostic.
     *
     * @param array $diagnostics
     * @return object|null
     */
    private static function primary_diagnostic(array $diagnostics): ?object {
        $sorted = self::sort_diagnostics($diagnostics);
        return reset($sorted) ?: null;
    }

    /**
     * Sort diagnostics by action priority.
     *
     * @param array $diagnostics
     * @return array
     */
    private static function sort_diagnostics(array $diagnostics): array {
        usort($diagnostics, static function($left, $right): int {
            $leftcategory = (string)($left->gapcategory ?? '');
            $rightcategory = (string)($right->gapcategory ?? '');
            $leftpriority = self::DIAGNOSTIC_PRIORITY[$leftcategory] ?? 100;
            $rightpriority = self::DIAGNOSTIC_PRIORITY[$rightcategory] ?? 100;
            if ($leftpriority !== $rightpriority) {
                return $leftpriority <=> $rightpriority;
            }
            return ((float)($right->confidence ?? 0)) <=> ((float)($left->confidence ?? 0));
        });
        return $diagnostics;
    }

    /**
     * Next-action title for a diagnostic category.
     *
     * @param string $category
     * @return string
     */
    private static function next_action_title(string $category): string {
        $identifier = 'uxnext_' . self::identifier_suffix($category) . '_title';
        if (get_string_manager()->string_exists($identifier, 'local_flwcupkp')) {
            return get_string($identifier, 'local_flwcupkp');
        }
        return get_string('uxnextdefaulttitle', 'local_flwcupkp');
    }

    /**
     * Progress URL for the learner in the current unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @return \moodle_url|null
     */
    private static function progress_url(int $courseid, string $unitcode, int $userid): ?\moodle_url {
        if ($courseid <= 0 || $unitcode === '') {
            return null;
        }
        if ($unitcode === 'U038') {
            $url = new \moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]);
        } else {
            $url = new \moodle_url('/local/flwcupkp/student.php', [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
            ]);
        }
        if ($userid > 0) {
            $url->param('userid', $userid);
        }
        return $url;
    }

    /**
     * Turn a machine token into a Moodle string identifier suffix.
     *
     * @param string $value
     * @return string
     */
    private static function identifier_suffix(string $value): string {
        return preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($value))) ?: 'unknown';
    }

    /**
     * Use a Moodle string when present, otherwise prettify the machine value.
     *
     * @param string $identifier
     * @param string $fallback
     * @return string
     */
    private static function string_or_human(string $identifier, string $fallback): string {
        if (get_string_manager()->string_exists($identifier, 'local_flwcupkp')) {
            return get_string($identifier, 'local_flwcupkp');
        }
        return self::human_label($fallback);
    }

    /**
     * Default empty state for a node type.
     *
     * @param string $type
     * @return string
     */
    private static function default_state(string $type): string {
        if ($type === 'competency') {
            return 'not_started';
        }
        if ($type === 'up') {
            return 'not_observed';
        }
        return 'not_introduced';
    }

    /**
     * Percent helper.
     *
     * @param float $value
     * @param float $total
     * @return float
     */
    private static function percent(float $value, float $total): float {
        return $total > 0 ? ($value / $total) * 100 : 0;
    }

    /**
     * Clamp a percentage.
     *
     * @param float $percent
     * @return float
     */
    private static function clamp_percent(float $percent): float {
        return max(0, min(100, $percent));
    }

    /**
     * Turn machine labels into human-readable labels.
     *
     * @param string $label
     * @return string
     */
    private static function human_label(string $label): string {
        return ucfirst(str_replace('_', ' ', $label));
    }
}

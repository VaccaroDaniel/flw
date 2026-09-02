<?php
// HTML renderer for H6 teacher history analytics.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders teacher-facing history analytics.
 */
class teacher_analytics_renderer {
    /**
     * Render H6 teacher analytics.
     *
     * @param array $dashboard Dashboard DTO.
     * @param \moodle_url $baseurl Base URL.
     * @return string
     */
    public static function render(array $dashboard, \moodle_url $baseurl): string {
        $html = \html_writer::start_div('local-flwhistory-dashboard local-flwhistory-teacher');
        $html .= self::topbar($dashboard);
        $html .= self::metric_strip($dashboard);
        $html .= self::attention_summary($dashboard);
        $html .= self::learner_table($dashboard, $baseurl);
        $html .= self::lower_grid($dashboard);
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render topbar.
     *
     * @param array $dashboard Dashboard.
     * @return string
     */
    private static function topbar(array $dashboard): string {
        $course = $dashboard['course'] ?? [];
        $html = \html_writer::start_tag('section', ['class' => 'flwhistory-topbar']);
        $html .= \html_writer::start_div('flwhistory-titleblock');
        $html .= \html_writer::tag('p', get_string('teacheranalyticssubtitle', 'local_flwhistory'), [
            'class' => 'flwhistory-eyebrow',
        ]);
        $html .= \html_writer::tag('h2', get_string('teacheranalyticstitle', 'local_flwhistory'));
        $html .= \html_writer::tag('p', s($course['fullname'] ?? ''), ['class' => 'flwhistory-muted']);
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('flwhistory-actionbox');
        $html .= \html_writer::tag('span', get_string('program3boundary', 'local_flwhistory'), [
            'class' => 'flwhistory-label',
        ]);
        $html .= \html_writer::tag('strong', get_string('historyonlyanalytics', 'local_flwhistory'));
        $html .= \html_writer::tag('small', s($dashboard['program3_boundary']['reason'] ?? ''));
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Render summary metrics.
     *
     * @param array $dashboard Dashboard.
     * @return string
     */
    private static function metric_strip(array $dashboard): string {
        $summary = $dashboard['class_summary'] ?? [];
        $completion = $summary['completion'] ?? [];
        $activity = $summary['activity'] ?? [];
        $grade = $summary['official_grade'] ?? [];
        $attempts = $summary['attempts'] ?? [];
        $attention = $summary['attention_counts'] ?? [];
        $attentiontotal = array_sum(array_map('intval', $attention));

        $metrics = [
            self::metric(get_string('learners', 'local_flwhistory'), (string)($summary['learnercount'] ?? 0),
                get_string('classroster', 'local_flwhistory'), 'available'),
            self::metric(get_string('completionprogress', 'local_flwhistory'),
                self::percent_value($completion['percent'] ?? null),
                get_string('completionratio', 'local_flwhistory', (object)[
                    'completed' => $completion['completed'] ?? 0,
                    'total' => $completion['possible'] ?? 0,
                ]),
                $completion['status'] ?? ''),
            self::metric(get_string('recentlyactive', 'local_flwhistory'),
                (string)($activity['activecount'] ?? 0),
                get_string('lastdays', 'local_flwhistory', $activity['windowdays'] ?? 14),
                $activity['status'] ?? ''),
            self::metric(get_string('officialgradeaverage', 'local_flwhistory'),
                self::number_value($grade['average'] ?? null),
                get_string('recordcount', 'local_flwhistory', $grade['count'] ?? 0),
                $grade['status'] ?? ''),
            self::metric(get_string('teacherattentionsignals', 'local_flwhistory'),
                (string)$attentiontotal,
                get_string('descriptiveonly', 'local_flwhistory'),
                $attentiontotal > 0 ? 'available' : 'insufficient_data'),
        ];
        if (($attempts['status'] ?? '') === 'available') {
            $metrics[] = self::metric(get_string('classattempts', 'local_flwhistory'),
                (string)$attempts['attemptcount'],
                get_string('attemptscoreaverage', 'local_flwhistory') . ': '
                    . self::percent_value(isset($attempts['averagescore']) ? ((float)$attempts['averagescore'] * 100) : null),
                'available');
        }

        return \html_writer::tag('section', implode('', $metrics), ['class' => 'flwhistory-metrics flwhistory-teacher-metrics']);
    }

    /**
     * Render attention summary.
     *
     * @param array $dashboard Dashboard.
     * @return string
     */
    private static function attention_summary(array $dashboard): string {
        $counts = $dashboard['class_summary']['attention_counts'] ?? [];
        $labels = self::signal_labels();
        $items = '';
        foreach ($labels as $key => $label) {
            $items .= \html_writer::tag('li',
                \html_writer::tag('strong', s((string)($counts[$key] ?? 0))) .
                \html_writer::span(s($label))
            );
        }
        return \html_writer::tag('section',
            \html_writer::tag('h3', get_string('attentionsignaloverview', 'local_flwhistory')) .
            \html_writer::tag('ul', $items, ['class' => 'flwhistory-attention-list']),
            ['class' => 'flwhistory-panel flwhistory-panel-wide']
        );
    }

    /**
     * Render learner analytics table.
     *
     * @param array $dashboard Dashboard.
     * @param \moodle_url $baseurl Base URL.
     * @return string
     */
    private static function learner_table(array $dashboard, \moodle_url $baseurl): string {
        $rows = $dashboard['learners'] ?? [];
        if (!$rows) {
            $body = self::empty_state(get_string('nolearnersfound', 'local_flwhistory'));
        } else {
            $rowhtml = '';
            foreach ($rows as $row) {
                $signals = self::signals_cell($row['attention_signals'] ?? []);
                $rowhtml .= \html_writer::tag('tr',
                    \html_writer::tag('td', self::learner_cell($row)) .
                    \html_writer::tag('td', s(self::completion_cell($row['completion'] ?? []))) .
                    \html_writer::tag('td', s(self::activity_cell($row['last_meaningful_activity'] ?? []))) .
                    \html_writer::tag('td', s(self::grade_cell($row['official_grade_summary'] ?? []))) .
                    \html_writer::tag('td', s(self::attempt_cell($row['attempt_trend'] ?? []))) .
                    \html_writer::tag('td', s(self::checkpoint_cell($row))) .
                    \html_writer::tag('td', $signals) .
                    \html_writer::tag('td', \html_writer::link($row['drilldownurl'], get_string('drilldown', 'local_flwhistory'),
                        ['class' => 'btn btn-secondary btn-sm']))
                );
            }
            $body = self::table([
                get_string('learner', 'local_flwhistory'),
                get_string('completionprogress', 'local_flwhistory'),
                get_string('lastmeaningfulactivity', 'local_flwhistory'),
                get_string('officialmoodlegrade', 'local_flwhistory'),
                get_string('attempttrend', 'local_flwhistory'),
                get_string('checkpointplacement', 'local_flwhistory'),
                get_string('teacherattentionsignals', 'local_flwhistory'),
                get_string('historydrilldown', 'local_flwhistory'),
            ], $rowhtml) . self::pager($dashboard['pagination'] ?? [], $baseurl);
        }

        return \html_writer::tag('section',
            \html_writer::tag('h3', get_string('individualhistorydrilldown', 'local_flwhistory')) . $body,
            ['class' => 'flwhistory-panel flwhistory-panel-wide']
        );
    }

    /**
     * Render lower summary panels.
     *
     * @param array $dashboard Dashboard.
     * @return string
     */
    private static function lower_grid(array $dashboard): string {
        $html = \html_writer::start_div('flwhistory-grid');
        $html .= self::panel(get_string('checkpointplacementhistory', 'local_flwhistory'),
            self::checkpoint_placement_summary($dashboard['checkpoint_placement_summary'] ?? []));
        $html .= self::panel(get_string('gradeaudit', 'local_flwhistory'), self::grade_audit($dashboard['grade_audit'] ?? []));
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render panel.
     *
     * @param string $title Title.
     * @param string $body Body.
     * @return string
     */
    private static function panel(string $title, string $body): string {
        return \html_writer::tag('section', \html_writer::tag('h3', s($title)) . $body, ['class' => 'flwhistory-panel']);
    }

    /**
     * Render checkpoint/placement summary.
     *
     * @param array $summary Summary.
     * @return string
     */
    private static function checkpoint_placement_summary(array $summary): string {
        if (($summary['status'] ?? '') !== 'available') {
            return self::empty_state(get_string('nocheckpointplacement', 'local_flwhistory'));
        }
        $facts = [
            get_string('visiblecheckpoints', 'local_flwhistory') => (string)($summary['visiblecheckpointcount'] ?? 0),
            get_string('visibleplacements', 'local_flwhistory') => (string)($summary['visibleplacementcount'] ?? 0),
        ];
        $levels = $summary['levels'] ?? [];
        foreach ($levels as $level => $count) {
            $facts[get_string('placementlevel', 'local_flwhistory') . ' ' . $level] = (string)$count;
        }
        return self::facts($facts);
    }

    /**
     * Render grade audit.
     *
     * @param array $audit Audit DTO.
     * @return string
     */
    private static function grade_audit(array $audit): string {
        if (($audit['status'] ?? '') === 'capability_required') {
            return self::empty_state(get_string('gradeauditcapabilityrequired', 'local_flwhistory'));
        }
        $records = $audit['records'] ?? [];
        if (!$records) {
            return self::empty_state(get_string('nogradeauditrecords', 'local_flwhistory'));
        }
        $rows = '';
        foreach ($records as $record) {
            $rows .= \html_writer::tag('tr',
                \html_writer::tag('td', s($record['learnername'] ?? (string)($record['userid'] ?? ''))) .
                \html_writer::tag('td', s($record['action'] ?? '')) .
                \html_writer::tag('td', s(self::number_value($record['previousgrade'] ?? null))) .
                \html_writer::tag('td', s(self::number_value($record['finalgrade'] ?? null))) .
                \html_writer::tag('td', s($record['gradername'] ?? (string)($record['graderid'] ?? ''))) .
                \html_writer::tag('td', s((string)($record['reason'] ?? ''))) .
                \html_writer::tag('td', s(self::date_value($record['gradetime'] ?? null)))
            );
        }
        return self::table([
            get_string('learner', 'local_flwhistory'),
            get_string('action', 'local_flwhistory'),
            get_string('previousgrade', 'local_flwhistory'),
            get_string('finalgrade', 'local_flwhistory'),
            get_string('grader', 'local_flwhistory'),
            get_string('reason', 'local_flwhistory'),
            get_string('recorded', 'local_flwhistory'),
        ], $rows);
    }

    /**
     * Metric card.
     *
     * @param string $label Label.
     * @param string $value Value.
     * @param string $meta Meta.
     * @param string $status Status.
     * @return string
     */
    private static function metric(string $label, string $value, string $meta, string $status): string {
        $class = 'flwhistory-metric';
        if ($status === 'insufficient_data') {
            $class .= ' is-muted';
        }
        return \html_writer::div(
            \html_writer::tag('span', s($label), ['class' => 'flwhistory-label']) .
            \html_writer::tag('strong', s($value)) .
            \html_writer::tag('small', s($meta)),
            $class
        );
    }

    /**
     * Learner cell.
     *
     * @param array $row Row.
     * @return string
     */
    private static function learner_cell(array $row): string {
        $learner = $row['learner'] ?? [];
        $html = \html_writer::tag('strong', s($learner['fullname'] ?? ''));
        $html .= \html_writer::tag('small', s($learner['username'] ?? ''), ['class' => 'flwhistory-muted']);
        return $html;
    }

    /**
     * Completion cell.
     *
     * @param array $completion Completion.
     * @return string
     */
    private static function completion_cell(array $completion): string {
        if (($completion['status'] ?? '') !== 'available') {
            return get_string('insufficientdata', 'local_flwhistory');
        }
        return self::percent_value($completion['percent'] ?? null) . ' '
            . get_string('completionratio', 'local_flwhistory', (object)[
                'completed' => $completion['completed'] ?? 0,
                'total' => $completion['total'] ?? 0,
            ]);
    }

    /**
     * Activity cell.
     *
     * @param array $activity Activity.
     * @return string
     */
    private static function activity_cell(array $activity): string {
        if (($activity['status'] ?? '') !== 'available') {
            return get_string('insufficientdata', 'local_flwhistory');
        }
        return self::date_value($activity['eventtime'] ?? null) . ' / ' . ($activity['eventtype'] ?? '');
    }

    /**
     * Grade cell.
     *
     * @param array $grade Grade.
     * @return string
     */
    private static function grade_cell(array $grade): string {
        if (($grade['status'] ?? '') !== 'available') {
            return get_string('insufficientdata', 'local_flwhistory');
        }
        return self::number_value($grade['officialaverage'] ?? null) . ' / '
            . get_string('recordcount', 'local_flwhistory', $grade['count'] ?? 0);
    }

    /**
     * Attempt cell.
     *
     * @param array $attempt Attempt.
     * @return string
     */
    private static function attempt_cell(array $attempt): string {
        if (($attempt['status'] ?? '') !== 'available') {
            return get_string('insufficientdata', 'local_flwhistory');
        }
        $trend = $attempt['trend'] ?? [];
        $latest = self::percent_value(isset($attempt['latestscore']) ? ((float)$attempt['latestscore'] * 100) : null);
        if (($trend['status'] ?? '') === 'available') {
            $latest .= ' / ' . self::signed_number($trend['delta'] ?? 0, '%');
        }
        return get_string('attempts', 'local_flwhistory', $attempt['attemptcount']) . ' / ' . $latest;
    }

    /**
     * Checkpoint/placement cell.
     *
     * @param array $row Row.
     * @return string
     */
    private static function checkpoint_cell(array $row): string {
        $checkpoint = $row['checkpoint_history'] ?? [];
        $placement = $row['placement_history'] ?? [];
        $parts = [get_string('checkpoints', 'local_flwhistory', $checkpoint['count'] ?? 0)];
        if (($placement['status'] ?? '') === 'available' && !empty($placement['currentlevel'])) {
            $parts[] = get_string('placementlevel', 'local_flwhistory') . ' ' . $placement['currentlevel'];
        }
        return implode(' / ', $parts);
    }

    /**
     * Signals cell.
     *
     * @param array $signals Signals.
     * @return string
     */
    private static function signals_cell(array $signals): string {
        if (!$signals) {
            return \html_writer::tag('span', get_string('nosignals', 'local_flwhistory'), [
                'class' => 'flwhistory-muted',
            ]);
        }
        $html = \html_writer::start_tag('ul', ['class' => 'flwhistory-signal-pills']);
        foreach ($signals as $signal) {
            $html .= \html_writer::tag('li', s($signal['label'] ?? ''), [
                'class' => 'signal-' . s($signal['severity'] ?? 'medium'),
            ]);
        }
        $html .= \html_writer::end_tag('ul');
        return $html;
    }

    /**
     * Render facts.
     *
     * @param array $facts Facts.
     * @return string
     */
    private static function facts(array $facts): string {
        $html = \html_writer::start_tag('dl', ['class' => 'flwhistory-facts']);
        foreach ($facts as $label => $value) {
            $html .= \html_writer::tag('dt', s($label));
            $html .= \html_writer::tag('dd', s($value));
        }
        $html .= \html_writer::end_tag('dl');
        return $html;
    }

    /**
     * Render table.
     *
     * @param array $headings Headings.
     * @param string $rows Rows.
     * @return string
     */
    private static function table(array $headings, string $rows): string {
        $header = '';
        foreach ($headings as $heading) {
            $header .= \html_writer::tag('th', s($heading), ['scope' => 'col']);
        }
        return \html_writer::tag('div',
            \html_writer::tag('table',
                \html_writer::tag('thead', \html_writer::tag('tr', $header))
                . \html_writer::tag('tbody', $rows),
                ['class' => 'generaltable flwhistory-table']
            ),
            ['class' => 'flwhistory-table-wrap']
        );
    }

    /**
     * Render pager.
     *
     * @param array $pagination Pagination.
     * @param \moodle_url $baseurl Base URL.
     * @return string
     */
    private static function pager(array $pagination, \moodle_url $baseurl): string {
        $limit = (int)($pagination['limit'] ?? 0);
        $offset = (int)($pagination['offset'] ?? 0);
        $total = (int)($pagination['total'] ?? 0);
        if ($limit <= 0 || $total <= $limit) {
            return '';
        }
        $html = \html_writer::start_div('flwhistory-pager');
        if ($offset > 0) {
            $prev = clone $baseurl;
            $prev->param('offset', max(0, $offset - $limit));
            $html .= \html_writer::link($prev, get_string('previous'), ['class' => 'btn btn-secondary btn-sm']);
        }
        if (!empty($pagination['hasmore'])) {
            $next = clone $baseurl;
            $next->param('offset', $offset + $limit);
            $html .= \html_writer::link($next, get_string('next'), ['class' => 'btn btn-secondary btn-sm']);
        }
        $html .= \html_writer::span(get_string('paginationtotal', 'local_flwhistory', $total), 'flwhistory-muted');
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Empty state.
     *
     * @param string $message Message.
     * @return string
     */
    private static function empty_state(string $message): string {
        return \html_writer::tag('p', s($message), ['class' => 'flwhistory-empty']);
    }

    /**
     * Signal labels.
     *
     * @return array
     */
    private static function signal_labels(): array {
        return [
            'inactive' => get_string('signal_inactive', 'local_flwhistory'),
            'repeated_unsuccessful_attempts' => get_string('signal_repeatedunsuccessful', 'local_flwhistory'),
            'grade_decline_with_enough_comparable_data' => get_string('signal_gradedecline', 'local_flwhistory'),
            'stalled_completion' => get_string('signal_stalledcompletion', 'local_flwhistory'),
            'missing_activity_evidence' => get_string('signal_missingactivity', 'local_flwhistory'),
        ];
    }

    /**
     * Percent formatter.
     *
     * @param mixed $value Value.
     * @return string
     */
    private static function percent_value($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float)$value, 1) . '%';
    }

    /**
     * Number formatter.
     *
     * @param mixed $value Value.
     * @return string
     */
    private static function number_value($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float)$value, 2);
    }

    /**
     * Signed number formatter.
     *
     * @param mixed $value Value.
     * @param string $suffix Suffix.
     * @return string
     */
    private static function signed_number($value, string $suffix): string {
        $number = (float)$value;
        $prefix = $number > 0 ? '+' : '';
        return $prefix . number_format($number, 1) . $suffix;
    }

    /**
     * Date formatter.
     *
     * @param mixed $time Timestamp.
     * @return string
     */
    private static function date_value($time): string {
        if (empty($time)) {
            return '-';
        }
        return userdate((int)$time, get_string('strftimedatetimeshort', 'core_langconfig'));
    }
}

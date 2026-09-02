<?php
// HTML renderer for the H5 learner history dashboard.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the H5 learner dashboard from the dashboard service DTO.
 */
class dashboard_renderer {
    /**
     * Render dashboard HTML.
     *
     * @param array $dashboard Dashboard DTO.
     * @param \moodle_url $baseurl Base URL with course and learner params.
     * @return string
     */
    public static function render(array $dashboard, \moodle_url $baseurl): string {
        $html = \html_writer::start_tag('div', ['class' => 'local-flwhistory-dashboard']);
        $html .= self::render_topbar($dashboard);
        $html .= self::render_metric_strip($dashboard);
        $html .= self::render_layout($dashboard, $baseurl);
        $html .= \html_writer::end_tag('div');
        return $html;
    }

    /**
     * Render top dashboard band.
     *
     * @param array $dashboard Dashboard DTO.
     * @return string
     */
    private static function render_topbar(array $dashboard): string {
        $present = $dashboard['present'] ?? [];
        $course = $present['course'] ?? [];
        $learner = $dashboard['learner'] ?? [];
        $action = $dashboard['standard_next_action'] ?? [];

        $html = \html_writer::start_tag('section', ['class' => 'flwhistory-topbar']);
        $html .= \html_writer::start_div('flwhistory-titleblock');
        $html .= \html_writer::tag('p', get_string('dashboardsubtitle', 'local_flwhistory'), [
            'class' => 'flwhistory-eyebrow',
        ]);
        $html .= \html_writer::tag('h2', get_string('dashboardtitle', 'local_flwhistory'));
        $html .= \html_writer::tag('p', s($learner['fullname'] ?? '') . ' / ' . s($course['fullname'] ?? ''), [
            'class' => 'flwhistory-muted',
        ]);
        $html .= \html_writer::end_div();

        $html .= \html_writer::start_div('flwhistory-actionbox');
        $html .= \html_writer::tag('span', get_string('standardnextaction', 'local_flwhistory'), [
            'class' => 'flwhistory-label',
        ]);
        $html .= \html_writer::tag('strong', s($action['label'] ?? get_string('noactionavailable', 'local_flwhistory')));
        if (!empty($action['activityname'])) {
            $html .= \html_writer::tag('span', s($action['activityname']), ['class' => 'flwhistory-muted']);
        }
        $html .= self::render_action_link($action);
        $html .= \html_writer::tag('small', get_string('standardnextactionnote', 'local_flwhistory'));
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Render link to a standard activity when available.
     *
     * @param array $action Action DTO.
     * @return string
     */
    private static function render_action_link(array $action): string {
        if (($action['status'] ?? '') !== 'available' || empty($action['cmid']) || empty($action['modname'])) {
            return '';
        }
        $url = new \moodle_url('/mod/' . clean_param((string)$action['modname'], PARAM_PLUGIN) . '/view.php', [
            'id' => (int)$action['cmid'],
        ]);
        return \html_writer::link($url, get_string('openactivity', 'local_flwhistory'), [
            'class' => 'btn btn-primary btn-sm',
        ]);
    }

    /**
     * Render metric strip.
     *
     * @param array $dashboard Dashboard DTO.
     * @return string
     */
    private static function render_metric_strip(array $dashboard): string {
        $present = $dashboard['present'] ?? [];
        $completion = $present['completion'] ?? [];
        $active = $present['active_days'] ?? [];
        $scores = $present['scores'] ?? [];
        $official = $scores['official_moodle_grade'] ?? [];
        $attempt = $scores['assessment_attempt_score'] ?? [];
        $study = $present['study_time'] ?? [];

        $metrics = [
            self::metric(get_string('completionprogress', 'local_flwhistory'),
                self::percent_value($completion['percent'] ?? null),
                get_string('completionratio', 'local_flwhistory', (object)[
                    'completed' => $completion['completed'] ?? 0,
                    'total' => $completion['total'] ?? 0,
                ]),
                $completion['status'] ?? ''),
            self::metric(get_string('activedays', 'local_flwhistory'),
                (string)($active['count'] ?? 0),
                get_string('lastdays', 'local_flwhistory', $active['windowdays'] ?? 90),
                $active['status'] ?? ''),
            self::metric(get_string('officialgradeaverage', 'local_flwhistory'),
                self::number_value($official['average'] ?? null),
                get_string('recordcount', 'local_flwhistory', $official['count'] ?? 0),
                $official['status'] ?? ''),
            self::metric(get_string('attemptscoreaverage', 'local_flwhistory'),
                self::percent_value(isset($attempt['average']) ? ((float)$attempt['average'] * 100.0) : null),
                get_string('recordcount', 'local_flwhistory', $attempt['count'] ?? 0),
                $attempt['status'] ?? ''),
            self::metric(get_string('studytime', 'local_flwhistory'),
                get_string('insufficientdata', 'local_flwhistory'),
                s($study['reason'] ?? ''),
                $study['status'] ?? 'insufficient_data'),
        ];

        return \html_writer::tag('section', implode('', $metrics), ['class' => 'flwhistory-metrics']);
    }

    /**
     * Render one metric.
     *
     * @param string $label Label.
     * @param string $value Value.
     * @param string $meta Meta text.
     * @param string $status Status.
     * @return string
     */
    private static function metric(string $label, string $value, string $meta, string $status): string {
        $class = 'flwhistory-metric';
        if ($status === 'insufficient_data') {
            $class .= ' is-muted';
        }
        $html = \html_writer::start_div($class);
        $html .= \html_writer::tag('span', s($label), ['class' => 'flwhistory-label']);
        $html .= \html_writer::tag('strong', s($value));
        $html .= \html_writer::tag('small', s($meta));
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render main layout.
     *
     * @param array $dashboard Dashboard DTO.
     * @param \moodle_url $baseurl Base URL.
     * @return string
     */
    private static function render_layout(array $dashboard, \moodle_url $baseurl): string {
        $html = \html_writer::start_div('flwhistory-grid');
        $html .= self::panel(get_string('learningjourney', 'local_flwhistory'), self::render_journey($dashboard['journey'] ?? []),
            'flwhistory-panel-wide');
        $html .= self::panel(get_string('gradedistinctions', 'local_flwhistory'),
            self::render_grade_distinctions($dashboard['grade_distinctions'] ?? []));
        $html .= self::panel(get_string('evidencetrend', 'local_flwhistory'),
            self::render_trend($dashboard['trend'] ?? []));
        $html .= self::panel(get_string('attempthistory', 'local_flwhistory'),
            self::render_attempts($dashboard['attempt_history'] ?? [], $baseurl), 'flwhistory-panel-wide');
        $html .= self::panel(get_string('gradehistory', 'local_flwhistory'),
            self::render_grades($dashboard['grade_history'] ?? [], $baseurl), 'flwhistory-panel-wide');
        $html .= self::panel(get_string('recentactivity', 'local_flwhistory'),
            self::render_learning_records($dashboard['recent_activity'] ?? [], $baseurl, 'activityoffset',
                get_string('norecentactivity', 'local_flwhistory')));
        $html .= self::panel(get_string('detailedlearninghistory', 'local_flwhistory'),
            self::render_learning_records($dashboard['learning_history'] ?? [], $baseurl, 'historyoffset',
                get_string('nolearninghistory', 'local_flwhistory')), 'flwhistory-panel-wide');
        $html .= self::panel(get_string('program3reserved', 'local_flwhistory'),
            self::render_program3_placeholders($dashboard['program3_placeholders'] ?? []));
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render a dashboard panel.
     *
     * @param string $title Title.
     * @param string $body Body HTML.
     * @param string $extra Extra class.
     * @return string
     */
    private static function panel(string $title, string $body, string $extra = ''): string {
        $class = trim('flwhistory-panel ' . $extra);
        $html = \html_writer::start_tag('section', ['class' => $class]);
        $html .= \html_writer::tag('h3', s($title));
        $html .= $body;
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Render learning journey.
     *
     * @param array $journey Journey DTO.
     * @return string
     */
    private static function render_journey(array $journey): string {
        $summary = $journey['summary'] ?? [];
        $total = (int)($summary['total'] ?? 0);
        if ($total === 0) {
            return self::empty_state(get_string('nojourneyitems', 'local_flwhistory'));
        }
        $completed = (int)($summary['completed'] ?? 0);
        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        $html = \html_writer::start_div('flwhistory-journey-summary');
        $html .= \html_writer::tag('strong', self::percent_value($percent));
        $html .= \html_writer::tag('span', get_string('completionratio', 'local_flwhistory', (object)[
            'completed' => $completed,
            'total' => $total,
        ]));
        $html .= \html_writer::tag('div', \html_writer::span('', 'flwhistory-progress-fill', [
            'style' => 'width:' . min(100, max(0, $percent)) . '%',
        ]), ['class' => 'flwhistory-progressbar', 'aria-hidden' => 'true']);
        $html .= \html_writer::end_div();

        $html .= \html_writer::start_tag('ol', ['class' => 'flwhistory-journey']);
        foreach ($journey['items'] ?? [] as $item) {
            $state = $item['state'] ?? 'notstarted';
            $label = self::state_label($state);
            $name = $item['name'] ?? get_string('unnamedactivity', 'local_flwhistory');
            $identity = $item['identity'] ?? [];
            $meta = [];
            if (!empty($identity['unitid'])) {
                $meta[] = $identity['unitid'];
            }
            if (!empty($item['checkpoint'])) {
                $meta[] = get_string('checkpoint', 'local_flwhistory');
            }
            $row = \html_writer::tag('span', s($label), ['class' => 'flwhistory-state state-' . s($state)]);
            $row .= \html_writer::tag('strong', s($name));
            $row .= \html_writer::tag('small', s(implode(' / ', $meta)));
            $html .= \html_writer::tag('li', $row);
        }
        $html .= \html_writer::end_tag('ol');
        return $html;
    }

    /**
     * Render grade distinction facts.
     *
     * @param array $distinctions Distinction DTO.
     * @return string
     */
    private static function render_grade_distinctions(array $distinctions): string {
        if (($distinctions['status'] ?? '') !== 'available') {
            return self::empty_state(get_string('nogradesummary', 'local_flwhistory'));
        }
        $facts = [
            get_string('latestattempt', 'local_flwhistory') => self::fact_value($distinctions['latest_attempt'] ?? [], true),
            get_string('bestattempt', 'local_flwhistory') => self::fact_value($distinctions['best_attempt'] ?? [], true),
            get_string('officialmoodlegrade', 'local_flwhistory') => self::fact_value($distinctions['official_moodle_grade'] ?? [], false),
            get_string('latestgradeversion', 'local_flwhistory') => self::fact_value($distinctions['latest_grade_version'] ?? [], false),
        ];
        $html = \html_writer::start_tag('dl', ['class' => 'flwhistory-facts']);
        foreach ($facts as $label => $value) {
            $html .= \html_writer::tag('dt', s($label));
            $html .= \html_writer::tag('dd', s($value));
        }
        $html .= \html_writer::end_tag('dl');
        return $html;
    }

    /**
     * Render basic trends.
     *
     * @param array $trend Trend DTO.
     * @return string
     */
    private static function render_trend(array $trend): string {
        $html = self::trend_block(get_string('attemptscoretrend', 'local_flwhistory'), $trend['attempt_score'] ?? [], true);
        $html .= self::trend_block(get_string('officialgradetrend', 'local_flwhistory'), $trend['official_grade'] ?? [], false);
        $skill = $trend['skill'] ?? [];
        $html .= \html_writer::tag('p', get_string('skilltrendnotavailable', 'local_flwhistory') . ' '
            . s($skill['reason'] ?? ''), ['class' => 'flwhistory-muted']);
        return $html;
    }

    /**
     * Render one trend block.
     *
     * @param string $title Title.
     * @param array $trend Trend.
     * @param bool $percent Whether values are percentages.
     * @return string
     */
    private static function trend_block(string $title, array $trend, bool $percent): string {
        $html = \html_writer::start_div('flwhistory-trend-block');
        $html .= \html_writer::tag('h4', s($title));
        if (($trend['status'] ?? '') !== 'available') {
            $html .= self::empty_state(get_string('notrendenoughdata', 'local_flwhistory'));
            $html .= \html_writer::end_div();
            return $html;
        }
        $latest = $percent ? self::percent_value($trend['latest'] ?? null) : self::number_value($trend['latest'] ?? null);
        $delta = self::signed_number($trend['delta'] ?? 0, $percent ? '%' : '');
        $html .= \html_writer::tag('p', $latest . ' (' . $delta . ')', [
            'class' => 'flwhistory-trend-delta direction-' . s($trend['direction'] ?? 'flat'),
        ]);
        $html .= self::sparkline($trend['points'] ?? []);
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render attempts table.
     *
     * @param array $attempts Attempt query DTO.
     * @param \moodle_url $baseurl Base URL.
     * @return string
     */
    private static function render_attempts(array $attempts, \moodle_url $baseurl): string {
        $records = $attempts['records'] ?? [];
        if (!$records) {
            return self::empty_state(get_string('noattemptrecords', 'local_flwhistory'));
        }
        $rows = '';
        foreach ($records as $record) {
            $rows .= \html_writer::tag('tr',
                \html_writer::tag('td', s($record['unitid'] ?? '')) .
                \html_writer::tag('td', s((string)($record['attemptno'] ?? ''))) .
                \html_writer::tag('td', s($record['attemptstate'] ?? '')) .
                \html_writer::tag('td', s(self::percent_value(isset($record['scaledscore']) ? $record['scaledscore'] * 100 : null))) .
                \html_writer::tag('td', s(self::date_value($record['timefinish'] ?? null)))
            );
        }
        return self::table([
            get_string('unit', 'local_flwhistory'),
            get_string('attempt', 'local_flwhistory'),
            get_string('state', 'local_flwhistory'),
            get_string('score', 'local_flwhistory'),
            get_string('finished', 'local_flwhistory'),
        ], $rows) . self::pager($attempts, $baseurl, 'attemptoffset');
    }

    /**
     * Render grades table.
     *
     * @param array $grades Grade history DTO.
     * @param \moodle_url $baseurl Base URL.
     * @return string
     */
    private static function render_grades(array $grades, \moodle_url $baseurl): string {
        $records = $grades['records'] ?? [];
        if (!$records) {
            return self::empty_state(get_string('nograderecords', 'local_flwhistory'));
        }
        $rows = '';
        foreach ($records as $record) {
            $rows .= \html_writer::tag('tr',
                \html_writer::tag('td', s((string)($record['gradeitemid'] ?? ''))) .
                \html_writer::tag('td', s($record['action'] ?? '')) .
                \html_writer::tag('td', s(self::number_value($record['previousgrade'] ?? null))) .
                \html_writer::tag('td', s(self::number_value($record['finalgrade'] ?? null))) .
                \html_writer::tag('td', s(self::date_value($record['gradetime'] ?? null)))
            );
        }
        return self::table([
            get_string('gradeitem', 'local_flwhistory'),
            get_string('action', 'local_flwhistory'),
            get_string('previousgrade', 'local_flwhistory'),
            get_string('finalgrade', 'local_flwhistory'),
            get_string('recorded', 'local_flwhistory'),
        ], $rows) . self::pager($grades, $baseurl, 'gradeoffset');
    }

    /**
     * Render learning records.
     *
     * @param array $query Query DTO.
     * @param \moodle_url $baseurl Base URL.
     * @param string $offsetparam Offset parameter.
     * @param string $empty Empty message.
     * @return string
     */
    private static function render_learning_records(
        array $query,
        \moodle_url $baseurl,
        string $offsetparam,
        string $empty
    ): string {
        $records = $query['records'] ?? [];
        if (!$records) {
            return self::empty_state($empty);
        }
        $rows = '';
        foreach ($records as $record) {
            $rows .= \html_writer::tag('tr',
                \html_writer::tag('td', s(self::date_value($record['eventtime'] ?? null))) .
                \html_writer::tag('td', s($record['sourcefamily'] ?? '')) .
                \html_writer::tag('td', s($record['eventtype'] ?? '')) .
                \html_writer::tag('td', s($record['unitid'] ?? '')) .
                \html_writer::tag('td', s($record['status'] ?? ''))
            );
        }
        return self::table([
            get_string('time', 'local_flwhistory'),
            get_string('source', 'local_flwhistory'),
            get_string('event', 'local_flwhistory'),
            get_string('unit', 'local_flwhistory'),
            get_string('state', 'local_flwhistory'),
        ], $rows) . self::pager($query, $baseurl, $offsetparam);
    }

    /**
     * Render Program 3 placeholder list.
     *
     * @param array $placeholders Placeholder DTOs.
     * @return string
     */
    private static function render_program3_placeholders(array $placeholders): string {
        if (!$placeholders) {
            return '';
        }
        $html = \html_writer::start_tag('ul', ['class' => 'flwhistory-placeholder-list']);
        foreach ($placeholders as $placeholder) {
            $html .= \html_writer::tag('li',
                \html_writer::tag('strong', s($placeholder['title'] ?? '')) .
                \html_writer::tag('span', get_string('notavailableyet', 'local_flwhistory')) .
                \html_writer::tag('small', s($placeholder['reason'] ?? ''))
            );
        }
        $html .= \html_writer::end_tag('ul');
        return $html;
    }

    /**
     * Render simple table.
     *
     * @param array $headings Headings.
     * @param string $rows Row HTML.
     * @return string
     */
    private static function table(array $headings, string $rows): string {
        $header = '';
        foreach ($headings as $heading) {
            $header .= \html_writer::tag('th', s($heading), ['scope' => 'col']);
        }
        return \html_writer::tag('div',
            \html_writer::tag('table',
                \html_writer::tag('thead', \html_writer::tag('tr', $header)) .
                \html_writer::tag('tbody', $rows),
                ['class' => 'generaltable flwhistory-table']
            ),
            ['class' => 'flwhistory-table-wrap']
        );
    }

    /**
     * Render a pager for an H4 query DTO.
     *
     * @param array $query Query DTO.
     * @param \moodle_url $baseurl Base URL.
     * @param string $offsetparam Offset parameter name.
     * @return string
     */
    private static function pager(array $query, \moodle_url $baseurl, string $offsetparam): string {
        $pagination = $query['pagination'] ?? [];
        $limit = (int)($pagination['limit'] ?? 0);
        $offset = (int)($pagination['offset'] ?? 0);
        $total = (int)($pagination['total'] ?? 0);
        if ($limit <= 0 || $total <= $limit) {
            return '';
        }
        $html = \html_writer::start_div('flwhistory-pager');
        if ($offset > 0) {
            $prev = clone $baseurl;
            $prev->param($offsetparam, max(0, $offset - $limit));
            $html .= \html_writer::link($prev, get_string('previous'), ['class' => 'btn btn-secondary btn-sm']);
        }
        if (!empty($pagination['hasmore'])) {
            $next = clone $baseurl;
            $next->param($offsetparam, $offset + $limit);
            $html .= \html_writer::link($next, get_string('next'), ['class' => 'btn btn-secondary btn-sm']);
        }
        $html .= \html_writer::span(get_string('paginationtotal', 'local_flwhistory', $total), 'flwhistory-muted');
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render empty state.
     *
     * @param string $message Message.
     * @return string
     */
    private static function empty_state(string $message): string {
        return \html_writer::tag('p', s($message), ['class' => 'flwhistory-empty']);
    }

    /**
     * Render small SVG sparkline.
     *
     * @param array $points Points.
     * @return string
     */
    private static function sparkline(array $points): string {
        if (count($points) < 2) {
            return '';
        }
        $values = array_map(fn(array $point): float => (float)$point['value'], $points);
        $min = min($values);
        $max = max($values);
        $range = max(1.0, $max - $min);
        $lastindex = max(1, count($points) - 1);
        $coords = [];
        foreach ($points as $index => $point) {
            $x = 8 + (($index / $lastindex) * 184);
            $y = 72 - ((((float)$point['value'] - $min) / $range) * 56);
            $coords[] = round($x, 2) . ',' . round($y, 2);
        }
        $polyline = \html_writer::empty_tag('polyline', [
            'points' => implode(' ', $coords),
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-width' => '3',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
        ]);
        return \html_writer::tag('svg', $polyline, [
            'class' => 'flwhistory-sparkline',
            'viewBox' => '0 0 200 80',
            'role' => 'img',
            'aria-label' => get_string('trendchart', 'local_flwhistory'),
        ]);
    }

    /**
     * Format state label.
     *
     * @param string $state State.
     * @return string
     */
    private static function state_label(string $state): string {
        $map = [
            'completed' => get_string('completed', 'local_flwhistory'),
            'current' => get_string('current', 'local_flwhistory'),
            'inprogress' => get_string('inprogress', 'local_flwhistory'),
            'future' => get_string('future', 'local_flwhistory'),
            'notstarted' => get_string('notstarted', 'local_flwhistory'),
        ];
        return $map[$state] ?? $state;
    }

    /**
     * Format fact value.
     *
     * @param array $fact Fact DTO.
     * @param bool $percent Whether value is percent.
     * @return string
     */
    private static function fact_value(array $fact, bool $percent): string {
        if (($fact['status'] ?? '') !== 'available') {
            return get_string('insufficientdata', 'local_flwhistory');
        }
        return $percent ? self::percent_value($fact['value'] ?? null) : self::number_value($fact['value'] ?? null);
    }

    /**
     * Format percent.
     *
     * @param mixed $value Numeric value.
     * @return string
     */
    private static function percent_value($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float)$value, 1) . '%';
    }

    /**
     * Format number.
     *
     * @param mixed $value Numeric value.
     * @return string
     */
    private static function number_value($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float)$value, 2);
    }

    /**
     * Format signed number.
     *
     * @param mixed $value Numeric value.
     * @param string $suffix Suffix.
     * @return string
     */
    private static function signed_number($value, string $suffix): string {
        $number = (float)$value;
        $prefix = $number > 0 ? '+' : '';
        return $prefix . number_format($number, 1) . $suffix;
    }

    /**
     * Format timestamp.
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

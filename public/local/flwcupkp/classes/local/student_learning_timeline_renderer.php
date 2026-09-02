<?php
// Program 3 Gate UX1 StudentLearningTimelineView renderer.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders Past, Present, and Future without receiving raw curriculum graphs.
 */
final class student_learning_timeline_renderer {
    /**
     * Render the integrated learner timeline.
     *
     * @param array $view StudentLearningTimelineView DTO.
     * @param \moodle_url $baseurl Pagination URL for delegated History panels.
     * @return string
     */
    public static function render(array $view, \moodle_url $baseurl): string {
        $html = \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux1']);
        $html .= self::topbar($view);
        $html .= self::stage_navigation();
        $html .= self::past($view['past'] ?? [], $baseurl);
        $html .= self::present($view['present'] ?? []);
        $html .= self::future($view['future'] ?? []);
        $html .= self::source_footer($view);
        $html .= \html_writer::end_tag('div');
        return $html;
    }

    /**
     * Render identity and current milestone.
     *
     * @param array $view
     * @return string
     */
    private static function topbar(array $view): string {
        $learner = $view['learner'] ?? [];
        $course = $view['course'] ?? [];
        $preferred = $view['present']['preferred_metric'] ?? [];
        $milestone = (string)($preferred['milestone'] ?? $view['present']['goal_achievement']['milestone'] ?? '');
        $value = self::percentage_or_milestone($preferred, $milestone);

        $html = \html_writer::start_tag('header', ['class' => 'local-flwcupkp-ux1-topbar']);
        $html .= \html_writer::start_div('local-flwcupkp-ux1-title');
        $html .= \html_writer::tag('span', get_string('learningtimelineeyebrow', 'local_flwcupkp'));
        $html .= \html_writer::tag('h2', get_string('learningtimelinetitle', 'local_flwcupkp'));
        $html .= \html_writer::tag('p', s((string)($learner['fullname'] ?? '')) . ' / ' .
            s((string)($course['fullname'] ?? '')));
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('local-flwcupkp-ux1-milestone');
        $html .= \html_writer::tag('span', get_string('currentmilestone', 'local_flwcupkp'));
        $html .= \html_writer::tag('strong', s($value));
        if ($milestone !== '') {
            $html .= \html_writer::tag('small', s(self::humanize($milestone)));
        }
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('header');
        return $html;
    }

    /**
     * Render stable Past, Present, Future jump navigation.
     *
     * @return string
     */
    private static function stage_navigation(): string {
        $stages = [
            ['key' => 'past', 'number' => '1', 'label' => get_string('timelinepast', 'local_flwcupkp'),
                'detail' => get_string('timelinepastdetail', 'local_flwcupkp')],
            ['key' => 'present', 'number' => '2', 'label' => get_string('timelinepresent', 'local_flwcupkp'),
                'detail' => get_string('timelinepresentdetail', 'local_flwcupkp')],
            ['key' => 'future', 'number' => '3', 'label' => get_string('timelinefuture', 'local_flwcupkp'),
                'detail' => get_string('timelinefuturedetail', 'local_flwcupkp')],
        ];
        $html = \html_writer::start_tag('nav', [
            'class' => 'local-flwcupkp-ux1-stage-nav',
            'aria-label' => get_string('learningtimelinetitle', 'local_flwcupkp'),
        ]);
        foreach ($stages as $stage) {
            $content = \html_writer::tag('span', $stage['number']) .
                \html_writer::tag('strong', $stage['label']) .
                \html_writer::tag('small', $stage['detail']);
            $html .= \html_writer::link('#local-flwcupkp-ux1-' . $stage['key'], $content);
        }
        $html .= \html_writer::end_tag('nav');
        return $html;
    }

    /**
     * Delegate the approved Program 2 dashboard panels.
     *
     * @param array $past
     * @param \moodle_url $baseurl
     * @return string
     */
    private static function past(array $past, \moodle_url $baseurl): string {
        $html = self::section_start('past', get_string('timelinepast', 'local_flwcupkp'),
            get_string('timelinepastintro', 'local_flwcupkp'));
        $renderer = '\\local_flwhistory\\local\\dashboard_renderer';
        $dashboard = is_array($past['dashboard'] ?? null) ? $past['dashboard'] : [];
        if (!$dashboard || !class_exists($renderer) || !method_exists($renderer, 'render')) {
            $html .= self::empty_state(get_string('timelinehistoryunavailable', 'local_flwcupkp'));
        } else {
            $html .= \html_writer::tag('div', $renderer::render($dashboard, $baseurl), [
                'class' => 'local-flwcupkp-ux1-history-delegated',
                'data-owner' => 'local_flwhistory',
            ]);
        }
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Render current Program 3 intelligence.
     *
     * @param array $present
     * @return string
     */
    private static function present(array $present): string {
        $html = self::section_start('present', get_string('timelinepresent', 'local_flwcupkp'),
            get_string('timelinepresentintro', 'local_flwcupkp'));
        $html .= self::current_location($present['current_location'] ?? []);
        $html .= self::present_metrics($present);
        $html .= \html_writer::start_div('local-flwcupkp-ux1-present-grid');
        $html .= self::goal_panel($present['goal'] ?? [], $present['goal_achievement'] ?? []);
        $html .= self::skill_states($present['skill_states'] ?? []);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Render History-owned current location as a compact fact.
     *
     * @param array $location
     * @return string
     */
    private static function current_location(array $location): string {
        $parts = array_values(array_filter([
            (string)($location['unitid'] ?? ''),
            (string)($location['lessonid'] ?? ''),
            (string)($location['activityid'] ?? ''),
        ]));
        $value = $parts ? implode(' / ', $parts) : get_string('insufficientdata', 'local_flwcupkp');
        $meta = !empty($location['eventtime']) ? userdate((int)$location['eventtime'], get_string('strftimedatetimeshort')) :
            get_string('timelinecurrentlocationempty', 'local_flwcupkp');
        $content = \html_writer::tag('span', get_string('timelinecurrentlocation', 'local_flwcupkp')) .
            \html_writer::tag('strong', s($value)) .
            \html_writer::tag('small', s($meta));
        return \html_writer::tag('div', $content, ['class' => 'local-flwcupkp-ux1-location']);
    }

    /**
     * Render semantically distinct A5C metrics.
     *
     * @param array $present
     * @return string
     */
    private static function present_metrics(array $present): string {
        $metrics = $present['metrics'] ?? [];
        $codes = ['mastery_progress', 'goal_readiness', 'path_progress'];
        $html = \html_writer::start_div('local-flwcupkp-ux1-metrics');
        foreach ($codes as $code) {
            $metric = is_array($metrics[$code] ?? null) ? $metrics[$code] : [];
            $label = get_string('metric_' . $code, 'local_flwcupkp');
            $value = isset($metric['percentage']) && $metric['percentage'] !== null ?
                format_float((float)$metric['percentage'], 1) . '%' : get_string('qualitativeonly', 'local_flwcupkp');
            $fraction = get_string('metricfraction', 'local_flwcupkp', (object)[
                'numerator' => format_float((float)($metric['numerator'] ?? 0), 2),
                'denominator' => format_float((float)($metric['denominator'] ?? 0), 2),
                'gaps' => count($metric['mandatory_gaps'] ?? []),
            ]);
            $content = \html_writer::tag('span', $label) .
                \html_writer::tag('strong', s($value)) .
                \html_writer::tag('small', s($fraction));
            $html .= \html_writer::tag('div', $content, ['class' => 'local-flwcupkp-ux1-metric']);
        }
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render current goal and achievement semantics.
     *
     * @param array $goal
     * @param array $achievement
     * @return string
     */
    private static function goal_panel(array $goal, array $achievement): string {
        $html = \html_writer::start_tag('article', ['class' => 'local-flwcupkp-ux1-panel']);
        $html .= \html_writer::tag('h4', get_string('timelinecurrentgoal', 'local_flwcupkp'));
        if (($goal['status'] ?? '') === 'insufficient_data') {
            $html .= self::empty_state(get_string('timelinegoalnotset', 'local_flwcupkp'));
            $html .= \html_writer::end_tag('article');
            return $html;
        }
        $title = (string)($goal['title'] ?? '');
        $html .= \html_writer::tag('strong', s($title !== '' ? $title : get_string('learninggoal', 'local_flwcupkp')));
        $meta = array_values(array_filter([
            (string)($goal['cefr'] ?? ''),
            (string)($goal['flwstage'] ?? ''),
            !empty($goal['currentversion']) ? 'v' . (int)$goal['currentversion'] : '',
        ]));
        if ($meta) {
            $html .= \html_writer::tag('p', s(implode(' / ', $meta)));
        }
        $milestone = (string)($achievement['milestone'] ?? '');
        if ($milestone !== '') {
            $html .= \html_writer::tag('span', s(self::humanize($milestone)), [
                'class' => 'local-flwcupkp-ux1-status ' . (!empty($achievement['achieved']) ? 'is-ready' : 'is-active'),
            ]);
        }
        $html .= \html_writer::end_tag('article');
        return $html;
    }

    /**
     * Render compact skill/mastery state table.
     *
     * @param array $rows
     * @return string
     */
    private static function skill_states(array $rows): string {
        $html = \html_writer::start_tag('article', ['class' => 'local-flwcupkp-ux1-panel local-flwcupkp-ux1-skill-panel']);
        $html .= \html_writer::tag('h4', get_string('timelineskillstate', 'local_flwcupkp'));
        if (!$rows) {
            $html .= self::empty_state(get_string('timelineskillstateempty', 'local_flwcupkp'));
            $html .= \html_writer::end_tag('article');
            return $html;
        }
        $body = '';
        foreach (array_slice($rows, 0, 12) as $row) {
            $target = $row['target'] ?? [];
            $label = self::target_label($target);
            $state = self::humanize((string)($row['gap_status'] ?? 'missing'));
            $body .= \html_writer::tag('tr',
                \html_writer::tag('td', s($label)) .
                \html_writer::tag('td', s($state)) .
                \html_writer::tag('td', format_float((float)($row['mastery_score'] ?? 0) * 100, 1) . '%') .
                \html_writer::tag('td', format_float((float)($row['confidence'] ?? 0) * 100, 1) . '%') .
                \html_writer::tag('td', s(self::humanize((string)($row['retention_state'] ?? 'missing'))))
            );
        }
        $head = \html_writer::tag('tr',
            \html_writer::tag('th', get_string('target', 'local_flwcupkp')) .
            \html_writer::tag('th', get_string('status')) .
            \html_writer::tag('th', get_string('mastery', 'local_flwcupkp')) .
            \html_writer::tag('th', get_string('confidence', 'local_flwcupkp')) .
            \html_writer::tag('th', get_string('retention', 'local_flwcupkp'))
        );
        $table = \html_writer::tag('table',
            \html_writer::tag('thead', $head) . \html_writer::tag('tbody', $body),
            ['class' => 'generaltable']
        );
        $html .= \html_writer::tag('div', $table, ['class' => 'local-flwcupkp-ux1-table-wrap']);
        $html .= \html_writer::end_tag('article');
        return $html;
    }

    /**
     * Render future adaptive route, roadmap, and history.
     *
     * @param array $future
     * @return string
     */
    private static function future(array $future): string {
        $html = self::section_start('future', get_string('timelinefuture', 'local_flwcupkp'),
            get_string('timelinefutureintro', 'local_flwcupkp'));
        $html .= self::adaptive_next($future['adaptive_next'] ?? []);
        $html .= self::roadmap($future['projected_roadmap'] ?? []);
        $html .= \html_writer::start_div('local-flwcupkp-ux1-future-grid');
        $html .= self::path_change($future['why_path_changed'] ?? []);
        $html .= self::recommendation_history($future['recommendation_history'] ?? []);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /**
     * Render adaptive next action.
     *
     * @param array $next
     * @return string
     */
    private static function adaptive_next(array $next): string {
        $html = \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux1-next']);
        $html .= \html_writer::start_div('local-flwcupkp-ux1-next-copy');
        $html .= \html_writer::tag('span', get_string('timelineadaptivenext', 'local_flwcupkp'));
        if (($next['status'] ?? '') === 'insufficient_data') {
            $html .= \html_writer::tag('strong', get_string('timelineadaptivenextempty', 'local_flwcupkp'));
        } else {
            $activity = $next['activity'] ?? [];
            $target = $next['target'] ?? [];
            $title = !empty($activity['title']) ? (string)$activity['title'] : self::target_label($target);
            $html .= \html_writer::tag('strong', s($title));
            if (!empty($next['reason'])) {
                $html .= \html_writer::tag('p', s((string)$next['reason']));
            }
            $codes = array_values($next['reason_codes'] ?? []);
            if ($codes) {
                $html .= \html_writer::tag('small', s(implode(' / ', array_map([self::class, 'humanize'], $codes))));
            }
        }
        $html .= \html_writer::end_div();
        $activity = $next['activity'] ?? [];
        if (!empty($activity['available']) && !empty($activity['url'])) {
            $html .= \html_writer::link((string)$activity['url'], get_string('openactivity', 'local_flwcupkp'), [
                'class' => 'btn btn-primary',
            ]);
        }
        $html .= \html_writer::end_tag('div');
        return $html;
    }

    /**
     * Render projected target-level roadmap.
     *
     * @param array $steps
     * @return string
     */
    private static function roadmap(array $steps): string {
        $html = \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux1-roadmap']);
        $html .= \html_writer::tag('h4', get_string('timelineprojectedroadmap', 'local_flwcupkp'));
        if (!$steps) {
            $html .= self::empty_state(get_string('timelineroadmapempty', 'local_flwcupkp'));
            $html .= \html_writer::end_tag('div');
            return $html;
        }
        $html .= \html_writer::start_tag('ol');
        foreach ($steps as $step) {
            $target = self::target_label($step['target'] ?? []);
            $destination = $step['destination'] ?? [];
            if ($target === '' && !empty($destination['available'])) {
                $target = (string)($destination['title'] ?? get_string('learninggoal', 'local_flwcupkp'));
            }
            if ($target === '') {
                $target = self::humanize((string)($step['action'] ?? $step['stage'] ?? 'next'));
            }
            $content = \html_writer::tag('span', (string)($step['step'] ?? '')) .
                \html_writer::tag('strong', s($target)) .
                \html_writer::tag('small', s(self::humanize((string)($step['stage'] ?? ''))));
            $class = !empty($step['selected']) ? 'is-selected' : '';
            $html .= \html_writer::tag('li', $content, ['class' => $class]);
        }
        $html .= \html_writer::end_tag('ol');
        $html .= \html_writer::end_tag('div');
        return $html;
    }

    /**
     * Render path-change explanation.
     *
     * @param array $change
     * @return string
     */
    private static function path_change(array $change): string {
        $html = \html_writer::start_tag('article', ['class' => 'local-flwcupkp-ux1-panel']);
        $html .= \html_writer::tag('h4', get_string('timelinewhypathchanged', 'local_flwcupkp'));
        $html .= \html_writer::tag('p', s((string)($change['reason'] ??
            get_string('timelinepathchangeempty', 'local_flwcupkp'))));
        $dimensions = array_values($change['dimensions'] ?? []);
        if ($dimensions) {
            $html .= \html_writer::start_tag('ul', ['class' => 'local-flwcupkp-ux1-tags']);
            foreach ($dimensions as $dimension) {
                $html .= \html_writer::tag('li', s(self::humanize((string)$dimension)));
            }
            $html .= \html_writer::end_tag('ul');
        }
        $html .= \html_writer::end_tag('article');
        return $html;
    }

    /**
     * Render bounded persisted recommendation history.
     *
     * @param array $records
     * @return string
     */
    private static function recommendation_history(array $records): string {
        $html = \html_writer::start_tag('article', ['class' => 'local-flwcupkp-ux1-panel']);
        $html .= \html_writer::tag('h4', get_string('timelinerecommendationhistory', 'local_flwcupkp'));
        if (!$records) {
            $html .= self::empty_state(get_string('timelinerecommendationempty', 'local_flwcupkp'));
            $html .= \html_writer::end_tag('article');
            return $html;
        }
        $html .= \html_writer::start_tag('ol', ['class' => 'local-flwcupkp-ux1-recommendations']);
        foreach (array_slice($records, 0, 10) as $record) {
            $target = self::target_label($record['target'] ?? []);
            $label = self::humanize((string)($record['action'] ?? 'recommendation'));
            if ($target !== '') {
                $label .= ': ' . $target;
            }
            $meta = !empty($record['time']) ? userdate((int)$record['time'], get_string('strftimedatetimeshort')) : '';
            $row = \html_writer::tag('strong', s($label)) .
                \html_writer::tag('span', s((string)($record['reason'] ?? ''))) .
                \html_writer::tag('small', s($meta));
            $html .= \html_writer::tag('li', $row);
        }
        $html .= \html_writer::end_tag('ol');
        $html .= \html_writer::end_tag('article');
        return $html;
    }

    /**
     * Render source/version footer.
     *
     * @param array $view
     * @return string
     */
    private static function source_footer(array $view): string {
        $contracts = $view['source_contracts'] ?? [];
        $parts = array_values(array_filter([
            (string)($contracts['history_dashboard'] ?? ''),
            (string)($contracts['progress_readiness'] ?? ''),
            (string)($contracts['adaptive_path'] ?? ''),
            (string)($view['view_policy_version'] ?? ''),
        ]));
        return \html_writer::tag('p', s(implode(' / ', $parts)), ['class' => 'local-flwcupkp-ux1-source']);
    }

    /**
     * Open one full-width stage section.
     *
     * @param string $key
     * @param string $title
     * @param string $intro
     * @return string
     */
    private static function section_start(string $key, string $title, string $intro): string {
        $html = \html_writer::start_tag('section', [
            'id' => 'local-flwcupkp-ux1-' . $key,
            'class' => 'local-flwcupkp-ux1-stage local-flwcupkp-ux1-' . $key,
        ]);
        $html .= \html_writer::start_div('local-flwcupkp-ux1-stage-head');
        $html .= \html_writer::tag('h3', $title);
        $html .= \html_writer::tag('p', $intro);
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Percentage when defensible, otherwise milestone.
     *
     * @param array $metric
     * @param string $milestone
     * @return string
     */
    private static function percentage_or_milestone(array $metric, string $milestone): string {
        if (array_key_exists('percentage', $metric) && $metric['percentage'] !== null) {
            return format_float((float)$metric['percentage'], 1) . '%';
        }
        if ($milestone !== '') {
            return self::humanize($milestone);
        }
        return get_string('qualitativeonly', 'local_flwcupkp');
    }

    /**
     * Target presentation label.
     *
     * @param array $target
     * @return string
     */
    private static function target_label(array $target): string {
        if (empty($target['available'])) {
            return '';
        }
        $parts = array_values(array_filter([
            (string)($target['externalid'] ?? ''),
            (string)($target['title'] ?? ''),
        ]));
        if ($parts) {
            return implode(' - ', $parts);
        }
        return (string)($target['type'] ?? '') . ':' . (int)($target['id'] ?? 0);
    }

    /**
     * Turn stable codes into readable labels.
     *
     * @param string $value
     * @return string
     */
    private static function humanize(string $value): string {
        $value = trim(str_replace(['_', '-'], ' ', strtolower($value)));
        return $value === '' ? '' : ucfirst($value);
    }

    /**
     * Render an honest empty state.
     *
     * @param string $message
     * @return string
     */
    private static function empty_state(string $message): string {
        return \html_writer::tag('p', s($message), ['class' => 'local-flwcupkp-ux1-empty']);
    }
}

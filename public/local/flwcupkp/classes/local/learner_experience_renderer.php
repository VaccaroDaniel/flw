<?php
// Program 3 Gate UX2 simplified learner experience renderer.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Mobile-first renderer for SimplifiedLearnerExperienceView.
 */
final class learner_experience_renderer {
    /**
     * Render the learner flow with one primary action and progressive disclosure.
     *
     * @param array $view
     * @return string
     */
    public static function render(array $view): string {
        $level1 = $view['level_1'] ?? [];
        $html = \html_writer::start_tag('main', ['class' => 'local-flwcupkp-ux2']);
        $html .= self::header($view);
        $html .= \html_writer::start_tag('div', [
            'class' => 'local-flwcupkp-ux2-flow',
            'aria-label' => get_string('learnerexperienceflow', 'local_flwcupkp'),
        ]);
        $html .= self::history_summary($level1['history'] ?? []);
        $html .= self::current($level1['current'] ?? []);
        $html .= self::next($level1['next'] ?? []);
        $html .= self::coming_up($level1['coming_up'] ?? []);
        $html .= self::milestone($level1['milestone'] ?? []);
        $html .= self::goal($level1['goal'] ?? []);
        $html .= \html_writer::end_tag('div');
        $html .= self::disclosures($view);
        $html .= \html_writer::end_tag('main');
        return $html;
    }

    /** Main learner heading. */
    private static function header(array $view): string {
        $learner = (string)($view['learner']['fullname'] ?? '');
        $course = (string)($view['course']['fullname'] ?? '');
        $html = \html_writer::start_tag('header', ['class' => 'local-flwcupkp-ux2-header']);
        $html .= \html_writer::tag('span', get_string('learnerexperienceeyebrow', 'local_flwcupkp'));
        $html .= \html_writer::tag('h2', get_string('learnerexperiencetitle', 'local_flwcupkp'));
        $identity = implode(' / ', array_values(array_filter([$learner, $course])));
        if ($identity !== '') {
            $html .= \html_writer::tag('p', s($identity));
        }
        $html .= \html_writer::end_tag('header');
        return $html;
    }

    /** Compressed History summary. */
    private static function history_summary(array $history): string {
        $total = (int)($history['learning_events'] ?? 0);
        $attempts = (int)($history['attempts'] ?? 0);
        $completed = (int)($history['completed_steps'] ?? 0);
        $lastactive = !empty($history['last_active']) ?
            userdate((int)$history['last_active'], get_string('strftimedatetimeshort')) :
            get_string('learnerhistorystarting', 'local_flwcupkp');
        $html = self::section_start('history', get_string('learnerhistory', 'local_flwcupkp'));
        $html .= \html_writer::start_div('local-flwcupkp-ux2-history-line');
        $html .= self::fact(get_string('learningevents', 'local_flwcupkp'), (string)$total);
        $html .= self::fact(get_string('attempts', 'local_flwcupkp'), (string)$attempts);
        $html .= self::fact(get_string('completedsteps', 'local_flwcupkp'), (string)$completed);
        $html .= self::fact(get_string('lastactive', 'local_flwcupkp'), $lastactive);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** Expanded Current area. */
    private static function current(array $current): string {
        $progress = is_array($current['progress'] ?? null) ? $current['progress'] : [];
        $html = self::section_start('current', get_string('whereiamnow', 'local_flwcupkp'));
        $html .= \html_writer::start_div('local-flwcupkp-ux2-current-head');
        $html .= \html_writer::tag('strong', s((string)($current['title'] ?? '')));
        $html .= \html_writer::tag('p', s((string)($current['position'] ?? '')));
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('local-flwcupkp-ux2-progress');
        $html .= \html_writer::tag('span', s((string)($progress['label'] ??
            get_string('currentprogress', 'local_flwcupkp'))));
        if (($progress['percentage'] ?? null) !== null) {
            $value = max(0.0, min(100.0, (float)$progress['percentage']));
            $html .= \html_writer::tag('strong', format_float($value, 1) . '%');
            $html .= \html_writer::tag('progress', '', [
                'max' => '100',
                'value' => (string)$value,
                'aria-label' => (string)($progress['label'] ?? get_string('currentprogress', 'local_flwcupkp')),
            ]);
        } else {
            $html .= \html_writer::tag('strong', s((string)($progress['milestone'] ??
                get_string('progressforming', 'local_flwcupkp'))));
        }
        $html .= \html_writer::end_div();
        $highlights = array_values($current['ability_highlights'] ?? []);
        if ($highlights) {
            $html .= \html_writer::start_tag('ul', ['class' => 'local-flwcupkp-ux2-highlights']);
            foreach ($highlights as $row) {
                $html .= \html_writer::tag('li',
                    \html_writer::tag('strong', s((string)($row['title'] ?? ''))) .
                    \html_writer::tag('span', s((string)($row['state'] ?? '')))
                );
            }
            $html .= \html_writer::end_tag('ul');
        }
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** One guarded primary action. */
    private static function next(array $next): string {
        $html = self::section_start('next', get_string('whatshouldidonext', 'local_flwcupkp'));
        if (!empty($next['available']) && !empty($next['url'])) {
            $html .= \html_writer::start_div('local-flwcupkp-ux2-next-ready');
            $html .= \html_writer::start_div('local-flwcupkp-ux2-next-copy');
            $html .= \html_writer::tag('span', s((string)($next['activity_type'] ?? '')));
            $html .= \html_writer::tag('strong', s((string)($next['activity_title'] ?? '')));
            if (!empty($next['action'])) {
                $html .= \html_writer::tag('small', s((string)$next['action']));
            }
            $html .= \html_writer::end_div();
            $html .= \html_writer::link((string)$next['url'], get_string('continuelearning', 'local_flwcupkp'), [
                'class' => 'btn btn-primary local-flwcupkp-ux2-primary',
            ]);
            $html .= \html_writer::end_div();
        } else {
            $html .= \html_writer::tag('p', get_string('nextactivitypreparing', 'local_flwcupkp'), [
                'class' => 'local-flwcupkp-ux2-empty',
            ]);
        }
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** Summarized Future. */
    private static function coming_up(array $rows): string {
        $html = self::section_start('coming', get_string('comingup', 'local_flwcupkp'));
        if (!$rows) {
            $html .= \html_writer::tag('p', get_string('comingupforming', 'local_flwcupkp'), [
                'class' => 'local-flwcupkp-ux2-empty',
            ]);
        } else {
            $html .= \html_writer::start_tag('ol', ['class' => 'local-flwcupkp-ux2-coming']);
            foreach (array_slice($rows, 0, learner_experience_service::DEFAULT_COMING_UP_LIMIT) as $row) {
                $html .= \html_writer::tag('li',
                    \html_writer::tag('strong', s((string)($row['title'] ?? ''))) .
                    \html_writer::tag('span', s((string)($row['kind'] ?? '')))
                );
            }
            $html .= \html_writer::end_tag('ol');
        }
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** Qualitative milestone without duplicate progress bars. */
    private static function milestone(array $milestone): string {
        $html = self::section_start('milestone', get_string('mymilestone', 'local_flwcupkp'));
        $class = !empty($milestone['achieved']) ? ' is-achieved' : '';
        $html .= \html_writer::tag('strong', s((string)($milestone['label'] ?? '')), [
            'class' => 'local-flwcupkp-ux2-milestone-value' . $class,
        ]);
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** Friendly goal. */
    private static function goal(array $goal): string {
        $html = self::section_start('goal', get_string('mygoal', 'local_flwcupkp'));
        $html .= \html_writer::tag('strong', s((string)($goal['title'] ?? '')));
        $meta = array_values(array_filter([
            (string)($goal['cefr'] ?? ''),
            (string)($goal['stage'] ?? ''),
            (string)($goal['purpose'] ?? ''),
        ]));
        if ($meta) {
            $html .= \html_writer::tag('p', s(implode(' / ', $meta)));
        }
        if (!empty($goal['target_date'])) {
            $html .= \html_writer::tag('small', get_string('targetdatevalue', 'local_flwcupkp',
                userdate((int)$goal['target_date'], get_string('strftimedatefullshort'))));
        }
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** Level two and three native disclosure controls. */
    private static function disclosures(array $view): string {
        $level2 = $view['level_2'] ?? [];
        $level3 = $view['level_3'] ?? [];
        $html = \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-ux2-disclosures',
            'aria-label' => get_string('moredetails', 'local_flwcupkp'),
        ]);
        $html .= self::history_details($level2['history']['summary'] ?? [], $view['scope'] ?? []);
        $html .= self::roadmap_details($level2['roadmap'] ?? []);
        $html .= self::why_details($level3['why_this_activity'] ?? []);
        $html .= self::more_details($level3['more_details'] ?? []);
        $html .= \html_writer::end_tag('section');
        return $html;
    }

    /** Show History detail. */
    private static function history_details(array $history, array $scope): string {
        $body = \html_writer::start_div('local-flwcupkp-ux2-detail-body');
        $body .= \html_writer::tag('p', get_string('historydetailssummary', 'local_flwcupkp', (object)[
            'events' => (int)($history['learning_events'] ?? 0),
            'attempts' => (int)($history['attempts'] ?? 0),
            'grades' => (int)($history['grade_updates'] ?? 0),
        ]));
        if (!empty($scope['courseid']) && !empty($scope['userid'])) {
            $url = new \moodle_url('/local/flwhistory/dashboard.php', [
                'courseid' => (int)$scope['courseid'],
                'userid' => (int)$scope['userid'],
            ]);
            $body .= \html_writer::link($url, get_string('openfullhistory', 'local_flwcupkp'), [
                'class' => 'local-flwcupkp-ux2-secondary-link',
            ]);
        }
        $body .= \html_writer::end_div();
        return self::details(get_string('showhistory', 'local_flwcupkp'), $body);
    }

    /** Show Roadmap detail. */
    private static function roadmap_details(array $rows): string {
        if (!$rows) {
            $body = \html_writer::tag('p', get_string('comingupforming', 'local_flwcupkp'));
        } else {
            $body = \html_writer::start_tag('ol', ['class' => 'local-flwcupkp-ux2-roadmap']);
            foreach (array_slice($rows, 0, learner_experience_service::ROADMAP_LIMIT) as $row) {
                $body .= \html_writer::tag('li',
                    \html_writer::tag('strong', s((string)($row['title'] ?? ''))) .
                    \html_writer::tag('span', s((string)($row['action'] ?? '')))
                );
            }
            $body .= \html_writer::end_tag('ol');
        }
        return self::details(get_string('showroadmap', 'local_flwcupkp'), $body);
    }

    /** Why This Activity detail. */
    private static function why_details(array $why): string {
        $body = \html_writer::start_div('local-flwcupkp-ux2-detail-body');
        if (!empty($why['action'])) {
            $body .= \html_writer::tag('strong', s((string)$why['action']));
        }
        $body .= \html_writer::tag('p', s((string)($why['reason'] ?? '')));
        $body .= \html_writer::end_div();
        return self::details(get_string('whythisactivity', 'local_flwcupkp'), $body);
    }

    /** Friendly learner detail without IDs or policy metadata. */
    private static function more_details(array $details): string {
        $body = \html_writer::start_div('local-flwcupkp-ux2-detail-body');
        $skills = array_values($details['skills'] ?? []);
        if ($skills) {
            $body .= \html_writer::tag('h4', get_string('myabilityprogress', 'local_flwcupkp'));
            $body .= \html_writer::start_tag('ul', ['class' => 'local-flwcupkp-ux2-skill-list']);
            foreach ($skills as $skill) {
                $meta = get_string('abilitydetailvalue', 'local_flwcupkp', (object)[
                    'progress' => format_float((float)($skill['ability_percentage'] ?? 0), 1),
                    'results' => (int)($skill['learning_results'] ?? 0),
                    'review' => (string)($skill['review'] ?? ''),
                ]);
                $body .= \html_writer::tag('li',
                    \html_writer::tag('strong', s((string)($skill['title'] ?? ''))) .
                    \html_writer::tag('span', s((string)($skill['state'] ?? ''))) .
                    \html_writer::tag('small', s($meta))
                );
            }
            $body .= \html_writer::end_tag('ul');
        }
        $change = is_array($details['path_change'] ?? null) ? $details['path_change'] : [];
        if (!empty($change['changed']) && !empty($change['reason'])) {
            $body .= \html_writer::tag('h4', get_string('whymypathchanged', 'local_flwcupkp'));
            $body .= \html_writer::tag('p', s((string)$change['reason']));
        }
        $recent = array_values($details['recent_recommendations'] ?? []);
        if ($recent) {
            $body .= \html_writer::tag('h4', get_string('recentlearningsteps', 'local_flwcupkp'));
            $body .= \html_writer::start_tag('ul', ['class' => 'local-flwcupkp-ux2-recent']);
            foreach ($recent as $row) {
                $label = implode(': ', array_values(array_filter([
                    (string)($row['action'] ?? ''),
                    (string)($row['title'] ?? ''),
                ])));
                $body .= \html_writer::tag('li', s($label));
            }
            $body .= \html_writer::end_tag('ul');
        }
        if (!$skills && empty($change['changed']) && !$recent) {
            $body .= \html_writer::tag('p', get_string('detailsforming', 'local_flwcupkp'));
        }
        $body .= \html_writer::end_div();
        return self::details(get_string('moredetails', 'local_flwcupkp'), $body);
    }

    /** Native accessible details element. */
    private static function details(string $label, string $body): string {
        return \html_writer::tag('details',
            \html_writer::tag('summary', s($label)) . $body,
            ['class' => 'local-flwcupkp-ux2-details']
        );
    }

    /** Start one semantic flow section. */
    private static function section_start(string $key, string $title): string {
        return \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-ux2-section local-flwcupkp-ux2-' . $key,
            'data-stage' => $key,
        ]) . \html_writer::tag('h3', $title);
    }

    /** Compact fact. */
    private static function fact(string $label, string $value): string {
        return \html_writer::tag('div',
            \html_writer::tag('strong', s($value)) . \html_writer::tag('span', $label),
            ['class' => 'local-flwcupkp-ux2-fact']
        );
    }
}

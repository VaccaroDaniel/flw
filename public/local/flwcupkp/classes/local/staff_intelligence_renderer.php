<?php
// Renderer for Program 3 Gate UX3 staff explainability and interventions.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/** Staff-only detailed intelligence renderer. */
final class staff_intelligence_renderer {
    /** Render the complete staff learner view. */
    public static function render(array $view, bool $canoverride, \moodle_url $baseurl): string {
        $html = \html_writer::start_tag('div', [
            'class' => 'local-flwcupkp-ux3',
            'aria-label' => get_string('staffintelligenceflow', 'local_flwcupkp'),
        ]);
        $html .= self::hero($view);
        $html .= self::recommendation($view);
        $html .= self::why_grid($view['explanations'] ?? []);
        $html .= self::state_detail($view['states'] ?? [], $view['retention'] ?? []);
        $html .= self::prerequisites($view['prerequisites'] ?? []);
        $html .= self::evidence($view['evidence'] ?? []);
        $html .= self::policy_versions($view['policy_versions'] ?? []);
        $html .= self::interventions($view['interventions'] ?? [], $view['intervention_history'] ?? [],
            $canoverride, $baseurl);
        if ($canoverride) {
            $html .= self::intervention_form($view, $baseurl);
        }
        $html .= \html_writer::end_tag('div');
        return $html;
    }

    /** Staff header and compact learner position. */
    private static function hero(array $view): string {
        $learner = $view['learner'] ?? [];
        $summary = $view['learner_summary'] ?? [];
        $current = $summary['current'] ?? [];
        $next = $summary['next'] ?? [];
        $html = \html_writer::start_tag('header', ['class' => 'local-flwcupkp-ux3-hero']);
        $html .= \html_writer::tag('span', get_string('staffintelligenceeyebrow', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-ux3-eyebrow',
        ]);
        $html .= \html_writer::tag('h2', s((string)($learner['fullname'] ?? '')));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-snapshot']);
        $html .= self::metric(get_string('currentstatus', 'local_flwcupkp'),
            (string)($current['milestone'] ?? $current['status'] ?? get_string('notavailable')));
        $html .= self::metric(get_string('currentprogress', 'local_flwcupkp'),
            (string)($current['progress']['label'] ?? get_string('progressforming', 'local_flwcupkp')));
        $html .= self::metric(get_string('currentrecommendation', 'local_flwcupkp'),
            (string)($next['action'] ?? get_string('notavailable')));
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('header');
        return $html;
    }

    /** Current effective recommendation. */
    private static function recommendation(array $view): string {
        $path = $view['path'] ?? [];
        $recommendation = $path['recommendation'] ?? [];
        $target = $recommendation['selected_target'] ?? [];
        $activity = $recommendation['selected_activity'] ?? [];
        $html = self::section_start('decision', get_string('staffcurrentdecision', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-decision']);
        $html .= \html_writer::tag('strong', s((string)($recommendation['action'] ??
            get_string('notavailable'))), ['class' => 'local-flwcupkp-ux3-action']);
        $html .= \html_writer::tag('p', s((string)($recommendation['reason'] ??
            get_string('staffnorecommendation', 'local_flwcupkp'))));
        $html .= \html_writer::start_tag('dl');
        $html .= self::definition(get_string('target', 'local_flwcupkp'),
            (string)($target['title'] ?? $target['externalid'] ?? get_string('notavailable')));
        $html .= self::definition(get_string('activity', 'local_flwcupkp'),
            (string)($activity['title'] ?? get_string('notavailable')));
        $html .= self::definition(get_string('decisioncode', 'local_flwcupkp'),
            (string)($recommendation['decision_code'] ?? ''));
        $html .= self::definition(get_string('persistence', 'local_flwcupkp'),
            (string)($path['persistence'] ?? ''));
        $html .= \html_writer::end_tag('dl');
        $html .= \html_writer::end_tag('div');
        $html .= self::section_end();
        return $html;
    }

    /** Six frozen why questions. */
    private static function why_grid(array $explanations): string {
        $labels = [
            'why_target' => 'staffwhytarget',
            'why_activity' => 'staffwhyactivity',
            'why_extra_practice' => 'staffwhyextrapractice',
            'why_review' => 'staffwhyreview',
            'why_skip' => 'staffwhyskip',
            'why_path_changed' => 'staffwhypathchanged',
        ];
        $html = self::section_start('why', get_string('staffrecommendationexplanation', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-why-grid']);
        foreach ($labels as $key => $stringkey) {
            $row = is_array($explanations[$key] ?? null) ? $explanations[$key] : [];
            $html .= \html_writer::start_tag('article', ['class' => 'local-flwcupkp-ux3-why']);
            $html .= \html_writer::tag('h4', get_string($stringkey, 'local_flwcupkp'));
            $html .= \html_writer::tag('p', s((string)($row['answer'] ?? get_string('notavailable'))));
            $html .= \html_writer::end_tag('article');
        }
        $html .= \html_writer::end_tag('div');
        $html .= self::section_end();
        return $html;
    }

    /** Mastery, confidence, and retention detail. */
    private static function state_detail(array $states, array $retention): string {
        $retentionmap = [];
        foreach ($retention as $row) {
            $key = (string)($row['target']['type'] ?? '') . ':' . (int)($row['target']['id'] ?? 0);
            $retentionmap[$key] = $row;
        }
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-flwcupkp-ux3-table';
        $table->head = [
            get_string('type', 'local_flwcupkp'),
            get_string('target', 'local_flwcupkp'),
            get_string('mastery', 'local_flwcupkp'),
            get_string('confidence', 'local_flwcupkp'),
            get_string('retention', 'local_flwcupkp'),
            get_string('evidence', 'local_flwcupkp'),
            get_string('policyversion', 'local_flwcupkp'),
        ];
        foreach ($states as $state) {
            $target = $state['target'] ?? [];
            $key = (string)($target['type'] ?? '') . ':' . (int)($target['id'] ?? 0);
            $retentionrow = $retentionmap[$key] ?? [];
            $table->data[] = [
                s(strtoupper((string)($target['type'] ?? ''))),
                s((string)($target['title'] ?? $target['externalid'] ?? '')),
                self::score_state($state['mastery'] ?? []),
                self::percent($state['confidence']['value'] ?? $state['confidence']['score'] ?? null),
                s((string)($retentionrow['calculated']['retentionstate'] ?? get_string('notavailable'))),
                (string)(int)($state['evidence']['count'] ?? 0),
                s((string)($state['policyversion'] ?? '')),
            ];
        }
        $html = self::section_start('states', get_string('stafflearnerstate', 'local_flwcupkp'));
        $html .= $states ? \html_writer::table($table) :
            \html_writer::tag('p', get_string('staffnostates', 'local_flwcupkp'));
        $html .= self::section_end();
        return $html;
    }

    /** Prerequisite detail. */
    private static function prerequisites(array $rows): string {
        $html = self::section_start('prerequisites', get_string('staffprerequisites', 'local_flwcupkp'));
        if (!$rows) {
            $html .= \html_writer::tag('p', get_string('staffnoprerequisites', 'local_flwcupkp'));
        } else {
            $html .= \html_writer::start_tag('ul', ['class' => 'local-flwcupkp-ux3-list']);
            foreach ($rows as $row) {
                $target = $row['target'] ?? [];
                $needed = $row['needed_first'] ?? [];
                $status = !empty($row['satisfied']) ? get_string('satisfied', 'local_flwcupkp') :
                    get_string('notyetsatisfied', 'local_flwcupkp');
                $text = get_string('staffprerequisiterow', 'local_flwcupkp', (object)[
                    'target' => (string)($target['title'] ?? $target['externalid'] ?? ''),
                    'needed' => (string)($needed['title'] ?? $needed['externalid'] ?? ''),
                    'requirement' => (string)($row['requirement'] ?? ''),
                    'status' => $status,
                ]);
                $html .= \html_writer::tag('li', s($text));
            }
            $html .= \html_writer::end_tag('ul');
        }
        $html .= self::section_end();
        return $html;
    }

    /** Evidence provenance detail. */
    private static function evidence(array $rows): string {
        $html = self::section_start('evidence', get_string('staffevidenceprovenance', 'local_flwcupkp'));
        if (!$rows) {
            $html .= \html_writer::tag('p', get_string('staffnoevidence', 'local_flwcupkp'));
            return $html . self::section_end();
        }
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-flwcupkp-ux3-table';
        $table->head = [
            get_string('target', 'local_flwcupkp'),
            get_string('score', 'local_flwcupkp'),
            get_string('confidence', 'local_flwcupkp'),
            get_string('result', 'local_flwcupkp'),
            get_string('source', 'local_flwcupkp'),
            get_string('provenance', 'local_flwcupkp'),
            get_string('date'),
        ];
        foreach ($rows as $row) {
            $target = $row['target'] ?? [];
            $table->data[] = [
                s((string)($target['title'] ?? $target['externalid'] ?? '')),
                self::percent($row['score'] ?? null),
                self::percent($row['confidence'] ?? null),
                s((string)($row['result_state'] ?? '')),
                s((string)($row['evidence_type'] ?? '')),
                s((string)($row['provenance'] ?? '')),
                !empty($row['timecreated']) ? userdate((int)$row['timecreated'], get_string('strftimedatetimeshort')) : '',
            ];
        }
        $html .= \html_writer::table($table);
        $html .= self::section_end();
        return $html;
    }

    /** Policy versions grouped away from the learner UI. */
    private static function policy_versions(array $versions): string {
        $html = self::section_start('policies', get_string('staffpolicyversions', 'local_flwcupkp'));
        $html .= \html_writer::start_tag('dl', ['class' => 'local-flwcupkp-ux3-policy-list']);
        foreach ($versions as $name => $version) {
            $html .= self::definition(ucwords(str_replace('_', ' ', (string)$name)), (string)$version);
        }
        $html .= \html_writer::end_tag('dl');
        $html .= self::section_end();
        return $html;
    }

    /** Active controls and immutable history. */
    private static function interventions(array $active, array $history, bool $canoverride,
            \moodle_url $baseurl): string {
        $html = self::section_start('interventions', get_string('staffinterventions', 'local_flwcupkp'));
        if (!$active) {
            $html .= \html_writer::tag('p', get_string('staffnoactiveinterventions', 'local_flwcupkp'));
        } else {
            $html .= \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-intervention-list']);
            foreach ($active as $row) {
                $html .= \html_writer::start_tag('article', ['class' => 'local-flwcupkp-ux3-intervention']);
                $html .= \html_writer::tag('h4', s(ucwords(str_replace('_', ' ',
                    (string)$row['interventiontype']))));
                $html .= \html_writer::tag('p', s((string)$row['reason']));
                $html .= \html_writer::tag('small', get_string('staffinterventionversion', 'local_flwcupkp',
                    (object)['version' => (int)$row['version'], 'action' => (string)$row['actioncode']]));
                if ($canoverride) {
                    $html .= self::release_form($row, $baseurl);
                }
                $html .= \html_writer::end_tag('article');
            }
            $html .= \html_writer::end_tag('div');
        }
        $html .= self::history_details($history);
        $html .= self::section_end();
        return $html;
    }

    /** New intervention form. */
    private static function intervention_form(array $view, \moodle_url $baseurl): string {
        $targetoptions = ['' => get_string('choosedots')];
        foreach (($view['intervention_options']['targets'] ?? []) as $target) {
            if (empty($target['id']) || empty($target['type'])) {
                continue;
            }
            $value = (string)$target['type'] . ':' . (int)$target['id'];
            $targetoptions[$value] = strtoupper((string)$target['type']) . ' - ' .
                (string)($target['title'] ?? $target['externalid'] ?? '');
        }
        $activityoptions = ['' => get_string('choosedots')];
        foreach (($view['intervention_options']['eligible_activities'] ?? []) as $activity) {
            $value = (int)($activity['objectid'] ?? 0) . ':' . (int)($activity['cmid'] ?? 0);
            $activityoptions[$value] = (string)($activity['title'] ?? '') . ' - ' .
                strtoupper((string)($activity['targettype'] ?? ''));
        }
        $types = [
            'assign_target_activity' => get_string('staffassignactivity', 'local_flwcupkp'),
            'force_review' => get_string('staffforcereview', 'local_flwcupkp'),
            'hold_advancement' => get_string('staffholdadvancement', 'local_flwcupkp'),
            'override_recommendation' => get_string('staffoverriderecommendation', 'local_flwcupkp'),
            'adjust_goal' => get_string('staffadjustgoal', 'local_flwcupkp'),
            'teacher_evidence' => get_string('staffteacherevidence', 'local_flwcupkp'),
        ];
        $actions = array_combine(
            \local_flwcupkp\local\adaptive_path_engine_service::ACTIONS,
            \local_flwcupkp\local\adaptive_path_engine_service::ACTIONS
        );

        $form = \html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $baseurl->out(false),
            'class' => 'local-flwcupkp-ux3-form',
        ]);
        $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
        $form .= self::select_field('interventiontype', get_string('staffinterventiontype', 'local_flwcupkp'),
            $types);
        $form .= self::select_field('target', get_string('target', 'local_flwcupkp'), $targetoptions);
        $form .= self::select_field('activitychoice', get_string('activity', 'local_flwcupkp'), $activityoptions);
        $form .= self::select_field('actioncode', get_string('staffrecommendationaction', 'local_flwcupkp'), $actions);
        $form .= self::input_field('score', get_string('score', 'local_flwcupkp'), 'number', '0.75',
            ['min' => '0', 'max' => '1', 'step' => '0.01']);
        $form .= self::input_field('confidence', get_string('confidence', 'local_flwcupkp'), 'number', '0.75',
            ['min' => '0', 'max' => '1', 'step' => '0.01']);
        $form .= self::input_field('goaltitle', get_string('goaltitle', 'local_flwcupkp'), 'text', '');
        $form .= self::input_field('goalpurpose', get_string('goalpurpose', 'local_flwcupkp'), 'text', '');
        $form .= self::input_field('observationnote', get_string('staffobservationnote', 'local_flwcupkp'),
            'text', '');
        $form .= self::input_field('reason', get_string('reason', 'local_flwcupkp'), 'text', '',
            ['required' => 'required']);
        $form .= \html_writer::tag('button', get_string('staffapplyintervention', 'local_flwcupkp'), [
            'type' => 'submit', 'class' => 'btn btn-primary',
        ]);
        $form .= \html_writer::end_tag('form');

        $html = self::section_start('control', get_string('staffcontrolledoverride', 'local_flwcupkp'));
        $html .= \html_writer::tag('details',
            \html_writer::tag('summary', get_string('staffnewintervention', 'local_flwcupkp')) . $form,
            ['class' => 'local-flwcupkp-ux3-control']
        );
        $html .= self::section_end();
        return $html;
    }

    /** Release form with mandatory reason. */
    private static function release_form(array $row, \moodle_url $baseurl): string {
        $form = \html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $baseurl->out(false),
            'class' => 'local-flwcupkp-ux3-release-form',
        ]);
        foreach ([
            'sesskey' => sesskey(),
            'action' => 'release',
            'interventionid' => (int)$row['id'],
        ] as $name => $value) {
            $form .= \html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => $name, 'value' => $value,
            ]);
        }
        $form .= \html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'releasereason',
            'required' => 'required',
            'aria-label' => get_string('staffreleasereason', 'local_flwcupkp'),
            'placeholder' => get_string('staffreleasereason', 'local_flwcupkp'),
        ]);
        $form .= \html_writer::tag('button', get_string('staffreleaseintervention', 'local_flwcupkp'), [
            'type' => 'submit', 'class' => 'btn btn-secondary btn-sm',
        ]);
        return $form . \html_writer::end_tag('form');
    }

    /** Immutable history disclosure. */
    private static function history_details(array $history): string {
        $body = '';
        if (!$history) {
            $body = \html_writer::tag('p', get_string('staffnointerventionhistory', 'local_flwcupkp'));
        } else {
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable local-flwcupkp-ux3-table';
            $table->head = [get_string('version', 'local_flwcupkp'), get_string('type', 'local_flwcupkp'),
                get_string('status'), get_string('reason', 'local_flwcupkp'), get_string('date')];
            foreach ($history as $row) {
                $table->data[] = [
                    (string)(int)$row['version'],
                    s(ucwords(str_replace('_', ' ', (string)$row['interventiontype']))),
                    s((string)$row['status']),
                    s((string)$row['reason']),
                    userdate((int)$row['timecreated'], get_string('strftimedatetimeshort')),
                ];
            }
            $body = \html_writer::table($table);
        }
        return \html_writer::tag('details',
            \html_writer::tag('summary', get_string('staffshowinterventionhistory', 'local_flwcupkp')) . $body,
            ['class' => 'local-flwcupkp-ux3-history']
        );
    }

    /** Labeled select field. */
    private static function select_field(string $name, string $label, array $options): string {
        $id = 'local-flwcupkp-ux3-' . $name;
        $html = \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-field']);
        $html .= \html_writer::label($label, $id);
        $html .= \html_writer::select($options, $name, '', false, ['id' => $id]);
        return $html . \html_writer::end_tag('div');
    }

    /** Labeled input field. */
    private static function input_field(string $name, string $label, string $type, string $value,
            array $attributes = []): string {
        $id = 'local-flwcupkp-ux3-' . $name;
        $attributes += ['type' => $type, 'name' => $name, 'id' => $id, 'value' => $value];
        $html = \html_writer::start_tag('div', ['class' => 'local-flwcupkp-ux3-field']);
        $html .= \html_writer::label($label, $id);
        $html .= \html_writer::empty_tag('input', $attributes);
        return $html . \html_writer::end_tag('div');
    }

    /** Start one full-width staff band. */
    private static function section_start(string $key, string $title): string {
        return \html_writer::start_tag('section', [
            'class' => 'local-flwcupkp-ux3-section local-flwcupkp-ux3-' . $key,
            'aria-labelledby' => 'local-flwcupkp-ux3-' . $key . '-title',
        ]) . \html_writer::tag('h3', $title, ['id' => 'local-flwcupkp-ux3-' . $key . '-title']);
    }

    /** End one staff band. */
    private static function section_end(): string {
        return \html_writer::end_tag('section');
    }

    /** One compact metric. */
    private static function metric(string $label, string $value): string {
        return \html_writer::tag('div',
            \html_writer::tag('span', $label) . \html_writer::tag('strong', s($value)),
            ['class' => 'local-flwcupkp-ux3-metric']
        );
    }

    /** One definition row. */
    private static function definition(string $term, string $value): string {
        return \html_writer::tag('dt', s($term)) . \html_writer::tag('dd', s($value));
    }

    /** Score and state. */
    private static function score_state(array $mastery): string {
        return s((string)($mastery['state'] ?? '')) . ' (' . self::percent($mastery['score'] ?? null) . ')';
    }

    /** Format normalized value as a percentage. */
    private static function percent($value): string {
        return $value === null || $value === '' ? get_string('notavailable') :
            format_float((float)$value * 100, 1) . '%';
    }
}

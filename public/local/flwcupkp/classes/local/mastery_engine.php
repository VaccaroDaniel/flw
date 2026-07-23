<?php
// Mastery calculation service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Explainable mastery engine.
 */
class mastery_engine {
    /** @var array Default provisional thresholds. */
    private const DEFAULTS = [
        'kp' => [
            'introduced' => 0.10,
            'practiced' => 0.35,
            'controlled_use' => 0.55,
            'independent_use' => 0.70,
            'mastered' => 0.85,
            'review_after_days' => 21,
        ],
        'up' => [
            'emerging' => 0.20,
            'developing' => 0.45,
            'demonstrated' => 0.70,
            'stable' => 0.82,
            'transfer_ready' => 0.90,
        ],
        'competency' => [
            'developing' => 0.35,
            'provisionally_achieved' => 0.70,
            'achieved' => 0.82,
            'sustained' => 0.90,
            'direct_evidence_required' => true,
        ],
    ];

    /**
     * Calculate state from evidence records.
     *
     * @param string $targettype kp, up, or competency
     * @param array $evidence
     * @param array|null $rules
     * @return array
     */
    public static function calculate(string $targettype, array $evidence, ?array $rules = null): array {
        $rules = $rules ?? self::rules_for($targettype);
        $count = count($evidence);
        $now = time();

        if ($count === 0) {
            return self::empty_state($targettype);
        }

        $weighted = 0.0;
        $weights = 0.0;
        $confidence = 0.0;
        $last = 0;
        $lastsuccess = null;
        $hasdirect = false;

        foreach ($evidence as $event) {
            $score = (float)($event->normalizedscore ?? 0);
            $strength = self::strength_weight((string)($event->evidencestrength ?? 'recognition'));
            $weighted += $score * $strength;
            $weights += $strength;
            $confidence = max($confidence, (float)($event->confidence ?? min(1, $strength / 5)));
            $time = (int)($event->timecreated ?? 0);
            $last = max($last, $time);
            if ($score >= 0.70) {
                $lastsuccess = max((int)$lastsuccess, $time);
            }
            if (in_array($event->evidencestrength ?? '', ['guided_performance', 'independent_performance', 'transfer_performance'], true)) {
                $hasdirect = true;
            }
        }

        $score = $weights > 0 ? $weighted / $weights : 0.0;
        $state = self::state_name($targettype, $score, $rules, $hasdirect);
        $nextreview = null;

        if ($targettype === 'kp' && $lastsuccess && $state === 'mastered') {
            $days = (int)($rules['review_after_days'] ?? self::DEFAULTS['kp']['review_after_days']);
            $nextreview = $lastsuccess + ($days * DAYSECS);
            if ($nextreview < $now) {
                $state = 'review_due';
            }
        }

        return [
            'masteryscore' => round($score, 5),
            'masterystate' => $state,
            'confidence' => round(min(1.0, $confidence), 5),
            'evidencecount' => $count,
            'lastevidence' => $last ?: null,
            'lastsuccess' => $lastsuccess,
            'nextreview' => $nextreview,
            'ruleversion' => $rules['version'] ?? 'default-v1',
            'explanation' => [
                'direct_evidence_present' => $hasdirect,
                'weighted_score' => round($score, 5),
                'evidence_count' => $count,
            ],
        ];
    }

    /**
     * Return the active threshold rules for a target type.
     *
     * @param string $targettype
     * @return array
     */
    public static function rules_for(string $targettype): array {
        global $DB;

        $defaults = self::default_rules($targettype);
        if (!$defaults) {
            return [];
        }

        try {
            if (!$DB->get_manager()->table_exists('flwcupkp_rule')) {
                return $defaults;
            }
            $records = $DB->get_records_sql(
                "SELECT *
                   FROM {flwcupkp_rule}
                  WHERE status = :status
                    AND ruletype = :ruletype
               ORDER BY timemodified DESC, id DESC",
                [
                    'status' => 'active',
                    'ruletype' => $targettype . '_mastery',
                ],
                0,
                1
            );
        } catch (\Throwable $e) {
            return $defaults;
        }

        $record = reset($records);
        if (!$record) {
            return $defaults;
        }

        $config = json_decode((string)$record->configjson, true);
        if (!is_array($config)) {
            return $defaults;
        }
        $config['version'] = (string)$record->version;
        return $config + $defaults;
    }

    /**
     * Built-in provisional fallback threshold rules.
     *
     * @param string $targettype
     * @return array
     */
    public static function default_rules(string $targettype): array {
        return self::DEFAULTS[$targettype] ?? [];
    }

    /**
     * Store evidence and update learner state.
     *
     * @param \stdClass $evidence
     * @return array
     */
    public static function record_evidence(\stdClass $evidence): array {
        global $DB, $USER;

        $evidence = evidence_guard::normalize_evidence($evidence);
        $evidence->timecreated = $evidence->timecreated ?? time();
        $evidence->usermodified = $USER->id ?? 0;
        $evidenceid = $DB->insert_record('flwcupkp_evidence', $evidence);

        $events = $DB->get_records('flwcupkp_evidence', [
            'userid' => $evidence->userid,
            'targettype' => $evidence->targettype,
            'targetid' => $evidence->targetid,
        ], 'timecreated ASC');

        $state = self::calculate($evidence->targettype, array_values($events));
        repository::upsert_state((int)$evidence->userid, $evidence->targettype, (int)$evidence->targetid, $state);
        repository::audit('evidence_recorded', $evidence->targettype, (int)$evidence->targetid, ['evidenceid' => $evidenceid]);
        self::rollup_dependents_if_ready($evidence);
        self::sync_moodle_competency_if_ready($evidence);

        return ['evidenceid' => (int)$evidenceid, 'state' => $state];
    }

    /**
     * Best-effort parent-state roll-up after a target receives evidence.
     *
     * @param \stdClass $evidence
     */
    private static function rollup_dependents_if_ready(\stdClass $evidence): void {
        try {
            rollup_engine::recalculate_dependents(
                (int)$evidence->userid,
                (string)$evidence->targettype,
                (int)$evidence->targetid,
                true
            );
        } catch (\Throwable $e) {
            repository::audit('rollup_state_sync_failed', (string)$evidence->targettype, (int)$evidence->targetid, [
                'userid' => (int)$evidence->userid,
                'message' => $e->getMessage(),
                'source' => 'record_evidence',
            ]);
        }
    }

    /**
     * Best-effort immediate native Moodle competency sync for competency evidence.
     *
     * @param \stdClass $evidence
     */
    private static function sync_moodle_competency_if_ready(\stdClass $evidence): void {
        if ((string)$evidence->targettype !== 'competency') {
            return;
        }
        if (!(bool)get_config('local_flwcupkp', 'enablesyncwrites')) {
            return;
        }
        $readiness = curriculum_manager::sync_readiness();
        if (empty($readiness['readyforwrites'])) {
            return;
        }

        try {
            moodle_competency_writer::sync_competency_state(
                (int)$evidence->userid,
                (int)$evidence->targetid,
                false
            );
        } catch (\Throwable $e) {
            repository::audit('moodle_competency_rating_sync_failed', 'competency', (int)$evidence->targetid, [
                'userid' => (int)$evidence->userid,
                'message' => $e->getMessage(),
                'source' => 'record_evidence',
            ]);
        }
    }

    /**
     * Evidence strength weights.
     *
     * @param string $strength
     * @return float
     */
    private static function strength_weight(string $strength): float {
        $weights = [
            'exposure' => 0.5,
            'recognition' => 1.0,
            'controlled_production' => 2.0,
            'guided_performance' => 3.0,
            'independent_performance' => 4.0,
            'transfer_performance' => 5.0,
        ];
        return $weights[$strength] ?? 1.0;
    }

    /**
     * Get state label.
     *
     * @param string $targettype
     * @param float $score
     * @param array $rules
     * @param bool $hasdirect
     * @return string
     */
    private static function state_name(string $targettype, float $score, array $rules, bool $hasdirect): string {
        if ($targettype === 'competency' && !empty($rules['direct_evidence_required']) && !$hasdirect) {
            return $score >= ($rules['developing'] ?? 0.35) ? 'developing' : 'not_started';
        }

        $thresholds = [
            'kp' => ['mastered', 'independent_use', 'controlled_use', 'practiced', 'introduced'],
            'up' => ['transfer_ready', 'stable', 'demonstrated', 'developing', 'emerging'],
            'competency' => ['sustained', 'achieved', 'provisionally_achieved', 'developing'],
        ];

        foreach ($thresholds[$targettype] ?? [] as $name) {
            if ($score >= (float)($rules[$name] ?? 1.1)) {
                return $name;
            }
        }

        return $targettype === 'kp' ? 'not_introduced' : ($targettype === 'up' ? 'not_observed' : 'not_started');
    }

    /**
     * Empty state by target type.
     *
     * @param string $targettype
     * @return array
     */
    private static function empty_state(string $targettype): array {
        return [
            'masteryscore' => 0,
            'masterystate' => $targettype === 'kp' ? 'not_introduced' : ($targettype === 'up' ? 'not_observed' : 'not_started'),
            'confidence' => 0,
            'evidencecount' => 0,
            'ruleversion' => 'default-v1',
            'explanation' => ['evidence_count' => 0],
        ];
    }
}

<?php
// Mastery calculation service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Explainable mastery engine.
 */
class mastery_engine {
    /** Versioned deterministic mastery calculation policy. */
    public const POLICY_VERSION = 'cupkp-mastery-policy-v1';

    /** Versioned deterministic confidence calculation policy. */
    public const CONFIDENCE_POLICY_VERSION = 'cupkp-confidence-policy-v1';

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
            $time = (int)($event->timecreated ?? 0);
            $last = max($last, $time);
            if (self::c3b_result_state($event) === 'inconclusive') {
                continue;
            }

            $score = (float)($event->normalizedscore ?? 0);
            $strength = self::strength_weight((string)($event->evidencestrength ?? 'recognition'));
            $weighted += $score * $strength;
            $weights += $strength;
            $confidence = max($confidence, (float)($event->confidence ?? min(1, $strength / 5)));
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

        $snapshot = self::snapshot_metadata($targettype, $evidence, $score, $hasdirect, $rules, $now);

        return [
            'masteryscore' => round($score, 5),
            'masterystate' => $state,
            'confidence' => $snapshot['confidence']['score'],
            'evidencecount' => $count,
            'lastevidence' => $last ?: null,
            'lastsuccess' => $lastsuccess,
            'nextreview' => $nextreview,
            'ruleversion' => $rules['version'] ?? 'default-v1',
            'policyversion' => self::POLICY_VERSION,
            'confidencepolicyversion' => self::CONFIDENCE_POLICY_VERSION,
            'evidenceids' => $snapshot['evidenceids'],
            'evidenceidsjson' => json_encode($snapshot['evidenceids'], JSON_UNESCAPED_SLASHES),
            'evidencehash' => $snapshot['evidencehash'],
            'calculatedtime' => $now,
            'status' => $state,
            'confidence_model' => $snapshot['confidence'],
            'explanation' => [
                'direct_evidence_present' => $hasdirect,
                'weighted_score' => round($score, 5),
                'evidence_count' => $count,
                'confidence_score' => $snapshot['confidence']['score'],
                'confidence_label' => $snapshot['confidence']['label'],
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

        $evidence->timecreated = $evidence->timecreated ?? time();
        $evidence->usermodified = $USER->id ?? 0;
        $evidence = evidence_guard::normalize_evidence($evidence);
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
     * Return C3B result state when present.
     *
     * @param \stdClass $event
     * @return string
     */
    private static function c3b_result_state(\stdClass $event): string {
        $rubric = json_decode((string)($event->rubricjson ?? ''), true);
        if (!is_array($rubric)) {
            return '';
        }
        return (string)($rubric['cupkp_c3b_semantics']['result_state'] ?? '');
    }

    /**
     * Build reproducibility metadata for a calculated state.
     *
     * @param string $targettype
     * @param array $evidence
     * @param float $masteryscore
     * @param bool $hasdirect
     * @param array $rules
     * @param int $now
     * @return array
     */
    private static function snapshot_metadata(string $targettype, array $evidence, float $masteryscore, bool $hasdirect,
            array $rules, int $now): array {
        $ids = self::evidence_ids($evidence);
        return [
            'evidenceids' => $ids,
            'evidencehash' => self::evidence_hash($evidence),
            'confidence' => self::confidence_model($targettype, $evidence, $masteryscore, $hasdirect, $rules, $now),
        ];
    }

    /**
     * Deterministic confidence model separate from mastery score and grades.
     *
     * @param string $targettype
     * @param array $evidence
     * @param float $masteryscore
     * @param bool $hasdirect
     * @param array $rules
     * @param int $now
     * @return array
     */
    private static function confidence_model(string $targettype, array $evidence, float $masteryscore, bool $hasdirect,
            array $rules, int $now): array {
        $meaningful = 0;
        $assessor = 0.0;
        $quality = 0.0;
        $independence = 0.0;
        $mode = 0.0;
        $recency = 0.0;
        $ceilingcap = 1.0;
        $sources = [];
        $strengths = [];
        $inconclusive = 0;

        foreach ($evidence as $event) {
            $semantics = self::c3b_semantics($event);
            if (($semantics['result_state'] ?? '') === 'inconclusive') {
                $inconclusive++;
                $ceilingcap = min($ceilingcap, 0.15);
                continue;
            }

            $meaningful++;
            $eventconfidence = self::clamp01((float)($event->confidence ?? 0.5));
            $assessor += $eventconfidence;
            $quality += self::quality_integrity($event, $semantics, $eventconfidence);
            $strengthweight = self::strength_weight((string)($event->evidencestrength ?? 'recognition'));
            $independence += min(1.0, $strengthweight / 5);
            $performancemode = (string)($semantics['performance_mode'] ?? '');
            if ($performancemode === '') {
                $performancemode = self::performance_mode_from_strength((string)($event->evidencestrength ?? ''));
            }
            $mode += self::performance_mode_weight($performancemode);
            $recency += self::recency_weight((int)($event->timecreated ?? 0), $now);
            $ceilingcap = min($ceilingcap, self::ceiling_cap($targettype, $semantics, $performancemode));
            $sources[] = (string)($event->provenance ?? '') . '|' . (string)($event->evidencetype ?? '');
            $strengths[] = (string)($event->evidencestrength ?? '');
        }

        if ($meaningful === 0) {
            return [
                'score' => 0.0,
                'label' => $inconclusive > 0 ? 'inconclusive' : 'none',
                'policyversion' => self::CONFIDENCE_POLICY_VERSION,
                'inputs' => [
                    'meaningful_evidence_count' => 0,
                    'inconclusive_evidence_count' => $inconclusive,
                    'minimum_evidence_required' => self::minimum_evidence_required($targettype, $hasdirect),
                    'source_diversity' => 0.0,
                    'evidence_ceiling_cap' => round($ceilingcap, 5),
                ],
            ];
        }

        $minimum = self::minimum_evidence_required($targettype, $hasdirect);
        $sufficiency = min(1.0, $meaningful / max(1, $minimum));
        $diversity = min(1.0, (count(array_unique($sources)) + count(array_unique($strengths))) / 4);
        $base = (
            (0.25 * ($assessor / $meaningful)) +
            (0.20 * ($quality / $meaningful)) +
            (0.20 * ($independence / $meaningful)) +
            (0.10 * ($mode / $meaningful)) +
            (0.10 * ($recency / $meaningful)) +
            (0.15 * $diversity)
        );
        $score = $base * (0.70 + (0.30 * $sufficiency));
        if ($targettype === 'competency' && !empty($rules['direct_evidence_required']) && !$hasdirect) {
            $ceilingcap = min($ceilingcap, 0.60);
        }
        $score = round(self::clamp01(min($score, $ceilingcap)), 5);

        return [
            'score' => $score,
            'label' => self::confidence_label($score),
            'policyversion' => self::CONFIDENCE_POLICY_VERSION,
            'inputs' => [
                'meaningful_evidence_count' => $meaningful,
                'inconclusive_evidence_count' => $inconclusive,
                'minimum_evidence_required' => $minimum,
                'minimum_sufficiency' => round($sufficiency, 5),
                'average_assessor_confidence' => round($assessor / $meaningful, 5),
                'average_quality_integrity' => round($quality / $meaningful, 5),
                'average_independence' => round($independence / $meaningful, 5),
                'average_performance_mode' => round($mode / $meaningful, 5),
                'bounded_recency' => round($recency / $meaningful, 5),
                'source_diversity' => round($diversity, 5),
                'evidence_ceiling_cap' => round($ceilingcap, 5),
                'mastery_score_observed' => round($masteryscore, 5),
            ],
        ];
    }

    /**
     * Extract C3B semantics from evidence rubric JSON.
     *
     * @param \stdClass $event
     * @return array
     */
    private static function c3b_semantics(\stdClass $event): array {
        $rubric = json_decode((string)($event->rubricjson ?? ''), true);
        if (!is_array($rubric)) {
            return [];
        }
        $semantics = $rubric['cupkp_c3b_semantics'] ?? [];
        return is_array($semantics) ? $semantics : [];
    }

    /**
     * Quality integrity from C3B metadata or a conservative fallback.
     *
     * @param \stdClass $event
     * @param array $semantics
     * @param float $fallback
     * @return float
     */
    private static function quality_integrity(\stdClass $event, array $semantics, float $fallback): float {
        if (isset($semantics['quality_integrity_score']) && is_numeric($semantics['quality_integrity_score'])) {
            return self::clamp01((float)$semantics['quality_integrity_score']);
        }
        $quality = $semantics['quality'] ?? [];
        if (is_array($quality)) {
            $dimensions = ['validity', 'reliability', 'independence', 'authenticity', 'confidence'];
            $sum = 0.0;
            $count = 0;
            foreach ($dimensions as $dimension) {
                if (isset($quality[$dimension]) && is_numeric($quality[$dimension])) {
                    $sum += self::clamp01((float)$quality[$dimension]);
                    $count++;
                }
            }
            if ($count > 0) {
                return $sum / $count;
            }
        }
        return max(0.45, min(0.75, $fallback));
    }

    /**
     * Map legacy evidence strength to a C3B-like performance mode.
     *
     * @param string $strength
     * @return string
     */
    private static function performance_mode_from_strength(string $strength): string {
        $map = [
            'exposure' => 'passive_exposure',
            'recognition' => 'recognition',
            'controlled_production' => 'controlled_recall',
            'guided_performance' => 'guided_production',
            'independent_performance' => 'independent_production',
            'transfer_performance' => 'transfer',
        ];
        return $map[$strength] ?? 'recognition';
    }

    /**
     * Confidence contribution from performance mode.
     *
     * @param string $mode
     * @return float
     */
    private static function performance_mode_weight(string $mode): float {
        $weights = [
            'passive_exposure' => 0.20,
            'recognition' => 0.45,
            'comprehension' => 0.50,
            'selection' => 0.50,
            'controlled_recall' => 0.62,
            'guided_production' => 0.75,
            'independent_production' => 0.90,
            'interaction' => 0.95,
            'transfer' => 1.00,
        ];
        return $weights[$mode] ?? 0.45;
    }

    /**
     * Bounded recency factor; it affects confidence, not mastery.
     *
     * @param int $timecreated
     * @param int $now
     * @return float
     */
    private static function recency_weight(int $timecreated, int $now): float {
        if ($timecreated <= 0) {
            return 0.50;
        }
        $days = max(0, ($now - $timecreated) / 86400);
        if ($days <= 14) {
            return 1.00;
        }
        if ($days <= 45) {
            return 0.85;
        }
        if ($days <= 90) {
            return 0.70;
        }
        if ($days <= 180) {
            return 0.55;
        }
        return 0.45;
    }

    /**
     * Advisory evidence ceiling cap from C3B semantics.
     *
     * @param string $targettype
     * @param array $semantics
     * @param string $mode
     * @return float
     */
    private static function ceiling_cap(string $targettype, array $semantics, string $mode): float {
        $ceiling = $semantics['evidence_ceiling_hint'] ?? [];
        $claim = is_array($ceiling) ? (string)($ceiling['claim'] ?? '') : '';
        if ($claim === 'no_positive_or_negative_mastery_claim') {
            return 0.15;
        }
        if ($claim === 'cannot_establish_higher_order_productive_mastery') {
            return 0.60;
        }
        if ($claim === 'lower_order_support_only') {
            return $targettype === 'kp' ? 0.72 : 0.60;
        }
        if (in_array($mode, ['passive_exposure', 'recognition', 'selection'], true)) {
            return $targettype === 'kp' ? 0.72 : 0.60;
        }
        if (in_array($mode, ['interaction', 'transfer', 'independent_production'], true)) {
            return 1.00;
        }
        return 0.85;
    }

    /**
     * Minimum sufficient evidence count for confidence.
     *
     * @param string $targettype
     * @param bool $hasdirect
     * @return int
     */
    private static function minimum_evidence_required(string $targettype, bool $hasdirect): int {
        if ($targettype === 'competency') {
            return $hasdirect ? 2 : 3;
        }
        return $targettype === 'up' ? 2 : 2;
    }

    /**
     * Human-readable confidence band.
     *
     * @param float $score
     * @return string
     */
    private static function confidence_label(float $score): string {
        if ($score >= 0.75) {
            return 'high';
        }
        if ($score >= 0.50) {
            return 'medium';
        }
        if ($score > 0) {
            return 'low';
        }
        return 'none';
    }

    /**
     * Evidence row IDs used by the state snapshot.
     *
     * @param array $evidence
     * @return array
     */
    private static function evidence_ids(array $evidence): array {
        $ids = [];
        foreach ($evidence as $event) {
            if (!empty($event->id)) {
                $ids[] = (int)$event->id;
            }
        }
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    /**
     * Hash the normalized evidence inputs that explain a state calculation.
     *
     * @param array $evidence
     * @return string
     */
    private static function evidence_hash(array $evidence): string {
        $fingerprints = [];
        $index = 0;
        foreach ($evidence as $event) {
            $fingerprints[] = [
                'id' => (int)($event->id ?? 0),
                'index' => $index++,
                'timecreated' => (int)($event->timecreated ?? 0),
                'evidencetype' => (string)($event->evidencetype ?? ''),
                'sourceattempt' => (string)($event->sourceattempt ?? ''),
                'targettype' => (string)($event->targettype ?? ''),
                'targetid' => (int)($event->targetid ?? 0),
                'score' => round((float)($event->normalizedscore ?? 0), 5),
                'confidence' => round((float)($event->confidence ?? 0), 5),
                'strength' => (string)($event->evidencestrength ?? ''),
                'provenance' => (string)($event->provenance ?? ''),
                'rubrichash' => sha1((string)($event->rubricjson ?? '')),
            ];
        }
        usort($fingerprints, static function(array $a, array $b): int {
            return [$a['timecreated'], $a['id'], $a['index']] <=> [$b['timecreated'], $b['id'], $b['index']];
        });
        return hash('sha256', json_encode($fingerprints, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Clamp a value to the confidence range.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
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
            'policyversion' => self::POLICY_VERSION,
            'confidencepolicyversion' => self::CONFIDENCE_POLICY_VERSION,
            'evidenceids' => [],
            'evidenceidsjson' => '[]',
            'evidencehash' => self::evidence_hash([]),
            'calculatedtime' => time(),
            'status' => $targettype === 'kp' ? 'not_introduced' : ($targettype === 'up' ? 'not_observed' : 'not_started'),
            'confidence_model' => [
                'score' => 0.0,
                'label' => 'none',
                'policyversion' => self::CONFIDENCE_POLICY_VERSION,
                'inputs' => [
                    'meaningful_evidence_count' => 0,
                    'minimum_evidence_required' => self::minimum_evidence_required($targettype, false),
                ],
            ],
            'explanation' => ['evidence_count' => 0],
        ];
    }
}

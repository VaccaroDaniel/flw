<?php
// Topology roll-up service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Recalculates parent UP and competency states from child C-UP-KP states.
 */
final class rollup_engine {
    /** @var string Rule version stored on roll-up-managed parent states. */
    private const RULEVERSION = 'rollup-v1';

    /** @var array Ordered evidence strength values. */
    private const STRENGTH_ORDER = [
        'exposure' => 0,
        'recognition' => 1,
        'controlled_production' => 2,
        'guided_performance' => 3,
        'independent_performance' => 4,
        'transfer_performance' => 5,
    ];

    /**
     * Recalculate every roll-up target affected by one changed target.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param bool $syncmoodle
     * @return array
     */
    public static function recalculate_dependents(int $userid, string $targettype, int $targetid,
            bool $syncmoodle = true): array {
        global $DB;

        $summary = self::empty_summary($userid, $targettype, $targetid);

        if ($userid <= 0 || $targetid <= 0) {
            return $summary;
        }

        if ($targettype === 'kp') {
            $maps = $DB->get_records('flwcupkp_up_kp', ['kpid' => $targetid], 'sortorder ASC, id ASC');
            foreach ($maps as $map) {
                $upresult = self::recalculate_up($userid, (int)$map->upid);
                $summary['up'][] = $upresult;
                foreach (self::parent_competency_ids((int)$map->upid) as $competencyid) {
                    $summary['competency'][] = self::recalculate_competency($userid, $competencyid, $syncmoodle);
                }
            }
            return $summary;
        }

        if ($targettype === 'up') {
            $summary['up'][] = self::recalculate_up($userid, $targetid);
            foreach (self::parent_competency_ids($targetid) as $competencyid) {
                $summary['competency'][] = self::recalculate_competency($userid, $competencyid, $syncmoodle);
            }
            return $summary;
        }

        if ($targettype === 'competency') {
            $summary['competency'][] = self::recalculate_competency($userid, $targetid, $syncmoodle);
        }

        return $summary;
    }

    /**
     * Recalculate all parent states for users with evidence or existing states.
     *
     * @param int|null $userid
     * @param bool $syncmoodle
     * @param int $limit
     * @return array
     */
    public static function recalculate_all(?int $userid = null, bool $syncmoodle = true, int $limit = 0): array {
        global $DB;

        $userids = [];
        if ($userid !== null && $userid > 0) {
            $userids[] = $userid;
        } else {
            $records = $DB->get_records_sql(
                "SELECT userid FROM {flwcupkp_evidence}
                 UNION
                 SELECT userid FROM {flwcupkp_state}
              ORDER BY userid ASC",
                [],
                0,
                $limit > 0 ? $limit : 0
            );
            $userids = array_map('intval', array_keys($records));
        }

        $upids = array_map('intval', $DB->get_fieldset_select('flwcupkp_up_kp', 'DISTINCT upid', '1=1'));
        $compids = array_map('intval', $DB->get_fieldset_select('flwcupkp_comp_up', 'DISTINCT competencyid', '1=1'));

        $summary = [
            'users' => count($userids),
            'up' => [],
            'competency' => [],
            'moodle' => [],
        ];

        foreach ($userids as $uid) {
            $directupids = $DB->get_fieldset_select(
                'flwcupkp_evidence',
                'DISTINCT targetid',
                'userid = :userid AND targettype = :targettype',
                ['userid' => $uid, 'targettype' => 'up']
            );
            foreach (array_unique(array_merge($upids, array_map('intval', $directupids))) as $upid) {
                $summary['up'][] = self::recalculate_up($uid, (int)$upid);
            }

            $directcompids = $DB->get_fieldset_select(
                'flwcupkp_evidence',
                'DISTINCT targetid',
                'userid = :userid AND targettype = :targettype',
                ['userid' => $uid, 'targettype' => 'competency']
            );
            foreach (array_unique(array_merge($compids, array_map('intval', $directcompids))) as $competencyid) {
                $result = self::recalculate_competency($uid, (int)$competencyid, $syncmoodle);
                $summary['competency'][] = $result;
                if (!empty($result['moodle'])) {
                    $summary['moodle'][] = $result['moodle'];
                }
            }
        }

        return $summary;
    }

    /**
     * Recalculate one UP from direct UP evidence plus child KP states.
     *
     * @param int $userid
     * @param int $upid
     * @return array
     */
    public static function recalculate_up(int $userid, int $upid): array {
        global $DB;

        $maps = $DB->get_records('flwcupkp_up_kp', ['upid' => $upid], 'sortorder ASC, id ASC');
        $aggregate = self::aggregate_child_states($userid, $maps, 'kp', 'kpid', 'minreadiness', 0.70);
        $direct = self::direct_evidence_state($userid, 'up', $upid);
        $evidencecount = $aggregate['evidencecount'] + ($direct['state']['evidencecount'] ?? 0);

        if ($evidencecount <= 0 && !self::state_exists($userid, 'up', $upid)) {
            return self::skip_result('up', $upid, 'no_evidence');
        }

        $score = max($aggregate['score'], (float)($direct['state']['masteryscore'] ?? 0));
        $confidence = max($aggregate['confidence'], (float)($direct['state']['confidence'] ?? 0));
        $state = self::up_state_name($score);
        if (empty($direct) && !$aggregate['requiredmet'] && self::state_rank('up', $state) > self::state_rank('up', 'developing')) {
            $state = 'developing';
        }

        return self::store_state($userid, 'up', $upid, [
            'masteryscore' => round($score, 5),
            'masterystate' => $state,
            'confidence' => round($confidence, 5),
            'evidencecount' => $evidencecount,
            'lastevidence' => self::latest_time($aggregate['lastevidence'], $direct['state']['lastevidence'] ?? null),
            'lastsuccess' => self::latest_time($aggregate['lastsuccess'], $direct['state']['lastsuccess'] ?? null),
            'nextreview' => null,
            'ruleversion' => self::RULEVERSION,
            'explanation' => [
                'rollup_type' => 'up_from_kp',
                'child_score' => $aggregate['score'],
                'direct_score' => $direct['state']['masteryscore'] ?? null,
                'required_met' => $aggregate['requiredmet'],
                'required_met_count' => $aggregate['requiredmetcount'],
                'required_total' => $aggregate['requiredtotal'],
                'children' => $aggregate['children'],
            ],
        ]);
    }

    /**
     * Recalculate one competency from direct competency evidence plus child UP states.
     *
     * @param int $userid
     * @param int $competencyid
     * @param bool $syncmoodle
     * @return array
     */
    public static function recalculate_competency(int $userid, int $competencyid, bool $syncmoodle = true): array {
        global $DB;

        $competency = $DB->get_record('flwcupkp_comp', ['id' => $competencyid], '*', IGNORE_MISSING);
        if (!$competency) {
            return self::skip_result('competency', $competencyid, 'missing_competency');
        }

        $maps = $DB->get_records('flwcupkp_comp_up', ['competencyid' => $competencyid], 'sortorder ASC, id ASC');
        $aggregate = self::aggregate_child_states($userid, $maps, 'up', 'upid', 'minmastery', 0.70);
        $direct = self::direct_evidence_state($userid, 'competency', $competencyid);
        $evidencecount = $aggregate['evidencecount'] + ($direct['state']['evidencecount'] ?? 0);

        if ($evidencecount <= 0 && !self::state_exists($userid, 'competency', $competencyid)) {
            return self::skip_result('competency', $competencyid, 'no_evidence');
        }

        $rule = self::competency_rule($competency);
        $directcompcount = self::direct_event_count($direct['events'] ?? [], $rule);
        $directupcount = self::direct_up_event_count($userid, $maps, $rule);
        $minimumdirect = (int)($rule['minimum_direct_events'] ?? 1);
        $hasdirectcompetency = $directcompcount >= $minimumdirect;
        $topologyready = $hasdirectcompetency || $aggregate['requiredmet'];
        $hasdirectsignal = $hasdirectcompetency || ($topologyready && ($directcompcount + $directupcount) >= $minimumdirect);

        $score = max($aggregate['score'], (float)($direct['state']['masteryscore'] ?? 0));
        $confidence = max($aggregate['confidence'], (float)($direct['state']['confidence'] ?? 0));
        $state = self::competency_state_name($score, $topologyready, $hasdirectsignal);

        $result = self::store_state($userid, 'competency', $competencyid, [
            'masteryscore' => round($score, 5),
            'masterystate' => $state,
            'confidence' => round($confidence, 5),
            'evidencecount' => $evidencecount,
            'lastevidence' => self::latest_time($aggregate['lastevidence'], $direct['state']['lastevidence'] ?? null),
            'lastsuccess' => self::latest_time($aggregate['lastsuccess'], $direct['state']['lastsuccess'] ?? null),
            'nextreview' => null,
            'ruleversion' => self::RULEVERSION,
            'explanation' => [
                'rollup_type' => 'competency_from_up',
                'child_score' => $aggregate['score'],
                'direct_score' => $direct['state']['masteryscore'] ?? null,
                'required_met' => $aggregate['requiredmet'],
                'required_met_count' => $aggregate['requiredmetcount'],
                'required_total' => $aggregate['requiredtotal'],
                'minimum_direct_events' => $minimumdirect,
                'direct_competency_events' => $directcompcount,
                'direct_up_events' => $directupcount,
                'has_direct_signal' => $hasdirectsignal,
                'children' => $aggregate['children'],
            ],
        ]);

        if ($syncmoodle && in_array($result['status'], ['created', 'updated', 'unchanged'], true)) {
            $result['moodle'] = self::sync_moodle_if_ready($userid, $competencyid);
        }

        return $result;
    }

    /**
     * Build a weighted child-state aggregate.
     *
     * @param int $userid
     * @param array $maps
     * @param string $childtype
     * @param string $childfield
     * @param string $minimumfield
     * @param float $defaultminimum
     * @return array
     */
    private static function aggregate_child_states(int $userid, array $maps, string $childtype, string $childfield,
            string $minimumfield, float $defaultminimum): array {
        $weighted = 0.0;
        $totalweight = 0.0;
        $confidenceweighted = 0.0;
        $evidencecount = 0;
        $lastevidence = null;
        $lastsuccess = null;
        $requiredtotal = 0;
        $requiredmet = 0;
        $children = [];

        foreach ($maps as $map) {
            $childid = (int)$map->{$childfield};
            $state = self::state_for($userid, $childtype, $childid);
            $weight = max(0.00001, (float)($map->weight ?? 1));
            $score = $state ? (float)$state->masteryscore : 0.0;
            $confidence = $state ? (float)$state->confidence : 0.0;
            $minimum = $map->{$minimumfield} === null ? $defaultminimum : (float)$map->{$minimumfield};
            $isrequired = (string)($map->role ?? 'required') === 'required';

            $weighted += $score * $weight;
            $confidenceweighted += $confidence * $weight;
            $totalweight += $weight;
            if ($state) {
                $evidencecount += (int)$state->evidencecount;
                $lastevidence = self::latest_time($lastevidence, $state->lastevidence ?? null);
                $lastsuccess = self::latest_time($lastsuccess, $state->lastsuccess ?? null);
            }
            if ($isrequired) {
                $requiredtotal++;
                if ($state && (int)$state->evidencecount > 0 && $score >= $minimum) {
                    $requiredmet++;
                }
            }

            $children[] = [
                'targettype' => $childtype,
                'targetid' => $childid,
                'role' => (string)($map->role ?? 'required'),
                'weight' => $weight,
                'minimum' => $minimum,
                'score' => round($score, 5),
                'state' => $state->masterystate ?? null,
                'evidencecount' => $state ? (int)$state->evidencecount : 0,
            ];
        }

        return [
            'score' => $totalweight > 0 ? round($weighted / $totalweight, 5) : 0.0,
            'confidence' => $totalweight > 0 ? round($confidenceweighted / $totalweight, 5) : 0.0,
            'evidencecount' => $evidencecount,
            'lastevidence' => $lastevidence,
            'lastsuccess' => $lastsuccess,
            'requiredtotal' => $requiredtotal,
            'requiredmetcount' => $requiredmet,
            'requiredmet' => $requiredtotal === 0 || $requiredmet === $requiredtotal,
            'children' => $children,
        ];
    }

    /**
     * Recalculate direct evidence state without reading the stored state row.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return array|null
     */
    private static function direct_evidence_state(int $userid, string $targettype, int $targetid): ?array {
        global $DB;

        $events = $DB->get_records('flwcupkp_evidence', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], 'timecreated ASC, id ASC');
        if (!$events) {
            return null;
        }

        return [
            'state' => mastery_engine::calculate($targettype, array_values($events)),
            'events' => array_values($events),
        ];
    }

    /**
     * Store a parent state when changed, respecting manual overrides.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param array $state
     * @return array
     */
    private static function store_state(int $userid, string $targettype, int $targetid, array $state): array {
        $existing = self::state_for($userid, $targettype, $targetid);
        if ($existing && !empty($existing->manualoverride)) {
            return [
                'status' => 'skipped',
                'reason' => 'manual_override',
                'targettype' => $targettype,
                'targetid' => $targetid,
                'userid' => $userid,
            ];
        }

        $status = $existing ? 'updated' : 'created';
        if ($existing && self::same_state($existing, $state)) {
            return [
                'status' => 'unchanged',
                'targettype' => $targettype,
                'targetid' => $targetid,
                'userid' => $userid,
                'masterystate' => $state['masterystate'],
                'masteryscore' => $state['masteryscore'],
                'evidencecount' => $state['evidencecount'],
            ];
        }

        repository::upsert_state($userid, $targettype, $targetid, $state);
        repository::audit('rollup_state_' . $status, $targettype, $targetid, [
            'userid' => $userid,
            'masterystate' => $state['masterystate'],
            'masteryscore' => $state['masteryscore'],
            'confidence' => $state['confidence'],
            'evidencecount' => $state['evidencecount'],
            'ruleversion' => $state['ruleversion'],
            'explanation' => $state['explanation'] ?? [],
        ]);

        return [
            'status' => $status,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'userid' => $userid,
            'masterystate' => $state['masterystate'],
            'masteryscore' => $state['masteryscore'],
            'evidencecount' => $state['evidencecount'],
        ];
    }

    /**
     * Sync one competency state to native Moodle if write mode is ready.
     *
     * @param int $userid
     * @param int $competencyid
     * @return array|null
     */
    private static function sync_moodle_if_ready(int $userid, int $competencyid): ?array {
        if (!(bool)get_config('local_flwcupkp', 'enablesyncwrites')) {
            return null;
        }
        $readiness = curriculum_manager::sync_readiness();
        if (empty($readiness['readyforwrites'])) {
            return null;
        }

        try {
            return moodle_competency_writer::sync_competency_state($userid, $competencyid, false);
        } catch (\Throwable $e) {
            repository::audit('moodle_competency_rating_sync_failed', 'competency', $competencyid, [
                'userid' => $userid,
                'message' => $e->getMessage(),
                'source' => 'rollup_engine',
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Count direct performance events that satisfy a competency rule.
     *
     * @param array $events
     * @param array $rule
     * @return int
     */
    private static function direct_event_count(array $events, array $rule): int {
        $minimumscore = (float)($rule['minimum_score'] ?? 0.70);
        $requiredstrength = (string)($rule['required_strength'] ?? 'guided_performance');
        $count = 0;

        foreach ($events as $event) {
            if ((float)($event->normalizedscore ?? 0) < $minimumscore) {
                continue;
            }
            if (self::strength_rank((string)($event->evidencestrength ?? 'recognition')) <
                    self::strength_rank($requiredstrength)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Count direct UP performance events attached to mapped child UPs.
     *
     * @param int $userid
     * @param array $maps
     * @param array $rule
     * @return int
     */
    private static function direct_up_event_count(int $userid, array $maps, array $rule): int {
        global $DB;

        $count = 0;
        foreach ($maps as $map) {
            $events = $DB->get_records('flwcupkp_evidence', [
                'userid' => $userid,
                'targettype' => 'up',
                'targetid' => (int)$map->upid,
            ], 'timecreated ASC, id ASC');
            $count += self::direct_event_count(array_values($events), $rule);
        }
        return $count;
    }

    /**
     * Decode the competency's evidence rule.
     *
     * @param \stdClass $competency
     * @return array
     */
    private static function competency_rule(\stdClass $competency): array {
        $rule = json_decode((string)($competency->evidencerule ?? ''), true);
        return is_array($rule) ? $rule : [];
    }

    /**
     * Resolve parent competency IDs for a UP.
     *
     * @param int $upid
     * @return array
     */
    private static function parent_competency_ids(int $upid): array {
        global $DB;

        $maps = $DB->get_records('flwcupkp_comp_up', ['upid' => $upid], 'sortorder ASC, id ASC');
        return array_values(array_unique(array_map(static function($map): int {
            return (int)$map->competencyid;
        }, $maps)));
    }

    /**
     * Fetch one stored state.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function state_for(int $userid, string $targettype, int $targetid): ?\stdClass {
        global $DB;

        return $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * True when a stored state exists.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return bool
     */
    private static function state_exists(int $userid, string $targettype, int $targetid): bool {
        return self::state_for($userid, $targettype, $targetid) !== null;
    }

    /**
     * Check if a stored state is already current.
     *
     * @param \stdClass $existing
     * @param array $state
     * @return bool
     */
    private static function same_state(\stdClass $existing, array $state): bool {
        return (string)$existing->masterystate === (string)$state['masterystate'] &&
            abs((float)$existing->masteryscore - (float)$state['masteryscore']) < 0.00001 &&
            abs((float)$existing->confidence - (float)$state['confidence']) < 0.00001 &&
            (int)$existing->evidencecount === (int)$state['evidencecount'] &&
            (string)$existing->ruleversion === (string)$state['ruleversion'];
    }

    /**
     * Resolve a UP state from a score.
     *
     * @param float $score
     * @return string
     */
    private static function up_state_name(float $score): string {
        if ($score >= 0.90) {
            return 'transfer_ready';
        }
        if ($score >= 0.82) {
            return 'stable';
        }
        if ($score >= 0.70) {
            return 'demonstrated';
        }
        if ($score >= 0.45) {
            return 'developing';
        }
        if ($score >= 0.20) {
            return 'emerging';
        }
        return 'not_observed';
    }

    /**
     * Resolve a competency state from a score and evidence gate results.
     *
     * @param float $score
     * @param bool $topologyready
     * @param bool $hasdirectsignal
     * @return string
     */
    private static function competency_state_name(float $score, bool $topologyready, bool $hasdirectsignal): string {
        if (!$topologyready) {
            return $score >= 0.35 ? 'developing' : 'not_started';
        }
        if (!$hasdirectsignal) {
            if ($score >= 0.70) {
                return 'provisionally_achieved';
            }
            return $score >= 0.35 ? 'developing' : 'not_started';
        }
        if ($score >= 0.90) {
            return 'sustained';
        }
        if ($score >= 0.82) {
            return 'achieved';
        }
        if ($score >= 0.70) {
            return 'provisionally_achieved';
        }
        return $score >= 0.35 ? 'developing' : 'not_started';
    }

    /**
     * Numeric state rank for capping.
     *
     * @param string $targettype
     * @param string $state
     * @return int
     */
    private static function state_rank(string $targettype, string $state): int {
        $states = [
            'up' => ['not_observed', 'emerging', 'developing', 'demonstrated', 'stable', 'transfer_ready'],
        ];
        return array_search($state, $states[$targettype] ?? [], true) ?: 0;
    }

    /**
     * Rank evidence strength.
     *
     * @param string $strength
     * @return int
     */
    private static function strength_rank(string $strength): int {
        return self::STRENGTH_ORDER[$strength] ?? 1;
    }

    /**
     * Latest nonempty timestamp.
     *
     * @param mixed $left
     * @param mixed $right
     * @return int|null
     */
    private static function latest_time($left, $right): ?int {
        $left = $left === null ? 0 : (int)$left;
        $right = $right === null ? 0 : (int)$right;
        $time = max($left, $right);
        return $time > 0 ? $time : null;
    }

    /**
     * Build an empty dependent summary.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return array
     */
    private static function empty_summary(int $userid, string $targettype, int $targetid): array {
        return [
            'userid' => $userid,
            'source' => [
                'targettype' => $targettype,
                'targetid' => $targetid,
            ],
            'up' => [],
            'competency' => [],
        ];
    }

    /**
     * Build a skipped result.
     *
     * @param string $targettype
     * @param int $targetid
     * @param string $reason
     * @return array
     */
    private static function skip_result(string $targettype, int $targetid, string $reason): array {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ];
    }
}

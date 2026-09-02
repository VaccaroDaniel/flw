<?php
// Program 3 History V1 consumer contract for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only Program 3 preflight for consuming local_flwhistory evidence facts.
 */
final class history_v1_consumer_contract {
    /** Program 3 preflight gate identifier. */
    public const GATE = 'P3_A0';

    /** Frozen Program 2 downstream contract required by Program 3. */
    public const REQUIRED_CONTRACT = 'FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1';

    /** Normal production source rule for Program 3 learner intelligence. */
    public const CONSUMPTION_RULE = 'use_history_v1_adapter_not_raw_moodle_logs';

    /** @var array Required History V1 fact families. */
    private const REQUIRED_FACT_TYPES = [
        'source_events',
        'attempts',
        'grades',
        'completion',
        'placement',
        'content_identities',
    ];

    /** @var array Required History V1 downstream guarantees. */
    private const REQUIRED_GUARANTEES = [
        'read_only',
        'bounded_queries',
        'stable_source_keys',
        'coverage_included',
        'no_adaptive_policy',
        'no_cupkp_mastery_mutation',
    ];

    /**
     * Report whether Program 3 can consume the frozen History V1 evidence surface.
     *
     * This method is intentionally read-only. It may inspect bounded History V1
     * adapter payloads, but it must not create C-UP-KP evidence, learner states,
     * recommendations, or audit rows.
     *
     * @param int $courseid Optional Moodle course ID for bounded sample reads.
     * @param int $samplelimit Optional per-family sample size.
     * @return array
     */
    public static function contract_status(int $courseid = 0, int $samplelimit = 1): array {
        $status = [
            'type' => 'Program3HistoryV1ConsumptionStatus',
            'gate' => self::GATE,
            'consumer' => 'local_flwcupkp',
            'requiredcontract' => self::REQUIRED_CONTRACT,
            'normal_source_rule' => self::CONSUMPTION_RULE,
            'historypluginavailable' => false,
            'contractavailable' => false,
            'contract' => null,
            'checks' => [],
            'sample' => [],
            'plannedpaths' => self::planned_consumption_paths(),
            'boundary' => self::normal_source_boundary(),
            'outofscope' => self::out_of_scope(),
            'findings' => [],
            'status' => 'blocked',
        ];

        $adapterclass = '\\local_flwhistory\\local\\evidence_source_adapter';
        if (!class_exists($adapterclass)) {
            $status['findings'][] = [
                'severity' => 'blocker',
                'code' => 'history_adapter_missing',
                'message' => 'local_flwhistory evidence_source_adapter is not available.',
            ];
            return $status;
        }

        $status['historypluginavailable'] = true;
        try {
            $contract = $adapterclass::contract();
        } catch (\Throwable $e) {
            $status['findings'][] = [
                'severity' => 'blocker',
                'code' => 'history_contract_unreadable',
                'message' => $e->getMessage(),
            ];
            return $status;
        }

        $status['contractavailable'] = true;
        $status['contract'] = $contract;
        $status['checks'] = self::contract_checks($contract);

        foreach ($status['checks'] as $check) {
            if (empty($check['pass'])) {
                $status['findings'][] = [
                    'severity' => 'blocker',
                    'code' => $check['code'],
                    'message' => $check['message'],
                ];
            }
        }

        if ($courseid > 0) {
            $status['sample'] = self::sample_course_facts($courseid, $samplelimit, $adapterclass);
            foreach ($status['sample'] as $facttype => $sample) {
                if (!empty($sample['error'])) {
                    $status['findings'][] = [
                        'severity' => 'warning',
                        'code' => 'history_sample_' . $facttype . '_failed',
                        'message' => $sample['error'],
                    ];
                }
            }
        }

        $blocking = array_filter($status['findings'], static function(array $finding): bool {
            return ($finding['severity'] ?? '') === 'blocker';
        });
        if ($blocking) {
            $status['status'] = 'blocked';
        } else if ($status['findings']) {
            $status['status'] = 'ready_with_findings';
        } else {
            $status['status'] = 'ready';
        }

        return $status;
    }

    /**
     * Planned Program 3 lanes that consume History V1 facts in later gates.
     *
     * @return array
     */
    public static function planned_consumption_paths(): array {
        return [
            'source_events' => [
                'source' => 'local_flwhistory evidence_source_adapter::source_events_for_course',
                'future_gate' => 'E2',
                'use' => 'Preserve provenance, source identity, coverage, and unresolved mapping state.',
                'decision_boundary' => 'Does not create mastery by itself.',
            ],
            'attempts' => [
                'source' => 'local_flwhistory evidence_source_adapter::attempts_for_course',
                'implemented_gate' => 'E1',
                'future_gate' => 'E2',
                'use' => 'Convert mapped attempts into policy-versioned C-UP-KP evidence events.',
                'decision_boundary' => 'Latest, best, and official grade concepts remain separate.',
            ],
            'grades' => [
                'source' => 'local_flwhistory evidence_source_adapter::grades_for_course',
                'future_gate' => 'E2',
                'use' => 'Support grade-linked evidence and learner evaluation summaries.',
                'decision_boundary' => 'Grade changes do not overwrite attempt evidence semantics.',
            ],
            'completion' => [
                'source' => 'local_flwhistory evidence_source_adapter::completions_for_course',
                'implemented_gate' => 'E1',
                'future_gate' => 'E2',
                'use' => 'Use completion only when the object mapping says completion is pedagogically valid evidence.',
                'decision_boundary' => 'Completion is not equivalent to mastery.',
            ],
            'placement' => [
                'source' => 'local_flwhistory evidence_source_adapter::placements_for_course',
                'future_gate' => 'A2',
                'use' => 'Seed cold-start learner level, diagnostics, and path initialization.',
                'decision_boundary' => 'Placement recommends a starting point, not final mastery.',
            ],
            'content_identities' => [
                'source' => 'local_flwhistory evidence_source_adapter::content_identities_for_course',
                'future_gate' => 'C3/A4B',
                'use' => 'Resolve Program 1 course, section, cmid, activity, assessment, and question identities.',
                'decision_boundary' => 'Unresolved identities become unresolved facts, not fabricated mappings.',
            ],
        ];
    }

    /**
     * Program 3 source-history boundary.
     *
     * @return array
     */
    public static function normal_source_boundary(): array {
        return [
            'normal_source_history_input' => self::REQUIRED_CONTRACT,
            'normal_adapter' => '\\local_flwhistory\\local\\evidence_source_adapter',
            'normal_rule' => self::CONSUMPTION_RULE,
            'raw_moodle_log_access' => 'diagnostic_only',
            'legacy_direct_observers' => 'keep_until_replaced_by_history_v1_reprocessing_gate',
            'state_changes_allowed_in_gate' => false,
        ];
    }

    /**
     * Work explicitly outside Program 3 Gate A0.
     *
     * @return array
     */
    public static function out_of_scope(): array {
        return [
            'canonical_domain_model_changes',
            'history_to_cupkp_evidence_writes',
            'mastery_policy_changes',
            'adaptive_decision_policy',
            'student_path_generation',
            'teacher_override_changes',
            'raw_moodle_log_scraping',
        ];
    }

    /**
     * Build deterministic contract checks.
     *
     * @param array $contract
     * @return array
     */
    private static function contract_checks(array $contract): array {
        $facttypes = array_map('strval', $contract['facttypes'] ?? []);
        $guarantees = is_array($contract['guarantees'] ?? null) ? $contract['guarantees'] : [];
        $missingfacts = array_values(array_diff(self::REQUIRED_FACT_TYPES, $facttypes));
        $missingguarantees = self::missing_guarantees($guarantees);

        return [
            [
                'code' => 'contract_version',
                'pass' => ($contract['version'] ?? '') === self::REQUIRED_CONTRACT,
                'message' => 'History contract version must be ' . self::REQUIRED_CONTRACT . '.',
                'actual' => $contract['version'] ?? null,
            ],
            [
                'code' => 'normalization_policy',
                'pass' => !empty($contract['normpolicyversion']),
                'message' => 'History contract must expose a normalization policy version.',
                'actual' => $contract['normpolicyversion'] ?? null,
            ],
            [
                'code' => 'required_fact_types',
                'pass' => empty($missingfacts),
                'message' => 'History contract must expose all Program 3 required fact families.',
                'missing' => $missingfacts,
            ],
            [
                'code' => 'required_guarantees',
                'pass' => empty($missingguarantees),
                'message' => 'History contract must expose read-only bounded-source guarantees.',
                'missing' => $missingguarantees,
            ],
        ];
    }

    /**
     * Return required guarantees missing from a contract payload.
     *
     * @param array $guarantees
     * @return array
     */
    private static function missing_guarantees(array $guarantees): array {
        $missing = [];
        foreach (self::REQUIRED_GUARANTEES as $guarantee) {
            if (empty($guarantees[$guarantee])) {
                $missing[] = $guarantee;
            }
        }
        return $missing;
    }

    /**
     * Read bounded samples from History V1 for a course.
     *
     * @param int $courseid
     * @param int $samplelimit
     * @param string $adapterclass
     * @return array
     */
    private static function sample_course_facts(int $courseid, int $samplelimit, string $adapterclass): array {
        $samplelimit = max(1, $samplelimit);
        $methods = [
            'source_events' => 'source_events_for_course',
            'attempts' => 'attempts_for_course',
            'grades' => 'grades_for_course',
            'completion' => 'completions_for_course',
            'placement' => 'placements_for_course',
            'content_identities' => 'content_identities_for_course',
        ];

        $samples = [];
        foreach ($methods as $facttype => $method) {
            try {
                $payload = $adapterclass::$method($courseid, $samplelimit, 0);
                $samples[$facttype] = [
                    'facttable' => $payload['facttable'] ?? null,
                    'contract' => $payload['contract'] ?? null,
                    'pagination' => $payload['pagination'] ?? null,
                    'recordcount' => count($payload['records'] ?? []),
                ];
            } catch (\Throwable $e) {
                $samples[$facttype] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $samples;
    }
}

<?php
// Program 3 Gate C5 Foundation Freeze V1.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes the C-UP-KP foundation surface for evidence, mastery, adaptive, and UX consumers.
 */
final class foundation_v1_contract {
    /** Program 3 Foundation Freeze gate. */
    public const GATE = 'P3_C5';

    /** Frozen Foundation V1 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_FOUNDATION_V1';

    /** Stable curriculum contract identifier recorded by C5. */
    public const CURRICULUM_CONTRACT_VERSION = 'FLW_CUPKP_CURRICULUM_CONTRACT_V1';

    /** @var array Canonical implementation ownership for Foundation V1. */
    private const AUTHORITATIVE_IMPLEMENTATIONS = [
        'competency_identification' => [
            'class' => '\\local_flwcupkp\\local\\canonical_domain_model',
            'tables' => ['flwcupkp_comp'],
            'methods' => ['contract', 'target_types', 'freeze_status'],
        ],
        'kp_identification' => [
            'class' => '\\local_flwcupkp\\local\\canonical_domain_model',
            'tables' => ['flwcupkp_kp'],
            'methods' => ['contract', 'kp_domains', 'freeze_status'],
        ],
        'up_identification' => [
            'class' => '\\local_flwcupkp\\local\\canonical_domain_model',
            'tables' => ['flwcupkp_up'],
            'methods' => ['contract', 'target_types', 'freeze_status'],
        ],
        'ontology_rules' => [
            'class' => '\\local_flwcupkp\\local\\ontology_boundary',
            'tables' => [],
            'methods' => ['contract', 'validate_package', 'boundary_status'],
        ],
        'relationships_and_prerequisites' => [
            'class' => '\\local_flwcupkp\\local\\relationship_graph_contract',
            'tables' => ['flwcupkp_comp_up', 'flwcupkp_up_kp', 'flwcupkp_kp_prereq'],
            'methods' => ['contract', 'adjacency', 'dependencies_for_target', 'where_used', 'graph_status'],
        ],
        'content_mappings' => [
            'class' => '\\local_flwcupkp\\local\\content_evidence_mapping_contract',
            'tables' => ['flwcupkp_object', 'flwcupkp_object_map'],
            'methods' => ['contract', 'identity_from_object', 'content_mapping_status'],
        ],
        'evidence_mappings_and_semantics' => [
            'class' => '\\local_flwcupkp\\local\\evidence_semantics_quality_contract',
            'tables' => ['flwcupkp_evidence'],
            'methods' => ['contract', 'semantics_for_evidence', 'source_key_from_evidence', 'evidence_semantics_status'],
        ],
        'evidence_provenance_and_policy' => [
            'class' => '\\local_flwcupkp\\local\\evidence_guard',
            'tables' => ['flwcupkp_evidence', 'flwcupkp_audit'],
            'methods' => ['target_table', 'normalize_evidence', 'assert_object_map_can_record'],
        ],
        'lifecycle_versioning_governance' => [
            'class' => '\\local_flwcupkp\\local\\lifecycle_governance_contract',
            'tables' => ['flwcupkp_framework', 'flwcupkp_comp', 'flwcupkp_up', 'flwcupkp_kp'],
            'methods' => ['contract', 'lifecycle_statuses', 'governance_status'],
        ],
        'validation' => [
            'class' => '\\local_flwcupkp\\local\\validator',
            'tables' => [],
            'methods' => ['validate_package'],
        ],
    ];

    /**
     * Return the frozen C5 Foundation V1 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpFoundationV1Contract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'status' => 'frozen',
            'recorded_versions' => self::version_record(),
            'component_contracts' => [
                'history_v1' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'c1_domain_model' => canonical_domain_model::CONTRACT_VERSION,
                'c1b_ontology_boundary' => ontology_boundary::CONTRACT_VERSION,
                'c2_relationship_graph' => relationship_graph_contract::CONTRACT_VERSION,
                'c3_content_evidence_mapping' => content_evidence_mapping_contract::CONTRACT_VERSION,
                'c3b_evidence_semantics_quality' => evidence_semantics_quality_contract::CONTRACT_VERSION,
                'c4_lifecycle_governance' => lifecycle_governance_contract::CONTRACT_VERSION,
            ],
            'authoritative_implementations' => self::AUTHORITATIVE_IMPLEMENTATIONS,
            'foundation_invariants' => [
                'competency_identity' => 'Competencies are identified by flwcupkp_comp.id plus stable externalid; semantic meaning is C1 integrated ability.',
                'kp_identity' => 'Knowledge Points are identified by flwcupkp_kp.id plus stable externalid; semantic meaning is C1 required knowledge.',
                'up_identity' => 'Use Points are identified by flwcupkp_up.id plus stable externalid; semantic meaning is C1 observable use or demonstration.',
                'relationship_queries' => 'Relationship traversal goes through relationship_graph_contract APIs, not ad hoc graph SQL in consumers.',
                'prerequisite_queries' => 'Hard prerequisite semantics are C2 REQUIRES edges with mandatory requirement.',
                'content_mapping_queries' => 'Learning-object mappings use stable Program 1 identity facts and object-map role semantics from C3.',
                'evidence_representation' => 'Evidence carries C3B semantics, History V1 source keys, result state, quality dimensions, and evidence policy version metadata.',
                'deprecated_records' => 'Deprecated or archived records remain explainable for history; new active mappings must follow C4 governance.',
                'version_behavior' => 'Published semantic rows are immutable; new versions are cloned or revised and linked through explicit lifecycle/replacement rules.',
                'consumer_boundary' => 'Evidence, mastery, adaptive, and UX consumers may read Foundation V1 contracts but must not add adaptive logic in C5.',
            ],
            'adaptive_api_contract' => self::adaptive_api_contract(),
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'does_not_do' => [
                'adaptive_path_selection',
                'learning_goal_model',
                'mastery_policy_change',
                'history_v1_reprocessing_writes',
                'raw_moodle_log_scraping',
            ],
        ];
    }

    /**
     * Return the required Foundation V1 production identifiers.
     *
     * @return array
     */
    public static function version_record(): array {
        return [
            'curriculum_contract_version' => self::CURRICULUM_CONTRACT_VERSION,
            'relationship_contract_version' => relationship_graph_contract::CONTRACT_VERSION,
            'evidence_policy_version' => evidence_semantics_quality_contract::EVIDENCE_POLICY_VERSION,
            'foundation_contract_version' => self::CONTRACT_VERSION,
            'history_contract_version' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'content_mapping_contract_version' => content_evidence_mapping_contract::CONTRACT_VERSION,
            'evidence_semantics_contract_version' => evidence_semantics_quality_contract::CONTRACT_VERSION,
            'lifecycle_governance_contract_version' => lifecycle_governance_contract::CONTRACT_VERSION,
        ];
    }

    /**
     * Return the read-only APIs downstream adaptive work may call after C5.
     *
     * @return array
     */
    public static function adaptive_api_contract(): array {
        return [
            'type' => 'CupkpAdaptiveFoundationApiContract',
            'version' => self::CONTRACT_VERSION,
            'may_rely_on' => [
                'competency_identification',
                'kp_identification',
                'up_identification',
                'relationship_queries',
                'prerequisite_queries',
                'content_mapping_queries',
                'evidence_representation',
                'deprecated_record_behavior',
                'version_behavior',
                'read_only_foundation_status',
            ],
            'allowed_read_apis' => [
                'canonical_domain_model::contract',
                'canonical_domain_model::target_types',
                'canonical_domain_model::kp_domains',
                'ontology_boundary::contract',
                'relationship_graph_contract::contract',
                'relationship_graph_contract::adjacency',
                'relationship_graph_contract::dependencies_for_target',
                'relationship_graph_contract::where_used',
                'content_evidence_mapping_contract::contract',
                'content_evidence_mapping_contract::identity_from_object',
                'content_evidence_mapping_contract::content_mapping_status',
                'evidence_semantics_quality_contract::contract',
                'evidence_semantics_quality_contract::semantics_for_evidence',
                'evidence_semantics_quality_contract::source_key_from_evidence',
                'evidence_semantics_quality_contract::quality_profile',
                'lifecycle_governance_contract::contract',
                'lifecycle_governance_contract::lifecycle_statuses',
                'foundation_v1_contract::contract',
                'foundation_v1_contract::version_record',
                'foundation_v1_contract::foundation_status',
            ],
            'forbidden_until_later_gates' => [
                'raw Moodle log reads as normal learner-intelligence input',
                'adaptive path ranking or selection',
                'new mastery-state policy writes',
                'History V1 evidence reprocessing writes',
                'learning-goal creation',
            ],
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Verify the Foundation V1 authoritative implementation registry.
     *
     * @return array
     */
    public static function authoritative_implementation_status(): array {
        global $DB;

        $dbman = $DB->get_manager();
        $areas = [];
        $findings = [];

        foreach (self::AUTHORITATIVE_IMPLEMENTATIONS as $area => $implementation) {
            $class = (string)$implementation['class'];
            $classpresent = class_exists($class);
            $tablechecks = [];
            $methodchecks = [];

            foreach ($implementation['tables'] as $table) {
                $present = $dbman->table_exists(new \xmldb_table($table));
                $tablechecks[$table] = $present;
                if (!$present) {
                    $findings[] = self::status_finding('blocker', 'missing_authoritative_table',
                        'Missing table for ' . $area . ': ' . $table);
                }
            }

            if (!$classpresent) {
                $findings[] = self::status_finding('blocker', 'missing_authoritative_class',
                    'Missing authoritative class for ' . $area . ': ' . $class);
            }

            foreach ($implementation['methods'] as $method) {
                $present = $classpresent && method_exists($class, $method);
                $methodchecks[$method] = $present;
                if (!$present) {
                    $findings[] = self::status_finding('error', 'missing_authoritative_method',
                        'Missing authoritative method for ' . $area . ': ' . $class . '::' . $method);
                }
            }

            $areas[$area] = [
                'class' => $class,
                'class_present' => $classpresent,
                'tables' => $tablechecks,
                'methods' => $methodchecks,
                'valid' => $classpresent && !in_array(false, $tablechecks, true) &&
                    !in_array(false, $methodchecks, true),
            ];
        }

        $legacyduplicates = self::legacy_duplicate_status();
        foreach ($legacyduplicates['findings'] as $finding) {
            $findings[] = $finding;
        }

        return [
            'type' => 'CupkpFoundationV1AuthoritativeImplementationStatus',
            'valid' => empty(array_filter($findings, [self::class, 'is_blocker_or_error'])),
            'areas' => $areas,
            'legacy_duplicate_checks' => $legacyduplicates['checks'],
            'findings' => $findings,
        ];
    }

    /**
     * Read-only Foundation V1 readiness status.
     *
     * @param int $courseid Optional Moodle course ID.
     * @param string $unitcode Optional unit code.
     * @param int $frameworkid Optional C-UP-KP framework ID.
     * @param int $limit Maximum records per sampled status check.
     * @return array
     */
    public static function foundation_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = max(1, min(200, $limit));
        $checks = [
            'history_v1' => self::safe_status_call('history_v1', ['ready', 'ready_with_findings'],
                static function() use ($courseid): array {
                    return history_v1_consumer_contract::contract_status($courseid, 1);
                }),
            'c1_domain_model' => self::safe_status_call('c1_domain_model', ['frozen'],
                static function() use ($courseid): array {
                    return canonical_domain_model::freeze_status($courseid);
                }),
            'c1b_ontology_boundary' => self::safe_status_call('c1b_ontology_boundary', ['guarded'],
                static function() use ($courseid, $frameworkid, $limit): array {
                    return ontology_boundary::boundary_status($courseid, $frameworkid, min($limit, 50));
                }),
            'c2_relationship_graph' => self::safe_status_call('c2_relationship_graph', ['frozen'],
                static function() use ($courseid, $frameworkid, $limit): array {
                    return relationship_graph_contract::graph_status($courseid, $frameworkid, $limit);
                }),
            'c3_content_evidence_mapping' => self::safe_status_call('c3_content_evidence_mapping', ['frozen'],
                static function() use ($courseid, $unitcode, $limit): array {
                    return content_evidence_mapping_contract::content_mapping_status($courseid, $unitcode, $limit);
                }),
            'c3b_evidence_semantics_quality' => self::safe_status_call('c3b_evidence_semantics_quality', ['frozen'],
                static function() use ($courseid, $unitcode, $limit): array {
                    return evidence_semantics_quality_contract::evidence_semantics_status($courseid, $unitcode, $limit);
                }),
            'c4_lifecycle_governance' => self::safe_status_call('c4_lifecycle_governance', ['frozen'],
                static function() use ($courseid, $frameworkid, $unitcode, $limit): array {
                    return lifecycle_governance_contract::governance_status($courseid, $frameworkid, $unitcode, $limit);
                }),
            'repository_audit' => self::safe_status_call('repository_audit',
                [
                    'ready_for_cm3',
                    'ready_for_cm3_with_findings',
                    'ready_for_cm4',
                    'ready_for_cm4_with_findings',
                    'ready_for_e1',
                    'ready_for_e1_with_findings',
                    'ready_for_e2',
                    'ready_for_e2_with_findings',
                    'ready_for_e3',
                    'ready_for_e3_with_findings',
                    'ready_for_a1',
                    'ready_for_a1_with_findings',
                    'ready_for_a2',
                    'ready_for_a2_with_findings',
                    'ready_for_a3',
                    'ready_for_a3_with_findings',
                    'ready_for_a4',
                    'ready_for_a4_with_findings',
                    'ready_for_a4b',
                    'ready_for_a4b_with_findings',
                    'ready_for_a5',
                    'ready_for_a5_with_findings',
                    'ready_for_a5b',
                    'ready_for_a5b_with_findings',
                    'ready_for_a5c',
                    'ready_for_a5c_with_findings',
                    'ready_for_ux1',
                    'ready_for_ux1_with_findings',
                    'ready_for_ux2',
                    'ready_for_ux2_with_findings',
                    'ready_for_ux3',
                    'ready_for_ux3_with_findings',
                    'ready_for_f1',
                    'ready_for_f1_with_findings',
                    'f1_validation_available',
                    'f1_validation_available_with_findings',
                    'ready_for_cm2',
                    'ready_for_cm2_with_findings',
                    'ready_for_cm1',
                    'ready_for_cm1_with_findings',
                    'ready_for_c5b',
                    'ready_for_c5b_with_findings',
                    'ready_for_c5',
                    'ready_for_c5_with_findings',
                ],
                static function() use ($courseid): array {
                    return program3_repository_audit::audit_status($courseid);
                }),
        ];
        $checks['authoritative_implementations'] = self::authoritative_implementation_status();

        $findings = [];
        foreach ($checks as $key => $check) {
            if (array_key_exists('allowed_statuses', $check) && !in_array((string)($check['status'] ?? ''),
                    $check['allowed_statuses'], true)) {
                $findings[] = [
                    'severity' => 'BLOCKER',
                    'source' => $key,
                    'code' => $key . '_not_ready',
                    'message' => 'Foundation V1 requires ' . $key . ' status to be one of: ' .
                        implode(', ', $check['allowed_statuses']) . '. Actual: ' . (string)($check['status'] ?? 'unknown') . '.',
                ];
            }
            foreach (($check['findings'] ?? []) as $finding) {
                $findings[] = self::normalize_finding($key, $finding);
            }
        }

        $unresolved = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['BLOCKER', 'HIGH'], true);
        });

        return [
            'type' => 'CupkpFoundationV1Status',
            'gate' => self::GATE,
            'status' => $unresolved ? 'blocked' : 'frozen',
            'contract' => self::contract(),
            'versions' => self::version_record(),
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'checks' => $checks,
            'migration_readiness' => self::migration_readiness($checks),
            'findings' => array_values($findings),
            'unresolved_blocker_high_count' => count($unresolved),
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Run a dependency status call and wrap exceptions as blocker findings.
     *
     * @param string $key
     * @param array $allowed
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(string $key, array $allowed, callable $callback): array {
        try {
            $status = $callback();
            if (!is_array($status)) {
                throw new \coding_exception($key . ' status did not return an array.');
            }
        } catch (\Throwable $e) {
            $status = [
                'type' => 'CupkpFoundationDependencyStatus',
                'status' => 'blocked',
                'findings' => [
                    self::status_finding('blocker', $key . '_unreadable', $e->getMessage()),
                ],
            ];
        }
        $status['allowed_statuses'] = $allowed;
        return $status;
    }

    /**
     * Return read-only migration readiness checks derived from the frozen dependencies.
     *
     * @param array $checks
     * @return array
     */
    private static function migration_readiness(array $checks): array {
        $c3sample = $checks['c3_content_evidence_mapping']['sample'] ?? [];
        $c3bsample = $checks['c3b_evidence_semantics_quality']['sample'] ?? [];
        $c4sample = $checks['c4_lifecycle_governance']['sample'] ?? [];
        $history = $checks['history_v1'] ?? [];

        return [
            'type' => 'CupkpFoundationV1MigrationReadiness',
            'read_only' => true,
            'checks' => [
                [
                    'code' => 'history_v1_source_boundary',
                    'status' => (($history['status'] ?? '') === 'blocked') ? 'blocked' : 'ready',
                    'message' => 'History V1 remains the only normal source-history input.',
                ],
                [
                    'code' => 'content_identity_mapping',
                    'status' => (($checks['c3_content_evidence_mapping']['status'] ?? '') === 'blocked') ?
                        'blocked' : 'ready',
                    'objects_sampled' => (int)($c3sample['objects'] ?? 0),
                    'stable_identity_objects' => (int)($c3sample['stable_identity_objects'] ?? 0),
                ],
                [
                    'code' => 'evidence_semantics_metadata',
                    'status' => (($checks['c3b_evidence_semantics_quality']['status'] ?? '') === 'blocked') ?
                        'blocked' : 'ready',
                    'evidence_rows_sampled' => (int)($c3bsample['evidence_rows'] ?? 0),
                    'with_c3b_semantics' => (int)($c3bsample['with_c3b_semantics'] ?? 0),
                    'legacy_without_c3b_semantics' => (int)($c3bsample['legacy_without_c3b_semantics'] ?? 0),
                ],
                [
                    'code' => 'lifecycle_versioning_governance',
                    'status' => (($checks['c4_lifecycle_governance']['status'] ?? '') === 'blocked') ?
                        'blocked' : 'ready',
                    'published_targets_missing_evidence_routes' =>
                        (int)($c4sample['published_targets_missing_evidence_routes'] ?? 0),
                    'invalid_published_states' => (int)($c4sample['invalid_published_states'] ?? 0),
                ],
            ],
        ];
    }

    /**
     * Detect safe duplicate implementation patterns that must stay resolved for C5.
     *
     * @return array
     */
    private static function legacy_duplicate_status(): array {
        $checks = [
            'mandatory_cycle_detection_centralized' =>
                !method_exists('\\local_flwcupkp\\local\\validator', 'find_mandatory_prereq_cycle') &&
                method_exists('\\local_flwcupkp\\local\\relationship_graph_contract', 'detect_hard_prerequisite_cycles'),
            'evidence_normalization_centralized' =>
                method_exists('\\local_flwcupkp\\local\\evidence_guard', 'normalize_evidence') &&
                method_exists('\\local_flwcupkp\\local\\evidence_semantics_quality_contract', 'semantics_for_evidence'),
        ];

        $findings = [];
        foreach ($checks as $code => $pass) {
            if (!$pass) {
                $findings[] = self::status_finding('error', 'duplicate_implementation_not_resolved',
                    'Foundation V1 duplicate implementation check failed: ' . $code);
            }
        }

        return [
            'checks' => $checks,
            'findings' => $findings,
        ];
    }

    /**
     * Normalize dependency severities to the C5 BLOCKER/HIGH/MEDIUM/INFO scale.
     *
     * @param string $source
     * @param array $finding
     * @return array
     */
    private static function normalize_finding(string $source, array $finding): array {
        $original = (string)($finding['severity'] ?? 'info');
        $lower = strtolower($original);
        if ($lower === 'blocker' || $original === 'BLOCKER') {
            $severity = 'BLOCKER';
        } else if ($lower === 'error' || $original === 'HIGH') {
            $severity = 'HIGH';
        } else if ($lower === 'warning' || $original === 'MEDIUM') {
            $severity = 'MEDIUM';
        } else {
            $severity = 'INFO';
        }

        return [
            'severity' => $severity,
            'source' => $finding['source'] ?? $source,
            'code' => $finding['code'] ?? 'foundation_dependency_finding',
            'message' => $finding['message'] ?? 'Foundation dependency finding.',
            'original_severity' => $original,
        ];
    }

    /**
     * Build an internal dependency finding.
     *
     * @param string $severity
     * @param string $code
     * @param string $message
     * @return array
     */
    private static function status_finding(string $severity, string $code, string $message): array {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * Whether a lower-case dependency finding blocks Foundation V1.
     *
     * @param array $finding
     * @return bool
     */
    private static function is_blocker_or_error(array $finding): bool {
        return in_array(strtolower((string)($finding['severity'] ?? '')), ['blocker', 'error'], true);
    }
}

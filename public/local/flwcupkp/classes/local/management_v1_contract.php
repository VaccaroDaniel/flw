<?php
// Program 3 Gate CM4 Management V1 freeze contract.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes the operational C-UP-KP management surface for production consumers.
 */
final class management_v1_contract {
    /** Program 3 Management V1 freeze gate. */
    public const GATE = 'P3_CM4';

    /** Frozen Management V1 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_MANAGEMENT_V1';

    /** @var array Required CM4 pass criteria. */
    private const CRITERIA = [
        'ontology_frozen',
        'graph_semantics_frozen',
        'management_crud_works',
        'permissions_work',
        'where_used_works',
        'coverage_validation_works',
        'bulk_management_safe',
        'governance_lifecycle_works',
        'program1_content_mappings_resolve',
        'program2_history_contract_ready',
    ];

    /**
     * Return the frozen Management V1 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpManagementV1Contract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                history_v1_consumer_contract::REQUIRED_CONTRACT,
                canonical_domain_model::CONTRACT_VERSION,
                ontology_boundary::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                content_evidence_mapping_contract::CONTRACT_VERSION,
                evidence_semantics_quality_contract::CONTRACT_VERSION,
                lifecycle_governance_contract::CONTRACT_VERSION,
                foundation_v1_contract::CONTRACT_VERSION,
                core_curriculum_manager::CONTRACT_VERSION,
                relationship_where_used_manager::CONTRACT_VERSION,
                coverage_bulk_governance_manager::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'pass_criteria' => self::CRITERIA,
            'management_surfaces' => [
                'cm1_operational_authoring',
                'cm2_guarded_relationship_editing',
                'cm3_bulk_coverage_governance',
                'cm4_read_only_consumer_snapshot',
            ],
            'allowed_read_apis' => [
                'management_v1_contract::contract',
                'management_v1_contract::management_status',
                'management_v1_contract::consumer_snapshot',
                'foundation_v1_contract::foundation_status',
                'core_curriculum_manager::navigation_model',
                'core_curriculum_manager::entity_detail',
                'relationship_where_used_manager::where_used_impact',
                'coverage_bulk_governance_manager::coverage_matrix',
                'coverage_bulk_governance_manager::governance_dashboard',
            ],
            'allowed_write_surfaces' => [
                'curriculum_manager::save_entity',
                'curriculum_manager::save_mapping',
                'curriculum_manager::delete_mapping',
                'curriculum_manager::transition_entity_status',
                'coverage_bulk_governance_manager::apply_bulk_import',
                'coverage_bulk_governance_manager::request_rollback',
            ],
            'consumer_contract' => [
                'read_only' => true,
                'state_changes_allowed' => false,
                'history_source' => 'History V1 downstream evidence contract only',
                'does_not_scrape_raw_moodle_logs' => true,
                'next_allowed_gate' => 'E2',
            ],
            'does_not_do' => [
                'adaptive_path_selection',
                'history_v1_evidence_reprocessing_writes',
                'mastery_state_recalculation',
                'learner_state_mutation',
                'recommendation_policy_change',
                'raw_moodle_log_scraping',
            ],
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Build a read-only Management V1 readiness status.
     *
     * @param int $courseid Optional Moodle course ID.
     * @param string $unitcode Optional unit code.
     * @param int $frameworkid Optional C-UP-KP framework ID.
     * @param int $limit Maximum sampled records for dependency surfaces.
     * @return array
     */
    public static function management_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = max(1, min(300, $limit));
        $dependencies = [
            'history_v1' => self::safe_status_call(static function() use ($courseid): array {
                return history_v1_consumer_contract::contract_status($courseid, 1);
            }),
            'c1_domain_model' => self::safe_status_call(static function() use ($courseid): array {
                return canonical_domain_model::freeze_status($courseid);
            }),
            'c1b_ontology_boundary' => self::safe_status_call(
                static function() use ($courseid, $frameworkid, $limit): array {
                    return ontology_boundary::boundary_status($courseid, $frameworkid, min($limit, 50));
                }
            ),
            'c2_relationship_graph' => self::safe_status_call(
                static function() use ($courseid, $frameworkid, $limit): array {
                    return relationship_graph_contract::graph_status($courseid, $frameworkid, $limit);
                }
            ),
            'c3_content_evidence_mapping' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $limit): array {
                    return content_evidence_mapping_contract::content_mapping_status($courseid, $unitcode, $limit);
                }
            ),
            'c3b_evidence_semantics_quality' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $limit): array {
                    return evidence_semantics_quality_contract::evidence_semantics_status($courseid, $unitcode, $limit);
                }
            ),
            'c4_lifecycle_governance' => self::safe_status_call(
                static function() use ($courseid, $frameworkid, $unitcode, $limit): array {
                    return lifecycle_governance_contract::governance_status($courseid, $frameworkid, $unitcode, $limit);
                }
            ),
            'foundation_v1' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
                    return foundation_v1_contract::foundation_status($courseid, $unitcode, $frameworkid, $limit);
                }
            ),
            'cm1_operational_authoring' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
                    return core_curriculum_manager::status($courseid, $unitcode, $frameworkid, $limit);
                }
            ),
            'cm2_relationship_where_used' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
                    return relationship_where_used_manager::status($courseid, $unitcode, $frameworkid, $limit);
                }
            ),
            'cm3_coverage_bulk_governance' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
                    return coverage_bulk_governance_manager::status($courseid, $unitcode, $frameworkid, $limit);
                }
            ),
            'cm3_coverage_matrix' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
                    return coverage_bulk_governance_manager::coverage_matrix($frameworkid, $courseid, $unitcode, $limit);
                }
            ),
            'cm3_governance_dashboard' => self::safe_status_call(
                static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
                    return coverage_bulk_governance_manager::governance_dashboard($frameworkid, $courseid, $unitcode,
                        min($limit, 200));
                }
            ),
        ];

        $permissions = self::permission_status();
        $files = self::file_status();
        $surface = self::surface_status();
        $criteria = self::criteria($dependencies, $permissions, $files, $surface);
        $criteriasummary = self::criteria_summary($criteria);
        $findings = self::criteria_findings($criteria);
        foreach ($dependencies as $key => $dependency) {
            foreach (($dependency['findings'] ?? []) as $finding) {
                $findings[] = self::normalize_finding($key, $finding);
            }
        }

        return [
            'type' => 'CupkpManagementV1Status',
            'gate' => self::GATE,
            'status' => $criteriasummary['failed'] > 0 ? 'blocked' : 'frozen',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $criteriasummary,
            'dependencies' => self::dependency_summary($dependencies),
            'permissions' => $permissions,
            'files' => $files,
            'surface' => $surface,
            'findings' => $findings,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Build the read-only snapshot intended for production consumers.
     *
     * @param int $courseid Optional Moodle course ID.
     * @param string $unitcode Optional unit code.
     * @param int $frameworkid Optional C-UP-KP framework ID.
     * @param int $limit Maximum sampled records for dependency surfaces.
     * @return array
     */
    public static function consumer_snapshot(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = max(1, min(300, $limit));
        $status = self::management_status($courseid, $unitcode, $frameworkid, $limit);
        $coverage = coverage_bulk_governance_manager::coverage_matrix($frameworkid, $courseid, $unitcode, $limit);
        $governance = coverage_bulk_governance_manager::governance_dashboard($frameworkid, $courseid, $unitcode,
            min($limit, 200));

        return [
            'type' => 'CupkpManagementV1ConsumerSnapshot',
            'contract' => self::CONTRACT_VERSION,
            'gate' => self::GATE,
            'management_status' => $status['status'],
            'criteria_summary' => $status['criteria_summary'],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'scope' => $status['scope'],
            'allowed_read_apis' => $status['contract']['allowed_read_apis'],
            'allowed_write_surfaces' => $status['contract']['allowed_write_surfaces'],
            'read_only_for_consumers' => true,
            'state_changes_allowed' => false,
            'coverage' => [
                'status' => $coverage['status'] ?? 'unknown',
                'counts' => $coverage['counts'] ?? [],
                'categories' => $coverage['categories'] ?? [],
                'open_findings' => count($coverage['findings'] ?? []),
            ],
            'governance' => [
                'status' => $governance['status'] ?? 'unknown',
                'summary' => $governance['summary'] ?? [],
                'recent_imports' => array_slice($governance['recent_imports'] ?? [], 0, 5),
            ],
            'handoff' => [
                'current_gate_complete_when_status' => 'frozen',
                'next_allowed_gate' => 'E2',
                'completed_e1_scope' => 'History V1 to C-UP-KP evidence adapter and controlled reprocessing.',
                'e2_scope' => 'Mastery, confidence, and current learner state consumption of derived evidence.',
            ],
            'forbidden_until_e1_or_later' => [
                'adaptive_path_selection',
                'mastery_state_recalculation',
                'learner_recommendation_policy_changes',
                'raw_moodle_log_scraping',
            ],
            'findings' => $status['findings'],
        ];
    }

    /**
     * Convert dependency outputs into CM4 criteria.
     *
     * @param array $dependencies
     * @param array $permissions
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $dependencies, array $permissions, array $files, array $surface): array {
        $content = $dependencies['c3_content_evidence_mapping'];
        $objects = (int)($content['sample']['objects'] ?? 0);
        $stableobjects = (int)($content['sample']['stable_identity_objects'] ?? 0);
        $contentfindings = array_filter($content['findings'] ?? [], static function(array $finding): bool {
            return in_array(strtolower((string)($finding['severity'] ?? '')), ['blocker', 'error', 'high'], true);
        });
        $coverage = $dependencies['cm3_coverage_matrix'];
        $cm3contract = coverage_bulk_governance_manager::contract();
        $bulkfeatures = $cm3contract['bulk_management'] ?? [];

        return [
            'ontology_frozen' => self::criterion(
                'ontology_frozen',
                ($dependencies['c1_domain_model']['status'] ?? '') === 'frozen' &&
                    ($dependencies['c1b_ontology_boundary']['status'] ?? '') === 'guarded',
                'Ontology and canonical domain boundaries are frozen and guarded.',
                [
                    'domain_status' => $dependencies['c1_domain_model']['status'] ?? 'unknown',
                    'boundary_status' => $dependencies['c1b_ontology_boundary']['status'] ?? 'unknown',
                ]
            ),
            'graph_semantics_frozen' => self::criterion(
                'graph_semantics_frozen',
                ($dependencies['c2_relationship_graph']['status'] ?? '') === 'frozen',
                'Relationship and prerequisite graph semantics remain frozen.',
                [
                    'graph_status' => $dependencies['c2_relationship_graph']['status'] ?? 'unknown',
                    'contract' => relationship_graph_contract::CONTRACT_VERSION,
                ]
            ),
            'management_crud_works' => self::criterion(
                'management_crud_works',
                ($dependencies['cm1_operational_authoring']['status'] ?? '') === 'ready' &&
                    empty($surface['missing_methods']) && empty($files['missing']),
                'Operational curriculum authoring files and CRUD methods are present.',
                [
                    'cm1_status' => $dependencies['cm1_operational_authoring']['status'] ?? 'unknown',
                    'missing_methods' => $surface['missing_methods'],
                    'missing_files' => $files['missing'],
                ]
            ),
            'permissions_work' => self::criterion(
                'permissions_work',
                $permissions['valid'],
                'Required management, import, reporting, learner-view, override, and sync capabilities are registered.',
                [
                    'capabilities' => $permissions['capabilities'],
                    'missing' => $permissions['missing'],
                ]
            ),
            'where_used_works' => self::criterion(
                'where_used_works',
                ($dependencies['cm2_relationship_where_used']['status'] ?? '') === 'ready' &&
                    method_exists(relationship_where_used_manager::class, 'where_used_impact'),
                'Guarded relationship editing and where-used impact previews are available.',
                [
                    'cm2_status' => $dependencies['cm2_relationship_where_used']['status'] ?? 'unknown',
                    'method' => 'relationship_where_used_manager::where_used_impact',
                ]
            ),
            'coverage_validation_works' => self::criterion(
                'coverage_validation_works',
                ($dependencies['cm3_coverage_bulk_governance']['status'] ?? '') === 'ready' &&
                    count($coverage['categories'] ?? []) === 6,
                'The six CM3 coverage checks run through the bounded aggregate matrix.',
                [
                    'cm3_status' => $dependencies['cm3_coverage_bulk_governance']['status'] ?? 'unknown',
                    'coverage_status' => $coverage['status'] ?? 'unknown',
                    'categories' => array_keys($coverage['categories'] ?? []),
                    'open_findings' => count($coverage['findings'] ?? []),
                ]
            ),
            'bulk_management_safe' => self::criterion(
                'bulk_management_safe',
                in_array('dry_run_validation', $bulkfeatures, true) &&
                    in_array('transactional_confirmed_import', $bulkfeatures, true) &&
                    in_array('duplicate_checksum_detection', $bulkfeatures, true) &&
                    in_array('controlled_rollback_request', $bulkfeatures, true) &&
                    method_exists(coverage_bulk_governance_manager::class, 'preview_bulk_import') &&
                    method_exists(coverage_bulk_governance_manager::class, 'apply_bulk_import') &&
                    method_exists(coverage_bulk_governance_manager::class, 'request_rollback'),
                'Bulk management has dry-run, transactional import, duplicate detection, export, and controlled rollback.',
                [
                    'bulk_features' => $bulkfeatures,
                ]
            ),
            'governance_lifecycle_works' => self::criterion(
                'governance_lifecycle_works',
                ($dependencies['c4_lifecycle_governance']['status'] ?? '') === 'frozen' &&
                    ($dependencies['cm3_governance_dashboard']['type'] ?? '') === 'CupkpCm3GovernanceDashboard',
                'Lifecycle versioning and governance dashboard services are operational.',
                [
                    'lifecycle_status' => $dependencies['c4_lifecycle_governance']['status'] ?? 'unknown',
                    'governance_status' => $dependencies['cm3_governance_dashboard']['status'] ?? 'unknown',
                ]
            ),
            'program1_content_mappings_resolve' => self::criterion(
                'program1_content_mappings_resolve',
                ($content['status'] ?? '') === 'frozen' && !$contentfindings &&
                    ($objects === 0 || $stableobjects >= $objects),
                'Program-1 imported content mappings resolve to stable object identities.',
                [
                    'content_mapping_status' => $content['status'] ?? 'unknown',
                    'objects' => $objects,
                    'stable_identity_objects' => $stableobjects,
                    'blocking_content_findings' => count($contentfindings),
                ]
            ),
            'program2_history_contract_ready' => self::criterion(
                'program2_history_contract_ready',
                in_array((string)($dependencies['history_v1']['status'] ?? ''), ['ready', 'ready_with_findings'], true) &&
                    ($dependencies['history_v1']['requiredcontract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT,
                'Program-2 History V1 downstream evidence contract is ready and remains the only normal source-history input.',
                [
                    'history_status' => $dependencies['history_v1']['status'] ?? 'unknown',
                    'required_contract' => $dependencies['history_v1']['requiredcontract'] ?? '',
                ]
            ),
        ];
    }

    /**
     * Check required capabilities and expected context levels.
     *
     * @return array
     */
    private static function permission_status(): array {
        $required = [
            'local/flwcupkp:manageframeworks' => CONTEXT_SYSTEM,
            'local/flwcupkp:import' => CONTEXT_SYSTEM,
            'local/flwcupkp:viewreports' => CONTEXT_COURSE,
            'local/flwcupkp:viewlearnerpath' => CONTEXT_COURSE,
            'local/flwcupkp:override' => CONTEXT_COURSE,
            'local/flwcupkp:synccompetencies' => CONTEXT_SYSTEM,
        ];
        $capabilities = [];
        $missing = [];
        foreach ($required as $capability => $contextlevel) {
            $info = function_exists('get_capability_info') ? get_capability_info($capability) : null;
            $present = !empty($info);
            $actualcontext = $present ? (int)$info->contextlevel : 0;
            $valid = $present && $actualcontext === $contextlevel;
            $capabilities[$capability] = [
                'present' => $present,
                'contextlevel' => $actualcontext,
                'expected_contextlevel' => $contextlevel,
                'valid' => $valid,
            ];
            if (!$valid) {
                $missing[] = $capability;
            }
        }

        return [
            'valid' => empty($missing),
            'capabilities' => $capabilities,
            'missing' => $missing,
        ];
    }

    /**
     * Verify expected management files exist.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'curriculum.php',
            'entity.php',
            'edit_entity.php',
            'mappings.php',
            'governance.php',
            'management.php',
            'foundation.php',
            'db/access.php',
            'db/services.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            $exists = file_exists($CFG->dirroot . '/local/flwcupkp/' . $file);
            $present[$file] = $exists;
            if (!$exists) {
                $missing[] = $file;
            }
        }

        return [
            'valid' => empty($missing),
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * Check required method surfaces without executing write operations.
     *
     * @return array
     */
    private static function surface_status(): array {
        $methods = [
            curriculum_manager::class => [
                'list_entities',
                'save_entity',
                'save_mapping',
                'delete_mapping',
                'transition_entity_status',
            ],
            core_curriculum_manager::class => [
                'status',
                'navigation_model',
                'entity_detail',
            ],
            relationship_where_used_manager::class => [
                'status',
                'where_used_impact',
                'coverage_governance_status',
            ],
            coverage_bulk_governance_manager::class => [
                'status',
                'coverage_matrix',
                'governance_dashboard',
                'preview_bulk_import',
                'apply_bulk_import',
                'export_bulk_package',
                'rollback_preview',
                'request_rollback',
            ],
        ];
        $present = [];
        $missing = [];
        foreach ($methods as $class => $classmethods) {
            foreach ($classmethods as $method) {
                $key = $class . '::' . $method;
                $exists = method_exists($class, $method);
                $present[$key] = $exists;
                if (!$exists) {
                    $missing[] = $key;
                }
            }
        }

        return [
            'valid' => empty($missing),
            'methods' => $present,
            'missing_methods' => $missing,
        ];
    }

    /**
     * Run a dependency status call and wrap failures as blocked status arrays.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            $status = $callback();
            if (!is_array($status)) {
                throw new \coding_exception('Status callback did not return an array.');
            }
            return $status;
        } catch (\Throwable $e) {
            return [
                'type' => 'CupkpManagementV1DependencyStatus',
                'status' => 'blocked',
                'findings' => [
                    [
                        'severity' => 'blocker',
                        'code' => 'dependency_unreadable',
                        'message' => $e->getMessage(),
                    ],
                ],
            ];
        }
    }

    /**
     * Create one criterion row.
     *
     * @param string $code
     * @param bool $pass
     * @param string $message
     * @param array $evidence
     * @return array
     */
    private static function criterion(string $code, bool $pass, string $message, array $evidence = []): array {
        return [
            'code' => $code,
            'status' => $pass ? 'pass' : 'fail',
            'message' => $message,
            'evidence' => $evidence,
        ];
    }

    /**
     * Summarize criterion statuses.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $summary = [
            'total' => count($criteria),
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0,
        ];
        foreach ($criteria as $criterion) {
            if (($criterion['status'] ?? '') === 'pass') {
                $summary['passed']++;
            } else if (($criterion['status'] ?? '') === 'warning') {
                $summary['warnings']++;
            } else {
                $summary['failed']++;
            }
        }
        return $summary;
    }

    /**
     * Convert failed criteria into blocker findings.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_findings(array $criteria): array {
        $findings = [];
        foreach ($criteria as $criterion) {
            if (($criterion['status'] ?? '') === 'fail') {
                $findings[] = [
                    'severity' => 'BLOCKER',
                    'source' => 'cm4_criteria',
                    'code' => $criterion['code'] . '_failed',
                    'message' => $criterion['message'],
                ];
            }
        }
        return $findings;
    }

    /**
     * Summarize dependency statuses without returning full sampled payloads twice.
     *
     * @param array $dependencies
     * @return array
     */
    private static function dependency_summary(array $dependencies): array {
        $summary = [];
        foreach ($dependencies as $key => $dependency) {
            $summary[$key] = [
                'type' => $dependency['type'] ?? '',
                'gate' => $dependency['gate'] ?? '',
                'status' => $dependency['status'] ?? ($dependency['foundation_status'] ?? 'unknown'),
                'contract' => $dependency['contract']['version'] ?? ($dependency['contract'] ?? ''),
                'next_allowed_gate' => $dependency['next_allowed_gate'] ?? '',
                'findings' => count($dependency['findings'] ?? []),
            ];
        }
        return $summary;
    }

    /**
     * Normalize dependency findings for the Management V1 status.
     *
     * @param string $source
     * @param array $finding
     * @return array
     */
    private static function normalize_finding(string $source, array $finding): array {
        return [
            'severity' => strtoupper((string)($finding['severity'] ?? 'INFO')),
            'source' => $source,
            'code' => (string)($finding['code'] ?? 'dependency_finding'),
            'message' => (string)($finding['message'] ?? json_encode($finding)),
        ];
    }
}

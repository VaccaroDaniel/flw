<?php
// Program 3 Gate C0 repository audit for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only integrated repository audit for Program 3 Gate C0.
 */
final class program3_repository_audit {
    /** Program 3 integrated repository audit gate. */
    public const GATE = 'P3_C0';

    /** Allowed C0 classification values. */
    public const CLASSIFICATIONS = ['KEEP', 'EXTEND', 'REFACTOR', 'DEPRECATE', 'REMOVE', 'UNKNOWN'];

    /** @var array Expected plugin-owned tables for current C0 audit. */
    private const EXPECTED_TABLES = [
        'flwcupkp_framework',
        'flwcupkp_comp',
        'flwcupkp_up',
        'flwcupkp_kp',
        'flwcupkp_comp_up',
        'flwcupkp_up_kp',
        'flwcupkp_kp_prereq',
        'flwcupkp_object',
        'flwcupkp_object_map',
        'flwcupkp_evidence',
        'flwcupkp_state',
        'flwcupkp_rule',
        'flwcupkp_recommend',
        'flwcupkp_import',
        'flwcupkp_calsnapshot',
        'flwcupkp_calproposal',
        'flwcupkp_calrecalc',
        'flwcupkp_eval_period',
        'flwcupkp_eval_snapshot',
        'flwcupkp_selfeval',
        'flwcupkp_diagnostic',
        'flwcupkp_goal',
        'flwcupkp_goal_version',
        'flwcupkp_placement_state',
        'flwcupkp_intervention',
        'flwcupkp_audit',
    ];

    /** @var array Expected service classes for current C0 audit. */
    private const EXPECTED_CLASSES = [
        'history_v1_consumer_contract' => '\\local_flwcupkp\\local\\history_v1_consumer_contract',
        'canonical_domain_model' => '\\local_flwcupkp\\local\\canonical_domain_model',
        'ontology_boundary' => '\\local_flwcupkp\\local\\ontology_boundary',
        'relationship_graph_contract' => '\\local_flwcupkp\\local\\relationship_graph_contract',
        'content_evidence_mapping_contract' => '\\local_flwcupkp\\local\\content_evidence_mapping_contract',
        'evidence_semantics_quality_contract' => '\\local_flwcupkp\\local\\evidence_semantics_quality_contract',
        'lifecycle_governance_contract' => '\\local_flwcupkp\\local\\lifecycle_governance_contract',
        'foundation_v1_contract' => '\\local_flwcupkp\\local\\foundation_v1_contract',
        'core_curriculum_manager' => '\\local_flwcupkp\\local\\core_curriculum_manager',
        'relationship_where_used_manager' => '\\local_flwcupkp\\local\\relationship_where_used_manager',
        'coverage_bulk_governance_manager' => '\\local_flwcupkp\\local\\coverage_bulk_governance_manager',
        'management_v1_contract' => '\\local_flwcupkp\\local\\management_v1_contract',
        'history_evidence_adapter' => '\\local_flwcupkp\\local\\history_evidence_adapter',
        'curriculum_manager' => '\\local_flwcupkp\\local\\curriculum_manager',
        'import_service' => '\\local_flwcupkp\\local\\import_service',
        'evidence_guard' => '\\local_flwcupkp\\local\\evidence_guard',
        'mastery_engine' => '\\local_flwcupkp\\local\\mastery_engine',
        'mastery_state_service' => '\\local_flwcupkp\\local\\mastery_state_service',
        'retention_review_service' => '\\local_flwcupkp\\local\\retention_review_service',
        'learning_goal_service' => '\\local_flwcupkp\\local\\learning_goal_service',
        'placement_diagnostic_service' => '\\local_flwcupkp\\local\\placement_diagnostic_service',
        'adaptive_decision_policy_service' => '\\local_flwcupkp\\local\\adaptive_decision_policy_service',
        'goal_gap_path_service' => '\\local_flwcupkp\\local\\goal_gap_path_service',
        'candidate_activity_resolution_service' => '\\local_flwcupkp\\local\\candidate_activity_resolution_service',
        'adaptive_path_engine_service' => '\\local_flwcupkp\\local\\adaptive_path_engine_service',
        'trajectory_invariant_service' => '\\local_flwcupkp\\local\\trajectory_invariant_service',
        'progress_goal_readiness_service' => '\\local_flwcupkp\\local\\progress_goal_readiness_service',
        'student_learning_timeline_view_service' => '\\local_flwcupkp\\local\\student_learning_timeline_view_service',
        'student_learning_timeline_renderer' => '\\local_flwcupkp\\local\\student_learning_timeline_renderer',
        'learner_experience_service' => '\\local_flwcupkp\\local\\learner_experience_service',
        'learner_experience_renderer' => '\\local_flwcupkp\\local\\learner_experience_renderer',
        'staff_intelligence_service' => '\\local_flwcupkp\\local\\staff_intelligence_service',
        'staff_intelligence_renderer' => '\\local_flwcupkp\\local\\staff_intelligence_renderer',
        'production_validation_service' => '\\local_flwcupkp\\local\\production_validation_service',
        'rollup_engine' => '\\local_flwcupkp\\local\\rollup_engine',
        'recommendation_engine' => '\\local_flwcupkp\\local\\recommendation_engine',
        'learner_evaluation' => '\\local_flwcupkp\\local\\learner_evaluation',
        'moodle_competency_writer' => '\\local_flwcupkp\\local\\moodle_competency_writer',
        'output_hooks' => '\\local_flwcupkp\\local\\output_hooks',
    ];

    /**
     * Run the read-only C0 audit.
     *
     * @param int $courseid Optional course ID for downstream History V1 sampling.
     * @return array
     */
    public static function audit_status(int $courseid = 0): array {
        $history = history_v1_consumer_contract::contract_status($courseid, 1);
        $schema = self::schema_status();
        $classes = self::class_status();
        $files = self::file_status();
        $program1 = self::program1_contract_status($history);
        $program2 = self::program2_contract_status($history);
        $subsystems = self::subsystem_classification();
        $gaps = self::foundation_gaps();

        $findings = array_merge(
            self::runtime_findings($schema, 'missing_table', 'Missing plugin-owned table.'),
            self::runtime_findings($classes, 'missing_class', 'Missing expected service class.'),
            self::runtime_findings($files['missing'], 'missing_file', 'Missing expected plugin file.')
        );
        if (($history['status'] ?? 'blocked') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'history_v1_contract_not_ready',
                'message' => 'Program 3 requires the frozen History V1 downstream evidence contract.',
            ];
        }
        foreach ($program1['checks'] as $check) {
            if (empty($check['pass'])) {
                $findings[] = [
                    'severity' => 'warning',
                    'code' => 'program1_' . $check['code'],
                    'message' => $check['message'],
                ];
            }
        }
        foreach ($program2['checks'] as $check) {
            if (empty($check['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => 'program2_' . $check['code'],
                    'message' => $check['message'],
                ];
            }
        }

        return [
            'type' => 'Program3C0RepositoryAudit',
            'gate' => self::GATE,
            'status' => self::overall_status($findings),
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'history' => [
                'status' => $history['status'] ?? 'blocked',
                'requiredcontract' => $history['requiredcontract'] ?? null,
                'contractavailable' => $history['contractavailable'] ?? false,
                'normpolicyversion' => $history['contract']['normpolicyversion'] ?? null,
                'findings' => $history['findings'] ?? [],
            ],
            'program1' => $program1,
            'program2' => $program2,
            'runtime' => [
                'tables' => $schema,
                'classes' => $classes,
                'files' => $files,
            ],
            'subsystems' => $subsystems,
            'foundation_gaps' => $gaps,
            'boundary' => self::stop_boundary(),
            'findings' => $findings,
        ];
    }

    /**
     * Return the static C0 subsystem classification.
     *
     * @return array
     */
    public static function subsystem_classification(): array {
        return [
            'schema' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'Base C-UP-KP tables plus the append-only UX3 intervention ledger exist. F1 validates the complete flow read-only and does not change table ownership.',
                'next' => 'No later Program 3 gate is authorized; deployment findings must be remediated and F1 rerun.',
            ],
            'c_kp_up' => [
                'classification' => ['KEEP'],
                'current' => 'Competency, Use Point, and Knowledge Point tables exist. C1/C1B meanings, code rules, ontology boundaries, C2 graph semantics, C3 mappings, C3B evidence semantics, C4 lifecycle governance, C5 Foundation V1, CM1 operational curriculum authoring, CM2 guarded relationship editing, CM3 bulk coverage governance, and CM4 Management V1 are present.',
                'next' => 'A5 must preserve these meanings while consuming eligible activity candidates.',
            ],
            'mappings' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'Competency-UP, UP-KP, object-target, and KP prerequisite mappings are guarded by C2/C3, C4 lifecycle/replacement governance, CM2 preview/confirm editing, and CM3 bulk governance.',
                'next' => 'A5 may consume resolved eligible activity candidates without changing mappings or scraping raw Moodle logs.',
            ],
            'prerequisites' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'KP prerequisite direction, hard/soft requirement semantics, replacement cycles, graph traversal APIs, REPLACED_BY lifecycle governance, and CM2 prerequisite impact previews are present.',
                'next' => 'A5 must preserve prerequisite and eligibility semantics when creating adaptive recommendations.',
            ],
            'evidence' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'Evidence records, legacy activity adapters, and the E1 History V1 evidence adapter exist. C3B stores History V1 source keys, result state, evidence role, performance mode, direct/inferred flag, quality dimensions, retry semantics, and a separate evidence policy version in rubric metadata.',
                'next' => 'A5 may consume derived evidence, current state, retention state, goal scope, placement state, A3 decisions, A4 route output, and A4B activity eligibility.',
            ],
            'mastery' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'Explainable mastery calculation, calibrated thresholds, deterministic confidence, snapshot metadata, rollups, and Moodle competency sync exist. Explicit C3B inconclusive evidence contributes no score weight.',
                'next' => 'A5 may consume A4B resolution output without changing mastery, retention, or placement semantics.',
            ],
            'learner_state' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'Current KP, UP, and competency states are stored with score, confidence, evidence count, policy version, trend, evidence references/hash, calculated time, retention/review metadata, and manual override fields.',
                'next' => 'A5 may write controlled adaptive recommendations without changing learner state outside its explicit policy.',
            ],
            'goal' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'A1 provides current learner goal records, immutable goal versions, source labels, destination profile fields, admin/student UI, CLI, APIs, privacy coverage, and audit history.',
                'next' => 'A5 may use the A4B resolved activity candidate as an executable adaptive next step.',
            ],
            'placement' => [
                'classification' => ['EXTEND'],
                'current' => 'A2 stores interpreted placement, diagnostic, stale, low-confidence, incomplete, and cold-start states while treating placement as diagnostic evidence only.',
                'next' => 'A5 must keep placement diagnostic and non-permanent while executing adaptive decisions.',
            ],
            'recommendation' => [
                'classification' => ['REFACTOR'],
                'current' => 'Legacy recommendations remain intact. A5 owns versioned adaptive recommendations. UX3 layers explicit, versioned staff controls after normal A5 resolution and never silently overwrites policy output.',
                'next' => 'F1 validates automatic and staff-controlled path transitions together.',
            ],
            'timeline' => [
                'classification' => ['EXTEND'],
                'current' => 'UX1 composes a read-only StudentLearningTimelineView. UX2 owns the simplified learner experience. UX3 consumes those surfaces only for authorized staff detail and does not expose staff controls in learner UI.',
                'next' => 'F1 validates the role boundary and both presentation surfaces end to end.',
            ],
            'teacher_admin_ui' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'The role-aware UX3 staff intelligence page explains target, activity, practice, review, skip, and path changes and exposes six permission-controlled interventions with immutable history.',
                'next' => 'F1 is the final read-only integrated production validation surface.',
            ],
            'tests' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'PHPUnit tests cover Program 3 through F1, including role separation, historical reproducibility, invariants, ownership, and the complete integrated evidence-to-adaptation pipeline.',
                'next' => 'Production scopes must meet the F1 validator with no BLOCKER or HIGH findings.',
            ],
            'privacy' => [
                'classification' => ['KEEP', 'EXTEND'],
                'current' => 'Moodle privacy coverage includes learner interventions and anonymizes staff actor references while preserving the append-only operational record. History remains owned by local_flwhistory.',
                'next' => 'F1 verifies privacy registration and authorization boundaries in the integrated deployment.',
            ],
            'backup_restore' => [
                'classification' => ['UNKNOWN', 'EXTEND'],
                'current' => 'No local plugin backup/restore implementation was found in the current source tree.',
                'next' => 'C4/C5 must decide export/backup ownership and add tests or explicit non-backup policy.',
            ],
        ];
    }

    /**
     * Return C1-C5 foundation gaps.
     *
     * @return array
     */
    public static function foundation_gaps(): array {
        return [
            'C1' => [
                'gate' => 'Canonical C-UP-KP Domain Model',
                'gaps' => [
                    'Freeze Competency, Use Point, and Knowledge Point meanings in code and docs.',
                    'Validate stable semantic codes without silently inventing stage semantics.',
                    'Separate CEFR level from FLW stage everywhere the model accepts both.',
                    'Confirm many-to-many topology without forcing a strict tree.',
                ],
            ],
            'C1B' => [
                'gate' => 'Ontology Boundary + Validation',
                'gaps' => [
                    'Define allowed entity roles, domains, statuses, and relationship vocabularies.',
                    'Add validation for invalid cross-framework, circular, duplicate, and lifecycle-incompatible links.',
                    'Separate curriculum ontology errors from import/file-format errors.',
                ],
            ],
            'C2' => [
                'gate' => 'Relationships + Prerequisites',
                'gaps' => [
                    'Complete as of Program 3 Gate C2: relationship direction and prerequisite strength semantics are frozen.',
                    'Complete as of Program 3 Gate C2: hard prerequisite and replacement cycle checks are centralized.',
                    'Complete as of Program 3 Gate C2: read-only adjacency, dependency, and where-used graph APIs are available.',
                ],
            ],
            'C3' => [
                'gate' => 'Content + Evidence Mapping Contracts',
                'gaps' => [
                    'Complete as of Program 3 Gate C3: Program 1 identity fields are preserved in learning-object metadata.',
                    'Complete as of Program 3 Gate C3: object mappings use frozen pedagogical roles TEACHES, PRACTICES, ASSESSES, and EVIDENCE_FOR.',
                    'Complete as of Program 3 Gate C3: unresolved Program 1 identities remain unresolved facts and are not fabricated from titles.',
                    'Complete as of Program 3 Gate C3: completion evidence is accepted only when the mapped role/purpose permits it.',
                ],
            ],
            'C3B' => [
                'gate' => 'Evidence Semantics + Quality Model',
                'gaps' => [
                    'Complete as of Program 3 Gate C3B: C-UP-KP evidence stores History V1 contract/source key metadata in rubricjson.',
                    'Complete as of Program 3 Gate C3B: result states positive, negative, partial, and inconclusive are represented.',
                    'Complete as of Program 3 Gate C3B: performance mode, evidence role, and direct/inferred evidence are represented.',
                    'Complete as of Program 3 Gate C3B: quality dimensions validity, reliability, independence, authenticity, production demand, contextual transfer, support level, difficulty, recency, and confidence are represented.',
                    'Complete as of Program 3 Gate C3B: evidence policy versioning is separated from mastery policy versioning.',
                ],
            ],
            'C4' => [
                'gate' => 'Lifecycle + Versioning + Governance',
                'gaps' => [
                    'Complete as of Program 3 Gate C4: canonical DRAFT/REVIEW/APPROVED/PUBLISHED/DEPRECATED/ARCHIVED status transitions are frozen.',
                    'Complete as of Program 3 Gate C4: published semantic rows cannot be overwritten in place; clone/revision is required.',
                    'Complete as of Program 3 Gate C4: REPLACED_BY requires deprecated/archived source and approved/published successor.',
                    'Complete as of Program 3 Gate C4: duplicate codes, invalid relationships, orphan rows, missing evidence routes, invalid replacements, and invalid published states are classified.',
                ],
            ],
            'C5' => [
                'gate' => 'Foundation Freeze V1',
                'gaps' => [
                    'Complete as of Program 3 Gate C5: invariant tests cover C1-C4 contracts through Foundation V1 readiness.',
                    'Complete as of Program 3 Gate C5: read-only migration checks summarize source keys, mappings, evidence semantics, and lifecycle states.',
                    'Complete as of Program 3 Gate C5: Foundation V1 is published for Program 3 evidence, mastery, adaptive, and UX gates.',
                    'Complete as of Program 3 Gate C5: adaptive-path work remains stopped until later gates explicitly introduce adaptive logic.',
                ],
            ],
        ];
    }

    /**
     * Verify Program 1 identity availability through History V1.
     *
     * @param array $history
     * @return array
     */
    private static function program1_contract_status(array $history): array {
        $facttypes = $history['contract']['facttypes'] ?? [];
        $hasidentity = in_array('content_identities', $facttypes, true);
        $sample = $history['sample']['content_identities'] ?? null;

        return [
            'type' => 'Program1IdentityResolutionStatus',
            'source' => 'History V1 content identity facts',
            'checks' => [
                [
                    'code' => 'content_identity_fact_type',
                    'pass' => $hasidentity,
                    'message' => 'History V1 must expose Program 1 content identity facts.',
                ],
                [
                    'code' => 'world_stage_unit_lesson_activity_assessment_question_fields',
                    'pass' => $hasidentity,
                    'message' => 'Program 1 identity fields are consumed through content_identities, not raw Moodle logs.',
                ],
                [
                    'code' => 'moodle_course_section_cmid_lifecycle_fields',
                    'pass' => $hasidentity,
                    'message' => 'Moodle course, section, cmid, freshness, and status are consumed through content_identities.',
                ],
            ],
            'sample' => $sample,
        ];
    }

    /**
     * Verify Program 2 History V1 fact availability.
     *
     * @param array $history
     * @return array
     */
    private static function program2_contract_status(array $history): array {
        $facttypes = $history['contract']['facttypes'] ?? [];
        $required = [
            'source_events',
            'attempts',
            'grades',
            'completion',
            'placement',
            'content_identities',
        ];
        $missing = array_values(array_diff($required, $facttypes));

        return [
            'type' => 'Program2HistoryQueryStatus',
            'source' => 'History V1 downstream evidence contract',
            'checks' => [
                [
                    'code' => 'history_contract_ready',
                    'pass' => ($history['status'] ?? 'blocked') !== 'blocked',
                    'message' => 'History V1 contract must be ready for Program 3 consumption.',
                ],
                [
                    'code' => 'required_fact_types',
                    'pass' => empty($missing),
                    'message' => 'Program 2 must expose event, attempt, grade, completion, placement, and source identity facts.',
                    'missing' => $missing,
                ],
            ],
        ];
    }

    /**
     * Verify plugin-owned schema tables exist.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $dbman = $DB->get_manager();
        $status = [];
        foreach (self::EXPECTED_TABLES as $tablename) {
            $status[$tablename] = $dbman->table_exists(new \xmldb_table($tablename));
        }
        return $status;
    }

    /**
     * Verify expected classes exist.
     *
     * @return array
     */
    private static function class_status(): array {
        $status = [];
        foreach (self::EXPECTED_CLASSES as $key => $classname) {
            $status[$key] = class_exists($classname);
        }
        return $status;
    }

    /**
     * Verify expected files exist.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $relativefiles = [
            'db/access.php',
            'db/events.php',
            'db/services.php',
            'db/tasks.php',
            'db/install.xml',
            'classes/privacy/provider.php',
            'index.php',
            'setup.php',
            'curriculum.php',
            'entity.php',
            'edit_entity.php',
            'foundation.php',
            'governance.php',
            'management.php',
            'history_evidence.php',
            'mastery_state.php',
            'retention_review.php',
            'learning_goal.php',
            'placement_diagnostic.php',
            'adaptive_decision.php',
            'initial_path.php',
            'activity_resolution.php',
            'adaptive_path.php',
            'trajectory_simulation.php',
            'progress_readiness.php',
            'learning_timeline.php',
            'staff_intelligence.php',
            'cli/learning_timeline.php',
            'cli/learner_experience.php',
            'cli/staff_intelligence.php',
            'cli/production_validation.php',
            'teacher.php',
            'student.php',
            'evaluation.php',
            'sync.php',
            'evidence_sync.php',
            'styles.css',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($relativefiles as $file) {
            $path = $CFG->dirroot . '/local/flwcupkp/' . $file;
            if (file_exists($path)) {
                $present[$file] = true;
            } else {
                $missing[$file] = false;
            }
        }

        $backupfiles = glob($CFG->dirroot . '/local/flwcupkp/backup/**/*.php') ?: [];

        return [
            'present' => $present,
            'missing' => $missing,
            'backup_restore_files' => array_values($backupfiles),
            'backup_restore_present' => !empty($backupfiles),
        ];
    }

    /**
     * Convert failed boolean runtime checks into findings.
     *
     * @param array $checks
     * @param string $code
     * @param string $message
     * @return array
     */
    private static function runtime_findings(array $checks, string $code, string $message): array {
        $findings = [];
        foreach ($checks as $name => $pass) {
            if (!$pass) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => $code,
                    'message' => $message . ' ' . $name,
                ];
            }
        }
        return $findings;
    }

    /**
     * Gate stop boundary.
     *
     * @return array
     */
    private static function stop_boundary(): array {
        return [
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'raw_moodle_logs' => 'diagnostic_only',
            'state_changes_allowed_in_gate' => false,
            'write_boundary' => [],
            'next_allowed_gate' => null,
            'not_started' => [],
            'final_gate' => true,
            'production_readiness_requires_scope_validation' => true,
        ];
    }

    /**
     * Overall status from findings.
     *
     * @param array $findings
     * @return string
     */
    private static function overall_status(array $findings): string {
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? '') === 'blocker') {
                return 'blocked';
            }
        }
        return $findings ? 'f1_validation_available_with_findings' : 'f1_validation_available';
    }
}

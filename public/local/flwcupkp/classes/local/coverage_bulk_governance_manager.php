<?php
// Program 3 Gate CM3 coverage, bulk management, and governance services.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Adds FLW-scale coverage governance and controlled bulk operations over CM1/CM2.
 */
final class coverage_bulk_governance_manager {
    /** Program 3 coverage and bulk governance gate. */
    public const GATE = 'P3_CM3';

    /** Frozen CM3 service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_COVERAGE_BULK_GOVERNANCE_V1';

    /** @var array Supported bulk import formats. */
    private const IMPORT_FORMATS = ['json', 'csv'];

    /** @var array Supported CSV import types mirrored from import_service. */
    private const CSV_TYPES = ['activity_mappings', 'quiz_kp_mappings'];

    /** @var array Package collection keys shown in dry-run counts. */
    private const PACKAGE_KEYS = [
        'frameworks',
        'competencies',
        'use_points',
        'knowledge_points',
        'learning_objects',
        'competency_up_mappings',
        'up_kp_mappings',
        'kp_prerequisites',
        'activity_mappings',
        'lesson_mappings',
        'project_competency_mappings',
        'project_evidence',
        'assessment_rules',
    ];

    /** @var array Lifecycle entity tables. */
    private const ENTITY_TABLES = [
        'framework' => 'flwcupkp_framework',
        'competency' => 'flwcupkp_comp',
        'up' => 'flwcupkp_up',
        'kp' => 'flwcupkp_kp',
    ];

    /**
     * Return the CM3 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpCoverageBulkGovernanceContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                foundation_v1_contract::CONTRACT_VERSION,
                core_curriculum_manager::CONTRACT_VERSION,
                relationship_where_used_manager::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                content_evidence_mapping_contract::CONTRACT_VERSION,
                evidence_semantics_quality_contract::CONTRACT_VERSION,
                lifecycle_governance_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'coverage_areas' => [
                'competency_coverage',
                'kp_teaching_coverage',
                'up_practice_coverage',
                'up_assessment_coverage',
                'evidence_quality_coverage',
                'production_interaction_coverage',
            ],
            'detects' => [
                'orphans',
                'taught_not_assessed',
                'assessed_not_taught',
                'interaction_target_with_recognition_only_evidence',
                'missing_prerequisite',
                'deprecated_references',
                'evidence_ceilings',
                'coverage_imbalance',
            ],
            'bulk_management' => [
                'dry_run_validation',
                'transactional_confirmed_import',
                'duplicate_checksum_detection',
                'json_export',
                'controlled_rollback_request',
            ],
            'governance_ui' => [
                'version',
                'review',
                'publication',
                'deprecation',
                'replacement',
                'impact',
            ],
            'state_changes_allowed' => false,
            'does_not_do' => [
                'adaptive_path_selection',
                'mastery_state_recalculation',
                'recommendation_policy_change',
                'history_v1_source_capture',
                'raw_moodle_log_scraping',
            ],
        ];
    }

    /**
     * CM3 readiness status.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0,
            int $limit = 100): array {
        global $CFG;

        $foundation = foundation_v1_contract::foundation_status($courseid, $unitcode, $frameworkid, $limit);
        $cm1 = core_curriculum_manager::status($courseid, $unitcode, $frameworkid, $limit);
        $cm2 = relationship_where_used_manager::status($courseid, $unitcode, $frameworkid, $limit);
        $findings = [];

        if (($foundation['status'] ?? '') !== 'frozen') {
            $findings[] = self::finding('BLOCKER', 'foundation_not_frozen',
                'CM3 requires the frozen Foundation V1 status.');
        }
        if (!in_array((string)($foundation['next_allowed_gate'] ?? ''), ['CM3', 'CM4', 'E1', 'E2'], true)) {
            $findings[] = self::finding('BLOCKER', 'foundation_gate_boundary_unexpected',
                'CM3 expects Foundation V1 to hand off to CM3 or later CM governance.');
        }
        if (($cm1['status'] ?? '') !== 'ready') {
            $findings[] = self::finding('BLOCKER', 'cm1_not_ready',
                'CM3 bulk governance requires the CM1 curriculum manager surface.');
        }
        if (($cm2['status'] ?? '') !== 'ready') {
            $findings[] = self::finding('BLOCKER', 'cm2_not_ready',
                'CM3 bulk governance requires CM2 guarded relationship editing.');
        }

        $requiredfiles = [
            'governance.php',
            'curriculum.php',
            'mappings.php',
            'import_export.php',
            'foundation.php',
        ];
        $files = [];
        foreach ($requiredfiles as $file) {
            $present = file_exists($CFG->dirroot . '/local/flwcupkp/' . $file);
            $files[$file] = $present;
            if (!$present) {
                $findings[] = self::finding('BLOCKER', 'missing_cm3_file', 'Missing CM3 file: ' . $file);
            }
        }

        $blocking = self::blocking_findings($findings);

        return [
            'type' => 'CupkpCoverageBulkGovernanceStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'foundation' => [
                'status' => $foundation['status'] ?? 'unknown',
                'next_allowed_gate' => $foundation['next_allowed_gate'] ?? null,
                'unresolved_blocker_high_count' => $foundation['unresolved_blocker_high_count'] ?? null,
            ],
            'cm1' => [
                'status' => $cm1['status'] ?? 'unknown',
                'contract' => $cm1['contract']['version'] ?? null,
            ],
            'cm2' => [
                'status' => $cm2['status'] ?? 'unknown',
                'contract' => $cm2['contract']['version'] ?? null,
            ],
            'files' => $files,
            'findings' => $findings,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Build the FLW-scale coverage matrix and governance findings.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function coverage_matrix(int $frameworkid = 0, int $courseid = 0,
            string $unitcode = '', int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $frameworkids = self::scoped_framework_ids($frameworkid, $courseid, $unitcode, $limit);
        $competencies = self::records_in_frameworks('flwcupkp_comp', $frameworkids, 'externalid ASC', $limit);
        $ups = self::records_in_frameworks('flwcupkp_up', $frameworkids, 'externalid ASC', $limit);
        $kps = self::records_in_frameworks('flwcupkp_kp', $frameworkids, 'externalid ASC', $limit);
        $objects = self::scoped_objects($frameworkids, $courseid, $unitcode, $limit);

        $compup = self::comp_up_records(array_keys($competencies), array_keys($ups), $limit);
        $upkp = self::up_kp_records(array_keys($ups), array_keys($kps), $limit);
        $prereqs = self::kp_prereq_records(array_keys($kps), $limit);
        $objectmaps = self::object_map_records(array_keys($objects), $limit);
        $evidence = self::scoped_evidence($courseid, $unitcode, $competencies, $ups, $kps, $objects, $limit);

        $relations = self::relationship_indexes($compup, $upkp, $prereqs);
        $objectindex = self::object_mapping_indexes($objectmaps, $objects);
        $evidenceindex = self::evidence_indexes($evidence);

        $competencycovered = self::covered_competencies($competencies, $relations, $objectindex);
        $kpteaching = self::target_coverage($kps, 'kp', $objectindex['rolekeys']['TEACHES'] ?? []);
        $uppractice = self::target_coverage($ups, 'up', $objectindex['rolekeys']['PRACTICES'] ?? []);
        $upassessment = self::target_coverage($ups, 'up', array_merge(
            $objectindex['rolekeys']['ASSESSES'] ?? [],
            $objectindex['rolekeys']['EVIDENCE_FOR'] ?? []
        ));
        $evidencequality = self::evidence_quality_coverage($evidence);
        $production = self::production_interaction_coverage($ups, $objectindex, $evidenceindex);

        $categories = [
            'competency_coverage' => self::category(
                'competency_coverage',
                'Competency coverage',
                count($competencies),
                count($competencycovered),
                'Competencies connected to UP/KP/object evidence routes.'
            ),
            'kp_teaching_coverage' => self::category(
                'kp_teaching_coverage',
                'KP teaching coverage',
                count($kps),
                count($kpteaching),
                'Knowledge Points with a teaching object route.'
            ),
            'up_practice_coverage' => self::category(
                'up_practice_coverage',
                'UP practice coverage',
                count($ups),
                count($uppractice),
                'Use Points with a practice object route.'
            ),
            'up_assessment_coverage' => self::category(
                'up_assessment_coverage',
                'UP assessment coverage',
                count($ups),
                count($upassessment),
                'Use Points with an assessment or direct evidence route.'
            ),
            'evidence_quality_coverage' => self::category(
                'evidence_quality_coverage',
                'Evidence-quality coverage',
                count($evidence),
                $evidencequality['covered'],
                'Evidence rows with policy metadata, confidence, strength, or rubric quality signals.'
            ),
            'production_interaction_coverage' => self::category(
                'production_interaction_coverage',
                'Production/interaction coverage',
                $production['total'],
                $production['covered'],
                'Interaction or production Use Points with non-recognition evidence routes.'
            ),
        ];

        $findings = self::coverage_findings(
            $competencies,
            $ups,
            $kps,
            $objects,
            $relations,
            $objectindex,
            $evidenceindex,
            $categories
        );
        $blocking = self::blocking_findings($findings);

        return [
            'type' => 'CupkpCm3CoverageMatrix',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => $blocking ? 'blocked' : ($findings ? 'warning' : 'governed'),
            'scope' => [
                'frameworkid' => $frameworkid,
                'frameworkids' => array_values($frameworkids),
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'limit' => $limit,
            ],
            'counts' => [
                'frameworks' => count($frameworkids),
                'competencies' => count($competencies),
                'use_points' => count($ups),
                'knowledge_points' => count($kps),
                'learning_objects' => count($objects),
                'comp_up_edges' => count($compup),
                'up_kp_edges' => count($upkp),
                'kp_prereq_edges' => count($prereqs),
                'object_map_edges' => count($objectmaps),
                'evidence_rows' => count($evidence),
            ],
            'categories' => $categories,
            'findings' => $findings,
            'aggregation' => [
                'mode' => 'bounded_aggregate_counts',
                'limit' => $limit,
                'expensive_counts' => 'summarized_without_expanding_learner_rows',
            ],
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Build lifecycle/version governance dashboard data.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function governance_dashboard(int $frameworkid = 0, int $courseid = 0,
            string $unitcode = '', int $limit = 200): array {
        $limit = max(1, min(500, $limit));
        $frameworkids = self::scoped_framework_ids($frameworkid, $courseid, $unitcode, $limit);
        $coverage = self::coverage_matrix($frameworkid, $courseid, $unitcode, $limit);
        $statuscounts = [];
        foreach (self::ENTITY_TABLES as $type => $table) {
            $statuscounts[$type] = self::status_counts($type, $table, $frameworkids);
        }

        $frameworks = self::framework_rows($frameworkids, $limit);
        $reviewcount = 0;
        $publishedcount = 0;
        $deprecatedcount = 0;
        foreach ($statuscounts as $counts) {
            $reviewcount += (int)($counts['review'] ?? 0);
            $publishedcount += (int)($counts['published'] ?? 0);
            $deprecatedcount += (int)($counts['deprecated'] ?? 0) + (int)($counts['archived'] ?? 0);
        }

        $replacements = self::replacement_edges(array_keys(self::records_in_frameworks('flwcupkp_kp',
            $frameworkids, 'externalid ASC', $limit)), $limit);
        $impact = array_slice($coverage['findings'], 0, 12);

        return [
            'type' => 'CupkpCm3GovernanceDashboard',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => $coverage['status'],
            'scope' => $coverage['scope'],
            'summary' => [
                'frameworks' => count($frameworks),
                'review_rows' => $reviewcount,
                'published_rows' => $publishedcount,
                'deprecated_or_archived_rows' => $deprecatedcount,
                'replacement_edges' => count($replacements),
                'open_findings' => count($coverage['findings']),
            ],
            'frameworks' => $frameworks,
            'lifecycle_counts' => $statuscounts,
            'replacement_edges' => $replacements,
            'impact' => $impact,
            'recent_imports' => self::recent_imports(15),
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Preview a bulk import without writing curriculum rows.
     *
     * @param string $content
     * @param string $format
     * @param string $csvtype
     * @param string $sourcefile
     * @return array
     */
    public static function preview_bulk_import(string $content, string $format = 'json',
            string $csvtype = 'activity_mappings', string $sourcefile = ''): array {
        global $DB;

        $format = self::normalize_format($format);
        $csvtype = self::normalize_csv_type($csvtype);
        $content = trim($content);
        $errors = [];
        $warnings = [];
        $counts = [];
        $validation = [];
        $checksum = '';

        if ($content === '') {
            $errors[] = 'Bulk import content is empty.';
        } else if ($format === 'json') {
            $checksum = hash('sha256', $content);
            $package = json_decode($content, true);
            if (!is_array($package)) {
                $errors[] = 'Invalid JSON package.';
                $validation = ['valid' => false, 'errors' => $errors, 'warnings' => []];
            } else {
                $validation = validator::validate_package($package);
                $errors = array_merge($errors, $validation['errors'] ?? []);
                $warnings = array_merge($warnings, $validation['warnings'] ?? []);
                $counts = self::package_counts($package);
                $warnings = array_merge($warnings, self::package_duplicate_warnings($package));
            }
        } else {
            $parsed = self::parse_csv($content);
            $checksum = hash('sha256', 'csv:' . $csvtype . "\n" . self::csv_checksum_payload($parsed));
            $validation = import_service::validate_csv($content, $csvtype);
            $errors = array_merge($errors, $validation['errors'] ?? []);
            $warnings = array_merge($warnings, $validation['warnings'] ?? []);
            $counts = [
                'rows' => count($parsed['rows']),
                'headers' => count($parsed['headers']),
                'csv_type' => $csvtype,
            ];
        }

        $duplicate = false;
        $existingid = null;
        if ($checksum !== '') {
            $existing = $DB->get_record('flwcupkp_import', ['checksum' => $checksum], 'id', IGNORE_MISSING);
            if ($existing) {
                $duplicate = true;
                $existingid = (int)$existing->id;
                $warnings[] = 'An import batch with this checksum already exists.';
            }
        }

        return [
            'type' => 'CupkpCm3BulkImportPreview',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'format' => $format,
            'csvtype' => $format === 'csv' ? $csvtype : null,
            'sourcefile' => $sourcefile,
            'valid' => empty($errors),
            'would_write' => false,
            'transactional' => true,
            'duplicate' => $duplicate,
            'existing_importid' => $existingid,
            'checksum' => $checksum,
            'counts' => $counts,
            'validation' => $validation,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Apply a previously previewable bulk import.
     *
     * @param string $content
     * @param string $format
     * @param string $csvtype
     * @param string $sourcefile
     * @return array
     */
    public static function apply_bulk_import(string $content, string $format = 'json',
            string $csvtype = 'activity_mappings', string $sourcefile = ''): array {
        $preview = self::preview_bulk_import($content, $format, $csvtype, $sourcefile);
        if (!$preview['valid']) {
            throw new \invalid_parameter_exception('CM3 bulk import preview is not valid.');
        }

        if ($preview['format'] === 'json') {
            $result = import_service::import_json($content, $sourcefile);
        } else {
            $result = import_service::import_csv($content, (string)$preview['csvtype'], $sourcefile);
        }

        repository::audit('cm3_bulk_import_applied', 'import', (int)($result['importid'] ?? 0), [
            'format' => $preview['format'],
            'csvtype' => $preview['csvtype'],
            'sourcefile' => $sourcefile,
            'status' => $result['status'] ?? 'unknown',
            'entitycount' => $result['entitycount'] ?? 0,
            'duplicate' => !empty($preview['duplicate']),
            'checksum' => $preview['checksum'],
        ]);

        return [
            'type' => 'CupkpCm3BulkImportApplyResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'preview' => $preview,
            'result' => $result,
            'applied' => in_array((string)($result['status'] ?? ''), ['imported', 'already_imported'], true),
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Export a framework package with checksum metadata.
     *
     * @param int $frameworkid
     * @return array
     */
    public static function export_bulk_package(int $frameworkid = 0): array {
        $package = curriculum_manager::export_package($frameworkid);
        $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \coding_exception('Unable to encode C-UP-KP export package.');
        }

        return [
            'type' => 'CupkpCm3BulkExportPackage',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'frameworkid' => $frameworkid,
            'filename' => 'flw-cupkp-cm3-export-' . ($package['unit_code'] ?: 'framework') . '-' .
                date('Ymd-His') . '.json',
            'checksum' => hash('sha256', $json),
            'bytes' => strlen($json),
            'package' => $package,
            'json' => $json,
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Preview controlled rollback handling for a historical import batch.
     *
     * @param int $importid
     * @return array
     */
    public static function rollback_preview(int $importid): array {
        global $DB;

        if ($importid <= 0) {
            throw new \invalid_parameter_exception('Import ID is required for rollback preview.');
        }
        $import = $DB->get_record('flwcupkp_import', ['id' => $importid], '*', MUST_EXIST);
        $physical = false;
        $reason = 'This import table records batch checksums but not row-level ownership. CM3 can safely mark and audit a rollback request; it does not delete curriculum rows blindly.';

        return [
            'type' => 'CupkpCm3RollbackPreview',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'importid' => $importid,
            'validationstatus' => (string)($import->validationstatus ?? ''),
            'rollbackstatus' => (string)($import->rollbackstatus ?? ''),
            'entitycount' => (int)($import->entitycount ?? 0),
            'schemaversion' => (string)($import->schemaversion ?? ''),
            'sourcefile' => (string)($import->sourcefile ?? ''),
            'checksum' => (string)($import->checksum ?? ''),
            'physical_rollback_available' => $physical,
            'controlled_action' => 'mark_rollback_requested',
            'reason' => $reason,
            'would_write' => false,
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Mark an import batch with a controlled rollback request and audit the decision.
     *
     * @param int $importid
     * @param string $reason
     * @return array
     */
    public static function request_rollback(int $importid, string $reason = ''): array {
        global $DB, $USER;

        $preview = self::rollback_preview($importid);
        $reason = trim($reason) !== '' ? trim($reason) : $preview['reason'];
        $DB->set_field('flwcupkp_import', 'rollbackstatus', 'rollback_requested', ['id' => $importid]);
        repository::audit('cm3_import_rollback_requested', 'import', $importid, [
            'reason' => $reason,
            'physical_rollback_available' => $preview['physical_rollback_available'],
            'previous_rollbackstatus' => $preview['rollbackstatus'],
            'userid' => $USER->id ?? 0,
        ]);

        $after = self::rollback_preview($importid);
        return [
            'type' => 'CupkpCm3RollbackRequestResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'requested' => true,
            'preview' => $preview,
            'after' => $after,
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Recent import batches with rollback status.
     *
     * @param int $limit
     * @return array
     */
    public static function recent_imports(int $limit = 20): array {
        global $DB;

        $limit = max(1, min(100, $limit));
        $records = $DB->get_records('flwcupkp_import', null, 'timecreated DESC, id DESC', '*', 0, $limit);
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'id' => (int)$record->id,
                'sourcefile' => (string)($record->sourcefile ?? ''),
                'schemaversion' => (string)($record->schemaversion ?? ''),
                'validationstatus' => (string)($record->validationstatus ?? ''),
                'rollbackstatus' => (string)($record->rollbackstatus ?? ''),
                'entitycount' => (int)($record->entitycount ?? 0),
                'checksum' => (string)($record->checksum ?? ''),
                'timecreated' => (int)($record->timecreated ?? 0),
            ];
        }
        return $rows;
    }

    /**
     * Add coverage findings.
     *
     * @param array $competencies
     * @param array $ups
     * @param array $kps
     * @param array $objects
     * @param array $relations
     * @param array $objectindex
     * @param array $evidenceindex
     * @param array $categories
     * @return array
     */
    private static function coverage_findings(array $competencies, array $ups, array $kps, array $objects,
            array $relations, array $objectindex, array $evidenceindex, array $categories): array {
        $findings = [];

        $uporphans = [];
        foreach ($ups as $up) {
            if (empty($relations['comp_by_up'][(int)$up->id])) {
                $uporphans[] = self::label('up', $up);
            }
        }
        $kporphans = [];
        foreach ($kps as $kp) {
            if (empty($relations['up_by_kp'][(int)$kp->id])) {
                $kporphans[] = self::label('kp', $kp);
            }
        }
        $objectorphans = [];
        foreach ($objects as $object) {
            if (empty($objectindex['maps_by_object'][(int)$object->id])) {
                $objectorphans[] = self::label('object', $object);
            }
        }
        $orphaned = count($uporphans) + count($kporphans) + count($objectorphans);
        if ($orphaned > 0) {
            $findings[] = self::finding('MEDIUM', 'orphans',
                'C-UP-KP rows exist without their expected parent/content links.', $orphaned,
                array_slice(array_merge($uporphans, $kporphans, $objectorphans), 0, 8));
        }

        $teaching = $objectindex['rolekeys']['TEACHES'] ?? [];
        $assessment = array_merge($objectindex['rolekeys']['ASSESSES'] ?? [], $objectindex['rolekeys']['EVIDENCE_FOR'] ?? []);
        $taughtnotassessed = array_values(array_diff(array_keys($teaching), array_keys($assessment)));
        if ($taughtnotassessed) {
            $findings[] = self::finding('MEDIUM', 'taught_not_assessed',
                'Targets have teaching coverage but no assessment or direct evidence route.', count($taughtnotassessed),
                self::target_key_samples($taughtnotassessed, $competencies, $ups, $kps));
        }
        $assessednottaught = array_values(array_diff(array_keys($assessment), array_keys($teaching)));
        if ($assessednottaught) {
            $findings[] = self::finding('MEDIUM', 'assessed_not_taught',
                'Targets have assessment coverage but no teaching route.', count($assessednottaught),
                self::target_key_samples($assessednottaught, $competencies, $ups, $kps));
        }

        $recognitiononly = [];
        foreach ($ups as $up) {
            if (!self::is_interaction_or_production_up($up)) {
                continue;
            }
            $key = 'up:' . (int)$up->id;
            $hasrecognition = !empty($objectindex['recognition_keys'][$key]) ||
                !empty($evidenceindex['recognition_keys'][$key]);
            $hasnonrecognition = !empty($objectindex['nonrecognition_keys'][$key]) ||
                !empty($evidenceindex['nonrecognition_keys'][$key]);
            if ($hasrecognition && !$hasnonrecognition) {
                $recognitiononly[] = self::label('up', $up);
            }
        }
        if ($recognitiononly) {
            $findings[] = self::finding('HIGH', 'interaction_recognition_only_evidence',
                'Interaction or production UP targets only have recognition-level evidence routes.',
                count($recognitiononly), array_slice($recognitiononly, 0, 8));
        }

        $missingprereq = [];
        foreach ($kps as $kp) {
            if (empty($relations['prereq_by_kp'][(int)$kp->id])) {
                $missingprereq[] = self::label('kp', $kp);
            }
        }
        if ($missingprereq) {
            $findings[] = self::finding('LOW', 'missing_prerequisite',
                'KPs without prerequisite edges should be reviewed for intended introductory status.',
                count($missingprereq), array_slice($missingprereq, 0, 8));
        }

        $deprecated = self::deprecated_reference_samples($competencies, $ups, $kps, $objects, $relations, $objectindex);
        if ($deprecated) {
            $findings[] = self::finding('MEDIUM', 'deprecated_references',
                'Deprecated or archived curriculum rows are still referenced by active coverage mappings.',
                count($deprecated), array_slice($deprecated, 0, 8));
        }

        $ceilings = self::evidence_ceiling_samples($objectindex, $evidenceindex, $competencies, $ups, $kps);
        if ($ceilings) {
            $findings[] = self::finding('MEDIUM', 'evidence_ceilings',
                'Some routes appear to cap evidence at recognition or explicitly declare an evidence ceiling.',
                count($ceilings), array_slice($ceilings, 0, 8));
        }

        $coverages = array_filter(array_map(static function(array $category): ?float {
            return ((int)$category['total'] > 0) ? (float)$category['percent'] : null;
        }, $categories), static function($value): bool {
            return $value !== null;
        });
        if ($coverages) {
            $min = min($coverages);
            $max = max($coverages);
            if (($max - $min) >= 50.0) {
                $findings[] = self::finding('LOW', 'coverage_imbalance',
                    'Coverage areas are uneven enough to require governance review.',
                    1, ['min=' . format_float($min, 1) . '%, max=' . format_float($max, 1) . '%']);
            }
        }

        return $findings;
    }

    /**
     * Build relationship indexes.
     *
     * @param array $compup
     * @param array $upkp
     * @param array $prereqs
     * @return array
     */
    private static function relationship_indexes(array $compup, array $upkp, array $prereqs): array {
        $relations = [
            'up_by_comp' => [],
            'comp_by_up' => [],
            'kp_by_up' => [],
            'up_by_kp' => [],
            'prereq_by_kp' => [],
            'replacement_by_kp' => [],
        ];
        foreach ($compup as $row) {
            $relations['up_by_comp'][(int)$row->competencyid][(int)$row->upid] = true;
            $relations['comp_by_up'][(int)$row->upid][(int)$row->competencyid] = true;
        }
        foreach ($upkp as $row) {
            $relations['kp_by_up'][(int)$row->upid][(int)$row->kpid] = true;
            $relations['up_by_kp'][(int)$row->kpid][(int)$row->upid] = true;
        }
        foreach ($prereqs as $row) {
            $relations['prereq_by_kp'][(int)$row->kpid][(int)$row->prereqkpid] = true;
            if (self::is_replacement_type((string)($row->relationshiptype ?? ''))) {
                $relations['replacement_by_kp'][(int)$row->kpid][(int)$row->prereqkpid] = true;
            }
        }
        return $relations;
    }

    /**
     * Build object mapping indexes.
     *
     * @param array $objectmaps
     * @param array $objects
     * @return array
     */
    private static function object_mapping_indexes(array $objectmaps, array $objects): array {
        $index = [
            'maps_by_object' => [],
            'maps_by_target' => [],
            'rolekeys' => [
                'TEACHES' => [],
                'PRACTICES' => [],
                'ASSESSES' => [],
                'EVIDENCE_FOR' => [],
            ],
            'recognition_keys' => [],
            'nonrecognition_keys' => [],
            'ceiling_keys' => [],
        ];
        foreach ($objectmaps as $map) {
            $objectid = (int)$map->objectid;
            $object = $objects[$objectid] ?? null;
            $key = (string)$map->targettype . ':' . (int)$map->targetid;
            $role = self::canonical_role($map, $object);
            $index['maps_by_object'][$objectid][] = $map;
            $index['maps_by_target'][$key][] = $map;
            $index['rolekeys'][$role][$key] = true;
            if (self::is_recognition_strength((string)($map->evidencestrength ?? ''))) {
                $index['recognition_keys'][$key] = true;
                if (in_array($role, ['ASSESSES', 'EVIDENCE_FOR'], true)) {
                    $index['ceiling_keys'][$key] = true;
                }
            } else {
                $index['nonrecognition_keys'][$key] = true;
            }
        }
        return $index;
    }

    /**
     * Build evidence indexes by target key.
     *
     * @param array $evidence
     * @return array
     */
    private static function evidence_indexes(array $evidence): array {
        $index = [
            'by_target' => [],
            'recognition_keys' => [],
            'nonrecognition_keys' => [],
            'ceiling_keys' => [],
        ];
        foreach ($evidence as $row) {
            $key = (string)$row->targettype . ':' . (int)$row->targetid;
            $index['by_target'][$key][] = $row;
            $strength = (string)($row->evidencestrength ?? '');
            if (self::is_recognition_strength($strength)) {
                $index['recognition_keys'][$key] = true;
            } else {
                $index['nonrecognition_keys'][$key] = true;
            }
            if (stripos((string)($row->rubricjson ?? ''), 'ceiling') !== false) {
                $index['ceiling_keys'][$key] = true;
            }
        }
        return $index;
    }

    /**
     * Covered competencies.
     *
     * @param array $competencies
     * @param array $relations
     * @param array $objectindex
     * @return array
     */
    private static function covered_competencies(array $competencies, array $relations, array $objectindex): array {
        $covered = [];
        foreach ($competencies as $competency) {
            $compid = (int)$competency->id;
            $directkey = 'competency:' . $compid;
            if (!empty($objectindex['maps_by_target'][$directkey])) {
                $covered[$compid] = true;
                continue;
            }
            foreach (array_keys($relations['up_by_comp'][$compid] ?? []) as $upid) {
                if (!empty($objectindex['maps_by_target']['up:' . $upid])) {
                    $covered[$compid] = true;
                    break;
                }
                foreach (array_keys($relations['kp_by_up'][(int)$upid] ?? []) as $kpid) {
                    if (!empty($objectindex['maps_by_target']['kp:' . $kpid])) {
                        $covered[$compid] = true;
                        break 2;
                    }
                }
            }
        }
        return $covered;
    }

    /**
     * Target coverage by object map role.
     *
     * @param array $records
     * @param string $type
     * @param array $rolekeys
     * @return array
     */
    private static function target_coverage(array $records, string $type, array $rolekeys): array {
        $covered = [];
        foreach ($records as $record) {
            $key = $type . ':' . (int)$record->id;
            if (!empty($rolekeys[$key])) {
                $covered[(int)$record->id] = true;
            }
        }
        return $covered;
    }

    /**
     * Evidence quality coverage.
     *
     * @param array $evidence
     * @return array
     */
    private static function evidence_quality_coverage(array $evidence): array {
        $covered = 0;
        foreach ($evidence as $row) {
            $rubric = (string)($row->rubricjson ?? '');
            $hasmetadata = stripos($rubric, 'evidence_policy_version') !== false ||
                stripos($rubric, 'quality') !== false ||
                stripos($rubric, 'result_state') !== false;
            $hasbasicquality = ((string)($row->evidencestrength ?? '') !== '') &&
                property_exists($row, 'confidence') && $row->confidence !== null;
            if ($hasmetadata || $hasbasicquality) {
                $covered++;
            }
        }
        return ['covered' => $covered, 'total' => count($evidence)];
    }

    /**
     * Production and interaction UP coverage.
     *
     * @param array $ups
     * @param array $objectindex
     * @param array $evidenceindex
     * @return array
     */
    private static function production_interaction_coverage(array $ups, array $objectindex, array $evidenceindex): array {
        $total = 0;
        $covered = 0;
        foreach ($ups as $up) {
            if (!self::is_interaction_or_production_up($up)) {
                continue;
            }
            $total++;
            $key = 'up:' . (int)$up->id;
            if (!empty($objectindex['nonrecognition_keys'][$key]) ||
                    !empty($evidenceindex['nonrecognition_keys'][$key])) {
                $covered++;
            }
        }
        return ['total' => $total, 'covered' => $covered];
    }

    /**
     * Return scoped framework IDs.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    private static function scoped_framework_ids(int $frameworkid, int $courseid, string $unitcode, int $limit): array {
        global $DB;

        if ($frameworkid > 0) {
            return $DB->record_exists('flwcupkp_framework', ['id' => $frameworkid]) ? [$frameworkid] : [];
        }

        $params = [];
        $where = ['frameworkid IS NOT NULL', 'frameworkid > 0'];
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($courseid > 0 || $unitcode !== '') {
            $records = $DB->get_records_sql(
                'SELECT DISTINCT frameworkid
                   FROM {flwcupkp_object}
                  WHERE ' . implode(' AND ', $where) . '
               ORDER BY frameworkid ASC',
                $params,
                0,
                $limit
            );
            $ids = [];
            foreach ($records as $record) {
                $ids[] = (int)$record->frameworkid;
            }
            return array_values(array_unique(array_filter($ids)));
        }

        $records = $DB->get_records('flwcupkp_framework', null, 'name ASC, externalid ASC', 'id', 0, $limit);
        return array_values(array_map('intval', array_keys($records)));
    }

    /**
     * Get records in framework scope.
     *
     * @param string $table
     * @param array $frameworkids
     * @param string $order
     * @param int $limit
     * @return array
     */
    private static function records_in_frameworks(string $table, array $frameworkids, string $order, int $limit): array {
        global $DB;

        if (!$frameworkids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($frameworkids, SQL_PARAMS_NAMED, 'fw');
        return $DB->get_records_select($table, 'frameworkid ' . $insql, $params, $order, '*', 0, $limit);
    }

    /**
     * Get framework records.
     *
     * @param array $frameworkids
     * @param int $limit
     * @return array
     */
    private static function framework_rows(array $frameworkids, int $limit): array {
        global $DB;

        if (!$frameworkids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($frameworkids, SQL_PARAMS_NAMED, 'fw');
        $records = $DB->get_records_select('flwcupkp_framework', 'id ' . $insql, $params,
            'name ASC, externalid ASC', '*', 0, $limit);
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'id' => (int)$record->id,
                'externalid' => (string)$record->externalid,
                'name' => (string)$record->name,
                'version' => (string)($record->version ?? ''),
                'status' => (string)($record->status ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * Get scoped learning objects.
     *
     * @param array $frameworkids
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    private static function scoped_objects(array $frameworkids, int $courseid, string $unitcode, int $limit): array {
        global $DB;

        if (!$frameworkids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($frameworkids, SQL_PARAMS_NAMED, 'fw');
        $where = ['frameworkid ' . $insql];
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        return $DB->get_records_select('flwcupkp_object', implode(' AND ', $where), $params,
            'unitcode ASC, lesson ASC, externalid ASC', '*', 0, $limit);
    }

    /**
     * Get competency-UP rows in scope.
     *
     * @param array $compids
     * @param array $upids
     * @param int $limit
     * @return array
     */
    private static function comp_up_records(array $compids, array $upids, int $limit): array {
        return self::mapping_records('flwcupkp_comp_up', [
            'competencyid' => $compids,
            'upid' => $upids,
        ], 'sortorder ASC, id ASC', $limit);
    }

    /**
     * Get UP-KP rows in scope.
     *
     * @param array $upids
     * @param array $kpids
     * @param int $limit
     * @return array
     */
    private static function up_kp_records(array $upids, array $kpids, int $limit): array {
        return self::mapping_records('flwcupkp_up_kp', [
            'upid' => $upids,
            'kpid' => $kpids,
        ], 'sortorder ASC, id ASC', $limit);
    }

    /**
     * Get prerequisite rows in scope.
     *
     * @param array $kpids
     * @param int $limit
     * @return array
     */
    private static function kp_prereq_records(array $kpids, int $limit): array {
        return self::mapping_records('flwcupkp_kp_prereq', [
            'kpid' => $kpids,
        ], 'id ASC', $limit);
    }

    /**
     * Get object-map rows in scope.
     *
     * @param array $objectids
     * @param int $limit
     * @return array
     */
    private static function object_map_records(array $objectids, int $limit): array {
        return self::mapping_records('flwcupkp_object_map', [
            'objectid' => $objectids,
        ], 'id ASC', $limit);
    }

    /**
     * Generic mapping query for one or more scoped columns.
     *
     * @param string $table
     * @param array $columns
     * @param string $order
     * @param int $limit
     * @return array
     */
    private static function mapping_records(string $table, array $columns, string $order, int $limit): array {
        global $DB;

        $where = [];
        $params = [];
        $prefix = 0;
        foreach ($columns as $column => $ids) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) {
                continue;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'm' . $prefix . '_');
            $where[] = $column . ' ' . $insql;
            $params += $inparams;
            $prefix++;
        }
        if (!$where) {
            return [];
        }
        return $DB->get_records_select($table, implode(' OR ', $where), $params, $order, '*', 0, $limit);
    }

    /**
     * Get evidence in scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param array $competencies
     * @param array $ups
     * @param array $kps
     * @param array $objects
     * @param int $limit
     * @return array
     */
    private static function scoped_evidence(int $courseid, string $unitcode, array $competencies, array $ups,
            array $kps, array $objects, int $limit): array {
        global $DB;

        $where = [];
        $params = [];
        if ($courseid > 0) {
            $where[] = 'courseid = :evcourseid';
            $params['evcourseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'unitcode = :evunitcode';
            $params['evunitcode'] = $unitcode;
        }

        $targetconditions = [];
        foreach ([
            'competency' => array_keys($competencies),
            'up' => array_keys($ups),
            'kp' => array_keys($kps),
        ] as $type => $ids) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) {
                continue;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ev' . $type . '_');
            $targetconditions[] = '(targettype = :evtype' . $type . ' AND targetid ' . $insql . ')';
            $params['evtype' . $type] = $type;
            $params += $inparams;
        }
        $objectids = array_values(array_filter(array_map('intval', array_keys($objects))));
        if ($objectids) {
            [$insql, $inparams] = $DB->get_in_or_equal($objectids, SQL_PARAMS_NAMED, 'evobject_');
            $targetconditions[] = 'objectid ' . $insql;
            $params += $inparams;
        }
        if ($targetconditions) {
            $where[] = '(' . implode(' OR ', $targetconditions) . ')';
        }
        $sqlwhere = $where ? implode(' AND ', $where) : '1=1';
        return $DB->get_records_select('flwcupkp_evidence', $sqlwhere, $params, 'timecreated DESC, id DESC', '*', 0, $limit);
    }

    /**
     * Count rows by status.
     *
     * @param string $type
     * @param string $table
     * @param array $frameworkids
     * @return array
     */
    private static function status_counts(string $type, string $table, array $frameworkids): array {
        global $DB;

        if (!$frameworkids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($frameworkids, SQL_PARAMS_NAMED, 'statusfw');
        $column = $type === 'framework' ? 'id' : 'frameworkid';
        $records = $DB->get_records_sql(
            "SELECT status, COUNT(1) AS rowcount
               FROM {{$table}}
              WHERE {$column} {$insql}
           GROUP BY status
           ORDER BY status ASC",
            $params
        );
        $counts = [];
        foreach ($records as $record) {
            $status = trim((string)($record->status ?? ''));
            $counts[$status !== '' ? $status : 'unknown'] = (int)$record->rowcount;
        }
        return $counts;
    }

    /**
     * Replacement edges.
     *
     * @param array $kpids
     * @param int $limit
     * @return array
     */
    private static function replacement_edges(array $kpids, int $limit): array {
        global $DB;

        if (!$kpids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($kpids), SQL_PARAMS_NAMED, 'rkp');
        $records = $DB->get_records_select('flwcupkp_kp_prereq', 'kpid ' . $insql, $params, 'id ASC', '*', 0, $limit);
        $rows = [];
        foreach ($records as $record) {
            if (!self::is_replacement_type((string)($record->relationshiptype ?? ''))) {
                continue;
            }
            $rows[] = [
                'id' => (int)$record->id,
                'source_kpid' => (int)$record->kpid,
                'replacement_kpid' => (int)$record->prereqkpid,
                'relationshiptype' => (string)$record->relationshiptype,
                'requirement' => (string)($record->requirement ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * JSON package counts.
     *
     * @param array $package
     * @return array
     */
    private static function package_counts(array $package): array {
        $counts = [];
        foreach (self::PACKAGE_KEYS as $key) {
            if (isset($package[$key]) && is_array($package[$key])) {
                $counts[$key] = count($package[$key]);
            }
        }
        return $counts;
    }

    /**
     * Duplicate warnings inside a package.
     *
     * @param array $package
     * @return array
     */
    private static function package_duplicate_warnings(array $package): array {
        $warnings = [];
        foreach (['frameworks', 'competencies', 'use_points', 'knowledge_points', 'learning_objects'] as $key) {
            $seen = [];
            foreach (($package[$key] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $externalid = trim((string)($row['externalid'] ?? ($row['object_externalid'] ?? '')));
                if ($externalid === '') {
                    continue;
                }
                if (isset($seen[$externalid])) {
                    $warnings[] = 'Duplicate package externalid in ' . $key . ': ' . $externalid;
                }
                $seen[$externalid] = true;
            }
        }
        return $warnings;
    }

    /**
     * Parse CSV content for dry-run checksum and counts.
     *
     * @param string $csv
     * @return array
     */
    private static function parse_csv(string $csv): array {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        if (trim($csv) === '') {
            return ['headers' => [], 'rows' => [], 'errors' => ['CSV content is empty.']];
        }

        $errors = [];
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return ['headers' => [], 'rows' => [], 'errors' => ['Unable to open temporary CSV stream.']];
        }
        fwrite($handle, $csv);
        rewind($handle);

        $headerrow = fgetcsv($handle);
        if ($headerrow === false) {
            fclose($handle);
            return ['headers' => [], 'rows' => [], 'errors' => ['CSV header row is missing.']];
        }
        $headers = array_map([self::class, 'normalize_csv_header'], $headerrow);
        $rows = [];
        $rownumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rownumber++;
            if (count($values) === 1 && trim((string)$values[0]) === '') {
                continue;
            }
            $values = array_pad($values, count($headers), '');
            $row = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = trim((string)$values[$idx]);
            }
            $row['_rownum'] = $rownumber;
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
    }

    /**
     * Replicate import_service CSV checksum payload.
     *
     * @param array $parsed
     * @return string
     */
    private static function csv_checksum_payload(array $parsed): string {
        $rows = $parsed['rows'] ?? [];
        foreach ($rows as &$row) {
            unset($row['_rownum']);
            ksort($row);
        }
        unset($row);
        return json_encode([$parsed['headers'] ?? [], $rows], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Normalize a CSV header.
     *
     * @param string $header
     * @return string
     */
    private static function normalize_csv_header(string $header): string {
        return strtolower(trim(str_replace([' ', '-'], '_', $header)));
    }

    /**
     * Normalize import format.
     *
     * @param string $format
     * @return string
     */
    private static function normalize_format(string $format): string {
        $format = strtolower(trim($format));
        if (!in_array($format, self::IMPORT_FORMATS, true)) {
            throw new \invalid_parameter_exception('Unsupported CM3 import format.');
        }
        return $format;
    }

    /**
     * Normalize CSV type labels.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_csv_type(string $type): string {
        $type = strtolower(trim(str_replace('-', '_', $type)));
        $aliases = [
            'activity' => 'activity_mappings',
            'activity_mapping' => 'activity_mappings',
            'activity_cupkp_mapping' => 'activity_mappings',
            'quiz' => 'quiz_kp_mappings',
            'quiz_kp_mapping' => 'quiz_kp_mappings',
        ];
        $type = $aliases[$type] ?? $type;
        if (!in_array($type, self::CSV_TYPES, true)) {
            throw new \invalid_parameter_exception('Unsupported C-UP-KP CSV import type.');
        }
        return $type;
    }

    /**
     * Create a category row.
     *
     * @param string $code
     * @param string $label
     * @param int $total
     * @param int $covered
     * @param string $detail
     * @return array
     */
    private static function category(string $code, string $label, int $total, int $covered, string $detail): array {
        $percent = $total > 0 ? round(($covered / $total) * 100, 1) : 100.0;
        return [
            'code' => $code,
            'label' => $label,
            'total' => $total,
            'covered' => $covered,
            'missing' => max(0, $total - $covered),
            'percent' => $percent,
            'detail' => $detail,
        ];
    }

    /**
     * Create a finding row.
     *
     * @param string $severity
     * @param string $code
     * @param string $message
     * @param int $count
     * @param array $samples
     * @return array
     */
    private static function finding(string $severity, string $code, string $message, int $count = 1,
            array $samples = []): array {
        return [
            'severity' => strtoupper($severity),
            'code' => $code,
            'message' => $message,
            'count' => $count,
            'samples' => array_values($samples),
        ];
    }

    /**
     * Determine if findings block readiness.
     *
     * @param array $findings
     * @return bool
     */
    private static function blocking_findings(array $findings): bool {
        foreach ($findings as $finding) {
            if (in_array(strtoupper((string)($finding['severity'] ?? '')), ['BLOCKER', 'HIGH'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Canonical C3 role for an object map.
     *
     * @param \stdClass $map
     * @param \stdClass|null $object
     * @return string
     */
    private static function canonical_role(\stdClass $map, ?\stdClass $object): string {
        try {
            return content_evidence_mapping_contract::canonical_pedagogical_role(
                (string)($map->role ?? ''),
                $object ? (string)($object->purpose ?? '') : '',
                $object ? (string)($object->objecttype ?? '') : ''
            );
        } catch (\Throwable $e) {
            $label = strtolower((string)($map->role ?? '') . ' ' . ($object->purpose ?? '') . ' ' .
                ($object->objecttype ?? ''));
            if (strpos($label, 'teach') !== false || strpos($label, 'lesson') !== false) {
                return 'TEACHES';
            }
            if (strpos($label, 'assess') !== false || strpos($label, 'quiz') !== false ||
                    strpos($label, 'checkpoint') !== false) {
                return 'ASSESSES';
            }
            if (strpos($label, 'evidence') !== false || strpos($label, 'project') !== false) {
                return 'EVIDENCE_FOR';
            }
            return 'PRACTICES';
        }
    }

    /**
     * Recognition-level strength.
     *
     * @param string $strength
     * @return bool
     */
    private static function is_recognition_strength(string $strength): bool {
        $strength = strtolower(trim($strength));
        return $strength === '' || strpos($strength, 'recognition') !== false;
    }

    /**
     * Interaction/production UP detector.
     *
     * @param \stdClass $up
     * @return bool
     */
    private static function is_interaction_or_production_up(\stdClass $up): bool {
        $label = strtolower(trim((string)($up->languagemode ?? '') . ' ' . (string)($up->interactiontype ?? '') .
            ' ' . (string)($up->evidencerequirements ?? '') . ' ' . (string)($up->title ?? '')));
        if (trim((string)($up->interactiontype ?? '')) !== '') {
            return true;
        }
        foreach (['speaking', 'writing', 'production', 'interaction', 'dialogue', 'presentation'] as $needle) {
            if (strpos($label, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Replacement relationship detector.
     *
     * @param string $type
     * @return bool
     */
    private static function is_replacement_type(string $type): bool {
        $type = strtolower(str_replace('-', '_', trim($type)));
        return in_array($type, ['replaced_by', 'replacement', 'superseded_by'], true);
    }

    /**
     * Deprecated-reference samples.
     *
     * @param array $competencies
     * @param array $ups
     * @param array $kps
     * @param array $objects
     * @param array $relations
     * @param array $objectindex
     * @return array
     */
    private static function deprecated_reference_samples(array $competencies, array $ups, array $kps,
            array $objects, array $relations, array $objectindex): array {
        $samples = [];
        foreach (['competency' => $competencies, 'up' => $ups, 'kp' => $kps, 'object' => $objects] as $type => $records) {
            foreach ($records as $record) {
                $status = strtolower((string)($record->status ?? ''));
                if (!in_array($status, ['deprecated', 'archived'], true)) {
                    continue;
                }
                $referenced = false;
                if ($type === 'competency') {
                    $referenced = !empty($relations['up_by_comp'][(int)$record->id]) ||
                        !empty($objectindex['maps_by_target']['competency:' . (int)$record->id]);
                } else if ($type === 'up') {
                    $referenced = !empty($relations['comp_by_up'][(int)$record->id]) ||
                        !empty($relations['kp_by_up'][(int)$record->id]) ||
                        !empty($objectindex['maps_by_target']['up:' . (int)$record->id]);
                } else if ($type === 'kp') {
                    $referenced = !empty($relations['up_by_kp'][(int)$record->id]) ||
                        !empty($relations['prereq_by_kp'][(int)$record->id]) ||
                        !empty($objectindex['maps_by_target']['kp:' . (int)$record->id]);
                } else if ($type === 'object') {
                    $referenced = !empty($objectindex['maps_by_object'][(int)$record->id]);
                }
                if ($referenced) {
                    $samples[] = self::label($type, $record);
                }
            }
        }
        return $samples;
    }

    /**
     * Evidence ceiling samples.
     *
     * @param array $objectindex
     * @param array $evidenceindex
     * @param array $competencies
     * @param array $ups
     * @param array $kps
     * @return array
     */
    private static function evidence_ceiling_samples(array $objectindex, array $evidenceindex, array $competencies,
            array $ups, array $kps): array {
        $keys = array_unique(array_merge(
            array_keys($objectindex['ceiling_keys'] ?? []),
            array_keys($evidenceindex['ceiling_keys'] ?? [])
        ));
        return self::target_key_samples($keys, $competencies, $ups, $kps);
    }

    /**
     * Target key samples.
     *
     * @param array $keys
     * @param array $competencies
     * @param array $ups
     * @param array $kps
     * @return array
     */
    private static function target_key_samples(array $keys, array $competencies, array $ups, array $kps): array {
        $samples = [];
        foreach (array_slice($keys, 0, 8) as $key) {
            [$type, $id] = array_pad(explode(':', (string)$key, 2), 2, '');
            $id = (int)$id;
            if ($type === 'competency' && isset($competencies[$id])) {
                $samples[] = self::label('competency', $competencies[$id]);
            } else if ($type === 'up' && isset($ups[$id])) {
                $samples[] = self::label('up', $ups[$id]);
            } else if ($type === 'kp' && isset($kps[$id])) {
                $samples[] = self::label('kp', $kps[$id]);
            } else {
                $samples[] = (string)$key;
            }
        }
        return $samples;
    }

    /**
     * Label one record.
     *
     * @param string $type
     * @param \stdClass $record
     * @return string
     */
    private static function label(string $type, \stdClass $record): string {
        $externalid = (string)($record->externalid ?? ('#' . (int)($record->id ?? 0)));
        $title = (string)($record->title ?? ($record->name ?? ''));
        return strtoupper($type) . ' ' . $externalid . ($title !== '' ? ' - ' . $title : '');
    }
}

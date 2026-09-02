<?php
// Program 3 Gate C1B ontology boundary validation.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Guards C/UP/KP ontology boundaries on top of the frozen C1 model.
 */
final class ontology_boundary {
    /** Program 3 ontology boundary gate. */
    public const GATE = 'P3_C1B';

    /** Frozen C1B contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_ONTOLOGY_BOUNDARY_V1';

    /** @var array Accepted lifecycle labels already used by FLW content. */
    private const ENTITY_STATUSES = [
        'draft',
        'review',
        'approved',
        'published',
        'validated',
        'active',
        'reference',
        'pilot',
        'inactive',
        'archived',
        'deprecated',
        'retired',
        'test',
    ];

    /** @var array Statuses that must not receive new active links. */
    private const LINK_INCOMPATIBLE_STATUSES = ['archived', 'deprecated', 'retired'];

    /** @var array Accepted C/UP and UP/KP mapping roles. */
    private const RELATION_ROLES = [
        'required',
        'supporting',
        'support',
        'optional',
        'extension',
        'remediation',
        'enrichment',
        'assessment',
        'evidence',
    ];

    /** @var array Accepted learning-object and object-map roles. */
    private const OBJECT_ROLES = [
        'teaches',
        'trains',
        'practice',
        'practices',
        'assessment',
        'assesses',
        'evidence_for',
        'diagnostic',
        'placement',
        'checkpoint',
        'teacher_observation',
        'external_assessment',
        'review',
        'review_of',
        'remediation',
        'extension',
        'project',
        'stt_task',
    ];

    /** @var array Accepted object purposes. */
    private const OBJECT_PURPOSES = [
        'lesson',
        'teach',
        'practice',
        'assessment',
        'diagnostic',
        'placement',
        'checkpoint',
        'teacher_observation',
        'external_assessment',
        'review',
        'remediation',
        'extension',
        'project',
        'stt_task',
        'instruction',
        'practice_evidence',
        'performance_evidence',
        'integrated_performance',
    ];

    /** @var array Accepted evidence-strength labels. */
    private const EVIDENCE_STRENGTHS = [
        'recognition',
        'guided_performance',
        'controlled_production',
        'independent_performance',
        'direct_performance',
        'indirect_signal',
        'diagnostic',
        'checkpoint',
        'weak',
        'medium',
        'strong',
    ];

    /** @var array Current prerequisite labels. C2 will freeze full graph semantics. */
    private const PREREQ_RELATIONSHIP_TYPES = [
        'prerequisite',
        'requires',
        'supports',
        'extends',
        'review_of',
        'alternative_to',
        'replaced_by',
        'meaning_support',
        'production_support',
        'genre_model',
        'language_resource',
        'discourse_support',
        'genre_support',
    ];

    /** @var array Accepted prerequisite requirement strengths. */
    private const PREREQ_REQUIREMENTS = ['mandatory', 'recommended', 'optional'];

    /** @var array Fields that make a row look like a UP task/use definition. */
    private const UP_OWNED_FIELDS = [
        'action_statement',
        'actionstatement',
        'intention',
        'context',
        'observable_action',
        'observableaction',
        'conditions',
        'success_criteria',
        'successcriteria',
        'language_mode',
        'languagemode',
        'interaction_type',
        'interactiontype',
        'rubric_ref',
        'rubricref',
    ];

    /** @var array Fields that make a row look like a KP knowledge definition. */
    private const KP_OWNED_FIELDS = [
        'form',
        'formtext',
        'meaning_function',
        'meaningfunction',
        'usage_constraints',
        'usageconstraints',
        'estimated_learning_load',
        'learningload',
        'new_knowledge',
        'target_knowledge',
        'grammar',
        'vocabulary',
    ];

    /** @var array Fields that make a row look like a competency definition. */
    private const COMPETENCY_OWNED_FIELDS = [
        'can_do',
        'cando',
        'scope',
        'evidence_rule',
        'evidencerule',
        'moodle_competency_id',
        'moodlecompetencyid',
    ];

    /**
     * Return the frozen C1B contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpOntologyBoundary',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => canonical_domain_model::CONTRACT_VERSION,
            'objective' => 'Prevent C/KP/UP category drift.',
            'operational_tests' => [
                'competency' => 'Is this a meaningful integrated ability?',
                'kp' => 'Does linguistic/content knowledge itself define the object?',
                'up' => 'Is this the same knowledge but a different required use or demonstration?',
            ],
            'validation' => [
                'detects' => [
                    'overly_narrow_competency',
                    'kp_written_as_task',
                    'up_containing_unmodeled_new_knowledge',
                    'semantic_duplicate_across_types',
                    'unsupported_status_or_role_vocabulary',
                    'lifecycle_incompatible_mapping',
                ],
                'does_not_do' => [
                    'adaptive_decision_logic',
                    'mastery_recalculation',
                    'raw_moodle_log_scraping',
                    'C2_graph_semantics_freeze',
                ],
            ],
            'vocabulary' => [
                'entity_statuses' => self::ENTITY_STATUSES,
                'relation_roles' => self::RELATION_ROLES,
                'object_roles' => self::OBJECT_ROLES,
                'object_purposes' => self::OBJECT_PURPOSES,
                'evidence_strengths' => self::EVIDENCE_STRENGTHS,
                'prereq_relationship_types_current' => self::PREREQ_RELATIONSHIP_TYPES,
                'prereq_requirements' => self::PREREQ_REQUIREMENTS,
            ],
            'authoring_reference' => self::authoring_reference(),
            'source_history_boundary' => canonical_domain_model::contract()['source_history_boundary'],
        ];
    }

    /**
     * Validate a whole package against C1B ontology boundaries.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package(array $package): array {
        $errors = [];
        $warnings = [];
        $details = [];
        $entitysets = self::collect_package_entities($package);

        foreach ($entitysets as $entitytype => $rows) {
            foreach ($rows as $externalid => $row) {
                $result = self::validate_curriculum_row($entitytype, $row);
                self::merge_result($entitytype . ':' . $externalid, $result, $errors, $warnings, $details);
            }
        }

        self::detect_semantic_duplicates($entitysets, $errors, $details);
        self::validate_package_mappings($package, $entitysets, $errors, $warnings, $details);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'contract' => self::CONTRACT_VERSION,
        ];
    }

    /**
     * Validate a single curriculum definition row.
     *
     * @param string $entitytype
     * @param array $row
     * @return array
     */
    public static function validate_curriculum_row(string $entitytype, array $row): array {
        $entitytype = self::normalize_entity_type($entitytype);
        $errors = [];
        $warnings = [];
        $details = [];

        if (array_key_exists('status', $row) && self::has_value($row['status'])) {
            self::append_enum_check('status', $row['status'], self::ENTITY_STATUSES, $errors, $details);
        }

        if ($entitytype === 'object') {
            if (array_key_exists('role', $row) && self::has_value($row['role'])) {
                self::append_enum_check('role', $row['role'], self::OBJECT_ROLES, $errors, $details);
            }
            if (array_key_exists('purpose', $row) && self::has_value($row['purpose'])) {
                self::append_enum_check('purpose', $row['purpose'], self::OBJECT_PURPOSES, $errors, $details);
            }
            self::validate_evidence_strength($row, $errors, $details);
            return self::result($errors, $warnings, $details);
        }

        if (!in_array($entitytype, canonical_domain_model::target_types(), true)) {
            return self::result($errors, $warnings, $details);
        }

        foreach (self::drift_errors($entitytype, $row) as $error) {
            $errors[] = $error['message'];
            $details[] = $error;
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Throw when a curriculum row violates C1B boundaries.
     *
     * @param string $entitytype
     * @param array $row
     */
    public static function assert_curriculum_row(string $entitytype, array $row): void {
        $result = self::validate_curriculum_row($entitytype, $row);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Validate a mapping row.
     *
     * @param string $mappingtype
     * @param array $row
     * @return array
     */
    public static function validate_mapping_row(string $mappingtype, array $row): array {
        $mappingtype = self::normalize_mapping_type($mappingtype);
        $errors = [];
        $warnings = [];
        $details = [];

        if (in_array($mappingtype, ['comp_up', 'up_kp'], true) &&
                array_key_exists('role', $row) && self::has_value($row['role'])) {
            self::append_enum_check('role', $row['role'], self::RELATION_ROLES, $errors, $details);
        }

        if ($mappingtype === 'kp_prereq') {
            if (array_key_exists('relationshiptype', $row) && self::has_value($row['relationshiptype'])) {
                self::append_enum_check('relationshiptype', $row['relationshiptype'],
                    self::PREREQ_RELATIONSHIP_TYPES, $errors, $details);
            }
            if (array_key_exists('relationship_type', $row) && self::has_value($row['relationship_type'])) {
                self::append_enum_check('relationship_type', $row['relationship_type'],
                    self::PREREQ_RELATIONSHIP_TYPES, $errors, $details);
            }
            if (array_key_exists('requirement', $row) && self::has_value($row['requirement'])) {
                self::append_enum_check('requirement', $row['requirement'], self::PREREQ_REQUIREMENTS, $errors, $details);
            }
            if (self::same_nonempty($row['kpid'] ?? null, $row['prereqkpid'] ?? null) ||
                    self::same_nonempty($row['kp_externalid'] ?? null, $row['prereq_kp_externalid'] ?? null)) {
                $errors[] = 'A Knowledge Point cannot be its own prerequisite.';
                $details[] = self::detail('self_prerequisite', 'error', 'kp_prereq');
            }
        }

        if ($mappingtype === 'object_map') {
            $targettype = self::normalize_entity_type((string)($row['targettype'] ?? ($row['target_type'] ?? '')));
            if ($targettype === '' || !in_array($targettype, canonical_domain_model::target_types(), true)) {
                $errors[] = 'Object map target type must be competency, up, or kp.';
                $details[] = self::detail('invalid_object_map_target_type', 'error', 'object_map');
            } else if (!empty($row['target_externalid'])) {
                $status = canonical_domain_model::semantic_code_status($targettype, (string)$row['target_externalid']);
                if (empty($status['valid'])) {
                    $errors[] = $status['message'];
                    $details[] = self::detail('object_map_target_type_mismatch', 'error', 'object_map');
                }
            }
            if (array_key_exists('role', $row) && self::has_value($row['role'])) {
                self::append_enum_check('role', $row['role'], self::OBJECT_ROLES, $errors, $details);
            }
            self::validate_evidence_strength($row, $errors, $details);
        }

        self::validate_weight_fields($row, $errors, $details);

        return self::result($errors, $warnings, $details);
    }

    /**
     * Throw when a mapping row violates C1B boundaries.
     *
     * @param string $mappingtype
     * @param array $row
     */
    public static function assert_mapping_row(string $mappingtype, array $row): void {
        $result = self::validate_mapping_row($mappingtype, $row);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Throw when an entity status is outside the C1B vocabulary.
     *
     * @param string $status
     */
    public static function assert_entity_status(string $status): void {
        $errors = [];
        $details = [];
        self::append_enum_check('status', $status, self::ENTITY_STATUSES, $errors, $details);
        if ($errors) {
            throw new \invalid_parameter_exception(implode(' ', $errors));
        }
    }

    /**
     * Read-only runtime status for the C1B boundary.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function boundary_status(int $courseid = 0, int $frameworkid = 0, int $limit = 50): array {
        $c1 = canonical_domain_model::freeze_status($courseid);
        $findings = [];
        if (($c1['status'] ?? '') !== 'frozen') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'c1_not_frozen',
                'message' => 'C1B requires the C1 canonical domain model to remain frozen.',
            ];
        }

        $scan = self::scan_existing_graph($frameworkid, max(1, $limit));
        $findings = array_merge($findings, $scan['findings']);

        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['blocker', 'error'], true);
        });

        return [
            'type' => 'CupkpOntologyBoundaryStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'guarded',
            'contract' => self::contract(),
            'c1' => [
                'status' => $c1['status'] ?? null,
                'contract' => $c1['contract']['version'] ?? null,
                'history' => $c1['history'] ?? [],
            ],
            'sample' => $scan['sample'],
            'findings' => $findings,
        ];
    }

    /**
     * Normalize entity type aliases.
     *
     * @param string $entitytype
     * @return string
     */
    private static function normalize_entity_type(string $entitytype): string {
        $entitytype = strtolower(trim(str_replace('-', '_', $entitytype)));
        $aliases = [
            'c' => 'competency',
            'comp' => 'competency',
            'competencies' => 'competency',
            'usepoint' => 'up',
            'use_point' => 'up',
            'use_points' => 'up',
            'knowledgepoint' => 'kp',
            'knowledge_point' => 'kp',
            'knowledge_points' => 'kp',
            'learning_object' => 'object',
            'learning_objects' => 'object',
        ];
        return $aliases[$entitytype] ?? $entitytype;
    }

    /**
     * Normalize mapping type aliases.
     *
     * @param string $mappingtype
     * @return string
     */
    private static function normalize_mapping_type(string $mappingtype): string {
        $mappingtype = strtolower(trim(str_replace('-', '_', $mappingtype)));
        $aliases = [
            'competency_up' => 'comp_up',
            'competency_up_mapping' => 'comp_up',
            'up_kp_mapping' => 'up_kp',
            'kp_prerequisite' => 'kp_prereq',
            'activity_mapping' => 'object_map',
            'lesson_mapping' => 'object_map',
        ];
        return $aliases[$mappingtype] ?? $mappingtype;
    }

    /**
     * Build package entity indexes keyed by external ID.
     *
     * @param array $package
     * @return array
     */
    private static function collect_package_entities(array $package): array {
        $sets = [
            'framework' => [],
            'competency' => [],
            'up' => [],
            'kp' => [],
            'object' => [],
        ];
        $map = [
            'frameworks' => 'framework',
            'competencies' => 'competency',
            'use_points' => 'up',
            'knowledge_points' => 'kp',
            'learning_objects' => 'object',
        ];
        foreach ($map as $key => $entitytype) {
            foreach (($package[$key] ?? []) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $externalid = self::externalid_for_row($row);
                if ($externalid === '') {
                    $externalid = $key . '[' . $index . ']';
                }
                $sets[$entitytype][$externalid] = $row;
            }
        }
        foreach (($package['lesson_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $externalid = (string)($row['object_externalid'] ?? ($row['externalid'] ?? ''));
            if ($externalid === '') {
                $externalid = 'lesson_mappings[' . $index . ']';
            }
            $objectrow = $row;
            $objectrow['externalid'] = $externalid;
            $sets['object'][$externalid] = $objectrow;
        }
        return $sets;
    }

    /**
     * Validate package relationship rows and endpoint lifecycle.
     *
     * @param array $package
     * @param array $entitysets
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function validate_package_mappings(array $package, array $entitysets,
            array &$errors, array &$warnings, array &$details): void {
        foreach (($package['competency_up_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            self::merge_result('competency_up_mappings[' . $index . ']',
                self::validate_mapping_row('comp_up', $row), $errors, $warnings, $details);
            self::validate_package_link('competency_up_mappings[' . $index . ']', $entitysets,
                'competency', (string)($row['competency_externalid'] ?? ''),
                'up', (string)($row['up_externalid'] ?? ''), $errors, $warnings, $details);
        }

        foreach (($package['up_kp_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            self::merge_result('up_kp_mappings[' . $index . ']',
                self::validate_mapping_row('up_kp', $row), $errors, $warnings, $details);
            self::validate_package_link('up_kp_mappings[' . $index . ']', $entitysets,
                'up', (string)($row['up_externalid'] ?? ''),
                'kp', (string)($row['kp_externalid'] ?? ''), $errors, $warnings, $details);
        }

        foreach (($package['kp_prerequisites'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            self::merge_result('kp_prerequisites[' . $index . ']',
                self::validate_mapping_row('kp_prereq', $row), $errors, $warnings, $details);
            self::validate_package_link('kp_prerequisites[' . $index . ']', $entitysets,
                'kp', (string)($row['kp_externalid'] ?? ''),
                'kp', (string)($row['prereq_kp_externalid'] ?? ''), $errors, $warnings, $details);
        }

        foreach (($package['activity_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            self::merge_result('activity_mappings[' . $index . ']',
                self::validate_mapping_row('object_map', $row), $errors, $warnings, $details);
            $targettype = self::normalize_entity_type((string)($row['target_type'] ?? ''));
            self::validate_package_link('activity_mappings[' . $index . ']', $entitysets,
                'object', (string)($row['object_externalid'] ?? ''),
                $targettype, (string)($row['target_externalid'] ?? ''), $errors, $warnings, $details);
        }

        foreach (($package['lesson_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? ($row['externalid'] ?? ''));
            if (!empty($row['target_type']) && !empty($row['target_externalid'])) {
                $targettype = self::normalize_entity_type((string)$row['target_type']);
                self::merge_result('lesson_mappings[' . $index . ']',
                    self::validate_mapping_row('object_map', [
                        'object_externalid' => $objectexternalid,
                        'target_type' => $targettype,
                        'target_externalid' => (string)$row['target_externalid'],
                        'role' => $row['map_role'] ?? ($row['role'] ?? null),
                        'evidence_strength' => $row['map_evidence_strength'] ?? ($row['evidence_strength'] ?? null),
                    ]), $errors, $warnings, $details);
                self::validate_package_link('lesson_mappings[' . $index . ']', $entitysets,
                    'object', $objectexternalid, $targettype, (string)$row['target_externalid'],
                    $errors, $warnings, $details);
            }
            foreach (['kp' => 'kp_externalid', 'up' => 'up_externalid', 'competency' => 'competency_externalid'] as $type => $field) {
                foreach (self::list_values($row[$field . 's'] ?? ($row[$field] ?? null)) as $targetexternalid) {
                    self::validate_package_link('lesson_mappings[' . $index . ']', $entitysets,
                        'object', $objectexternalid, $type, $targetexternalid, $errors, $warnings, $details);
                }
            }
        }

        foreach (($package['project_competency_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? ($row['externalid'] ?? ''));
            foreach (self::list_values($row['competency_externalids'] ?? ($row['competency_externalid'] ?? null)) as $targetexternalid) {
                self::merge_result('project_competency_mappings[' . $index . ']',
                    self::validate_mapping_row('object_map', [
                        'object_externalid' => $objectexternalid,
                        'target_type' => 'competency',
                        'target_externalid' => $targetexternalid,
                        'role' => $row['role'] ?? 'assessment',
                        'evidence_strength' => $row['evidence_strength'] ?? 'independent_performance',
                    ]), $errors, $warnings, $details);
                self::validate_package_link('project_competency_mappings[' . $index . ']', $entitysets,
                    'object', $objectexternalid, 'competency', $targetexternalid, $errors, $warnings, $details);
            }
        }
    }

    /**
     * Validate one package endpoint link.
     *
     * @param string $context
     * @param array $entitysets
     * @param string $lefttype
     * @param string $leftid
     * @param string $righttype
     * @param string $rightid
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function validate_package_link(string $context, array $entitysets, string $lefttype, string $leftid,
            string $righttype, string $rightid, array &$errors, array &$warnings, array &$details): void {
        if ($leftid === '' || $rightid === '' || !isset($entitysets[$lefttype]) || !isset($entitysets[$righttype])) {
            return;
        }

        $left = $entitysets[$lefttype][$leftid] ?? null;
        $right = $entitysets[$righttype][$rightid] ?? null;
        if (!$left) {
            $warnings[] = $context . ': ' . $lefttype . ' "' . $leftid .
                '" is not defined in this package; import will resolve an existing record if present.';
        }
        if (!$right) {
            $warnings[] = $context . ': ' . $righttype . ' "' . $rightid .
                '" is not defined in this package; import will resolve an existing record if present.';
        }
        if (!$left || !$right) {
            return;
        }

        $leftframework = self::framework_key($left);
        $rightframework = self::framework_key($right);
        if ($leftframework !== '' && $rightframework !== '' && $leftframework !== $rightframework) {
            $errors[] = $context . ': ontology mapping crosses framework boundary: ' . $leftid . ' -> ' . $rightid . '.';
            $details[] = self::detail('cross_framework_mapping', 'error', $context);
        }

        foreach ([[$lefttype, $leftid, $left], [$righttype, $rightid, $right]] as $endpoint) {
            $status = strtolower(trim((string)($endpoint[2]['status'] ?? '')));
            if (in_array($status, self::LINK_INCOMPATIBLE_STATUSES, true)) {
                $errors[] = $context . ': cannot link ' . $endpoint[0] . ' "' . $endpoint[1] .
                    '" while its status is ' . $status . '.';
                $details[] = self::detail('lifecycle_incompatible_mapping', 'error', $context);
            }
        }
    }

    /**
     * Detect exact semantic duplicate labels across C/UP/KP types.
     *
     * @param array $entitysets
     * @param array $errors
     * @param array $details
     */
    private static function detect_semantic_duplicates(array $entitysets, array &$errors, array &$details): void {
        $seen = [];
        foreach (['competency', 'up', 'kp'] as $entitytype) {
            foreach ($entitysets[$entitytype] ?? [] as $externalid => $row) {
                $fingerprint = self::semantic_fingerprint($row);
                if ($fingerprint === '') {
                    continue;
                }
                if (isset($seen[$fingerprint]) && $seen[$fingerprint]['type'] !== $entitytype) {
                    $errors[] = 'Semantic duplicate across types: ' . $seen[$fingerprint]['type'] . ' "' .
                        $seen[$fingerprint]['externalid'] . '" and ' . $entitytype . ' "' . $externalid .
                        '" share the same ontology label.';
                    $details[] = self::detail('semantic_duplicate_across_types', 'error', $entitytype);
                    continue;
                }
                $seen[$fingerprint] = ['type' => $entitytype, 'externalid' => $externalid];
            }
        }
    }

    /**
     * Return category drift errors for a target row.
     *
     * @param string $entitytype
     * @param array $row
     * @return array
     */
    private static function drift_errors(string $entitytype, array $row): array {
        $errors = [];
        $text = self::row_text($row);

        if ($entitytype === 'competency') {
            $wrongfields = array_merge(
                self::present_fields($row, self::UP_OWNED_FIELDS),
                self::present_fields($row, self::KP_OWNED_FIELDS)
            );
            if ($wrongfields) {
                $errors[] = [
                    'code' => 'overly_narrow_competency',
                    'severity' => 'error',
                    'message' => 'Competency appears to contain UP/KP-specific fields: ' . implode(', ', $wrongfields) . '.',
                ];
            }
            if ((self::looks_like_knowledge_item($text) || self::looks_like_single_assessment_item($text)) &&
                    !self::has_integrated_ability_signal($text)) {
                $errors[] = [
                    'code' => 'overly_narrow_competency',
                    'severity' => 'error',
                    'message' => 'Overly narrow competency: competency should be an integrated ability, not a single KP/task item.',
                ];
            }
        } else if ($entitytype === 'kp') {
            $wrongfields = array_merge(
                self::present_fields($row, self::UP_OWNED_FIELDS),
                self::present_fields($row, self::COMPETENCY_OWNED_FIELDS)
            );
            if ($wrongfields || self::looks_like_task_performance(self::kp_task_text($row))) {
                $errors[] = [
                    'code' => 'kp_written_as_task',
                    'severity' => 'error',
                    'message' => 'KP written as task: Knowledge Points must define knowledge, not the learner task or performance.',
                ];
            }
        } else if ($entitytype === 'up') {
            $wrongfields = self::present_fields($row, self::KP_OWNED_FIELDS);
            if (array_key_exists('domain', $row) && self::has_value($row['domain'])) {
                $wrongfields[] = 'domain';
            }
            if ($wrongfields || self::has_unmodeled_knowledge_signal($text)) {
                $errors[] = [
                    'code' => 'up_containing_unmodeled_new_knowledge',
                    'severity' => 'error',
                    'message' => 'UP containing unmodeled new knowledge: model new language/content as KP and link it to the UP.',
                ];
            }
        }

        return $errors;
    }

    /**
     * Scan existing DB rows without mutating data.
     *
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function scan_existing_graph(int $frameworkid, int $limit): array {
        global $DB;

        $findings = [];
        $entitysets = ['competency' => [], 'up' => [], 'kp' => [], 'object' => [], 'framework' => []];
        $sample = [
            'frameworkid' => $frameworkid,
            'checked' => [
                'competency' => 0,
                'up' => 0,
                'kp' => 0,
                'object' => 0,
                'mappings' => 0,
            ],
        ];

        $tables = [
            'competency' => 'flwcupkp_comp',
            'up' => 'flwcupkp_up',
            'kp' => 'flwcupkp_kp',
            'object' => 'flwcupkp_object',
        ];
        foreach ($tables as $entitytype => $table) {
            [$where, $params] = self::framework_where($frameworkid);
            $records = $DB->get_records_select($table, $where, $params, 'id ASC', '*', 0, $limit);
            foreach ($records as $record) {
                $sample['checked'][$entitytype]++;
                $row = (array)$record;
                $entitysets[$entitytype][(string)$record->externalid] = $row;
                $result = self::validate_curriculum_row($entitytype, $row);
                foreach ($result['errors'] as $error) {
                    $findings[] = [
                        'severity' => 'error',
                        'code' => 'existing_' . $entitytype . '_ontology_violation',
                        'message' => $entitytype . ' ' . $record->externalid . ': ' . $error,
                    ];
                }
            }
        }
        self::detect_existing_duplicates($entitysets, $findings);
        self::scan_existing_mappings($frameworkid, $limit, $findings, $sample);

        return ['findings' => $findings, 'sample' => $sample];
    }

    /**
     * Detect semantic duplicates in existing records.
     *
     * @param array $entitysets
     * @param array $findings
     */
    private static function detect_existing_duplicates(array $entitysets, array &$findings): void {
        $errors = [];
        $details = [];
        self::detect_semantic_duplicates($entitysets, $errors, $details);
        foreach ($errors as $error) {
            $findings[] = [
                'severity' => 'error',
                'code' => 'existing_semantic_duplicate_across_types',
                'message' => $error,
            ];
        }
    }

    /**
     * Scan existing mapping rows.
     *
     * @param int $frameworkid
     * @param int $limit
     * @param array $findings
     * @param array $sample
     */
    private static function scan_existing_mappings(int $frameworkid, int $limit, array &$findings, array &$sample): void {
        global $DB;

        $queries = [
            'comp_up' => "SELECT m.id, m.role, m.weight, m.minmastery, c.frameworkid AS leftframeworkid,
                                u.frameworkid AS rightframeworkid
                           FROM {flwcupkp_comp_up} m
                           JOIN {flwcupkp_comp} c ON c.id = m.competencyid
                           JOIN {flwcupkp_up} u ON u.id = m.upid",
            'up_kp' => "SELECT m.id, m.role, m.weight, m.minreadiness, u.frameworkid AS leftframeworkid,
                               kp.frameworkid AS rightframeworkid
                          FROM {flwcupkp_up_kp} m
                          JOIN {flwcupkp_up} u ON u.id = m.upid
                          JOIN {flwcupkp_kp} kp ON kp.id = m.kpid",
            'kp_prereq' => "SELECT m.id, m.relationshiptype, m.strength, m.requirement, kp.frameworkid AS leftframeworkid,
                                   prereq.frameworkid AS rightframeworkid, m.kpid, m.prereqkpid
                              FROM {flwcupkp_kp_prereq} m
                              JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
                              JOIN {flwcupkp_kp} prereq ON prereq.id = m.prereqkpid",
        ];

        foreach ($queries as $mappingtype => $sql) {
            $params = [];
            if ($frameworkid > 0) {
                $sql = 'SELECT * FROM (' . $sql . ') mapped WHERE leftframeworkid = :frameworkid';
                $params['frameworkid'] = $frameworkid;
            }
            $order = $frameworkid > 0 ? ' ORDER BY id ASC' : ' ORDER BY m.id ASC';
            $records = $DB->get_records_sql($sql . $order, $params, 0, $limit);
            foreach ($records as $record) {
                $sample['checked']['mappings']++;
                self::append_existing_mapping_result($mappingtype, $record, $findings);
            }
        }

        $sql = "SELECT m.id, m.targettype, m.targetid, m.role, m.evidencestrength, o.frameworkid AS leftframeworkid
                  FROM {flwcupkp_object_map} m
                  JOIN {flwcupkp_object} o ON o.id = m.objectid";
        $params = [];
        if ($frameworkid > 0) {
            $sql .= ' WHERE o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $records = $DB->get_records_sql($sql . ' ORDER BY m.id ASC', $params, 0, $limit);
        foreach ($records as $record) {
            $sample['checked']['mappings']++;
            self::append_existing_mapping_result('object_map', $record, $findings);
            try {
                $targettable = evidence_guard::target_table((string)$record->targettype);
            } catch (\invalid_parameter_exception $e) {
                continue;
            }
            $target = $DB->get_record($targettable, ['id' => (int)$record->targetid], 'id, frameworkid', IGNORE_MISSING);
            if (!$target) {
                $findings[] = [
                    'severity' => 'error',
                    'code' => 'existing_missing_mapping_target',
                    'message' => 'object_map ' . $record->id . ' target does not exist.',
                ];
            } else if ((int)$record->leftframeworkid !== (int)$target->frameworkid) {
                $findings[] = [
                    'severity' => 'error',
                    'code' => 'existing_cross_framework_mapping',
                    'message' => 'object_map ' . $record->id . ' crosses framework boundary.',
                ];
            }
        }
    }

    /**
     * Append validation findings for one existing mapping row.
     *
     * @param string $mappingtype
     * @param \stdClass $record
     * @param array $findings
     */
    private static function append_existing_mapping_result(string $mappingtype, \stdClass $record, array &$findings): void {
        $result = self::validate_mapping_row($mappingtype, (array)$record);
        foreach ($result['errors'] as $error) {
            $findings[] = [
                'severity' => 'error',
                'code' => 'existing_mapping_ontology_violation',
                'message' => $mappingtype . ' ' . $record->id . ': ' . $error,
            ];
        }
        if (property_exists($record, 'leftframeworkid') && property_exists($record, 'rightframeworkid') &&
                (int)$record->leftframeworkid !== (int)$record->rightframeworkid) {
            $findings[] = [
                'severity' => 'error',
                'code' => 'existing_cross_framework_mapping',
                'message' => $mappingtype . ' ' . $record->id . ' crosses framework boundary.',
            ];
        }
    }

    /**
     * WHERE clause for optional framework scoping.
     *
     * @param int $frameworkid
     * @return array
     */
    private static function framework_where(int $frameworkid): array {
        if ($frameworkid > 0) {
            return ['frameworkid = :frameworkid', ['frameworkid' => $frameworkid]];
        }
        return ['1=1', []];
    }

    /**
     * Validate evidence-strength vocabulary if present.
     *
     * @param array $row
     * @param array $errors
     * @param array $details
     */
    private static function validate_evidence_strength(array $row, array &$errors, array &$details): void {
        foreach (['evidence_strength', 'evidencestrength'] as $field) {
            if (array_key_exists($field, $row) && self::has_value($row[$field])) {
                self::append_enum_check($field, $row[$field], self::EVIDENCE_STRENGTHS, $errors, $details);
            }
        }
    }

    /**
     * Validate numeric weights/readiness/mastery fields as normalized 0..1 values.
     *
     * @param array $row
     * @param array $errors
     * @param array $details
     */
    private static function validate_weight_fields(array $row, array &$errors, array &$details): void {
        foreach (['weight', 'minimum_up_mastery', 'minmastery', 'minimum_kp_readiness', 'minreadiness', 'strength'] as $field) {
            if (!array_key_exists($field, $row) || !self::has_value($row[$field])) {
                continue;
            }
            if (!is_numeric($row[$field]) || (float)$row[$field] < 0 || (float)$row[$field] > 1) {
                $errors[] = $field . ' must be a number from 0 to 1.';
                $details[] = self::detail('invalid_weight_range', 'error', $field);
            }
        }
    }

    /**
     * Append an enum validation result.
     *
     * @param string $field
     * @param mixed $value
     * @param array $allowed
     * @param array $errors
     * @param array $details
     */
    private static function append_enum_check(string $field, $value, array $allowed, array &$errors, array &$details): void {
        $normalized = strtolower(trim((string)$value));
        if (!in_array($normalized, $allowed, true)) {
            $errors[] = $field . ' "' . $value . '" is outside the C1B ontology vocabulary.';
            $details[] = self::detail('unsupported_ontology_vocabulary', 'error', $field);
        }
    }

    /**
     * Find meaningful wrong-owned fields present on a row.
     *
     * @param array $row
     * @param array $fields
     * @return array
     */
    private static function present_fields(array $row, array $fields): array {
        $present = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row) && self::has_value($row[$field])) {
                $present[] = $field;
            }
        }
        return array_values(array_unique($present));
    }

    /**
     * Has a non-empty value.
     *
     * @param mixed $value
     * @return bool
     */
    private static function has_value($value): bool {
        return $value !== null && trim((string)$value) !== '';
    }

    /**
     * Compare non-empty scalar values.
     *
     * @param mixed $left
     * @param mixed $right
     * @return bool
     */
    private static function same_nonempty($left, $right): bool {
        return self::has_value($left) && self::has_value($right) && (string)$left === (string)$right;
    }

    /**
     * Text fields that are strong evidence of a KP being authored as a task.
     *
     * @param array $row
     * @return string
     */
    private static function kp_task_text(array $row): string {
        $fields = [
            'title',
            'can_do',
            'cando',
            'action_statement',
            'actionstatement',
            'observable_action',
            'observableaction',
            'success_criteria',
            'successcriteria',
        ];
        $parts = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row) && self::has_value($row[$field])) {
                $parts[] = (string)$row[$field];
            }
        }
        return strtolower(' ' . implode(' ', $parts) . ' ');
    }

    /**
     * Combined row text for classification.
     *
     * @param array $row
     * @return string
     */
    private static function row_text(array $row): string {
        $fields = [
            'title',
            'can_do',
            'cando',
            'description',
            'action_statement',
            'actionstatement',
            'observable_action',
            'observableaction',
            'success_criteria',
            'successcriteria',
            'form',
            'formtext',
            'meaning_function',
            'meaningfunction',
            'usage_constraints',
            'usageconstraints',
        ];
        $parts = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row) && self::has_value($row[$field])) {
                $parts[] = (string)$row[$field];
            }
        }
        return strtolower(' ' . implode(' ', $parts) . ' ');
    }

    /**
     * Does text look like an isolated knowledge item?
     *
     * @param string $text
     * @return bool
     */
    private static function looks_like_knowledge_item(string $text): bool {
        return (bool)preg_match(
            '/\b(vocabulary|lexical set|grammar|noun|nouns|verb|verbs|modal|clause|clauses|connector|connectors|' .
            'collocation|collocations|pronunciation|spelling|orthography|script|phrase|phrases|expression|expressions|' .
            'text structure|reading strategy|listening strategy)\b/',
            $text
        );
    }

    /**
     * Does text look like one task or one assessment item rather than integrated ability?
     *
     * @param string $text
     * @return bool
     */
    private static function looks_like_single_assessment_item(string $text): bool {
        return (bool)preg_match('/\b(single|one)\s+(word|item|question|sentence)\b|\b(gap[- ]?fill|multiple choice|quiz question)\b/', $text);
    }

    /**
     * Does text show integrated ability rather than one narrow item?
     *
     * @param string $text
     * @return bool
     */
    private static function has_integrated_ability_signal(string $text): bool {
        if (preg_match('/\b(integrated|communicative|operational|workplace|real[- ]world|project|discussion|conversation)\b/', $text)) {
            return true;
        }
        if (preg_match('/\b(explain|discuss|compare|propose|agree|write|summari[sz]e|present|respond|negotiate|collaborate)\b/', $text) &&
                preg_match('/\b(and|while|with|using|because|so that)\b/', $text)) {
            return true;
        }
        return false;
    }

    /**
     * Does text make a KP look like a learner performance task?
     *
     * @param string $text
     * @return bool
     */
    private static function looks_like_task_performance(string $text): bool {
        return (bool)preg_match(
            '/^\s*can\s+|\b(write|draft|submit|upload|role[- ]?play|present|record|complete|take|answer|solve)\s+' .
            '(a|an|the|with|to)\b|\bdiscuss\s+(with|a partner|in pairs|the problem)\b|\bagree on\s+(a|the)\b/',
            $text
        );
    }

    /**
     * Does text introduce knowledge that should be a KP?
     *
     * @param string $text
     * @return bool
     */
    private static function has_unmodeled_knowledge_signal(string $text): bool {
        return (bool)preg_match(
            '/\b(new|target|required)\s+(vocabulary|grammar|language|expressions|phrases|forms|content knowledge)\b|' .
            '\b(introduces|teaches)\s+(vocabulary|grammar|language|expressions|phrases|forms)\b/',
            $text
        );
    }

    /**
     * Semantic duplicate fingerprint.
     *
     * @param array $row
     * @return string
     */
    private static function semantic_fingerprint(array $row): string {
        $label = strtolower(trim((string)($row['title'] ?? ($row['name'] ?? ''))));
        if ($label === '') {
            return '';
        }
        $label = preg_replace('/\b(can|able to|use|uses|using|knowledge of|skill of|the|a|an|to)\b/', ' ', $label);
        $label = preg_replace('/[^a-z0-9]+/', ' ', (string)$label);
        $label = trim((string)preg_replace('/\s+/', ' ', (string)$label));
        return strlen($label) >= 8 ? $label : '';
    }

    /**
     * Resolve an external ID from a package row.
     *
     * @param array $row
     * @return string
     */
    private static function externalid_for_row(array $row): string {
        return trim((string)($row['externalid'] ?? ($row['object_externalid'] ?? '')));
    }

    /**
     * Return optional package framework key.
     *
     * @param array $row
     * @return string
     */
    private static function framework_key(array $row): string {
        foreach (['framework_externalid', 'frameworkid', 'framework_id', 'framework'] as $field) {
            if (array_key_exists($field, $row) && self::has_value($row[$field])) {
                return (string)$row[$field];
            }
        }
        return '';
    }

    /**
     * Normalize scalar/list mapping values.
     *
     * @param mixed $value
     * @return array
     */
    private static function list_values($value): array {
        if ($value === null || $value === '') {
            return [];
        }
        $values = is_array($value) ? $value : [$value];
        $out = [];
        foreach ($values as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Merge child validation results with context prefix.
     *
     * @param string $prefix
     * @param array $result
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function merge_result(string $prefix, array $result, array &$errors,
            array &$warnings, array &$details): void {
        foreach ($result['errors'] ?? [] as $error) {
            $errors[] = $prefix . ': ' . $error;
        }
        foreach ($result['warnings'] ?? [] as $warning) {
            $warnings[] = $prefix . ': ' . $warning;
        }
        foreach ($result['details'] ?? [] as $detail) {
            $detail['context'] = $prefix;
            $details[] = $detail;
        }
    }

    /**
     * Build a validation result.
     *
     * @param array $errors
     * @param array $warnings
     * @param array $details
     * @return array
     */
    private static function result(array $errors, array $warnings, array $details): array {
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'contract' => self::CONTRACT_VERSION,
        ];
    }

    /**
     * Build a validation detail record.
     *
     * @param string $code
     * @param string $severity
     * @param string $field
     * @return array
     */
    private static function detail(string $code, string $severity, string $field): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'field' => $field,
        ];
    }

    /**
     * Reference examples and counterexamples for authors.
     *
     * @return array
     */
    private static function authoring_reference(): array {
        return [
            'examples' => [
                [
                    'type' => 'competency',
                    'externalid' => 'C-FR-A2-SI-004',
                    'label' => 'Can discuss a local problem, compare options, and agree on a next step.',
                    'why' => 'Integrated ability across meaning, interaction, and action.',
                ],
                [
                    'type' => 'kp',
                    'externalid' => 'KP-FR-A2-FUNC-031',
                    'label' => 'Expressions for suggesting alternatives.',
                    'why' => 'The knowledge itself defines the item.',
                ],
                [
                    'type' => 'up',
                    'externalid' => 'UP-FR-A2-SI-031-04',
                    'label' => 'Use suggestion language to negotiate a group decision politely.',
                    'why' => 'Same knowledge can be demonstrated in a particular use context.',
                ],
            ],
            'counterexamples' => [
                [
                    'type' => 'competency',
                    'label' => 'Past tense regular verbs.',
                    'issue' => 'Too narrow; this is KP-like knowledge.',
                ],
                [
                    'type' => 'kp',
                    'label' => 'Write a solution note to your manager.',
                    'issue' => 'This is a UP/task, not the knowledge object.',
                ],
                [
                    'type' => 'up',
                    'label' => 'New vocabulary: deadline, delay, workflow.',
                    'issue' => 'The new knowledge must be modeled as KP and linked to the UP.',
                ],
            ],
        ];
    }
}

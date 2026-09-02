<?php
// Program 3 Gate C4 lifecycle, versioning, and governance.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes C-UP-KP lifecycle governance without changing C1-C3B semantics.
 */
final class lifecycle_governance_contract {
    /** Program 3 lifecycle governance gate. */
    public const GATE = 'P3_C4';

    /** Frozen C4 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1';

    /** @var array Canonical C4 lifecycle statuses. */
    private const STATUSES = [
        'draft',
        'review',
        'approved',
        'published',
        'deprecated',
        'archived',
    ];

    /** @var array Legacy labels already present in earlier FLW material. */
    private const STATUS_ALIASES = [
        'validated' => 'approved',
        'active' => 'published',
        'reference' => 'published',
        'pilot' => 'review',
        'inactive' => 'archived',
        'retired' => 'archived',
        'test' => 'draft',
    ];

    /** @var array Allowed lifecycle transitions after canonicalization. */
    private const TRANSITIONS = [
        'draft' => ['draft', 'review', 'approved', 'archived'],
        'review' => ['draft', 'review', 'approved', 'archived'],
        'approved' => ['review', 'approved', 'published', 'deprecated', 'archived'],
        'published' => ['published', 'deprecated'],
        'deprecated' => ['deprecated', 'archived'],
        'archived' => ['archived'],
    ];

    /** @var array Entity backing tables. */
    private const ENTITY_TABLES = [
        'framework' => 'flwcupkp_framework',
        'competency' => 'flwcupkp_comp',
        'up' => 'flwcupkp_up',
        'kp' => 'flwcupkp_kp',
        'object' => 'flwcupkp_object',
    ];

    /** @var array Table to entity aliases for import governance. */
    private const TABLE_ENTITY_TYPES = [
        'flwcupkp_framework' => 'framework',
        'flwcupkp_comp' => 'competency',
        'flwcupkp_up' => 'up',
        'flwcupkp_kp' => 'kp',
        'flwcupkp_object' => 'object',
    ];

    /** @var array Fields whose published changes alter curriculum meaning. */
    private const SEMANTIC_FIELDS = [
        'framework' => [
            'externalid',
            'name',
            'coursecode',
            'language',
            'cefrrange',
            'version',
            'description',
        ],
        'competency' => [
            'externalid',
            'frameworkid',
            'title',
            'cando',
            'description',
            'cefr',
            'stage',
            'domain',
            'scope',
            'evidencerule',
            'version',
        ],
        'up' => [
            'externalid',
            'frameworkid',
            'title',
            'actionstatement',
            'intention',
            'context',
            'observableaction',
            'conditions',
            'successcriteria',
            'cefr',
            'languagemode',
            'interactiontype',
            'evidencerequirements',
            'rubricref',
            'version',
        ],
        'kp' => [
            'externalid',
            'frameworkid',
            'title',
            'description',
            'language',
            'cefr',
            'domain',
            'formtext',
            'meaningfunction',
            'usageconstraints',
            'difficulty',
            'learningload',
            'evidencerequirements',
            'version',
        ],
        'object' => [
            'externalid',
            'frameworkid',
            'courseid',
            'unitcode',
            'lesson',
            'objecttype',
            'title',
            'sourceid',
            'purpose',
            'evidencestrength',
            'difficulty',
            'role',
            'metadatajson',
        ],
    ];

    /**
     * Return the frozen C4 lifecycle governance contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpLifecycleGovernanceContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                canonical_domain_model::CONTRACT_VERSION,
                ontology_boundary::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                content_evidence_mapping_contract::CONTRACT_VERSION,
                evidence_semantics_quality_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'lifecycle' => [
                'canonical_statuses' => self::STATUSES,
                'legacy_aliases' => self::STATUS_ALIASES,
                'transition_matrix' => self::TRANSITIONS,
            ],
            'versioning' => [
                'stable_code_policy' => 'Curriculum external IDs are stable semantic codes.',
                'published_mutation_policy' =>
                    'Published semantic changes must be made by cloning/revisioning, not by overwriting the published row.',
                'clone_policy' =>
                    'Framework clone operations copy curriculum graph rows only; learner evidence, states, recommendations, imports, and audit records are not cloned.',
            ],
            'deprecation' => [
                'rule' => 'Published entities with learner evidence are retained and moved through DEPRECATED/REPLACED_BY rather than physically deleted.',
                'replacement_relation' => 'REPLACED_BY',
                'source_state' => ['deprecated', 'archived'],
                'target_state' => ['approved', 'published'],
            ],
            'validation' => [
                'severity_levels' => ['ERROR', 'WARNING', 'INFO'],
                'detects' => [
                    'duplicate_codes',
                    'invalid_relationships',
                    'cycles',
                    'orphans',
                    'missing_evidence_routes',
                    'invalid_replacements',
                    'invalid_published_states',
                    'published_semantic_overwrite',
                ],
            ],
            'does_not_do' => [
                'adaptive_path_selection',
                'mastery_recalculation',
                'raw_moodle_log_scraping',
                'new_source_history_capture',
            ],
        ];
    }

    /**
     * Canonical lifecycle statuses.
     *
     * @return array
     */
    public static function lifecycle_statuses(): array {
        return self::STATUSES;
    }

    /**
     * Status labels for admin UI controls.
     *
     * @return array
     */
    public static function status_options(): array {
        return [
            'draft' => 'DRAFT',
            'review' => 'REVIEW',
            'approved' => 'APPROVED',
            'published' => 'PUBLISHED',
            'deprecated' => 'DEPRECATED',
            'archived' => 'ARCHIVED',
        ];
    }

    /**
     * Canonicalize a lifecycle status, preserving legacy package compatibility.
     *
     * @param string $status
     * @return string
     */
    public static function canonical_status(string $status): string {
        $status = strtolower(trim($status));
        if ($status === '') {
            throw new \invalid_parameter_exception('Lifecycle status is required.');
        }
        $status = str_replace('-', '_', $status);
        if (isset(self::STATUS_ALIASES[$status])) {
            return self::STATUS_ALIASES[$status];
        }
        if (in_array($status, self::STATUSES, true)) {
            return $status;
        }
        throw new \invalid_parameter_exception('Unsupported C4 lifecycle status: ' . $status);
    }

    /**
     * Return the entity type for a plugin table governed by C4.
     *
     * @param string $table
     * @return string
     */
    public static function entity_type_for_table(string $table): string {
        if (!isset(self::TABLE_ENTITY_TYPES[$table])) {
            throw new \invalid_parameter_exception('Unsupported C4 governed table: ' . $table);
        }
        return self::TABLE_ENTITY_TYPES[$table];
    }

    /**
     * Normalize entity status and version values before earlier contracts inspect them.
     *
     * @param string $entitytype
     * @param array $data
     * @return array
     */
    public static function normalize_entity_payload(string $entitytype, array $data): array {
        $entitytype = self::normalize_entity_type($entitytype);
        if ($entitytype !== 'object' && array_key_exists('status', $data) && trim((string)$data['status']) !== '') {
            $data['status'] = self::canonical_status((string)$data['status']);
        }
        if (array_key_exists('version', $data)) {
            $data['version'] = trim((string)$data['version']);
        }
        return $data;
    }

    /**
     * Throw when a curriculum entity write violates C4.
     *
     * @param string $entitytype
     * @param array $newdata
     * @param \stdClass|null $existing
     */
    public static function assert_entity_write(string $entitytype, array $newdata, ?\stdClass $existing = null): void {
        $result = self::validate_entity_write($entitytype, $newdata, $existing);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Validate a single entity write against lifecycle and version rules.
     *
     * @param string $entitytype
     * @param array $newdata
     * @param \stdClass|null $existing
     * @return array
     */
    public static function validate_entity_write(string $entitytype, array $newdata,
            ?\stdClass $existing = null): array {
        $entitytype = self::normalize_entity_type($entitytype);
        $errors = [];
        $warnings = [];
        $details = [];
        $newdata = self::normalize_entity_payload($entitytype, $newdata);

        if ($entitytype === 'object') {
            return self::result($errors, $warnings, $details, [
                'entitytype' => $entitytype,
                'governed_by_framework_version' => true,
            ]);
        }

        try {
            $newstatus = self::status_from_row($newdata, $existing);
        } catch (\invalid_parameter_exception $e) {
            $errors[] = $e->getMessage();
            $details[] = self::detail('unsupported_lifecycle_status', 'ERROR', [
                'entitytype' => $entitytype,
                'status' => (string)($newdata['status'] ?? ''),
            ]);
            $newstatus = 'draft';
        }

        $version = (string)($newdata['version'] ?? ($existing->version ?? ''));
        if (self::version_required($newstatus) && !self::semantic_version_is_valid($version)) {
            $errors[] = strtoupper($newstatus) . ' ' . $entitytype . ' rows require a non-empty semantic version.';
            $details[] = self::detail('invalid_or_missing_semantic_version', 'ERROR', [
                'entitytype' => $entitytype,
                'status' => $newstatus,
                'version' => $version,
            ]);
        }

        if ($existing !== null) {
            $existingstatus = self::status_value_or_draft($existing->status ?? 'draft');
            if (!self::transition_allowed($existingstatus, $newstatus)) {
                $errors[] = 'Invalid C4 lifecycle transition for ' . $entitytype . ': ' .
                    strtoupper($existingstatus) . ' to ' . strtoupper($newstatus) . '.';
                $details[] = self::detail('invalid_lifecycle_transition', 'ERROR', [
                    'entitytype' => $entitytype,
                    'from' => $existingstatus,
                    'to' => $newstatus,
                ]);
            }

            if (!empty($newdata['externalid']) && (string)$newdata['externalid'] !== (string)$existing->externalid) {
                $errors[] = 'Curriculum external IDs are stable semantic codes and cannot be changed in place.';
                $details[] = self::detail('stable_code_changed', 'ERROR', ['entitytype' => $entitytype]);
            }

            $changed = self::changed_semantic_fields($entitytype, $newdata, $existing);
            if ($existingstatus === 'published' && $changed) {
                $errors[] = 'Published ' . $entitytype .
                    ' semantic changes must be made through a new revision/version, not by overwriting the published row.';
                $details[] = self::detail('published_semantic_overwrite', 'ERROR', [
                    'entitytype' => $entitytype,
                    'changed_fields' => $changed,
                ]);
            }

            if ($existingstatus === 'archived' && $changed) {
                $errors[] = 'Archived ' . $entitytype . ' rows are immutable.';
                $details[] = self::detail('archived_semantic_overwrite', 'ERROR', [
                    'entitytype' => $entitytype,
                    'changed_fields' => $changed,
                ]);
            }
        } else if ($newstatus === 'archived') {
            $warnings[] = 'New rows should normally begin in DRAFT, REVIEW, or APPROVED rather than ARCHIVED.';
            $details[] = self::detail('new_row_archived', 'WARNING', ['entitytype' => $entitytype]);
        }

        return self::result($errors, $warnings, $details, [
            'entitytype' => $entitytype,
            'status' => $newstatus,
            'version' => $version,
        ]);
    }

    /**
     * Throw when a mapping write violates C4 governance.
     *
     * @param string $mappingtype
     * @param array $row
     */
    public static function assert_mapping_change(string $mappingtype, array $row): void {
        $result = self::validate_mapping_change($mappingtype, $row);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Validate a mapping write against lifecycle and replacement rules.
     *
     * @param string $mappingtype
     * @param array $row
     * @return array
     */
    public static function validate_mapping_change(string $mappingtype, array $row): array {
        $mappingtype = self::normalize_mapping_type($mappingtype);
        $errors = [];
        $warnings = [];
        $details = [];
        $endpoints = self::mapping_endpoint_records($mappingtype, $row);

        foreach ($endpoints['errors'] as $error) {
            $errors[] = $error;
        }
        foreach ($endpoints['details'] as $detail) {
            $details[] = $detail;
        }
        if ($errors) {
            return self::result($errors, $warnings, $details, ['mappingtype' => $mappingtype]);
        }

        $semantic = relationship_graph_contract::semantic_for_mapping($mappingtype, $row);
        $source = $endpoints['source'];
        $target = $endpoints['target'];

        if (!empty($source->frameworkid) && !empty($target->frameworkid) &&
                (int)$source->frameworkid !== (int)$target->frameworkid) {
            $errors[] = 'C4 mappings cannot cross framework boundaries.';
            $details[] = self::detail('cross_framework_mapping', 'ERROR', ['mappingtype' => $mappingtype]);
        }

        if ($semantic === 'REPLACED_BY') {
            self::validate_replacement_endpoint($source, $target, $errors, $details);
        } else {
            foreach (['source' => $source, 'target' => $target] as $side => $record) {
                if (!property_exists($record, 'status')) {
                    continue;
                }
                $status = self::status_value_or_draft($record->status ?? 'draft');
                if (in_array($status, ['deprecated', 'archived'], true)) {
                    $errors[] = 'New active mappings cannot use a ' . strtoupper($status) . ' ' . $side . ' node.';
                    $details[] = self::detail('lifecycle_incompatible_mapping', 'ERROR', [
                        'mappingtype' => $mappingtype,
                        'side' => $side,
                        'status' => $status,
                    ]);
                }
            }
        }

        return self::result($errors, $warnings, $details, [
            'mappingtype' => $mappingtype,
            'semantic' => $semantic,
        ]);
    }

    /**
     * Throw when physical mapping deletion would break historical evidence governance.
     *
     * @param string $mappingtype
     * @param \stdClass $record
     */
    public static function assert_mapping_delete(string $mappingtype, \stdClass $record): void {
        $result = self::validate_mapping_delete($mappingtype, $record);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Validate a mapping deletion.
     *
     * @param string $mappingtype
     * @param \stdClass $record
     * @return array
     */
    public static function validate_mapping_delete(string $mappingtype, \stdClass $record): array {
        global $DB;

        $mappingtype = self::normalize_mapping_type($mappingtype);
        $errors = [];
        $warnings = [];
        $details = [];

        if ($mappingtype === 'object_map') {
            $evidence = $DB->count_records('flwcupkp_evidence', [
                'objectid' => (int)$record->objectid,
                'targettype' => (string)$record->targettype,
                'targetid' => (int)$record->targetid,
            ]);
            if ($evidence > 0) {
                $errors[] = 'Object mappings with learner evidence cannot be physically deleted.';
                $details[] = self::detail('delete_object_map_with_evidence', 'ERROR', [
                    'evidence_rows' => $evidence,
                ]);
            }

            $target = self::record_for_target((string)$record->targettype, (int)$record->targetid);
            if ($target && property_exists($target, 'status') &&
                    self::status_value_or_draft($target->status ?? 'draft') === 'published') {
                $routes = $DB->count_records('flwcupkp_object_map', [
                    'targettype' => (string)$record->targettype,
                    'targetid' => (int)$record->targetid,
                ]);
                if ($routes <= 1) {
                    $warnings[] = 'Deleting this object mapping will leave a published target without an evidence route.';
                    $details[] = self::detail('published_target_would_lose_evidence_route', 'WARNING');
                }
            }
        }

        return self::result($errors, $warnings, $details, ['mappingtype' => $mappingtype]);
    }

    /**
     * Throw when framework cloning violates C4 versioning rules.
     *
     * @param \stdClass $source
     * @param string $newversion
     * @param string $suffix
     */
    public static function assert_framework_clone(\stdClass $source, string $newversion, string $suffix): void {
        $newversion = trim($newversion);
        if (!self::semantic_version_is_valid($newversion)) {
            throw new \invalid_parameter_exception('New framework version must be a semantic version.');
        }
        if ($newversion === (string)($source->version ?? '')) {
            throw new \invalid_parameter_exception('A cloned framework version must use a new semantic version.');
        }
        if (trim($suffix) === '') {
            throw new \invalid_parameter_exception('A cloned framework version requires a stable external ID suffix.');
        }
    }

    /**
     * Validate package lifecycle and governance rules.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package_governance(array $package): array {
        $errors = [];
        $warnings = [];
        $details = [];
        $entities = self::package_entities($package);
        $routes = self::package_evidence_routes($package);

        self::detect_package_duplicate_codes($entities, $errors, $details);
        self::validate_package_entity_states($entities, $routes, $errors, $warnings, $details);
        self::validate_package_orphans($entities, $package, $errors, $warnings, $details);
        self::validate_package_replacements($entities, $package, $errors, $details);

        return self::result($errors, $warnings, $details, [
            'contract' => self::CONTRACT_VERSION,
            'counts' => [
                'frameworks' => count($entities['framework'] ?? []),
                'competencies' => count($entities['competency'] ?? []),
                'use_points' => count($entities['up'] ?? []),
                'knowledge_points' => count($entities['kp'] ?? []),
                'learning_objects' => count($entities['object'] ?? []),
                'evidence_routes' => count($routes),
            ],
        ]);
    }

    /**
     * Read-only runtime status for lifecycle governance.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function governance_status(int $courseid = 0, int $frameworkid = 0,
            string $unitcode = '', int $limit = 100): array {
        $graph = relationship_graph_contract::graph_status($courseid, $frameworkid, min($limit, 100));
        $c3 = content_evidence_mapping_contract::content_mapping_status($courseid, $unitcode, min($limit, 100));
        $c3b = evidence_semantics_quality_contract::evidence_semantics_status($courseid, $unitcode, min($limit, 100));
        $history = history_v1_consumer_contract::contract_status($courseid, 1);
        $findings = [];

        if (($graph['status'] ?? '') === 'blocked') {
            $findings[] = self::status_finding('blocker', 'c2_not_frozen',
                'C4 requires C2 relationship graph semantics to remain frozen.');
        }
        if (($c3['status'] ?? '') === 'blocked') {
            $findings[] = self::status_finding('blocker', 'c3_not_frozen',
                'C4 requires C3 content/evidence mappings to remain frozen.');
        }
        if (($c3b['status'] ?? '') === 'blocked') {
            $findings[] = self::status_finding('blocker', 'c3b_not_frozen',
                'C4 requires C3B evidence semantics to remain frozen.');
        }
        if (($history['status'] ?? '') === 'blocked') {
            $findings[] = self::status_finding('blocker', 'history_v1_not_ready',
                'C4 requires History V1 as the only normal source-history input.');
        }

        $scan = self::scan_database_governance($courseid, $frameworkid, $unitcode, max(1, $limit));
        $findings = array_merge($findings, $scan['findings']);
        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['blocker', 'error'], true);
        });

        return [
            'type' => 'CupkpLifecycleGovernanceStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'frozen',
            'contract' => self::contract(),
            'dependencies' => [
                'c2' => $graph['status'] ?? null,
                'c3' => $c3['status'] ?? null,
                'c3b' => $c3b['status'] ?? null,
                'history_v1' => $history['status'] ?? null,
            ],
            'sample' => $scan['sample'],
            'findings' => $findings,
            'read_only' => true,
            'next_allowed_gate' => 'C5',
        ];
    }

    /**
     * Decide if a semantic version value is acceptable for C4 governance.
     *
     * @param string $version
     * @return bool
     */
    private static function semantic_version_is_valid(string $version): bool {
        $version = trim($version);
        if ($version === '') {
            return false;
        }
        return (bool)preg_match('/^(?:[A-Za-z][A-Za-z0-9._-]*-)?v?\d+(?:\.\d+){0,2}(?:[-+][A-Za-z0-9][A-Za-z0-9._-]*)?$/', $version);
    }

    /**
     * Normalize entity type labels.
     *
     * @param string $entitytype
     * @return string
     */
    private static function normalize_entity_type(string $entitytype): string {
        $entitytype = strtolower(trim(str_replace('-', '_', $entitytype)));
        $aliases = [
            'frameworks' => 'framework',
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
        $entitytype = $aliases[$entitytype] ?? $entitytype;
        if (!isset(self::ENTITY_TABLES[$entitytype])) {
            throw new \invalid_parameter_exception('Unknown C4 entity type.');
        }
        return $entitytype;
    }

    /**
     * Normalize mapping type labels.
     *
     * @param string $mappingtype
     * @return string
     */
    private static function normalize_mapping_type(string $mappingtype): string {
        $mappingtype = strtolower(trim(str_replace('-', '_', $mappingtype)));
        $aliases = [
            'competency_up' => 'comp_up',
            'competency_use_point' => 'comp_up',
            'use_point_kp' => 'up_kp',
            'use_point_knowledge_point' => 'up_kp',
            'kp_prerequisite' => 'kp_prereq',
            'knowledge_point_prerequisite' => 'kp_prereq',
            'activity_mapping' => 'object_map',
            'activity_mappings' => 'object_map',
        ];
        $mappingtype = $aliases[$mappingtype] ?? $mappingtype;
        if (!in_array($mappingtype, ['comp_up', 'up_kp', 'kp_prereq', 'object_map'], true)) {
            throw new \invalid_parameter_exception('Unknown C4 mapping type.');
        }
        return $mappingtype;
    }

    /**
     * Get the proposed row status.
     *
     * @param array $newdata
     * @param \stdClass|null $existing
     * @return string
     */
    private static function status_from_row(array $newdata, ?\stdClass $existing): string {
        if (array_key_exists('status', $newdata) && trim((string)$newdata['status']) !== '') {
            return self::canonical_status((string)$newdata['status']);
        }
        if ($existing !== null) {
            return self::status_value_or_draft($existing->status ?? 'draft');
        }
        return 'draft';
    }

    /**
     * Is version required for the status?
     *
     * @param string $status
     * @return bool
     */
    private static function version_required(string $status): bool {
        return in_array($status, ['approved', 'published', 'deprecated', 'archived'], true);
    }

    /**
     * True if lifecycle transition is allowed.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    private static function transition_allowed(string $from, string $to): bool {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Canonicalize a status value, treating legacy blanks as draft.
     *
     * @param mixed $status
     * @return string
     */
    private static function status_value_or_draft($status): string {
        $status = trim((string)$status);
        return $status === '' ? 'draft' : self::canonical_status($status);
    }

    /**
     * Semantic fields changed by a proposed write.
     *
     * @param string $entitytype
     * @param array $newdata
     * @param \stdClass $existing
     * @return array
     */
    private static function changed_semantic_fields(string $entitytype, array $newdata, \stdClass $existing): array {
        $changed = [];
        foreach (self::SEMANTIC_FIELDS[$entitytype] ?? [] as $field) {
            if (!array_key_exists($field, $newdata)) {
                continue;
            }
            $old = property_exists($existing, $field) ? $existing->{$field} : null;
            if (!self::same_value($old, $newdata[$field])) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    /**
     * Stable loose comparison for Moodle DB/form values.
     *
     * @param mixed $left
     * @param mixed $right
     * @return bool
     */
    private static function same_value($left, $right): bool {
        if ($left === null) {
            $left = '';
        }
        if ($right === null) {
            $right = '';
        }
        if (is_float($left) || is_float($right) || is_int($left) || is_int($right)) {
            return (string)$left === (string)$right || (is_numeric($left) && is_numeric($right) &&
                abs((float)$left - (float)$right) < 0.00001);
        }
        return trim((string)$left) === trim((string)$right);
    }

    /**
     * Resolve mapping endpoint DB rows.
     *
     * @param string $mappingtype
     * @param array $row
     * @return array
     */
    private static function mapping_endpoint_records(string $mappingtype, array $row): array {
        global $DB;

        $errors = [];
        $details = [];
        $source = null;
        $target = null;

        if ($mappingtype === 'comp_up') {
            $source = $DB->get_record('flwcupkp_comp', ['id' => (int)($row['competencyid'] ?? 0)], '*', IGNORE_MISSING);
            $target = $DB->get_record('flwcupkp_up', ['id' => (int)($row['upid'] ?? 0)], '*', IGNORE_MISSING);
        } else if ($mappingtype === 'up_kp') {
            $source = $DB->get_record('flwcupkp_up', ['id' => (int)($row['upid'] ?? 0)], '*', IGNORE_MISSING);
            $target = $DB->get_record('flwcupkp_kp', ['id' => (int)($row['kpid'] ?? 0)], '*', IGNORE_MISSING);
        } else if ($mappingtype === 'kp_prereq') {
            $source = $DB->get_record('flwcupkp_kp', ['id' => (int)($row['kpid'] ?? 0)], '*', IGNORE_MISSING);
            $target = $DB->get_record('flwcupkp_kp', ['id' => (int)($row['prereqkpid'] ?? 0)], '*', IGNORE_MISSING);
        } else if ($mappingtype === 'object_map') {
            $source = $DB->get_record('flwcupkp_object', ['id' => (int)($row['objectid'] ?? 0)], '*', IGNORE_MISSING);
            try {
                $target = self::record_for_target((string)($row['targettype'] ?? ''), (int)($row['targetid'] ?? 0));
            } catch (\invalid_parameter_exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!$source) {
            $errors[] = 'C4 mapping source does not exist.';
            $details[] = self::detail('missing_mapping_source', 'ERROR', ['mappingtype' => $mappingtype]);
        }
        if (!$target) {
            $errors[] = 'C4 mapping target does not exist.';
            $details[] = self::detail('missing_mapping_target', 'ERROR', ['mappingtype' => $mappingtype]);
        }

        return [
            'source' => $source ?: new \stdClass(),
            'target' => $target ?: new \stdClass(),
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /**
     * Fetch a target row by type.
     *
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function record_for_target(string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $targettype = self::normalize_entity_type($targettype);
        if (!in_array($targettype, ['competency', 'up', 'kp'], true)) {
            throw new \invalid_parameter_exception('Unsupported C4 target type.');
        }
        return $DB->get_record(self::ENTITY_TABLES[$targettype], ['id' => $targetid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Validate a REPLACED_BY edge.
     *
     * @param \stdClass $source
     * @param \stdClass $target
     * @param array $errors
     * @param array $details
     */
    private static function validate_replacement_endpoint(\stdClass $source, \stdClass $target,
            array &$errors, array &$details): void {
        $sourcestatus = self::status_value_or_draft($source->status ?? 'draft');
        $targetstatus = self::status_value_or_draft($target->status ?? 'draft');
        if ((int)($source->id ?? 0) === (int)($target->id ?? 0)) {
            $errors[] = 'REPLACED_BY cannot point to the same entity.';
            $details[] = self::detail('self_replacement', 'ERROR');
        }
        if (!in_array($sourcestatus, ['deprecated', 'archived'], true)) {
            $errors[] = 'REPLACED_BY source must be DEPRECATED or ARCHIVED.';
            $details[] = self::detail('replacement_source_not_deprecated', 'ERROR', ['status' => $sourcestatus]);
        }
        if (!in_array($targetstatus, ['approved', 'published'], true)) {
            $errors[] = 'REPLACED_BY target must be APPROVED or PUBLISHED.';
            $details[] = self::detail('replacement_target_not_active_successor', 'ERROR', ['status' => $targetstatus]);
        }
    }

    /**
     * Collect package entities by C4 type.
     *
     * @param array $package
     * @return array
     */
    private static function package_entities(array $package): array {
        $map = [
            'frameworks' => 'framework',
            'competencies' => 'competency',
            'use_points' => 'up',
            'knowledge_points' => 'kp',
            'learning_objects' => 'object',
        ];
        $entities = [
            'framework' => [],
            'competency' => [],
            'up' => [],
            'kp' => [],
            'object' => [],
        ];
        foreach ($map as $key => $type) {
            foreach (($package[$key] ?? []) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $externalid = (string)($row['externalid'] ?? '');
                $entities[$type][] = [
                    'index' => $index,
                    'externalid' => $externalid,
                    'row' => $row,
                ];
            }
        }
        return $entities;
    }

    /**
     * Collect package evidence routes keyed by target type and external ID.
     *
     * @param array $package
     * @return array
     */
    private static function package_evidence_routes(array $package): array {
        $routes = [];
        foreach (($package['activity_mappings'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::add_package_route($routes, (string)($row['target_type'] ?? ''),
                (string)($row['target_externalid'] ?? ''), 'activity_mappings');
        }
        foreach (($package['lesson_mappings'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::add_package_route($routes, (string)($row['target_type'] ?? ''),
                (string)($row['target_externalid'] ?? ''), 'lesson_mappings');
            self::add_package_routes($routes, 'kp',
                $row['kp_externalids'] ?? ($row['kp_externalid'] ?? null), 'lesson_mappings');
            self::add_package_routes($routes, 'up',
                $row['up_externalids'] ?? ($row['up_externalid'] ?? null), 'lesson_mappings');
            self::add_package_routes($routes, 'competency',
                $row['competency_externalids'] ?? ($row['competency_externalid'] ?? null), 'lesson_mappings');
        }
        foreach (($package['project_competency_mappings'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::add_package_routes($routes, 'competency',
                $row['competency_externalids'] ?? ($row['competency_externalid'] ?? null),
                'project_competency_mappings');
        }
        foreach (($package['project_evidence'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::add_package_routes($routes, 'competency',
                $row['competency_externalids'] ?? ($row['competency_externalid'] ?? null), 'project_evidence');
        }
        ksort($routes);
        return $routes;
    }

    /**
     * Add one or more target routes.
     *
     * @param array $routes
     * @param string $targettype
     * @param mixed $values
     * @param string $source
     */
    private static function add_package_routes(array &$routes, string $targettype, $values, string $source): void {
        if ($values === null || $values === '') {
            return;
        }
        foreach ((array)$values as $value) {
            self::add_package_route($routes, $targettype, (string)$value, $source);
        }
    }

    /**
     * Add a target route.
     *
     * @param array $routes
     * @param string $targettype
     * @param string $externalid
     * @param string $source
     */
    private static function add_package_route(array &$routes, string $targettype, string $externalid, string $source): void {
        $targettype = strtolower(trim($targettype));
        if ($targettype === 'comp') {
            $targettype = 'competency';
        }
        if ($targettype === '' || $externalid === '') {
            return;
        }
        $routes[$targettype . ':' . trim($externalid)][] = $source;
    }

    /**
     * Detect duplicate package codes in every entity set.
     *
     * @param array $entities
     * @param array $errors
     * @param array $details
     */
    private static function detect_package_duplicate_codes(array $entities, array &$errors, array &$details): void {
        foreach ($entities as $entitytype => $rows) {
            $seen = [];
            foreach ($rows as $entry) {
                $externalid = $entry['externalid'];
                if ($externalid === '') {
                    continue;
                }
                if (isset($seen[$externalid])) {
                    $errors[] = 'Duplicate ' . $entitytype . ' externalid: ' . $externalid;
                    $details[] = self::detail('duplicate_code', 'ERROR', [
                        'entitytype' => $entitytype,
                        'externalid' => $externalid,
                    ]);
                }
                $seen[$externalid] = true;
            }
        }
    }

    /**
     * Validate package entity lifecycle states.
     *
     * @param array $entities
     * @param array $routes
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function validate_package_entity_states(array $entities, array $routes, array &$errors,
            array &$warnings, array &$details): void {
        foreach ($entities as $entitytype => $rows) {
            if ($entitytype === 'object') {
                continue;
            }
            foreach ($rows as $entry) {
                $row = $entry['row'];
                try {
                    $status = self::status_from_row($row, null);
                } catch (\invalid_parameter_exception $e) {
                    $errors[] = $entry['externalid'] . ': ' . $e->getMessage();
                    $details[] = self::detail('unsupported_lifecycle_status', 'ERROR', [
                        'entitytype' => $entitytype,
                        'externalid' => $entry['externalid'],
                    ]);
                    continue;
                }
                $version = (string)($row['version'] ?? '');
                if (self::version_required($status) && !self::semantic_version_is_valid($version)) {
                    $errors[] = $entry['externalid'] . ': ' . strtoupper($status) .
                        ' package rows require a semantic version.';
                    $details[] = self::detail('invalid_published_state', 'ERROR', [
                        'entitytype' => $entitytype,
                        'externalid' => $entry['externalid'],
                        'status' => $status,
                    ]);
                }
                if (in_array($status, ['published'], true) &&
                        in_array($entitytype, ['competency', 'up', 'kp'], true) &&
                        empty($routes[$entitytype . ':' . $entry['externalid']])) {
                    $errors[] = $entry['externalid'] . ': PUBLISHED targets require at least one evidence route.';
                    $details[] = self::detail('missing_evidence_route', 'ERROR', [
                        'entitytype' => $entitytype,
                        'externalid' => $entry['externalid'],
                    ]);
                } else if ($status === 'approved' && in_array($entitytype, ['competency', 'up', 'kp'], true) &&
                        empty($routes[$entitytype . ':' . $entry['externalid']])) {
                    $warnings[] = $entry['externalid'] . ': APPROVED targets should declare an evidence route before publish.';
                    $details[] = self::detail('approved_target_without_evidence_route', 'WARNING', [
                        'entitytype' => $entitytype,
                        'externalid' => $entry['externalid'],
                    ]);
                }
            }
        }
    }

    /**
     * Validate package orphan states.
     *
     * @param array $entities
     * @param array $package
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function validate_package_orphans(array $entities, array $package, array &$errors,
            array &$warnings, array &$details): void {
        $upmapped = [];
        foreach (($package['competency_up_mappings'] ?? []) as $row) {
            if (is_array($row) && !empty($row['up_externalid'])) {
                $upmapped[(string)$row['up_externalid']] = true;
            }
        }
        $kpmapped = [];
        foreach (($package['up_kp_mappings'] ?? []) as $row) {
            if (is_array($row) && !empty($row['kp_externalid'])) {
                $kpmapped[(string)$row['kp_externalid']] = true;
            }
        }

        foreach ($entities['up'] as $entry) {
            if ($entry['externalid'] === '' || isset($upmapped[$entry['externalid']])) {
                continue;
            }
            self::append_orphan_package_finding($entry, 'up', $errors, $warnings, $details);
        }
        foreach ($entities['kp'] as $entry) {
            if ($entry['externalid'] === '' || isset($kpmapped[$entry['externalid']])) {
                continue;
            }
            self::append_orphan_package_finding($entry, 'kp', $errors, $warnings, $details);
        }
    }

    /**
     * Append an orphan finding, making published orphans errors.
     *
     * @param array $entry
     * @param string $entitytype
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function append_orphan_package_finding(array $entry, string $entitytype, array &$errors,
            array &$warnings, array &$details): void {
        try {
            $status = self::status_from_row($entry['row'], null);
        } catch (\invalid_parameter_exception $e) {
            $status = 'draft';
        }
        $message = $entry['externalid'] . ': orphan ' . strtoupper($entitytype) . ' has no required parent mapping.';
        if ($status === 'published') {
            $errors[] = $message;
            $details[] = self::detail('published_orphan', 'ERROR', [
                'entitytype' => $entitytype,
                'externalid' => $entry['externalid'],
            ]);
        } else {
            $warnings[] = $message;
            $details[] = self::detail('orphan', 'WARNING', [
                'entitytype' => $entitytype,
                'externalid' => $entry['externalid'],
            ]);
        }
    }

    /**
     * Validate package REPLACED_BY semantics.
     *
     * @param array $entities
     * @param array $package
     * @param array $errors
     * @param array $details
     */
    private static function validate_package_replacements(array $entities, array $package, array &$errors,
            array &$details): void {
        $kpstatus = [];
        foreach ($entities['kp'] as $entry) {
            if ($entry['externalid'] !== '') {
                $kpstatus[$entry['externalid']] = self::status_from_row($entry['row'], null);
            }
        }

        foreach (($package['kp_prerequisites'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string)($row['relationship_type'] ?? ($row['relationshiptype'] ?? ''))));
            if ($type !== 'replaced_by' && $type !== 'replaced-by' && $type !== 'replaced by') {
                continue;
            }
            $source = (string)($row['kp_externalid'] ?? ($row['kpid'] ?? ''));
            $target = (string)($row['prereq_kp_externalid'] ?? ($row['prereqkpid'] ?? ''));
            $sourcestatus = $kpstatus[$source] ?? '';
            $targetstatus = $kpstatus[$target] ?? '';

            if ($source === $target) {
                $errors[] = 'kp_prerequisites[' . $index . ']: REPLACED_BY cannot point to itself.';
                $details[] = self::detail('self_replacement', 'ERROR');
            }
            if (!in_array($sourcestatus, ['deprecated', 'archived'], true)) {
                $errors[] = 'kp_prerequisites[' . $index . ']: REPLACED_BY source must be DEPRECATED or ARCHIVED.';
                $details[] = self::detail('replacement_source_not_deprecated', 'ERROR', [
                    'externalid' => $source,
                    'status' => $sourcestatus,
                ]);
            }
            if (!in_array($targetstatus, ['approved', 'published'], true)) {
                $errors[] = 'kp_prerequisites[' . $index . ']: REPLACED_BY target must be APPROVED or PUBLISHED.';
                $details[] = self::detail('replacement_target_not_active_successor', 'ERROR', [
                    'externalid' => $target,
                    'status' => $targetstatus,
                ]);
            }
        }
    }

    /**
     * Scan existing DB governance state.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    private static function scan_database_governance(int $courseid, int $frameworkid,
            string $unitcode, int $limit): array {
        $findings = [];
        $sample = [
            'duplicate_codes' => 0,
            'invalid_relationships' => 0,
            'orphans' => [
                'up_without_competency' => 0,
                'kp_without_up' => 0,
                'object_without_target' => 0,
            ],
            'published_targets_missing_evidence_routes' => 0,
            'invalid_replacements' => 0,
            'invalid_published_states' => 0,
        ];

        self::scan_duplicate_codes($findings, $sample, $limit);
        self::scan_invalid_relationships($findings, $sample, $limit);
        self::scan_orphans($findings, $sample, $frameworkid, $courseid, $unitcode);
        self::scan_published_states($findings, $sample, $frameworkid, $courseid, $unitcode, $limit);
        self::scan_replacements($findings, $sample, $frameworkid, $limit);

        return ['findings' => $findings, 'sample' => $sample];
    }

    /**
     * Find duplicate external IDs in DB tables.
     *
     * @param array $findings
     * @param array $sample
     * @param int $limit
     */
    private static function scan_duplicate_codes(array &$findings, array &$sample, int $limit): void {
        global $DB;

        foreach (self::ENTITY_TABLES as $entitytype => $table) {
            $records = $DB->get_records_sql(
                "SELECT externalid, COUNT(1) AS dupcount
                   FROM {{$table}}
               GROUP BY externalid
                 HAVING COUNT(1) > 1",
                [], 0, $limit
            );
            foreach ($records as $record) {
                $sample['duplicate_codes']++;
                $findings[] = self::status_finding('error', 'duplicate_code',
                    $entitytype . ' externalid is duplicated: ' . $record->externalid);
            }
        }
    }

    /**
     * Find relationship rows with missing targets.
     *
     * @param array $findings
     * @param array $sample
     * @param int $limit
     */
    private static function scan_invalid_relationships(array &$findings, array &$sample, int $limit): void {
        global $DB;

        $checks = [
            'comp_up' => "SELECT COUNT(1) FROM {flwcupkp_comp_up} m
                           LEFT JOIN {flwcupkp_comp} c ON c.id = m.competencyid
                           LEFT JOIN {flwcupkp_up} u ON u.id = m.upid
                          WHERE c.id IS NULL OR u.id IS NULL",
            'up_kp' => "SELECT COUNT(1) FROM {flwcupkp_up_kp} m
                         LEFT JOIN {flwcupkp_up} u ON u.id = m.upid
                         LEFT JOIN {flwcupkp_kp} k ON k.id = m.kpid
                        WHERE u.id IS NULL OR k.id IS NULL",
            'kp_prereq' => "SELECT COUNT(1) FROM {flwcupkp_kp_prereq} m
                             LEFT JOIN {flwcupkp_kp} k ON k.id = m.kpid
                             LEFT JOIN {flwcupkp_kp} p ON p.id = m.prereqkpid
                            WHERE k.id IS NULL OR p.id IS NULL",
            'object_map' => "SELECT COUNT(1) FROM {flwcupkp_object_map} m
                              LEFT JOIN {flwcupkp_object} o ON o.id = m.objectid
                              LEFT JOIN {flwcupkp_comp} c ON c.id = m.targetid AND m.targettype = 'competency'
                              LEFT JOIN {flwcupkp_up} u ON u.id = m.targetid AND m.targettype = 'up'
                              LEFT JOIN {flwcupkp_kp} k ON k.id = m.targetid AND m.targettype = 'kp'
                             WHERE o.id IS NULL
                                OR (m.targettype = 'competency' AND c.id IS NULL)
                                OR (m.targettype = 'up' AND u.id IS NULL)
                                OR (m.targettype = 'kp' AND k.id IS NULL)
                                OR m.targettype NOT IN ('competency', 'up', 'kp')",
        ];

        foreach ($checks as $type => $sql) {
            $count = (int)$DB->count_records_sql($sql);
            $sample['invalid_relationships'] += $count;
            if ($count > 0) {
                $findings[] = self::status_finding('error', 'invalid_relationship',
                    $type . ' has missing or invalid endpoint rows: ' . $count);
            }
        }
    }

    /**
     * Find orphan rows.
     *
     * @param array $findings
     * @param array $sample
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     */
    private static function scan_orphans(array &$findings, array &$sample, int $frameworkid,
            int $courseid, string $unitcode): void {
        global $DB;

        $upframeworkclause = $frameworkid > 0 ? ' AND u.frameworkid = :frameworkid' : '';
        $kpframeworkclause = $frameworkid > 0 ? ' AND k.frameworkid = :frameworkid' : '';
        $frameworkparams = $frameworkid > 0 ? ['frameworkid' => $frameworkid] : [];
        $upcount = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_up} u
              WHERE NOT EXISTS (SELECT 1 FROM {flwcupkp_comp_up} m WHERE m.upid = u.id)" . $upframeworkclause,
            $frameworkparams
        );
        $kpcount = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_kp} k
              WHERE NOT EXISTS (SELECT 1 FROM {flwcupkp_up_kp} m WHERE m.kpid = k.id)" . $kpframeworkclause,
            $frameworkparams
        );

        $objectwhere = '1=1';
        $objectparams = [];
        if ($frameworkid > 0) {
            $objectwhere .= ' AND o.frameworkid = :oframeworkid';
            $objectparams['oframeworkid'] = $frameworkid;
        }
        if ($courseid > 0) {
            $objectwhere .= ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
            $objectparams['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $objectwhere .= ' AND o.unitcode = :unitcode';
            $objectparams['unitcode'] = $unitcode;
        }
        $objectcount = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_object} o
              WHERE {$objectwhere}
                AND NOT EXISTS (SELECT 1 FROM {flwcupkp_object_map} m WHERE m.objectid = o.id)",
            $objectparams
        );

        $sample['orphans']['up_without_competency'] = $upcount;
        $sample['orphans']['kp_without_up'] = $kpcount;
        $sample['orphans']['object_without_target'] = $objectcount;

        if ($upcount > 0) {
            $findings[] = self::status_finding('warning', 'orphan_up',
                'Use Points without a competency mapping: ' . $upcount);
        }
        if ($kpcount > 0) {
            $findings[] = self::status_finding('warning', 'orphan_kp',
                'Knowledge Points without a UP mapping: ' . $kpcount);
        }
        if ($objectcount > 0) {
            $findings[] = self::status_finding('warning', 'orphan_learning_object',
                'Learning objects without a target mapping: ' . $objectcount);
        }
    }

    /**
     * Find invalid published states and missing evidence routes.
     *
     * @param array $findings
     * @param array $sample
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     */
    private static function scan_published_states(array &$findings, array &$sample, int $frameworkid,
            int $courseid, string $unitcode, int $limit): void {
        global $DB;

        $publishedvalues = self::published_status_values();
        [$insql, $inparams] = $DB->get_in_or_equal($publishedvalues, SQL_PARAMS_NAMED, 'pub');

        foreach ([
            'framework' => ['table' => 'flwcupkp_framework', 'alias' => 'f', 'target' => null],
            'competency' => ['table' => 'flwcupkp_comp', 'alias' => 'c', 'target' => 'competency'],
            'up' => ['table' => 'flwcupkp_up', 'alias' => 'u', 'target' => 'up'],
            'kp' => ['table' => 'flwcupkp_kp', 'alias' => 'k', 'target' => 'kp'],
        ] as $entitytype => $config) {
            $table = $config['table'];
            $alias = $config['alias'];
            $where = "{$alias}.status {$insql}";
            $params = $inparams;
            if ($frameworkid > 0) {
                $where .= $entitytype === 'framework' ? ' AND f.id = :frameworkid' :
                    " AND {$alias}.frameworkid = :frameworkid";
                $params['frameworkid'] = $frameworkid;
            }
            $records = $DB->get_records_sql(
                "SELECT {$alias}.id, {$alias}.externalid, {$alias}.status, {$alias}.version
                   FROM {{$table}} {$alias}
                  WHERE {$where}
               ORDER BY {$alias}.externalid ASC",
                $params, 0, $limit
            );
            foreach ($records as $record) {
                if (!self::semantic_version_is_valid((string)($record->version ?? ''))) {
                    $sample['invalid_published_states']++;
                    $findings[] = self::status_finding('error', 'invalid_published_state',
                        $entitytype . ' ' . $record->externalid . ' is published without a valid version.');
                }
                if ($config['target'] !== null && !self::target_has_evidence_route(
                        $config['target'], (int)$record->id, $courseid, $unitcode)) {
                    $sample['published_targets_missing_evidence_routes']++;
                    $findings[] = self::status_finding('warning', 'missing_evidence_route',
                        strtoupper($config['target']) . ' ' . $record->externalid .
                        ' is published without an object evidence route.');
                }
            }
        }
    }

    /**
     * Find invalid DB replacement rows.
     *
     * @param array $findings
     * @param array $sample
     * @param int $frameworkid
     * @param int $limit
     */
    private static function scan_replacements(array &$findings, array &$sample, int $frameworkid, int $limit): void {
        global $DB;

        $where = "LOWER(m.relationshiptype) IN ('replaced_by', 'replaced-by', 'replaced by')";
        $params = [];
        if ($frameworkid > 0) {
            $where .= ' AND s.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $records = $DB->get_records_sql(
            "SELECT m.id, s.id AS sourceid, s.externalid AS sourceexternalid, s.status AS sourcestatus,
                    s.frameworkid AS sourceframeworkid, t.id AS targetid, t.externalid AS targetexternalid,
                    t.status AS targetstatus, t.frameworkid AS targetframeworkid
               FROM {flwcupkp_kp_prereq} m
               JOIN {flwcupkp_kp} s ON s.id = m.kpid
               JOIN {flwcupkp_kp} t ON t.id = m.prereqkpid
              WHERE {$where}
           ORDER BY m.id ASC",
            $params, 0, $limit
        );

        foreach ($records as $record) {
            $invalid = false;
            $sourcestatus = self::status_value_or_draft($record->sourcestatus);
            $targetstatus = self::status_value_or_draft($record->targetstatus);
            if ((int)$record->sourceid === (int)$record->targetid ||
                    (int)$record->sourceframeworkid !== (int)$record->targetframeworkid ||
                    !in_array($sourcestatus, ['deprecated', 'archived'], true) ||
                    !in_array($targetstatus, ['approved', 'published'], true)) {
                $invalid = true;
            }
            if ($invalid) {
                $sample['invalid_replacements']++;
                $findings[] = self::status_finding('error', 'invalid_replacement',
                    'REPLACED_BY row ' . (int)$record->id . ' has invalid source/target lifecycle state.');
            }
        }
    }

    /**
     * Published-status values including legacy aliases found in older DB rows.
     *
     * @return array
     */
    private static function published_status_values(): array {
        return ['published', 'active', 'reference'];
    }

    /**
     * Check whether a target has an object route.
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $courseid
     * @param string $unitcode
     * @return bool
     */
    private static function target_has_evidence_route(string $targettype, int $targetid,
            int $courseid, string $unitcode): bool {
        global $DB;

        $where = 'm.targettype = :targettype AND m.targetid = :targetid';
        $params = ['targettype' => $targettype, 'targetid' => $targetid];
        if ($courseid > 0 || $unitcode !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM {flwcupkp_object} o WHERE o.id = m.objectid';
            if ($courseid > 0) {
                $where .= ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
                $params['courseid'] = $courseid;
            }
            if ($unitcode !== '') {
                $where .= ' AND o.unitcode = :unitcode';
                $params['unitcode'] = $unitcode;
            }
            $where .= ')';
        }
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {flwcupkp_object_map} m
              WHERE {$where}",
            $params
        );
    }

    /**
     * Convert a status scan into a finding row.
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
     * Build a validation result.
     *
     * @param array $errors
     * @param array $warnings
     * @param array $details
     * @param array $extra
     * @return array
     */
    private static function result(array $errors, array $warnings, array $details, array $extra = []): array {
        return array_merge([
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'contract' => self::CONTRACT_VERSION,
        ], $extra);
    }

    /**
     * Build a classified validation detail.
     *
     * @param string $code
     * @param string $severity
     * @param array $extra
     * @return array
     */
    private static function detail(string $code, string $severity, array $extra = []): array {
        return array_merge([
            'code' => $code,
            'severity' => strtoupper($severity),
            'gate' => self::GATE,
        ], $extra);
    }
}

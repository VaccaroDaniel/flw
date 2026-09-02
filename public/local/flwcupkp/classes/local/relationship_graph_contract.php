<?php
// Program 3 Gate C2 relationship and prerequisite graph semantics.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes relationship semantics and centralizes curriculum graph queries.
 */
final class relationship_graph_contract {
    /** Program 3 relationship graph gate. */
    public const GATE = 'P3_C2';

    /** Frozen C2 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_RELATIONSHIP_GRAPH_V1';

    /** @var array Existing mapping tables that contribute C-UP-KP graph edges. */
    private const MAPPING_TABLES = [
        'comp_up' => 'flwcupkp_comp_up',
        'up_kp' => 'flwcupkp_up_kp',
        'kp_prereq' => 'flwcupkp_kp_prereq',
        'object_map' => 'flwcupkp_object_map',
    ];

    /** @var array Frozen C2 relation contract matrix. */
    private const RELATIONS = [
        'SUPPORTS' => [
            'allowed_source_types' => ['competency', 'up', 'kp', 'object'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'source_supports_target',
            'cardinality' => 'many_to_many',
            'symmetry' => false,
            'transitivity' => false,
            'cycles' => 'allowed_but_not_inferred',
            'inference' => 'No automatic mastery or dependency inference.',
            'version_behavior' => 'Support links are copied only by explicit framework version clone operations.',
            'deprecation_behavior' => 'New active support links must not target archived, deprecated, or retired nodes.',
        ],
        'REQUIRES' => [
            'allowed_source_types' => ['competency', 'up', 'kp'],
            'allowed_target_types' => ['up', 'kp'],
            'direction' => 'source_requires_target',
            'cardinality' => 'many_to_many',
            'symmetry' => false,
            'transitivity' => true,
            'cycles' => 'hard_prerequisite_cycles_forbidden',
            'inference' => 'Transitive only for dependency analysis; it does not imply mastery.',
            'version_behavior' => 'Required links remain tied to the framework version that declared them.',
            'deprecation_behavior' => 'Required links to archived, deprecated, or retired nodes must be removed or replaced.',
        ],
        'EVIDENCE_FOR' => [
            'allowed_source_types' => ['object'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'source_produces_evidence_for_target',
            'cardinality' => 'many_to_many',
            'symmetry' => false,
            'transitivity' => false,
            'cycles' => 'not_applicable',
            'inference' => 'Evidence may be consumed later only by explicit evidence and mastery policies.',
            'version_behavior' => 'Historical evidence remains attached to the original object and target version.',
            'deprecation_behavior' => 'Evidence links may remain for history but new active collection requires active targets.',
        ],
        'TRAINS' => [
            'allowed_source_types' => ['object'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'source_trains_target',
            'cardinality' => 'many_to_many',
            'symmetry' => false,
            'transitivity' => false,
            'cycles' => 'not_applicable',
            'inference' => 'Training links do not create evidence unless a later evidence policy says so.',
            'version_behavior' => 'Training links are preserved with the object version and framework version.',
            'deprecation_behavior' => 'Deprecated training links are hidden from new learner-path selection.',
        ],
        'EXTENDS' => [
            'allowed_source_types' => ['competency', 'up', 'kp'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'source_extends_target',
            'cardinality' => 'many_to_many_same_type',
            'symmetry' => false,
            'transitivity' => false,
            'cycles' => 'discouraged_not_inferred',
            'inference' => 'Extension is descriptive only and does not inherit prerequisites or mastery.',
            'version_behavior' => 'Extension links do not replace version lineage.',
            'deprecation_behavior' => 'An extension may point to retained reference material but must not reactivate retired targets.',
        ],
        'ALTERNATIVE_TO' => [
            'allowed_source_types' => ['competency', 'up', 'kp'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'undirected_equivalent_pair',
            'cardinality' => 'many_to_many_same_type',
            'symmetry' => true,
            'transitivity' => false,
            'cycles' => 'allowed',
            'inference' => 'Alternatives are candidate substitutions only; no automatic mastery transfer.',
            'version_behavior' => 'Alternatives remain peer links and are not version successors.',
            'deprecation_behavior' => 'Deprecated alternatives may be shown for explanation but not selected as new defaults.',
        ],
        'REVIEW_OF' => [
            'allowed_source_types' => ['competency', 'up', 'kp', 'object'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'source_reviews_target',
            'cardinality' => 'many_to_many',
            'symmetry' => false,
            'transitivity' => false,
            'cycles' => 'allowed_but_not_inferred',
            'inference' => 'Review links are reinforcement hints only.',
            'version_behavior' => 'Review links remain anchored to the reviewed target version.',
            'deprecation_behavior' => 'Review links to deprecated targets are kept only for historical explanation.',
        ],
        'REPLACED_BY' => [
            'allowed_source_types' => ['competency', 'up', 'kp'],
            'allowed_target_types' => ['competency', 'up', 'kp'],
            'direction' => 'source_replaced_by_target',
            'cardinality' => 'one_successor_per_source_unless_explicitly_split',
            'symmetry' => false,
            'transitivity' => true,
            'cycles' => 'forbidden',
            'inference' => 'Historical evidence remains on the original entity; no automatic learner-state copy.',
            'version_behavior' => 'Successor links express lineage only, not learner-state inheritance.',
            'deprecation_behavior' => 'The source should be deprecated, retired, or archived before replacement is operational.',
        ],
    ];

    /**
     * Return the frozen C2 relationship graph contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpRelationshipGraphContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                canonical_domain_model::CONTRACT_VERSION,
                ontology_boundary::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'relations' => self::RELATIONS,
            'mapping_semantics' => [
                'flwcupkp_comp_up' => 'competency -> up',
                'flwcupkp_up_kp' => 'up -> kp',
                'flwcupkp_kp_prereq' => 'kp -> kp',
                'flwcupkp_object_map' => 'object -> competency|up|kp',
            ],
            'hard_prerequisite_rule' => 'A kp_prereq row is a hard prerequisite only when its requirement is mandatory and its semantic relation is REQUIRES.',
            'centralized_query_apis' => [
                'adjacency',
                'dependencies_for_target',
                'where_used',
                'graph_status',
                'detect_hard_prerequisite_cycles',
            ],
            'does_not_do' => [
                'adaptive_path_selection',
                'mastery_state_recalculation',
                'evidence_quality_policy',
                'raw_moodle_log_scraping',
            ],
        ];
    }

    /**
     * Return frozen relation definitions.
     *
     * @return array
     */
    public static function relation_types(): array {
        return self::RELATIONS;
    }

    /**
     * Resolve one plugin mapping row into the frozen C2 relation label.
     *
     * @param string $mappingtype
     * @param mixed $row
     * @return string
     */
    public static function semantic_for_mapping(string $mappingtype, $row): string {
        $mappingtype = self::normalize_mapping_type($mappingtype);
        $row = self::row_to_array($row);

        if ($mappingtype === 'comp_up' || $mappingtype === 'up_kp') {
            $role = self::normalize_label((string)($row['role'] ?? 'required'));
            if ($role === 'required') {
                return 'REQUIRES';
            }
            if ($role === 'assessment' || $role === 'evidence') {
                return 'EVIDENCE_FOR';
            }
            return 'SUPPORTS';
        }

        if ($mappingtype === 'kp_prereq') {
            $relationtype = self::normalize_label((string)($row['relationshiptype'] ??
                ($row['relationship_type'] ?? 'prerequisite')));
            if ($relationtype === 'replaced_by') {
                return 'REPLACED_BY';
            }
            if ($relationtype === 'alternative_to') {
                return 'ALTERNATIVE_TO';
            }
            if ($relationtype === 'review_of') {
                return 'REVIEW_OF';
            }
            if ($relationtype === 'extends') {
                return 'EXTENDS';
            }
            $requirement = self::normalize_label((string)($row['requirement'] ?? 'recommended'));
            if ($requirement === 'mandatory' || in_array($relationtype, ['prerequisite', 'requires'], true)) {
                return 'REQUIRES';
            }
            return 'SUPPORTS';
        }

        if ($mappingtype === 'object_map') {
            $role = self::normalize_label((string)($row['role'] ?? 'practice'));
            if (in_array($role, ['assessment', 'assesses', 'evidence_for'], true)) {
                return 'EVIDENCE_FOR';
            }
            if (in_array($role, ['review', 'review_of'], true)) {
                return 'REVIEW_OF';
            }
            return 'TRAINS';
        }

        throw new \invalid_parameter_exception('Unknown C-UP-KP mapping type.');
    }

    /**
     * Validate one graph mapping row against frozen C2 relation semantics.
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

        try {
            $semantic = self::semantic_for_mapping($mappingtype, $row);
            $endpoint = self::endpoint_for_mapping($mappingtype, $row);
        } catch (\invalid_parameter_exception $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
                'warnings' => [],
                'details' => [],
                'contract' => self::CONTRACT_VERSION,
            ];
        }

        $rule = self::RELATIONS[$semantic] ?? null;
        if ($rule === null) {
            $errors[] = 'Unsupported C2 relationship semantic: ' . $semantic . '.';
            $details[] = self::detail('unsupported_relation_semantic', 'error', $mappingtype, $semantic);
        } else {
            if (!in_array($endpoint['source_type'], $rule['allowed_source_types'], true)) {
                $errors[] = $mappingtype . ' cannot use ' . $semantic . ' from ' . $endpoint['source_type'] . '.';
                $details[] = self::detail('invalid_source_type', 'error', $mappingtype, $semantic);
            }
            if (!in_array($endpoint['target_type'], $rule['allowed_target_types'], true)) {
                $errors[] = $mappingtype . ' cannot use ' . $semantic . ' to ' . $endpoint['target_type'] . '.';
                $details[] = self::detail('invalid_target_type', 'error', $mappingtype, $semantic);
            }
            if (strpos((string)$rule['cardinality'], 'same_type') !== false &&
                    $endpoint['source_type'] !== $endpoint['target_type']) {
                $errors[] = $semantic . ' requires source and target to be the same C-UP-KP type.';
                $details[] = self::detail('same_type_required', 'error', $mappingtype, $semantic);
            }
        }

        if ($mappingtype === 'kp_prereq') {
            $source = (string)($endpoint['source_id'] ?? '');
            $target = (string)($endpoint['target_id'] ?? '');
            if ($source !== '' && $target !== '' && $source === $target) {
                $errors[] = 'A Knowledge Point graph edge cannot point to itself.';
                $details[] = self::detail('self_kp_graph_edge', 'error', $mappingtype, $semantic);
            }
            if ($semantic === 'ALTERNATIVE_TO' &&
                    self::normalize_label((string)($row['requirement'] ?? 'recommended')) === 'mandatory') {
                $errors[] = 'ALTERNATIVE_TO cannot be declared as a mandatory prerequisite.';
                $details[] = self::detail('alternative_marked_mandatory', 'error', $mappingtype, $semantic);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'contract' => self::CONTRACT_VERSION,
            'semantic' => $semantic,
            'endpoint' => $endpoint,
        ];
    }

    /**
     * Throw when a mapping row violates the C2 relationship graph contract.
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
     * Throw when adding/updating a mapping would violate whole-graph C2 invariants.
     *
     * @param string $mappingtype
     * @param array $row
     */
    public static function assert_mapping_change(string $mappingtype, array $row): void {
        $mappingtype = self::normalize_mapping_type($mappingtype);
        self::assert_mapping_row($mappingtype, $row);

        if ($mappingtype !== 'kp_prereq') {
            return;
        }

        $semantic = self::semantic_for_mapping($mappingtype, $row);
        $endpoint = self::endpoint_for_mapping($mappingtype, $row);
        if ($semantic !== 'REPLACED_BY' && !self::is_hard_prerequisite_row($mappingtype, $row)) {
            return;
        }

        $edges = self::existing_kp_edges_for_cycle_scan($semantic);
        $edges[] = [
            'from' => 'kp:' . (int)$endpoint['source_id'],
            'to' => 'kp:' . (int)$endpoint['target_id'],
            'label' => $semantic,
        ];
        $cycles = self::detect_cycles($edges);
        if ($cycles) {
            $message = $semantic === 'REPLACED_BY' ?
                'REPLACED_BY cycle detected: ' :
                'Hard prerequisite cycle detected: ';
            throw new \invalid_parameter_exception($message . implode(' -> ', $cycles[0]));
        }
    }

    /**
     * Validate all package graph rows and package-level graph invariants.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package_graph(array $package): array {
        $errors = [];
        $warnings = [];
        $details = [];
        $hardedges = [];
        $replacededges = [];
        $counts = [
            'comp_up' => 0,
            'up_kp' => 0,
            'kp_prereq' => 0,
            'object_map' => 0,
        ];

        foreach (($package['competency_up_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $counts['comp_up']++;
            self::merge_result('competency_up_mappings[' . $index . ']',
                self::validate_mapping_row('comp_up', $row), $errors, $warnings, $details);
        }

        foreach (($package['up_kp_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $counts['up_kp']++;
            self::merge_result('up_kp_mappings[' . $index . ']',
                self::validate_mapping_row('up_kp', $row), $errors, $warnings, $details);
        }

        foreach (($package['kp_prerequisites'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $counts['kp_prereq']++;
            $result = self::validate_mapping_row('kp_prereq', $row);
            self::merge_result('kp_prerequisites[' . $index . ']', $result, $errors, $warnings, $details);
            if (!empty($result['semantic']) && self::is_hard_prerequisite_row('kp_prereq', $row)) {
                $hardedges[] = [
                    'from' => 'kp:' . (string)($row['kp_externalid'] ?? ($row['kpid'] ?? '')),
                    'to' => 'kp:' . (string)($row['prereq_kp_externalid'] ?? ($row['prereqkpid'] ?? '')),
                    'label' => 'REQUIRES',
                ];
            }
            if (($result['semantic'] ?? '') === 'REPLACED_BY') {
                $replacededges[] = [
                    'from' => 'kp:' . (string)($row['kp_externalid'] ?? ($row['kpid'] ?? '')),
                    'to' => 'kp:' . (string)($row['prereq_kp_externalid'] ?? ($row['prereqkpid'] ?? '')),
                    'label' => 'REPLACED_BY',
                ];
            }
        }

        foreach (self::package_object_map_rows($package) as $context => $row) {
            $counts['object_map']++;
            self::merge_result($context, self::validate_mapping_row('object_map', $row),
                $errors, $warnings, $details);
        }

        foreach (self::detect_hard_prerequisite_cycles($hardedges) as $cycle) {
            $errors[] = 'Hard prerequisite cycle detected: ' . implode(' -> ', $cycle);
            $details[] = self::detail('hard_prerequisite_cycle', 'error', 'kp_prereq', 'REQUIRES');
        }
        foreach (self::detect_cycles($replacededges) as $cycle) {
            $errors[] = 'REPLACED_BY cycle detected: ' . implode(' -> ', $cycle);
            $details[] = self::detail('replaced_by_cycle', 'error', 'kp_prereq', 'REPLACED_BY');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'contract' => self::CONTRACT_VERSION,
            'counts' => $counts,
        ];
    }

    /**
     * Detect cycles in a hard prerequisite edge list.
     *
     * Edge format: ['from' => string, 'to' => string].
     *
     * @param array $edges
     * @return array
     */
    public static function detect_hard_prerequisite_cycles(array $edges): array {
        return self::detect_cycles($edges);
    }

    /**
     * Return current graph adjacency across plugin-owned mapping tables.
     *
     * @param int $frameworkid
     * @param array $options
     * @return array
     */
    public static function adjacency(int $frameworkid = 0, array $options = []): array {
        global $DB;

        $limit = max(0, (int)($options['limit'] ?? 0));
        $edges = [];

        $params = [];
        $where = '';
        if ($frameworkid > 0) {
            $where = ' WHERE c.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $records = $DB->get_records_sql(
            "SELECT m.*, c.frameworkid
               FROM {flwcupkp_comp_up} m
               JOIN {flwcupkp_comp} c ON c.id = m.competencyid" . $where,
            $params, 0, $limit
        );
        foreach (array_values($records) as $record) {
            $edges[] = self::edge_from_record('comp_up', $record, 'competency', (int)$record->competencyid,
                'up', (int)$record->upid);
        }

        $params = [];
        $where = '';
        if ($frameworkid > 0) {
            $where = ' WHERE u.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $records = $DB->get_records_sql(
            "SELECT m.*, u.frameworkid
               FROM {flwcupkp_up_kp} m
               JOIN {flwcupkp_up} u ON u.id = m.upid" . $where,
            $params, 0, $limit
        );
        foreach (array_values($records) as $record) {
            $edges[] = self::edge_from_record('up_kp', $record, 'up', (int)$record->upid,
                'kp', (int)$record->kpid);
        }

        $params = [];
        $where = '';
        if ($frameworkid > 0) {
            $where = ' WHERE k.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $records = $DB->get_records_sql(
            "SELECT m.*, k.frameworkid
               FROM {flwcupkp_kp_prereq} m
               JOIN {flwcupkp_kp} k ON k.id = m.kpid" . $where,
            $params, 0, $limit
        );
        foreach (array_values($records) as $record) {
            $edges[] = self::edge_from_record('kp_prereq', $record, 'kp', (int)$record->kpid,
                'kp', (int)$record->prereqkpid);
        }

        $params = [];
        $where = '';
        if ($frameworkid > 0) {
            $where = ' WHERE o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $records = $DB->get_records_sql(
            "SELECT m.*, o.frameworkid
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid" . $where,
            $params, 0, $limit
        );
        foreach (array_values($records) as $record) {
            $targettype = self::normalize_entity_type((string)$record->targettype);
            $edges[] = self::edge_from_record('object_map', $record, 'object', (int)$record->objectid,
                $targettype, (int)$record->targetid);
        }

        return $edges;
    }

    /**
     * Return transitive C2 REQUIRES dependencies for a target node.
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $frameworkid
     * @return array
     */
    public static function dependencies_for_target(string $targettype, int $targetid, int $frameworkid = 0): array {
        $start = self::node_key(self::normalize_entity_type($targettype), $targetid);
        $edges = array_values(array_filter(self::adjacency($frameworkid), static function(array $edge): bool {
            return ($edge['relation'] ?? '') === 'REQUIRES';
        }));
        return self::walk_graph($start, $edges, false);
    }

    /**
     * Return graph rows that use or depend on a target node.
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $frameworkid
     * @return array
     */
    public static function where_used(string $targettype, int $targetid, int $frameworkid = 0): array {
        $start = self::node_key(self::normalize_entity_type($targettype), $targetid);
        return self::walk_graph($start, self::adjacency($frameworkid), true);
    }

    /**
     * Read-only runtime status for the C2 graph contract.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function graph_status(int $courseid = 0, int $frameworkid = 0, int $limit = 100): array {
        $boundary = ontology_boundary::boundary_status($courseid, $frameworkid, min($limit, 100));
        $findings = [];
        if (($boundary['status'] ?? '') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'c1b_not_guarded',
                'message' => 'C2 requires C1B ontology boundary validation to remain guarded.',
            ];
        }

        $scan = self::scan_existing_graph($frameworkid, max(1, $limit));
        $findings = array_merge($findings, $scan['findings']);
        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['blocker', 'error'], true);
        });

        return [
            'type' => 'CupkpRelationshipGraphStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'frozen',
            'contract' => self::contract(),
            'c1b' => [
                'status' => $boundary['status'] ?? null,
                'contract' => $boundary['contract']['version'] ?? null,
            ],
            'sample' => $scan['sample'],
            'findings' => $findings,
        ];
    }

    /**
     * Build a normalized edge row.
     *
     * @param string $mappingtype
     * @param \stdClass $record
     * @param string $sourcetype
     * @param int $sourceid
     * @param string $targettype
     * @param int $targetid
     * @return array
     */
    private static function edge_from_record(string $mappingtype, \stdClass $record, string $sourcetype,
            int $sourceid, string $targettype, int $targetid): array {
        $row = (array)$record;
        $relation = self::semantic_for_mapping($mappingtype, $row);
        return [
            'mappingtype' => $mappingtype,
            'table' => self::MAPPING_TABLES[$mappingtype],
            'mappingid' => (int)$record->id,
            'relation' => $relation,
            'source_type' => $sourcetype,
            'source_id' => $sourceid,
            'source' => self::node_key($sourcetype, $sourceid),
            'target_type' => $targettype,
            'target_id' => $targetid,
            'target' => self::node_key($targettype, $targetid),
            'hard_prerequisite' => self::is_hard_prerequisite_row($mappingtype, $row),
            'row' => $row,
        ];
    }

    /**
     * Scan current DB rows for graph contract findings.
     *
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function scan_existing_graph(int $frameworkid, int $limit): array {
        $edges = self::adjacency($frameworkid, ['limit' => $limit]);
        $findings = [];
        $relationcounts = array_fill_keys(array_keys(self::RELATIONS), 0);
        $hardedges = [];
        $replacededges = [];

        foreach ($edges as $edge) {
            if (isset($relationcounts[$edge['relation']])) {
                $relationcounts[$edge['relation']]++;
            }
            $result = self::validate_mapping_row($edge['mappingtype'], $edge['row']);
            foreach ($result['errors'] as $error) {
                $findings[] = [
                    'severity' => 'error',
                    'code' => 'invalid_graph_edge',
                    'message' => $edge['table'] . '#' . $edge['mappingid'] . ': ' . $error,
                ];
            }
            if (!empty($edge['hard_prerequisite'])) {
                $hardedges[] = [
                    'from' => $edge['source'],
                    'to' => $edge['target'],
                    'label' => 'REQUIRES',
                ];
            }
            if (($edge['relation'] ?? '') === 'REPLACED_BY') {
                $replacededges[] = [
                    'from' => $edge['source'],
                    'to' => $edge['target'],
                    'label' => 'REPLACED_BY',
                ];
            }
        }

        foreach (self::detect_hard_prerequisite_cycles($hardedges) as $cycle) {
            $findings[] = [
                'severity' => 'error',
                'code' => 'hard_prerequisite_cycle',
                'message' => 'Hard prerequisite cycle detected: ' . implode(' -> ', $cycle),
            ];
        }
        foreach (self::detect_cycles($replacededges) as $cycle) {
            $findings[] = [
                'severity' => 'error',
                'code' => 'replaced_by_cycle',
                'message' => 'REPLACED_BY cycle detected: ' . implode(' -> ', $cycle),
            ];
        }

        return [
            'sample' => [
                'edgecount' => count($edges),
                'relationcounts' => $relationcounts,
                'limit_per_table' => $limit,
            ],
            'findings' => $findings,
        ];
    }

    /**
     * Existing KP edges relevant to a proposed cycle-sensitive change.
     *
     * @param string $semantic
     * @return array
     */
    private static function existing_kp_edges_for_cycle_scan(string $semantic): array {
        global $DB;

        $edges = [];
        $records = $DB->get_records('flwcupkp_kp_prereq');
        foreach ($records as $record) {
            $row = (array)$record;
            if ($semantic === 'REPLACED_BY' && self::semantic_for_mapping('kp_prereq', $row) !== 'REPLACED_BY') {
                continue;
            }
            if ($semantic !== 'REPLACED_BY' && !self::is_hard_prerequisite_row('kp_prereq', $row)) {
                continue;
            }
            $edges[] = [
                'from' => 'kp:' . (int)$record->kpid,
                'to' => 'kp:' . (int)$record->prereqkpid,
                'label' => $semantic,
            ];
        }
        return $edges;
    }

    /**
     * Detect graph cycles in a simple edge list.
     *
     * @param array $edges
     * @return array
     */
    private static function detect_cycles(array $edges): array {
        $graph = [];
        foreach ($edges as $edge) {
            $from = (string)($edge['from'] ?? '');
            $to = (string)($edge['to'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }
            $graph[$from][] = $to;
            if (!isset($graph[$to])) {
                $graph[$to] = [];
            }
        }

        $visited = [];
        $stack = [];
        $cycles = [];
        $cyclekeys = [];

        foreach (array_keys($graph) as $node) {
            self::cycle_walk($node, $graph, $visited, $stack, $cycles, $cyclekeys);
        }

        return $cycles;
    }

    /**
     * DFS helper for cycle detection.
     *
     * @param string $node
     * @param array $graph
     * @param array $visited
     * @param array $stack
     * @param array $cycles
     * @param array $cyclekeys
     */
    private static function cycle_walk(string $node, array $graph, array &$visited, array &$stack,
            array &$cycles, array &$cyclekeys): void {
        if (isset($visited[$node])) {
            return;
        }
        $stack[$node] = count($stack);
        foreach ($graph[$node] ?? [] as $next) {
            if (isset($stack[$next])) {
                $nodes = array_keys($stack);
                $cycle = array_slice($nodes, (int)$stack[$next]);
                $cycle[] = $next;
                $key = implode('|', $cycle);
                if (!isset($cyclekeys[$key])) {
                    $cycles[] = $cycle;
                    $cyclekeys[$key] = true;
                }
                continue;
            }
            self::cycle_walk($next, $graph, $visited, $stack, $cycles, $cyclekeys);
        }
        unset($stack[$node]);
        $visited[$node] = true;
    }

    /**
     * Traverse graph edges from or into a start node.
     *
     * @param string $start
     * @param array $edges
     * @param bool $reverse
     * @return array
     */
    private static function walk_graph(string $start, array $edges, bool $reverse): array {
        $index = [];
        foreach ($edges as $edge) {
            $from = $reverse ? $edge['target'] : $edge['source'];
            $index[$from][] = $edge;
        }

        $seen = [$start => true];
        $queue = [$start];
        $matchededges = [];

        while ($queue) {
            $node = array_shift($queue);
            foreach ($index[$node] ?? [] as $edge) {
                $next = $reverse ? $edge['source'] : $edge['target'];
                $matchededges[] = self::edge_without_raw_row($edge);
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        unset($seen[$start]);
        return [
            'start' => $start,
            'nodes' => array_keys($seen),
            'edges' => $matchededges,
        ];
    }

    /**
     * Remove raw DB row payload from an edge returned by traversal APIs.
     *
     * @param array $edge
     * @return array
     */
    private static function edge_without_raw_row(array $edge): array {
        unset($edge['row']);
        return $edge;
    }

    /**
     * Return endpoint types/IDs for a mapping row.
     *
     * @param string $mappingtype
     * @param array $row
     * @return array
     */
    private static function endpoint_for_mapping(string $mappingtype, array $row): array {
        $mappingtype = self::normalize_mapping_type($mappingtype);
        if ($mappingtype === 'comp_up') {
            return [
                'source_type' => 'competency',
                'source_id' => $row['competencyid'] ?? ($row['competency_externalid'] ?? null),
                'target_type' => 'up',
                'target_id' => $row['upid'] ?? ($row['up_externalid'] ?? null),
            ];
        }
        if ($mappingtype === 'up_kp') {
            return [
                'source_type' => 'up',
                'source_id' => $row['upid'] ?? ($row['up_externalid'] ?? null),
                'target_type' => 'kp',
                'target_id' => $row['kpid'] ?? ($row['kp_externalid'] ?? null),
            ];
        }
        if ($mappingtype === 'kp_prereq') {
            return [
                'source_type' => 'kp',
                'source_id' => $row['kpid'] ?? ($row['kp_externalid'] ?? null),
                'target_type' => 'kp',
                'target_id' => $row['prereqkpid'] ?? ($row['prereq_kp_externalid'] ?? null),
            ];
        }
        if ($mappingtype === 'object_map') {
            $targettype = self::normalize_entity_type((string)($row['targettype'] ?? ($row['target_type'] ?? '')));
            return [
                'source_type' => 'object',
                'source_id' => $row['objectid'] ?? ($row['object_externalid'] ?? null),
                'target_type' => $targettype,
                'target_id' => $row['targetid'] ?? ($row['target_externalid'] ?? null),
            ];
        }
        throw new \invalid_parameter_exception('Unknown C-UP-KP mapping type.');
    }

    /**
     * Whether a mapping row is a hard KP prerequisite edge.
     *
     * @param string $mappingtype
     * @param array $row
     * @return bool
     */
    private static function is_hard_prerequisite_row(string $mappingtype, array $row): bool {
        if (self::normalize_mapping_type($mappingtype) !== 'kp_prereq') {
            return false;
        }
        return self::semantic_for_mapping('kp_prereq', $row) === 'REQUIRES' &&
            self::normalize_label((string)($row['requirement'] ?? 'recommended')) === 'mandatory';
    }

    /**
     * Package object-map rows including alias package shapes.
     *
     * @param array $package
     * @return array
     */
    private static function package_object_map_rows(array $package): array {
        $rows = [];
        foreach (($package['activity_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows['activity_mappings[' . $index . ']'] = $row;
        }
        foreach (($package['lesson_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? ($row['externalid'] ?? ''));
            $role = $row['map_role'] ?? ($row['role'] ?? null);
            $strength = $row['map_evidence_strength'] ?? ($row['evidence_strength'] ?? null);
            if (!empty($row['target_type']) && !empty($row['target_externalid'])) {
                $rows['lesson_mappings[' . $index . ']'] = [
                    'object_externalid' => $objectexternalid,
                    'target_type' => (string)$row['target_type'],
                    'target_externalid' => (string)$row['target_externalid'],
                    'role' => $role,
                    'evidence_strength' => $strength,
                ];
            }
            foreach (['kp' => 'kp_externalid', 'up' => 'up_externalid', 'competency' => 'competency_externalid'] as $type => $field) {
                foreach (self::list_values($row[$field . 's'] ?? ($row[$field] ?? null)) as $targetexternalid) {
                    $rows['lesson_mappings[' . $index . ']:' . $type . ':' . $targetexternalid] = [
                        'object_externalid' => $objectexternalid,
                        'target_type' => $type,
                        'target_externalid' => $targetexternalid,
                        'role' => $role,
                        'evidence_strength' => $strength,
                    ];
                }
            }
        }
        foreach (($package['project_competency_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? ($row['externalid'] ?? ''));
            foreach (self::list_values($row['competency_externalids'] ?? ($row['competency_externalid'] ?? null)) as $targetexternalid) {
                $rows['project_competency_mappings[' . $index . ']:' . $targetexternalid] = [
                    'object_externalid' => $objectexternalid,
                    'target_type' => 'competency',
                    'target_externalid' => $targetexternalid,
                    'role' => $row['role'] ?? 'assessment',
                    'evidence_strength' => $row['evidence_strength'] ?? 'independent_performance',
                ];
            }
        }
        return $rows;
    }

    /**
     * Merge validation result into package validation accumulators.
     *
     * @param string $context
     * @param array $result
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function merge_result(string $context, array $result, array &$errors, array &$warnings,
            array &$details): void {
        foreach ($result['errors'] ?? [] as $error) {
            $errors[] = $context . ': ' . $error;
        }
        foreach ($result['warnings'] ?? [] as $warning) {
            $warnings[] = $context . ': ' . $warning;
        }
        foreach ($result['details'] ?? [] as $detail) {
            $detail['context'] = $context;
            $details[] = $detail;
        }
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
            'kp_prerequisites' => 'kp_prereq',
            'activity_mapping' => 'object_map',
            'lesson_mapping' => 'object_map',
            'object_mapping' => 'object_map',
        ];
        return $aliases[$mappingtype] ?? $mappingtype;
    }

    /**
     * Normalize labels to stable lowercase underscore values.
     *
     * @param string $value
     * @return string
     */
    private static function normalize_label(string $value): string {
        return strtolower(trim(str_replace(['-', ' '], '_', $value)));
    }

    /**
     * Convert supported row types to array.
     *
     * @param mixed $row
     * @return array
     */
    private static function row_to_array($row): array {
        if ($row instanceof \stdClass) {
            return (array)$row;
        }
        if (is_array($row)) {
            return $row;
        }
        return [];
    }

    /**
     * Node key for traversal.
     *
     * @param string $type
     * @param int|string|null $id
     * @return string
     */
    private static function node_key(string $type, $id): string {
        return $type . ':' . (string)$id;
    }

    /**
     * Normalize a scalar or list value to trimmed values.
     *
     * @param mixed $values
     * @return array
     */
    private static function list_values($values): array {
        if ($values === null || $values === '') {
            return [];
        }
        $out = [];
        foreach ((array)$values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * Build a structured validation detail.
     *
     * @param string $code
     * @param string $severity
     * @param string $mappingtype
     * @param string|null $semantic
     * @return array
     */
    private static function detail(string $code, string $severity, string $mappingtype, ?string $semantic = null): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'mappingtype' => $mappingtype,
            'semantic' => $semantic,
            'contract' => self::CONTRACT_VERSION,
        ];
    }
}

<?php
// Program 3 Gate CM2 relationship editor and where-used impact services.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Adds guarded relationship editing and impact previews over Foundation V1.
 */
final class relationship_where_used_manager {
    /** Program 3 relationship editor gate. */
    public const GATE = 'P3_CM2';

    /** Frozen CM2 service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_RELATIONSHIP_WHERE_USED_V1';

    /** @var array Entity tables included in where-used impact previews. */
    private const ENTITY_TABLES = [
        'framework' => 'flwcupkp_framework',
        'competency' => 'flwcupkp_comp',
        'up' => 'flwcupkp_up',
        'kp' => 'flwcupkp_kp',
        'object' => 'flwcupkp_object',
    ];

    /** @var array Impact count keys shown in CM2 UI. */
    private const COUNT_KEYS = [
        'competencies',
        'use_points',
        'knowledge_points',
        'learning_objects',
        'courses',
        'units',
        'lessons',
        'activities',
        'questions',
        'checkpoints',
        'evidence_count',
        'learner_state_references',
    ];

    /**
     * Return the CM2 relationship/where-used contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpRelationshipWhereUsedContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                foundation_v1_contract::CONTRACT_VERSION,
                core_curriculum_manager::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                content_evidence_mapping_contract::CONTRACT_VERSION,
                lifecycle_governance_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'editor_controls' => [
                'preview_before_save',
                'preview_before_delete',
                'confirm_before_write',
                'c2_semantics_validation',
                'c3_object_mapping_validation',
                'c4_lifecycle_delete_protection',
            ],
            'where_used_shows' => [
                'competencies',
                'knowledge_points',
                'use_points',
                'courses',
                'units',
                'lessons',
                'activities',
                'questions',
                'checkpoints',
                'evidence_counts',
                'learner_state_references',
            ],
            'coverage_governance' => [
                'uses_cached_or_aggregated_counts',
                'flags_object_maps_with_evidence',
                'flags_objects_without_targets',
                'flags_published_targets_without_evidence_routes',
            ],
            'state_changes_allowed' => false,
            'does_not_do' => [
                'adaptive_path_selection',
                'mastery_state_recalculation',
                'history_v1_source_capture',
                'raw_moodle_log_scraping',
            ],
        ];
    }

    /**
     * CM2 readiness status.
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
        $findings = [];

        if (($foundation['status'] ?? '') !== 'frozen') {
            $findings[] = self::finding('BLOCKER', 'foundation_not_frozen',
                'CM2 requires the frozen Foundation V1 status.');
        }
        if (!in_array((string)($foundation['next_allowed_gate'] ?? ''), ['CM2', 'CM3', 'CM4', 'E1', 'E2'], true)) {
            $findings[] = self::finding('BLOCKER', 'foundation_gate_boundary_unexpected',
                'CM2 expects Foundation V1 to hand off to CM2 or later CM governance.');
        }
        if (($cm1['status'] ?? '') !== 'ready') {
            $findings[] = self::finding('BLOCKER', 'cm1_not_ready',
                'CM2 relationship editing requires the CM1 curriculum manager surface.');
        }

        $requiredfiles = [
            'mappings.php',
            'entity.php',
            'curriculum.php',
            'foundation.php',
        ];
        $files = [];
        foreach ($requiredfiles as $file) {
            $present = file_exists($CFG->dirroot . '/local/flwcupkp/' . $file);
            $files[$file] = $present;
            if (!$present) {
                $findings[] = self::finding('BLOCKER', 'missing_cm2_file', 'Missing CM2 file: ' . $file);
            }
        }

        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['BLOCKER', 'HIGH'], true);
        });

        return [
            'type' => 'CupkpRelationshipWhereUsedStatus',
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
            'files' => $files,
            'findings' => $findings,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Summarize relationship coverage governance using bounded aggregate checks.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function coverage_governance_status(int $frameworkid = 0, int $courseid = 0,
            string $unitcode = '', int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        $foundation = foundation_v1_contract::foundation_status($courseid, $unitcode, $frameworkid, $limit);
        $graph = relationship_graph_contract::graph_status($courseid, $frameworkid, $limit);
        $content = content_evidence_mapping_contract::content_mapping_status($courseid, $unitcode, $limit);
        $counts = [
            'objects' => self::count_scoped_objects($frameworkid, $courseid, $unitcode),
            'object_maps' => self::count_scoped_object_maps($frameworkid, $courseid, $unitcode),
            'protected_object_maps_with_evidence' => self::count_protected_object_maps($frameworkid, $courseid, $unitcode),
            'objects_without_targets' => self::count_objects_without_targets($frameworkid, $courseid, $unitcode),
            'published_targets_without_evidence_routes' =>
                self::count_published_targets_without_evidence_routes($frameworkid, $courseid, $unitcode),
            'evidence_rows' => self::count_scoped_evidence($courseid, $unitcode),
            'learner_state_references' => self::count_scoped_states($frameworkid),
            'hard_prerequisite_edges' => self::count_hard_prerequisite_edges($frameworkid),
            'replacement_edges' => self::count_replacement_edges($frameworkid),
        ];

        $findings = [];
        if (($foundation['status'] ?? '') !== 'frozen') {
            $findings[] = self::finding('BLOCKER', 'foundation_not_frozen',
                'Coverage governance requires Foundation V1 to remain frozen.');
        }
        if (($graph['status'] ?? '') !== 'frozen') {
            $findings[] = self::finding('HIGH', 'relationship_graph_not_frozen',
                'C2 relationship graph status is not frozen.');
        }
        if ($counts['objects_without_targets'] > 0) {
            $findings[] = self::finding('MEDIUM', 'objects_without_targets',
                'Learning objects without any C-UP-KP target mapping: ' . $counts['objects_without_targets']);
        }
        if ($counts['published_targets_without_evidence_routes'] > 0) {
            $findings[] = self::finding('MEDIUM', 'published_targets_without_routes',
                'Published targets without object evidence routes: ' .
                    $counts['published_targets_without_evidence_routes']);
        }

        return [
            'type' => 'CupkpCm2CoverageGovernanceStatus',
            'gate' => self::GATE,
            'status' => self::blocking_findings($findings) ? 'blocked' : 'governed',
            'contract' => self::CONTRACT_VERSION,
            'foundation_status' => $foundation['status'] ?? 'unknown',
            'graph_status' => $graph['status'] ?? 'unknown',
            'content_mapping_status' => $content['status'] ?? 'unknown',
            'counts' => $counts,
            'findings' => $findings,
            'aggregation' => [
                'mode' => 'cached_or_aggregated_counts',
                'limit' => $limit,
                'expensive_counts' => 'summarized_without_expanding_learner_rows',
            ],
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Build where-used impact for one entity.
     *
     * @param string $type
     * @param int $id
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function where_used_impact(string $type, int $id, int $courseid = 0,
            string $unitcode = '', int $limit = 50): array {
        $type = self::normalize_entity_type($type);
        if ($id <= 0) {
            throw new \invalid_parameter_exception('C-UP-KP entity ID is required.');
        }
        $record = self::record_for_entity($type, $id);
        if (!$record) {
            throw new \invalid_parameter_exception('C-UP-KP entity not found.');
        }
        $frameworkid = $type === 'framework' ? (int)$record->id : (int)($record->frameworkid ?? 0);
        $impact = self::impact_for_nodes([['type' => $type, 'id' => $id]], $frameworkid, $courseid, $unitcode, $limit);
        $impact['type'] = 'CupkpCm2WhereUsedImpact';
        $impact['entity'] = [
            'type' => $type,
            'id' => $id,
            'label' => self::node_label($type, $id),
            'frameworkid' => $frameworkid,
        ];
        return $impact;
    }

    /**
     * Preview a mapping change without writing.
     *
     * @param string $type
     * @param array $data
     * @param string $action
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function preview_mapping_change(string $type, array $data, string $action = 'save',
            int $courseid = 0, string $unitcode = '', int $limit = 50): array {
        $type = self::normalize_mapping_type($type);
        $action = strtolower(trim($action));
        $errors = [];
        $warnings = [];
        $details = [];
        $existing = null;
        $proposed = null;
        $semantic = '';
        $endpoint = [];
        $frameworkid = 0;

        if ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'A mapping ID is required before previewing delete.';
            } else {
                $existing = curriculum_manager::get_mapping($type, $id);
                if (!$existing) {
                    $errors[] = 'C-UP-KP mapping row not found.';
                } else {
                    $proposed = (array)$existing;
                    $semantic = relationship_graph_contract::semantic_for_mapping($type, $proposed);
                    $endpoint = self::mapping_endpoint($type, $proposed);
                    $frameworkid = self::frameworkid_from_endpoint($endpoint);
                    self::merge_validation(lifecycle_governance_contract::validate_mapping_delete($type, $existing),
                        $errors, $warnings, $details);
                }
            }
        } else if ($action === 'save') {
            $proposed = self::mapping_payload($type, $data);
            if (!empty($proposed['id'])) {
                $existing = curriculum_manager::get_mapping($type, (int)$proposed['id']);
                if (!$existing) {
                    $errors[] = 'C-UP-KP mapping row not found.';
                } else {
                    foreach (curriculum_manager::mapping_config($type)['fields'] as $field) {
                        if (!array_key_exists($field, $proposed) && property_exists($existing, $field)) {
                            $proposed[$field] = $existing->{$field};
                        }
                    }
                }
            }

            self::validate_required_mapping_fields($type, $proposed, $errors, $details);
            if (!$errors) {
                self::merge_validation(ontology_boundary::validate_mapping_row($type, $proposed),
                    $errors, $warnings, $details);
                self::merge_validation(relationship_graph_contract::validate_mapping_row($type, $proposed),
                    $errors, $warnings, $details);
                self::merge_validation(self::validate_mapping_references($type, $proposed),
                    $errors, $warnings, $details);
                if ($type === 'object_map') {
                    $object = self::record_for_entity('object', (int)($proposed['objectid'] ?? 0));
                    self::merge_validation(content_evidence_mapping_contract::validate_object_map_row(
                        $proposed,
                        $object ? (array)$object : []
                    ), $errors, $warnings, $details);
                }
                self::capture_assert(static function() use ($type, $proposed): void {
                    relationship_graph_contract::assert_mapping_change($type, $proposed);
                }, $errors, $details, 'c2_whole_graph_change');
                self::merge_validation(lifecycle_governance_contract::validate_mapping_change($type, $proposed),
                    $errors, $warnings, $details);
                self::merge_validation(self::validate_unique_mapping($type, $proposed, $existing),
                    $errors, $warnings, $details);
                $semantic = relationship_graph_contract::semantic_for_mapping($type, $proposed);
                $endpoint = self::mapping_endpoint($type, $proposed);
                $frameworkid = self::frameworkid_from_endpoint($endpoint);
            }
        } else {
            $errors[] = 'Unknown CM2 relationship action.';
        }

        $impact = !empty($endpoint) ?
            self::mapping_impact($type, (object)$proposed, $courseid, $unitcode, $limit) :
            self::empty_impact($limit);
        $valid = empty($errors);

        return [
            'type' => 'CupkpCm2RelationshipChangePreview',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'mappingtype' => $type,
            'action' => $action,
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'semantic' => $semantic,
            'endpoint' => self::endpoint_labels($endpoint),
            'frameworkid' => $frameworkid,
            'existing' => $existing ? (array)$existing : null,
            'proposed' => $proposed,
            'impact' => $impact,
            'would_write' => false,
            'state_changes_allowed' => false,
            'confirm_required' => $valid,
        ];
    }

    /**
     * Apply a previously previewable mapping change and write an audit entry.
     *
     * @param string $type
     * @param array $data
     * @param string $action
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function apply_mapping_change(string $type, array $data, string $action = 'save',
            int $courseid = 0, string $unitcode = '', int $limit = 50): array {
        $preview = self::preview_mapping_change($type, $data, $action, $courseid, $unitcode, $limit);
        if (!$preview['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $preview['errors']));
        }

        if ($preview['action'] === 'delete') {
            $id = (int)($preview['proposed']['id'] ?? 0);
            curriculum_manager::delete_mapping($preview['mappingtype'], $id);
            $appliedid = $id;
        } else {
            $appliedid = curriculum_manager::save_mapping($preview['mappingtype'], $preview['proposed']);
        }

        repository::audit('cm2_relationship_change_applied', $preview['mappingtype'], $appliedid, [
            'action' => $preview['action'],
            'semantic' => $preview['semantic'],
            'endpoint' => $preview['endpoint'],
            'impact_counts' => $preview['impact']['counts'] ?? [],
            'contract' => self::CONTRACT_VERSION,
            'state_changes_allowed' => false,
        ]);

        $preview['applied'] = true;
        $preview['appliedid'] = $appliedid;
        return $preview;
    }

    /**
     * Impact summary for one mapping row.
     *
     * @param string $type
     * @param \stdClass $record
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function mapping_impact(string $type, \stdClass $record, int $courseid = 0,
            string $unitcode = '', int $limit = 50): array {
        $type = self::normalize_mapping_type($type);
        $endpoint = self::mapping_endpoint($type, (array)$record);
        $frameworkid = self::frameworkid_from_endpoint($endpoint);
        $nodes = [
            ['type' => $endpoint['source_type'], 'id' => (int)$endpoint['source_id']],
            ['type' => $endpoint['target_type'], 'id' => (int)$endpoint['target_id']],
        ];
        $impact = self::impact_for_nodes($nodes, $frameworkid, $courseid, $unitcode, $limit);
        $impact['mapping'] = [
            'type' => $type,
            'id' => (int)($record->id ?? 0),
            'semantic' => relationship_graph_contract::semantic_for_mapping($type, (array)$record),
            'endpoint' => self::endpoint_labels($endpoint),
        ];
        return $impact;
    }

    /**
     * Normalize a mapping payload for preview/apply.
     *
     * @param string $type
     * @param array $data
     * @return array
     */
    private static function mapping_payload(string $type, array $data): array {
        $config = curriculum_manager::mapping_config($type);
        $payload = [];
        if (!empty($data['id'])) {
            $payload['id'] = (int)$data['id'];
        }
        foreach ($config['fields'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $payload[$field] = self::normalize_field_value($field, $data[$field]);
            }
        }
        if ($type === 'object_map' && !empty($data['target']) &&
                (empty($payload['targettype']) || empty($payload['targetid']))) {
            [$targettype, $targetid] = array_pad(explode(':', (string)$data['target'], 2), 2, '');
            $payload['targettype'] = self::normalize_entity_type($targettype);
            $payload['targetid'] = (int)$targetid;
        }
        return $payload;
    }

    /**
     * Validate required mapping fields.
     *
     * @param string $type
     * @param array $row
     * @param array $errors
     * @param array $details
     */
    private static function validate_required_mapping_fields(string $type, array $row, array &$errors,
            array &$details): void {
        foreach (curriculum_manager::mapping_config($type)['required'] as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === '' || (string)$row[$field] === '0') {
                $errors[] = $field . ' is required.';
                $details[] = self::detail('required_field_missing', 'ERROR', ['field' => $field]);
            }
        }
    }

    /**
     * Validate DB endpoint existence and same-framework boundaries.
     *
     * @param string $type
     * @param array $row
     * @return array
     */
    private static function validate_mapping_references(string $type, array $row): array {
        $errors = [];
        $warnings = [];
        $details = [];
        try {
            $endpoint = self::mapping_endpoint($type, $row);
            $source = self::record_for_entity($endpoint['source_type'], (int)$endpoint['source_id']);
            $target = self::record_for_entity($endpoint['target_type'], (int)$endpoint['target_id']);
            if (!$source || !$target) {
                $errors[] = 'Referenced C-UP-KP mapping endpoint does not exist.';
                $details[] = self::detail('missing_mapping_endpoint', 'ERROR');
            } else if (!empty($source->frameworkid) && !empty($target->frameworkid) &&
                    (int)$source->frameworkid !== (int)$target->frameworkid) {
                $errors[] = 'C-UP-KP mapping endpoints must belong to the same framework.';
                $details[] = self::detail('cross_framework_mapping', 'ERROR');
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $details[] = self::detail('invalid_mapping_endpoint', 'ERROR');
        }
        return self::validation_result($errors, $warnings, $details);
    }

    /**
     * Validate unique mapping endpoints during preview.
     *
     * @param string $type
     * @param array $row
     * @param \stdClass|null $existing
     * @return array
     */
    private static function validate_unique_mapping(string $type, array $row, ?\stdClass $existing): array {
        global $DB;

        $config = curriculum_manager::mapping_config($type);
        $keys = [
            $config['left'] => $row[$config['left']] ?? 0,
            $config['right'] => $row[$config['right']] ?? 0,
        ];
        if ($type === 'object_map') {
            $keys['targettype'] = $row['targettype'] ?? '';
        }
        $conflict = $DB->get_record($config['table'], $keys, 'id', IGNORE_MISSING);
        if ($conflict && (!$existing || (int)$conflict->id !== (int)$existing->id)) {
            return self::validation_result(
                ['A C-UP-KP mapping with these endpoints already exists.'],
                [],
                [self::detail('duplicate_mapping_endpoint', 'ERROR')]
            );
        }
        return self::validation_result([], [], []);
    }

    /**
     * Build impact for a group of seed nodes.
     *
     * @param array $seednodes
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    private static function impact_for_nodes(array $seednodes, int $frameworkid, int $courseid,
            string $unitcode, int $limit): array {
        $limit = max(1, min(500, $limit));
        $nodes = [];
        $edges = [];

        foreach ($seednodes as $node) {
            self::add_node($nodes, (string)($node['type'] ?? ''), (int)($node['id'] ?? 0));
        }
        if ($frameworkid > 0 && isset($nodes['framework:' . $frameworkid])) {
            self::add_framework_nodes($nodes, $frameworkid, $limit);
        }

        foreach (array_values($nodes) as $node) {
            if (!in_array($node['type'], ['competency', 'up', 'kp'], true)) {
                continue;
            }
            self::merge_graph_walk($nodes, $edges,
                relationship_graph_contract::where_used($node['type'], $node['id'], $frameworkid));
            self::merge_graph_walk($nodes, $edges,
                relationship_graph_contract::dependencies_for_target($node['type'], $node['id'], $frameworkid));
        }
        self::merge_direct_edges($nodes, $edges, $frameworkid, $limit);

        $objectids = self::object_ids_for_nodes($nodes, $limit);
        foreach ($objectids as $objectid) {
            self::add_node($nodes, 'object', (int)$objectid);
        }

        $objects = self::object_records($objectids, $frameworkid, $courseid, $unitcode, $limit);
        $targetnodes = self::target_nodes($nodes);
        $counts = self::impact_counts($nodes, $objects, $targetnodes, $courseid, $unitcode);

        return [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'counts' => $counts,
            'nodes' => self::node_summaries($nodes, $limit),
            'edges' => array_slice(array_values($edges), 0, $limit),
            'objects' => self::object_summaries($objects, $limit),
            'warnings' => self::impact_warnings($counts),
            'aggregation' => [
                'mode' => 'cached_or_aggregated_counts',
                'limit' => $limit,
                'expensive_counts' => 'evidence_and_state_counts_are_summarized',
            ],
            'would_write' => false,
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Empty impact payload for invalid previews.
     *
     * @param int $limit
     * @return array
     */
    private static function empty_impact(int $limit): array {
        return [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'counts' => array_fill_keys(self::COUNT_KEYS, 0),
            'nodes' => [],
            'edges' => [],
            'objects' => [],
            'warnings' => [],
            'aggregation' => ['mode' => 'cached_or_aggregated_counts', 'limit' => $limit],
            'would_write' => false,
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Merge graph walk rows into node/edge collections.
     *
     * @param array $nodes
     * @param array $edges
     * @param array $walk
     */
    private static function merge_graph_walk(array &$nodes, array &$edges, array $walk): void {
        foreach ($walk['nodes'] ?? [] as $nodekey) {
            [$type, $id] = self::split_node_key((string)$nodekey);
            self::add_node($nodes, $type, $id);
        }
        foreach ($walk['edges'] ?? [] as $edge) {
            self::add_edge($nodes, $edges, $edge);
        }
    }

    /**
     * Merge direct edges touching already-known nodes.
     *
     * @param array $nodes
     * @param array $edges
     * @param int $frameworkid
     * @param int $limit
     */
    private static function merge_direct_edges(array &$nodes, array &$edges, int $frameworkid, int $limit): void {
        $known = array_fill_keys(array_keys($nodes), true);
        foreach (relationship_graph_contract::adjacency($frameworkid, ['limit' => $limit]) as $edge) {
            if (empty($known[$edge['source'] ?? '']) && empty($known[$edge['target'] ?? ''])) {
                continue;
            }
            self::add_edge($nodes, $edges, $edge);
        }
    }

    /**
     * Add node by normalized type/id.
     *
     * @param array $nodes
     * @param string $type
     * @param int $id
     */
    private static function add_node(array &$nodes, string $type, int $id): void {
        $type = self::normalize_entity_type($type);
        if ($id <= 0 || !isset(self::ENTITY_TABLES[$type])) {
            return;
        }
        $nodes[$type . ':' . $id] = ['type' => $type, 'id' => $id];
    }

    /**
     * Add edge and both endpoints.
     *
     * @param array $nodes
     * @param array $edges
     * @param array $edge
     */
    private static function add_edge(array &$nodes, array &$edges, array $edge): void {
        self::add_node($nodes, (string)($edge['source_type'] ?? ''), (int)($edge['source_id'] ?? 0));
        self::add_node($nodes, (string)($edge['target_type'] ?? ''), (int)($edge['target_id'] ?? 0));
        $key = (string)($edge['mappingtype'] ?? '') . ':' . (int)($edge['mappingid'] ?? 0) . ':' .
            (string)($edge['source'] ?? '') . ':' . (string)($edge['target'] ?? '');
        unset($edge['row']);
        $edges[$key] = $edge;
    }

    /**
     * Add bounded framework nodes for whole-framework previews.
     *
     * @param array $nodes
     * @param int $frameworkid
     * @param int $limit
     */
    private static function add_framework_nodes(array &$nodes, int $frameworkid, int $limit): void {
        global $DB;

        foreach ([
            'competency' => 'flwcupkp_comp',
            'up' => 'flwcupkp_up',
            'kp' => 'flwcupkp_kp',
            'object' => 'flwcupkp_object',
        ] as $type => $table) {
            $records = $DB->get_records($table, ['frameworkid' => $frameworkid], 'id ASC', 'id', 0, $limit);
            foreach ($records as $record) {
                self::add_node($nodes, $type, (int)$record->id);
            }
        }
    }

    /**
     * Return learning object IDs referenced by impacted graph nodes.
     *
     * @param array $nodes
     * @param int $limit
     * @return array
     */
    private static function object_ids_for_nodes(array $nodes, int $limit): array {
        global $DB;

        $ids = [];
        foreach ($nodes as $node) {
            if ($node['type'] === 'object') {
                $ids[(int)$node['id']] = (int)$node['id'];
                continue;
            }
            if (!in_array($node['type'], ['competency', 'up', 'kp'], true)) {
                continue;
            }
            $records = $DB->get_records('flwcupkp_object_map', [
                'targettype' => $node['type'],
                'targetid' => (int)$node['id'],
            ], 'objectid ASC', 'id, objectid', 0, $limit);
            foreach ($records as $record) {
                $ids[(int)$record->objectid] = (int)$record->objectid;
            }
        }
        return array_values($ids);
    }

    /**
     * Return scoped object records.
     *
     * @param array $objectids
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    private static function object_records(array $objectids, int $frameworkid, int $courseid,
            string $unitcode, int $limit): array {
        global $DB;

        if (!$objectids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($objectids, SQL_PARAMS_NAMED, 'obj');
        $where = 'id ' . $insql;
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        if ($courseid > 0) {
            $where .= ' AND (courseid = :courseid OR courseid IS NULL)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        return $DB->get_records_select('flwcupkp_object', $where, $params,
            'unitcode ASC, lesson ASC, externalid ASC', '*', 0, $limit);
    }

    /**
     * Return target nodes from an impact node set.
     *
     * @param array $nodes
     * @return array
     */
    private static function target_nodes(array $nodes): array {
        return array_values(array_filter($nodes, static function(array $node): bool {
            return in_array($node['type'], ['competency', 'up', 'kp'], true);
        }));
    }

    /**
     * Build impact counts.
     *
     * @param array $nodes
     * @param array $objects
     * @param array $targetnodes
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function impact_counts(array $nodes, array $objects, array $targetnodes,
            int $courseid, string $unitcode): array {
        $counts = array_fill_keys(self::COUNT_KEYS, 0);
        foreach ($nodes as $node) {
            if ($node['type'] === 'competency') {
                $counts['competencies']++;
            } else if ($node['type'] === 'up') {
                $counts['use_points']++;
            } else if ($node['type'] === 'kp') {
                $counts['knowledge_points']++;
            }
        }

        $courses = [];
        $units = [];
        $lessons = [];
        $activities = [];
        $questions = [];
        $checkpoints = 0;
        foreach ($objects as $object) {
            if (!empty($object->courseid)) {
                $courses[(int)$object->courseid] = true;
            }
            if ((string)($object->unitcode ?? '') !== '') {
                $units[(string)$object->unitcode] = true;
            }
            if ((string)($object->lesson ?? '') !== '') {
                $lessons[(string)($object->unitcode ?? '') . ':' . (string)$object->lesson] = true;
            }
            if (!empty($object->cmid)) {
                $activities[(int)$object->cmid] = true;
            }
            $identity = content_evidence_mapping_contract::identity_from_object($object);
            if (!empty($identity['questionid'])) {
                $questions[(string)$identity['questionid']] = true;
            }
            if (self::is_checkpoint_object($object)) {
                $checkpoints++;
            }
        }

        $counts['learning_objects'] = count($objects);
        $counts['courses'] = count($courses);
        $counts['units'] = count($units);
        $counts['lessons'] = count($lessons);
        $counts['activities'] = count($activities);
        $counts['questions'] = count($questions);
        $counts['checkpoints'] = $checkpoints + self::count_eval_checkpoints($courseid, $unitcode);
        $counts['evidence_count'] = self::count_evidence_for_nodes($targetnodes, array_keys($objects), $courseid, $unitcode);
        $counts['learner_state_references'] = self::count_states_for_nodes($targetnodes);

        return $counts;
    }

    /**
     * Summarize node labels.
     *
     * @param array $nodes
     * @param int $limit
     * @return array
     */
    private static function node_summaries(array $nodes, int $limit): array {
        $summaries = [];
        foreach (array_values($nodes) as $node) {
            $summaries[] = [
                'type' => $node['type'],
                'id' => (int)$node['id'],
                'label' => self::node_label($node['type'], (int)$node['id']),
            ];
        }
        return array_slice($summaries, 0, $limit);
    }

    /**
     * Summarize object rows.
     *
     * @param array $objects
     * @param int $limit
     * @return array
     */
    private static function object_summaries(array $objects, int $limit): array {
        $summaries = [];
        foreach ($objects as $object) {
            $identity = content_evidence_mapping_contract::identity_from_object($object);
            $summaries[] = [
                'id' => (int)$object->id,
                'externalid' => (string)$object->externalid,
                'title' => (string)$object->title,
                'courseid' => (int)($object->courseid ?? 0),
                'unitcode' => (string)($object->unitcode ?? ''),
                'lesson' => (string)($object->lesson ?? ''),
                'cmid' => (int)($object->cmid ?? 0),
                'objecttype' => (string)($object->objecttype ?? ''),
                'questionid' => (string)($identity['questionid'] ?? ''),
            ];
        }
        return array_slice($summaries, 0, $limit);
    }

    /**
     * Impact warnings from counts.
     *
     * @param array $counts
     * @return array
     */
    private static function impact_warnings(array $counts): array {
        $warnings = [];
        if (($counts['evidence_count'] ?? 0) > 0) {
            $warnings[] = 'This relationship touches existing learner evidence.';
        }
        if (($counts['learner_state_references'] ?? 0) > 0) {
            $warnings[] = 'This relationship touches existing learner mastery state references.';
        }
        return $warnings;
    }

    /**
     * Is this learning object checkpoint-like?
     *
     * @param \stdClass $object
     * @return bool
     */
    private static function is_checkpoint_object(\stdClass $object): bool {
        $label = strtolower(implode(' ', [
            (string)($object->objecttype ?? ''),
            (string)($object->purpose ?? ''),
            (string)($object->role ?? ''),
        ]));
        foreach (['checkpoint', 'assessment', 'exam', 'placement'] as $needle) {
            if (strpos($label, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count evaluation periods acting as checkpoints in the same scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_eval_checkpoints(int $courseid, string $unitcode): int {
        global $DB;

        $where = "LOWER(periodtype) LIKE :checkpoint AND status <> :archived";
        $params = ['checkpoint' => '%checkpoint%', 'archived' => 'archived'];
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        return (int)$DB->count_records_select('flwcupkp_eval_period', $where, $params);
    }

    /**
     * Count distinct evidence records touching nodes or objects.
     *
     * @param array $targetnodes
     * @param array $objectids
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_evidence_for_nodes(array $targetnodes, array $objectids,
            int $courseid, string $unitcode): int {
        global $DB;

        $ids = [];
        foreach ($targetnodes as $node) {
            $params = ['targettype' => $node['type'], 'targetid' => (int)$node['id']];
            $where = 'targettype = :targettype AND targetid = :targetid';
            self::append_evidence_scope($where, $params, $courseid, $unitcode);
            foreach ($DB->get_records_select('flwcupkp_evidence', $where, $params, '', 'id', 0, 10000) as $record) {
                $ids[(int)$record->id] = true;
            }
        }
        foreach ($objectids as $objectid) {
            $params = ['objectid' => (int)$objectid];
            $where = 'objectid = :objectid';
            self::append_evidence_scope($where, $params, $courseid, $unitcode);
            foreach ($DB->get_records_select('flwcupkp_evidence', $where, $params, '', 'id', 0, 10000) as $record) {
                $ids[(int)$record->id] = true;
            }
        }
        return count($ids);
    }

    /**
     * Count state rows for target nodes.
     *
     * @param array $targetnodes
     * @return int
     */
    private static function count_states_for_nodes(array $targetnodes): int {
        global $DB;

        $count = 0;
        foreach ($targetnodes as $node) {
            $count += (int)$DB->count_records('flwcupkp_state', [
                'targettype' => $node['type'],
                'targetid' => (int)$node['id'],
            ]);
        }
        return $count;
    }

    /**
     * Append course/unit evidence scope.
     *
     * @param string $where
     * @param array $params
     * @param int $courseid
     * @param string $unitcode
     */
    private static function append_evidence_scope(string &$where, array &$params, int $courseid, string $unitcode): void {
        if ($courseid > 0) {
            $where .= ' AND (courseid = :courseid OR courseid IS NULL)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
    }

    /**
     * Return endpoint information for a mapping row.
     *
     * @param string $type
     * @param array $row
     * @return array
     */
    private static function mapping_endpoint(string $type, array $row): array {
        if ($type === 'comp_up') {
            return [
                'source_type' => 'competency',
                'source_id' => (int)($row['competencyid'] ?? 0),
                'target_type' => 'up',
                'target_id' => (int)($row['upid'] ?? 0),
            ];
        }
        if ($type === 'up_kp') {
            return [
                'source_type' => 'up',
                'source_id' => (int)($row['upid'] ?? 0),
                'target_type' => 'kp',
                'target_id' => (int)($row['kpid'] ?? 0),
            ];
        }
        if ($type === 'kp_prereq') {
            return [
                'source_type' => 'kp',
                'source_id' => (int)($row['kpid'] ?? 0),
                'target_type' => 'kp',
                'target_id' => (int)($row['prereqkpid'] ?? 0),
            ];
        }
        if ($type === 'object_map') {
            return [
                'source_type' => 'object',
                'source_id' => (int)($row['objectid'] ?? 0),
                'target_type' => self::normalize_entity_type((string)($row['targettype'] ?? '')),
                'target_id' => (int)($row['targetid'] ?? 0),
            ];
        }
        throw new \invalid_parameter_exception('Unknown C-UP-KP mapping type.');
    }

    /**
     * Resolve endpoint labels.
     *
     * @param array $endpoint
     * @return array
     */
    private static function endpoint_labels(array $endpoint): array {
        if (!$endpoint) {
            return [];
        }
        return array_merge($endpoint, [
            'source_label' => self::node_label((string)$endpoint['source_type'], (int)$endpoint['source_id']),
            'target_label' => self::node_label((string)$endpoint['target_type'], (int)$endpoint['target_id']),
        ]);
    }

    /**
     * Determine framework ID from endpoint rows.
     *
     * @param array $endpoint
     * @return int
     */
    private static function frameworkid_from_endpoint(array $endpoint): int {
        $source = self::record_for_entity((string)($endpoint['source_type'] ?? ''), (int)($endpoint['source_id'] ?? 0));
        if ($source && isset($source->frameworkid)) {
            return (int)$source->frameworkid;
        }
        $target = self::record_for_entity((string)($endpoint['target_type'] ?? ''), (int)($endpoint['target_id'] ?? 0));
        if ($target && isset($target->frameworkid)) {
            return (int)$target->frameworkid;
        }
        return 0;
    }

    /**
     * Find an entity row.
     *
     * @param string $type
     * @param int $id
     * @return \stdClass|null
     */
    private static function record_for_entity(string $type, int $id): ?\stdClass {
        global $DB;

        $type = self::normalize_entity_type($type);
        if ($id <= 0 || !isset(self::ENTITY_TABLES[$type])) {
            return null;
        }
        return $DB->get_record(self::ENTITY_TABLES[$type], ['id' => $id], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Node label for impact UI/audit.
     *
     * @param string $type
     * @param int $id
     * @return string
     */
    private static function node_label(string $type, int $id): string {
        $record = self::record_for_entity($type, $id);
        if (!$record) {
            return $type . ':' . $id;
        }
        $externalid = (string)($record->externalid ?? '');
        $title = (string)($record->title ?? ($record->name ?? ''));
        return trim($type . ' ' . $externalid . ' - ' . $title);
    }

    /**
     * Split a graph node key.
     *
     * @param string $key
     * @return array
     */
    private static function split_node_key(string $key): array {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, 0);
        return [self::normalize_entity_type($type), (int)$id];
    }

    /**
     * Normalize entity aliases.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_entity_type(string $type): string {
        $type = strtolower(trim(str_replace(['-', ' '], '_', $type)));
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
        return $aliases[$type] ?? $type;
    }

    /**
     * Normalize mapping aliases.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_mapping_type(string $type): string {
        $type = strtolower(trim(str_replace(['-', ' '], '_', $type)));
        $aliases = [
            'competency_up' => 'comp_up',
            'competency_up_mapping' => 'comp_up',
            'up_kp_mapping' => 'up_kp',
            'kp_prerequisite' => 'kp_prereq',
            'kp_prerequisites' => 'kp_prereq',
            'object_mapping' => 'object_map',
            'activity_mapping' => 'object_map',
            'lesson_mapping' => 'object_map',
        ];
        $type = $aliases[$type] ?? $type;
        curriculum_manager::mapping_config($type);
        return $type;
    }

    /**
     * Normalize web field values.
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    private static function normalize_field_value(string $field, $value) {
        if (in_array($field, [
            'competencyid',
            'upid',
            'kpid',
            'prereqkpid',
            'objectid',
            'targetid',
            'sortorder',
        ], true)) {
            return (int)$value;
        }
        if (in_array($field, ['weight', 'minmastery', 'minreadiness', 'strength'], true) && $value !== '') {
            return (float)$value;
        }
        if ($field === 'targettype') {
            return self::normalize_entity_type((string)$value);
        }
        return trim((string)$value);
    }

    /**
     * Merge a validator result into preview accumulators.
     *
     * @param array $result
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function merge_validation(array $result, array &$errors, array &$warnings, array &$details): void {
        foreach ($result['errors'] ?? [] as $error) {
            $errors[] = $error;
        }
        foreach ($result['warnings'] ?? [] as $warning) {
            $warnings[] = $warning;
        }
        foreach ($result['details'] ?? [] as $detail) {
            $details[] = is_array($detail) ? $detail : ['message' => (string)$detail];
        }
    }

    /**
     * Capture assert-style validation in preview mode.
     *
     * @param callable $callback
     * @param array $errors
     * @param array $details
     * @param string $code
     */
    private static function capture_assert(callable $callback, array &$errors, array &$details, string $code): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $details[] = self::detail($code, 'ERROR');
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
    private static function validation_result(array $errors, array $warnings, array $details): array {
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'contract' => self::CONTRACT_VERSION,
        ];
    }

    /**
     * Build a validation detail.
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
            'contract' => self::CONTRACT_VERSION,
        ], $extra);
    }

    /**
     * Status finding.
     *
     * @param string $severity
     * @param string $code
     * @param string $message
     * @return array
     */
    private static function finding(string $severity, string $code, string $message): array {
        return [
            'severity' => strtoupper($severity),
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * Whether findings contain a blocking severity.
     *
     * @param array $findings
     * @return bool
     */
    private static function blocking_findings(array $findings): bool {
        foreach ($findings as $finding) {
            if (in_array($finding['severity'] ?? '', ['BLOCKER', 'HIGH'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count scoped learning objects.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_scoped_objects(int $frameworkid, int $courseid, string $unitcode): int {
        global $DB;

        [$where, $params] = self::object_scope('', $frameworkid, $courseid, $unitcode);
        return (int)$DB->count_records_select('flwcupkp_object', $where, $params);
    }

    /**
     * Count scoped object mappings.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_scoped_object_maps(int $frameworkid, int $courseid, string $unitcode): int {
        global $DB;

        [$where, $params] = self::object_scope('o.', $frameworkid, $courseid, $unitcode);
        return (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE {$where}",
            $params
        );
    }

    /**
     * Count object maps protected by existing evidence.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_protected_object_maps(int $frameworkid, int $courseid, string $unitcode): int {
        global $DB;

        [$where, $params] = self::object_scope('o.', $frameworkid, $courseid, $unitcode);
        return (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT m.id)
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
               JOIN {flwcupkp_evidence} e ON e.objectid = m.objectid
                AND e.targettype = m.targettype
                AND e.targetid = m.targetid
              WHERE {$where}",
            $params
        );
    }

    /**
     * Count learning objects with no target mapping.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_objects_without_targets(int $frameworkid, int $courseid, string $unitcode): int {
        global $DB;

        [$where, $params] = self::object_scope('o.', $frameworkid, $courseid, $unitcode);
        return (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_object} o
              WHERE {$where}
                AND NOT EXISTS (SELECT 1 FROM {flwcupkp_object_map} m WHERE m.objectid = o.id)",
            $params
        );
    }

    /**
     * Count published target rows without object evidence routes.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_published_targets_without_evidence_routes(int $frameworkid,
            int $courseid, string $unitcode): int {
        global $DB;

        $total = 0;
        foreach ([
            'competency' => 'flwcupkp_comp',
            'up' => 'flwcupkp_up',
            'kp' => 'flwcupkp_kp',
        ] as $type => $table) {
            $where = "t.status IN ('published', 'active', 'reference')";
            $params = ['targettype' => $type];
            if ($frameworkid > 0) {
                $where .= ' AND t.frameworkid = :frameworkid';
                $params['frameworkid'] = $frameworkid;
            }
            $objectscope = '';
            if ($courseid > 0) {
                $objectscope .= ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
                $params['courseid'] = $courseid;
            }
            if ($unitcode !== '') {
                $objectscope .= ' AND o.unitcode = :unitcode';
                $params['unitcode'] = $unitcode;
            }
            $total += (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {{$table}} t
                  WHERE {$where}
                    AND NOT EXISTS (
                        SELECT 1
                          FROM {flwcupkp_object_map} m
                          JOIN {flwcupkp_object} o ON o.id = m.objectid
                         WHERE m.targettype = :targettype
                           AND m.targetid = t.id
                           {$objectscope}
                    )",
                $params
            );
        }
        return $total;
    }

    /**
     * Count scoped evidence rows.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return int
     */
    private static function count_scoped_evidence(int $courseid, string $unitcode): int {
        global $DB;

        $where = '1=1';
        $params = [];
        self::append_evidence_scope($where, $params, $courseid, $unitcode);
        return (int)$DB->count_records_select('flwcupkp_evidence', $where, $params);
    }

    /**
     * Count learner-state rows for an optional framework scope.
     *
     * @param int $frameworkid
     * @return int
     */
    private static function count_scoped_states(int $frameworkid): int {
        global $DB;

        if ($frameworkid <= 0) {
            return (int)$DB->count_records('flwcupkp_state');
        }
        $total = 0;
        foreach ([
            'competency' => 'flwcupkp_comp',
            'up' => 'flwcupkp_up',
            'kp' => 'flwcupkp_kp',
        ] as $type => $table) {
            $total += (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {flwcupkp_state} s
                   JOIN {{$table}} t ON t.id = s.targetid
                  WHERE s.targettype = :targettype
                    AND t.frameworkid = :frameworkid",
                ['targettype' => $type, 'frameworkid' => $frameworkid]
            );
        }
        return $total;
    }

    /**
     * Count hard prerequisite edges.
     *
     * @param int $frameworkid
     * @return int
     */
    private static function count_hard_prerequisite_edges(int $frameworkid): int {
        global $DB;

        $where = "LOWER(m.requirement) = 'mandatory'
                  AND LOWER(m.relationshiptype) IN ('prerequisite', 'requires')";
        $params = [];
        if ($frameworkid > 0) {
            $where .= ' AND kp.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_kp_prereq} m
               JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
              WHERE {$where}",
            $params
        );
    }

    /**
     * Count replacement edges.
     *
     * @param int $frameworkid
     * @return int
     */
    private static function count_replacement_edges(int $frameworkid): int {
        global $DB;

        $where = "LOWER(m.relationshiptype) IN ('replaced_by', 'replaced-by', 'replaced by')";
        $params = [];
        if ($frameworkid > 0) {
            $where .= ' AND kp.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwcupkp_kp_prereq} m
               JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
              WHERE {$where}",
            $params
        );
    }

    /**
     * Build scoped object SQL where fragment.
     *
     * @param string $prefix
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function object_scope(string $prefix, int $frameworkid, int $courseid, string $unitcode): array {
        $where = '1=1';
        $params = [];
        if ($frameworkid > 0) {
            $where .= ' AND ' . $prefix . 'frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        if ($courseid > 0) {
            $where .= ' AND (' . $prefix . 'courseid = :courseid OR ' . $prefix . 'courseid IS NULL)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND ' . $prefix . 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        return [$where, $params];
    }
}

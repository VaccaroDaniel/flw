<?php
// Program 3 Gate CM1 Core C-UP-KP Curriculum Manager.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Adds operational curriculum authoring over the frozen Foundation V1 surface.
 */
final class core_curriculum_manager {
    /** Program 3 core curriculum manager gate. */
    public const GATE = 'P3_CM1';

    /** Frozen CM1 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_CORE_CURRICULUM_MANAGER_V1';

    /** @var array Entity types exposed by the CM1 navigation surface. */
    private const ENTITY_TYPES = ['competency', 'up', 'kp', 'object', 'framework'];

    /** @var array Workflow statuses supported directly by CM1. */
    private const WORKFLOW_STATUSES = ['review', 'approved', 'published', 'deprecated'];

    /**
     * Return the CM1 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpCoreCurriculumManagerContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                foundation_v1_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'foundation_boundary' => [
                'requires_status' => 'frozen',
                'allowed_next_allowed_gate_values' => ['CM1', 'CM2', 'CM3', 'CM4', 'E1', 'E2'],
                'uses' => [
                    'foundation_v1_contract::foundation_status',
                    'foundation_v1_contract::contract',
                    'relationship_graph_contract::adjacency',
                    'relationship_graph_contract::dependencies_for_target',
                    'relationship_graph_contract::where_used',
                    'content_evidence_mapping_contract::content_mapping_status',
                    'lifecycle_governance_contract::validate_entity_write',
                ],
            ],
            'navigation' => [
                'language',
                'cefr_level',
                'flw_stage',
                'domain_or_skill',
                'competency',
                'knowledge_point',
                'use_point',
            ],
            'workflow' => [
                'view',
                'create',
                'edit_prepublication_rows',
                'review',
                'approve',
                'publish',
                'deprecate',
            ],
            'selected_entity_sections' => [
                'definition',
                'stable_code',
                'revision_version',
                'status',
                'relationships',
                'prerequisites',
                'content_usage',
                'evidence_coverage',
                'validation',
                'history',
            ],
            'state_changes_allowed' => false,
            'does_not_do' => [
                'adaptive_path_selection',
                'mastery_policy_change',
                'history_v1_evidence_reprocessing_writes',
                'learning_goal_creation',
                'raw_moodle_log_scraping',
            ],
        ];
    }

    /**
     * CM1 readiness status.
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
        $findings = [];
        if (($foundation['status'] ?? '') !== 'frozen') {
            $findings[] = self::finding('BLOCKER', 'foundation_not_frozen',
                'CM1 requires the frozen Foundation V1 status before operational authoring.');
        }
        if (!in_array((string)($foundation['next_allowed_gate'] ?? ''),
                ['CM1', 'CM2', 'CM3', 'CM4', 'E1', 'E2'], true)) {
            $findings[] = self::finding('BLOCKER', 'foundation_gate_boundary_unexpected',
                'CM1 expects Foundation V1 to hand off to CM1 or later CM governance.');
        }

        $requiredfiles = [
            'curriculum.php',
            'entity.php',
            'edit_entity.php',
            'mappings.php',
            'foundation.php',
        ];
        $files = [];
        foreach ($requiredfiles as $file) {
            $present = file_exists($CFG->dirroot . '/local/flwcupkp/' . $file);
            $files[$file] = $present;
            if (!$present) {
                $findings[] = self::finding('BLOCKER', 'missing_cm1_file', 'Missing CM1 file: ' . $file);
            }
        }

        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['BLOCKER', 'HIGH'], true);
        });

        return [
            'type' => 'CupkpCoreCurriculumManagerStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'foundation' => [
                'status' => $foundation['status'] ?? 'unknown',
                'contract' => $foundation['versions']['foundation_contract_version'] ?? null,
                'unresolved_blocker_high_count' => $foundation['unresolved_blocker_high_count'] ?? null,
                'next_allowed_gate' => $foundation['next_allowed_gate'] ?? null,
            ],
            'files' => $files,
            'findings' => $findings,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Build navigation facets and matching rows for the manager page.
     *
     * @param int $frameworkid
     * @param string $unitcode
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public static function navigation_model(int $frameworkid = 0, string $unitcode = '', array $filters = [],
            int $limit = 100): array {
        $filters = self::normalize_filters($filters);
        $facets = self::facet_options($frameworkid, $unitcode);
        $counts = [];
        foreach (self::ENTITY_TYPES as $type) {
            $counts[$type] = count(self::entity_rows($type, $frameworkid, $unitcode, $filters, 0));
        }
        $selectedtype = in_array($filters['entitytype'], self::ENTITY_TYPES, true) ?
            $filters['entitytype'] : 'competency';

        return [
            'type' => 'CupkpCoreCurriculumNavigationModel',
            'contract' => self::CONTRACT_VERSION,
            'filters' => $filters,
            'facets' => $facets,
            'counts' => $counts,
            'selected_type' => $selectedtype,
            'rows' => self::entity_rows($selectedtype, $frameworkid, $unitcode, $filters, $limit),
            'limit' => $limit,
        ];
    }

    /**
     * Build the selected entity detail surface.
     *
     * @param string $type
     * @param int $id
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function entity_detail(string $type, int $id, int $courseid = 0, string $unitcode = '',
            int $limit = 50): array {
        global $DB;

        $type = self::normalize_entity_type($type);
        $config = curriculum_manager::entity_config($type);
        $record = $DB->get_record($config['table'], ['id' => $id], '*', MUST_EXIST);
        $frameworkid = $type === 'framework' ? (int)$record->id : (int)($record->frameworkid ?? 0);

        $edges = self::direct_edges($type, $id, $frameworkid, $limit);
        $dependencies = in_array($type, ['competency', 'up', 'kp'], true) ?
            relationship_graph_contract::dependencies_for_target($type, $id, $frameworkid) :
            ['start' => $type . ':' . $id, 'nodes' => [], 'edges' => []];
        $whereused = in_array($type, ['competency', 'up', 'kp', 'object'], true) ?
            relationship_graph_contract::where_used($type, $id, $frameworkid) :
            ['start' => $type . ':' . $id, 'nodes' => [], 'edges' => []];

        return [
            'type' => 'CupkpCoreCurriculumEntityDetail',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'entity_type' => $type,
            'table' => $config['table'],
            'record' => $record,
            'frameworkid' => $frameworkid,
            'identity' => [
                'stable_code' => (string)($record->externalid ?? ''),
                'version' => (string)($record->version ?? ''),
                'status' => (string)($record->status ?? ''),
            ],
            'definition' => self::definition_fields($type, $record),
            'relationships' => [
                'direct_edges' => $edges,
                'where_used' => $whereused,
            ],
            'prerequisites' => $dependencies,
            'content_usage' => self::content_usage($type, $id, $courseid, $unitcode, $limit),
            'evidence_coverage' => self::evidence_coverage($type, $id, $courseid, $unitcode),
            'validation' => self::entity_validation($type, $record, $courseid, $unitcode, $frameworkid, $limit),
            'history' => self::audit_history($type, $id, $limit),
            'workflow' => self::workflow_actions_for_record($type, $record),
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Permission matrix for the current user/context.
     *
     * @param \context $context
     * @return array
     */
    public static function permission_matrix(\context $context): array {
        $canmanage = has_capability('local/flwcupkp:manageframeworks', $context);
        $canview = $canmanage || has_capability('local/flwcupkp:viewreports', $context);
        return [
            'view' => $canview,
            'create' => $canmanage,
            'edit_prepublication_rows' => $canmanage,
            'review' => $canmanage,
            'approve' => $canmanage,
            'publish' => $canmanage,
            'deprecate' => $canmanage,
        ];
    }

    /**
     * Workflow actions available for one record under C4 transitions.
     *
     * @param string $type
     * @param \stdClass $record
     * @return array
     */
    public static function workflow_actions_for_record(string $type, \stdClass $record): array {
        $type = self::normalize_entity_type($type);
        if (!in_array($type, ['framework', 'competency', 'up', 'kp'], true)) {
            return [];
        }
        $actions = [];
        foreach (self::WORKFLOW_STATUSES as $status) {
            if ((string)($record->status ?? 'draft') === $status) {
                continue;
            }
            $proposed = (array)$record;
            $proposed['status'] = $status;
            $validation = lifecycle_governance_contract::validate_entity_write($type, $proposed, $record);
            if (!empty($validation['valid'])) {
                $actions[$status] = [
                    'status' => $status,
                    'label' => self::workflow_label($status),
                ];
            }
        }
        return $actions;
    }

    /**
     * Normalize CM1 filters.
     *
     * @param array $filters
     * @return array
     */
    private static function normalize_filters(array $filters): array {
        $entitytype = trim((string)($filters['entitytype'] ?? ''));
        $entitytype = $entitytype === '' ? 'competency' : $entitytype;
        return [
            'language' => trim((string)($filters['language'] ?? '')),
            'cefr' => trim((string)($filters['cefr'] ?? '')),
            'stage' => trim((string)($filters['stage'] ?? '')),
            'domain' => trim((string)($filters['domain'] ?? '')),
            'entitytype' => self::normalize_entity_type($entitytype),
            'q' => trim((string)($filters['q'] ?? '')),
        ];
    }

    /**
     * Facet options for the CM1 tree/navigation controls.
     *
     * @param int $frameworkid
     * @param string $unitcode
     * @return array
     */
    private static function facet_options(int $frameworkid, string $unitcode): array {
        $sets = [
            'language' => [],
            'cefr' => [],
            'stage' => [],
            'domain' => [],
        ];
        foreach (self::ENTITY_TYPES as $type) {
            foreach (self::entity_rows($type, $frameworkid, $unitcode, ['entitytype' => $type], 0) as $record) {
                self::collect_facet_values($type, $record, $sets);
            }
        }

        $facets = [
            'entitytype' => self::entity_type_options(),
        ];
        foreach ($sets as $key => $values) {
            ksort($values, SORT_NATURAL | SORT_FLAG_CASE);
            $facets[$key] = array_combine(array_keys($values), array_keys($values)) ?: [];
        }
        return $facets;
    }

    /**
     * Rows matching the selected CM1 navigation scope.
     *
     * @param string $type
     * @param int $frameworkid
     * @param string $unitcode
     * @param array $filters
     * @param int $limit
     * @return array
     */
    private static function entity_rows(string $type, int $frameworkid, string $unitcode, array $filters,
            int $limit): array {
        $type = self::normalize_entity_type($type);
        $filters = self::normalize_filters($filters);
        $unitids = $unitcode !== '' ? self::unit_target_ids($unitcode) : [];
        $rows = curriculum_manager::list_entities($type, $frameworkid, $filters['q']);
        $frameworks = self::framework_cache();
        $matches = [];
        foreach ($rows as $id => $record) {
            if ($type === 'framework' && $frameworkid > 0 && (int)$record->id !== $frameworkid) {
                continue;
            }
            if ($unitcode !== '' && !self::record_in_unit($type, $record, $unitcode, $unitids)) {
                continue;
            }
            if (!self::record_matches_facets($type, $record, $filters, $frameworks)) {
                continue;
            }
            $matches[$id] = $record;
            if ($limit > 0 && count($matches) >= $limit) {
                break;
            }
        }
        return $matches;
    }

    /**
     * Entity type labels.
     *
     * @return array
     */
    private static function entity_type_options(): array {
        return [
            'competency' => get_string('competencies', 'local_flwcupkp'),
            'up' => get_string('usepoints', 'local_flwcupkp'),
            'kp' => get_string('knowledgepoints', 'local_flwcupkp'),
            'object' => get_string('learningobjects', 'local_flwcupkp'),
            'framework' => get_string('frameworks', 'local_flwcupkp'),
        ];
    }

    /**
     * Collect distinct facet values from one row.
     *
     * @param string $type
     * @param \stdClass $record
     * @param array $sets
     */
    private static function collect_facet_values(string $type, \stdClass $record, array &$sets): void {
        foreach (['language', 'cefr', 'stage', 'domain'] as $key) {
            foreach (self::facet_values_for_record($type, $record, $key, self::framework_cache()) as $value) {
                $sets[$key][$value] = true;
            }
        }
    }

    /**
     * Test a row against selected facets.
     *
     * @param string $type
     * @param \stdClass $record
     * @param array $filters
     * @param array $frameworks
     * @return bool
     */
    private static function record_matches_facets(string $type, \stdClass $record, array $filters,
            array $frameworks): bool {
        foreach (['language', 'cefr', 'stage', 'domain'] as $facet) {
            if ($filters[$facet] === '') {
                continue;
            }
            $values = self::facet_values_for_record($type, $record, $facet, $frameworks);
            if (!in_array($filters[$facet], $values, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Facet values exposed by one record.
     *
     * @param string $type
     * @param \stdClass $record
     * @param string $facet
     * @param array $frameworks
     * @return array
     */
    private static function facet_values_for_record(string $type, \stdClass $record, string $facet,
            array $frameworks): array {
        $values = [];
        $framework = null;
        if ($type !== 'framework' && !empty($record->frameworkid)) {
            $framework = $frameworks[(int)$record->frameworkid] ?? null;
        }

        if ($facet === 'language') {
            $values[] = $record->language ?? ($framework->language ?? null);
        } else if ($facet === 'cefr') {
            $values[] = $record->cefr ?? null;
            $values[] = $record->cefrrange ?? null;
            $values[] = $framework->cefrrange ?? null;
        } else if ($facet === 'stage') {
            $values[] = $record->stage ?? null;
        } else if ($facet === 'domain') {
            $values[] = $record->domain ?? null;
            $values[] = $record->scope ?? null;
            $values[] = $record->languagemode ?? null;
            $values[] = $record->interactiontype ?? null;
            $values[] = $record->objecttype ?? null;
            $values[] = $record->purpose ?? null;
            $values[] = $record->role ?? null;
        }

        $clean = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $clean[] = $value;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * Framework cache keyed by ID.
     *
     * @return array
     */
    private static function framework_cache(): array {
        global $DB;

        return $DB->get_records('flwcupkp_framework', null, '', '*');
    }

    /**
     * Target IDs connected to a unit through learning-object maps.
     *
     * @param string $unitcode
     * @return array
     */
    private static function unit_target_ids(string $unitcode): array {
        global $DB;

        $sets = ['object' => [], 'competency' => [], 'up' => [], 'kp' => []];
        $objects = $DB->get_records('flwcupkp_object', ['unitcode' => $unitcode], '', 'id');
        foreach ($objects as $object) {
            $sets['object'][(int)$object->id] = true;
        }
        if (!$objects) {
            return $sets;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($sets['object']), SQL_PARAMS_NAMED, 'obj');
        $maps = $DB->get_records_select('flwcupkp_object_map', 'objectid ' . $insql, $params);
        foreach ($maps as $map) {
            if (isset($sets[$map->targettype])) {
                $sets[$map->targettype][(int)$map->targetid] = true;
            }
        }
        $upkp = $DB->get_records('flwcupkp_up_kp');
        foreach ($upkp as $map) {
            if (isset($sets['kp'][(int)$map->kpid])) {
                $sets['up'][(int)$map->upid] = true;
            }
        }
        $compup = $DB->get_records('flwcupkp_comp_up');
        foreach ($compup as $map) {
            if (isset($sets['up'][(int)$map->upid])) {
                $sets['competency'][(int)$map->competencyid] = true;
            }
        }
        return $sets;
    }

    /**
     * Whether one record is included in the selected unit.
     *
     * @param string $type
     * @param \stdClass $record
     * @param string $unitcode
     * @param array $unitids
     * @return bool
     */
    private static function record_in_unit(string $type, \stdClass $record, string $unitcode, array $unitids): bool {
        if ($type === 'framework') {
            return true;
        }
        if ($type === 'object') {
            return (string)($record->unitcode ?? '') === $unitcode;
        }
        return isset($unitids[$type][(int)$record->id]);
    }

    /**
     * Definition fields for the detail page.
     *
     * @param string $type
     * @param \stdClass $record
     * @return array
     */
    private static function definition_fields(string $type, \stdClass $record): array {
        $fields = [
            'framework' => ['name', 'description', 'courseid', 'coursecode', 'language', 'cefrrange', 'moodleframeworkid'],
            'competency' => ['title', 'cando', 'description', 'cefr', 'stage', 'domain', 'scope', 'evidencerule', 'moodlecompetencyid'],
            'up' => ['title', 'actionstatement', 'intention', 'context', 'observableaction', 'conditions', 'successcriteria', 'cefr', 'languagemode', 'interactiontype', 'evidencerequirements', 'rubricref'],
            'kp' => ['title', 'description', 'language', 'cefr', 'domain', 'formtext', 'meaningfunction', 'usageconstraints', 'difficulty', 'learningload', 'evidencerequirements'],
            'object' => ['title', 'courseid', 'unitcode', 'lesson', 'objecttype', 'cmid', 'sourceid', 'purpose', 'evidencestrength', 'difficulty', 'role', 'metadatajson'],
        ];
        $out = [];
        foreach ($fields[$type] ?? [] as $field) {
            $value = $record->{$field} ?? null;
            if ($value !== null && trim((string)$value) !== '') {
                $out[$field] = $value;
            }
        }
        return $out;
    }

    /**
     * Direct graph edges touching the selected entity.
     *
     * @param string $type
     * @param int $id
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function direct_edges(string $type, int $id, int $frameworkid, int $limit): array {
        $edges = relationship_graph_contract::adjacency($frameworkid, ['limit' => max(50, $limit * 4)]);
        $matches = [];
        foreach ($edges as $edge) {
            if (($edge['source_type'] === $type && (int)$edge['source_id'] === $id) ||
                    ($edge['target_type'] === $type && (int)$edge['target_id'] === $id)) {
                unset($edge['row']);
                $matches[] = $edge;
            }
            if (count($matches) >= $limit) {
                break;
            }
        }
        return $matches;
    }

    /**
     * Content rows connected to an entity.
     *
     * @param string $type
     * @param int $id
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    private static function content_usage(string $type, int $id, int $courseid, string $unitcode, int $limit): array {
        global $DB;

        $params = [];
        if ($type === 'object') {
            $where = 'o.id = :objectid';
            $params['objectid'] = $id;
        } else if (in_array($type, ['competency', 'up', 'kp'], true)) {
            $where = 'om.targettype = :targettype AND om.targetid = :targetid';
            $params['targettype'] = $type;
            $params['targetid'] = $id;
        } else {
            $where = 'o.frameworkid = :frameworkid';
            $params['frameworkid'] = $id;
        }
        if ($courseid > 0) {
            $where .= ' AND o.courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND o.unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }

        return array_values($DB->get_records_sql(
            "SELECT om.id AS mapid, o.id AS objectid, o.externalid, o.title, o.courseid, o.unitcode,
                    o.lesson, o.objecttype, o.cmid, o.sourceid, om.targettype, om.targetid,
                    om.role AS maprole, om.evidencestrength AS mapevidencestrength
               FROM {flwcupkp_object} o
          LEFT JOIN {flwcupkp_object_map} om ON om.objectid = o.id
              WHERE {$where}
           ORDER BY o.unitcode ASC, o.lesson ASC, o.externalid ASC",
            $params,
            0,
            $limit
        ));
    }

    /**
     * Evidence and learner-state coverage for an entity.
     *
     * @param string $type
     * @param int $id
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function evidence_coverage(string $type, int $id, int $courseid, string $unitcode): array {
        global $DB;

        $where = [];
        $params = [];
        if ($type === 'object') {
            $where[] = 'objectid = :objectid';
            $params['objectid'] = $id;
        } else if (in_array($type, ['competency', 'up', 'kp'], true)) {
            $where[] = 'targettype = :targettype AND targetid = :targetid';
            $params['targettype'] = $type;
            $params['targetid'] = $id;
        } else {
            return [
                'evidence_rows' => 0,
                'learner_state_rows' => 0,
                'latest_evidence' => null,
                'coverage_scope' => 'framework',
            ];
        }
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $wheresql = implode(' AND ', $where);
        $evidence = (int)$DB->count_records_select('flwcupkp_evidence', $wheresql, $params);
        $latest = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {flwcupkp_evidence} WHERE {$wheresql}",
            $params
        );

        $statecount = 0;
        if (in_array($type, ['competency', 'up', 'kp'], true)) {
            $statecount = (int)$DB->count_records('flwcupkp_state', ['targettype' => $type, 'targetid' => $id]);
        }

        return [
            'evidence_rows' => $evidence,
            'learner_state_rows' => $statecount,
            'latest_evidence' => $latest ? (int)$latest : null,
            'coverage_scope' => $type,
        ];
    }

    /**
     * Validation details for the selected entity.
     *
     * @param string $type
     * @param \stdClass $record
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function entity_validation(string $type, \stdClass $record, int $courseid, string $unitcode,
            int $frameworkid, int $limit): array {
        $checks = [];
        foreach ([
            'canonical_domain_model' => canonical_domain_model::validate_curriculum_row($type, (array)$record),
            'ontology_boundary' => ontology_boundary::validate_curriculum_row($type, (array)$record),
            'lifecycle_governance' => lifecycle_governance_contract::validate_entity_write($type, (array)$record, $record),
        ] as $name => $result) {
            $checks[$name] = $result + ['valid' => empty($result['errors'])];
        }
        if ($type === 'object') {
            $checks['content_evidence_mapping'] =
                content_evidence_mapping_contract::validate_learning_object_row((array)$record);
        }
        $foundation = foundation_v1_contract::foundation_status($courseid, $unitcode, $frameworkid, min($limit, 50));
        $checks['foundation_v1'] = [
            'valid' => ($foundation['status'] ?? '') === 'frozen',
            'errors' => ($foundation['status'] ?? '') === 'frozen' ? [] : ['Foundation V1 is not frozen.'],
            'warnings' => array_map(static function(array $finding): string {
                return ($finding['code'] ?? 'finding') . ': ' . ($finding['message'] ?? '');
            }, array_filter($foundation['findings'] ?? [], static function(array $finding): bool {
                return !in_array($finding['severity'] ?? '', ['BLOCKER', 'HIGH'], true);
            })),
            'contract' => foundation_v1_contract::CONTRACT_VERSION,
        ];

        return [
            'valid' => empty(array_filter($checks, static function(array $check): bool {
                return empty($check['valid']);
            })),
            'checks' => $checks,
        ];
    }

    /**
     * Recent audit rows for one entity.
     *
     * @param string $type
     * @param int $id
     * @param int $limit
     * @return array
     */
    private static function audit_history(string $type, int $id, int $limit): array {
        global $DB;

        return array_values($DB->get_records('flwcupkp_audit', [
            'targettype' => $type,
            'targetid' => $id,
        ], 'timecreated DESC, id DESC', '*', 0, $limit));
    }

    /**
     * Normalize entity type aliases.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_entity_type(string $type): string {
        $type = strtolower(trim(str_replace('-', '_', $type)));
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
        $type = $aliases[$type] ?? $type;
        if (!in_array($type, self::ENTITY_TYPES, true)) {
            throw new \invalid_parameter_exception('Unknown CM1 entity type.');
        }
        return $type;
    }

    /**
     * Human workflow label.
     *
     * @param string $status
     * @return string
     */
    private static function workflow_label(string $status): string {
        $labels = [
            'review' => get_string('sendtoreview', 'local_flwcupkp'),
            'approved' => get_string('approve', 'local_flwcupkp'),
            'published' => get_string('publish', 'local_flwcupkp'),
            'deprecated' => get_string('deprecate', 'local_flwcupkp'),
        ];
        return $labels[$status] ?? strtoupper($status);
    }

    /**
     * Build a finding row.
     *
     * @param string $severity
     * @param string $code
     * @param string $message
     * @return array
     */
    private static function finding(string $severity, string $code, string $message): array {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ];
    }
}

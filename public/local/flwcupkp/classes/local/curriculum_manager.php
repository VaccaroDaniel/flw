<?php
// Curriculum management helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads and writes the C-UP-KP curriculum graph.
 */
final class curriculum_manager {
    /** @var array Entity configuration keyed by UI type. */
    private const ENTITIES = [
        'framework' => [
            'table' => 'flwcupkp_framework',
            'label' => 'framework',
            'fields' => ['externalid', 'name', 'courseid', 'coursecode', 'language', 'cefrrange', 'version', 'status', 'description', 'moodleframeworkid'],
            'required' => ['externalid', 'name'],
            'textarea' => ['description'],
        ],
        'competency' => [
            'table' => 'flwcupkp_comp',
            'label' => 'competency',
            'fields' => ['frameworkid', 'externalid', 'title', 'cando', 'description', 'cefr', 'stage', 'domain', 'scope', 'evidencerule', 'moodlecompetencyid', 'status', 'version'],
            'required' => ['frameworkid', 'externalid', 'title'],
            'textarea' => ['cando', 'description', 'evidencerule'],
        ],
        'up' => [
            'table' => 'flwcupkp_up',
            'label' => 'usepoint',
            'fields' => ['frameworkid', 'externalid', 'title', 'actionstatement', 'intention', 'context', 'observableaction', 'conditions', 'successcriteria', 'cefr', 'languagemode', 'interactiontype', 'evidencerequirements', 'rubricref', 'status', 'version'],
            'required' => ['frameworkid', 'externalid', 'title'],
            'textarea' => ['actionstatement', 'intention', 'context', 'observableaction', 'conditions', 'successcriteria', 'evidencerequirements'],
        ],
        'kp' => [
            'table' => 'flwcupkp_kp',
            'label' => 'knowledgepoint',
            'fields' => ['frameworkid', 'externalid', 'title', 'description', 'language', 'cefr', 'domain', 'formtext', 'meaningfunction', 'usageconstraints', 'difficulty', 'learningload', 'evidencerequirements', 'status', 'version'],
            'required' => ['frameworkid', 'externalid', 'title', 'domain'],
            'textarea' => ['description', 'formtext', 'meaningfunction', 'usageconstraints', 'evidencerequirements'],
        ],
        'object' => [
            'table' => 'flwcupkp_object',
            'label' => 'learningobject',
            'fields' => ['frameworkid', 'externalid', 'courseid', 'unitcode', 'lesson', 'objecttype', 'title', 'cmid', 'sourceid', 'purpose', 'evidencestrength', 'difficulty', 'role', 'metadatajson'],
            'required' => ['frameworkid', 'externalid', 'title', 'objecttype'],
            'textarea' => ['metadatajson'],
        ],
    ];

    /** @var array Mapping configuration keyed by UI type. */
    private const MAPPINGS = [
        'comp_up' => [
            'table' => 'flwcupkp_comp_up',
            'left' => 'competencyid',
            'right' => 'upid',
            'fields' => ['competencyid', 'upid', 'role', 'weight', 'sortorder', 'minmastery', 'evidencerule', 'notes'],
            'required' => ['competencyid', 'upid'],
            'audit_target' => 'comp_up',
        ],
        'up_kp' => [
            'table' => 'flwcupkp_up_kp',
            'left' => 'upid',
            'right' => 'kpid',
            'fields' => ['upid', 'kpid', 'role', 'weight', 'minreadiness', 'sortorder', 'notes'],
            'required' => ['upid', 'kpid'],
            'audit_target' => 'up_kp',
        ],
        'kp_prereq' => [
            'table' => 'flwcupkp_kp_prereq',
            'left' => 'kpid',
            'right' => 'prereqkpid',
            'fields' => ['kpid', 'prereqkpid', 'relationshiptype', 'strength', 'requirement', 'notes'],
            'required' => ['kpid', 'prereqkpid'],
            'audit_target' => 'kp_prereq',
        ],
        'object_map' => [
            'table' => 'flwcupkp_object_map',
            'left' => 'objectid',
            'right' => 'targetid',
            'fields' => ['objectid', 'targettype', 'targetid', 'role', 'evidencestrength'],
            'required' => ['objectid', 'targettype', 'targetid'],
            'audit_target' => 'object_map',
        ],
    ];

    /**
     * Entity type configuration.
     *
     * @return array
     */
    public static function entity_types(): array {
        return self::ENTITIES;
    }

    /**
     * Mapping type configuration.
     *
     * @return array
     */
    public static function mapping_types(): array {
        return self::MAPPINGS;
    }

    /**
     * Get one entity configuration.
     *
     * @param string $type
     * @return array
     */
    public static function entity_config(string $type): array {
        if (!isset(self::ENTITIES[$type])) {
            throw new \invalid_parameter_exception('Unknown C-UP-KP entity type.');
        }
        return self::ENTITIES[$type];
    }

    /**
     * Get one mapping configuration.
     *
     * @param string $type
     * @return array
     */
    public static function mapping_config(string $type): array {
        if (!isset(self::MAPPINGS[$type])) {
            throw new \invalid_parameter_exception('Unknown C-UP-KP mapping type.');
        }
        return self::MAPPINGS[$type];
    }

    /**
     * Framework select options.
     *
     * @return array
     */
    public static function framework_options(): array {
        global $DB;

        $records = $DB->get_records('flwcupkp_framework', null, 'name ASC, externalid ASC', 'id, externalid, name');
        $options = [];
        foreach ($records as $record) {
            $options[(int)$record->id] = $record->name . ' (' . $record->externalid . ')';
        }
        return $options;
    }

    /**
     * Unit-code select options.
     *
     * @return array
     */
    public static function unit_options(): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT unitcode
               FROM {flwcupkp_object}
              WHERE unitcode IS NOT NULL AND unitcode <> ''
           ORDER BY unitcode ASC"
        );
        $options = [];
        foreach ($records as $record) {
            $options[$record->unitcode] = $record->unitcode;
        }
        return $options;
    }

    /**
     * Get one entity record.
     *
     * @param string $type
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_entity(string $type, int $id): ?\stdClass {
        global $DB;

        if ($id <= 0) {
            return null;
        }
        $config = self::entity_config($type);
        return $DB->get_record($config['table'], ['id' => $id], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * List entity records.
     *
     * @param string $type
     * @param int $frameworkid
     * @param string $query
     * @return array
     */
    public static function list_entities(string $type, int $frameworkid = 0, string $query = ''): array {
        global $DB;

        $config = self::entity_config($type);
        $params = [];
        $where = '1=1';
        if ($frameworkid > 0 && $type !== 'framework') {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        if ($query !== '') {
            $where .= ' AND (' . $DB->sql_like('externalid', ':queryid', false) . ' OR ' .
                $DB->sql_like($type === 'framework' ? 'name' : 'title', ':querytitle', false) . ')';
            $params['queryid'] = '%' . $DB->sql_like_escape($query) . '%';
            $params['querytitle'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        $order = $type === 'framework' ? 'name ASC, externalid ASC' : 'externalid ASC';
        return $DB->get_records_select($config['table'], $where, $params, $order, '*', 0, 500);
    }

    /**
     * Get one mapping record.
     *
     * @param string $type
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_mapping(string $type, int $id): ?\stdClass {
        global $DB;

        if ($id <= 0) {
            return null;
        }
        $config = self::mapping_config($type);
        return $DB->get_record($config['table'], ['id' => $id], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Save an entity row.
     *
     * @param string $type
     * @param array $data
     * @return int
     */
    public static function save_entity(string $type, array $data): int {
        global $DB;

        $config = self::entity_config($type);
        $data = lifecycle_governance_contract::normalize_entity_payload($type, $data);
        $existing = null;
        if (!empty($data['id'])) {
            $existing = $DB->get_record($config['table'], ['id' => (int)$data['id']], '*', MUST_EXIST);
        } else if (!empty($data['externalid'])) {
            $existing = $DB->get_record($config['table'], ['externalid' => (string)$data['externalid']], '*', IGNORE_MISSING) ?: null;
        }
        canonical_domain_model::assert_curriculum_row($type, $data);
        ontology_boundary::assert_curriculum_row($type, $data);
        if ($type === 'object') {
            content_evidence_mapping_contract::assert_learning_object_row($data);
        }
        $record = new \stdClass();
        foreach ($config['fields'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $record->{$field} = self::normalize_value($field, $data[$field]);
        }

        foreach ($config['required'] as $field) {
            if (!isset($record->{$field}) || $record->{$field} === '' || $record->{$field} === 0) {
                throw new \invalid_parameter_exception($field . ' is required.');
            }
        }
        self::assert_entity_references($type, $record);
        lifecycle_governance_contract::assert_entity_write($type, $data, $existing);

        if ($existing !== null && !empty($data['id'])) {
            $record->id = (int)$existing->id;
            $record->externalid = $existing->externalid;
            if (isset($existing->timecreated)) {
                $record->timecreated = $existing->timecreated;
            }
        }

        $id = repository::upsert_by_externalid($config['table'], $record);
        repository::audit('curriculum_entity_saved', $type, $id, [
            'externalid' => $record->externalid ?? '',
            'table' => $config['table'],
        ]);
        return $id;
    }

    /**
     * Save a mapping row.
     *
     * @param string $type
     * @param array $data
     * @return int
     */
    public static function save_mapping(string $type, array $data): int {
        global $DB;

        $config = self::mapping_config($type);
        $record = new \stdClass();
        $existing = null;
        if (!empty($data['id'])) {
            $existing = $DB->get_record($config['table'], ['id' => (int)$data['id']], '*', MUST_EXIST);
            $record->id = (int)$existing->id;
        }
        foreach ($config['fields'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $record->{$field} = self::normalize_value($field, $data[$field]);
        }

        foreach ($config['required'] as $field) {
            if (!isset($record->{$field}) || $record->{$field} === '' || $record->{$field} === 0) {
                throw new \invalid_parameter_exception($field . ' is required.');
            }
        }
        ontology_boundary::assert_mapping_row($type, (array)$record);
        relationship_graph_contract::assert_mapping_row($type, (array)$record);
        self::assert_mapping_references($type, $record);
        if ($type === 'object_map') {
            $object = $DB->get_record('flwcupkp_object', ['id' => (int)$record->objectid], '*', MUST_EXIST);
            content_evidence_mapping_contract::assert_object_map_contract($object, $record);
        }
        relationship_graph_contract::assert_mapping_change($type, (array)$record);
        lifecycle_governance_contract::assert_mapping_change($type, (array)$record);

        $keys = [];
        $keys[$config['left']] = $record->{$config['left']};
        if ($type === 'object_map') {
            $keys['targettype'] = $record->targettype;
        }
        $keys[$config['right']] = $record->{$config['right']};

        if ($existing !== null) {
            $conflict = $DB->get_record($config['table'], $keys, 'id', IGNORE_MISSING);
            if ($conflict && (int)$conflict->id !== (int)$existing->id) {
                throw new \invalid_parameter_exception('A C-UP-KP mapping with these endpoints already exists.');
            }
            $DB->update_record($config['table'], $record);
            $id = (int)$existing->id;
        } else {
            $id = repository::upsert_mapping($config['table'], $keys, $record);
        }
        repository::audit('curriculum_mapping_saved', $config['audit_target'], $id, [
            'table' => $config['table'],
            'keys' => $keys,
            'existingid' => $existing->id ?? null,
        ]);
        return $id;
    }

    /**
     * Delete a mapping row.
     *
     * @param string $type
     * @param int $id
     */
    public static function delete_mapping(string $type, int $id): void {
        global $DB;

        $config = self::mapping_config($type);
        $record = $DB->get_record($config['table'], ['id' => $id], '*', MUST_EXIST);
        lifecycle_governance_contract::assert_mapping_delete($type, $record);
        $DB->delete_records($config['table'], ['id' => $id]);
        repository::audit('curriculum_mapping_deleted', $config['audit_target'], $id, [
            'table' => $config['table'],
            'record' => $record,
        ]);
    }

    /**
     * Apply one status to every entity of a type in a framework scope.
     *
     * @param string $type
     * @param int $frameworkid
     * @param string $status
     * @return array
     */
    public static function bulk_update_status(string $type, int $frameworkid, string $status): array {
        global $DB, $USER;

        if (!in_array($type, ['framework', 'competency', 'up', 'kp'], true)) {
            throw new \invalid_parameter_exception('This entity type does not support bulk status changes.');
        }
        if ($status === '') {
            throw new \invalid_parameter_exception('Status is required.');
        }
        $status = lifecycle_governance_contract::canonical_status($status);
        ontology_boundary::assert_entity_status($status);

        $config = self::entity_config($type);
        $params = [];
        if ($type === 'framework') {
            $where = $frameworkid > 0 ? 'id = :frameworkid' : '1=1';
            if ($frameworkid > 0) {
                $params['frameworkid'] = $frameworkid;
            }
        } else {
            if ($frameworkid <= 0) {
                throw new \invalid_parameter_exception('Choose a framework before bulk-changing entity status.');
            }
            $where = 'frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }

        $records = $DB->get_records_select($config['table'], $where, $params);
        foreach ($records as $record) {
            $proposed = (array)$record;
            $proposed['status'] = $status;
            lifecycle_governance_contract::assert_entity_write($type, $proposed, $record);
        }

        $count = count($records);
        if ($count > 0) {
            $transaction = $DB->start_delegated_transaction();
            $now = time();
            foreach ($records as $record) {
                $update = (object)[
                    'id' => $record->id,
                    'status' => $status,
                    'timemodified' => $now,
                    'usermodified' => $USER->id ?? 0,
                ];
                $DB->update_record($config['table'], $update);
            }
            $transaction->allow_commit();
        }

        repository::audit('curriculum_bulk_status_updated', $type, $frameworkid ?: null, [
            'table' => $config['table'],
            'frameworkid' => $frameworkid,
            'status' => $status,
            'count' => $count,
        ]);

        return ['type' => $type, 'frameworkid' => $frameworkid, 'status' => $status, 'count' => $count];
    }

    /**
     * Apply one governed lifecycle transition to a single curriculum entity.
     *
     * @param string $type
     * @param int $id
     * @param string $status
     * @return array
     */
    public static function transition_entity_status(string $type, int $id, string $status): array {
        global $DB, $USER;

        if (!in_array($type, ['framework', 'competency', 'up', 'kp'], true)) {
            throw new \invalid_parameter_exception('This entity type does not support lifecycle workflow actions.');
        }
        if ($id <= 0) {
            throw new \invalid_parameter_exception('Entity ID is required.');
        }

        $config = self::entity_config($type);
        $record = $DB->get_record($config['table'], ['id' => $id], '*', MUST_EXIST);
        $newstatus = lifecycle_governance_contract::canonical_status($status);
        ontology_boundary::assert_entity_status($newstatus);

        $proposed = (array)$record;
        $proposed['status'] = $newstatus;
        lifecycle_governance_contract::assert_entity_write($type, $proposed, $record);

        $DB->update_record($config['table'], (object)[
            'id' => $id,
            'status' => $newstatus,
            'timemodified' => time(),
            'usermodified' => $USER->id ?? 0,
        ]);

        repository::audit('curriculum_entity_status_transitioned', $type, $id, [
            'table' => $config['table'],
            'from' => $record->status ?? 'draft',
            'to' => $newstatus,
            'externalid' => $record->externalid ?? '',
        ]);

        return [
            'type' => $type,
            'id' => $id,
            'from' => (string)($record->status ?? 'draft'),
            'to' => $newstatus,
        ];
    }

    /**
     * Clone a framework and its curriculum graph into a new draft version.
     *
     * Learner evidence, learner states, recommendations, import records, and audit rows are intentionally not cloned.
     *
     * @param int $frameworkid
     * @param string $newversion
     * @param string $suffix
     * @return array
     */
    public static function clone_framework_version(int $frameworkid, string $newversion, string $suffix): array {
        global $DB, $USER;

        if ($frameworkid <= 0) {
            throw new \invalid_parameter_exception('Source framework is required.');
        }
        $newversion = trim($newversion);
        $suffix = self::normalize_clone_suffix($suffix);
        if ($newversion === '') {
            throw new \invalid_parameter_exception('New version is required.');
        }

        $source = $DB->get_record('flwcupkp_framework', ['id' => $frameworkid], '*', MUST_EXIST);
        lifecycle_governance_contract::assert_framework_clone($source, $newversion, $suffix);
        $newframeworkexternalid = self::clone_externalid((string)$source->externalid, $suffix, 100);
        if ($DB->record_exists('flwcupkp_framework', ['externalid' => $newframeworkexternalid])) {
            throw new \invalid_parameter_exception('The cloned framework external ID already exists: ' . $newframeworkexternalid);
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $frameworkrecord = (object)[
            'externalid' => $newframeworkexternalid,
            'name' => $source->name . ' ' . $newversion,
            'courseid' => $source->courseid,
            'coursecode' => $source->coursecode,
            'language' => $source->language,
            'cefrrange' => $source->cefrrange,
            'version' => $newversion,
            'status' => 'draft',
            'description' => $source->description,
            'parentid' => $source->id,
            'moodleframeworkid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $USER->id ?? 0,
        ];
        $newframeworkid = (int)$DB->insert_record('flwcupkp_framework', $frameworkrecord);

        $compmap = self::clone_framework_entities('flwcupkp_comp', $frameworkid, $newframeworkid, $newversion, $suffix, [
            'frameworkid', 'externalid', 'title', 'cando', 'description', 'cefr', 'stage', 'domain', 'scope',
            'evidencerule', 'status', 'version', 'validfrom', 'validto', 'timecreated', 'timemodified', 'usermodified',
        ], ['moodlecompetencyid'], 100);
        $upmap = self::clone_framework_entities('flwcupkp_up', $frameworkid, $newframeworkid, $newversion, $suffix, [
            'frameworkid', 'externalid', 'title', 'actionstatement', 'intention', 'context', 'observableaction',
            'conditions', 'successcriteria', 'cefr', 'languagemode', 'interactiontype', 'evidencerequirements',
            'rubricref', 'status', 'version', 'timecreated', 'timemodified', 'usermodified',
        ], [], 100);
        $kpmap = self::clone_framework_entities('flwcupkp_kp', $frameworkid, $newframeworkid, $newversion, $suffix, [
            'frameworkid', 'externalid', 'title', 'description', 'language', 'cefr', 'domain', 'formtext',
            'meaningfunction', 'usageconstraints', 'difficulty', 'learningload', 'evidencerequirements',
            'status', 'version', 'timecreated', 'timemodified', 'usermodified',
        ], [], 100);
        $objectmap = self::clone_framework_entities('flwcupkp_object', $frameworkid, $newframeworkid, $newversion, $suffix, [
            'frameworkid', 'externalid', 'courseid', 'unitcode', 'lesson', 'objecttype', 'title', 'cmid', 'sourceid',
            'purpose', 'evidencestrength', 'difficulty', 'role', 'metadatajson',
        ], ['courseid', 'cmid'], 120);

        self::clone_mapping_rows('flwcupkp_comp_up', ['competencyid' => $compmap, 'upid' => $upmap], [
            'competencyid', 'upid', 'role', 'weight', 'sortorder', 'minmastery', 'evidencerule', 'notes',
        ]);
        self::clone_mapping_rows('flwcupkp_up_kp', ['upid' => $upmap, 'kpid' => $kpmap], [
            'upid', 'kpid', 'role', 'weight', 'minreadiness', 'sortorder', 'notes',
        ]);
        self::clone_mapping_rows('flwcupkp_kp_prereq', ['kpid' => $kpmap, 'prereqkpid' => $kpmap], [
            'kpid', 'prereqkpid', 'relationshiptype', 'strength', 'requirement', 'notes',
        ]);
        self::clone_object_map_rows($objectmap, $compmap, $upmap, $kpmap);

        repository::audit('curriculum_framework_version_cloned', 'framework', $newframeworkid, [
            'sourceframeworkid' => $frameworkid,
            'sourceexternalid' => $source->externalid,
            'externalid' => $newframeworkexternalid,
            'version' => $newversion,
            'suffix' => $suffix,
            'competencies' => count($compmap),
            'use_points' => count($upmap),
            'knowledge_points' => count($kpmap),
            'learning_objects' => count($objectmap),
        ]);
        $transaction->allow_commit();

        return [
            'frameworkid' => $newframeworkid,
            'externalid' => $newframeworkexternalid,
            'version' => $newversion,
            'competencies' => count($compmap),
            'use_points' => count($upmap),
            'knowledge_points' => count($kpmap),
            'learning_objects' => count($objectmap),
        ];
    }

    /**
     * Build a graph for curriculum browsing.
     *
     * @param int $frameworkid
     * @param string $unitcode
     * @param string $query
     * @return array
     */
    public static function graph(int $frameworkid = 0, string $unitcode = '', string $query = ''): array {
        global $DB;

        $frameworks = $frameworkid > 0 ?
            $DB->get_records('flwcupkp_framework', ['id' => $frameworkid], 'name ASC') :
            $DB->get_records('flwcupkp_framework', null, 'name ASC, externalid ASC');

        $params = [];
        $frameworksql = '';
        if ($frameworkid > 0) {
            $frameworksql = ' WHERE frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }

        $competencies = $DB->get_records_sql("SELECT * FROM {flwcupkp_comp}{$frameworksql} ORDER BY externalid ASC", $params);
        $ups = $DB->get_records_sql("SELECT * FROM {flwcupkp_up}{$frameworksql} ORDER BY externalid ASC", $params);
        $kps = $DB->get_records_sql("SELECT * FROM {flwcupkp_kp}{$frameworksql} ORDER BY externalid ASC", $params);
        $objects = $DB->get_records_sql("SELECT * FROM {flwcupkp_object}{$frameworksql} ORDER BY unitcode ASC, lesson ASC, externalid ASC", $params);

        if ($unitcode !== '') {
            $objects = array_filter($objects, static function($object) use ($unitcode): bool {
                return (string)$object->unitcode === $unitcode;
            });
        }

        $compup = $DB->get_records('flwcupkp_comp_up', null, 'sortorder ASC, id ASC');
        $upkp = $DB->get_records('flwcupkp_up_kp', null, 'sortorder ASC, id ASC');
        $prereqs = $DB->get_records('flwcupkp_kp_prereq', null, 'id ASC');
        $objectmaps = $DB->get_records('flwcupkp_object_map', null, 'id ASC');

        if ($query !== '') {
            self::apply_graph_query($competencies, $ups, $kps, $objects, $query);
        }

        if ($unitcode !== '') {
            self::apply_unit_scope($competencies, $ups, $kps, $objects, $compup, $upkp, $objectmaps);
        }

        return [
            'frameworks' => $frameworks,
            'competencies' => $competencies,
            'use_points' => $ups,
            'knowledge_points' => $kps,
            'learning_objects' => $objects,
            'comp_up' => $compup,
            'up_kp' => $upkp,
            'kp_prereq' => $prereqs,
            'object_map' => $objectmaps,
            'coverage' => audit_service::coverage($frameworkid ?: null),
        ];
    }

    /**
     * List mapping rows with display records.
     *
     * @param string $type
     * @param int $frameworkid
     * @return array
     */
    public static function list_mappings(string $type, int $frameworkid = 0): array {
        global $DB;

        self::mapping_config($type);
        if ($type === 'comp_up') {
            $params = [];
            $where = '';
            if ($frameworkid > 0) {
                $where = 'WHERE c.frameworkid = :frameworkid';
                $params['frameworkid'] = $frameworkid;
            }
            return $DB->get_records_sql(
                "SELECT m.*, c.externalid AS leftexternalid, c.title AS lefttitle,
                        u.externalid AS rightexternalid, u.title AS righttitle
                   FROM {flwcupkp_comp_up} m
                   JOIN {flwcupkp_comp} c ON c.id = m.competencyid
                   JOIN {flwcupkp_up} u ON u.id = m.upid
                   {$where}
               ORDER BY c.externalid ASC, m.sortorder ASC, u.externalid ASC",
                $params
            );
        }
        if ($type === 'up_kp') {
            $params = [];
            $where = '';
            if ($frameworkid > 0) {
                $where = 'WHERE u.frameworkid = :frameworkid';
                $params['frameworkid'] = $frameworkid;
            }
            return $DB->get_records_sql(
                "SELECT m.*, u.externalid AS leftexternalid, u.title AS lefttitle,
                        kp.externalid AS rightexternalid, kp.title AS righttitle
                   FROM {flwcupkp_up_kp} m
                   JOIN {flwcupkp_up} u ON u.id = m.upid
                   JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
                   {$where}
               ORDER BY u.externalid ASC, m.sortorder ASC, kp.externalid ASC",
                $params
            );
        }
        if ($type === 'kp_prereq') {
            $params = [];
            $where = '';
            if ($frameworkid > 0) {
                $where = 'WHERE kp.frameworkid = :frameworkid';
                $params['frameworkid'] = $frameworkid;
            }
            return $DB->get_records_sql(
                "SELECT m.*, kp.externalid AS leftexternalid, kp.title AS lefttitle,
                        prereq.externalid AS rightexternalid, prereq.title AS righttitle
                   FROM {flwcupkp_kp_prereq} m
                   JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
                   JOIN {flwcupkp_kp} prereq ON prereq.id = m.prereqkpid
                   {$where}
               ORDER BY kp.externalid ASC, prereq.externalid ASC",
                $params
            );
        }

        $params = [];
        $where = '';
        if ($frameworkid > 0) {
            $where = 'WHERE o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return $DB->get_records_sql(
            "SELECT m.*, o.externalid AS leftexternalid, o.title AS lefttitle
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
               {$where}
           ORDER BY o.unitcode ASC, o.lesson ASC, o.externalid ASC, m.targettype ASC",
            $params
        );
    }

    /**
     * Build a canonical export package.
     *
     * @param int $frameworkid
     * @return array
     */
    public static function export_package(int $frameworkid = 0): array {
        $graph = self::graph($frameworkid);
        $frameworkids = array_keys($graph['frameworks']);
        $package = [
            'cupkp_schema_version' => '1.0',
            'course_code' => self::first_nonempty($graph['frameworks'], 'coursecode'),
            'unit_code' => self::first_nonempty($graph['learning_objects'], 'unitcode'),
            'cefr_level' => self::first_nonempty($graph['competencies'], 'cefr') ?: self::first_nonempty($graph['knowledge_points'], 'cefr'),
            'frameworks' => [],
            'competencies' => [],
            'use_points' => [],
            'knowledge_points' => [],
            'competency_up_mappings' => [],
            'up_kp_mappings' => [],
            'kp_prerequisites' => [],
            'learning_objects' => [],
            'activity_mappings' => [],
            'assessment_rules' => [],
        ];

        foreach ($graph['frameworks'] as $framework) {
            $package['frameworks'][] = [
                'externalid' => $framework->externalid,
                'name' => $framework->name,
                'course_code' => $framework->coursecode,
                'language' => $framework->language,
                'cefr_range' => $framework->cefrrange,
                'version' => $framework->version,
                'status' => $framework->status,
                'description' => $framework->description,
            ];
        }
        foreach ($graph['competencies'] as $competency) {
            $package['competencies'][] = [
                'externalid' => $competency->externalid,
                'title' => $competency->title,
                'can_do' => $competency->cando,
                'description' => $competency->description,
                'cefr' => $competency->cefr,
                'stage' => $competency->stage,
                'domain' => $competency->domain,
                'scope' => $competency->scope,
                'evidence_rule' => self::decode_json_field($competency->evidencerule),
                'status' => $competency->status,
                'version' => $competency->version,
            ];
        }
        foreach ($graph['use_points'] as $up) {
            $package['use_points'][] = [
                'externalid' => $up->externalid,
                'title' => $up->title,
                'action_statement' => $up->actionstatement,
                'intention' => $up->intention,
                'context' => $up->context,
                'observable_action' => $up->observableaction,
                'conditions' => $up->conditions,
                'success_criteria' => $up->successcriteria,
                'cefr' => $up->cefr,
                'language_mode' => $up->languagemode,
                'interaction_type' => $up->interactiontype,
                'evidence_requirements' => self::decode_json_field($up->evidencerequirements),
                'rubric_ref' => $up->rubricref,
                'status' => $up->status,
                'version' => $up->version,
            ];
        }
        foreach ($graph['knowledge_points'] as $kp) {
            $package['knowledge_points'][] = [
                'externalid' => $kp->externalid,
                'title' => $kp->title,
                'description' => $kp->description,
                'language' => $kp->language,
                'cefr' => $kp->cefr,
                'domain' => $kp->domain,
                'form' => $kp->formtext,
                'meaning_function' => $kp->meaningfunction,
                'usage_constraints' => $kp->usageconstraints,
                'difficulty' => $kp->difficulty === null ? null : (float)$kp->difficulty,
                'estimated_learning_load' => $kp->learningload === null ? null : (float)$kp->learningload,
                'evidence_requirements' => self::decode_json_field($kp->evidencerequirements),
                'status' => $kp->status,
                'version' => $kp->version,
            ];
        }
        foreach ($graph['learning_objects'] as $object) {
            $package['learning_objects'][] = [
                'externalid' => $object->externalid,
                'title' => $object->title,
                'courseid' => $object->courseid ? (int)$object->courseid : null,
                'unit_code' => $object->unitcode,
                'lesson' => $object->lesson,
                'object_type' => $object->objecttype,
                'cmid' => $object->cmid ? (int)$object->cmid : null,
                'source_id' => $object->sourceid,
                'purpose' => $object->purpose,
                'evidence_strength' => $object->evidencestrength,
                'difficulty' => $object->difficulty === null ? null : (float)$object->difficulty,
                'role' => $object->role,
                'metadata' => self::decode_json_field($object->metadatajson),
            ];
        }

        self::append_mapping_exports($package, $graph, $frameworkids);
        return $package;
    }

    /**
     * Summarize native Moodle competency sync readiness.
     *
     * @return array
     */
    public static function sync_readiness(): array {
        global $DB;

        $frameworks = $DB->count_records('flwcupkp_framework');
        $competencies = $DB->count_records('flwcupkp_comp');
        $linkedframeworks = $DB->count_records_select(
            'flwcupkp_framework',
            'moodleframeworkid IS NOT NULL AND moodleframeworkid > 0'
        );
        $linkedcompetencies = $DB->count_records_select(
            'flwcupkp_comp',
            'moodlecompetencyid IS NOT NULL AND moodlecompetencyid > 0'
        );

        $unlinkedframeworks = max(0, $frameworks - $linkedframeworks);
        $unlinkedcompetencies = max(0, $competencies - $linkedcompetencies);

        return [
            'frameworks' => $frameworks,
            'competencies' => $competencies,
            'linkedframeworks' => $linkedframeworks,
            'linkedcompetencies' => $linkedcompetencies,
            'unlinkedframeworks' => $unlinkedframeworks,
            'unlinkedcompetencies' => $unlinkedcompetencies,
            'readyforwrites' => $frameworks > 0 && $competencies > 0 &&
                $unlinkedframeworks === 0 && $unlinkedcompetencies === 0,
        ];
    }

    /**
     * Validate foreign keys for directly edited entity rows.
     *
     * @param string $type
     * @param \stdClass $record
     */
    private static function assert_entity_references(string $type, \stdClass $record): void {
        global $DB;

        if ($type !== 'framework' && !empty($record->frameworkid) &&
                !$DB->record_exists('flwcupkp_framework', ['id' => (int)$record->frameworkid])) {
            throw new \invalid_parameter_exception('Referenced C-UP-KP framework does not exist.');
        }
        if ($type === 'object' && !empty($record->courseid) &&
                !$DB->record_exists('course', ['id' => (int)$record->courseid])) {
            throw new \invalid_parameter_exception('Referenced Moodle course does not exist.');
        }
    }

    /**
     * Validate foreign keys for directly edited mapping rows.
     *
     * @param string $type
     * @param \stdClass $record
     */
    private static function assert_mapping_references(string $type, \stdClass $record): void {
        global $DB;

        if ($type === 'comp_up') {
            $competency = $DB->get_record('flwcupkp_comp', ['id' => (int)$record->competencyid],
                'id, frameworkid', IGNORE_MISSING);
            $up = $DB->get_record('flwcupkp_up', ['id' => (int)$record->upid], 'id, frameworkid', IGNORE_MISSING);
            self::assert_same_framework($competency, $up);
            return;
        }

        if ($type === 'up_kp') {
            $up = $DB->get_record('flwcupkp_up', ['id' => (int)$record->upid], 'id, frameworkid', IGNORE_MISSING);
            $kp = $DB->get_record('flwcupkp_kp', ['id' => (int)$record->kpid], 'id, frameworkid', IGNORE_MISSING);
            self::assert_same_framework($up, $kp);
            return;
        }

        if ($type === 'kp_prereq') {
            $kp = $DB->get_record('flwcupkp_kp', ['id' => (int)$record->kpid], 'id, frameworkid', IGNORE_MISSING);
            $prereq = $DB->get_record('flwcupkp_kp', ['id' => (int)$record->prereqkpid], 'id, frameworkid', IGNORE_MISSING);
            self::assert_same_framework($kp, $prereq);
            return;
        }

        if ($type === 'object_map') {
            $object = $DB->get_record('flwcupkp_object', ['id' => (int)$record->objectid],
                'id, frameworkid', IGNORE_MISSING);
            if (!$object) {
                throw new \invalid_parameter_exception('Referenced learning object does not exist.');
            }
            $target = $DB->get_record(evidence_guard::target_table((string)$record->targettype),
                ['id' => (int)$record->targetid], 'id, frameworkid', IGNORE_MISSING);
            self::assert_same_framework($object, $target);
        }
    }

    /**
     * Confirm two curriculum records exist and belong to the same framework.
     *
     * @param \stdClass|false $left
     * @param \stdClass|false $right
     */
    private static function assert_same_framework($left, $right): void {
        if (!$left || !$right) {
            throw new \invalid_parameter_exception('Referenced C-UP-KP mapping endpoint does not exist.');
        }
        if ((int)$left->frameworkid !== (int)$right->frameworkid) {
            throw new \invalid_parameter_exception('C-UP-KP mapping endpoints must belong to the same framework.');
        }
    }

    /**
     * Normalize web values.
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    private static function normalize_value(string $field, $value) {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (in_array($field, ['id', 'frameworkid', 'courseid', 'cmid', 'moodleframeworkid', 'moodlecompetencyid', 'sortorder', 'competencyid', 'upid', 'kpid', 'prereqkpid', 'objectid', 'targetid'], true)) {
            return $value === '' ? null : (int)$value;
        }
        if (in_array($field, ['weight', 'minmastery', 'minreadiness', 'strength', 'difficulty', 'learningload'], true)) {
            return $value === '' ? null : (float)$value;
        }
        return $value === '' ? null : $value;
    }

    /**
     * Normalize a suffix for cloned external IDs.
     *
     * @param string $suffix
     * @return string
     */
    private static function normalize_clone_suffix(string $suffix): string {
        $suffix = trim($suffix);
        $suffix = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $suffix);
        $suffix = trim((string)$suffix, '-_.');
        if ($suffix === '') {
            throw new \invalid_parameter_exception('External ID suffix is required.');
        }
        return $suffix;
    }

    /**
     * Build a cloned external ID.
     *
     * @param string $externalid
     * @param string $suffix
     * @param int $maxlength
     * @return string
     */
    private static function clone_externalid(string $externalid, string $suffix, int $maxlength): string {
        $newid = $externalid . '-' . $suffix;
        if (strlen($newid) > $maxlength) {
            $keep = max(1, $maxlength - strlen($suffix) - 1);
            $newid = substr($externalid, 0, $keep) . '-' . $suffix;
        }
        return $newid;
    }

    /**
     * Clone rows belonging to one framework and return an old-id to new-id map.
     *
     * @param string $table
     * @param int $sourceframeworkid
     * @param int $targetframeworkid
     * @param string $newversion
     * @param string $suffix
     * @param array $fields
     * @param array $forceempty
     * @param int $externalidmaxlength
     * @return array
     */
    private static function clone_framework_entities(string $table, int $sourceframeworkid, int $targetframeworkid,
            string $newversion, string $suffix, array $fields, array $forceempty, int $externalidmaxlength): array {
        global $DB, $USER;

        $now = time();
        $idmap = [];
        $records = $DB->get_records($table, ['frameworkid' => $sourceframeworkid], 'id ASC');
        foreach ($records as $source) {
            $record = new \stdClass();
            foreach ($fields as $field) {
                if (property_exists($source, $field)) {
                    $record->{$field} = $source->{$field};
                }
            }
            $record->frameworkid = $targetframeworkid;
            $record->externalid = self::clone_externalid((string)$source->externalid, $suffix, $externalidmaxlength);
            if (in_array('version', $fields, true)) {
                $record->version = $newversion;
            }
            if (in_array('status', $fields, true)) {
                $record->status = 'draft';
            }
            if (property_exists($source, 'timecreated')) {
                $record->timecreated = $now;
            }
            if (property_exists($source, 'timemodified')) {
                $record->timemodified = $now;
            }
            if (property_exists($source, 'usermodified')) {
                $record->usermodified = $USER->id ?? 0;
            }
            foreach ($forceempty as $field) {
                $record->{$field} = null;
            }
            if ($DB->record_exists($table, ['externalid' => $record->externalid])) {
                throw new \invalid_parameter_exception('The cloned external ID already exists: ' . $record->externalid);
            }
            $idmap[(int)$source->id] = (int)$DB->insert_record($table, $record);
        }
        return $idmap;
    }

    /**
     * Clone a mapping table where every endpoint has a mapped clone.
     *
     * @param string $table
     * @param array $fieldmaps
     * @param array $fields
     */
    private static function clone_mapping_rows(string $table, array $fieldmaps, array $fields): void {
        global $DB;

        $records = $DB->get_records($table, null, 'id ASC');
        foreach ($records as $source) {
            $record = new \stdClass();
            foreach ($fieldmaps as $field => $idmap) {
                $oldid = (int)$source->{$field};
                if (!isset($idmap[$oldid])) {
                    continue 2;
                }
                $record->{$field} = $idmap[$oldid];
            }
            foreach ($fields as $field) {
                if (!isset($fieldmaps[$field]) && property_exists($source, $field)) {
                    $record->{$field} = $source->{$field};
                }
            }
            $DB->insert_record($table, $record);
        }
    }

    /**
     * Clone object target mappings for cloned objects and targets.
     *
     * @param array $objectmap
     * @param array $compmap
     * @param array $upmap
     * @param array $kpmap
     */
    private static function clone_object_map_rows(array $objectmap, array $compmap, array $upmap, array $kpmap): void {
        global $DB;

        $targetmaps = [
            'competency' => $compmap,
            'up' => $upmap,
            'kp' => $kpmap,
        ];
        $records = $DB->get_records('flwcupkp_object_map', null, 'id ASC');
        foreach ($records as $source) {
            $oldobjectid = (int)$source->objectid;
            if (!isset($objectmap[$oldobjectid]) || !isset($targetmaps[$source->targettype])) {
                continue;
            }
            $oldtargetid = (int)$source->targetid;
            if (!isset($targetmaps[$source->targettype][$oldtargetid])) {
                continue;
            }
            $DB->insert_record('flwcupkp_object_map', (object)[
                'objectid' => $objectmap[$oldobjectid],
                'targettype' => $source->targettype,
                'targetid' => $targetmaps[$source->targettype][$oldtargetid],
                'role' => $source->role,
                'evidencestrength' => $source->evidencestrength,
            ]);
        }
    }

    /**
     * Apply a search query to graph records.
     */
    private static function apply_graph_query(array &$competencies, array &$ups, array &$kps, array &$objects, string $query): void {
        $needle = \core_text::strtolower($query);
        $filter = static function($record) use ($needle): bool {
            $haystack = \core_text::strtolower(($record->externalid ?? '') . ' ' . ($record->title ?? '') . ' ' .
                ($record->name ?? '') . ' ' . ($record->description ?? ''));
            return strpos($haystack, $needle) !== false;
        };
        $competencies = array_filter($competencies, $filter);
        $ups = array_filter($ups, $filter);
        $kps = array_filter($kps, $filter);
        $objects = array_filter($objects, $filter);
    }

    /**
     * Scope graph records to learning objects currently in the selected unit.
     */
    private static function apply_unit_scope(array &$competencies, array &$ups, array &$kps, array $objects,
            array &$compup, array &$upkp, array $objectmaps): void {
        $objectids = array_fill_keys(array_keys($objects), true);
        $targetsets = ['competency' => [], 'up' => [], 'kp' => []];
        foreach ($objectmaps as $map) {
            if (!isset($objectids[(int)$map->objectid])) {
                continue;
            }
            $targetsets[$map->targettype][(int)$map->targetid] = true;
        }

        foreach ($upkp as $map) {
            if (isset($targetsets['kp'][(int)$map->kpid])) {
                $targetsets['up'][(int)$map->upid] = true;
            }
        }
        foreach ($compup as $map) {
            if (isset($targetsets['up'][(int)$map->upid])) {
                $targetsets['competency'][(int)$map->competencyid] = true;
            }
        }
        foreach ($compup as $map) {
            if (isset($targetsets['competency'][(int)$map->competencyid])) {
                $targetsets['up'][(int)$map->upid] = true;
            }
        }
        foreach ($upkp as $map) {
            if (isset($targetsets['up'][(int)$map->upid])) {
                $targetsets['kp'][(int)$map->kpid] = true;
            }
        }

        $competencies = array_filter($competencies, static function($record) use ($targetsets): bool {
            return isset($targetsets['competency'][(int)$record->id]);
        });
        $ups = array_filter($ups, static function($record) use ($targetsets): bool {
            return isset($targetsets['up'][(int)$record->id]);
        });
        $kps = array_filter($kps, static function($record) use ($targetsets): bool {
            return isset($targetsets['kp'][(int)$record->id]);
        });
        $compup = array_filter($compup, static function($map) use ($competencies, $ups): bool {
            return isset($competencies[(int)$map->competencyid]) && isset($ups[(int)$map->upid]);
        });
        $upkp = array_filter($upkp, static function($map) use ($ups, $kps): bool {
            return isset($ups[(int)$map->upid]) && isset($kps[(int)$map->kpid]);
        });
    }

    /**
     * Append mappings to an export package.
     */
    private static function append_mapping_exports(array &$package, array $graph, array $frameworkids): void {
        foreach ($graph['comp_up'] as $map) {
            if (!isset($graph['competencies'][(int)$map->competencyid]) || !isset($graph['use_points'][(int)$map->upid])) {
                continue;
            }
            $package['competency_up_mappings'][] = [
                'competency_externalid' => $graph['competencies'][(int)$map->competencyid]->externalid,
                'up_externalid' => $graph['use_points'][(int)$map->upid]->externalid,
                'role' => $map->role,
                'weight' => $map->weight === null ? null : (float)$map->weight,
                'sequence' => (int)$map->sortorder,
                'minimum_up_mastery' => $map->minmastery === null ? null : (float)$map->minmastery,
                'evidence_rule' => self::decode_json_field($map->evidencerule),
                'notes' => $map->notes,
            ];
        }
        foreach ($graph['up_kp'] as $map) {
            if (!isset($graph['use_points'][(int)$map->upid]) || !isset($graph['knowledge_points'][(int)$map->kpid])) {
                continue;
            }
            $package['up_kp_mappings'][] = [
                'up_externalid' => $graph['use_points'][(int)$map->upid]->externalid,
                'kp_externalid' => $graph['knowledge_points'][(int)$map->kpid]->externalid,
                'role' => $map->role,
                'weight' => $map->weight === null ? null : (float)$map->weight,
                'minimum_kp_readiness' => $map->minreadiness === null ? null : (float)$map->minreadiness,
                'sequence' => (int)$map->sortorder,
                'notes' => $map->notes,
            ];
        }
        foreach ($graph['kp_prereq'] as $map) {
            if (!isset($graph['knowledge_points'][(int)$map->kpid]) || !isset($graph['knowledge_points'][(int)$map->prereqkpid])) {
                continue;
            }
            $package['kp_prerequisites'][] = [
                'kp_externalid' => $graph['knowledge_points'][(int)$map->kpid]->externalid,
                'prereq_kp_externalid' => $graph['knowledge_points'][(int)$map->prereqkpid]->externalid,
                'relationship_type' => $map->relationshiptype,
                'strength' => $map->strength === null ? null : (float)$map->strength,
                'requirement' => $map->requirement,
                'notes' => $map->notes,
            ];
        }
        foreach ($graph['object_map'] as $map) {
            if (!isset($graph['learning_objects'][(int)$map->objectid])) {
                continue;
            }
            $target = self::target_record($map->targettype, (int)$map->targetid, $graph);
            if (!$target || ($frameworkids && !in_array((int)$target->frameworkid, $frameworkids, true))) {
                continue;
            }
            $package['activity_mappings'][] = [
                'object_externalid' => $graph['learning_objects'][(int)$map->objectid]->externalid,
                'target_type' => $map->targettype,
                'target_externalid' => $target->externalid,
                'role' => $map->role,
                'evidence_strength' => $map->evidencestrength,
            ];
        }
    }

    /**
     * Get a target record from graph arrays.
     */
    private static function target_record(string $targettype, int $targetid, array $graph): ?\stdClass {
        if ($targettype === 'competency') {
            return $graph['competencies'][$targetid] ?? null;
        }
        if ($targettype === 'up') {
            return $graph['use_points'][$targetid] ?? null;
        }
        if ($targettype === 'kp') {
            return $graph['knowledge_points'][$targetid] ?? null;
        }
        return null;
    }

    /**
     * Decode stored JSON fields while tolerating legacy text values.
     */
    private static function decode_json_field(?string $value) {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Return the first nonempty field from a record list.
     */
    private static function first_nonempty(array $records, string $field): string {
        foreach ($records as $record) {
            if (!empty($record->{$field})) {
                return (string)$record->{$field};
            }
        }
        return '';
    }
}

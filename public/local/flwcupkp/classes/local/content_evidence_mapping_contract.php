<?php
// Program 3 Gate C3 content and evidence mapping contract.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes how C-UP-KP nodes connect to FLW content IDs and evidence sources.
 */
final class content_evidence_mapping_contract {
    /** Program 3 content/evidence mapping gate. */
    public const GATE = 'P3_C3';

    /** Frozen C3 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1';

    /** @var array Stable Program 1 identity fields accepted by C3. */
    private const STABLE_IDENTITY_FIELDS = [
        'sourcekey',
        'unitid',
        'lessonid',
        'componentid',
        'activityid',
        'assessmentid',
        'questionid',
        'cmid',
    ];

    /** @var array Evidence source types accepted by C3. */
    private const SOURCE_TYPES = [
        'program2_attempt',
        'grade_linked_assessment',
        'completion',
        'teacher_observation',
        'placement',
        'checkpoint',
        'external_assessment',
    ];

    /** @var array Canonical pedagogical roles. */
    private const PEDAGOGICAL_ROLES = [
        'TEACHES' => [
            'meaning' => 'Introduces or explains content but does not create evidence by itself.',
            'can_create_evidence' => false,
        ],
        'PRACTICES' => [
            'meaning' => 'Gives learner practice; scored attempts can become evidence, plain completion normally cannot.',
            'can_create_evidence' => true,
        ],
        'ASSESSES' => [
            'meaning' => 'Measures target performance or knowledge.',
            'can_create_evidence' => true,
        ],
        'EVIDENCE_FOR' => [
            'meaning' => 'Directly declares that the source can produce evidence for the target.',
            'can_create_evidence' => true,
        ],
    ];

    /**
     * Return the frozen C3 content/evidence mapping contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpContentEvidenceMappingContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                canonical_domain_model::CONTRACT_VERSION,
                ontology_boundary::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'stable_content_identity' => [
                'rule' => 'Map by stable FLW/Program-1 IDs, not human titles.',
                'fields' => self::STABLE_IDENTITY_FIELDS,
                'moodle_ids_are_links_not_identity' => ['courseid', 'cmid'],
                'unresolved_identity_behavior' => 'Keep unresolved identity facts; do not fabricate mappings from titles.',
            ],
            'pedagogical_roles' => self::PEDAGOGICAL_ROLES,
            'source_types' => self::SOURCE_TYPES,
            'completion_rule' => [
                'completion_is_mastery' => false,
                'allowed_only_when_pedagogically_valid' => true,
                'default_allowed_roles' => ['ASSESSES', 'EVIDENCE_FOR'],
                'practice_completion_requires_evidence_purpose_or_explicit_flag' => true,
            ],
            'content_mapping_surface' => [
                'learning_object' => 'Stores stable object externalid plus Program 1 identity metadata.',
                'object_map' => 'Connects object IDs to C/UP/KP targets with a pedagogical role.',
                'quiz_kp_metadata' => 'Stores question/item IDs without requiring title-based question matching.',
            ],
            'does_not_do' => [
                'mastery_state_decision',
                'evidence_quality_policy',
                'adaptive_path_selection',
                'raw_moodle_log_scraping',
            ],
        ];
    }

    /**
     * Validate a learning-object package or DB row.
     *
     * @param array $row
     * @return array
     */
    public static function validate_learning_object_row(array $row): array {
        $errors = [];
        $warnings = [];
        $details = [];

        if (!self::has_value($row['externalid'] ?? ($row['object_externalid'] ?? null))) {
            $errors[] = 'Learning object mappings require a stable externalid; titles are not identity.';
            $details[] = self::detail('missing_stable_object_externalid', 'error');
        }

        $metadata = self::decode_metadata($row['metadatajson'] ?? ($row['metadata'] ?? []), $errors, $details);
        $identity = self::identity_from_row($row, $metadata);
        $details[] = self::detail('program1_identity', 'info', [
            'identity' => $identity,
            'resolved' => self::identity_has_stable_key($identity),
        ]);

        if (array_key_exists('completion_counts_as_evidence', $row)) {
            self::boolish((string)$row['completion_counts_as_evidence'], $errors, $details);
        }

        if (self::has_value($row['role'] ?? null) || self::has_value($row['purpose'] ?? null)) {
            try {
                self::canonical_pedagogical_role(
                    (string)($row['role'] ?? ''),
                    (string)($row['purpose'] ?? ''),
                    (string)($row['objecttype'] ?? ($row['object_type'] ?? ''))
                );
            } catch (\invalid_parameter_exception $e) {
                $errors[] = $e->getMessage();
                $details[] = self::detail('unsupported_pedagogical_role', 'error');
            }
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Throw when a learning-object row violates C3.
     *
     * @param array $row
     */
    public static function assert_learning_object_row(array $row): void {
        $result = self::validate_learning_object_row($row);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Validate one object-target mapping row.
     *
     * @param array $row
     * @param array $objectrow
     * @return array
     */
    public static function validate_object_map_row(array $row, array $objectrow = []): array {
        $errors = [];
        $warnings = [];
        $details = [];

        if (!self::has_value($row['role'] ?? null) && empty($objectrow)) {
            $row['role'] = 'practice';
            $warnings[] = 'Object mapping without a role defaults to PRACTICES for legacy imports.';
            $details[] = self::detail('object_map_role_defaulted', 'warning', ['role' => 'PRACTICES']);
        }

        try {
            $role = self::canonical_pedagogical_role(
                (string)($row['role'] ?? ''),
                (string)($objectrow['purpose'] ?? ''),
                (string)($objectrow['objecttype'] ?? ($objectrow['object_type'] ?? ''))
            );
            $details[] = self::detail('pedagogical_role', 'info', ['role' => $role]);
        } catch (\invalid_parameter_exception $e) {
            $errors[] = $e->getMessage();
            $details[] = self::detail('unsupported_pedagogical_role', 'error');
            $role = null;
        }

        if (!self::has_value($row['objectid'] ?? ($row['object_externalid'] ?? null)) &&
                self::has_value($row['object_title'] ?? null)) {
            $errors[] = 'Object mappings must use object_externalid/objectid, not object_title.';
            $details[] = self::detail('title_used_as_object_identity', 'error');
        }
        if (!self::has_value($row['targetid'] ?? ($row['target_externalid'] ?? null)) &&
                self::has_value($row['target_title'] ?? null)) {
            $errors[] = 'Object mappings must use target_externalid/targetid, not target_title.';
            $details[] = self::detail('title_used_as_target_identity', 'error');
        }

        if (self::boolish_value($row['completion_counts_as_evidence'] ?? null) === true && $role === 'TEACHES') {
            $errors[] = 'TEACHES mappings cannot declare completion as evidence.';
            $details[] = self::detail('completion_on_teaches_mapping', 'error');
        }
        if (array_key_exists('completion_counts_as_evidence', $row)) {
            self::boolish((string)$row['completion_counts_as_evidence'], $errors, $details);
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Throw when an object-map DB row violates C3.
     *
     * @param \stdClass $object
     * @param \stdClass $map
     */
    public static function assert_object_map_contract(\stdClass $object, \stdClass $map): void {
        $result = self::validate_object_map_row((array)$map, (array)$object);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Return canonical C3 pedagogical role for existing FLW labels.
     *
     * @param string $role
     * @param string $purpose
     * @param string $objecttype
     * @return string
     */
    public static function canonical_pedagogical_role(string $role, string $purpose = '', string $objecttype = ''): string {
        $label = self::normalize_label($role);
        if ($label === '') {
            $label = self::normalize_label($purpose);
        }
        if ($label === '') {
            $label = self::normalize_label($objecttype);
        }

        $aliases = [
            'teach' => 'TEACHES',
            'teaches' => 'TEACHES',
            'trains' => 'TEACHES',
            'instruction' => 'TEACHES',
            'lesson' => 'TEACHES',
            'practice' => 'PRACTICES',
            'practices' => 'PRACTICES',
            'practice_evidence' => 'PRACTICES',
            'review' => 'PRACTICES',
            'review_of' => 'PRACTICES',
            'remediation' => 'PRACTICES',
            'extension' => 'PRACTICES',
            'assessment' => 'ASSESSES',
            'assesses' => 'ASSESSES',
            'diagnostic' => 'ASSESSES',
            'placement' => 'ASSESSES',
            'checkpoint' => 'ASSESSES',
            'performance_evidence' => 'ASSESSES',
            'integrated_performance' => 'ASSESSES',
            'project' => 'ASSESSES',
            'external_assessment' => 'ASSESSES',
            'teacher_observation' => 'EVIDENCE_FOR',
            'evidence_for' => 'EVIDENCE_FOR',
        ];

        if (isset($aliases[$label])) {
            return $aliases[$label];
        }
        if (isset(self::PEDAGOGICAL_ROLES[strtoupper($label)])) {
            return strtoupper($label);
        }
        throw new \invalid_parameter_exception('Unsupported C3 pedagogical role: ' . $role . '.');
    }

    /**
     * Return C3 source type for an evidence payload.
     *
     * @param \stdClass $evidence
     * @return string
     */
    public static function source_type_for_evidence_payload(\stdClass $evidence): string {
        if (self::has_value($evidence->sourcetype ?? null)) {
            return self::normalize_source_type((string)$evidence->sourcetype);
        }
        return self::source_type_for_evidence_type(
            (string)($evidence->evidencetype ?? ''),
            (string)($evidence->assessortype ?? ''),
            (string)($evidence->provenance ?? '')
        );
    }

    /**
     * Return C3 source type for existing evidence labels.
     *
     * @param string $evidencetype
     * @param string $assessortype
     * @param string $provenance
     * @return string
     */
    public static function source_type_for_evidence_type(string $evidencetype, string $assessortype = '',
            string $provenance = ''): string {
        $type = self::normalize_label($evidencetype);
        $assessor = self::normalize_label($assessortype);
        $source = self::normalize_label($provenance);

        if (strpos($type, 'completion') !== false) {
            return 'completion';
        }
        if (strpos($type, 'grade') !== false || strpos($assessor, 'grade') !== false) {
            return 'grade_linked_assessment';
        }
        if (strpos($type, 'placement') !== false) {
            return 'placement';
        }
        if (strpos($type, 'checkpoint') !== false) {
            return 'checkpoint';
        }
        if (strpos($type, 'teacher') !== false || strpos($type, 'manual') !== false ||
                $assessor === 'teacher' || strpos($source, 'teacher') !== false ||
                strpos($source, 'manual') !== false) {
            return 'teacher_observation';
        }
        if (strpos($type, 'quiz') !== false || strpos($type, 'attempt') !== false ||
                strpos($type, 'submission') !== false || strpos($type, 'h5p') !== false ||
                strpos($type, 'scorm') !== false || strpos($type, 'vr_') === 0) {
            return 'program2_attempt';
        }
        return 'external_assessment';
    }

    /**
     * Whether one source type may create evidence for an object mapping.
     *
     * @param string $sourcetype
     * @param \stdClass $object
     * @param \stdClass $map
     * @return bool
     */
    public static function source_can_count(string $sourcetype, \stdClass $object, \stdClass $map): bool {
        $sourcetype = self::normalize_source_type($sourcetype);
        if (!in_array($sourcetype, self::SOURCE_TYPES, true)) {
            return false;
        }
        $role = self::canonical_pedagogical_role(
            (string)($map->role ?? ''),
            (string)($object->purpose ?? ''),
            (string)($object->objecttype ?? '')
        );

        if ($role === 'TEACHES') {
            return false;
        }
        if ($sourcetype === 'completion') {
            return self::completion_evidence_allowed($object, $map, $role);
        }
        if ($sourcetype === 'placement') {
            return in_array($role, ['ASSESSES', 'EVIDENCE_FOR'], true);
        }
        return in_array($role, ['PRACTICES', 'ASSESSES', 'EVIDENCE_FOR'], true);
    }

    /**
     * Throw when a source may not count as evidence for a map.
     *
     * @param string $sourcetype
     * @param \stdClass $object
     * @param \stdClass $map
     */
    public static function assert_source_can_count(string $sourcetype, \stdClass $object, \stdClass $map): void {
        if (!self::source_can_count($sourcetype, $object, $map)) {
            $role = self::canonical_pedagogical_role(
                (string)($map->role ?? ''),
                (string)($object->purpose ?? ''),
                (string)($object->objecttype ?? '')
            );
            throw new \invalid_parameter_exception(
                $sourcetype . ' is not pedagogically valid evidence for a ' . $role . ' mapping.'
            );
        }
    }

    /**
     * Validate an evidence payload before storage.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @return array
     */
    public static function validate_evidence_payload(\stdClass $evidence, ?\stdClass $object = null,
            ?\stdClass $map = null): array {
        $errors = [];
        $warnings = [];
        $details = [];
        $sourcetype = self::source_type_for_evidence_payload($evidence);
        $details[] = self::detail('source_type', 'info', ['source_type' => $sourcetype]);

        if (!in_array($sourcetype, self::SOURCE_TYPES, true)) {
            $errors[] = 'Unsupported C3 evidence source type: ' . $sourcetype . '.';
            $details[] = self::detail('unsupported_source_type', 'error', ['source_type' => $sourcetype]);
        }

        if (in_array($sourcetype, self::SOURCE_TYPES, true) && $object !== null && $map !== null) {
            $mapresult = self::validate_object_map_row((array)$map, (array)$object);
            self::merge_result($mapresult, $errors, $warnings, $details);
            if (!self::source_can_count($sourcetype, $object, $map)) {
                $role = self::canonical_pedagogical_role(
                    (string)($map->role ?? ''),
                    (string)($object->purpose ?? ''),
                    (string)($object->objecttype ?? '')
                );
                $errors[] = $sourcetype . ' is not pedagogically valid evidence for a ' . $role . ' mapping.';
                $details[] = self::detail('source_not_allowed_for_mapping', 'error', [
                    'source_type' => $sourcetype,
                    'role' => $role,
                    'completion_is_mastery' => false,
                ]);
            }
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Throw when an evidence payload violates C3.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     */
    public static function assert_evidence_payload(\stdClass $evidence, ?\stdClass $object = null,
            ?\stdClass $map = null): void {
        $result = self::validate_evidence_payload($evidence, $object, $map);
        if (!$result['valid']) {
            throw new \invalid_parameter_exception(implode(' ', $result['errors']));
        }
    }

    /**
     * Add non-schema C3 provenance metadata into rubricjson before storage.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @return \stdClass
     */
    public static function augment_evidence_payload(\stdClass $evidence, ?\stdClass $object = null,
            ?\stdClass $map = null): \stdClass {
        $ignorederrors = [];
        $ignoreddetails = [];
        $rubric = self::decode_metadata($evidence->rubricjson ?? [], $ignorederrors, $ignoreddetails);
        $source = self::source_type_for_evidence_payload($evidence);
        $contract = [
            'contract' => self::CONTRACT_VERSION,
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'source_type' => $source,
            'completion_is_mastery' => false,
        ];
        if ($object !== null) {
            $contract['content_identity'] = self::identity_from_object($object);
            $contract['object_externalid'] = (string)($object->externalid ?? '');
        }
        if ($object !== null && $map !== null) {
            $contract['pedagogical_role'] = self::canonical_pedagogical_role(
                (string)($map->role ?? ''),
                (string)($object->purpose ?? ''),
                (string)($object->objecttype ?? '')
            );
            $contract['target_type'] = (string)($map->targettype ?? '');
            $contract['target_id'] = (int)($map->targetid ?? 0);
        }
        $rubric['cupkp_c3_mapping'] = $contract;
        $evidence->rubricjson = json_encode($rubric, JSON_UNESCAPED_SLASHES);
        return $evidence;
    }

    /**
     * Normalize package object metadata to carry Program 1 identity fields.
     *
     * @param array $row
     * @param mixed $metadata
     * @return array
     */
    public static function normalize_object_metadata_from_row(array $row, $metadata = []): array {
        $errors = [];
        $details = [];
        $metadata = self::decode_metadata($metadata, $errors, $details);
        $identity = self::identity_from_row($row, $metadata);
        if ($identity) {
            $metadata['program1_identity'] = $identity;
        }
        $metadata['content_evidence_mapping_contract'] = self::CONTRACT_VERSION;
        $metadata['source_history_contract'] = history_v1_consumer_contract::REQUIRED_CONTRACT;
        $metadata['completion_counts_as_evidence'] =
            self::boolish_value($row['completion_counts_as_evidence'] ?? ($metadata['completion_counts_as_evidence'] ?? null));
        return $metadata;
    }

    /**
     * Return C3 identity metadata from a DB object.
     *
     * @param \stdClass $object
     * @return array
     */
    public static function identity_from_object(\stdClass $object): array {
        $errors = [];
        $details = [];
        $metadata = self::decode_metadata($object->metadatajson ?? [], $errors, $details);
        return self::identity_from_row((array)$object, $metadata);
    }

    /**
     * Validate C3 package content/evidence mapping contract.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package_contract(array $package): array {
        $errors = [];
        $warnings = [];
        $details = [];
        $counts = [
            'learning_objects' => 0,
            'object_maps' => 0,
            'project_evidence' => 0,
        ];

        foreach (($package['learning_objects'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $counts['learning_objects']++;
            self::merge_context_result('learning_objects[' . $index . ']',
                self::validate_learning_object_row($row), $errors, $warnings, $details);
        }

        foreach (($package['lesson_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $counts['learning_objects']++;
            $objectrow = $row;
            $objectrow['externalid'] = $row['object_externalid'] ?? ($row['externalid'] ?? '');
            self::merge_context_result('lesson_mappings[' . $index . ']',
                self::validate_learning_object_row($objectrow), $errors, $warnings, $details);
        }

        foreach (self::package_object_map_rows($package) as $context => $row) {
            $counts['object_maps']++;
            self::merge_context_result($context, self::validate_object_map_row($row), $errors, $warnings, $details);
        }

        foreach (($package['project_evidence'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $counts['project_evidence']++;
            if (!self::has_value($row['object_externalid'] ?? null)) {
                $errors[] = 'project_evidence[' . $index . ']: object_externalid is required; titles are not identity.';
                $details[] = self::detail('project_evidence_missing_object_identity', 'error');
            }
            if (!self::has_value($row['competency_externalid'] ?? ($row['competency_externalids'] ?? null))) {
                $errors[] = 'project_evidence[' . $index . ']: competency_externalid is required.';
                $details[] = self::detail('project_evidence_missing_competency_identity', 'error');
            }
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
     * Read-only status for C3 content/evidence mapping readiness.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function content_mapping_status(int $courseid = 0, string $unitcode = '', int $limit = 100): array {
        global $DB;

        $graph = relationship_graph_contract::graph_status($courseid, 0, min($limit, 100));
        $history = history_v1_consumer_contract::contract_status($courseid, 1);
        $findings = [];
        if (($graph['status'] ?? '') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'c2_not_frozen',
                'message' => 'C3 requires C2 relationship graph semantics to remain frozen.',
            ];
        }
        if (($history['status'] ?? '') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'history_v1_not_ready',
                'message' => 'C3 requires History V1 as the only normal source-history input.',
            ];
        }

        $params = [];
        $where = '1=1';
        if ($courseid > 0) {
            $where .= ' AND (courseid = :courseid OR courseid IS NULL)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }

        $objects = $DB->get_records_select('flwcupkp_object', $where, $params, 'unitcode ASC, lesson ASC, externalid ASC',
            '*', 0, max(1, $limit));
        $rolecounts = array_fill_keys(array_keys(self::PEDAGOGICAL_ROLES), 0);
        $completionallowed = 0;
        $stableidentitycount = 0;
        $mapcount = 0;

        foreach ($objects as $object) {
            $objectresult = self::validate_learning_object_row((array)$object);
            foreach ($objectresult['errors'] as $error) {
                $findings[] = [
                    'severity' => 'error',
                    'code' => 'invalid_learning_object_mapping',
                    'message' => 'object ' . (int)$object->id . ': ' . $error,
                ];
            }
            if (self::identity_has_stable_key(self::identity_from_object($object))) {
                $stableidentitycount++;
            }
            $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => (int)$object->id]);
            foreach ($maps as $map) {
                $mapcount++;
                $mapresult = self::validate_object_map_row((array)$map, (array)$object);
                foreach ($mapresult['errors'] as $error) {
                    $findings[] = [
                        'severity' => 'error',
                        'code' => 'invalid_content_evidence_map',
                        'message' => 'object_map ' . (int)$map->id . ': ' . $error,
                    ];
                }
                try {
                    $role = self::canonical_pedagogical_role((string)$map->role, (string)($object->purpose ?? ''),
                        (string)($object->objecttype ?? ''));
                    $rolecounts[$role]++;
                    if (self::source_can_count('completion', $object, $map)) {
                        $completionallowed++;
                    }
                } catch (\invalid_parameter_exception $e) {
                    // Already reported by validation.
                }
            }
        }

        $contentidentities = self::history_content_identity_summary($courseid, $limit);
        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['blocker', 'error'], true);
        });

        return [
            'type' => 'CupkpContentEvidenceMappingStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'frozen',
            'contract' => self::contract(),
            'dependencies' => [
                'c2' => $graph['status'] ?? null,
                'history_v1' => $history['status'] ?? null,
            ],
            'sample' => [
                'objects' => count($objects),
                'object_maps' => $mapcount,
                'stable_identity_objects' => $stableidentitycount,
                'rolecounts' => $rolecounts,
                'completion_evidence_allowed_maps' => $completionallowed,
                'history_content_identities' => $contentidentities,
            ],
            'findings' => $findings,
        ];
    }

    /**
     * Decide if completion can count as evidence for a role.
     *
     * @param \stdClass $object
     * @param \stdClass $map
     * @param string $role
     * @return bool
     */
    private static function completion_evidence_allowed(\stdClass $object, \stdClass $map, string $role): bool {
        if (in_array($role, ['ASSESSES', 'EVIDENCE_FOR'], true)) {
            return true;
        }
        if ($role !== 'PRACTICES') {
            return false;
        }

        $errors = [];
        $details = [];
        $metadata = self::decode_metadata($object->metadatajson ?? [], $errors, $details);
        $override = self::completion_map_override($metadata, $map);
        if ($override !== null) {
            return $override;
        }
        $explicit = self::boolish_value($metadata['completion_counts_as_evidence'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }
        $purpose = self::normalize_label((string)($object->purpose ?? ''));
        return in_array($purpose, ['practice_evidence', 'performance_evidence', 'integrated_performance'], true);
    }

    /**
     * Read a map-specific completion evidence override from object metadata.
     *
     * @param array $metadata
     * @param \stdClass $map
     * @return bool|null
     */
    private static function completion_map_override(array $metadata, \stdClass $map): ?bool {
        $overrides = $metadata['completion_evidence_map_overrides'] ?? [];
        if (is_object($overrides)) {
            $overrides = (array)$overrides;
        }
        if (!is_array($overrides)) {
            return null;
        }

        $keys = [];
        if (!empty($map->targettype) && !empty($map->targetid)) {
            $keys[] = (string)$map->targettype . ':' . (int)$map->targetid;
        }
        if (!empty($map->id)) {
            $keys[] = 'map:' . (int)$map->id;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $overrides)) {
                return self::boolish_value($overrides[$key]);
            }
        }
        return null;
    }

    /**
     * Build identity payload from object row plus metadata.
     *
     * @param array $row
     * @param array $metadata
     * @return array
     */
    private static function identity_from_row(array $row, array $metadata): array {
        $existing = is_array($metadata['program1_identity'] ?? null) ? $metadata['program1_identity'] : [];
        $identity = [
            'sourcekey' => self::first_value($row, ['program1_sourcekey', 'sourcekey', 'history_sourcekey'], $existing['sourcekey'] ?? null),
            'unitid' => self::first_value($row, ['unit_id', 'unitid', 'unitcode', 'unit_code'], $existing['unitid'] ?? null),
            'lessonid' => self::first_value($row, ['lesson_id', 'lessonid', 'lesson'], $existing['lessonid'] ?? null),
            'componentid' => self::first_value($row, ['component_id', 'componentid'], $existing['componentid'] ?? null),
            'activityid' => self::first_value($row, ['activity_id', 'activityid', 'source_id', 'sourceid'], $existing['activityid'] ?? null),
            'assessmentid' => self::first_value($row, ['assessment_id', 'assessmentid'], $existing['assessmentid'] ?? null),
            'questionid' => self::first_value($row, ['question_id', 'questionid'], $existing['questionid'] ?? null),
            'cmid' => self::first_value($row, ['cmid'], $existing['cmid'] ?? null),
        ];
        if (!self::has_value($identity['activityid'] ?? null) && self::has_value($row['externalid'] ?? null)) {
            $identity['activityid'] = (string)$row['externalid'];
        }
        return array_filter($identity, [self::class, 'has_value']);
    }

    /**
     * Whether identity has at least one stable non-title key.
     *
     * @param array $identity
     * @return bool
     */
    private static function identity_has_stable_key(array $identity): bool {
        foreach (['sourcekey', 'unitid', 'lessonid', 'activityid', 'assessmentid', 'questionid'] as $field) {
            if (self::has_value($identity[$field] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Summarize History V1 content identity payloads without mutating anything.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    private static function history_content_identity_summary(int $courseid, int $limit): array {
        if ($courseid <= 0) {
            return ['available' => false, 'reason' => 'courseid_not_supplied'];
        }
        $adapterclass = '\\local_flwhistory\\local\\evidence_source_adapter';
        if (!class_exists($adapterclass)) {
            return ['available' => false, 'reason' => 'history_adapter_missing'];
        }
        try {
            $payload = $adapterclass::content_identities_for_course($courseid, max(1, min(100, $limit)));
        } catch (\Throwable $e) {
            return ['available' => false, 'reason' => $e->getMessage()];
        }
        $records = $payload['records'] ?? [];
        $resolved = 0;
        $unresolved = 0;
        foreach ($records as $record) {
            if (($record['status'] ?? '') === 'resolved') {
                $resolved++;
            } else {
                $unresolved++;
            }
        }
        return [
            'available' => true,
            'records' => count($records),
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'source' => 'local_flwhistory evidence_source_adapter::content_identities_for_course',
        ];
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
            if (is_array($row)) {
                $rows['activity_mappings[' . $index . ']'] = $row;
            }
        }
        foreach (($package['lesson_mappings'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? ($row['externalid'] ?? ''));
            $role = $row['map_role'] ?? ($row['role'] ?? null);
            $strength = $row['map_evidence_strength'] ?? ($row['evidence_strength'] ?? null);
            if (!empty($row['target_type']) || !empty($row['target_externalid'])) {
                $rows['lesson_mappings[' . $index . ']'] = [
                    'object_externalid' => $objectexternalid,
                    'target_type' => (string)($row['target_type'] ?? ''),
                    'target_externalid' => (string)($row['target_externalid'] ?? ''),
                    'role' => $role,
                    'evidence_strength' => $strength,
                    'completion_counts_as_evidence' => $row['completion_counts_as_evidence'] ?? null,
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
                        'completion_counts_as_evidence' => $row['completion_counts_as_evidence'] ?? null,
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
                    'completion_counts_as_evidence' => $row['completion_counts_as_evidence'] ?? null,
                ];
            }
        }
        return $rows;
    }

    /**
     * Normalize a source type label.
     *
     * @param string $sourcetype
     * @return string
     */
    private static function normalize_source_type(string $sourcetype): string {
        $sourcetype = self::normalize_label($sourcetype);
        $aliases = [
            'attempt' => 'program2_attempt',
            'program_2_attempt' => 'program2_attempt',
            'quiz_attempt' => 'program2_attempt',
            'grade' => 'grade_linked_assessment',
            'assessment_grade' => 'grade_linked_assessment',
            'teacher' => 'teacher_observation',
            'manual' => 'teacher_observation',
            'external' => 'external_assessment',
        ];
        return $aliases[$sourcetype] ?? $sourcetype;
    }

    /**
     * Decode metadata from JSON or array.
     *
     * @param mixed $metadata
     * @param array|null $errors
     * @param array|null $details
     * @return array
     */
    private static function decode_metadata($metadata, ?array &$errors = null, ?array &$details = null): array {
        if (is_array($metadata)) {
            return $metadata;
        }
        if ($metadata instanceof \stdClass) {
            return (array)$metadata;
        }
        if ($metadata === null || $metadata === '') {
            return [];
        }
        $decoded = json_decode((string)$metadata, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if ($errors !== null) {
            $errors[] = 'metadatajson must be valid JSON.';
        }
        if ($details !== null) {
            $details[] = self::detail('invalid_metadata_json', 'error');
        }
        return [];
    }

    /**
     * Read first present row value.
     *
     * @param array $row
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    private static function first_value(array $row, array $keys, $default = null) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && self::has_value($row[$key])) {
                return $row[$key];
            }
        }
        return $default;
    }

    /**
     * Validate bool-like field.
     *
     * @param string $value
     * @param array $errors
     * @param array $details
     */
    private static function boolish(string $value, array &$errors, array &$details): void {
        if (!in_array(self::normalize_label($value), ['1', '0', 'true', 'false', 'yes', 'no', ''], true)) {
            $errors[] = 'completion_counts_as_evidence must be boolean.';
            $details[] = self::detail('invalid_completion_evidence_flag', 'error');
        }
    }

    /**
     * Normalize bool-ish values.
     *
     * @param mixed $value
     * @return bool|null
     */
    private static function boolish_value($value): ?bool {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $value = self::normalize_label((string)$value);
        if (in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }
        return null;
    }

    /**
     * Merge validation result without context.
     *
     * @param array $result
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function merge_result(array $result, array &$errors, array &$warnings, array &$details): void {
        $errors = array_merge($errors, $result['errors'] ?? []);
        $warnings = array_merge($warnings, $result['warnings'] ?? []);
        $details = array_merge($details, $result['details'] ?? []);
    }

    /**
     * Merge validation result with package context.
     *
     * @param string $context
     * @param array $result
     * @param array $errors
     * @param array $warnings
     * @param array $details
     */
    private static function merge_context_result(string $context, array $result, array &$errors, array &$warnings,
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
     * Common validation result shape.
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
     * Normalize labels.
     *
     * @param string $value
     * @return string
     */
    private static function normalize_label(string $value): string {
        return strtolower(trim(str_replace(['-', ' '], '_', $value)));
    }

    /**
     * Whether a value is non-empty.
     *
     * @param mixed $value
     * @return bool
     */
    private static function has_value($value): bool {
        if (is_array($value)) {
            return !empty($value);
        }
        return $value !== null && trim((string)$value) !== '';
    }

    /**
     * Normalize scalar/list value.
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
     * Structured validation detail.
     *
     * @param string $code
     * @param string $severity
     * @param array $extra
     * @return array
     */
    private static function detail(string $code, string $severity, array $extra = []): array {
        return $extra + [
            'code' => $code,
            'severity' => $severity,
            'contract' => self::CONTRACT_VERSION,
        ];
    }
}

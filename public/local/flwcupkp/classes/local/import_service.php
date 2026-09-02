<?php
// Import service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Imports canonical C-UP-KP JSON packages.
 */
class import_service {
    /** @var array CSV import types and required headers. */
    private const CSV_IMPORT_TYPES = [
        'activity_mappings' => [
            'required' => ['object_externalid', 'target_type', 'target_externalid'],
            'optional' => ['role', 'evidence_strength', 'completion_counts_as_evidence'],
        ],
        'quiz_kp_mappings' => [
            'required' => ['item_id', 'object_externalid', 'kp_externalid'],
            'optional' => ['evidence_strength', 'notes'],
        ],
    ];

    /**
     * Validate and import a JSON package.
     *
     * @param string $json
     * @param string $sourcefile
     * @return array
     */
    public static function import_json(string $json, string $sourcefile = ''): array {
        global $DB, $USER;

        $package = json_decode($json, true);
        if (!is_array($package)) {
            throw new \invalid_parameter_exception('Invalid JSON package');
        }

        $validation = validator::validate_package($package);
        $checksum = hash('sha256', $json);

        if ($existing = $DB->get_record('flwcupkp_import', ['checksum' => $checksum], '*', IGNORE_MISSING)) {
            return [
                'importid' => (int)$existing->id,
                'status' => 'already_imported',
                'validation' => $validation,
            ];
        }

        $transaction = $DB->start_delegated_transaction();
        $entitycount = 0;

        $importid = $DB->insert_record('flwcupkp_import', (object)[
            'sourcefile' => $sourcefile,
            'schemaversion' => $package['cupkp_schema_version'] ?? 'unknown',
            'checksum' => $checksum,
            'validationstatus' => $validation['valid'] ? 'valid' : 'invalid',
            'warningsjson' => json_encode($validation['warnings']),
            'errorsjson' => json_encode($validation['errors']),
            'entitycount' => 0,
            'rollbackstatus' => 'not_rolled_back',
            'userid' => $USER->id ?? 0,
            'timecreated' => time(),
        ]);

        if (!$validation['valid']) {
            $transaction->allow_commit();
            return ['importid' => (int)$importid, 'status' => 'invalid', 'validation' => $validation];
        }

        $frameworkids = self::import_frameworks($package['frameworks'] ?? []);
        $frameworkid = reset($frameworkids) ?: null;

        $lessonpackage = self::normalize_lesson_mappings($package['lesson_mappings'] ?? []);
        $projectmappings = self::normalize_project_competency_mappings($package['project_competency_mappings'] ?? []);

        $competencyids = self::import_entities('competencies', $package['competencies'] ?? [], $frameworkid);
        $upids = self::import_entities('use_points', $package['use_points'] ?? [], $frameworkid);
        $kpids = self::import_entities('knowledge_points', $package['knowledge_points'] ?? [], $frameworkid);
        $learningobjects = array_merge($package['learning_objects'] ?? [], $lessonpackage['learning_objects']);
        $objectids = self::import_entities('learning_objects', $learningobjects, $frameworkid);

        $entitycount += count($frameworkids) + count($competencyids) + count($upids) + count($kpids) + count($objectids);
        $entitycount += self::import_comp_up($package['competency_up_mappings'] ?? [], $competencyids, $upids);
        $entitycount += self::import_up_kp($package['up_kp_mappings'] ?? [], $upids, $kpids);
        $entitycount += self::import_prereqs($package['kp_prerequisites'] ?? [], $kpids);
        $activitymappings = array_merge(
            $package['activity_mappings'] ?? [],
            $lessonpackage['activity_mappings'],
            $projectmappings
        );
        $entitycount += self::import_object_maps($activitymappings, $objectids, $competencyids, $upids, $kpids);
        $entitycount += self::import_rules($package['assessment_rules'] ?? []);

        $DB->set_field('flwcupkp_import', 'entitycount', $entitycount, ['id' => $importid]);
        repository::audit('package_imported', 'import', (int)$importid, ['sourcefile' => $sourcefile, 'entitycount' => $entitycount]);
        $transaction->allow_commit();

        return [
            'importid' => (int)$importid,
            'status' => 'imported',
            'entitycount' => $entitycount,
            'validation' => $validation,
        ];
    }

    /**
     * Validate a supported C-UP-KP CSV artifact without importing it.
     *
     * @param string $csv
     * @param string $type
     * @return array
     */
    public static function validate_csv(string $csv, string $type = 'activity_mappings'): array {
        $type = self::normalize_csv_type($type);
        return self::validate_csv_rows(self::parse_csv($csv), $type);
    }

    /**
     * Validate and import a supported C-UP-KP CSV artifact.
     *
     * Supported CSV types match the shipped templates:
     * - activity_mappings: object_externalid,target_type,target_externalid,role,evidence_strength
     * - quiz_kp_mappings: item_id,object_externalid,kp_externalid,evidence_strength,notes
     *
     * @param string $csv
     * @param string $type
     * @param string $sourcefile
     * @return array
     */
    public static function import_csv(string $csv, string $type = 'activity_mappings', string $sourcefile = ''): array {
        global $DB, $USER;

        $type = self::normalize_csv_type($type);
        $parsed = self::parse_csv($csv);
        $validation = self::validate_csv_rows($parsed, $type);
        $checksum = hash('sha256', 'csv:' . $type . "\n" . self::csv_checksum_payload($parsed));

        if ($existing = $DB->get_record('flwcupkp_import', ['checksum' => $checksum], '*', IGNORE_MISSING)) {
            return [
                'importid' => (int)$existing->id,
                'status' => 'already_imported',
                'validation' => $validation,
            ];
        }

        $transaction = $DB->start_delegated_transaction();
        $importid = $DB->insert_record('flwcupkp_import', (object)[
            'sourcefile' => $sourcefile,
            'schemaversion' => 'csv-' . $type,
            'checksum' => $checksum,
            'validationstatus' => $validation['valid'] ? 'valid' : 'invalid',
            'warningsjson' => json_encode($validation['warnings']),
            'errorsjson' => json_encode($validation['errors']),
            'entitycount' => 0,
            'rollbackstatus' => 'not_rolled_back',
            'userid' => $USER->id ?? 0,
            'timecreated' => time(),
        ]);

        if (!$validation['valid']) {
            $transaction->allow_commit();
            return ['importid' => (int)$importid, 'status' => 'invalid', 'validation' => $validation];
        }

        $entitycount = $type === 'quiz_kp_mappings' ?
            self::import_quiz_kp_csv_rows($parsed['rows']) :
            self::import_activity_mapping_csv_rows($parsed['rows']);

        $DB->set_field('flwcupkp_import', 'entitycount', $entitycount, ['id' => $importid]);
        repository::audit('csv_imported', 'import', (int)$importid, [
            'sourcefile' => $sourcefile,
            'csvtype' => $type,
            'entitycount' => $entitycount,
        ]);
        $transaction->allow_commit();

        return [
            'importid' => (int)$importid,
            'status' => 'imported',
            'entitycount' => $entitycount,
            'validation' => $validation,
        ];
    }

    /**
     * Normalize lesson_cupkp_map-style rows into learning objects and activity mappings.
     *
     * @param array $rows
     * @return array
     */
    private static function normalize_lesson_mappings(array $rows): array {
        $objects = [];
        $mappings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? $row['externalid'] ?? '');
            if ($objectexternalid === '') {
                continue;
            }
            $objects[] = [
                'externalid' => $objectexternalid,
                'title' => $row['title'] ?? $objectexternalid,
                'unit_code' => $row['unit_code'] ?? ($row['unitcode'] ?? null),
                'lesson' => $row['lesson'] ?? null,
                'object_type' => $row['object_type'] ?? ($row['objecttype'] ?? 'lesson'),
                'source_id' => $row['source_id'] ?? ($row['sourceid'] ?? null),
                'program1_sourcekey' => $row['program1_sourcekey'] ?? ($row['sourcekey'] ?? null),
                'unit_id' => $row['unit_id'] ?? ($row['unitid'] ?? null),
                'lesson_id' => $row['lesson_id'] ?? ($row['lessonid'] ?? null),
                'component_id' => $row['component_id'] ?? ($row['componentid'] ?? null),
                'activity_id' => $row['activity_id'] ?? ($row['activityid'] ?? null),
                'assessment_id' => $row['assessment_id'] ?? ($row['assessmentid'] ?? null),
                'question_id' => $row['question_id'] ?? ($row['questionid'] ?? null),
                'purpose' => $row['purpose'] ?? 'lesson',
                'evidence_strength' => $row['evidence_strength'] ?? null,
                'difficulty' => $row['difficulty'] ?? null,
                'role' => $row['role'] ?? 'practice',
                'completion_counts_as_evidence' => $row['completion_counts_as_evidence'] ?? null,
                'metadata' => $row['metadata'] ?? ['imported_from' => 'lesson_mappings'],
            ];

            $role = (string)($row['map_role'] ?? ($row['role'] ?? 'practice'));
            $strength = isset($row['map_evidence_strength']) ?
                (string)$row['map_evidence_strength'] :
                (isset($row['evidence_strength']) ? (string)$row['evidence_strength'] : null);

            if (!empty($row['target_type']) && !empty($row['target_externalid'])) {
                $mappings[] = [
                    'object_externalid' => $objectexternalid,
                    'target_type' => (string)$row['target_type'],
                    'target_externalid' => (string)$row['target_externalid'],
                    'role' => $role,
                    'evidence_strength' => $strength,
                    'completion_counts_as_evidence' => $row['completion_counts_as_evidence'] ?? null,
                ];
            }
            self::add_lesson_targets($mappings, $objectexternalid, 'kp',
                $row['kp_externalids'] ?? ($row['kp_externalid'] ?? null), $role, $strength,
                $row['completion_counts_as_evidence'] ?? null);
            self::add_lesson_targets($mappings, $objectexternalid, 'up',
                $row['up_externalids'] ?? ($row['up_externalid'] ?? null), $role, $strength,
                $row['completion_counts_as_evidence'] ?? null);
            self::add_lesson_targets($mappings, $objectexternalid, 'competency',
                $row['competency_externalids'] ?? ($row['competency_externalid'] ?? null), $role, $strength,
                $row['completion_counts_as_evidence'] ?? null);
        }
        return ['learning_objects' => $objects, 'activity_mappings' => $mappings];
    }

    /**
     * Normalize project_competency_mapping-style rows into activity mappings.
     *
     * @param array $rows
     * @return array
     */
    private static function normalize_project_competency_mappings(array $rows): array {
        $mappings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $objectexternalid = (string)($row['object_externalid'] ?? $row['externalid'] ?? '');
            $competencies = $row['competency_externalids'] ?? ($row['competency_externalid'] ?? null);
            self::add_lesson_targets($mappings, $objectexternalid, 'competency', $competencies,
                (string)($row['role'] ?? 'assessment'),
                isset($row['evidence_strength']) ? (string)$row['evidence_strength'] : 'independent_performance',
                $row['completion_counts_as_evidence'] ?? null);
        }
        return $mappings;
    }

    /**
     * Add one or more target mappings from scalar or array external IDs.
     *
     * @param array $mappings
     * @param string $objectexternalid
     * @param string $targettype
     * @param mixed $values
     * @param string $role
     * @param string|null $strength
     */
    private static function add_lesson_targets(array &$mappings, string $objectexternalid, string $targettype,
            $values, string $role, ?string $strength, $completioncounts = null): void {
        if ($objectexternalid === '' || $values === null || $values === '') {
            return;
        }
        foreach ((array)$values as $targetexternalid) {
            $targetexternalid = trim((string)$targetexternalid);
            if ($targetexternalid === '') {
                continue;
            }
            $mappings[] = [
                'object_externalid' => $objectexternalid,
                'target_type' => $targettype,
                'target_externalid' => $targetexternalid,
                'role' => $role,
                'evidence_strength' => $strength,
                'completion_counts_as_evidence' => $completioncounts,
            ];
        }
    }

    /**
     * Import frameworks.
     *
     * @param array $rows
     * @return array
     */
    private static function import_frameworks(array $rows): array {
        global $DB;

        $ids = [];
        foreach ($rows as $row) {
            $record = (object)[
                'externalid' => $row['externalid'],
                'name' => $row['name'] ?? $row['externalid'],
                'courseid' => $row['courseid'] ?? null,
                'coursecode' => $row['course_code'] ?? null,
                'language' => $row['language'] ?? null,
                'cefrrange' => $row['cefr_range'] ?? null,
                'version' => $row['version'] ?? '1.0',
                'status' => $row['status'] ?? 'draft',
                'description' => $row['description'] ?? null,
                'parentid' => null,
                'moodleframeworkid' => $row['moodle_framework_id'] ?? null,
            ];
            $record = (object)lifecycle_governance_contract::normalize_entity_payload('framework', (array)$record);
            $existing = $DB->get_record('flwcupkp_framework', ['externalid' => $record->externalid],
                '*', IGNORE_MISSING) ?: null;
            lifecycle_governance_contract::assert_entity_write('framework', (array)$record, $existing);
            $ids[$row['externalid']] = repository::upsert_by_externalid('flwcupkp_framework', $record);
        }
        return $ids;
    }

    /**
     * Import entity rows.
     *
     * @param string $key
     * @param array $rows
     * @param int|null $frameworkid
     * @return array
     */
    private static function import_entities(string $key, array $rows, ?int $frameworkid): array {
        global $DB;

        $ids = [];
        $table = repository::table_for_entity($key);
        $entitytype = lifecycle_governance_contract::entity_type_for_table($table);
        foreach ($rows as $row) {
            if ($table === 'flwcupkp_object') {
                content_evidence_mapping_contract::assert_learning_object_row($row);
            }
            $record = self::record_for_table($table, $row, $frameworkid);
            $record = (object)lifecycle_governance_contract::normalize_entity_payload($entitytype, (array)$record);
            $existing = $DB->get_record($table, ['externalid' => $record->externalid], '*', IGNORE_MISSING) ?: null;
            lifecycle_governance_contract::assert_entity_write($entitytype, (array)$record, $existing);
            $ids[$row['externalid']] = repository::upsert_by_externalid($table, $record);
        }
        return $ids;
    }

    /**
     * Normalize package row to table record.
     *
     * @param string $table
     * @param array $row
     * @param int|null $frameworkid
     * @return \stdClass
     */
    private static function record_for_table(string $table, array $row, ?int $frameworkid): \stdClass {
        $record = (object)$row;
        $record->frameworkid = $row['frameworkid'] ?? $frameworkid;
        if ($table === 'flwcupkp_comp') {
            $record->cando = $row['can_do'] ?? ($row['cando'] ?? null);
            $record->evidencerule = json_encode($row['evidence_rule'] ?? []);
            $record->moodlecompetencyid = $row['moodle_competency_id'] ?? null;
        } else if ($table === 'flwcupkp_up') {
            $record->actionstatement = $row['action_statement'] ?? null;
            $record->observableaction = $row['observable_action'] ?? null;
            $record->successcriteria = $row['success_criteria'] ?? null;
            $record->languagemode = $row['language_mode'] ?? null;
            $record->interactiontype = $row['interaction_type'] ?? null;
            $record->evidencerequirements = json_encode($row['evidence_requirements'] ?? []);
            $record->rubricref = $row['rubric_ref'] ?? null;
        } else if ($table === 'flwcupkp_kp') {
            $record->formtext = $row['form'] ?? null;
            $record->meaningfunction = $row['meaning_function'] ?? null;
            $record->usageconstraints = $row['usage_constraints'] ?? null;
            $record->learningload = $row['estimated_learning_load'] ?? null;
            $record->evidencerequirements = json_encode($row['evidence_requirements'] ?? []);
        } else if ($table === 'flwcupkp_object') {
            $record->courseid = $row['courseid'] ?? ($row['course_id'] ?? null);
            $record->unitcode = $row['unit_code'] ?? ($row['unitcode'] ?? null);
            $record->objecttype = $row['object_type'] ?? ($row['objecttype'] ?? null);
            $record->sourceid = $row['source_id'] ?? ($row['sourceid'] ??
                ($row['activity_id'] ?? ($row['program1_sourcekey'] ?? ($row['sourcekey'] ?? null))));
            $record->evidencestrength = $row['evidence_strength'] ?? null;
            $metadata = content_evidence_mapping_contract::normalize_object_metadata_from_row(
                $row,
                $row['metadata'] ?? ($row['metadatajson'] ?? [])
            );
            $record->metadatajson = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        }
        return self::filter_record($record, $table);
    }

    /**
     * Remove fields not present in the table.
     *
     * @param \stdClass $record
     * @param string $table
     * @return \stdClass
     */
    private static function filter_record(\stdClass $record, string $table): \stdClass {
        $allowed = [
            'flwcupkp_comp' => ['frameworkid', 'externalid', 'title', 'cando', 'description', 'cefr', 'stage', 'domain', 'scope', 'evidencerule', 'moodlecompetencyid', 'status', 'version', 'validfrom', 'validto'],
            'flwcupkp_up' => ['frameworkid', 'externalid', 'title', 'actionstatement', 'intention', 'context', 'observableaction', 'conditions', 'successcriteria', 'cefr', 'languagemode', 'interactiontype', 'evidencerequirements', 'rubricref', 'status', 'version'],
            'flwcupkp_kp' => ['frameworkid', 'externalid', 'title', 'description', 'language', 'cefr', 'domain', 'formtext', 'meaningfunction', 'usageconstraints', 'difficulty', 'learningload', 'evidencerequirements', 'status', 'version'],
            'flwcupkp_object' => ['frameworkid', 'externalid', 'courseid', 'unitcode', 'lesson', 'objecttype', 'title', 'cmid', 'sourceid', 'purpose', 'evidencestrength', 'difficulty', 'role', 'metadatajson'],
        ];
        $out = new \stdClass();
        foreach ($allowed[$table] as $field) {
            if (property_exists($record, $field)) {
                $out->{$field} = $record->{$field};
            }
        }
        return $out;
    }

    /**
     * Import competency-UP mappings.
     */
    private static function import_comp_up(array $rows, array $competencyids, array $upids): int {
        $count = 0;
        foreach ($rows as $row) {
            $competencyid = $competencyids[$row['competency_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_comp', (string)$row['competency_externalid']);
            $upid = $upids[$row['up_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_up', (string)$row['up_externalid']);
            if (!$competencyid || !$upid) {
                continue;
            }
            $record = (object)[
                'competencyid' => $competencyid,
                'upid' => $upid,
                'role' => $row['role'] ?? 'required',
                'weight' => $row['weight'] ?? 1,
                'sortorder' => $row['sequence'] ?? 0,
                'minmastery' => $row['minimum_up_mastery'] ?? null,
                'evidencerule' => json_encode($row['evidence_rule'] ?? []),
                'notes' => $row['notes'] ?? null,
            ];
            relationship_graph_contract::assert_mapping_change('comp_up', (array)$record);
            lifecycle_governance_contract::assert_mapping_change('comp_up', (array)$record);
            repository::upsert_mapping('flwcupkp_comp_up', ['competencyid' => $competencyid, 'upid' => $upid], $record);
            $count++;
        }
        return $count;
    }

    /**
     * Import UP-KP mappings.
     */
    private static function import_up_kp(array $rows, array $upids, array $kpids): int {
        $count = 0;
        foreach ($rows as $row) {
            $upid = $upids[$row['up_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_up', (string)$row['up_externalid']);
            $kpid = $kpids[$row['kp_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_kp', (string)$row['kp_externalid']);
            if (!$upid || !$kpid) {
                continue;
            }
            $record = (object)[
                'upid' => $upid,
                'kpid' => $kpid,
                'role' => $row['role'] ?? 'required',
                'weight' => $row['weight'] ?? 1,
                'minreadiness' => $row['minimum_kp_readiness'] ?? null,
                'sortorder' => $row['sequence'] ?? 0,
                'notes' => $row['notes'] ?? null,
            ];
            relationship_graph_contract::assert_mapping_change('up_kp', (array)$record);
            lifecycle_governance_contract::assert_mapping_change('up_kp', (array)$record);
            repository::upsert_mapping('flwcupkp_up_kp', ['upid' => $upid, 'kpid' => $kpid], $record);
            $count++;
        }
        return $count;
    }

    /**
     * Import KP prerequisites.
     */
    private static function import_prereqs(array $rows, array $kpids): int {
        $count = 0;
        foreach ($rows as $row) {
            $kpid = $kpids[$row['kp_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_kp', (string)$row['kp_externalid']);
            $prereq = $kpids[$row['prereq_kp_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_kp', (string)$row['prereq_kp_externalid']);
            if (!$kpid || !$prereq) {
                continue;
            }
            $record = (object)[
                'kpid' => $kpid,
                'prereqkpid' => $prereq,
                'relationshiptype' => $row['relationship_type'] ?? 'prerequisite',
                'strength' => $row['strength'] ?? 1,
                'requirement' => $row['requirement'] ?? 'recommended',
                'notes' => $row['notes'] ?? null,
            ];
            relationship_graph_contract::assert_mapping_change('kp_prereq', (array)$record);
            lifecycle_governance_contract::assert_mapping_change('kp_prereq', (array)$record);
            repository::upsert_mapping('flwcupkp_kp_prereq', ['kpid' => $kpid, 'prereqkpid' => $prereq], $record);
            $count++;
        }
        return $count;
    }

    /**
     * Import object mappings.
     */
    private static function import_object_maps(array $rows, array $objectids, array $competencyids, array $upids, array $kpids): int {
        global $DB;

        $count = 0;
        foreach ($rows as $row) {
            $objectid = $objectids[$row['object_externalid']] ??
                repository::get_id_by_externalid('flwcupkp_object', (string)$row['object_externalid']);
            $targettype = $row['target_type'] ?? '';
            $targetexternal = $row['target_externalid'] ?? '';
            $targetid = $targettype === 'competency' ?
                ($competencyids[$targetexternal] ?? repository::get_id_by_externalid('flwcupkp_comp', (string)$targetexternal)) :
                ($targettype === 'up' ?
                    ($upids[$targetexternal] ?? repository::get_id_by_externalid('flwcupkp_up', (string)$targetexternal)) :
                    ($kpids[$targetexternal] ?? repository::get_id_by_externalid('flwcupkp_kp', (string)$targetexternal)));
            if (!$objectid || !$targetid) {
                continue;
            }
            $record = (object)[
                'objectid' => $objectid,
                'targettype' => $targettype,
                'targetid' => $targetid,
                'role' => $row['role'] ?? 'practice',
                'evidencestrength' => $row['evidence_strength'] ?? null,
            ];
            relationship_graph_contract::assert_mapping_change('object_map', (array)$record);
            lifecycle_governance_contract::assert_mapping_change('object_map', (array)$record);
            $object = $DB->get_record('flwcupkp_object', ['id' => (int)$objectid], '*', MUST_EXIST);
            content_evidence_mapping_contract::assert_object_map_contract($object, $record);
            repository::upsert_mapping('flwcupkp_object_map',
                ['objectid' => $objectid, 'targettype' => $targettype, 'targetid' => $targetid], $record);
            self::set_completion_map_override((int)$objectid, (string)$targettype, (int)$targetid,
                $row['completion_counts_as_evidence'] ?? null);
            $count++;
        }
        return $count;
    }

    /**
     * Import activity mapping CSV rows.
     *
     * @param array $rows
     * @return int
     */
    private static function import_activity_mapping_csv_rows(array $rows): int {
        $count = 0;
        foreach ($rows as $row) {
            $object = self::object_by_externalid((string)$row['object_externalid']);
            $targettype = self::normalize_target_type((string)$row['target_type']);
            $target = self::target_by_externalid($targettype, (string)$row['target_externalid']);
            $record = (object)[
                'objectid' => (int)$object->id,
                'targettype' => $targettype,
                'targetid' => (int)$target->id,
                'role' => self::csv_value($row, 'role', 'practice'),
                'evidencestrength' => self::csv_value($row, 'evidence_strength', null),
            ];
            relationship_graph_contract::assert_mapping_change('object_map', (array)$record);
            lifecycle_governance_contract::assert_mapping_change('object_map', (array)$record);
            content_evidence_mapping_contract::assert_object_map_contract($object, $record);
            repository::upsert_mapping('flwcupkp_object_map', [
                'objectid' => (int)$object->id,
                'targettype' => $targettype,
                'targetid' => (int)$target->id,
            ], $record);
            self::set_completion_map_override((int)$object->id, $targettype, (int)$target->id,
                $row['completion_counts_as_evidence'] ?? null);
            $count++;
        }
        return $count;
    }

    /**
     * Persist map-specific completion evidence intent in object metadata.
     *
     * @param int $objectid
     * @param string $targettype
     * @param int $targetid
     * @param mixed $value
     */
    private static function set_completion_map_override(int $objectid, string $targettype, int $targetid, $value): void {
        global $DB;

        if (!self::csv_boolish_true($value)) {
            return;
        }

        $object = $DB->get_record('flwcupkp_object', ['id' => $objectid], '*', MUST_EXIST);
        $metadata = json_decode((string)($object->metadatajson ?? ''), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $metadata['completion_evidence_map_overrides'][$targettype . ':' . $targetid] = true;
        $metadata['content_evidence_mapping_contract'] =
            content_evidence_mapping_contract::CONTRACT_VERSION;
        $metadata['source_history_contract'] = history_v1_consumer_contract::REQUIRED_CONTRACT;
        $DB->set_field('flwcupkp_object', 'metadatajson',
            json_encode($metadata, JSON_UNESCAPED_SLASHES), ['id' => $objectid]);
    }

    /**
     * Import quiz-question-to-KP CSV rows.
     *
     * The Moodle evidence adapter records quiz attempts at the mapped activity level. The per-item CSV
     * therefore both ensures an object-to-KP evidence map and preserves the item-level trace in object metadata.
     *
     * @param array $rows
     * @return int
     */
    private static function import_quiz_kp_csv_rows(array $rows): int {
        global $DB;

        $count = 0;
        $metadataupdates = [];
        foreach ($rows as $row) {
            $object = self::object_by_externalid((string)$row['object_externalid']);
            $kp = self::target_by_externalid('kp', (string)$row['kp_externalid']);
            $strength = self::csv_value($row, 'evidence_strength', 'recognition');

            $record = (object)[
                'objectid' => (int)$object->id,
                'targettype' => 'kp',
                'targetid' => (int)$kp->id,
                'role' => 'assessment',
                'evidencestrength' => $strength,
            ];
            relationship_graph_contract::assert_mapping_change('object_map', (array)$record);
            lifecycle_governance_contract::assert_mapping_change('object_map', (array)$record);
            content_evidence_mapping_contract::assert_object_map_contract($object, $record);
            repository::upsert_mapping('flwcupkp_object_map', [
                'objectid' => (int)$object->id,
                'targettype' => 'kp',
                'targetid' => (int)$kp->id,
            ], $record);

            $metadataupdates[(int)$object->id][] = [
                'item_id' => (string)$row['item_id'],
                'kp_externalid' => (string)$row['kp_externalid'],
                'evidence_strength' => $strength,
                'notes' => self::csv_value($row, 'notes', ''),
            ];
            $count++;
        }

        foreach ($metadataupdates as $objectid => $items) {
            $object = $DB->get_record('flwcupkp_object', ['id' => $objectid], '*', MUST_EXIST);
            $metadata = json_decode((string)($object->metadatajson ?? ''), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }
            $existing = [];
            foreach (($metadata['quiz_kp_mappings'] ?? []) as $item) {
                if (is_array($item) && !empty($item['item_id']) && !empty($item['kp_externalid'])) {
                    $existing[(string)$item['item_id'] . '|' . (string)$item['kp_externalid']] = $item;
                }
            }
            foreach ($items as $item) {
                $existing[$item['item_id'] . '|' . $item['kp_externalid']] = $item;
            }
            ksort($existing);
            $metadata['quiz_kp_mappings'] = array_values($existing);
            $DB->set_field('flwcupkp_object', 'metadatajson',
                json_encode($metadata, JSON_UNESCAPED_SLASHES), ['id' => $objectid]);
        }

        return $count;
    }

    /**
     * Parse CSV into normalized header/value rows.
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
            if (count($values) > count($headers)) {
                $errors[] = "CSV row {$rownumber} has more values than headers.";
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
     * Validate parsed CSV rows.
     *
     * @param array $parsed
     * @param string $type
     * @return array
     */
    private static function validate_csv_rows(array $parsed, string $type): array {
        $errors = $parsed['errors'] ?? [];
        $warnings = [];
        $config = self::CSV_IMPORT_TYPES[$type];

        foreach ($config['required'] as $header) {
            if (!in_array($header, $parsed['headers'] ?? [], true)) {
                $errors[] = "Missing required CSV header: {$header}";
            }
        }
        if (empty($parsed['rows'])) {
            $errors[] = 'CSV contains no data rows.';
        }

        $seen = [];
        foreach ($parsed['rows'] ?? [] as $row) {
            $rownumber = (int)($row['_rownum'] ?? 0);
            foreach ($config['required'] as $field) {
                if (!isset($row[$field]) || trim((string)$row[$field]) === '') {
                    $errors[] = "CSV row {$rownumber} missing required value: {$field}";
                }
            }
            $key = $type === 'quiz_kp_mappings' ?
                (($row['object_externalid'] ?? '') . '|' . ($row['item_id'] ?? '') . '|' . ($row['kp_externalid'] ?? '')) :
                (($row['object_externalid'] ?? '') . '|' . ($row['target_type'] ?? '') . '|' . ($row['target_externalid'] ?? ''));
            if ($key !== '||' && isset($seen[$key])) {
                $warnings[] = "Duplicate CSV mapping at row {$rownumber}; later values overwrite earlier equivalent mappings.";
            }
            $seen[$key] = true;

            $ontologyrow = $type === 'quiz_kp_mappings' ? [
                'object_externalid' => $row['object_externalid'] ?? '',
                'target_type' => 'kp',
                'target_externalid' => $row['kp_externalid'] ?? '',
                'role' => 'assessment',
                'evidence_strength' => $row['evidence_strength'] ?? 'recognition',
            ] : [
                'object_externalid' => $row['object_externalid'] ?? '',
                'target_type' => $row['target_type'] ?? '',
                'target_externalid' => $row['target_externalid'] ?? '',
                'role' => $row['role'] ?? '',
                'evidence_strength' => $row['evidence_strength'] ?? '',
                'completion_counts_as_evidence' => $row['completion_counts_as_evidence'] ?? null,
            ];
            $ontology = ontology_boundary::validate_mapping_row('object_map', $ontologyrow);
            foreach ($ontology['errors'] as $error) {
                $errors[] = "CSV row {$rownumber}: " . $error;
            }
            $graph = relationship_graph_contract::validate_mapping_row('object_map', $ontologyrow);
            foreach ($graph['errors'] as $error) {
                $errors[] = "CSV row {$rownumber}: " . $error;
            }
            $contentmap = content_evidence_mapping_contract::validate_object_map_row($ontologyrow);
            foreach ($contentmap['errors'] as $error) {
                $errors[] = "CSV row {$rownumber}: " . $error;
            }
            foreach ($contentmap['warnings'] as $warning) {
                $warnings[] = "CSV row {$rownumber}: " . $warning;
            }
        }

        if (empty($errors)) {
            self::validate_csv_references($parsed['rows'], $type, $errors);
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate DB references for parsed CSV rows.
     *
     * @param array $rows
     * @param string $type
     * @param array $errors
     */
    private static function validate_csv_references(array $rows, string $type, array &$errors): void {
        foreach ($rows as $row) {
            $rownumber = (int)($row['_rownum'] ?? 0);
            try {
                $object = self::object_by_externalid((string)$row['object_externalid']);
                if ($type === 'quiz_kp_mappings') {
                    $target = self::target_by_externalid('kp', (string)$row['kp_externalid']);
                    self::assert_same_framework_for_csv($object, $target);
                    lifecycle_governance_contract::assert_mapping_change('object_map', [
                        'objectid' => (int)$object->id,
                        'targettype' => 'kp',
                        'targetid' => (int)$target->id,
                        'role' => 'assessment',
                        'evidencestrength' => $row['evidence_strength'] ?? 'recognition',
                    ]);
                    continue;
                }
                $targettype = self::normalize_target_type((string)$row['target_type']);
                $target = self::target_by_externalid($targettype, (string)$row['target_externalid']);
                self::assert_same_framework_for_csv($object, $target);
                lifecycle_governance_contract::assert_mapping_change('object_map', [
                    'objectid' => (int)$object->id,
                    'targettype' => $targettype,
                    'targetid' => (int)$target->id,
                    'role' => self::csv_value($row, 'role', 'practice'),
                    'evidencestrength' => self::csv_value($row, 'evidence_strength', null),
                ]);
            } catch (\Exception $e) {
                $errors[] = "CSV row {$rownumber}: " . $e->getMessage();
            }
        }
    }

    /**
     * Normalize supported CSV type aliases.
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
        if (!isset(self::CSV_IMPORT_TYPES[$type])) {
            throw new \invalid_parameter_exception('Unsupported C-UP-KP CSV import type.');
        }
        return $type;
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
     * Normalize target type aliases.
     *
     * @param string $targettype
     * @return string
     */
    private static function normalize_target_type(string $targettype): string {
        $targettype = strtolower(trim(str_replace('-', '_', $targettype)));
        $aliases = [
            'comp' => 'competency',
            'competencies' => 'competency',
            'use_point' => 'up',
            'usepoint' => 'up',
            'knowledge_point' => 'kp',
            'knowledgepoint' => 'kp',
        ];
        $targettype = $aliases[$targettype] ?? $targettype;
        evidence_guard::target_table($targettype);
        return $targettype;
    }

    /**
     * Fetch object by external ID.
     *
     * @param string $externalid
     * @return \stdClass
     */
    private static function object_by_externalid(string $externalid): \stdClass {
        global $DB;

        $record = $DB->get_record('flwcupkp_object', ['externalid' => $externalid], '*', IGNORE_MISSING);
        if (!$record) {
            throw new \invalid_parameter_exception('Learning object not found: ' . $externalid);
        }
        return $record;
    }

    /**
     * Fetch C-UP-KP target by external ID.
     *
     * @param string $targettype
     * @param string $externalid
     * @return \stdClass
     */
    private static function target_by_externalid(string $targettype, string $externalid): \stdClass {
        global $DB;

        $table = evidence_guard::target_table($targettype);
        $record = $DB->get_record($table, ['externalid' => $externalid], '*', IGNORE_MISSING);
        if (!$record) {
            throw new \invalid_parameter_exception('Target not found: ' . $externalid);
        }
        return $record;
    }

    /**
     * Ensure a CSV object/target pair belongs to the same framework.
     *
     * @param \stdClass $object
     * @param \stdClass $target
     */
    private static function assert_same_framework_for_csv(\stdClass $object, \stdClass $target): void {
        if ((int)$object->frameworkid !== (int)$target->frameworkid) {
            throw new \invalid_parameter_exception('Object and target frameworks differ.');
        }
    }

    /**
     * Read a non-empty CSV value with fallback.
     *
     * @param array $row
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function csv_value(array $row, string $key, $default) {
        return isset($row[$key]) && trim((string)$row[$key]) !== '' ? trim((string)$row[$key]) : $default;
    }

    /**
     * True-like import value.
     *
     * @param mixed $value
     * @return bool
     */
    private static function csv_boolish_true($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * Produce deterministic payload for CSV checksum.
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
     * Import package assessment rules as configurable records.
     *
     * @param array $rows
     * @return int
     */
    private static function import_rules(array $rows): int {
        global $DB;

        $count = 0;
        $now = time();
        foreach ($rows as $row) {
            $version = $row['rule_id'] ?? ('imported-' . sha1(json_encode($row)));
            $record = (object)[
                'ruletype' => $row['target_type'] ?? 'assessment',
                'name' => $row['rule_id'] ?? 'Imported assessment rule',
                'version' => $version,
                'configjson' => json_encode($row),
                'status' => 'active',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            if ($existing = $DB->get_record('flwcupkp_rule', ['ruletype' => $record->ruletype, 'version' => $record->version], IGNORE_MISSING)) {
                $record->id = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('flwcupkp_rule', $record);
            } else {
                $DB->insert_record('flwcupkp_rule', $record);
            }
            $count++;
        }
        return $count;
    }
}

<?php
// Import service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Imports canonical C-UP-KP JSON packages.
 */
class import_service {
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

        if ($existing = $DB->get_record('flwcupkp_import', ['checksum' => $checksum], IGNORE_MISSING)) {
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

        $competencyids = self::import_entities('competencies', $package['competencies'] ?? [], $frameworkid);
        $upids = self::import_entities('use_points', $package['use_points'] ?? [], $frameworkid);
        $kpids = self::import_entities('knowledge_points', $package['knowledge_points'] ?? [], $frameworkid);
        $objectids = self::import_entities('learning_objects', $package['learning_objects'] ?? [], $frameworkid);

        $entitycount += count($frameworkids) + count($competencyids) + count($upids) + count($kpids) + count($objectids);
        $entitycount += self::import_comp_up($package['competency_up_mappings'] ?? [], $competencyids, $upids);
        $entitycount += self::import_up_kp($package['up_kp_mappings'] ?? [], $upids, $kpids);
        $entitycount += self::import_prereqs($package['kp_prerequisites'] ?? [], $kpids);
        $entitycount += self::import_object_maps($package['activity_mappings'] ?? [], $objectids, $competencyids, $upids, $kpids);
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
     * Import frameworks.
     *
     * @param array $rows
     * @return array
     */
    private static function import_frameworks(array $rows): array {
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
        $ids = [];
        $table = repository::table_for_entity($key);
        foreach ($rows as $row) {
            $record = self::record_for_table($table, $row, $frameworkid);
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
            $record->courseid = $row['courseid'] ?? null;
            $record->unitcode = $row['unit_code'] ?? null;
            $record->objecttype = $row['object_type'] ?? null;
            $record->sourceid = $row['source_id'] ?? null;
            $record->evidencestrength = $row['evidence_strength'] ?? null;
            $record->metadatajson = json_encode($row['metadata'] ?? []);
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
            $competencyid = $competencyids[$row['competency_externalid']] ?? null;
            $upid = $upids[$row['up_externalid']] ?? null;
            if (!$competencyid || !$upid) {
                continue;
            }
            repository::upsert_mapping('flwcupkp_comp_up', ['competencyid' => $competencyid, 'upid' => $upid], (object)[
                'competencyid' => $competencyid,
                'upid' => $upid,
                'role' => $row['role'] ?? 'required',
                'weight' => $row['weight'] ?? 1,
                'sortorder' => $row['sequence'] ?? 0,
                'minmastery' => $row['minimum_up_mastery'] ?? null,
                'evidencerule' => json_encode($row['evidence_rule'] ?? []),
                'notes' => $row['notes'] ?? null,
            ]);
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
            $upid = $upids[$row['up_externalid']] ?? null;
            $kpid = $kpids[$row['kp_externalid']] ?? null;
            if (!$upid || !$kpid) {
                continue;
            }
            repository::upsert_mapping('flwcupkp_up_kp', ['upid' => $upid, 'kpid' => $kpid], (object)[
                'upid' => $upid,
                'kpid' => $kpid,
                'role' => $row['role'] ?? 'required',
                'weight' => $row['weight'] ?? 1,
                'minreadiness' => $row['minimum_kp_readiness'] ?? null,
                'sortorder' => $row['sequence'] ?? 0,
                'notes' => $row['notes'] ?? null,
            ]);
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
            $kpid = $kpids[$row['kp_externalid']] ?? null;
            $prereq = $kpids[$row['prereq_kp_externalid']] ?? null;
            if (!$kpid || !$prereq) {
                continue;
            }
            repository::upsert_mapping('flwcupkp_kp_prereq', ['kpid' => $kpid, 'prereqkpid' => $prereq], (object)[
                'kpid' => $kpid,
                'prereqkpid' => $prereq,
                'relationshiptype' => $row['relationship_type'] ?? 'prerequisite',
                'strength' => $row['strength'] ?? 1,
                'requirement' => $row['requirement'] ?? 'recommended',
                'notes' => $row['notes'] ?? null,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Import object mappings.
     */
    private static function import_object_maps(array $rows, array $objectids, array $competencyids, array $upids, array $kpids): int {
        $count = 0;
        foreach ($rows as $row) {
            $objectid = $objectids[$row['object_externalid']] ?? null;
            $targettype = $row['target_type'] ?? '';
            $targetexternal = $row['target_externalid'] ?? '';
            $targetid = $targettype === 'competency' ? ($competencyids[$targetexternal] ?? null) : ($targettype === 'up' ? ($upids[$targetexternal] ?? null) : ($kpids[$targetexternal] ?? null));
            if (!$objectid || !$targetid) {
                continue;
            }
            repository::upsert_mapping('flwcupkp_object_map', ['objectid' => $objectid, 'targettype' => $targettype, 'targetid' => $targetid], (object)[
                'objectid' => $objectid,
                'targettype' => $targettype,
                'targetid' => $targetid,
                'role' => $row['role'] ?? 'practice',
                'evidencestrength' => $row['evidence_strength'] ?? null,
            ]);
            $count++;
        }
        return $count;
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

<?php
// Import validation helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates C-UP-KP package structures.
 */
class validator {
    /** @var array Allowed C-UP-KP target types. */
    private const TARGET_TYPES = ['competency', 'up', 'kp'];

    /**
     * Validate decoded package.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package(array $package): array {
        $errors = [];
        $warnings = [];

        $c1 = canonical_domain_model::validate_package_semantics($package);
        $errors = array_merge($errors, $c1['errors']);
        $warnings = array_merge($warnings, $c1['warnings']);
        $c1b = ontology_boundary::validate_package($package);
        $errors = array_merge($errors, $c1b['errors']);
        $warnings = array_merge($warnings, $c1b['warnings']);
        $c2 = relationship_graph_contract::validate_package_graph($package);
        $errors = array_merge($errors, $c2['errors']);
        $warnings = array_merge($warnings, $c2['warnings']);
        $c3 = content_evidence_mapping_contract::validate_package_contract($package);
        $errors = array_merge($errors, $c3['errors']);
        $warnings = array_merge($warnings, $c3['warnings']);
        $c4 = lifecycle_governance_contract::validate_package_governance($package);
        $errors = array_merge($errors, $c4['errors']);
        $warnings = array_merge($warnings, $c4['warnings']);

        foreach (['cupkp_schema_version', 'frameworks', 'competencies', 'use_points', 'knowledge_points'] as $required) {
            if (!array_key_exists($required, $package)) {
                $errors[] = "Missing required field: {$required}";
            }
        }

        foreach (['competencies', 'use_points', 'knowledge_points', 'learning_objects'] as $key) {
            $seen = [];
            foreach (($package[$key] ?? []) as $row) {
                $externalid = $row['externalid'] ?? '';
                if ($externalid === '') {
                    $errors[] = "{$key} row missing externalid";
                    continue;
                }
                if (isset($seen[$externalid])) {
                    $errors[] = "Duplicate {$key} externalid: {$externalid}";
                }
                $seen[$externalid] = true;
            }
        }

        $seenlessonobjects = [];
        foreach (($package['lesson_mappings'] ?? []) as $row) {
            $objectid = $row['object_externalid'] ?? ($row['externalid'] ?? '');
            if ($objectid === '') {
                $errors[] = 'lesson_mappings row missing object_externalid';
                continue;
            }
            if (isset($seenlessonobjects[$objectid])) {
                $errors[] = "Duplicate lesson_mappings object externalid: {$objectid}";
            }
            $seenlessonobjects[$objectid] = true;
            if (!empty($row['target_type']) && !in_array((string)$row['target_type'], self::TARGET_TYPES, true)) {
                $errors[] = 'Invalid lesson_mappings target_type for ' . $objectid . ': ' . $row['target_type'];
            }
            if (!empty($row['target_type']) && empty($row['target_externalid'])) {
                $errors[] = 'lesson_mappings row with target_type must include target_externalid: ' . $objectid;
            }
        }

        foreach (($package['project_competency_mappings'] ?? []) as $row) {
            $objectid = $row['object_externalid'] ?? ($row['externalid'] ?? '');
            if ($objectid === '') {
                $errors[] = 'project_competency_mappings row missing object_externalid';
            }
            if (empty($row['competency_externalid']) && empty($row['competency_externalids'])) {
                $errors[] = 'project_competency_mappings row missing competency_externalid';
            }
        }

        foreach (($package['knowledge_points'] ?? []) as $kp) {
            $domain = $kp['domain'] ?? '';
            if ($domain !== '' && !in_array($domain, canonical_domain_model::kp_domains(), true)) {
                $errors[] = 'Invalid KP domain for ' . ($kp['externalid'] ?? 'unknown') . ': ' . $domain;
            }
        }

        if (empty($package['project_evidence'])) {
            $warnings[] = 'No project_evidence entries found; competencies may lack direct integrated evidence.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'ontology' => [
                'valid' => empty($c1['errors']) && empty($c1b['errors']),
                'contracts' => [
                    $c1['contract'] ?? canonical_domain_model::CONTRACT_VERSION,
                    $c1b['contract'] ?? ontology_boundary::CONTRACT_VERSION,
                ],
                'errors' => array_merge($c1['errors'], $c1b['errors']),
                'warnings' => array_merge($c1['warnings'], $c1b['warnings']),
                'details' => $c1b['details'] ?? [],
            ],
            'graph' => [
                'valid' => empty($c2['errors']),
                'contract' => $c2['contract'] ?? relationship_graph_contract::CONTRACT_VERSION,
                'errors' => $c2['errors'],
                'warnings' => $c2['warnings'],
                'details' => $c2['details'] ?? [],
                'counts' => $c2['counts'] ?? [],
            ],
            'content_evidence' => [
                'valid' => empty($c3['errors']),
                'contract' => $c3['contract'] ?? content_evidence_mapping_contract::CONTRACT_VERSION,
                'errors' => $c3['errors'],
                'warnings' => $c3['warnings'],
                'details' => $c3['details'] ?? [],
                'counts' => $c3['counts'] ?? [],
            ],
            'governance' => [
                'valid' => empty($c4['errors']),
                'contract' => $c4['contract'] ?? lifecycle_governance_contract::CONTRACT_VERSION,
                'errors' => $c4['errors'],
                'warnings' => $c4['warnings'],
                'details' => $c4['details'] ?? [],
                'counts' => $c4['counts'] ?? [],
            ],
        ];
    }
}

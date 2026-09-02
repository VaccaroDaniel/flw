<?php
// Program 3 Gate C1 canonical C-UP-KP domain model.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Frozen C1 domain meanings and lightweight semantic validation helpers.
 */
final class canonical_domain_model {
    /** Program 3 canonical domain model gate. */
    public const GATE = 'P3_C1';

    /** Frozen C1 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1';

    /** @var array Allowed CEFR macro levels for the C1 model. */
    private const CEFR_LEVELS = ['PRE-A1', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    /** @var array Allowed KP domains already used by the plugin schema/importer. */
    private const KP_DOMAINS = [
        'LEX',
        'GRAM',
        'FUNC',
        'PRON',
        'ORTH',
        'SCRIPT',
        'LISTEN',
        'SPEAK',
        'READ',
        'WRITE',
        'DISC',
        'STRAT',
        'PRAG',
        'CULT',
    ];

    /** @var array Learner-state fields that must never be stored on curriculum definitions. */
    private const LEARNER_STATE_FIELDS = [
        'masteryscore',
        'mastery_score',
        'masterystate',
        'mastery_state',
        'confidence',
        'evidencecount',
        'evidence_count',
        'lastevidence',
        'last_evidence',
        'lastsuccess',
        'last_success',
        'nextreview',
        'next_review',
        'manualoverride',
        'manual_override',
        'overridereason',
        'override_reason',
    ];

    /** @var array C1 entity meanings and code aliases. */
    private const ENTITY_CONTRACT = [
        'competency' => [
            'short' => 'C',
            'aliases' => ['C', 'COMP', 'COMPETENCY'],
            'meaning' => 'Meaningful integrated ability a learner can demonstrate in a real communicative or operational context.',
            'owns' => ['can-do statement', 'CEFR level', 'FLW stage', 'domain/scope', 'evidence rule reference'],
            'does_not_own' => ['learner mastery state', 'attempt score', 'recommendation decision'],
        ],
        'up' => [
            'short' => 'UP',
            'aliases' => ['UP', 'USEPOINT', 'USE_POINT'],
            'meaning' => 'Observable use point describing how relevant knowledge must be used or demonstrated.',
            'owns' => ['action statement', 'intention', 'context', 'observable action', 'success criteria'],
            'does_not_own' => ['new unmodeled knowledge', 'learner mastery state', 'attempt score'],
        ],
        'kp' => [
            'short' => 'KP',
            'aliases' => ['KP', 'KNOWLEDGEPOINT', 'KNOWLEDGE_POINT'],
            'meaning' => 'Linguistic, strategic, cultural, procedural, or content knowledge needed for use.',
            'owns' => ['language', 'CEFR level', 'knowledge domain', 'form', 'meaning/function', 'usage constraints'],
            'does_not_own' => ['task completion state', 'learner mastery state', 'recommendation decision'],
        ],
    ];

    /**
     * Return the frozen C1 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CanonicalCupkpDomainModel',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'entities' => self::ENTITY_CONTRACT,
            'stable_code_policy' => [
                'canonical_examples' => [
                    'competency' => 'C-FR-A2-SI-004',
                    'kp' => 'KP-FR-A2-FUNC-031',
                    'up' => 'UP-FR-A2-SI-031-04',
                ],
                'legacy_flw_style_allowed' => true,
                'untyped_legacy_ids_allowed_with_warning' => true,
                'wrong_entity_prefix_rejected' => true,
            ],
            'topology' => [
                'strict_tree' => false,
                'many_to_many' => true,
                'relations' => [
                    'competency_to_up' => 'many_to_many',
                    'up_to_kp' => 'many_to_many',
                    'kp_to_prerequisite_kp' => 'many_to_many',
                    'learning_object_to_target' => 'many_to_many',
                ],
            ],
            'cefr_stage_semantics' => [
                'cefr_level' => 'Official CEFR macro level only: PRE-A1, A1, A2, B1, B2, C1, or C2.',
                'flw_stage' => 'Separate FLW curricular stage or product stage label.',
                'pseudo_cefr_sublevels' => 'A2.1/A2.2-style values are not accepted as CEFR levels.',
            ],
            'learner_state_separation' => [
                'rule' => 'Never store learner mastery directly on curriculum definitions.',
                'curriculum_tables' => ['flwcupkp_comp', 'flwcupkp_up', 'flwcupkp_kp'],
                'learner_state_table' => 'flwcupkp_state',
                'evidence_table' => 'flwcupkp_evidence',
            ],
            'source_history_boundary' => history_v1_consumer_contract::normal_source_boundary(),
        ];
    }

    /**
     * Return supported C-UP-KP target types.
     *
     * @return array
     */
    public static function target_types(): array {
        return array_keys(self::ENTITY_CONTRACT);
    }

    /**
     * Return allowed CEFR macro levels.
     *
     * @return array
     */
    public static function cefr_levels(): array {
        return self::CEFR_LEVELS;
    }

    /**
     * Return allowed Knowledge Point domains.
     *
     * @return array
     */
    public static function kp_domains(): array {
        return self::KP_DOMAINS;
    }

    /**
     * Validate package-level and row-level C1 semantics.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package_semantics(array $package): array {
        $errors = [];
        $warnings = [];

        if (array_key_exists('cefr_level', $package)) {
            self::merge_result('package.cefr_level', self::validate_cefr_level_value($package['cefr_level']), $errors, $warnings);
        }
        if (array_key_exists('flw_stage', $package)) {
            self::merge_result('package.flw_stage', self::validate_flw_stage_value($package['flw_stage']), $errors, $warnings);
        }

        $map = [
            'frameworks' => 'framework',
            'competencies' => 'competency',
            'use_points' => 'up',
            'knowledge_points' => 'kp',
        ];
        foreach ($map as $key => $entitytype) {
            foreach (($package[$key] ?? []) as $index => $row) {
                if (!is_array($row)) {
                    $errors[] = $key . '[' . $index . '] must be an object.';
                    continue;
                }
                self::merge_result($key . '[' . $index . ']', self::validate_curriculum_row($entitytype, $row), $errors, $warnings);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'contract' => self::CONTRACT_VERSION,
        ];
    }

    /**
     * Validate one curriculum definition row against C1 semantics.
     *
     * @param string $entitytype
     * @param array $row
     * @return array
     */
    public static function validate_curriculum_row(string $entitytype, array $row): array {
        $entitytype = self::normalize_entity_type($entitytype);
        $errors = [];
        $warnings = [];

        foreach (self::LEARNER_STATE_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                $errors[] = 'Learner state field "' . $field . '" is not allowed on curriculum definitions.';
            }
        }

        if (in_array($entitytype, self::target_types(), true) && !empty($row['externalid'])) {
            $status = self::semantic_code_status($entitytype, (string)$row['externalid']);
            if (empty($status['valid'])) {
                $errors[] = $status['message'];
            } else if (!empty($status['warning'])) {
                $warnings[] = $status['message'];
            }
        }

        if ($entitytype === 'framework') {
            $range = $row['cefrrange'] ?? ($row['cefr_range'] ?? null);
            if ($range !== null && $range !== '') {
                self::merge_result('cefr_range', self::validate_cefr_range_value($range), $errors, $warnings);
            }
        } else if (array_key_exists('cefr', $row) && $row['cefr'] !== null && $row['cefr'] !== '') {
            self::merge_result('cefr', self::validate_cefr_level_value($row['cefr']), $errors, $warnings);
        }

        $stage = $row['stage'] ?? ($row['flw_stage'] ?? null);
        if ($stage !== null && $stage !== '') {
            if ($entitytype === 'competency') {
                self::merge_result('stage', self::validate_flw_stage_value($stage), $errors, $warnings);
            } else {
                $warnings[] = 'FLW stage is currently stored on competencies only; keep UP/KP stage meaning explicit in mappings or metadata.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Throw when a curriculum row violates C1 semantics.
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
     * Inspect whether a stable external ID matches the expected entity type.
     *
     * @param string $entitytype competency, up, or kp.
     * @param string $externalid
     * @return array
     */
    public static function semantic_code_status(string $entitytype, string $externalid): array {
        $entitytype = self::normalize_entity_type($entitytype);
        if (!isset(self::ENTITY_CONTRACT[$entitytype])) {
            throw new \invalid_parameter_exception('Unknown C-UP-KP entity type.');
        }
        $externalid = trim($externalid);
        if ($externalid === '') {
            return [
                'valid' => false,
                'status' => 'missing',
                'message' => 'Stable external ID is required.',
            ];
        }

        $detected = self::detected_entity_types($externalid);
        if (!$detected) {
            return [
                'valid' => true,
                'status' => 'untyped_legacy',
                'warning' => true,
                'detected' => [],
                'message' => 'External ID "' . $externalid . '" is accepted as legacy but does not expose a C/UP/KP semantic marker.',
            ];
        }

        if (!in_array($entitytype, $detected, true)) {
            return [
                'valid' => false,
                'status' => 'type_mismatch',
                'detected' => $detected,
                'message' => 'External ID "' . $externalid . '" looks like ' . implode('/', $detected) .
                    ' but is being used as ' . $entitytype . '.',
            ];
        }

        $other = array_values(array_diff($detected, [$entitytype]));
        if ($other) {
            return [
                'valid' => false,
                'status' => 'ambiguous',
                'detected' => $detected,
                'message' => 'External ID "' . $externalid . '" mixes semantic markers for ' . implode('/', $detected) . '.',
            ];
        }

        return [
            'valid' => true,
            'status' => 'typed',
            'detected' => $detected,
            'message' => 'External ID matches ' . $entitytype . ' semantics.',
        ];
    }

    /**
     * Read-only C1 freeze status for runtime checks.
     *
     * @param int $courseid Optional course id for the inherited History V1 check.
     * @return array
     */
    public static function freeze_status(int $courseid = 0): array {
        global $DB;

        $dbman = $DB->get_manager();
        $history = history_v1_consumer_contract::contract_status($courseid, 1);
        $tables = [];
        foreach (['flwcupkp_comp', 'flwcupkp_up', 'flwcupkp_kp', 'flwcupkp_comp_up', 'flwcupkp_up_kp', 'flwcupkp_state'] as $table) {
            $tables[$table] = $dbman->table_exists(new \xmldb_table($table));
        }

        $contract = self::contract();
        $findings = [];
        if (($history['status'] ?? 'blocked') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'history_v1_not_ready',
                'message' => 'C1 requires the A0 History V1 boundary to remain ready.',
            ];
        }
        foreach ($tables as $table => $present) {
            if (!$present) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => 'missing_table',
                    'message' => 'Missing table: ' . $table,
                ];
            }
        }

        return [
            'type' => 'CanonicalCupkpDomainModelFreezeStatus',
            'gate' => self::GATE,
            'status' => $findings ? 'blocked' : 'frozen',
            'contract' => $contract,
            'history' => [
                'status' => $history['status'] ?? 'blocked',
                'requiredcontract' => $history['requiredcontract'] ?? null,
                'normpolicyversion' => $history['contract']['normpolicyversion'] ?? null,
            ],
            'tables' => $tables,
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
        $entitytype = strtolower(trim($entitytype));
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
        ];
        return $aliases[$entitytype] ?? $entitytype;
    }

    /**
     * Detect semantic entity markers in an external ID.
     *
     * @param string $externalid
     * @return array
     */
    private static function detected_entity_types(string $externalid): array {
        $value = strtoupper(trim($externalid));
        $tokens = preg_split('/[-_.:\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $detected = [];

        foreach (self::ENTITY_CONTRACT as $entitytype => $contract) {
            foreach ($contract['aliases'] as $alias) {
                if (in_array($alias, $tokens, true)) {
                    $detected[] = $entitytype;
                    break;
                }
            }
        }

        foreach ($tokens as $token) {
            if (in_array($token, self::KP_DOMAINS, true)) {
                $detected[] = 'kp';
                break;
            }
        }

        if (preg_match('/^(C|COMP)-/', $value)) {
            $detected[] = 'competency';
        }
        if (preg_match('/^UP-/', $value)) {
            $detected[] = 'up';
        }
        if (preg_match('/^KP-/', $value)) {
            $detected[] = 'kp';
        }

        return array_values(array_unique($detected));
    }

    /**
     * Validate one CEFR level value.
     *
     * @param mixed $value
     * @return array
     */
    private static function validate_cefr_level_value($value): array {
        $normalized = strtoupper(trim((string)$value));
        if (self::looks_like_pseudo_cefr_sublevel($normalized)) {
            return [
                'valid' => false,
                'errors' => ['A2.1/A2.2-style pseudo-CEFR values are not valid CEFR macro levels.'],
                'warnings' => [],
            ];
        }
        if (!in_array($normalized, self::CEFR_LEVELS, true)) {
            return [
                'valid' => false,
                'errors' => ['CEFR level must be one of: ' . implode(', ', self::CEFR_LEVELS) . '.'],
                'warnings' => [],
            ];
        }
        return ['valid' => true, 'errors' => [], 'warnings' => []];
    }

    /**
     * Validate one framework CEFR range value.
     *
     * @param mixed $value
     * @return array
     */
    private static function validate_cefr_range_value($value): array {
        $normalized = strtoupper(str_replace(' ', '', trim((string)$value)));
        if (self::looks_like_pseudo_cefr_sublevel($normalized)) {
            return [
                'valid' => false,
                'errors' => ['Framework CEFR range must not use A2.1/A2.2-style pseudo-CEFR values.'],
                'warnings' => [],
            ];
        }
        $level = '(?:PRE-A1|A1|A2|B1|B2|C1|C2)';
        if (preg_match('/^' . $level . '$/', $normalized)) {
            return ['valid' => true, 'errors' => [], 'warnings' => []];
        }
        if (preg_match('/^' . $level . '-' . $level . '$/', $normalized)) {
            return ['valid' => true, 'errors' => [], 'warnings' => []];
        }
        return [
            'valid' => false,
            'errors' => ['CEFR range must be one macro level or a range such as A2-B1.'],
            'warnings' => [],
        ];
    }

    /**
     * Validate one FLW stage value.
     *
     * @param mixed $value
     * @return array
     */
    private static function validate_flw_stage_value($value): array {
        $stage = trim((string)$value);
        $normalized = strtoupper($stage);
        if (self::looks_like_pseudo_cefr_sublevel($normalized)) {
            return [
                'valid' => false,
                'errors' => ['FLW stage must be separate from A2.1/A2.2-style pseudo-CEFR labels.'],
                'warnings' => [],
            ];
        }
        if (in_array($normalized, self::CEFR_LEVELS, true)) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => ['FLW stage duplicates a CEFR level; keep stage and CEFR meaning separate.'],
            ];
        }
        return ['valid' => true, 'errors' => [], 'warnings' => []];
    }

    /**
     * Does a value look like a forbidden pseudo CEFR sublevel?
     *
     * @param string $value
     * @return bool
     */
    private static function looks_like_pseudo_cefr_sublevel(string $value): bool {
        return (bool)preg_match('/\b(?:PRE-A1|A1|A2|B1|B2|C1|C2)\.\d+\b/', $value);
    }

    /**
     * Merge a validation result into parent errors/warnings.
     *
     * @param string $prefix
     * @param array $result
     * @param array $errors
     * @param array $warnings
     */
    private static function merge_result(string $prefix, array $result, array &$errors, array &$warnings): void {
        foreach ($result['errors'] ?? [] as $error) {
            $errors[] = $prefix . ': ' . $error;
        }
        foreach ($result['warnings'] ?? [] as $warning) {
            $warnings[] = $prefix . ': ' . $warning;
        }
    }
}

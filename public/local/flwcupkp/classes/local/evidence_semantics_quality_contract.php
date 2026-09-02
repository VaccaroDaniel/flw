<?php
// Program 3 Gate C3B evidence semantics and quality contract.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes evidence meaning before mastery and adaptive policies consume it.
 */
final class evidence_semantics_quality_contract {
    /** Program 3 evidence semantics and quality gate. */
    public const GATE = 'P3_C3B';

    /** Frozen C3B contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1';

    /** Deterministic evidence interpretation policy. */
    public const EVIDENCE_POLICY_VERSION = 'cupkp-evidence-quality-v1';

    /** @var array Canonical C3B result states. */
    private const RESULT_STATES = [
        'positive' => 'The event supports the target claim.',
        'negative' => 'The event shows unsuccessful or incorrect target performance.',
        'partial' => 'The event gives mixed or incomplete support for the target claim.',
        'inconclusive' => 'The event cannot responsibly support or reduce the target claim.',
    ];

    /** @var array Canonical C3B performance modes. */
    private const PERFORMANCE_MODES = [
        'passive_exposure' => 'Learner was exposed to content; no independent performance is shown.',
        'recognition' => 'Learner recognized or selected known information.',
        'comprehension' => 'Learner demonstrated understanding without production.',
        'selection' => 'Learner selected among constrained options.',
        'controlled_recall' => 'Learner recalled or produced with strong controls.',
        'guided_production' => 'Learner produced with prompts, scaffolds, or support.',
        'independent_production' => 'Learner produced independently in the task context.',
        'interaction' => 'Learner used the target in interaction with another speaker/system.',
        'transfer' => 'Learner used the target in a changed or real-world context.',
    ];

    /** @var array Canonical direct/inferred flags. */
    private const EVIDENCE_DIRECTIONS = [
        'direct' => 'The source event directly measured the mapped target.',
        'inferred' => 'The target claim is inferred from source evidence and an inference path.',
    ];

    /** @var array Canonical evidence roles. */
    private const EVIDENCE_ROLES = [
        'learning_signal' => 'A weak or enabling signal, such as valid completion.',
        'practice_evidence' => 'Practice performance that may support learning state.',
        'assessment_evidence' => 'Mapped assessment evidence for a target.',
        'teacher_evidence' => 'Human observation or teacher-entered evidence.',
        'placement_evidence' => 'Diagnostic placement evidence.',
        'checkpoint_evidence' => 'Checkpoint or progress-check evidence.',
        'external_evidence' => 'Evidence imported from a trusted external source.',
    ];

    /** @var array Normalized quality dimensions. Higher values mean stronger evidence. */
    private const QUALITY_DIMENSIONS = [
        'validity' => 'How well the event measures the target.',
        'reliability' => 'How trustworthy and repeatable the measurement is.',
        'independence' => 'How independently the learner performed.',
        'authenticity' => 'How close the task is to realistic language use.',
        'production_demand' => 'How much productive language performance was required.',
        'contextual_transfer' => 'How far the performance transferred beyond the taught context.',
        'support_level' => 'How unsupported the performance was; 1 means unsupported.',
        'difficulty' => 'Relative task difficulty for the target and learner context.',
        'recency' => 'How fresh the captured source event is at recording time.',
        'confidence' => 'System or assessor confidence in this evidence interpretation.',
    ];

    /**
     * Return the frozen C3B evidence semantics contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpEvidenceSemanticsQualityContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'evidence_policy_version' => self::EVIDENCE_POLICY_VERSION,
            'depends_on' => [
                canonical_domain_model::CONTRACT_VERSION,
                ontology_boundary::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                content_evidence_mapping_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'event_fields' => [
                'evidence_id',
                'learner_id',
                'history_source_id',
                'source_type',
                'source_attempt_id',
                'target_entity_type',
                'target_entity_id',
                'evidence_role',
                'performance_mode',
                'result_state',
                'raw_score',
                'normalized_score',
                'occurred_at',
                'recorded_at',
                'curriculum_version',
                'evidence_policy_version',
                'quality',
                'provenance',
            ],
            'result_states' => self::RESULT_STATES,
            'inconclusive_rule' => 'Inconclusive evidence must not directly reduce mastery.',
            'performance_modes' => self::PERFORMANCE_MODES,
            'direct_inferred' => self::EVIDENCE_DIRECTIONS,
            'evidence_roles' => self::EVIDENCE_ROLES,
            'quality_dimensions' => self::QUALITY_DIMENSIONS,
            'quality_normalization' => [
                'range' => [0, 1],
                'deterministic_inputs' => [
                    'evidence fields',
                    'C3 mapping metadata',
                    'object metadata',
                    'explicit rubric overrides',
                    'evidence_policy_version',
                ],
                'single_quality_weight' => 'not_created_by_c3b',
            ],
            'inferred_evidence' => [
                'requires_source_evidence_or_source_key' => true,
                'stores_inference_path' => true,
            ],
            'retry_semantics' => [
                'preserve_attempts' => true,
                'hint_or_answer_exposure_lowers_independence_and_support_quality' => true,
            ],
            'evidence_ceilings' => [
                'rule' => 'Performance mode limits the maximum mastery claim justified by an event.',
                'c3b_behavior' => 'record_advisory_ceiling_only',
            ],
            'does_not_do' => [
                'mastery_threshold_changes',
                'adaptive_path_selection',
                'history_v1_reprocessing',
                'raw_moodle_log_scraping',
                'teacher_override_workflow',
            ],
        ];
    }

    /**
     * Return canonical result-state labels.
     *
     * @return array
     */
    public static function result_states(): array {
        return array_keys(self::RESULT_STATES);
    }

    /**
     * Return canonical performance-mode labels.
     *
     * @return array
     */
    public static function performance_modes(): array {
        return array_keys(self::PERFORMANCE_MODES);
    }

    /**
     * Return canonical quality-dimension labels.
     *
     * @return array
     */
    public static function quality_dimensions(): array {
        return array_keys(self::QUALITY_DIMENSIONS);
    }

    /**
     * Validate an evidence payload against the frozen C3B semantics.
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
        $rubric = self::decode_metadata($evidence->rubricjson ?? [], $errors, $details);
        self::merge_result(self::validate_explicit_semantic_overrides($rubric), $errors, $warnings, $details);

        $c3 = content_evidence_mapping_contract::validate_evidence_payload($evidence, $object, $map);
        self::merge_result($c3, $errors, $warnings, $details);

        $semantics = self::semantics_for_evidence($evidence, $object, $map);
        $result = self::validate_semantics($semantics);
        self::merge_result($result, $errors, $warnings, $details);

        if (($semantics['result_state'] ?? '') === 'inconclusive') {
            $details[] = self::detail('inconclusive_no_direct_mastery_reduction', 'info');
        }
        if (($semantics['evidence_direction'] ?? '') === 'inferred' &&
                empty($semantics['inference_path'])) {
            $errors[] = 'Inferred evidence must store an inference path.';
            $details[] = self::detail('missing_inference_path', 'error');
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Throw when an evidence payload violates C3B.
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
     * Validate a C3B semantics payload.
     *
     * @param array $semantics
     * @return array
     */
    public static function validate_semantics(array $semantics): array {
        $errors = [];
        $warnings = [];
        $details = [];

        if (($semantics['contract'] ?? '') !== self::CONTRACT_VERSION) {
            $errors[] = 'Evidence semantics contract must be ' . self::CONTRACT_VERSION . '.';
            $details[] = self::detail('invalid_semantics_contract', 'error');
        }
        if (($semantics['policy_version'] ?? '') !== self::EVIDENCE_POLICY_VERSION) {
            $errors[] = 'Evidence policy version must be ' . self::EVIDENCE_POLICY_VERSION . '.';
            $details[] = self::detail('invalid_evidence_policy_version', 'error');
        }
        if (($semantics['history_contract'] ?? '') !== history_v1_consumer_contract::REQUIRED_CONTRACT) {
            $errors[] = 'Evidence semantics must preserve the History V1 downstream contract.';
            $details[] = self::detail('missing_history_contract', 'error');
        }
        if (($semantics['normal_source_rule'] ?? '') !== history_v1_consumer_contract::CONSUMPTION_RULE) {
            $errors[] = 'Evidence semantics must preserve the History V1 source boundary.';
            $details[] = self::detail('missing_history_source_boundary', 'error');
        }
        if (!isset(self::RESULT_STATES[$semantics['result_state'] ?? ''])) {
            $errors[] = 'Unsupported C3B result state.';
            $details[] = self::detail('unsupported_result_state', 'error');
        }
        if (!isset(self::PERFORMANCE_MODES[$semantics['performance_mode'] ?? ''])) {
            $errors[] = 'Unsupported C3B performance mode.';
            $details[] = self::detail('unsupported_performance_mode', 'error');
        }
        if (!isset(self::EVIDENCE_DIRECTIONS[$semantics['evidence_direction'] ?? ''])) {
            $errors[] = 'Unsupported C3B evidence direction.';
            $details[] = self::detail('unsupported_evidence_direction', 'error');
        }
        if (!isset(self::EVIDENCE_ROLES[$semantics['evidence_role'] ?? ''])) {
            $errors[] = 'Unsupported C3B evidence role.';
            $details[] = self::detail('unsupported_evidence_role', 'error');
        }

        $quality = $semantics['quality'] ?? null;
        if (!is_array($quality)) {
            $errors[] = 'Evidence quality dimensions are required.';
            $details[] = self::detail('missing_quality_dimensions', 'error');
        } else {
            foreach (array_keys(self::QUALITY_DIMENSIONS) as $dimension) {
                if (!array_key_exists($dimension, $quality)) {
                    $errors[] = 'Evidence quality dimension is missing: ' . $dimension . '.';
                    $details[] = self::detail('missing_quality_dimension', 'error', ['dimension' => $dimension]);
                    continue;
                }
                if (!is_numeric($quality[$dimension]) || (float)$quality[$dimension] < 0 ||
                        (float)$quality[$dimension] > 1) {
                    $errors[] = 'Evidence quality dimension must be normalized between 0 and 1: ' . $dimension . '.';
                    $details[] = self::detail('invalid_quality_dimension', 'error', ['dimension' => $dimension]);
                }
            }
        }

        if (($semantics['result_state'] ?? '') === 'inconclusive' &&
                ($semantics['counts_for_mastery_policy'] ?? null) !== false) {
            $warnings[] = 'Inconclusive evidence should be marked as not counting directly for mastery.';
            $details[] = self::detail('inconclusive_mastery_boundary_warning', 'warning');
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Add C3B semantics into rubricjson before storage.
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
        $rubric['cupkp_c3b_semantics'] = self::semantics_for_evidence($evidence, $object, $map);
        $evidence->rubricjson = json_encode($rubric, JSON_UNESCAPED_SLASHES);
        return $evidence;
    }

    /**
     * Return C3B semantics without mutating the evidence payload.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @return array
     */
    public static function semantics_for_evidence(\stdClass $evidence, ?\stdClass $object = null,
            ?\stdClass $map = null): array {
        $ignorederrors = [];
        $ignoreddetails = [];
        $rubric = self::decode_metadata($evidence->rubricjson ?? [], $ignorederrors, $ignoreddetails);
        $existing = self::existing_semantics($rubric);
        $existing = self::merge_top_level_semantic_overrides($existing, $rubric);
        $sourcekey = self::source_key_from_evidence($evidence);
        $resultstate = self::infer_result_state($evidence, $existing);
        $mode = self::infer_performance_mode($evidence, $object, $map, $existing);
        $direction = self::infer_evidence_direction($evidence, $mode, $existing);
        $role = self::infer_evidence_role($evidence, $object, $map, $sourcekey, $existing);
        $quality = self::quality_profile($evidence, $object, $map, $resultstate, $mode, $direction, $existing);

        return [
            'contract' => self::CONTRACT_VERSION,
            'policy_version' => self::EVIDENCE_POLICY_VERSION,
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'source_key' => $sourcekey,
            'source_type' => $sourcekey['source_type'] ?? content_evidence_mapping_contract::source_type_for_evidence_payload($evidence),
            'source_attempt_id' => (string)($evidence->sourceattempt ?? ''),
            'target_entity_type' => (string)($evidence->targettype ?? ''),
            'target_entity_id' => (int)($evidence->targetid ?? 0),
            'evidence_role' => $role,
            'performance_mode' => $mode,
            'result_state' => $resultstate,
            'evidence_direction' => $direction,
            'raw_score' => (float)($evidence->rawscore ?? 0),
            'normalized_score' => self::score_available($evidence) ? self::clamp01((float)$evidence->normalizedscore) : null,
            'occurred_at' => self::occurred_at($evidence, $rubric),
            'recorded_at' => self::recorded_at($evidence, $rubric),
            'curriculum_version' => self::curriculum_version($object, $map, $rubric),
            'quality' => $quality,
            'quality_integrity_score' => self::quality_integrity_score($quality),
            'evidence_ceiling_hint' => self::evidence_ceiling_hint(
                (string)($evidence->targettype ?? ''),
                $mode,
                $resultstate,
                $sourcekey['source_type'] ?? ''
            ),
            'attempt_semantics' => self::attempt_semantics($evidence, $rubric),
            'inference_path' => $direction === 'inferred' ? self::inference_path($evidence, $sourcekey, $rubric) : [],
            'counts_for_mastery_policy' => $resultstate === 'inconclusive' ? false : null,
            'mastery_policy_boundary' => 'quality_dimensions_are_not_mastery_thresholds_in_c3b',
        ];
    }

    /**
     * Build History V1-aware source identity metadata for an evidence row.
     *
     * @param \stdClass $evidence
     * @return array
     */
    public static function source_key_from_evidence(\stdClass $evidence): array {
        $ignorederrors = [];
        $ignoreddetails = [];
        $rubric = self::decode_metadata($evidence->rubricjson ?? [], $ignorederrors, $ignoreddetails);
        $existing = self::source_key_overrides($rubric);
        $c3 = is_array($rubric['cupkp_c3_mapping'] ?? null) ? $rubric['cupkp_c3_mapping'] : [];
        $source = (string)($c3['source_type'] ?? content_evidence_mapping_contract::source_type_for_evidence_payload($evidence));
        $sourceref = (string)($evidence->sourceref ?? ($existing['source_ref'] ?? ''));
        $sourceattempt = (string)($evidence->sourceattempt ?? ($existing['source_attempt_id'] ?? ''));
        $provenance = (string)($evidence->provenance ?? ($existing['provenance'] ?? ''));
        $historysourcekey = self::first_value($existing, ['history_source_key', 'source_key', 'history_source_id']);
        if ($historysourcekey === null || $historysourcekey === '') {
            $historysourcekey = self::first_non_empty([$sourceref, $sourceattempt, $provenance]);
        }

        $key = [
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'history_source_id' => self::first_value($existing, ['history_source_id', 'source_id']),
            'history_source_key' => $historysourcekey,
            'source_type' => $source,
            'source_attempt_id' => $sourceattempt,
            'source_ref' => $sourceref,
            'provenance' => $provenance,
            'evidence_type' => (string)($evidence->evidencetype ?? ''),
            'assessor_type' => (string)($evidence->assessortype ?? ''),
            'legacy_direct_capture' => !self::looks_like_history_v1_source($provenance, $sourceref, $existing),
        ];

        if (is_array($c3['content_identity'] ?? null)) {
            $key['content_identity'] = $c3['content_identity'];
        }
        if (!empty($c3['object_externalid'])) {
            $key['object_externalid'] = (string)$c3['object_externalid'];
        }
        if (!empty($evidence->id)) {
            $key['evidence_id'] = (int)$evidence->id;
        }

        return self::filter_empty($key);
    }

    /**
     * Infer the C3B result state.
     *
     * @param \stdClass $evidence
     * @param array $existing
     * @return string
     */
    public static function infer_result_state(\stdClass $evidence, array $existing = []): string {
        $explicit = self::normalized_existing_value($existing, ['result_state', 'result']);
        if (isset(self::RESULT_STATES[$explicit])) {
            return $explicit;
        }
        if (self::explicit_inconclusive_signal($evidence, $existing)) {
            return 'inconclusive';
        }
        if (!self::score_available($evidence)) {
            return 'inconclusive';
        }

        $score = self::clamp01((float)$evidence->normalizedscore);
        if ($score >= 0.70) {
            return 'positive';
        }
        if ($score >= 0.35) {
            return 'partial';
        }
        return 'negative';
    }

    /**
     * Infer the C3B performance mode.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @param array $existing
     * @return string
     */
    public static function infer_performance_mode(\stdClass $evidence, ?\stdClass $object = null,
            ?\stdClass $map = null, array $existing = []): string {
        $explicit = self::normalized_existing_value($existing, ['performance_mode', 'mode']);
        if (isset(self::PERFORMANCE_MODES[$explicit])) {
            return $explicit;
        }

        $strength = self::normalize_performance_mode((string)($evidence->evidencestrength ?? ''));
        if ($strength !== '') {
            return $strength;
        }

        $source = content_evidence_mapping_contract::source_type_for_evidence_payload($evidence);
        if ($source === 'completion') {
            return 'passive_exposure';
        }
        if ($source === 'teacher_observation') {
            return 'interaction';
        }
        if (in_array($source, ['placement', 'checkpoint'], true)) {
            return 'selection';
        }
        if ($source === 'grade_linked_assessment') {
            return self::object_implies_production($object, $map) ? 'independent_production' : 'comprehension';
        }
        if ($source === 'external_assessment') {
            return 'independent_production';
        }
        return self::object_implies_production($object, $map) ? 'guided_production' : 'recognition';
    }

    /**
     * Infer whether evidence is direct or inferred.
     *
     * @param \stdClass $evidence
     * @param string $mode
     * @param array $existing
     * @return string
     */
    public static function infer_evidence_direction(\stdClass $evidence, string $mode, array $existing = []): string {
        $explicit = self::normalized_existing_value($existing, ['evidence_direction', 'direction', 'direct_inferred']);
        if (isset(self::EVIDENCE_DIRECTIONS[$explicit])) {
            return $explicit;
        }
        $source = content_evidence_mapping_contract::source_type_for_evidence_payload($evidence);
        if ($source === 'completion' || $mode === 'passive_exposure' || $mode === 'recognition') {
            return 'inferred';
        }
        return 'direct';
    }

    /**
     * Build normalized quality dimensions.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @param string $resultstate
     * @param string $mode
     * @param string $direction
     * @param array $existing
     * @return array
     */
    public static function quality_profile(\stdClass $evidence, ?\stdClass $object = null, ?\stdClass $map = null,
            string $resultstate = '', string $mode = '', string $direction = '', array $existing = []): array {
        $source = content_evidence_mapping_contract::source_type_for_evidence_payload($evidence);
        $quality = self::quality_baseline($source);
        $mode = $mode !== '' ? $mode : self::infer_performance_mode($evidence, $object, $map, $existing);
        $direction = $direction !== '' ? $direction : self::infer_evidence_direction($evidence, $mode, $existing);

        self::apply_mode_adjustments($quality, $mode);
        if ($direction === 'inferred') {
            $quality['validity'] *= 0.85;
            $quality['confidence'] *= 0.9;
        }
        if ($resultstate === 'inconclusive') {
            $quality['validity'] *= 0.35;
            $quality['reliability'] *= 0.45;
            $quality['confidence'] *= 0.4;
        }

        $quality['difficulty'] = self::difficulty_from_object($object, $quality['difficulty']);
        $quality['recency'] = self::recency_from_evidence($evidence);
        $quality['confidence'] = self::confidence_from_evidence($evidence, $quality['confidence']);
        self::apply_retry_adjustments($quality, self::attempt_semantics($evidence, self::rubric_array($evidence)));

        foreach (self::quality_overrides($existing) as $dimension => $value) {
            $quality[$dimension] = $value;
        }

        foreach ($quality as $dimension => $value) {
            $quality[$dimension] = round(self::clamp01((float)$value), 5);
        }

        return $quality;
    }

    /**
     * Read-only status for C3B readiness and existing evidence coverage.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function evidence_semantics_status(int $courseid = 0, string $unitcode = '', int $limit = 100): array {
        global $DB;

        $c3 = content_evidence_mapping_contract::content_mapping_status($courseid, $unitcode, min($limit, 100));
        $history = history_v1_consumer_contract::contract_status($courseid, 1);
        $findings = [];
        if (($c3['status'] ?? '') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'c3_not_frozen',
                'message' => 'C3B requires C3 content/evidence mapping contracts to remain frozen.',
            ];
        }
        if (($history['status'] ?? '') === 'blocked') {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'history_v1_not_ready',
                'message' => 'C3B requires History V1 as the only normal source-history input.',
            ];
        }

        $params = [];
        $where = '1=1';
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }

        $records = $DB->get_records_select('flwcupkp_evidence', $where, $params, 'timecreated DESC, id DESC',
            '*', 0, max(1, $limit));
        $counts = [
            'evidence_rows' => count($records),
            'with_c3b_semantics' => 0,
            'legacy_without_c3b_semantics' => 0,
            'result_states' => array_fill_keys(array_keys(self::RESULT_STATES), 0),
            'performance_modes' => array_fill_keys(array_keys(self::PERFORMANCE_MODES), 0),
            'directions' => array_fill_keys(array_keys(self::EVIDENCE_DIRECTIONS), 0),
            'quality_complete' => 0,
            'history_v1_source_keys' => 0,
            'legacy_direct_capture' => 0,
        ];

        foreach ($records as $record) {
            $rubric = self::rubric_array($record);
            $semantics = $rubric['cupkp_c3b_semantics'] ?? null;
            if (!is_array($semantics)) {
                $counts['legacy_without_c3b_semantics']++;
                continue;
            }
            $counts['with_c3b_semantics']++;
            $validation = self::validate_semantics($semantics);
            foreach ($validation['errors'] as $error) {
                $findings[] = [
                    'severity' => 'error',
                    'code' => 'invalid_c3b_semantics',
                    'message' => 'evidence ' . (int)$record->id . ': ' . $error,
                ];
            }
            $state = (string)($semantics['result_state'] ?? '');
            if (isset($counts['result_states'][$state])) {
                $counts['result_states'][$state]++;
            }
            $mode = (string)($semantics['performance_mode'] ?? '');
            if (isset($counts['performance_modes'][$mode])) {
                $counts['performance_modes'][$mode]++;
            }
            $direction = (string)($semantics['evidence_direction'] ?? '');
            if (isset($counts['directions'][$direction])) {
                $counts['directions'][$direction]++;
            }
            if (self::quality_complete($semantics['quality'] ?? null)) {
                $counts['quality_complete']++;
            }
            $sourcekey = is_array($semantics['source_key'] ?? null) ? $semantics['source_key'] : [];
            if (($sourcekey['history_contract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT) {
                $counts['history_v1_source_keys']++;
            }
            if (!empty($sourcekey['legacy_direct_capture'])) {
                $counts['legacy_direct_capture']++;
            }
        }

        $blocking = array_filter($findings, static function(array $finding): bool {
            return in_array($finding['severity'] ?? '', ['blocker', 'error'], true);
        });

        return [
            'type' => 'CupkpEvidenceSemanticsQualityStatus',
            'gate' => self::GATE,
            'status' => $blocking ? 'blocked' : 'frozen',
            'contract' => self::contract(),
            'dependencies' => [
                'c3' => $c3['status'] ?? null,
                'history_v1' => $history['status'] ?? null,
            ],
            'sample' => $counts,
            'findings' => $findings,
            'read_only' => true,
            'next_allowed_gate' => 'C4',
        ];
    }

    /**
     * Return semantics already present in rubric metadata.
     *
     * @param array $rubric
     * @return array
     */
    private static function existing_semantics(array $rubric): array {
        if (is_array($rubric['cupkp_c3b_semantics'] ?? null)) {
            return $rubric['cupkp_c3b_semantics'];
        }
        return [];
    }

    /**
     * Validate explicit semantic overrides before inferred defaults are applied.
     *
     * @param array $rubric
     * @return array
     */
    private static function validate_explicit_semantic_overrides(array $rubric): array {
        $errors = [];
        $warnings = [];
        $details = [];
        $existing = self::merge_top_level_semantic_overrides(self::existing_semantics($rubric), $rubric);

        $resultstate = self::normalized_existing_value($existing, ['result_state', 'result']);
        if ($resultstate !== '' && !isset(self::RESULT_STATES[$resultstate])) {
            $errors[] = 'Unsupported explicit C3B result state.';
            $details[] = self::detail('unsupported_explicit_result_state', 'error');
        }

        $performancemode = self::normalized_existing_value($existing, ['performance_mode', 'mode']);
        if (self::has_value($existing['performance_mode'] ?? ($existing['mode'] ?? null)) &&
                !isset(self::PERFORMANCE_MODES[$performancemode])) {
            $errors[] = 'Unsupported explicit C3B performance mode.';
            $details[] = self::detail('unsupported_explicit_performance_mode', 'error');
        }

        $direction = self::normalized_existing_value($existing, ['evidence_direction', 'direction', 'direct_inferred']);
        if ($direction !== '' && !isset(self::EVIDENCE_DIRECTIONS[$direction])) {
            $errors[] = 'Unsupported explicit C3B evidence direction.';
            $details[] = self::detail('unsupported_explicit_evidence_direction', 'error');
        }

        $role = self::normalized_existing_value($existing, ['evidence_role', 'role']);
        if ($role !== '' && !isset(self::EVIDENCE_ROLES[$role])) {
            $errors[] = 'Unsupported explicit C3B evidence role.';
            $details[] = self::detail('unsupported_explicit_evidence_role', 'error');
        }

        foreach (self::raw_quality_overrides($existing) as $dimension => $value) {
            $canonical = self::canonical_quality_dimension((string)$dimension);
            if ($canonical === '') {
                $errors[] = 'Unsupported explicit C3B quality dimension: ' . (string)$dimension . '.';
                $details[] = self::detail('unsupported_explicit_quality_dimension', 'error',
                    ['dimension' => (string)$dimension]);
                continue;
            }
            if (self::quality_value($canonical, $value) === null) {
                $errors[] = 'Invalid explicit C3B quality value for: ' . $canonical . '.';
                $details[] = self::detail('invalid_explicit_quality_value', 'error', ['dimension' => $canonical]);
            }
        }

        return self::result($errors, $warnings, $details);
    }

    /**
     * Preserve explicit top-level metadata keys used by older importers.
     *
     * @param array $existing
     * @param array $rubric
     * @return array
     */
    private static function merge_top_level_semantic_overrides(array $existing, array $rubric): array {
        foreach ([
            'result_state',
            'performance_mode',
            'evidence_direction',
            'evidence_role',
            'quality',
            'quality_dimensions',
            'inference_path',
        ] as $key) {
            if (!array_key_exists($key, $existing) && array_key_exists($key, $rubric)) {
                $existing[$key] = $rubric[$key];
            }
        }
        return $existing;
    }

    /**
     * Infer a canonical C3B evidence role.
     *
     * @param \stdClass $evidence
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @param array $sourcekey
     * @param array $existing
     * @return string
     */
    private static function infer_evidence_role(\stdClass $evidence, ?\stdClass $object, ?\stdClass $map,
            array $sourcekey, array $existing): string {
        $explicit = self::normalized_existing_value($existing, ['evidence_role', 'role']);
        if (isset(self::EVIDENCE_ROLES[$explicit])) {
            return $explicit;
        }

        $source = (string)($sourcekey['source_type'] ?? content_evidence_mapping_contract::source_type_for_evidence_payload($evidence));
        if ($source === 'completion') {
            return 'learning_signal';
        }
        if ($source === 'teacher_observation') {
            return 'teacher_evidence';
        }
        if ($source === 'placement') {
            return 'placement_evidence';
        }
        if ($source === 'checkpoint') {
            return 'checkpoint_evidence';
        }
        if ($source === 'external_assessment') {
            return 'external_evidence';
        }

        if ($object !== null && $map !== null) {
            $role = content_evidence_mapping_contract::canonical_pedagogical_role(
                (string)($map->role ?? ''),
                (string)($object->purpose ?? ''),
                (string)($object->objecttype ?? '')
            );
            if (in_array($role, ['ASSESSES', 'EVIDENCE_FOR'], true)) {
                return 'assessment_evidence';
            }
        }
        return 'practice_evidence';
    }

    /**
     * Return a normalized performance mode from existing C-UP-KP labels.
     *
     * @param string $value
     * @return string
     */
    private static function normalize_performance_mode(string $value): string {
        $label = self::normalize_label($value);
        $aliases = [
            'exposure' => 'passive_exposure',
            'passive' => 'passive_exposure',
            'passive_completion' => 'passive_exposure',
            'indirect_signal' => 'passive_exposure',
            'weak' => 'passive_exposure',
            'medium' => 'comprehension',
            'strong' => 'independent_production',
            'diagnostic' => 'selection',
            'checkpoint' => 'selection',
            'controlled_production' => 'guided_production',
            'controlled_performance' => 'guided_production',
            'guided_performance' => 'guided_production',
            'direct_performance' => 'independent_production',
            'independent_performance' => 'independent_production',
            'transfer_performance' => 'transfer',
            'interaction_performance' => 'interaction',
        ];
        if (isset($aliases[$label])) {
            return $aliases[$label];
        }
        return isset(self::PERFORMANCE_MODES[$label]) ? $label : '';
    }

    /**
     * Baseline quality by C3 source type.
     *
     * @param string $source
     * @return array
     */
    private static function quality_baseline(string $source): array {
        $baselines = [
            'completion' => [0.35, 0.60, 0.25, 0.20, 0.10, 0.05, 0.25, 0.20, 0.75, 0.55],
            'program2_attempt' => [0.70, 0.75, 0.60, 0.55, 0.45, 0.35, 0.60, 0.50, 0.75, 0.70],
            'grade_linked_assessment' => [0.80, 0.75, 0.70, 0.65, 0.60, 0.45, 0.70, 0.55, 0.75, 0.75],
            'teacher_observation' => [0.75, 0.60, 0.75, 0.85, 0.75, 0.60, 0.75, 0.55, 0.75, 0.70],
            'placement' => [0.70, 0.70, 0.65, 0.55, 0.45, 0.35, 0.65, 0.55, 0.75, 0.70],
            'checkpoint' => [0.78, 0.72, 0.68, 0.60, 0.55, 0.45, 0.68, 0.55, 0.75, 0.72],
            'external_assessment' => [0.78, 0.68, 0.70, 0.65, 0.65, 0.50, 0.70, 0.60, 0.75, 0.68],
        ];
        $values = $baselines[$source] ?? $baselines['external_assessment'];
        return array_combine(array_keys(self::QUALITY_DIMENSIONS), $values);
    }

    /**
     * Adjust quality dimensions by performance mode.
     *
     * @param array $quality
     * @param string $mode
     */
    private static function apply_mode_adjustments(array &$quality, string $mode): void {
        $adjustments = [
            'passive_exposure' => ['production_demand' => 0.10, 'contextual_transfer' => 0.05, 'independence' => 0.25],
            'recognition' => ['production_demand' => 0.15, 'contextual_transfer' => 0.15],
            'comprehension' => ['production_demand' => 0.20, 'contextual_transfer' => 0.25],
            'selection' => ['production_demand' => 0.18, 'contextual_transfer' => 0.25],
            'controlled_recall' => ['production_demand' => 0.35, 'support_level' => 0.45],
            'guided_production' => ['production_demand' => 0.55, 'support_level' => 0.55],
            'independent_production' => ['production_demand' => 0.75, 'independence' => 0.80, 'support_level' => 0.80],
            'interaction' => ['production_demand' => 0.85, 'authenticity' => 0.85, 'support_level' => 0.75],
            'transfer' => ['contextual_transfer' => 0.90, 'authenticity' => 0.85, 'support_level' => 0.85],
        ];
        foreach ($adjustments[$mode] ?? [] as $dimension => $value) {
            $quality[$dimension] = max((float)$quality[$dimension], (float)$value);
        }
    }

    /**
     * Apply retry/hint semantics to quality dimensions.
     *
     * @param array $quality
     * @param array $attempt
     */
    private static function apply_retry_adjustments(array &$quality, array $attempt): void {
        if (empty($attempt['hint_or_answer_exposure'])) {
            return;
        }
        $quality['independence'] *= 0.65;
        $quality['support_level'] *= 0.60;
        $quality['confidence'] *= 0.85;
    }

    /**
     * Explicit quality overrides from C3B rubric metadata.
     *
     * @param array $existing
     * @return array
     */
    private static function quality_overrides(array $existing): array {
        $out = [];
        foreach (self::raw_quality_overrides($existing) as $dimension => $value) {
            $dimension = self::canonical_quality_dimension((string)$dimension);
            if ($dimension === '') {
                continue;
            }
            $normalized = self::quality_value($dimension, $value);
            if ($normalized !== null) {
                $out[$dimension] = $normalized;
            }
        }
        return $out;
    }

    /**
     * Raw quality override map from existing semantics.
     *
     * @param array $existing
     * @return array
     */
    private static function raw_quality_overrides(array $existing): array {
        foreach (['quality', 'quality_dimensions'] as $key) {
            if (is_array($existing[$key] ?? null)) {
                return $existing[$key];
            }
        }
        return [];
    }

    /**
     * Canonical quality dimension label.
     *
     * @param string $dimension
     * @return string
     */
    private static function canonical_quality_dimension(string $dimension): string {
        $dimension = self::normalize_label($dimension);
        $aliases = [
            'transfer' => 'contextual_transfer',
            'support' => 'support_level',
        ];
        $dimension = $aliases[$dimension] ?? $dimension;
        return isset(self::QUALITY_DIMENSIONS[$dimension]) ? $dimension : '';
    }

    /**
     * Normalize a quality value, including conceptual support/transfer labels.
     *
     * @param string $dimension
     * @param mixed $value
     * @return float|null
     */
    private static function quality_value(string $dimension, $value): ?float {
        if (is_numeric($value)) {
            return self::clamp01((float)$value);
        }
        $label = self::normalize_label((string)$value);
        if ($dimension === 'support_level') {
            $support = [
                'full_model' => 0.10,
                'partial_model' => 0.35,
                'prompted' => 0.50,
                'hinted' => 0.65,
                'unsupported' => 0.90,
            ];
            return $support[$label] ?? null;
        }
        if ($dimension === 'contextual_transfer') {
            $transfer = [
                'same_context' => 0.25,
                'near_transfer' => 0.55,
                'far_transfer' => 0.80,
                'real_world' => 0.95,
            ];
            return $transfer[$label] ?? null;
        }
        return null;
    }

    /**
     * Summarize overall evidence integrity without making a mastery decision.
     *
     * @param array $quality
     * @return float
     */
    private static function quality_integrity_score(array $quality): float {
        $dimensions = ['validity', 'reliability', 'independence', 'authenticity', 'confidence'];
        $sum = 0.0;
        foreach ($dimensions as $dimension) {
            $sum += (float)($quality[$dimension] ?? 0);
        }
        return round($sum / count($dimensions), 5);
    }

    /**
     * Advisory ceiling from performance mode and target type.
     *
     * @param string $targettype
     * @param string $mode
     * @param string $resultstate
     * @param string $source
     * @return array
     */
    private static function evidence_ceiling_hint(string $targettype, string $mode, string $resultstate,
            string $source): array {
        if ($resultstate === 'inconclusive') {
            $claim = 'no_positive_or_negative_mastery_claim';
        } else if ($source === 'completion' || in_array($mode, ['passive_exposure', 'recognition', 'selection'], true)) {
            $claim = in_array($targettype, ['up', 'competency'], true) ?
                'cannot_establish_higher_order_productive_mastery' : 'lower_order_support_only';
        } else if (in_array($mode, ['interaction', 'transfer'], true)) {
            $claim = 'may_support_high_order_mastery_when_mastery_policy_agrees';
        } else if ($mode === 'independent_production') {
            $claim = 'may_support_productive_mastery_when_mastery_policy_agrees';
        } else {
            $claim = 'may_support_intermediate_mastery_when_mastery_policy_agrees';
        }

        return [
            'claim' => $claim,
            'source_type' => $source,
            'performance_mode' => $mode,
            'target_entity_type' => $targettype,
            'policy_boundary' => 'advisory_only_until_mastery_policy_gate',
        ];
    }

    /**
     * Attempt/retry semantics from evidence metadata.
     *
     * @param \stdClass $evidence
     * @param array $rubric
     * @return array
     */
    private static function attempt_semantics(\stdClass $evidence, array $rubric): array {
        $attemptnumber = self::first_non_empty([
            $rubric['attempt_number'] ?? null,
            $rubric['attemptnumber'] ?? null,
            $rubric['attempt_sequence'] ?? null,
            $rubric['attemptsequence'] ?? null,
        ]);
        $hinted = self::truthy($rubric['hint_shown'] ?? null) ||
            self::truthy($rubric['hintshown'] ?? null) ||
            self::truthy($rubric['hints_used'] ?? null) ||
            self::truthy($rubric['answer_exposed'] ?? null) ||
            self::truthy($rubric['model_shown'] ?? null) ||
            self::truthy($rubric['retry_after_hint'] ?? null);

        return [
            'source_attempt_id' => (string)($evidence->sourceattempt ?? ''),
            'attempt_number' => $attemptnumber === null ? null : (int)$attemptnumber,
            'hint_or_answer_exposure' => $hinted,
            'preserve_attempts' => true,
            'retry_collapse_allowed' => false,
        ];
    }

    /**
     * Inference path for inferred evidence.
     *
     * @param \stdClass $evidence
     * @param array $sourcekey
     * @param array $rubric
     * @return array
     */
    private static function inference_path(\stdClass $evidence, array $sourcekey, array $rubric): array {
        $existing = $rubric['cupkp_c3b_semantics']['inference_path'] ?? ($rubric['inference_path'] ?? null);
        if (is_array($existing) && !empty($existing)) {
            return $existing;
        }
        return [[
            'source' => self::filter_empty([
                'history_source_key' => $sourcekey['history_source_key'] ?? null,
                'source_attempt_id' => $sourcekey['source_attempt_id'] ?? null,
                'source_type' => $sourcekey['source_type'] ?? null,
            ]),
            'relationship' => 'INFERRED_EVIDENCE_FOR',
            'target_entity_type' => (string)($evidence->targettype ?? ''),
            'target_entity_id' => (int)($evidence->targetid ?? 0),
        ]];
    }

    /**
     * Decode an evidence row's rubric metadata.
     *
     * @param \stdClass $evidence
     * @return array
     */
    private static function rubric_array(\stdClass $evidence): array {
        $ignorederrors = [];
        $ignoreddetails = [];
        return self::decode_metadata($evidence->rubricjson ?? [], $ignorederrors, $ignoreddetails);
    }

    /**
     * Get source-key overrides.
     *
     * @param array $rubric
     * @return array
     */
    private static function source_key_overrides(array $rubric): array {
        if (is_array($rubric['cupkp_c3b_semantics']['source_key'] ?? null)) {
            return $rubric['cupkp_c3b_semantics']['source_key'];
        }
        if (is_array($rubric['history_source'] ?? null)) {
            return $rubric['history_source'];
        }
        if (is_array($rubric['source_key'] ?? null)) {
            return $rubric['source_key'];
        }
        return [];
    }

    /**
     * Whether source metadata already looks like History V1.
     *
     * @param string $provenance
     * @param string $sourceref
     * @param array $existing
     * @return bool
     */
    private static function looks_like_history_v1_source(string $provenance, string $sourceref, array $existing): bool {
        if (array_key_exists('legacy_direct_capture', $existing)) {
            return empty($existing['legacy_direct_capture']);
        }
        if (($existing['history_contract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT ||
                ($existing['normal_source_rule'] ?? '') === history_v1_consumer_contract::CONSUMPTION_RULE) {
            return true;
        }
        $haystack = self::normalize_label($provenance . ' ' . $sourceref . ' ' .
            (string)($existing['history_source_key'] ?? ''));
        return strpos($haystack, 'local_flwhistory') !== false ||
            strpos($haystack, 'flwhistory') !== false ||
            strpos($haystack, 'history_v1') !== false;
    }

    /**
     * Explicit result-state or failure signals.
     *
     * @param \stdClass $evidence
     * @param array $existing
     * @return bool
     */
    private static function explicit_inconclusive_signal(\stdClass $evidence, array $existing): bool {
        $rubric = self::rubric_array($evidence);
        foreach (['technical_failure', 'microphone_failure', 'stt_uncertain', 'abandoned', 'invalid_task_execution'] as $key) {
            if (self::truthy($rubric[$key] ?? ($existing[$key] ?? null))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether normalized score is available on the payload.
     *
     * @param \stdClass $evidence
     * @return bool
     */
    private static function score_available(\stdClass $evidence): bool {
        return property_exists($evidence, 'normalizedscore') && $evidence->normalizedscore !== null &&
            $evidence->normalizedscore !== '';
    }

    /**
     * Difficulty from object metadata.
     *
     * @param \stdClass|null $object
     * @param float $default
     * @return float
     */
    private static function difficulty_from_object(?\stdClass $object, float $default): float {
        if ($object === null) {
            return $default;
        }
        $ignorederrors = [];
        $ignoreddetails = [];
        $metadata = self::decode_metadata($object->metadatajson ?? [], $ignorederrors, $ignoreddetails);
        foreach (['difficulty', 'task_difficulty', 'estimated_difficulty'] as $key) {
            if (is_numeric($metadata[$key] ?? null)) {
                return self::clamp01((float)$metadata[$key]);
            }
        }
        return $default;
    }

    /**
     * Recency at recording time from occurred/recorded fields.
     *
     * @param \stdClass $evidence
     * @return float
     */
    private static function recency_from_evidence(\stdClass $evidence): float {
        $rubric = self::rubric_array($evidence);
        $occurred = self::occurred_at($evidence, $rubric);
        $recorded = self::recorded_at($evidence, $rubric);
        if (!$occurred || !$recorded) {
            return 0.75;
        }
        $lagdays = max(0, (int)floor(((int)$recorded - (int)$occurred) / DAYSECS));
        if ($lagdays <= 1) {
            return 1.0;
        }
        if ($lagdays <= 7) {
            return 0.85;
        }
        if ($lagdays <= 30) {
            return 0.65;
        }
        if ($lagdays <= 90) {
            return 0.45;
        }
        return 0.25;
    }

    /**
     * Confidence from the evidence row.
     *
     * @param \stdClass $evidence
     * @param float $default
     * @return float
     */
    private static function confidence_from_evidence(\stdClass $evidence, float $default): float {
        if (property_exists($evidence, 'confidence') && is_numeric($evidence->confidence)) {
            return self::clamp01((float)$evidence->confidence);
        }
        return $default;
    }

    /**
     * Occurred-at timestamp from rubric or evidence.
     *
     * @param \stdClass $evidence
     * @param array $rubric
     * @return int|null
     */
    private static function occurred_at(\stdClass $evidence, array $rubric): ?int {
        $value = self::first_non_empty([
            $rubric['occurred_at'] ?? null,
            $rubric['timeoccurred'] ?? null,
            $rubric['source_timecreated'] ?? null,
            $evidence->timecreated ?? null,
        ]);
        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Recorded-at timestamp from rubric or evidence.
     *
     * @param \stdClass $evidence
     * @param array $rubric
     * @return int|null
     */
    private static function recorded_at(\stdClass $evidence, array $rubric): ?int {
        $value = self::first_non_empty([
            $rubric['recorded_at'] ?? null,
            $rubric['timerecorded'] ?? null,
            $evidence->timecreated ?? null,
        ]);
        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Curriculum version metadata.
     *
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @param array $rubric
     * @return string
     */
    private static function curriculum_version(?\stdClass $object, ?\stdClass $map, array $rubric): string {
        $value = self::first_non_empty([
            $rubric['curriculum_version'] ?? null,
            $rubric['framework_version'] ?? null,
            $object->version ?? null,
            $map->version ?? null,
        ]);
        return $value === null ? 'unversioned' : (string)$value;
    }

    /**
     * Whether an object/map implies production-oriented performance.
     *
     * @param \stdClass|null $object
     * @param \stdClass|null $map
     * @return bool
     */
    private static function object_implies_production(?\stdClass $object, ?\stdClass $map): bool {
        $text = self::normalize_label(
            (string)($object->purpose ?? '') . ' ' .
            (string)($object->objecttype ?? '') . ' ' .
            (string)($object->title ?? '') . ' ' .
            (string)($map->role ?? '') . ' ' .
            (string)($map->evidencestrength ?? '')
        );
        foreach (['production', 'performance', 'interaction', 'speaking', 'writing', 'stt_task', 'project'] as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return normalized existing semantics value.
     *
     * @param array $existing
     * @param array $keys
     * @return string
     */
    private static function normalized_existing_value(array $existing, array $keys): string {
        foreach ($keys as $key) {
            if (self::has_value($existing[$key] ?? null)) {
                if (in_array($key, ['performance_mode', 'mode'], true)) {
                    return self::normalize_performance_mode((string)$existing[$key]);
                }
                return self::normalize_label((string)$existing[$key]);
            }
        }
        return '';
    }

    /**
     * Return first present value from an associative array.
     *
     * @param array $row
     * @param array $keys
     * @return mixed|null
     */
    private static function first_value(array $row, array $keys) {
        foreach ($keys as $key) {
            if (self::has_value($row[$key] ?? null)) {
                return $row[$key];
            }
        }
        return null;
    }

    /**
     * Return the first non-empty value.
     *
     * @param array $values
     * @return mixed|null
     */
    private static function first_non_empty(array $values) {
        foreach ($values as $value) {
            if (self::has_value($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Decode JSON/array metadata.
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
            $errors[] = 'rubricjson must be valid JSON.';
        }
        if ($details !== null) {
            $details[] = self::detail('invalid_rubric_json', 'error');
        }
        return [];
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
            'policy_version' => self::EVIDENCE_POLICY_VERSION,
        ];
    }

    /**
     * Merge validation results.
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
     * Whether all quality dimensions are present.
     *
     * @param mixed $quality
     * @return bool
     */
    private static function quality_complete($quality): bool {
        if (!is_array($quality)) {
            return false;
        }
        foreach (array_keys(self::QUALITY_DIMENSIONS) as $dimension) {
            if (!array_key_exists($dimension, $quality) || !is_numeric($quality[$dimension])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Filter only empty null/string/array values while preserving false and zero.
     *
     * @param array $data
     * @return array
     */
    private static function filter_empty(array $data): array {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * Normalized truthy metadata.
     *
     * @param mixed $value
     * @return bool
     */
    private static function truthy($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float)$value > 0;
        }
        return in_array(self::normalize_label((string)$value), ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * Clamp a value to 0..1.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
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
     * Whether a value is present.
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

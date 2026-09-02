<?php
// Program 3 Gate E1 History V1 to C-UP-KP evidence adapter.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts trusted History V1 facts into derived C-UP-KP evidence.
 */
final class history_evidence_adapter {
    /** Program 3 evidence adapter gate. */
    public const GATE = 'P3_E1';

    /** Frozen adapter contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_HISTORY_EVIDENCE_ADAPTER_V1';

    /** C-UP-KP provenance marker for derived History V1 evidence. */
    public const PROVENANCE = 'local_flwhistory_history_v1';

    /** @var string History source adapter class name. */
    private const HISTORY_ADAPTER = '\\local_flwhistory\\local\\evidence_source_adapter';

    /** @var array Fact types that E1 may turn into evidence. */
    private const SUPPORTED_FACT_TYPES = ['attempts', 'completion'];

    /** @var array History adapter methods by E1 fact type. */
    private const HISTORY_METHODS = [
        'attempts' => 'attempts_for_course',
        'completion' => 'completions_for_course',
    ];

    /**
     * Return the E1 adapter contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpHistoryEvidenceAdapterContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                management_v1_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
                content_evidence_mapping_contract::CONTRACT_VERSION,
                evidence_semantics_quality_contract::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'source_adapter' => self::HISTORY_ADAPTER,
            'supported_fact_types' => self::SUPPORTED_FACT_TYPES,
            'preserved_read_only_fact_types' => ['source_events', 'grades', 'placement', 'content_identities'],
            'chain' => [
                'program2_history_fact',
                'program1_content_identity',
                'cupkp_object_mapping',
                'cupkp_evidence_event',
            ],
            'missing_mapping_rule' => [
                'preserve_source_history' => true,
                'fabricate_evidence' => false,
                'mark_unresolved_mapping' => true,
            ],
            'reprocessing' => [
                'preview_is_read_only' => true,
                'apply_is_controlled' => true,
                'idempotency_key' => 'history_source_key + fact_type + object + target + mapping fingerprint + adapter/evidence policy versions',
                'raw_program2_history_unchanged' => true,
                'old_derived_meaning_auditable' => true,
            ],
            'write_boundary' => [
                'writer' => 'mastery_engine::record_evidence',
                'direct_table_mutation' => false,
                'state_updates' => 'existing_canonical_evidence_writer_only',
            ],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'adaptive_path_selection',
                'mastery_threshold_changes',
                'grade_version_mastery_collapse',
                'content_title_matching',
                'history_v1_source_mutation',
            ],
            'state_changes_allowed' => false,
        ];
    }

    /**
     * Return E1 readiness and bounded source-fact summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = self::bounded_limit($limit, 300);
        $management = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return management_v1_contract::management_status($courseid, $unitcode, $frameworkid, $limit);
        });
        $history = self::safe_status_call(static function() use ($courseid): array {
            return history_v1_consumer_contract::contract_status($courseid, 1);
        });
        $files = self::file_status();
        $source = self::source_adapter_status($courseid);
        $criteria = self::status_criteria($management, $history, $files, $source);
        $summary = self::criteria_summary($criteria);
        $findings = self::status_findings($criteria, $management, $history, $source);

        return [
            'type' => 'CupkpHistoryEvidenceAdapterStatus',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $summary,
            'management_v1' => [
                'status' => $management['status'] ?? 'unknown',
                'contract' => $management['contract']['version'] ?? null,
                'next_allowed_gate' => $management['next_allowed_gate'] ?? null,
            ],
            'history_v1' => [
                'status' => $history['status'] ?? 'unknown',
                'requiredcontract' => $history['requiredcontract'] ?? null,
                'contractavailable' => $history['contractavailable'] ?? false,
            ],
            'source_adapter' => $source,
            'files' => $files,
            'findings' => $findings,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Preview controlled reprocessing without mutating C-UP-KP evidence, state, or audit rows.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $facttypes
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function preview_reprocess(int $courseid, string $unitcode = '', int $frameworkid = 0,
            array $facttypes = [], int $limit = 100, int $offset = 0): array {
        return self::process_reprocess($courseid, $unitcode, $frameworkid, $facttypes, $limit, $offset, false, '');
    }

    /**
     * Apply controlled reprocessing and write idempotent derived evidence.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $facttypes
     * @param int $limit
     * @param int $offset
     * @param string $reason
     * @return array
     */
    public static function apply_reprocess(int $courseid, string $unitcode = '', int $frameworkid = 0,
            array $facttypes = [], int $limit = 100, int $offset = 0, string $reason = ''): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $offset = max(0, $offset);
        $facttypes = self::normalize_fact_types($facttypes);
        $requestid = repository::audit('history_evidence_reprocess_requested', 'course', $courseid, [
            'gate' => self::GATE,
            'adapter_contract' => self::CONTRACT_VERSION,
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'facttypes' => $facttypes,
            'limit' => $limit,
            'offset' => $offset,
            'reason' => $reason,
        ]);

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = self::process_reprocess($courseid, $unitcode, $frameworkid, $facttypes, $limit, $offset, true,
                $reason);
            $result['request_audit_id'] = $requestid;
            repository::audit('history_evidence_reprocess_completed', 'course', $courseid, [
                'request_audit_id' => $requestid,
                'gate' => self::GATE,
                'adapter_contract' => self::CONTRACT_VERSION,
                'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'summary' => $result['summary'],
                'created_evidenceids' => array_slice($result['created_evidenceids'], 0, 50),
                'unresolved_sample' => array_slice($result['unresolved'], 0, 20),
                'rejected_map_sample' => array_slice($result['rejectedmaps'], 0, 20),
            ]);
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $ignored) {
                // The original exception is rethrown after the failure audit is recorded.
            }
            repository::audit('history_evidence_reprocess_failed', 'course', $courseid, [
                'request_audit_id' => $requestid,
                'gate' => self::GATE,
                'adapter_contract' => self::CONTRACT_VERSION,
                'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Return recent E1 reprocessing audit rows.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function recent_reprocess_history(int $courseid = 0, string $unitcode = '', int $limit = 20): array {
        global $DB;

        $limit = self::bounded_limit($limit, 100);
        $actions = [
            'history_evidence_reprocess_requested',
            'history_evidence_reprocess_completed',
            'history_evidence_reprocess_failed',
        ];
        list($insql, $params) = $DB->get_in_or_equal($actions, SQL_PARAMS_NAMED, 'action');
        $where = "action {$insql}";
        if ($courseid > 0) {
            $where .= ' AND targettype = :targettype AND targetid = :targetid';
            $params['targettype'] = 'course';
            $params['targetid'] = $courseid;
        }
        $records = $DB->get_records_select('flwcupkp_audit', $where, $params, 'timecreated DESC, id DESC', '*', 0,
            $limit);
        $rows = [];
        foreach ($records as $record) {
            $details = json_decode((string)$record->detailsjson, true);
            if (!is_array($details)) {
                $details = [];
            }
            if ($unitcode !== '' && isset($details['unitcode']) && (string)$details['unitcode'] !== $unitcode) {
                continue;
            }
            $rows[] = [
                'id' => (int)$record->id,
                'action' => (string)$record->action,
                'targettype' => (string)($record->targettype ?? ''),
                'targetid' => isset($record->targetid) ? (int)$record->targetid : null,
                'userid' => isset($record->userid) ? (int)$record->userid : null,
                'timecreated' => (int)$record->timecreated,
                'details' => $details,
            ];
        }
        return $rows;
    }

    /**
     * Shared preview/apply implementation.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $facttypes
     * @param int $limit
     * @param int $offset
     * @param bool $write
     * @param string $reason
     * @return array
     */
    private static function process_reprocess(int $courseid, string $unitcode, int $frameworkid, array $facttypes,
            int $limit, int $offset, bool $write, string $reason): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('A course ID is required for History V1 evidence reprocessing.');
        }

        $limit = self::bounded_limit($limit, 500);
        $offset = max(0, $offset);
        $facttypes = self::normalize_fact_types($facttypes);
        $status = self::status($courseid, $unitcode, $frameworkid, min($limit, 300));
        if (($status['status'] ?? '') === 'blocked') {
            return [
                'type' => 'CupkpHistoryEvidenceReprocessResult',
                'gate' => self::GATE,
                'status' => 'blocked',
                'mode' => $write ? 'apply' : 'preview',
                'summary' => [
                    'records_seen' => 0,
                    'resolved_facts' => 0,
                    'planned' => 0,
                    'created' => 0,
                    'existing' => 0,
                    'unresolved' => 0,
                    'skipped' => 0,
                    'rejected_maps' => 0,
                ],
                'plans' => [],
                'created_evidenceids' => [],
                'unresolved' => [],
                'skipped' => [],
                'rejectedmaps' => [],
                'findings' => $status['findings'] ?? [],
                'read_only' => !$write,
            ];
        }

        $context = [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'limit' => $limit,
            'offset' => $offset,
            'write' => $write,
            'reason' => $reason,
            'content_identities' => self::content_identity_index($courseid),
        ];

        $summary = [
            'records_seen' => 0,
            'resolved_facts' => 0,
            'planned' => 0,
            'created' => 0,
            'existing' => 0,
            'unresolved' => 0,
            'skipped' => 0,
            'rejected_maps' => 0,
            'history_totals' => [],
        ];
        $plans = [];
        $createdids = [];
        $unresolved = [];
        $skipped = [];
        $rejectedmaps = [];

        foreach ($facttypes as $facttype) {
            $payload = self::history_payload($facttype, $courseid, $limit, $offset);
            $summary['history_totals'][$facttype] = (int)($payload['pagination']['total'] ?? 0);
            foreach (($payload['records'] ?? []) as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                $summary['records_seen']++;
                $result = self::process_fact($facttype, $fact, $context);
                if (!empty($result['resolved'])) {
                    $summary['resolved_facts']++;
                }
                foreach ($result['plans'] as $plan) {
                    $plans[] = $plan;
                    $summary['planned']++;
                    if (($plan['status'] ?? '') === 'existing') {
                        $summary['existing']++;
                    } else if (($plan['status'] ?? '') === 'created') {
                        $summary['created']++;
                    }
                    if (!empty($plan['evidenceid'])) {
                        $createdids[] = (int)$plan['evidenceid'];
                    }
                }
                foreach ($result['unresolved'] as $row) {
                    $unresolved[] = $row;
                    $summary['unresolved']++;
                }
                foreach ($result['skipped'] as $row) {
                    $skipped[] = $row;
                    $summary['skipped']++;
                }
                foreach ($result['rejectedmaps'] as $row) {
                    $rejectedmaps[] = $row;
                    $summary['rejected_maps']++;
                }
            }
        }

        return [
            'type' => 'CupkpHistoryEvidenceReprocessResult',
            'gate' => self::GATE,
            'adapter_contract' => self::CONTRACT_VERSION,
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'status' => 'processed',
            'mode' => $write ? 'apply' : 'preview',
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'facttypes' => $facttypes,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'summary' => $summary,
            'plans' => $plans,
            'created_evidenceids' => $createdids,
            'unresolved' => $unresolved,
            'skipped' => $skipped,
            'rejectedmaps' => $rejectedmaps,
            'read_only' => !$write,
            'state_changes_allowed' => false,
            'next_allowed_gate' => 'E2',
        ];
    }

    /**
     * Process a single History V1 fact.
     *
     * @param string $facttype
     * @param array $fact
     * @param array $context
     * @return array
     */
    private static function process_fact(string $facttype, array $fact, array $context): array {
        global $DB;

        $result = [
            'resolved' => false,
            'plans' => [],
            'unresolved' => [],
            'skipped' => [],
            'rejectedmaps' => [],
        ];
        $score = self::score_for_fact($facttype, $fact);
        if ($score['status'] !== 'ok') {
            $result['skipped'][] = self::fact_summary($facttype, $fact, $score['reason']);
            return $result;
        }

        $objects = self::candidate_objects($fact, $context);
        if (!$objects) {
            $result['unresolved'][] = self::fact_summary($facttype, $fact, 'no_cupkp_object_for_history_fact');
            return $result;
        }

        foreach ($objects as $object) {
            try {
                evidence_guard::assert_object_scope($object, (int)$context['courseid'], (string)$context['unitcode']);
                evidence_guard::assert_user_enrolled_for_course((int)($fact['userid'] ?? 0), (int)$context['courseid']);
            } catch (\invalid_parameter_exception $e) {
                $result['unresolved'][] = self::fact_summary($facttype, $fact, 'evidence_scope_rejected', [
                    'objectid' => (int)$object->id,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => (int)$object->id],
                'targettype ASC, targetid ASC');
            if (!$maps) {
                $result['unresolved'][] = self::fact_summary($facttype, $fact, 'object_has_no_targets', [
                    'objectid' => (int)$object->id,
                ]);
                continue;
            }

            $result['resolved'] = true;
            $sourcetype = $facttype === 'completion' ? 'completion' : 'program2_attempt';
            foreach ($maps as $map) {
                try {
                    evidence_guard::assert_object_map($object, $map);
                    content_evidence_mapping_contract::assert_source_can_count($sourcetype, $object, $map);
                } catch (\invalid_parameter_exception $e) {
                    $result['rejectedmaps'][] = self::fact_summary($facttype, $fact, 'map_rejected', [
                        'objectid' => (int)$object->id,
                        'mapid' => (int)$map->id,
                        'targettype' => (string)$map->targettype,
                        'targetid' => (int)$map->targetid,
                        'message' => $e->getMessage(),
                    ]);
                    continue;
                }

                $evidence = self::evidence_payload_for_map($facttype, $fact, $score, $object, $map, $context);
                $existingid = $DB->get_field('flwcupkp_evidence', 'id', [
                    'objectid' => (int)$object->id,
                    'sourceattempt' => $evidence['sourceattempt'],
                    'targettype' => (string)$map->targettype,
                    'targetid' => (int)$map->targetid,
                ], IGNORE_MISSING);
                if ($existingid) {
                    $result['plans'][] = self::plan_row($facttype, $fact, $object, $map, $evidence, 'existing',
                        (int)$existingid);
                    continue;
                }

                if (empty($context['write'])) {
                    $result['plans'][] = self::plan_row($facttype, $fact, $object, $map, $evidence, 'would_create');
                    continue;
                }

                try {
                    $created = mastery_engine::record_evidence((object)$evidence);
                    $result['plans'][] = self::plan_row($facttype, $fact, $object, $map, $evidence, 'created',
                        (int)$created['evidenceid']);
                } catch (\Throwable $e) {
                    $result['rejectedmaps'][] = self::fact_summary($facttype, $fact, 'evidence_write_rejected', [
                        'objectid' => (int)$object->id,
                        'mapid' => (int)$map->id,
                        'targettype' => (string)$map->targettype,
                        'targetid' => (int)$map->targetid,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Build a normalized evidence payload for one object mapping.
     *
     * @param string $facttype
     * @param array $fact
     * @param array $score
     * @param \stdClass $object
     * @param \stdClass $map
     * @param array $context
     * @return array
     */
    private static function evidence_payload_for_map(string $facttype, array $fact, array $score, \stdClass $object,
            \stdClass $map, array $context): array {
        global $USER;

        $evidencetype = $facttype === 'completion' ? 'history_v1_completion' : 'history_v1_attempt';
        $sourceattempt = self::sourceattempt_key($facttype, $fact, $object, $map);
        $sourceref = self::sourceref($fact);
        $identity = content_evidence_mapping_contract::identity_from_object($object);
        $rubric = [
            'history_source' => [
                'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
                'adapter_contract' => self::CONTRACT_VERSION,
                'history_source_id' => $fact['sourceeventid'] ?? null,
                'history_source_key' => $fact['sourcekey'] ?? null,
                'source_fact_key' => $fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? null),
                'source_type' => $facttype === 'completion' ? 'completion' : 'program2_attempt',
                'source_attempt_id' => $fact['sourceattemptid'] ?? null,
                'source_ref' => $sourceref,
                'norm_policy_version' => $fact['normpolicyversion'] ?? null,
                'legacy_direct_capture' => false,
            ],
            'e1_reprocessing' => [
                'gate' => self::GATE,
                'adapter_contract' => self::CONTRACT_VERSION,
                'evidence_policy_version' => evidence_semantics_quality_contract::EVIDENCE_POLICY_VERSION,
                'mode' => empty($context['write']) ? 'preview' : 'apply',
                'reason' => (string)($context['reason'] ?? ''),
                'fact_type' => $facttype,
                'sourceattempt_key' => $sourceattempt,
                'objectid' => (int)$object->id,
                'mapid' => (int)$map->id,
                'mapping_fingerprint' => self::mapping_fingerprint($object, $map),
                'idempotency_hash' => self::idempotency_hash($facttype, $fact, $object, $map),
            ],
            'program1_identity' => $identity,
            'source_fact' => self::sanitized_fact($fact),
        ];

        return [
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)$context['courseid'],
            'unitcode' => (string)($object->unitcode ?? $context['unitcode']),
            'objectid' => (int)$object->id,
            'sourceattempt' => $sourceattempt,
            'evidencetype' => $evidencetype,
            'targettype' => (string)$map->targettype,
            'targetid' => (int)$map->targetid,
            'rawscore' => (float)$score['rawscore'],
            'normalizedscore' => self::clamp01((float)$score['normalizedscore']),
            'rubricjson' => json_encode($rubric, JSON_UNESCAPED_SLASHES),
            'assessortype' => $facttype === 'completion' ? 'history_v1_completion' : 'history_v1_attempt',
            'confidence' => $facttype === 'completion' ? 0.55 : 0.80,
            'evidencestrength' => self::evidence_strength($facttype, $object, $map),
            'provenance' => self::PROVENANCE,
            'sourceref' => $sourceref,
            'timecreated' => self::fact_time($facttype, $fact),
            'usermodified' => (int)($USER->id ?? 0),
        ];
    }

    /**
     * Resolve C-UP-KP learning objects for a History V1 fact.
     *
     * @param array $fact
     * @param array $context
     * @return array
     */
    private static function candidate_objects(array $fact, array $context): array {
        global $DB;

        $objects = [];
        $identity = self::matching_content_identity($fact, $context['content_identities']);
        $selectors = self::selector_values($fact, $identity);
        $scope = self::object_scope_where((int)$context['courseid'], (string)$context['unitcode'],
            (int)$context['frameworkid']);

        $cmid = (int)($selectors['cmid'] ?? 0);
        if ($cmid > 0) {
            $records = $DB->get_records_select('flwcupkp_object',
                $scope['where'] . ' AND cmid = :cmid',
                $scope['params'] + ['cmid' => $cmid],
                'id ASC'
            );
            self::merge_objects($objects, $records);
        }

        $stableids = self::stable_selector_strings($selectors);
        if ($stableids) {
            list($externalinsql, $externalparams) = $DB->get_in_or_equal($stableids, SQL_PARAMS_NAMED, 'exsid');
            list($sourceinsql, $sourceparams) = $DB->get_in_or_equal($stableids, SQL_PARAMS_NAMED, 'srcsid');
            $records = $DB->get_records_select('flwcupkp_object',
                $scope['where'] . " AND (externalid {$externalinsql} OR sourceid {$sourceinsql})",
                $scope['params'] + $externalparams + $sourceparams,
                'id ASC'
            );
            self::merge_objects($objects, $records);
        }

        $records = $DB->get_records_select('flwcupkp_object', $scope['where'], $scope['params'], 'id ASC', '*', 0, 500);
        foreach ($records as $record) {
            if (self::object_matches_selectors($record, $selectors)) {
                $objects[(int)$record->id] = $record;
            }
        }

        return array_values($objects);
    }

    /**
     * Return score information for a fact.
     *
     * @param string $facttype
     * @param array $fact
     * @return array
     */
    private static function score_for_fact(string $facttype, array $fact): array {
        if ($facttype === 'completion') {
            $state = (int)($fact['completionstate'] ?? 0);
            $complete = $state === self::completion_constant('COMPLETION_COMPLETE', 1) ||
                $state === self::completion_constant('COMPLETION_COMPLETE_PASS', 2);
            if (!$complete) {
                return ['status' => 'skip', 'reason' => 'completion_not_complete'];
            }
            return ['status' => 'ok', 'rawscore' => 1.0, 'normalizedscore' => 1.0];
        }

        $state = strtolower((string)($fact['attemptstate'] ?? ''));
        if (in_array($state, ['abandoned', 'inprogress', 'preview'], true)) {
            return ['status' => 'skip', 'reason' => 'attempt_not_finished'];
        }
        $scaled = self::float_or_null($fact['scaledscore'] ?? null);
        $raw = self::float_or_null($fact['rawscore'] ?? null);
        $max = self::float_or_null($fact['maxscore'] ?? null);
        if ($scaled !== null) {
            return ['status' => 'ok', 'rawscore' => $raw ?? $scaled, 'normalizedscore' => self::clamp01($scaled)];
        }
        if ($raw !== null && $max !== null && $max > 0) {
            return ['status' => 'ok', 'rawscore' => $raw, 'normalizedscore' => self::clamp01($raw / $max)];
        }
        if ($raw !== null && $raw >= 0 && $raw <= 1) {
            return ['status' => 'ok', 'rawscore' => $raw, 'normalizedscore' => self::clamp01($raw)];
        }
        return ['status' => 'skip', 'reason' => 'attempt_without_normalized_score'];
    }

    /**
     * Return the deterministic derived evidence key.
     *
     * @param string $facttype
     * @param array $fact
     * @param \stdClass $object
     * @param \stdClass $map
     * @return string
     */
    private static function sourceattempt_key(string $facttype, array $fact, \stdClass $object, \stdClass $map): string {
        return 'history_v1:' . self::idempotency_hash($facttype, $fact, $object, $map) . ':' .
            (string)$map->targettype . ':' . (int)$map->targetid;
    }

    /**
     * Return idempotency hash for one derived meaning.
     *
     * @param string $facttype
     * @param array $fact
     * @param \stdClass $object
     * @param \stdClass $map
     * @return string
     */
    private static function idempotency_hash(string $facttype, array $fact, \stdClass $object, \stdClass $map): string {
        return substr(sha1(implode('|', [
            self::CONTRACT_VERSION,
            history_v1_consumer_contract::REQUIRED_CONTRACT,
            evidence_semantics_quality_contract::EVIDENCE_POLICY_VERSION,
            $facttype,
            (string)($fact['sourcekey'] ?? ''),
            (string)($fact['sourcefactkey'] ?? ''),
            (int)$object->id,
            (int)$map->id,
            (string)$map->targettype,
            (int)$map->targetid,
            self::mapping_fingerprint($object, $map),
        ])), 0, 32);
    }

    /**
     * Return a stable fingerprint for the evidence meaning of one mapping.
     *
     * @param \stdClass $object
     * @param \stdClass $map
     * @return string
     */
    private static function mapping_fingerprint(\stdClass $object, \stdClass $map): string {
        return substr(sha1(json_encode([
            'object_externalid' => (string)($object->externalid ?? ''),
            'object_sourceid' => (string)($object->sourceid ?? ''),
            'object_cmid' => (int)($object->cmid ?? 0),
            'object_purpose' => (string)($object->purpose ?? ''),
            'object_evidencestrength' => (string)($object->evidencestrength ?? ''),
            'map_role' => (string)($map->role ?? ''),
            'map_evidencestrength' => (string)($map->evidencestrength ?? ''),
            'targettype' => (string)($map->targettype ?? ''),
            'targetid' => (int)($map->targetid ?? 0),
        ], JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    /**
     * Convert one plan into a UI/API-safe row.
     *
     * @param string $facttype
     * @param array $fact
     * @param \stdClass $object
     * @param \stdClass $map
     * @param array $evidence
     * @param string $status
     * @param int $evidenceid
     * @return array
     */
    private static function plan_row(string $facttype, array $fact, \stdClass $object, \stdClass $map, array $evidence,
            string $status, int $evidenceid = 0): array {
        return [
            'status' => $status,
            'facttype' => $facttype,
            'history_source_key' => (string)($fact['sourcekey'] ?? ''),
            'source_fact_key' => (string)($fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? '')),
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)($fact['courseid'] ?? 0),
            'cmid' => isset($fact['cmid']) ? (int)$fact['cmid'] : null,
            'unitid' => (string)($fact['unitid'] ?? ''),
            'activityid' => (string)($fact['activityid'] ?? ''),
            'assessmentid' => (string)($fact['assessmentid'] ?? ''),
            'objectid' => (int)$object->id,
            'object_externalid' => (string)$object->externalid,
            'object_title' => (string)$object->title,
            'mapid' => (int)$map->id,
            'targettype' => (string)$map->targettype,
            'targetid' => (int)$map->targetid,
            'sourceattempt' => (string)$evidence['sourceattempt'],
            'normalizedscore' => (float)$evidence['normalizedscore'],
            'rawscore' => (float)$evidence['rawscore'],
            'evidencetype' => (string)$evidence['evidencetype'],
            'evidenceid' => $evidenceid ?: null,
            'would_write' => $status === 'would_create',
        ];
    }

    /**
     * Return a compact fact summary for unresolved/skipped/rejected rows.
     *
     * @param string $facttype
     * @param array $fact
     * @param string $reason
     * @param array $extra
     * @return array
     */
    private static function fact_summary(string $facttype, array $fact, string $reason, array $extra = []): array {
        return $extra + [
            'facttype' => $facttype,
            'reason' => $reason,
            'history_source_key' => (string)($fact['sourcekey'] ?? ''),
            'source_fact_key' => (string)($fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? '')),
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)($fact['courseid'] ?? 0),
            'cmid' => isset($fact['cmid']) ? (int)$fact['cmid'] : null,
            'unitid' => (string)($fact['unitid'] ?? ''),
            'activityid' => (string)($fact['activityid'] ?? ''),
            'assessmentid' => (string)($fact['assessmentid'] ?? ''),
            'questionid' => (string)($fact['questionid'] ?? ''),
        ];
    }

    /**
     * Build selector values from a source fact plus History content identity.
     *
     * @param array $fact
     * @param array $identity
     * @return array
     */
    private static function selector_values(array $fact, array $identity): array {
        $selectors = [];
        foreach (['cmid', 'unitid', 'lessonid', 'componentid', 'activityid', 'assessmentid', 'questionid'] as $field) {
            $value = self::first_non_empty([$fact[$field] ?? null, $identity[$field] ?? null]);
            if ($value !== null && $value !== '') {
                $selectors[$field] = $field === 'cmid' ? (int)$value : (string)$value;
            }
        }
        foreach (['sourcekey', 'sourcefactkey', 'sourceid'] as $field) {
            if (!empty($fact[$field])) {
                $selectors[$field] = (string)$fact[$field];
            }
        }
        if (!empty($identity['sourcekey'])) {
            $selectors['content_sourcekey'] = (string)$identity['sourcekey'];
        }
        return $selectors;
    }

    /**
     * Get stable string selectors that can map object externalid/sourceid directly.
     *
     * @param array $selectors
     * @return array
     */
    private static function stable_selector_strings(array $selectors): array {
        $values = [];
        foreach (['activityid', 'assessmentid', 'questionid', 'sourceid', 'content_sourcekey'] as $field) {
            if (!empty($selectors[$field])) {
                $values[] = (string)$selectors[$field];
            }
        }
        return array_values(array_unique($values));
    }

    /**
     * Whether a C-UP-KP object matches History V1/Program 1 selectors.
     *
     * @param \stdClass $object
     * @param array $selectors
     * @return bool
     */
    private static function object_matches_selectors(\stdClass $object, array $selectors): bool {
        if (!empty($selectors['cmid']) && (int)($object->cmid ?? 0) === (int)$selectors['cmid']) {
            return true;
        }
        $direct = self::stable_selector_strings($selectors);
        if (in_array((string)($object->externalid ?? ''), $direct, true) ||
                in_array((string)($object->sourceid ?? ''), $direct, true)) {
            return true;
        }
        $identity = content_evidence_mapping_contract::identity_from_object($object);
        foreach (['sourcekey', 'activityid', 'assessmentid', 'questionid', 'cmid'] as $field) {
            if (!empty($identity[$field]) && !empty($selectors[$field]) &&
                    (string)$identity[$field] === (string)$selectors[$field]) {
                return true;
            }
        }
        if (!empty($identity['sourcekey']) && !empty($selectors['content_sourcekey']) &&
                (string)$identity['sourcekey'] === (string)$selectors['content_sourcekey']) {
            return true;
        }
        return false;
    }

    /**
     * Build scoped object WHERE clause.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function object_scope_where(int $courseid, string $unitcode, int $frameworkid): array {
        $where = '1=1';
        $params = [];
        if ($courseid > 0) {
            $where .= ' AND (courseid = :courseid OR courseid IS NULL OR courseid = 0)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return ['where' => $where, 'params' => $params];
    }

    /**
     * Merge object records into an ID-indexed list.
     *
     * @param array $objects
     * @param array $records
     */
    private static function merge_objects(array &$objects, array $records): void {
        foreach ($records as $record) {
            $objects[(int)$record->id] = $record;
        }
    }

    /**
     * Build a content identity index from History V1 facts.
     *
     * @param int $courseid
     * @return array
     */
    private static function content_identity_index(int $courseid): array {
        $payload = self::history_adapter_call('content_identities_for_course', $courseid, 500, 0);
        $index = [
            'records' => [],
            'by_cmid' => [],
            'by_activityid' => [],
            'by_assessmentid' => [],
            'by_questionid' => [],
            'by_sourcekey' => [],
        ];
        foreach (($payload['records'] ?? []) as $identity) {
            if (!is_array($identity) || ($identity['status'] ?? '') !== 'resolved') {
                continue;
            }
            $index['records'][] = $identity;
            foreach ([
                'cmid' => 'by_cmid',
                'activityid' => 'by_activityid',
                'assessmentid' => 'by_assessmentid',
                'questionid' => 'by_questionid',
                'sourcekey' => 'by_sourcekey',
            ] as $field => $bucket) {
                if (!empty($identity[$field])) {
                    $index[$bucket][(string)$identity[$field]] = $identity;
                }
            }
        }
        return $index;
    }

    /**
     * Find the best content identity for a fact.
     *
     * @param array $fact
     * @param array $index
     * @return array
     */
    private static function matching_content_identity(array $fact, array $index): array {
        foreach ([
            'cmid' => 'by_cmid',
            'activityid' => 'by_activityid',
            'assessmentid' => 'by_assessmentid',
            'questionid' => 'by_questionid',
        ] as $field => $bucket) {
            if (!empty($fact[$field]) && isset($index[$bucket][(string)$fact[$field]])) {
                return $index[$bucket][(string)$fact[$field]];
            }
        }
        return [];
    }

    /**
     * Fetch a History V1 payload for one fact type.
     *
     * @param string $facttype
     * @param int $courseid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private static function history_payload(string $facttype, int $courseid, int $limit, int $offset): array {
        if (!isset(self::HISTORY_METHODS[$facttype])) {
            throw new \invalid_parameter_exception('Unsupported History V1 fact type for E1 reprocessing.');
        }
        return self::history_adapter_call(self::HISTORY_METHODS[$facttype], $courseid, $limit, $offset);
    }

    /**
     * Invoke the History V1 adapter through the frozen boundary.
     *
     * @param string $method
     * @param int $courseid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private static function history_adapter_call(string $method, int $courseid, int $limit, int $offset = 0): array {
        $adapter = self::HISTORY_ADAPTER;
        if (!class_exists($adapter) || !method_exists($adapter, $method)) {
            throw new \coding_exception('History V1 source adapter is not available: ' . $method);
        }
        $payload = $adapter::$method($courseid, self::bounded_limit($limit, 500), max(0, $offset));
        if (!is_array($payload)) {
            throw new \coding_exception('History V1 source adapter returned an invalid payload.');
        }
        return $payload;
    }

    /**
     * Normalize requested fact types.
     *
     * @param array $facttypes
     * @return array
     */
    private static function normalize_fact_types(array $facttypes): array {
        if (!$facttypes) {
            return self::SUPPORTED_FACT_TYPES;
        }
        $normalized = [];
        foreach ($facttypes as $facttype) {
            $facttype = strtolower(trim((string)$facttype));
            if ($facttype === 'attempt') {
                $facttype = 'attempts';
            } else if ($facttype === 'completions') {
                $facttype = 'completion';
            }
            if (in_array($facttype, self::SUPPORTED_FACT_TYPES, true)) {
                $normalized[] = $facttype;
            }
        }
        $normalized = array_values(array_unique($normalized));
        return $normalized ?: self::SUPPORTED_FACT_TYPES;
    }

    /**
     * Return the source adapter status and optional bounded totals.
     *
     * @param int $courseid
     * @return array
     */
    private static function source_adapter_status(int $courseid): array {
        $adapter = self::HISTORY_ADAPTER;
        $methods = ['contract', 'attempts_for_course', 'completions_for_course', 'content_identities_for_course'];
        $missing = [];
        foreach ($methods as $method) {
            if (!class_exists($adapter) || !method_exists($adapter, $method)) {
                $missing[] = $method;
            }
        }

        $source = [
            'class' => $adapter,
            'available' => empty($missing),
            'missing_methods' => $missing,
            'history_totals' => [],
        ];
        if (!$source['available']) {
            return $source;
        }
        try {
            $contract = $adapter::contract();
            $source['contract'] = $contract['version'] ?? null;
            $source['facttypes'] = $contract['facttypes'] ?? [];
        } catch (\Throwable $e) {
            $source['available'] = false;
            $source['error'] = $e->getMessage();
            return $source;
        }
        if ($courseid > 0) {
            foreach (['attempts', 'completion', 'content_identities'] as $facttype) {
                $method = $facttype === 'attempts' ? 'attempts_for_course' :
                    ($facttype === 'completion' ? 'completions_for_course' : 'content_identities_for_course');
                try {
                    $payload = self::history_adapter_call($method, $courseid, 1, 0);
                    $source['history_totals'][$facttype] = (int)($payload['pagination']['total'] ?? 0);
                } catch (\Throwable $e) {
                    $source['available'] = false;
                    $source['error'] = $e->getMessage();
                    break;
                }
            }
        }
        return $source;
    }

    /**
     * Return page/CLI file status.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'history_evidence.php',
            'cli/history_evidence.php',
            'classes/local/history_evidence_adapter.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            $exists = file_exists($CFG->dirroot . '/local/flwcupkp/' . $file);
            $present[$file] = $exists;
            if (!$exists) {
                $missing[] = $file;
            }
        }
        return ['present' => $present, 'missing' => $missing];
    }

    /**
     * Build status criteria.
     *
     * @param array $management
     * @param array $history
     * @param array $files
     * @param array $source
     * @return array
     */
    private static function status_criteria(array $management, array $history, array $files, array $source): array {
        return [
            'management_v1_consumed' => self::criterion(
                'management_v1_consumed',
                ($management['status'] ?? '') === 'frozen' &&
                    ($management['contract']['version'] ?? '') === management_v1_contract::CONTRACT_VERSION,
                'E1 consumes the frozen Management V1 surface.'
            ),
            'history_v1_boundary_ready' => self::criterion(
                'history_v1_boundary_ready',
                ($history['status'] ?? '') !== 'blocked' && !empty($source['available']) &&
                    ($source['contract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT,
                'E1 consumes the History V1 downstream evidence contract only.'
            ),
            'supported_fact_methods_present' => self::criterion(
                'supported_fact_methods_present',
                empty($source['missing_methods']),
                'Attempts, completion, and content identity facts are available through History V1.'
            ),
            'controlled_reprocessing_surface_present' => self::criterion(
                'controlled_reprocessing_surface_present',
                empty($files['missing']),
                'Admin page and CLI expose preview/apply controlled reprocessing.'
            ),
            'source_boundary_enforced' => self::criterion(
                'source_boundary_enforced',
                in_array('raw_moodle_log_scraping', self::contract()['does_not_do'], true),
                'No raw Moodle log scraping is part of the E1 adapter.'
            ),
            'idempotent_versioned_derivation' => self::criterion(
                'idempotent_versioned_derivation',
                true,
                'Derived evidence sourceattempt keys include adapter and evidence policy versions.'
            ),
        ];
    }

    /**
     * Build one criterion row.
     *
     * @param string $key
     * @param bool $pass
     * @param string $detail
     * @return array
     */
    private static function criterion(string $key, bool $pass, string $detail): array {
        return [
            'key' => $key,
            'status' => $pass ? 'pass' : 'fail',
            'pass' => $pass,
            'detail' => $detail,
        ];
    }

    /**
     * Summarize criteria.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $passed = 0;
        foreach ($criteria as $criterion) {
            if (!empty($criterion['pass'])) {
                $passed++;
            }
        }
        return [
            'total' => count($criteria),
            'passed' => $passed,
            'failed' => count($criteria) - $passed,
        ];
    }

    /**
     * Build status findings.
     *
     * @param array $criteria
     * @param array $management
     * @param array $history
     * @param array $source
     * @return array
     */
    private static function status_findings(array $criteria, array $management, array $history, array $source): array {
        $findings = [];
        foreach ($criteria as $criterion) {
            if (empty($criterion['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => $criterion['key'] . '_failed',
                    'message' => $criterion['detail'],
                ];
            }
        }
        foreach ([$management, $history] as $dependency) {
            foreach (($dependency['findings'] ?? []) as $finding) {
                $findings[] = [
                    'severity' => strtolower((string)($finding['severity'] ?? 'warning')),
                    'code' => (string)($finding['code'] ?? 'dependency_finding'),
                    'message' => (string)($finding['message'] ?? json_encode($finding)),
                ];
            }
        }
        if (!empty($source['error'])) {
            $findings[] = [
                'severity' => 'blocker',
                'code' => 'history_source_adapter_error',
                'message' => (string)$source['error'],
            ];
        }
        return $findings;
    }

    /**
     * Safe wrapper for dependency status calls.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            $status = $callback();
            return is_array($status) ? $status : [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'invalid_status_payload',
                    'message' => 'Dependency status did not return an array.',
                ]],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'dependency_status_exception',
                    'message' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Return a compact safe copy of the source fact.
     *
     * @param array $fact
     * @return array
     */
    private static function sanitized_fact(array $fact): array {
        $fields = [
            'source',
            'facttype',
            'sourcekey',
            'sourcefactkey',
            'sourceeventid',
            'sourcefamily',
            'sourcesystem',
            'sourcetype',
            'sourceid',
            'sourceattemptid',
            'userid',
            'courseid',
            'cmid',
            'unitid',
            'activityid',
            'assessmentid',
            'questionid',
            'attemptno',
            'attemptstate',
            'rawscore',
            'maxscore',
            'scaledscore',
            'completionstate',
            'viewed',
            'timestart',
            'timefinish',
            'completiontime',
            'normpolicyversion',
        ];
        $safe = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $fact)) {
                $safe[$field] = $fact[$field];
            }
        }
        return $safe;
    }

    /**
     * Return evidence strength for a fact/map.
     *
     * @param string $facttype
     * @param \stdClass $object
     * @param \stdClass $map
     * @return string
     */
    private static function evidence_strength(string $facttype, \stdClass $object, \stdClass $map): string {
        if (!empty($map->evidencestrength)) {
            return (string)$map->evidencestrength;
        }
        if (!empty($object->evidencestrength)) {
            return (string)$object->evidencestrength;
        }
        return 'recognition';
    }

    /**
     * Return fact occurrence time.
     *
     * @param string $facttype
     * @param array $fact
     * @return int
     */
    private static function fact_time(string $facttype, array $fact): int {
        if ($facttype === 'completion') {
            return (int)($fact['completiontime'] ?? time()) ?: time();
        }
        return (int)($fact['timefinish'] ?? ($fact['timestart'] ?? time())) ?: time();
    }

    /**
     * Return compact source reference for evidence.
     *
     * @param array $fact
     * @return string
     */
    private static function sourceref(array $fact): string {
        $key = (string)($fact['sourcekey'] ?? ($fact['sourcefactkey'] ?? ''));
        if ($key === '') {
            $key = sha1(json_encode(self::sanitized_fact($fact), JSON_UNESCAPED_SLASHES));
        }
        return substr('history_v1:' . $key, 0, 255);
    }

    /**
     * First non-empty value.
     *
     * @param array $values
     * @return mixed
     */
    private static function first_non_empty(array $values) {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    /**
     * Normalize a numeric value or return null.
     *
     * @param mixed $value
     * @return float|null
     */
    private static function float_or_null($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * Clamp score.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Moodle completion constants may not be loaded in isolated tests.
     *
     * @param string $name
     * @param int $fallback
     * @return int
     */
    private static function completion_constant(string $name, int $fallback): int {
        return defined($name) ? (int)constant($name) : $fallback;
    }

    /**
     * Bound result limits.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }
}

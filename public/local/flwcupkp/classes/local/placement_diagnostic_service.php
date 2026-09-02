<?php
// Program 3 Gate A2 placement, diagnostic, and cold-start policy.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Consumes History V1 placement facts as initial diagnostic evidence.
 */
final class placement_diagnostic_service {
    /** Program 3 placement diagnostic gate. */
    public const GATE = 'P3_A2';

    /** Frozen A2 contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_PLACEMENT_DIAGNOSTIC_COLD_START_V1';

    /** Deterministic placement interpretation policy. */
    public const POLICY_VERSION = 'cupkp-placement-diagnostic-coldstart-v1';

    /** C-UP-KP provenance marker for A2-derived placement evidence. */
    public const PROVENANCE = 'local_flwhistory_placement_a2';

    /** Next allowed gate after A2. */
    public const NEXT_ALLOWED_GATE = 'A3';

    /** @var string History source adapter class name. */
    private const HISTORY_ADAPTER = '\\local_flwhistory\\local\\evidence_source_adapter';

    /** @var int Placement facts older than this become stale. */
    private const STALE_AFTER_SECONDS = 15552000;

    /** @var float Placement facts below this confidence remain diagnostic only. */
    private const LOW_CONFIDENCE_THRESHOLD = 0.60;

    /** @var array A2 placement states. */
    private const STATES = [
        'NOT_TAKEN',
        'VALID',
        'STALE',
        'INCOMPLETE',
        'LOW_CONFIDENCE',
        'TEACHER_OVERRIDE',
    ];

    /** @var array Placement/cold-start cases frozen by A2. */
    private const POLICY_CASES = [
        'no_placement',
        'partial',
        'abandoned',
        'refused',
        'imported_history',
        'institutional_entry',
        'teacher_override',
        'stale_placement',
    ];

    /** @var array Required flwcupkp_placement_state fields. */
    private const STATE_FIELDS = [
        'userid',
        'courseid',
        'frameworkid',
        'unitcode',
        'sourcekey',
        'sourcefactkey',
        'placementstatus',
        'policystate',
        'sourcecategory',
        'previouslevel',
        'currentlevel',
        'score',
        'confidence',
        'placementtime',
        'staleafter',
        'assesseddimensionsjson',
        'evidenceidsjson',
        'diagnosticjson',
        'policyversion',
        'checksum',
        'timecreated',
        'timemodified',
        'usermodified',
    ];

    /**
     * Return the frozen A2 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpPlacementDiagnosticColdStartContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                learning_goal_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
                management_v1_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'policy_version' => self::POLICY_VERSION,
            'states' => self::STATES,
            'policy_cases' => self::policy_cases(),
            'placement_enters_pipeline' => [
                'source_fact_type' => 'placement',
                'writer' => 'mastery_engine::record_evidence',
                'only_for_explicit_assessed_dimensions' => true,
                'overall_level_is_not_target_evidence_without_mapping' => true,
            ],
            'write_boundary' => [
                'flwcupkp_placement_state',
                'flwcupkp_evidence',
                'flwcupkp_state',
                'flwcupkp_audit',
            ],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'placement_as_permanent_truth',
                'fabricate_unassessed_dimensions',
                'adaptive_path_selection',
                'recommendation_ranking_changes',
                'learning_goal_mutation',
                'history_v1_source_mutation',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return frozen placement policy cases.
     *
     * @return array
     */
    public static function policy_cases(): array {
        return [
            'no_placement' => [
                'state' => 'NOT_TAKEN',
                'pipeline' => 'no_evidence',
                'rule' => 'No History V1 placement fact exists for the learner/course scope.',
            ],
            'partial' => [
                'state' => 'INCOMPLETE',
                'pipeline' => 'assessed_dimensions_only',
                'rule' => 'Partial placement may write evidence only for profile dimensions with explicit scores and targets.',
            ],
            'abandoned' => [
                'state' => 'INCOMPLETE',
                'pipeline' => 'no_evidence_unless_explicit_scored_dimensions_exist',
                'rule' => 'Abandoned placement is not a full placement result.',
            ],
            'refused' => [
                'state' => 'NOT_TAKEN',
                'pipeline' => 'no_evidence',
                'rule' => 'Refusal records learner choice but does not create proficiency evidence.',
            ],
            'imported_history' => [
                'state' => 'VALID_OR_STALE_OR_LOW_CONFIDENCE',
                'pipeline' => 'assessed_dimensions_only',
                'rule' => 'Imported placement is diagnostic evidence and is reclassified by age, status, and confidence.',
            ],
            'institutional_entry' => [
                'state' => 'VALID_OR_LOW_CONFIDENCE',
                'pipeline' => 'assessed_dimensions_only',
                'rule' => 'Institutional placement may enter the pipeline only when it names assessed dimensions.',
            ],
            'teacher_override' => [
                'state' => 'TEACHER_OVERRIDE',
                'pipeline' => 'assessed_dimensions_only',
                'rule' => 'Teacher override remains labeled as an override and never becomes permanent truth.',
            ],
            'stale_placement' => [
                'state' => 'STALE',
                'pipeline' => 'no_new_evidence',
                'rule' => 'Placement older than the freshness window is visible but not replayed into evidence.',
            ],
        ];
    }

    /**
     * Readiness status for A2.
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
        $a1 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return learning_goal_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $history = self::safe_status_call(static function() use ($courseid): array {
            return history_v1_consumer_contract::contract_status($courseid, 1);
        });
        $schema = self::schema_status();
        $files = self::file_status();
        $surface = self::surface_status();
        $source = self::source_adapter_status($courseid);
        $summary = self::placement_state_summary($courseid, $unitcode, $frameworkid, $limit);
        $criteria = self::criteria($a1, $history, $schema, $files, $surface, $source);
        $criteriasummary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpPlacementDiagnosticColdStartStatus',
            'gate' => self::GATE,
            'status' => $criteriasummary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $criteriasummary,
            'dependencies' => [
                'learning_goal_service' => self::dependency_summary($a1),
                'history_v1' => self::dependency_summary($history),
            ],
            'source_adapter' => $source,
            'schema' => $schema,
            'files' => $files,
            'surface' => $surface,
            'summary' => $summary,
            'findings' => self::status_findings($criteria, [$a1, $history, $source]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return current placement diagnostic state for one learner.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function current_placement(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('A learner userid is required.');
        }
        $limit = self::bounded_limit($limit, 100);
        $rows = self::placement_state_rows($courseid, $unitcode, $frameworkid, $userid, $limit);
        $latest = $rows ? $rows[0] : null;
        $historyfact = !$latest && $courseid > 0 ? self::latest_history_placement($userid, $courseid) : null;
        $coldstart = null;

        if (!$latest && !$historyfact) {
            $coldstart = self::cold_start_state($userid, $courseid, $unitcode, $frameworkid);
        } else if (!$latest && $historyfact) {
            $policy = self::policy_for_fact($historyfact);
            $coldstart = [
                'policystate' => $policy['state'],
                'policycase' => $policy['case'],
                'sourcecategory' => $policy['sourcecategory'],
                'history_fact_processed' => false,
                'message' => 'History V1 placement exists but has not been applied to A2 placement state.',
            ];
        }

        return [
            'type' => 'CupkpLearnerPlacementDiagnosticState',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
            ],
            'state' => $latest ? self::serialize_state($latest) : $coldstart,
            'states' => array_map([self::class, 'serialize_state'], $rows),
            'history_fact' => $historyfact,
            'has_processed_state' => (bool)$latest,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level placement diagnostic summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = self::bounded_limit($limit, 500);
        $summary = self::placement_state_summary($courseid, $unitcode, $frameworkid, $limit);
        return [
            'type' => 'CupkpClassPlacementDiagnosticSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'states' => array_map([self::class, 'serialize_state'],
                self::placement_state_rows($courseid, $unitcode, $frameworkid, 0, $limit)),
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Preview placement reprocessing.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function preview_reprocess(int $courseid, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100, int $offset = 0): array {
        return self::process_reprocess($courseid, $unitcode, $frameworkid, $userid, $limit, $offset, false, '');
    }

    /**
     * Apply placement reprocessing.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param int $offset
     * @param string $reason
     * @return array
     */
    public static function apply_reprocess(int $courseid, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100, int $offset = 0, string $reason = ''): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $offset = max(0, $offset);
        $requestid = repository::audit('placement_diagnostic_reprocess_requested', 'course', $courseid, [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'userid' => $userid,
            'limit' => $limit,
            'offset' => $offset,
            'reason' => $reason,
        ]);

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = self::process_reprocess($courseid, $unitcode, $frameworkid, $userid, $limit, $offset, true,
                $reason);
            $result['request_audit_id'] = $requestid;
            repository::audit('placement_diagnostic_reprocess_completed', 'course', $courseid, [
                'request_audit_id' => $requestid,
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'summary' => $result['summary'],
                'created_evidenceids' => array_slice($result['created_evidenceids'], 0, 50),
                'skipped_sample' => array_slice($result['skipped'], 0, 20),
            ]);
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $ignored) {
                // The failure audit below reports the original exception.
            }
            repository::audit('placement_diagnostic_reprocess_failed', 'course', $courseid, [
                'request_audit_id' => $requestid,
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Recent placement reprocess history.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function recent_reprocess_history(int $courseid = 0, string $unitcode = '', int $limit = 20): array {
        global $DB;

        $actions = [
            'placement_diagnostic_reprocess_requested',
            'placement_diagnostic_reprocess_completed',
            'placement_diagnostic_reprocess_failed',
        ];
        [$insql, $params] = $DB->get_in_or_equal($actions, SQL_PARAMS_NAMED, 'action');
        $where = "action {$insql}";
        if ($courseid > 0) {
            $where .= ' AND targettype = :targettype AND targetid = :targetid';
            $params['targettype'] = 'course';
            $params['targetid'] = $courseid;
        }
        $records = $DB->get_records_select('flwcupkp_audit', $where, $params, 'timecreated DESC, id DESC', '*', 0,
            self::bounded_limit($limit, 100));
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
     * @param int $userid
     * @param int $limit
     * @param int $offset
     * @param bool $write
     * @param string $reason
     * @return array
     */
    private static function process_reprocess(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, int $offset, bool $write, string $reason): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('A course ID is required for placement diagnostic reprocessing.');
        }
        $limit = self::bounded_limit($limit, 500);
        $offset = max(0, $offset);
        $status = self::status($courseid, $unitcode, $frameworkid, min($limit, 300));
        if (($status['status'] ?? '') === 'blocked') {
            return self::blocked_result($write, $courseid, $unitcode, $frameworkid, $userid, $limit, $offset,
                $status['findings'] ?? []);
        }

        $payload = self::history_payload($courseid, $limit, $offset);
        $summary = [
            'records_seen' => 0,
            'state_records_planned' => 0,
            'state_records_written' => 0,
            'dimensions_seen' => 0,
            'dimensions_assessed' => 0,
            'evidence_planned' => 0,
            'evidence_created' => 0,
            'evidence_existing' => 0,
            'no_assessed_dimensions' => 0,
            'no_mapped_target' => 0,
            'skipped_by_policy' => 0,
            'policy_states' => array_fill_keys(self::STATES, 0),
            'policy_cases' => array_fill_keys(self::POLICY_CASES, 0),
            'history_total' => (int)($payload['pagination']['total'] ?? 0),
        ];
        $plans = [];
        $states = [];
        $createdids = [];
        $skipped = [];
        $rejected = [];
        $context = [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'write' => $write,
            'reason' => $reason,
        ];

        foreach (($payload['records'] ?? []) as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            if ($userid > 0 && (int)($fact['userid'] ?? 0) !== $userid) {
                continue;
            }
            $summary['records_seen']++;
            $policy = self::policy_for_fact($fact);
            $summary['policy_states'][$policy['state']]++;
            $summary['policy_cases'][$policy['case']]++;
            $dimensions = self::assessed_dimensions($fact);
            if (!$dimensions) {
                $summary['no_assessed_dimensions']++;
            }
            $evidenceids = [];
            $dimensionrows = [];

            foreach ($dimensions as $dimension) {
                $summary['dimensions_seen']++;
                if (!array_key_exists('score', $dimension) || $dimension['score'] === null) {
                    $dimensionrows[] = self::dimension_result($dimension, [], 'dimension_without_score');
                    continue;
                }
                $summary['dimensions_assessed']++;
                if (empty($policy['pipeline_allowed'])) {
                    $summary['skipped_by_policy']++;
                    $row = self::fact_summary($fact, 'policy_state_no_evidence', [
                        'policy_state' => $policy['state'],
                        'policy_case' => $policy['case'],
                        'dimension' => $dimension['key'],
                    ]);
                    $skipped[] = $row;
                    $dimensionrows[] = self::dimension_result($dimension, [], 'policy_state_no_evidence');
                    continue;
                }

                $targets = self::targets_for_dimension($dimension, $context);
                if (!$targets) {
                    $summary['no_mapped_target']++;
                    $row = self::fact_summary($fact, 'no_mapped_target_for_assessed_dimension', [
                        'policy_state' => $policy['state'],
                        'policy_case' => $policy['case'],
                        'dimension' => $dimension['key'],
                    ]);
                    $skipped[] = $row;
                    $dimensionrows[] = self::dimension_result($dimension, [], 'no_mapped_target');
                    continue;
                }

                foreach ($targets as $target) {
                    try {
                        $evidence = self::evidence_payload_for_target($fact, $policy, $dimension, $target, $context);
                        $existingid = self::existing_evidence_id($evidence);
                        if ($existingid) {
                            $summary['evidence_existing']++;
                            $evidenceids[] = $existingid;
                            $plans[] = self::plan_row($fact, $policy, $dimension, $target, $evidence, 'existing',
                                $existingid);
                            continue;
                        }
                        if (empty($write)) {
                            $summary['evidence_planned']++;
                            $plans[] = self::plan_row($fact, $policy, $dimension, $target, $evidence, 'would_create');
                            continue;
                        }

                        $created = mastery_engine::record_evidence((object)$evidence);
                        $evidenceid = (int)$created['evidenceid'];
                        $summary['evidence_created']++;
                        $evidenceids[] = $evidenceid;
                        $createdids[] = $evidenceid;
                        $plans[] = self::plan_row($fact, $policy, $dimension, $target, $evidence, 'created',
                            $evidenceid);
                    } catch (\Throwable $e) {
                        $rejected[] = self::fact_summary($fact, 'placement_evidence_write_rejected', [
                            'policy_state' => $policy['state'],
                            'policy_case' => $policy['case'],
                            'dimension' => $dimension['key'],
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
                $dimensionrows[] = self::dimension_result($dimension, $targets, 'processed');
            }

            $state = self::state_payload($fact, $policy, $dimensionrows, array_values(array_unique($evidenceids)),
                $context);
            $summary['state_records_planned']++;
            if ($write) {
                $state['id'] = self::save_state($state);
                $summary['state_records_written']++;
            }
            $states[] = $state;
        }

        return [
            'type' => 'CupkpPlacementDiagnosticReprocessResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'status' => 'processed',
            'mode' => $write ? 'apply' : 'preview',
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'summary' => $summary,
            'states' => $states,
            'plans' => $plans,
            'created_evidenceids' => $createdids,
            'skipped' => $skipped,
            'rejected' => $rejected,
            'read_only' => !$write,
            'state_changes_allowed' => $write,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build evidence payload for one placement dimension/target.
     *
     * @param array $fact
     * @param array $policy
     * @param array $dimension
     * @param array $target
     * @param array $context
     * @return array
     */
    private static function evidence_payload_for_target(array $fact, array $policy, array $dimension, array $target,
            array $context): array {
        global $USER;

        $score = self::clamp01((float)$dimension['score']);
        $confidence = self::evidence_confidence($fact, $policy);
        $rubric = [
            'a2_placement_diagnostic' => [
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'policy_version' => self::POLICY_VERSION,
                'policy_state' => $policy['state'],
                'policy_case' => $policy['case'],
                'sourcecategory' => $policy['sourcecategory'],
                'placement_is_permanent_truth' => false,
                'fabricated_dimension' => false,
                'stale_after_seconds' => self::STALE_AFTER_SECONDS,
                'reason' => (string)($context['reason'] ?? ''),
            ],
            'history_source' => [
                'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
                'history_source_key' => $fact['sourcekey'] ?? null,
                'source_fact_key' => $fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? null),
                'source_type' => 'placement',
                'norm_policy_version' => $fact['normpolicyversion'] ?? null,
            ],
            'assessed_dimension' => self::sanitized_dimension($dimension),
            'source_fact' => self::sanitized_fact($fact),
        ];

        return [
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)$context['courseid'],
            'unitcode' => (string)($context['unitcode'] ?? ''),
            'objectid' => (int)($target['objectid'] ?? 0),
            'sourceattempt' => self::sourceattempt_key($fact, $dimension, $target),
            'evidencetype' => 'history_v1_placement',
            'targettype' => (string)$target['targettype'],
            'targetid' => (int)$target['targetid'],
            'rawscore' => $score,
            'normalizedscore' => $score,
            'rubricjson' => json_encode($rubric, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'assessortype' => $policy['state'] === 'TEACHER_OVERRIDE' ? 'teacher_override' : 'placement',
            'confidence' => $confidence,
            'evidencestrength' => (string)($target['evidencestrength'] ?? 'recognition'),
            'provenance' => self::PROVENANCE,
            'sourceref' => (string)($fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? '')),
            'timecreated' => self::fact_time($fact),
            'usermodified' => (int)($USER->id ?? 0),
        ];
    }

    /**
     * Return policy for one History V1 placement fact.
     *
     * @param array $fact
     * @return array
     */
    private static function policy_for_fact(array $fact): array {
        $status = self::normalize_label((string)($fact['placementstatus'] ?? ''));
        $source = self::normalize_label(implode(' ', [
            (string)($fact['sourcesystem'] ?? ''),
            (string)($fact['sourcetype'] ?? ''),
            (string)($fact['sourcefamily'] ?? ''),
            $status,
        ]));
        $sourcecategory = self::source_category($source);
        $confidence = self::float_or_null($fact['confidence'] ?? null);
        $currentlevel = trim((string)($fact['currentlevel'] ?? ''));
        $score = self::float_or_null($fact['score'] ?? null);
        $time = self::fact_time($fact);

        if (self::contains_any($status, ['refused', 'declined', 'optout', 'opt_out'])) {
            return self::policy('NOT_TAKEN', 'refused', $sourcecategory, false);
        }
        if (self::contains_any($status, ['teacher_override', 'teacher override', 'override'])) {
            return self::policy('TEACHER_OVERRIDE', 'teacher_override', 'teacher_override', true);
        }
        if ($time > 0 && $time < time() - self::STALE_AFTER_SECONDS) {
            return self::policy('STALE', 'stale_placement', $sourcecategory, false);
        }
        if (self::contains_any($status, ['abandoned', 'cancelled', 'canceled'])) {
            return self::policy('INCOMPLETE', 'abandoned', $sourcecategory, false);
        }
        if (self::contains_any($status, ['partial', 'incomplete']) || ($currentlevel === '' && $score === null)) {
            return self::policy('INCOMPLETE', 'partial', $sourcecategory, true);
        }
        if ($confidence !== null && $confidence < self::LOW_CONFIDENCE_THRESHOLD) {
            return self::policy('LOW_CONFIDENCE', $sourcecategory, $sourcecategory, true);
        }
        if ($sourcecategory === 'imported_history') {
            return self::policy('VALID', 'imported_history', $sourcecategory, true);
        }
        if ($sourcecategory === 'institutional_entry') {
            return self::policy('VALID', 'institutional_entry', $sourcecategory, true);
        }
        return self::policy('VALID', 'imported_history', $sourcecategory, true);
    }

    /**
     * Return one policy row.
     *
     * @param string $state
     * @param string $case
     * @param string $sourcecategory
     * @param bool $pipelineallowed
     * @return array
     */
    private static function policy(string $state, string $case, string $sourcecategory, bool $pipelineallowed): array {
        return [
            'state' => $state,
            'case' => $case,
            'sourcecategory' => $sourcecategory,
            'pipeline_allowed' => $pipelineallowed,
        ];
    }

    /**
     * Determine source category for a placement fact.
     *
     * @param string $source
     * @return string
     */
    private static function source_category(string $source): string {
        if (self::contains_any($source, ['teacher', 'override'])) {
            return 'teacher_override';
        }
        if (self::contains_any($source, ['institution', 'institutional'])) {
            return 'institutional_entry';
        }
        if (self::contains_any($source, ['import', 'history', 'legacy'])) {
            return 'imported_history';
        }
        return 'imported_history';
    }

    /**
     * Extract explicitly assessed dimensions from a placement profile.
     *
     * @param array $fact
     * @return array
     */
    private static function assessed_dimensions(array $fact): array {
        $profile = $fact['profile'] ?? [];
        if (!is_array($profile)) {
            $profile = [];
        }
        $dimensions = [];
        foreach ([
            'assessed_targets',
            'target_scores',
            'dimensions',
            'dimension_scores',
            'skill_scores',
            'skill_percentages',
            'kp_mastery',
        ] as $key) {
            self::collect_dimensions($profile[$key] ?? null, $key, $dimensions);
        }
        if (!empty($profile['skill_levels']) && is_array($profile['skill_levels'])) {
            foreach ($profile['skill_levels'] as $skill => $level) {
                $dimensions[] = self::dimension_from_value((string)$skill, $level, 'skill_levels');
            }
        }
        if (!empty($profile['skill_profile']) && is_array($profile['skill_profile'])) {
            foreach ($profile['skill_profile'] as $skill => $level) {
                $dimensions[] = self::dimension_from_value((string)$skill, $level, 'skill_profile');
            }
        }
        $score = self::float_or_null($fact['score'] ?? null);
        $level = trim((string)($fact['currentlevel'] ?? ''));
        if ($score !== null || $level !== '') {
            $dimensions[] = [
                'key' => 'overall',
                'label' => 'overall',
                'source' => 'placement_overall',
                'score' => $score !== null ? self::normalize_score($score) : null,
                'level' => $level,
                'targettype' => '',
                'targetid' => 0,
                'externalid' => '',
            ];
        }

        return self::unique_dimensions($dimensions);
    }

    /**
     * Collect dimensions from a profile subsection.
     *
     * @param mixed $value
     * @param string $source
     * @param array $dimensions
     */
    private static function collect_dimensions($value, string $source, array &$dimensions): void {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $dimensions[] = self::dimension_from_array((string)$key, $item, $source);
            } else {
                $dimensions[] = self::dimension_from_value((string)$key, $item, $source);
            }
        }
    }

    /**
     * Build dimension from scalar.
     *
     * @param string $key
     * @param mixed $value
     * @param string $source
     * @return array
     */
    private static function dimension_from_value(string $key, $value, string $source): array {
        $score = is_numeric($value) ? self::normalize_score((float)$value) : null;
        return [
            'key' => self::clean_dimension_key($key),
            'label' => (string)$key,
            'source' => $source,
            'score' => $score,
            'level' => $score === null ? trim((string)$value) : '',
            'targettype' => '',
            'targetid' => 0,
            'externalid' => self::looks_like_externalid((string)$key) ? (string)$key : '',
        ];
    }

    /**
     * Build dimension from array.
     *
     * @param string $key
     * @param array $item
     * @param string $source
     * @return array
     */
    private static function dimension_from_array(string $key, array $item, string $source): array {
        $label = (string)($item['dimension'] ?? ($item['skill'] ?? ($item['label'] ?? $key)));
        $score = self::first_numeric([
            $item['normalizedscore'] ?? null,
            $item['normalized_score'] ?? null,
            $item['score'] ?? null,
            $item['percent'] ?? null,
            $item['percentage'] ?? null,
            $item['value'] ?? null,
            $item['mastery'] ?? null,
        ]);
        $externalid = (string)($item['externalid'] ?? ($item['external_id'] ?? ($item['target_externalid'] ?? '')));
        if ($externalid === '' && self::looks_like_externalid($key)) {
            $externalid = $key;
        }
        return [
            'key' => self::clean_dimension_key((string)($item['key'] ?? $label)),
            'label' => $label,
            'source' => $source,
            'score' => $score !== null ? self::normalize_score($score) : null,
            'level' => (string)($item['level'] ?? ($item['cefr'] ?? '')),
            'targettype' => self::normalize_target_type((string)($item['targettype'] ?? ($item['target_type'] ?? ''))),
            'targetid' => max(0, (int)($item['targetid'] ?? ($item['target_id'] ?? 0))),
            'externalid' => $externalid,
        ];
    }

    /**
     * Resolve explicit targets for a placement dimension.
     *
     * @param array $dimension
     * @param array $context
     * @return array
     */
    private static function targets_for_dimension(array $dimension, array $context): array {
        $targets = [];
        $targettype = (string)($dimension['targettype'] ?? '');
        $targetid = (int)($dimension['targetid'] ?? 0);
        if ($targettype !== '' && $targetid > 0) {
            $target = self::target_from_id($targettype, $targetid, (int)$context['frameworkid']);
            if ($target) {
                $targets[] = $target + ['resolution' => 'profile_target_id'];
            }
        }

        $externalids = array_values(array_filter(array_unique([
            (string)($dimension['externalid'] ?? ''),
            self::looks_like_externalid((string)($dimension['key'] ?? '')) ? (string)$dimension['key'] : '',
            self::looks_like_externalid((string)($dimension['label'] ?? '')) ? (string)$dimension['label'] : '',
        ])));
        foreach ($externalids as $externalid) {
            foreach (['competency', 'up', 'kp'] as $type) {
                $target = self::target_from_externalid($type, $externalid, (int)$context['frameworkid']);
                if ($target) {
                    $targets[] = $target + ['resolution' => 'profile_externalid'];
                }
            }
        }

        foreach (self::placement_object_targets($dimension, $context) as $target) {
            $targets[] = $target;
        }

        return self::unique_targets($targets);
    }

    /**
     * Resolve targets through explicit placement objects and object maps.
     *
     * @param array $dimension
     * @param array $context
     * @return array
     */
    private static function placement_object_targets(array $dimension, array $context): array {
        global $DB;

        $scope = self::object_scope_where((int)$context['courseid'], (string)$context['unitcode'],
            (int)$context['frameworkid']);
        $records = $DB->get_records_select('flwcupkp_object', $scope['where'], $scope['params'], 'id ASC', '*', 0, 500);
        $targets = [];
        foreach ($records as $object) {
            if (!self::object_is_placement($object) || !self::object_matches_dimension($object, $dimension)) {
                continue;
            }
            $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => (int)$object->id],
                'targettype ASC, targetid ASC');
            foreach ($maps as $map) {
                try {
                    evidence_guard::assert_object_map($object, $map);
                    content_evidence_mapping_contract::assert_source_can_count('placement', $object, $map);
                } catch (\invalid_parameter_exception $e) {
                    continue;
                }
                $targets[] = [
                    'targettype' => (string)$map->targettype,
                    'targetid' => (int)$map->targetid,
                    'objectid' => (int)$object->id,
                    'mapid' => (int)$map->id,
                    'externalid' => self::target_externalid((string)$map->targettype, (int)$map->targetid),
                    'evidencestrength' => (string)($map->evidencestrength ?: ($object->evidencestrength ?: 'recognition')),
                    'resolution' => 'placement_object_map',
                ];
            }
        }
        return $targets;
    }

    /**
     * Whether an object is a placement/diagnostic assessment object.
     *
     * @param \stdClass $object
     * @return bool
     */
    private static function object_is_placement(\stdClass $object): bool {
        $text = self::normalize_label(implode(' ', [
            (string)($object->objecttype ?? ''),
            (string)($object->purpose ?? ''),
            (string)($object->role ?? ''),
        ]));
        return self::contains_any($text, ['placement', 'diagnostic', 'assessment', 'assesses']);
    }

    /**
     * Whether object metadata explicitly names the dimension.
     *
     * @param \stdClass $object
     * @param array $dimension
     * @return bool
     */
    private static function object_matches_dimension(\stdClass $object, array $dimension): bool {
        $keys = self::dimension_match_keys($dimension);
        if (!$keys) {
            return false;
        }
        $direct = [
            self::normalize_label((string)($object->externalid ?? '')),
            self::normalize_label((string)($object->sourceid ?? '')),
        ];
        foreach ($keys as $key) {
            if (in_array($key, $direct, true)) {
                return true;
            }
        }

        $metadata = json_decode((string)($object->metadatajson ?? ''), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $declared = self::metadata_dimension_keys($metadata);
        foreach ($keys as $key) {
            if (in_array($key, $declared, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dimension keys from metadata.
     *
     * @param array $metadata
     * @return array
     */
    private static function metadata_dimension_keys(array $metadata): array {
        $values = [];
        foreach ([
            'placement_dimension',
            'placement_dimensions',
            'assessed_dimension',
            'assessed_dimensions',
            'dimension',
            'dimensions',
            'skill',
            'skills',
            'profile_key',
            'profile_keys',
        ] as $key) {
            if (array_key_exists($key, $metadata)) {
                self::collect_scalar_values($metadata[$key], $values);
            }
        }
        return array_values(array_unique(array_map([self::class, 'normalize_label'], $values)));
    }

    /**
     * Build a placement state payload.
     *
     * @param array $fact
     * @param array $policy
     * @param array $dimensions
     * @param array $evidenceids
     * @param array $context
     * @return array
     */
    private static function state_payload(array $fact, array $policy, array $dimensions, array $evidenceids,
            array $context): array {
        $time = self::fact_time($fact);
        $payload = [
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)$context['courseid'],
            'frameworkid' => self::nullable_int((int)$context['frameworkid']),
            'unitcode' => (string)($context['unitcode'] ?? '') !== '' ? (string)$context['unitcode'] : null,
            'sourcekey' => (string)($fact['sourcekey'] ?? ''),
            'sourcefactkey' => (string)($fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? '')),
            'placementstatus' => (string)($fact['placementstatus'] ?? ''),
            'policystate' => $policy['state'],
            'sourcecategory' => $policy['sourcecategory'],
            'previouslevel' => (string)($fact['previouslevel'] ?? ''),
            'currentlevel' => (string)($fact['currentlevel'] ?? ''),
            'score' => self::float_or_null($fact['score'] ?? null),
            'confidence' => self::float_or_null($fact['confidence'] ?? null),
            'placementtime' => $time,
            'staleafter' => $time > 0 ? $time + self::STALE_AFTER_SECONDS : null,
            'assesseddimensionsjson' => json_encode($dimensions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'evidenceidsjson' => json_encode(array_values(array_unique(array_map('intval', $evidenceids)))),
            'diagnosticjson' => json_encode([
                'policy_case' => $policy['case'],
                'pipeline_allowed' => (bool)$policy['pipeline_allowed'],
                'placement_is_permanent_truth' => false,
                'fabricated_dimensions' => false,
                'source_fact' => self::sanitized_fact($fact),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'policyversion' => self::POLICY_VERSION,
        ];
        $payload['checksum'] = sha1(json_encode([
            $payload['sourcefactkey'],
            $payload['policystate'],
            $payload['sourcecategory'],
            $payload['currentlevel'],
            $payload['score'],
            $payload['confidence'],
            $payload['assesseddimensionsjson'],
            $payload['evidenceidsjson'],
            $payload['policyversion'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $payload;
    }

    /**
     * Save or update a placement state row by scoped source fact key.
     *
     * @param array $payload
     * @return int
     */
    private static function save_state(array $payload): int {
        global $DB, $USER;

        $now = time();
        $record = (object)$payload;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->usermodified = (int)($USER->id ?? 0);
        $params = [
            'sourcefactkey' => (string)$payload['sourcefactkey'],
            'userid' => (int)$payload['userid'],
            'courseid' => (int)$payload['courseid'],
        ];
        $where = 'sourcefactkey = :sourcefactkey AND userid = :userid AND courseid = :courseid';
        if (!empty($payload['unitcode'])) {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = (string)$payload['unitcode'];
        } else {
            $where .= ' AND unitcode IS NULL';
        }
        if (!empty($payload['frameworkid'])) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = (int)$payload['frameworkid'];
        } else {
            $where .= ' AND frameworkid IS NULL';
        }
        $existing = $DB->get_record_select('flwcupkp_placement_state', $where, $params, '*', IGNORE_MISSING);
        if ($existing) {
            $record->id = (int)$existing->id;
            $record->timecreated = (int)$existing->timecreated;
            $DB->update_record('flwcupkp_placement_state', $record);
            return (int)$existing->id;
        }
        return (int)$DB->insert_record('flwcupkp_placement_state', $record);
    }

    /**
     * Existing evidence id for idempotent placement evidence.
     *
     * @param array $evidence
     * @return int
     */
    private static function existing_evidence_id(array $evidence): int {
        global $DB;

        return (int)$DB->get_field('flwcupkp_evidence', 'id', [
            'sourceattempt' => (string)$evidence['sourceattempt'],
            'targettype' => (string)$evidence['targettype'],
            'targetid' => (int)$evidence['targetid'],
        ], IGNORE_MISSING);
    }

    /**
     * Return a compact plan row.
     *
     * @param array $fact
     * @param array $policy
     * @param array $dimension
     * @param array $target
     * @param array $evidence
     * @param string $status
     * @param int $evidenceid
     * @return array
     */
    private static function plan_row(array $fact, array $policy, array $dimension, array $target, array $evidence,
            string $status, int $evidenceid = 0): array {
        return [
            'status' => $status,
            'history_source_key' => (string)($fact['sourcekey'] ?? ''),
            'source_fact_key' => (string)($fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? '')),
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)($fact['courseid'] ?? 0),
            'policy_state' => $policy['state'],
            'policy_case' => $policy['case'],
            'dimension' => (string)$dimension['key'],
            'targettype' => (string)$target['targettype'],
            'targetid' => (int)$target['targetid'],
            'objectid' => (int)($target['objectid'] ?? 0),
            'mapid' => (int)($target['mapid'] ?? 0),
            'resolution' => (string)($target['resolution'] ?? ''),
            'sourceattempt' => (string)$evidence['sourceattempt'],
            'normalizedscore' => (float)$evidence['normalizedscore'],
            'confidence' => (float)$evidence['confidence'],
            'evidenceid' => $evidenceid ?: null,
            'would_write' => $status === 'would_create',
        ];
    }

    /**
     * Serialize placement state record.
     *
     * @param \stdClass $row
     * @return array
     */
    private static function serialize_state(\stdClass $row): array {
        return [
            'id' => (int)$row->id,
            'userid' => (int)$row->userid,
            'courseid' => (int)($row->courseid ?? 0),
            'frameworkid' => (int)($row->frameworkid ?? 0),
            'unitcode' => (string)($row->unitcode ?? ''),
            'sourcekey' => (string)($row->sourcekey ?? ''),
            'sourcefactkey' => (string)($row->sourcefactkey ?? ''),
            'placementstatus' => (string)($row->placementstatus ?? ''),
            'policystate' => (string)($row->policystate ?? ''),
            'sourcecategory' => (string)($row->sourcecategory ?? ''),
            'previouslevel' => (string)($row->previouslevel ?? ''),
            'currentlevel' => (string)($row->currentlevel ?? ''),
            'score' => self::float_or_null($row->score ?? null),
            'confidence' => self::float_or_null($row->confidence ?? null),
            'placementtime' => (int)($row->placementtime ?? 0),
            'staleafter' => (int)($row->staleafter ?? 0),
            'assessed_dimensions' => self::decode_json($row->assesseddimensionsjson ?? '[]'),
            'evidenceids' => self::decode_int_json($row->evidenceidsjson ?? '[]'),
            'diagnostic' => self::decode_json($row->diagnosticjson ?? '{}'),
            'policyversion' => (string)($row->policyversion ?? ''),
            'checksum' => (string)($row->checksum ?? ''),
            'timecreated' => (int)($row->timecreated ?? 0),
            'timemodified' => (int)($row->timemodified ?? 0),
            'usermodified' => (int)($row->usermodified ?? 0),
        ];
    }

    /**
     * Return state rows for a scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @return array
     */
    private static function placement_state_rows(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit): array {
        global $DB;

        if (!self::tables_ready()) {
            return [];
        }
        $where = '1=1';
        $params = [];
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
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
        if ($userid > 0) {
            $where .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }
        return array_values($DB->get_records_select('flwcupkp_placement_state', $where, $params,
            'placementtime DESC, id DESC', '*', 0, self::bounded_limit($limit, 500)));
    }

    /**
     * Summarize placement states in scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function placement_state_summary(int $courseid, string $unitcode, int $frameworkid,
            int $limit): array {
        $rows = self::placement_state_rows($courseid, $unitcode, $frameworkid, 0, $limit);
        $states = array_fill_keys(self::STATES, 0);
        $sourcecategories = [
            'imported_history' => 0,
            'institutional_entry' => 0,
            'teacher_override' => 0,
        ];
        $learners = [];
        $evidencecount = 0;
        foreach ($rows as $row) {
            $state = (string)$row->policystate;
            if (isset($states[$state])) {
                $states[$state]++;
            }
            $source = (string)$row->sourcecategory;
            if (isset($sourcecategories[$source])) {
                $sourcecategories[$source]++;
            }
            $learners[(int)$row->userid] = true;
            $evidencecount += count(self::decode_int_json($row->evidenceidsjson ?? '[]'));
        }
        $enrolled = self::enrolled_count($courseid, $limit);
        $notknown = $enrolled > 0 ? max(0, $enrolled - count($learners)) : 0;
        if ($notknown > 0) {
            $states['NOT_TAKEN'] += $notknown;
        }
        return [
            'records' => count($rows),
            'learners_with_state' => count($learners),
            'enrolled_sample' => $enrolled,
            'not_taken_or_unknown' => $states['NOT_TAKEN'],
            'evidence_links' => $evidencecount,
            'states' => $states,
            'sourcecategories' => $sourcecategories,
        ];
    }

    /**
     * Cold-start state when no placement has been found.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function cold_start_state(int $userid, int $courseid, string $unitcode, int $frameworkid): array {
        return [
            'policystate' => 'NOT_TAKEN',
            'policycase' => 'no_placement',
            'sourcecategory' => 'none',
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'placement_is_permanent_truth' => false,
            'fabricated_dimensions' => false,
            'evidenceids' => [],
        ];
    }

    /**
     * Latest History V1 placement fact for a learner/course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    private static function latest_history_placement(int $userid, int $courseid): ?array {
        if (!class_exists(self::HISTORY_ADAPTER) || !method_exists(self::HISTORY_ADAPTER, 'placements_for_course')) {
            return null;
        }
        $payload = call_user_func([self::HISTORY_ADAPTER, 'placements_for_course'], $courseid, 500, 0);
        foreach (($payload['records'] ?? []) as $fact) {
            if (is_array($fact) && (int)($fact['userid'] ?? 0) === $userid) {
                return $fact;
            }
        }
        return null;
    }

    /**
     * Return History V1 placement payload.
     *
     * @param int $courseid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private static function history_payload(int $courseid, int $limit, int $offset): array {
        if (!class_exists(self::HISTORY_ADAPTER) || !method_exists(self::HISTORY_ADAPTER, 'placements_for_course')) {
            throw new \coding_exception('History V1 placement source adapter is not available.');
        }
        $payload = call_user_func([self::HISTORY_ADAPTER, 'placements_for_course'], $courseid, $limit, $offset);
        return is_array($payload) ? $payload : ['records' => [], 'pagination' => ['total' => 0]];
    }

    /**
     * Source adapter status.
     *
     * @param int $courseid
     * @return array
     */
    private static function source_adapter_status(int $courseid): array {
        $class = self::HISTORY_ADAPTER;
        $exists = class_exists($class);
        $method = $exists && method_exists($class, 'placements_for_course');
        $contractok = false;
        $total = null;
        $error = '';
        try {
            if ($exists && method_exists($class, 'contract')) {
                $contract = call_user_func([$class, 'contract']);
                $contractok = ($contract['version'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT &&
                    in_array('placement', $contract['facttypes'] ?? [], true);
            }
            if ($method && $courseid > 0) {
                $payload = call_user_func([$class, 'placements_for_course'], $courseid, 1, 0);
                $total = (int)($payload['pagination']['total'] ?? 0);
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        return [
            'valid' => $exists && $method && $contractok && $error === '',
            'class' => $class,
            'class_exists' => $exists,
            'placements_for_course' => $method,
            'contract_supports_placement' => $contractok,
            'course_placement_fact_total' => $total,
            'error' => $error,
        ];
    }

    /**
     * Schema status.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('flwcupkp_placement_state');
        $exists = $dbman->table_exists($table);
        $columns = $exists ? $DB->get_columns('flwcupkp_placement_state') : [];
        $present = [];
        foreach (self::STATE_FIELDS as $field) {
            $present[$field] = isset($columns[$field]);
        }
        $missing = array_keys(array_filter($present, static function(bool $ok): bool {
            return !$ok;
        }));
        return [
            'valid' => $exists && !$missing,
            'tables' => [
                'flwcupkp_placement_state' => $exists,
                'flwcupkp_diagnostic' => $dbman->table_exists(new \xmldb_table('flwcupkp_diagnostic')),
                'flwcupkp_evidence' => $dbman->table_exists(new \xmldb_table('flwcupkp_evidence')),
                'flwcupkp_state' => $dbman->table_exists(new \xmldb_table('flwcupkp_state')),
            ],
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * File status.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $base = $CFG->dirroot . '/local/flwcupkp/';
        $files = [
            'placement_diagnostic.php',
            'cli/placement_diagnostic.php',
            'classes/local/placement_diagnostic_service.php',
            'openapi.json',
        ];
        $present = [];
        foreach ($files as $file) {
            $present[$file] = file_exists($base . $file);
        }
        return [
            'valid' => !in_array(false, $present, true),
            'present' => $present,
            'missing' => array_keys(array_filter($present, static function(bool $ok): bool {
                return !$ok;
            })),
        ];
    }

    /**
     * Surface status.
     *
     * @return array
     */
    private static function surface_status(): array {
        $methods = [
            self::class . '::status' => method_exists(self::class, 'status'),
            self::class . '::current_placement' => method_exists(self::class, 'current_placement'),
            self::class . '::class_summary' => method_exists(self::class, 'class_summary'),
            self::class . '::preview_reprocess' => method_exists(self::class, 'preview_reprocess'),
            self::class . '::apply_reprocess' => method_exists(self::class, 'apply_reprocess'),
            self::class . '::recent_reprocess_history' => method_exists(self::class, 'recent_reprocess_history'),
        ];
        return [
            'valid' => !in_array(false, $methods, true),
            'methods' => $methods,
            'missing_methods' => array_keys(array_filter($methods, static function(bool $ok): bool {
                return !$ok;
            })),
        ];
    }

    /**
     * Readiness criteria.
     *
     * @param array $a1
     * @param array $history
     * @param array $schema
     * @param array $files
     * @param array $surface
     * @param array $source
     * @return array
     */
    private static function criteria(array $a1, array $history, array $schema, array $files, array $surface,
            array $source): array {
        return [
            'a1_learning_goals_consumed' => self::criterion(
                'a1_learning_goals_consumed',
                ($a1['status'] ?? '') === 'ready' &&
                    ($a1['contract']['version'] ?? '') === learning_goal_service::CONTRACT_VERSION,
                'A2 consumes the frozen A1 learning-goal contract.'
            ),
            'history_v1_placement_available' => self::criterion(
                'history_v1_placement_available',
                ($history['contractavailable'] ?? false) && ($source['valid'] ?? false),
                'History V1 exposes bounded placement facts.'
            ),
            'placement_state_schema_present' => self::criterion(
                'placement_state_schema_present',
                $schema['valid'],
                'A2 stores interpreted placement diagnostic states separately from raw History V1 facts.'
            ),
            'all_prompt_states_supported' => self::criterion(
                'all_prompt_states_supported',
                self::STATES === ['NOT_TAKEN', 'VALID', 'STALE', 'INCOMPLETE', 'LOW_CONFIDENCE', 'TEACHER_OVERRIDE'],
                'A2 supports every prompt placement state.'
            ),
            'all_policy_cases_defined' => self::criterion(
                'all_policy_cases_defined',
                array_keys(self::policy_cases()) === self::POLICY_CASES,
                'A2 defines policy for no placement, partial, abandoned, refused, imported history, institutional entry, teacher override, and stale placement.'
            ),
            'pipeline_writer_present' => self::criterion(
                'pipeline_writer_present',
                method_exists(mastery_engine::class, 'record_evidence'),
                'Placement evidence enters the existing C-UP-KP evidence/state pipeline.'
            ),
            'no_fabrication_guard_present' => self::criterion(
                'no_fabrication_guard_present',
                true,
                'Placement dimensions require explicit assessed scores and target mapping before evidence is written.'
            ),
        ];
    }

    /**
     * One readiness criterion.
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
     * Criteria summary.
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
     * Findings from criteria/dependencies.
     *
     * @param array $criteria
     * @param array $dependencies
     * @return array
     */
    private static function status_findings(array $criteria, array $dependencies): array {
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
        foreach ($dependencies as $dependency) {
            foreach (($dependency['findings'] ?? []) as $finding) {
                $severity = strtolower((string)($finding['severity'] ?? 'info'));
                if (in_array($severity, ['blocker', 'error', 'high', 'medium', 'warning'], true)) {
                    $findings[] = [
                        'severity' => $severity,
                        'code' => (string)($finding['code'] ?? 'dependency_finding'),
                        'message' => (string)($finding['message'] ?? json_encode($finding)),
                    ];
                }
            }
            if (!empty($dependency['error'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => 'dependency_error',
                    'message' => (string)$dependency['error'],
                ];
            }
        }
        return $findings;
    }

    /**
     * Dependency summary.
     *
     * @param array $dependency
     * @return array
     */
    private static function dependency_summary(array $dependency): array {
        return [
            'type' => $dependency['type'] ?? '',
            'gate' => $dependency['gate'] ?? '',
            'status' => $dependency['status'] ?? 'unknown',
            'contract' => $dependency['contract']['version'] ?? ($dependency['requiredcontract'] ?? ''),
            'next_allowed_gate' => $dependency['next_allowed_gate'] ?? '',
            'findings' => count($dependency['findings'] ?? []),
        ];
    }

    /**
     * Wrap dependency call failures.
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
                    'code' => 'invalid_dependency_status',
                    'message' => 'Dependency did not return an array status.',
                ]],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'dependency_exception',
                    'message' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Blocked reprocess result.
     *
     * @param bool $write
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param int $offset
     * @param array $findings
     * @return array
     */
    private static function blocked_result(bool $write, int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, int $offset, array $findings): array {
        return [
            'type' => 'CupkpPlacementDiagnosticReprocessResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => 'blocked',
            'mode' => $write ? 'apply' : 'preview',
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'summary' => [
                'records_seen' => 0,
                'state_records_planned' => 0,
                'state_records_written' => 0,
                'evidence_planned' => 0,
                'evidence_created' => 0,
                'evidence_existing' => 0,
            ],
            'states' => [],
            'plans' => [],
            'created_evidenceids' => [],
            'skipped' => [],
            'rejected' => [],
            'findings' => $findings,
            'read_only' => !$write,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Check whether A2 tables exist.
     *
     * @return bool
     */
    private static function tables_ready(): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new \xmldb_table('flwcupkp_placement_state'));
    }

    /**
     * Target from id.
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $frameworkid
     * @return array|null
     */
    private static function target_from_id(string $targettype, int $targetid, int $frameworkid): ?array {
        global $DB;

        if (!in_array($targettype, ['competency', 'up', 'kp'], true)) {
            return null;
        }
        $row = $DB->get_record(evidence_guard::target_table($targettype), ['id' => $targetid], '*', IGNORE_MISSING);
        if (!$row || ($frameworkid > 0 && (int)($row->frameworkid ?? 0) !== $frameworkid)) {
            return null;
        }
        return [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'externalid' => (string)($row->externalid ?? ''),
            'objectid' => 0,
            'mapid' => 0,
            'evidencestrength' => 'recognition',
        ];
    }

    /**
     * Target from external id.
     *
     * @param string $targettype
     * @param string $externalid
     * @param int $frameworkid
     * @return array|null
     */
    private static function target_from_externalid(string $targettype, string $externalid, int $frameworkid): ?array {
        global $DB;

        if ($externalid === '') {
            return null;
        }
        $params = ['externalid' => $externalid];
        $where = 'externalid = :externalid';
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $rows = $DB->get_records_select(evidence_guard::target_table($targettype), $where, $params, 'id ASC', '*', 0,
            1);
        if (!$rows) {
            return null;
        }
        $row = reset($rows);
        return [
            'targettype' => $targettype,
            'targetid' => (int)$row->id,
            'externalid' => (string)$row->externalid,
            'objectid' => 0,
            'mapid' => 0,
            'evidencestrength' => 'recognition',
        ];
    }

    /**
     * Target external id.
     *
     * @param string $targettype
     * @param int $targetid
     * @return string
     */
    private static function target_externalid(string $targettype, int $targetid): string {
        global $DB;

        if (!in_array($targettype, ['competency', 'up', 'kp'], true) || $targetid <= 0) {
            return '';
        }
        return (string)$DB->get_field(evidence_guard::target_table($targettype), 'externalid', ['id' => $targetid],
            IGNORE_MISSING);
    }

    /**
     * Object scope SQL.
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
     * Sourceattempt key.
     *
     * @param array $fact
     * @param array $dimension
     * @param array $target
     * @return string
     */
    private static function sourceattempt_key(array $fact, array $dimension, array $target): string {
        $hash = substr(sha1(json_encode([
            self::CONTRACT_VERSION,
            history_v1_consumer_contract::REQUIRED_CONTRACT,
            evidence_semantics_quality_contract::EVIDENCE_POLICY_VERSION,
            $fact['sourcekey'] ?? '',
            $fact['sourcefactkey'] ?? '',
            $dimension['key'] ?? '',
            $dimension['source'] ?? '',
            $target['targettype'] ?? '',
            $target['targetid'] ?? '',
            $target['objectid'] ?? 0,
            $target['mapid'] ?? 0,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 32);
        return 'history_v1_placement:' . $hash . ':' . (string)$target['targettype'] . ':' . (int)$target['targetid'];
    }

    /**
     * Fact summary.
     *
     * @param array $fact
     * @param string $reason
     * @param array $extra
     * @return array
     */
    private static function fact_summary(array $fact, string $reason, array $extra = []): array {
        return $extra + [
            'reason' => $reason,
            'history_source_key' => (string)($fact['sourcekey'] ?? ''),
            'source_fact_key' => (string)($fact['sourcefactkey'] ?? ($fact['sourcekey'] ?? '')),
            'userid' => (int)($fact['userid'] ?? 0),
            'courseid' => (int)($fact['courseid'] ?? 0),
            'currentlevel' => (string)($fact['currentlevel'] ?? ''),
            'placementstatus' => (string)($fact['placementstatus'] ?? ''),
        ];
    }

    /**
     * Dimension result stored with placement state.
     *
     * @param array $dimension
     * @param array $targets
     * @param string $status
     * @return array
     */
    private static function dimension_result(array $dimension, array $targets, string $status): array {
        return [
            'key' => (string)($dimension['key'] ?? ''),
            'label' => (string)($dimension['label'] ?? ''),
            'source' => (string)($dimension['source'] ?? ''),
            'score' => $dimension['score'] ?? null,
            'level' => (string)($dimension['level'] ?? ''),
            'status' => $status,
            'targets' => array_values(array_map(static function(array $target): array {
                return [
                    'targettype' => (string)($target['targettype'] ?? ''),
                    'targetid' => (int)($target['targetid'] ?? 0),
                    'externalid' => (string)($target['externalid'] ?? ''),
                    'objectid' => (int)($target['objectid'] ?? 0),
                    'mapid' => (int)($target['mapid'] ?? 0),
                    'resolution' => (string)($target['resolution'] ?? ''),
                ];
            }, $targets)),
        ];
    }

    /**
     * Evidence confidence after A2 policy caps.
     *
     * @param array $fact
     * @param array $policy
     * @return float
     */
    private static function evidence_confidence(array $fact, array $policy): float {
        $confidence = self::float_or_null($fact['confidence'] ?? null);
        $confidence = $confidence !== null ? self::clamp01($confidence) : 0.55;
        if ($policy['state'] === 'INCOMPLETE') {
            $confidence = min($confidence, 0.55);
        } else if ($policy['state'] === 'LOW_CONFIDENCE') {
            $confidence = min($confidence, self::LOW_CONFIDENCE_THRESHOLD);
        } else if ($policy['sourcecategory'] === 'institutional_entry') {
            $confidence = min($confidence, 0.70);
        }
        return $confidence;
    }

    /**
     * Enrolled learner count sample.
     *
     * @param int $courseid
     * @param int $limit
     * @return int
     */
    private static function enrolled_count(int $courseid, int $limit): int {
        if ($courseid <= 0) {
            return 0;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return 0;
        }
        return count(get_enrolled_users($context, '', 0, 'u.id', '', 0, self::bounded_limit($limit, 500)));
    }

    /**
     * Fact time.
     *
     * @param array $fact
     * @return int
     */
    private static function fact_time(array $fact): int {
        return (int)($fact['placementtime'] ?? time());
    }

    /**
     * Normalize target type.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_target_type(string $type): string {
        $type = strtolower(trim($type));
        if ($type === 'comp') {
            return 'competency';
        }
        return in_array($type, ['competency', 'up', 'kp'], true) ? $type : '';
    }

    /**
     * Normalize a label for comparisons.
     *
     * @param string $value
     * @return string
     */
    private static function normalize_label(string $value): string {
        return strtolower(trim(preg_replace('/[^a-z0-9._-]+/i', '_', $value)));
    }

    /**
     * Dimension comparison keys.
     *
     * @param array $dimension
     * @return array
     */
    private static function dimension_match_keys(array $dimension): array {
        return array_values(array_filter(array_unique(array_map([self::class, 'normalize_label'], [
            (string)($dimension['key'] ?? ''),
            (string)($dimension['label'] ?? ''),
            (string)($dimension['externalid'] ?? ''),
        ]))));
    }

    /**
     * Whether a string resembles a C-UP-KP external ID.
     *
     * @param string $value
     * @return bool
     */
    private static function looks_like_externalid(string $value): bool {
        return (bool)preg_match('/^(?:FLW-|C-|UP-|KP-)/i', trim($value));
    }

    /**
     * Clean dimension key.
     *
     * @param string $value
     * @return string
     */
    private static function clean_dimension_key(string $value): string {
        $value = trim($value);
        return $value !== '' ? clean_param($value, PARAM_TEXT) : 'dimension';
    }

    /**
     * Normalize score. Percentages above 1 are divided by 100.
     *
     * @param float $score
     * @return float
     */
    private static function normalize_score(float $score): float {
        if ($score > 1.0) {
            $score = $score / 100.0;
        }
        return self::clamp01($score);
    }

    /**
     * First numeric value.
     *
     * @param array $values
     * @return float|null
     */
    private static function first_numeric(array $values): ?float {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return (float)$value;
            }
        }
        return null;
    }

    /**
     * Float or null.
     *
     * @param mixed $value
     * @return float|null
     */
    private static function float_or_null($value): ?float {
        return $value === null || $value === '' ? null : (float)$value;
    }

    /**
     * Nullable int.
     *
     * @param int $value
     * @return int|null
     */
    private static function nullable_int(int $value): ?int {
        return $value > 0 ? $value : null;
    }

    /**
     * Decode JSON to array.
     *
     * @param mixed $json
     * @return array
     */
    private static function decode_json($json): array {
        if (is_array($json)) {
            return $json;
        }
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Decode integer JSON list.
     *
     * @param mixed $json
     * @return array
     */
    private static function decode_int_json($json): array {
        $decoded = self::decode_json($json);
        return array_values(array_filter(array_map('intval', $decoded), static function(int $id): bool {
            return $id > 0;
        }));
    }

    /**
     * Unique dimensions.
     *
     * @param array $dimensions
     * @return array
     */
    private static function unique_dimensions(array $dimensions): array {
        $seen = [];
        $out = [];
        foreach ($dimensions as $dimension) {
            $key = implode(':', [
                (string)($dimension['source'] ?? ''),
                (string)($dimension['key'] ?? ''),
                (string)($dimension['targettype'] ?? ''),
                (int)($dimension['targetid'] ?? 0),
                (string)($dimension['externalid'] ?? ''),
            ]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $dimension;
        }
        return $out;
    }

    /**
     * Unique targets.
     *
     * @param array $targets
     * @return array
     */
    private static function unique_targets(array $targets): array {
        $seen = [];
        $out = [];
        foreach ($targets as $target) {
            $key = (string)$target['targettype'] . ':' . (int)$target['targetid'] . ':' .
                (int)($target['objectid'] ?? 0) . ':' . (int)($target['mapid'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $target;
        }
        return $out;
    }

    /**
     * Recursively collect scalar values.
     *
     * @param mixed $value
     * @param array $out
     */
    private static function collect_scalar_values($value, array &$out): void {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::collect_scalar_values($item, $out);
            }
            return;
        }
        if ($value !== null && $value !== '') {
            $out[] = (string)$value;
        }
    }

    /**
     * Contains any normalized needle.
     *
     * @param string $value
     * @param array $needles
     * @return bool
     */
    private static function contains_any(string $value, array $needles): bool {
        foreach ($needles as $needle) {
            if (strpos($value, self::normalize_label($needle)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitized fact.
     *
     * @param array $fact
     * @return array
     */
    private static function sanitized_fact(array $fact): array {
        $out = $fact;
        if (isset($out['profile']) && is_array($out['profile'])) {
            $out['profile'] = array_intersect_key($out['profile'], array_flip([
                'overall_cefr',
                'cefr_level',
                'placement_status',
                'skill_levels',
                'skill_scores',
                'skill_percentages',
                'kp_mastery',
                'assessed_targets',
                'target_scores',
                'dimensions',
                'dimension_scores',
                'weak_skill_warnings',
                'support_flags',
            ]));
        }
        return $out;
    }

    /**
     * Sanitized dimension.
     *
     * @param array $dimension
     * @return array
     */
    private static function sanitized_dimension(array $dimension): array {
        return [
            'key' => (string)($dimension['key'] ?? ''),
            'label' => (string)($dimension['label'] ?? ''),
            'source' => (string)($dimension['source'] ?? ''),
            'score' => $dimension['score'] ?? null,
            'level' => (string)($dimension['level'] ?? ''),
            'targettype' => (string)($dimension['targettype'] ?? ''),
            'targetid' => (int)($dimension['targetid'] ?? 0),
            'externalid' => (string)($dimension['externalid'] ?? ''),
        ];
    }

    /**
     * Bounded result limit.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }

    /**
     * Clamp value to 0..1.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }
}

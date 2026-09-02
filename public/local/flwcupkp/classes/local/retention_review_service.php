<?php
// Program 3 Gate E3 Retention / Retrieval / Review.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Rebuildable retention/retrieval/review state service.
 */
final class retention_review_service {
    /** Program 3 retention gate. */
    public const GATE = 'P3_E3';

    /** Frozen E3 service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_RETENTION_RETRIEVAL_REVIEW_V1';

    /** Deterministic retention policy version. */
    public const RETENTION_POLICY_VERSION = 'cupkp-retention-policy-v1';

    /** Next prompt gate after E3. */
    public const NEXT_ALLOWED_GATE = 'A1';

    /** @var array Canonical storage states. */
    private const RETENTION_STATES = [
        'new' => 'NEW',
        'learning' => 'LEARNING',
        'consolidating' => 'CONSOLIDATING',
        'retained' => 'RETAINED',
        'review_due' => 'REVIEW_DUE',
        'retention_uncertain' => 'RETENTION_UNCERTAIN',
        'relearning' => 'RELEARNING',
    ];

    /** @var array Mastery states that can have separate retention status. */
    private const STRONG_STATES = [
        'kp' => ['independent_use', 'mastered', 'review_due'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained'],
    ];

    /** @var array Review interval by target type. */
    private const REVIEW_INTERVAL_DAYS = [
        'kp' => 21,
        'up' => 14,
        'competency' => 28,
    ];

    /** @var array Minimum successful retrievals expected for retained status. */
    private const MIN_RETRIEVALS = [
        'kp' => 2,
        'up' => 2,
        'competency' => 2,
    ];

    /** @var array Quality threshold expected for retained status. */
    private const RETAINED_QUALITY = [
        'kp' => 0.55,
        'up' => 0.70,
        'competency' => 0.75,
    ];

    /**
     * E3 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpRetentionRetrievalReviewContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                mastery_state_service::CONTRACT_VERSION,
                history_evidence_adapter::CONTRACT_VERSION,
                evidence_semantics_quality_contract::CONTRACT_VERSION,
                management_v1_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'retention_policy_version' => self::RETENTION_POLICY_VERSION,
            'retention_states' => array_values(self::RETENTION_STATES),
            'storage_states' => array_keys(self::RETENTION_STATES),
            'snapshot_fields' => self::required_retention_fields(),
            'target_type_policy' => [
                'kp' => [
                    'review_interval_days' => self::REVIEW_INTERVAL_DAYS['kp'],
                    'preferred_review_modes' => ['controlled_recall', 'independent_production', 'transfer'],
                ],
                'up' => [
                    'review_interval_days' => self::REVIEW_INTERVAL_DAYS['up'],
                    'preferred_review_modes' => ['independent_production', 'interaction', 'transfer'],
                ],
                'competency' => [
                    'review_interval_days' => self::REVIEW_INTERVAL_DAYS['competency'],
                    'preferred_review_modes' => ['independent_production', 'interaction', 'transfer'],
                ],
            ],
            'rules' => [
                'time_triggers_review_not_mastery_decay' => true,
                'failed_review_sets_retention_state_not_mastery_state' => true,
                'kp_and_up_can_diverge' => true,
                'manual_mastery_override_preserved' => true,
            ],
            'controlled_rebuild' => [
                'preview_is_read_only' => true,
                'apply_is_controlled' => true,
                'writes' => 'retention fields on flwcupkp_state only',
                'audit_actions' => [
                    'retention_review_rebuild_requested',
                    'retention_review_rebuild_completed',
                    'retention_review_rebuild_failed',
                ],
            ],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'adaptive_path_selection',
                'mastery_decay',
                'grade_mastery_collapse',
                'history_v1_source_mutation',
                'learner_goal_modeling',
            ],
        ];
    }

    /**
     * Readiness for E3 surfaces.
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
        $e2 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return mastery_state_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $schema = self::schema_status();
        $files = self::file_status();
        $surface = self::surface_status();
        $cache = self::cache_summary($courseid, $unitcode, $frameworkid, $limit);
        $criteria = self::criteria($e2, $schema, $files, $surface);
        $summary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpRetentionReviewStatus',
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
            'dependencies' => [
                'mastery_state_service' => self::dependency_summary($e2),
            ],
            'schema' => $schema,
            'files' => $files,
            'surface' => $surface,
            'cache' => $cache,
            'findings' => self::status_findings($criteria, [$e2]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'controlled_rebuild_available' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return the current retention/review state view for a learner.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function current_retention_state(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        if ($courseid > 0) {
            evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        }

        $limit = self::bounded_limit($limit, 500);
        $targets = self::scoped_targets($userid, $courseid, $unitcode, $frameworkid, $limit);
        $states = [];
        foreach ($targets as $target) {
            $states[] = self::retention_view($userid, $target, $courseid, $unitcode);
        }

        return [
            'type' => 'CupkpCurrentRetentionState',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => self::learner_summary($states),
            'states' => $states,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level retention/review summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '', int $frameworkid = 0,
            int $limit = 100): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID is required.');
        }
        $limit = self::bounded_limit($limit, 300);
        $learners = self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $rows = [];
        $summary = self::empty_summary();
        $summary['learners'] = count($learners);

        foreach ($learners as $userid) {
            try {
                $state = self::current_retention_state($userid, $courseid, $unitcode, $frameworkid, 300);
            } catch (\invalid_parameter_exception $e) {
                $summary['skipped_unenrolled']++;
                $rows[] = [
                    'userid' => (int)$userid,
                    'summary' => self::empty_summary(),
                    'status' => 'skipped_unenrolled',
                    'reason' => $e->getMessage(),
                ];
                continue;
            }
            $learnersummary = $state['summary'];
            self::merge_summary($summary, $learnersummary);
            $rows[] = [
                'userid' => (int)$userid,
                'summary' => $learnersummary,
            ];
        }

        return [
            'type' => 'CupkpClassRetentionSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'learners' => $rows,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Preview retention cache rebuild.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function preview_rebuild(int $courseid = 0, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100): array {
        return self::process_rebuild($courseid, $unitcode, $frameworkid, $userid, $limit, false, '');
    }

    /**
     * Apply controlled retention cache rebuild.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param string $reason
     * @return array
     */
    public static function apply_rebuild(int $courseid = 0, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100, string $reason = ''): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $requestid = repository::audit('retention_review_rebuild_requested', $courseid > 0 ? 'course' : 'system',
            $courseid > 0 ? $courseid : null, [
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
                'reason' => $reason,
            ]);

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = self::process_rebuild($courseid, $unitcode, $frameworkid, $userid, $limit, true, $reason);
            $result['request_audit_id'] = $requestid;
            repository::audit('retention_review_rebuild_completed', $courseid > 0 ? 'course' : 'system',
                $courseid > 0 ? $courseid : null, [
                    'request_audit_id' => $requestid,
                    'gate' => self::GATE,
                    'contract' => self::CONTRACT_VERSION,
                    'courseid' => $courseid,
                    'unitcode' => $unitcode,
                    'frameworkid' => $frameworkid,
                    'userid' => $userid,
                    'summary' => $result['summary'],
                ]);
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $ignored) {
                // Record the original failure after rollback handling.
            }
            repository::audit('retention_review_rebuild_failed', $courseid > 0 ? 'course' : 'system',
                $courseid > 0 ? $courseid : null, [
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
     * Recent E3 rebuild audit rows.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function recent_rebuild_history(int $courseid = 0, int $limit = 20): array {
        global $DB;

        $limit = self::bounded_limit($limit, 100);
        $actions = [
            'retention_review_rebuild_requested',
            'retention_review_rebuild_completed',
            'retention_review_rebuild_failed',
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
            $rows[] = [
                'id' => (int)$record->id,
                'action' => (string)$record->action,
                'targettype' => (string)($record->targettype ?? ''),
                'targetid' => isset($record->targetid) ? (int)$record->targetid : null,
                'userid' => isset($record->userid) ? (int)$record->userid : null,
                'timecreated' => (int)$record->timecreated,
                'details' => is_array($details) ? $details : [],
            ];
        }
        return $rows;
    }

    /**
     * Shared rebuild preview/apply implementation.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param bool $write
     * @param string $reason
     * @return array
     */
    private static function process_rebuild(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, bool $write, string $reason): array {
        $limit = self::bounded_limit($limit, 500);
        $status = self::status($courseid, $unitcode, $frameworkid, min($limit, 300));
        if (($status['status'] ?? '') === 'blocked') {
            return self::empty_rebuild_result($courseid, $unitcode, $frameworkid, $userid, $limit, $write,
                $status['findings'] ?? []);
        }

        $userids = $userid > 0 ? [$userid] : self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $summary = [
            'learners' => count($userids),
            'targets_seen' => 0,
            'created' => 0,
            'changed' => 0,
            'metadata_refreshed' => 0,
            'current' => 0,
            'missing_current_state' => 0,
            'skipped_unenrolled' => 0,
            'review_due' => 0,
            'retention_uncertain' => 0,
            'relearning' => 0,
            'applied' => 0,
            'skipped' => 0,
        ];
        $changes = [];

        foreach ($userids as $uid) {
            if ($courseid > 0) {
                try {
                    evidence_guard::assert_user_enrolled_for_course((int)$uid, $courseid);
                } catch (\invalid_parameter_exception $e) {
                    $summary['skipped_unenrolled']++;
                    $summary['skipped']++;
                    continue;
                }
            }
            $targets = self::scoped_targets((int)$uid, $courseid, $unitcode, $frameworkid, $limit);
            foreach ($targets as $target) {
                $summary['targets_seen']++;
                $comparison = self::retention_comparison((int)$uid, $target, $courseid, $unitcode);
                $bucket = self::summary_bucket($comparison['status']);
                if (isset($summary[$bucket])) {
                    $summary[$bucket]++;
                }
                $retentionstate = (string)($comparison['calculated_retention']['retentionstate'] ?? '');
                if (isset($summary[$retentionstate])) {
                    $summary[$retentionstate]++;
                }
                if (in_array($comparison['status'], ['created', 'changed', 'metadata_missing'], true)) {
                    $changes[] = $comparison;
                    if ($write && !empty($comparison['stateid'])) {
                        if (repository::update_retention_state((int)$comparison['stateid'],
                                $comparison['calculated_retention'])) {
                            $summary['applied']++;
                        } else {
                            $summary['skipped']++;
                        }
                    }
                } else if ($write) {
                    $summary['skipped']++;
                }
            }
        }

        return [
            'type' => 'CupkpRetentionReviewRebuildResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => 'processed',
            'mode' => $write ? 'apply' : 'preview',
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'changes' => array_slice($changes, 0, $limit),
            'read_only' => !$write,
            'state_changes_allowed' => $write,
            'reason' => $reason,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build one learner-target retention view.
     *
     * @param int $userid
     * @param array $target
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function retention_view(int $userid, array $target, int $courseid, string $unitcode): array {
        $comparison = self::retention_comparison($userid, $target, $courseid, $unitcode);
        $stored = $comparison['stored_state'];
        $calculated = $comparison['calculated_retention'];
        $state = $stored ?: (object)[];

        return [
            'type' => self::retention_state_type($target['targettype']),
            'userid' => $userid,
            'target' => [
                'type' => $target['targettype'],
                'id' => (int)$target['targetid'],
                'externalid' => (string)($target['externalid'] ?? ''),
                'title' => (string)($target['title'] ?? ''),
                'frameworkid' => (int)($target['frameworkid'] ?? 0),
            ],
            'mastery' => [
                'score' => round((float)($state->masteryscore ?? 0), 5),
                'state' => (string)($state->masterystate ?? ''),
                'confidence' => round((float)($state->confidence ?? 0), 5),
                'preserved' => true,
            ],
            'retention' => [
                'state' => (string)($state->retentionstate ?? $calculated['retentionstate']),
                'canonical_state' => self::canonical_state((string)($state->retentionstate ?? $calculated['retentionstate'])),
                'confidence' => round((float)($state->retentionconfidence ?? $calculated['retentionconfidence']), 5),
                'nextreview' => isset($state->retentionnextreview) ? (int)$state->retentionnextreview :
                    $calculated['retentionnextreview'],
                'lastretrieval' => isset($state->retentionlastretrieval) ? (int)$state->retentionlastretrieval :
                    $calculated['retentionlastretrieval'],
                'retrievalcount' => (int)($state->retentionretrievalcount ??
                    $calculated['retentionretrievalcount']),
                'policyversion' => (string)($state->retentionpolicyversion ??
                    $calculated['retentionpolicyversion']),
                'evidencehash' => (string)($state->retentionevidencehash ??
                    $calculated['retentionevidencehash']),
                'calculatedtime' => (int)($state->retentioncalculatedtime ??
                    $calculated['retentioncalculatedtime']),
            ],
            'status' => $comparison['status'],
            'reason' => $comparison['reason'],
            'review_quality' => $calculated['review_quality'],
            'evidence' => $comparison['evidence_summary'],
            'rebuild' => [
                'needed' => in_array($comparison['status'], ['created', 'changed', 'metadata_missing'], true),
                'reason' => $comparison['reason'],
            ],
            'calculated' => $calculated,
        ];
    }

    /**
     * Compare stored and rebuilt retention state for one target.
     *
     * @param int $userid
     * @param array $target
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function retention_comparison(int $userid, array $target, int $courseid, string $unitcode): array {
        $events = self::evidence_for_target($userid, $target['targettype'], (int)$target['targetid'], $courseid,
            $unitcode);
        $stored = self::stored_state($userid, $target['targettype'], (int)$target['targetid']);
        $calculated = self::calculate_retention($stored, $events, $target['targettype']);
        $status = self::comparison_status($stored, $calculated);

        return [
            'userid' => $userid,
            'stateid' => $stored ? (int)$stored->id : 0,
            'targettype' => $target['targettype'],
            'targetid' => (int)$target['targetid'],
            'target_externalid' => (string)($target['externalid'] ?? ''),
            'target_title' => (string)($target['title'] ?? ''),
            'status' => $status,
            'reason' => self::comparison_reason($status),
            'current_mastery_state' => $stored ? (string)$stored->masterystate : '',
            'current_mastery_score' => $stored ? (float)$stored->masteryscore : null,
            'current_retention_state' => $stored ? (string)($stored->retentionstate ?? '') : '',
            'proposed_retention_state' => (string)$calculated['retentionstate'],
            'proposed_retention_confidence' => (float)$calculated['retentionconfidence'],
            'stored_state' => $stored,
            'calculated_retention' => $calculated,
            'evidence_summary' => self::evidence_summary($events, $calculated),
        ];
    }

    /**
     * Calculate retention state from stored E2 current state and normalized evidence.
     *
     * @param \stdClass|null $stored
     * @param array $events
     * @param string $targettype
     * @return array
     */
    private static function calculate_retention(?\stdClass $stored, array $events, string $targettype): array {
        $now = time();
        $reviewanchor = self::review_anchor($now);
        $analysis = self::review_analysis($events, $targettype, $now);
        if (!$stored) {
            return self::retention_payload('new', 0.0, null, null, 0, $events, $analysis, $now,
                'No E2 current-state row exists yet.');
        }

        $mastery = (string)($stored->masterystate ?? '');
        $masteryscore = (float)($stored->masteryscore ?? 0);
        $masteryconfidence = self::clamp01((float)($stored->confidence ?? 0));
        $strong = self::is_strong_state($targettype, $mastery);
        $interval = self::review_interval_seconds($targettype);
        $lastretrieval = $analysis['last_successful_retrieval_time'];
        $latestfailure = $analysis['latest_failed_retrieval_time'];
        $failedafterretrieval = $latestfailure > 0 && $latestfailure >= $lastretrieval;
        $nextreview = $lastretrieval > 0 ? $lastretrieval + $interval : null;
        $retentionconfidence = self::retention_confidence($stored, $analysis, $targettype, $now);
        $reason = '';

        if ((int)($stored->evidencecount ?? 0) === 0 && empty($events)) {
            return self::retention_payload('new', 0.0, null, null, 0, $events, $analysis, $now,
                'No evidence has reached the current-state engine.');
        }

        if ($failedafterretrieval && ($strong || $masteryscore >= 0.35)) {
            return self::retention_payload('relearning', min($retentionconfidence, 0.45), $reviewanchor, $lastretrieval,
                $analysis['successful_retrieval_count'], $events, $analysis, $now,
                'A failed retrieval/review indicates retrievability needs rebuilding; mastery is preserved.');
        }

        if (!$strong) {
            if ($masteryscore < 0.10) {
                return self::retention_payload('new', $retentionconfidence, null, $lastretrieval,
                    $analysis['successful_retrieval_count'], $events, $analysis, $now,
                    'Current mastery is not established yet.');
            }
            if ($masteryscore < 0.55) {
                return self::retention_payload('learning', $retentionconfidence, $reviewanchor + (7 * DAYSECS),
                    $lastretrieval, $analysis['successful_retrieval_count'], $events, $analysis, $now,
                    'Learner is still building initial control.');
            }
            return self::retention_payload('consolidating', $retentionconfidence, $reviewanchor + (7 * DAYSECS),
                $lastretrieval, $analysis['successful_retrieval_count'], $events, $analysis, $now,
                'Learner has partial control but not a strong mastery state.');
        }

        if ($lastretrieval <= 0) {
            return self::retention_payload('retention_uncertain', min($retentionconfidence, 0.45), $reviewanchor, null,
                0, $events, $analysis, $now,
                'Mastery exists, but no target-appropriate retrieval/transfer evidence is available.');
        }

        if ($nextreview !== null && $nextreview <= $now) {
            return self::retention_payload('review_due', $retentionconfidence, $reviewanchor, $lastretrieval,
                $analysis['successful_retrieval_count'], $events, $analysis, $now,
                'Time triggers review need, not mastery decay.');
        }

        if ($retentionconfidence < 0.50) {
            return self::retention_payload('retention_uncertain', $retentionconfidence, $nextreview, $lastretrieval,
                $analysis['successful_retrieval_count'], $events, $analysis, $now,
                'Retrieval evidence exists, but retention confidence is still low.');
        }

        $minimum = self::MIN_RETRIEVALS[$targettype] ?? 2;
        $quality = self::RETAINED_QUALITY[$targettype] ?? 0.70;
        if ($analysis['successful_retrieval_count'] >= $minimum &&
                $analysis['best_successful_review_quality'] >= $quality) {
            $reason = 'Target-appropriate retrieval/transfer evidence is strong enough for retained status.';
            return self::retention_payload('retained', $retentionconfidence, $nextreview, $lastretrieval,
                $analysis['successful_retrieval_count'], $events, $analysis, $now, $reason);
        }

        return self::retention_payload('consolidating', $retentionconfidence, $nextreview, $lastretrieval,
            $analysis['successful_retrieval_count'], $events, $analysis, $now,
            'Mastery is demonstrated; additional retrieval evidence is needed before retained status.');
    }

    /**
     * Package a retention snapshot payload.
     *
     * @param string $state
     * @param float $confidence
     * @param int|null $nextreview
     * @param int|null $lastretrieval
     * @param int $retrievalcount
     * @param array $events
     * @param array $analysis
     * @param int $now
     * @param string $reason
     * @return array
     */
    private static function retention_payload(string $state, float $confidence, ?int $nextreview,
            ?int $lastretrieval, int $retrievalcount, array $events, array $analysis, int $now,
            string $reason): array {
        $ids = self::retention_evidence_ids($analysis, $events);
        return [
            'retentionstate' => $state,
            'canonical_state' => self::canonical_state($state),
            'retentionconfidence' => round(self::clamp01($confidence), 5),
            'retentionnextreview' => $nextreview,
            'retentionlastretrieval' => $lastretrieval,
            'retentionretrievalcount' => $retrievalcount,
            'retentionpolicyversion' => self::RETENTION_POLICY_VERSION,
            'retentionevidenceids' => $ids,
            'retentionevidenceidsjson' => json_encode($ids, JSON_UNESCAPED_SLASHES),
            'retentionevidencehash' => self::retention_evidence_hash($events, $analysis),
            'retentioncalculatedtime' => $now,
            'review_quality' => [
                'successful_retrieval_count' => $analysis['successful_retrieval_count'],
                'failed_retrieval_count' => $analysis['failed_retrieval_count'],
                'best_successful_review_quality' => round((float)$analysis['best_successful_review_quality'], 5),
                'average_successful_review_quality' => round((float)$analysis['average_successful_review_quality'], 5),
                'latest_failed_retrieval_time' => $analysis['latest_failed_retrieval_time'],
                'preferred_modes' => self::preferred_modes($analysis['targettype']),
            ],
            'explanation' => [
                'reason' => $reason,
                'mastery_preserved' => true,
                'time_decays_mastery' => false,
                'failed_review_erases_mastery' => false,
                'retention_policy_version' => self::RETENTION_POLICY_VERSION,
            ],
        ];
    }

    /**
     * Analyze normalized evidence for retrieval/review quality.
     *
     * @param array $events
     * @param string $targettype
     * @param int $now
     * @return array
     */
    private static function review_analysis(array $events, string $targettype, int $now): array {
        $successful = [];
        $failed = [];
        $retrievalids = [];
        $allqualities = [];

        foreach ($events as $event) {
            $semantics = self::event_semantics($event);
            $quality = self::retrieval_quality($targettype, $semantics);
            if ($quality <= 0) {
                continue;
            }
            $retrievalids[] = (int)($event->id ?? 0);
            $allqualities[] = $quality;
            $result = (string)$semantics['result_state'];
            $score = self::clamp01((float)($event->normalizedscore ?? 0));
            $row = [
                'id' => (int)($event->id ?? 0),
                'timecreated' => (int)($event->timecreated ?? 0),
                'quality' => $quality,
                'mode' => (string)$semantics['performance_mode'],
                'result_state' => $result,
                'score' => $score,
            ];
            if ($result === 'positive' && $score >= 0.70) {
                $successful[] = $row;
            } else if ($result === 'negative' || $score < 0.35) {
                $failed[] = $row;
            }
        }

        $lastsuccess = self::max_event_time($successful);
        $latestfailure = self::max_event_time($failed);
        $successquality = self::average_event_quality($successful);

        return [
            'targettype' => $targettype,
            'evidence_count' => count($events),
            'retrieval_evidence_ids' => array_values(array_filter($retrievalids)),
            'successful_retrieval_count' => count($successful),
            'failed_retrieval_count' => count($failed),
            'last_successful_retrieval_time' => $lastsuccess,
            'latest_failed_retrieval_time' => $latestfailure,
            'best_successful_review_quality' => self::best_event_quality($successful),
            'average_successful_review_quality' => $successquality,
            'average_retrieval_quality' => empty($allqualities) ? 0.0 : array_sum($allqualities) / count($allqualities),
            'bounded_recency' => self::bounded_recency($lastsuccess, $targettype, $now),
        ];
    }

    /**
     * Retention confidence is separate from mastery confidence.
     *
     * @param \stdClass $stored
     * @param array $analysis
     * @param string $targettype
     * @param int $now
     * @return float
     */
    private static function retention_confidence(\stdClass $stored, array $analysis, string $targettype, int $now): float {
        $minimum = self::MIN_RETRIEVALS[$targettype] ?? 2;
        $sufficiency = min(1.0, ((int)$analysis['successful_retrieval_count']) / max(1, $minimum));
        $quality = (float)($analysis['average_successful_review_quality'] ?: $analysis['average_retrieval_quality']);
        $masteryconfidence = self::clamp01((float)($stored->confidence ?? 0));
        $recency = (float)$analysis['bounded_recency'];
        $score = (0.35 * $quality) + (0.25 * $masteryconfidence) + (0.25 * $recency) + (0.15 * $sufficiency);
        if ((int)$analysis['successful_retrieval_count'] === 0) {
            $score = min($score, 0.45);
        }
        if ((int)$analysis['latest_failed_retrieval_time'] >= (int)$analysis['last_successful_retrieval_time'] &&
                (int)$analysis['latest_failed_retrieval_time'] > 0) {
            $score = min($score, 0.45);
        }
        return round(self::clamp01($score), 5);
    }

    /**
     * Extract or infer event semantics.
     *
     * @param \stdClass $event
     * @return array
     */
    private static function event_semantics(\stdClass $event): array {
        $existing = self::rubric_semantics($event);
        $result = evidence_semantics_quality_contract::infer_result_state($event, $existing);
        $mode = evidence_semantics_quality_contract::infer_performance_mode($event, null, null, $existing);
        $quality = evidence_semantics_quality_contract::quality_profile($event, null, null, $result, $mode, '',
            $existing);
        return [
            'result_state' => $result,
            'performance_mode' => $mode,
            'quality' => $quality,
        ];
    }

    /**
     * Target-appropriate retrieval quality.
     *
     * @param string $targettype
     * @param array $semantics
     * @return float
     */
    private static function retrieval_quality(string $targettype, array $semantics): float {
        $mode = (string)($semantics['performance_mode'] ?? '');
        $modeweight = self::mode_weight($targettype, $mode);
        if ($modeweight <= 0) {
            return 0.0;
        }
        $quality = (array)($semantics['quality'] ?? []);
        $dimensions = ['validity', 'reliability', 'independence', 'authenticity',
            'production_demand', 'contextual_transfer', 'support_level'];
        $sum = 0.0;
        $count = 0;
        foreach ($dimensions as $dimension) {
            if (isset($quality[$dimension]) && is_numeric($quality[$dimension])) {
                $sum += self::clamp01((float)$quality[$dimension]);
                $count++;
            }
        }
        $base = $count > 0 ? $sum / $count : 0.5;
        return round(self::clamp01($base * $modeweight), 5);
    }

    /**
     * Mode weight by target type.
     *
     * @param string $targettype
     * @param string $mode
     * @return float
     */
    private static function mode_weight(string $targettype, string $mode): float {
        $weights = [
            'kp' => [
                'controlled_recall' => 0.80,
                'guided_production' => 0.70,
                'independent_production' => 0.95,
                'interaction' => 0.95,
                'transfer' => 1.00,
            ],
            'up' => [
                'guided_production' => 0.55,
                'independent_production' => 0.85,
                'interaction' => 0.95,
                'transfer' => 1.00,
            ],
            'competency' => [
                'independent_production' => 0.80,
                'interaction' => 0.90,
                'transfer' => 1.00,
            ],
        ];
        return $weights[$targettype][$mode] ?? 0.0;
    }

    /**
     * Preferred retrieval modes by target type.
     *
     * @param string $targettype
     * @return array
     */
    private static function preferred_modes(string $targettype): array {
        if ($targettype === 'kp') {
            return ['controlled_recall', 'independent_production', 'transfer'];
        }
        return ['independent_production', 'interaction', 'transfer'];
    }

    /**
     * Return mapped targets plus parent targets for a learner/scope.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function scoped_targets(int $userid, int $courseid, string $unitcode, int $frameworkid,
            int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $targets = [];
        $scope = self::object_scope_where($courseid, $unitcode, $frameworkid);
        $maps = $DB->get_records_sql(
            "SELECT m.id, m.targettype, m.targetid
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE {$scope['where']}
           ORDER BY m.targettype ASC, m.targetid ASC",
            $scope['params'],
            0,
            $limit * 4
        );
        foreach ($maps as $map) {
            self::add_target($targets, (string)$map->targettype, (int)$map->targetid, $frameworkid);
        }

        if ($userid > 0) {
            $where = 'userid = :userid';
            $params = ['userid' => $userid];
            if ($courseid > 0) {
                $where .= ' AND courseid = :courseid';
                $params['courseid'] = $courseid;
            }
            if ($unitcode !== '') {
                $where .= ' AND unitcode = :unitcode';
                $params['unitcode'] = $unitcode;
            }
            $events = $DB->get_records_select('flwcupkp_evidence', $where, $params,
                'targettype ASC, targetid ASC', 'id, targettype, targetid', 0, $limit * 4);
            foreach ($events as $event) {
                self::add_target($targets, (string)$event->targettype, (int)$event->targetid, $frameworkid);
            }

            $states = $DB->get_records('flwcupkp_state', ['userid' => $userid], 'targettype ASC, targetid ASC',
                'id, targettype, targetid');
            foreach ($states as $state) {
                self::add_target($targets, (string)$state->targettype, (int)$state->targetid, $frameworkid);
            }
        }

        self::expand_parent_targets($targets, $frameworkid);
        return array_slice(array_values($targets), 0, $limit);
    }

    /**
     * Add a target row if valid and in framework scope.
     *
     * @param array $targets
     * @param string $targettype
     * @param int $targetid
     * @param int $frameworkid
     */
    private static function add_target(array &$targets, string $targettype, int $targetid, int $frameworkid = 0): void {
        $target = self::target_record($targettype, $targetid);
        if (!$target) {
            return;
        }
        if ($frameworkid > 0 && (int)($target->frameworkid ?? 0) !== $frameworkid) {
            return;
        }
        $key = $targettype . ':' . $targetid;
        $targets[$key] = [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'externalid' => (string)($target->externalid ?? ''),
            'title' => (string)($target->title ?? ''),
            'frameworkid' => (int)($target->frameworkid ?? 0),
        ];
    }

    /**
     * Include UP and competency parent targets.
     *
     * @param array $targets
     * @param int $frameworkid
     */
    private static function expand_parent_targets(array &$targets, int $frameworkid): void {
        global $DB;

        $keys = array_keys($targets);
        foreach ($keys as $key) {
            $target = $targets[$key];
            if ($target['targettype'] === 'kp') {
                $maps = $DB->get_records('flwcupkp_up_kp', ['kpid' => $target['targetid']]);
                foreach ($maps as $map) {
                    self::add_target($targets, 'up', (int)$map->upid, $frameworkid);
                }
            }
        }
        $keys = array_keys($targets);
        foreach ($keys as $key) {
            $target = $targets[$key];
            if ($target['targettype'] === 'up') {
                $maps = $DB->get_records('flwcupkp_comp_up', ['upid' => $target['targetid']]);
                foreach ($maps as $map) {
                    self::add_target($targets, 'competency', (int)$map->competencyid, $frameworkid);
                }
            }
        }
    }

    /**
     * Return a target record by type.
     *
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function target_record(string $targettype, int $targetid): ?\stdClass {
        global $DB;

        try {
            $table = evidence_guard::target_table($targettype);
        } catch (\Throwable $e) {
            return null;
        }
        $record = $DB->get_record($table, ['id' => $targetid], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Evidence rows for a learner-target in scope.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function evidence_for_target(int $userid, string $targettype, int $targetid, int $courseid,
            string $unitcode): array {
        global $DB;

        $where = 'userid = :userid AND targettype = :targettype AND targetid = :targetid';
        $params = [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ];
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        return array_values($DB->get_records_select('flwcupkp_evidence', $where, $params,
            'timecreated ASC, id ASC'));
    }

    /**
     * Stored state row for a learner-target.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function stored_state(int $userid, string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $record = $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Current cache freshness status.
     *
     * @param \stdClass|null $stored
     * @param array $calculated
     * @return string
     */
    private static function comparison_status(?\stdClass $stored, array $calculated): string {
        if (!$stored) {
            return 'missing_current_state';
        }
        if (empty($stored->retentionstate)) {
            return 'created';
        }
        if (self::retention_differs($stored, $calculated)) {
            return 'changed';
        }
        if (self::retention_metadata_missing($stored)) {
            return 'metadata_missing';
        }
        return 'current';
    }

    /**
     * Whether stored retention state differs from fresh calculation.
     *
     * @param \stdClass $stored
     * @param array $calculated
     * @return bool
     */
    private static function retention_differs(\stdClass $stored, array $calculated): bool {
        return (string)($stored->retentionstate ?? '') !== (string)$calculated['retentionstate'] ||
            abs((float)($stored->retentionconfidence ?? 0) - (float)$calculated['retentionconfidence']) > 0.00001 ||
            (int)($stored->retentionnextreview ?? 0) !== (int)($calculated['retentionnextreview'] ?? 0) ||
            (int)($stored->retentionlastretrieval ?? 0) !== (int)($calculated['retentionlastretrieval'] ?? 0) ||
            (int)($stored->retentionretrievalcount ?? 0) !== (int)$calculated['retentionretrievalcount'] ||
            (string)($stored->retentionpolicyversion ?? '') !== (string)$calculated['retentionpolicyversion'] ||
            (string)($stored->retentionevidencehash ?? '') !== (string)$calculated['retentionevidencehash'];
    }

    /**
     * Whether E3 retention snapshot metadata is missing.
     *
     * @param \stdClass $stored
     * @return bool
     */
    private static function retention_metadata_missing(\stdClass $stored): bool {
        return empty($stored->retentionstate) ||
            empty($stored->retentionpolicyversion) ||
            empty($stored->retentionevidencehash) ||
            empty($stored->retentionevidenceidsjson) ||
            empty($stored->retentioncalculatedtime);
    }

    /**
     * Human-readable reason for comparison status.
     *
     * @param string $status
     * @return string
     */
    private static function comparison_reason(string $status): string {
        $reasons = [
            'missing_current_state' => 'E2 current-state row is required before retention can be cached',
            'created' => 'no retention snapshot exists yet',
            'changed' => 'stored retention state differs from rebuilt state',
            'metadata_missing' => 'stored retention row lacks E3 snapshot metadata',
            'current' => 'stored retention snapshot matches rebuilt state',
        ];
        return $reasons[$status] ?? $status;
    }

    /**
     * Summary bucket for rebuild status.
     *
     * @param string $status
     * @return string
     */
    private static function summary_bucket(string $status): string {
        $map = [
            'metadata_missing' => 'metadata_refreshed',
        ];
        return $map[$status] ?? $status;
    }

    /**
     * Evidence summary for display/API.
     *
     * @param array $events
     * @param array $calculated
     * @return array
     */
    private static function evidence_summary(array $events, array $calculated): array {
        $summary = [
            'count' => count($events),
            'ids' => $calculated['retentionevidenceids'] ?? [],
            'hash' => (string)($calculated['retentionevidencehash'] ?? ''),
            'history_v1' => 0,
            'legacy_direct' => 0,
            'latest' => null,
        ];
        foreach ($events as $event) {
            if ((string)($event->provenance ?? '') === history_evidence_adapter::PROVENANCE) {
                $summary['history_v1']++;
            } else {
                $summary['legacy_direct']++;
            }
            if (!$summary['latest'] || (int)$event->timecreated > (int)$summary['latest']['timecreated']) {
                $semantics = self::event_semantics($event);
                $summary['latest'] = [
                    'id' => (int)($event->id ?? 0),
                    'evidencetype' => (string)($event->evidencetype ?? ''),
                    'score' => round((float)($event->normalizedscore ?? 0), 5),
                    'confidence' => round((float)($event->confidence ?? 0), 5),
                    'strength' => (string)($event->evidencestrength ?? ''),
                    'result_state' => (string)$semantics['result_state'],
                    'performance_mode' => (string)$semantics['performance_mode'],
                    'provenance' => (string)($event->provenance ?? ''),
                    'timecreated' => (int)($event->timecreated ?? 0),
                ];
            }
        }
        return $summary;
    }

    /**
     * Extract C3B semantics from rubric JSON.
     *
     * @param \stdClass $event
     * @return array
     */
    private static function rubric_semantics(\stdClass $event): array {
        $rubric = json_decode((string)($event->rubricjson ?? ''), true);
        if (!is_array($rubric)) {
            return [];
        }
        $semantics = $rubric['cupkp_c3b_semantics'] ?? [];
        return is_array($semantics) ? $semantics : [];
    }

    /**
     * Learner summary from rows.
     *
     * @param array $states
     * @return array
     */
    private static function learner_summary(array $states): array {
        $summary = self::empty_summary();
        $summary['states'] = count($states);
        foreach ($states as $state) {
            $type = $state['target']['type'];
            if (isset($summary[$type])) {
                $summary[$type]++;
            }
            $retentionstate = (string)($state['retention']['state'] ?? '');
            if (isset($summary[$retentionstate])) {
                $summary[$retentionstate]++;
            }
            if (($state['retention']['confidence'] ?? 0) < 0.50) {
                $summary['low_confidence']++;
            }
            if (in_array($state['status'], ['created', 'changed', 'metadata_missing'], true)) {
                $summary['stale_or_missing_cache']++;
            }
            if (($state['evidence']['history_v1'] ?? 0) > 0) {
                $summary['history_v1_evidence'] += (int)$state['evidence']['history_v1'];
            }
        }
        return $summary;
    }

    /**
     * Empty summary counters.
     *
     * @return array
     */
    private static function empty_summary(): array {
        return [
            'learners' => 0,
            'states' => 0,
            'kp' => 0,
            'up' => 0,
            'competency' => 0,
            'new' => 0,
            'learning' => 0,
            'consolidating' => 0,
            'retained' => 0,
            'review_due' => 0,
            'retention_uncertain' => 0,
            'relearning' => 0,
            'low_confidence' => 0,
            'stale_or_missing_cache' => 0,
            'history_v1_evidence' => 0,
            'skipped_unenrolled' => 0,
        ];
    }

    /**
     * Merge learner counters into class counters.
     *
     * @param array $summary
     * @param array $row
     */
    private static function merge_summary(array &$summary, array $row): void {
        foreach ($row as $key => $value) {
            if ($key === 'learners') {
                continue;
            }
            if (isset($summary[$key]) && is_numeric($value)) {
                $summary[$key] += (int)$value;
            }
        }
    }

    /**
     * Learner IDs for rebuild scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function learner_ids_for_scope(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $userids = [];

        if ($courseid > 0) {
            foreach (self::course_learner_ids($courseid, $limit) as $userid) {
                self::add_learner_id($userids, $userid);
            }
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
        $records = $DB->get_records_select('flwcupkp_evidence', $where, $params, 'userid ASC',
            'DISTINCT userid', 0, $limit);
        foreach ($records as $record) {
            self::add_learner_id($userids, (int)$record->userid);
        }

        $targets = self::scoped_targets(0, $courseid, $unitcode, $frameworkid, $limit);
        foreach ($targets as $target) {
            $records = $DB->get_records('flwcupkp_state', [
                'targettype' => $target['targettype'],
                'targetid' => (int)$target['targetid'],
            ], 'userid ASC', 'DISTINCT userid', 0, $limit);
            foreach ($records as $record) {
                self::add_learner_id($userids, (int)$record->userid);
            }
        }

        if ($courseid === 0 && $unitcode === '' && $frameworkid === 0) {
            $records = $DB->get_records('flwcupkp_state', [], 'userid ASC', 'DISTINCT userid', 0, $limit);
            foreach ($records as $record) {
                self::add_learner_id($userids, (int)$record->userid);
            }
        }

        sort($userids, SORT_NUMERIC);
        return array_slice(array_values($userids), 0, $limit);
    }

    /**
     * Add a valid learner ID to a keyed unique list.
     *
     * @param array $userids
     * @param int $userid
     */
    private static function add_learner_id(array &$userids, int $userid): void {
        if ($userid <= 0) {
            return;
        }
        $userids[$userid] = $userid;
    }

    /**
     * Enrolled learner IDs for a course.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    private static function course_learner_ids(int $courseid, int $limit): array {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        $users = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname ASC, u.firstname ASC, u.id ASC', 0,
            self::bounded_limit($limit, 500), true);
        return array_values(array_map(static function($user): int {
            return (int)$user->id;
        }, $users));
    }

    /**
     * Object scope clause.
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
            $where .= ' AND (o.courseid = :courseid OR o.courseid IS NULL OR o.courseid = 0)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND o.unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return ['where' => $where, 'params' => $params];
    }

    /**
     * Status criteria.
     *
     * @param array $e2
     * @param array $schema
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $e2, array $schema, array $files, array $surface): array {
        return [
            'e2_current_state_consumed' => self::criterion(
                'e2_current_state_consumed',
                ($e2['status'] ?? '') === 'ready' &&
                    ($e2['contract']['version'] ?? '') === mastery_state_service::CONTRACT_VERSION,
                'E3 consumes the frozen E2 current learner-state service.'
            ),
            'retention_cache_metadata_present' => self::criterion(
                'retention_cache_metadata_present',
                $schema['valid'],
                'flwcupkp_state stores E3 retention snapshot metadata.'
            ),
            'state_surfaces_present' => self::criterion(
                'state_surfaces_present',
                $files['valid'] && $surface['valid'],
                'Admin page, CLI, service, and web-service methods are present.'
            ),
            'mastery_retention_separated' => self::criterion(
                'mastery_retention_separated',
                true,
                'Retention state never rewrites mastery score or mastery state.'
            ),
            'review_quality_model_present' => self::criterion(
                'review_quality_model_present',
                true,
                'Review quality prefers target-appropriate retrieval/transfer evidence.'
            ),
            'failed_review_non_destructive' => self::criterion(
                'failed_review_non_destructive',
                true,
                'Failed review moves retention state without immediately erasing mastery.'
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
     * Schema status for E3 fields.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $columns = $DB->get_columns('flwcupkp_state');
        $present = [];
        $missing = [];
        foreach (self::required_retention_fields() as $field) {
            $present[$field] = isset($columns[$field]);
            if (!$present[$field]) {
                $missing[] = $field;
            }
        }
        return [
            'valid' => empty($missing),
            'table' => 'flwcupkp_state',
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * File status for E3.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'retention_review.php',
            'cli/retention_review.php',
            'classes/local/retention_review_service.php',
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
        return [
            'valid' => empty($missing),
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * Method status for E3.
     *
     * @return array
     */
    private static function surface_status(): array {
        $methods = [
            self::class . '::status' => method_exists(self::class, 'status'),
            self::class . '::current_retention_state' => method_exists(self::class, 'current_retention_state'),
            self::class . '::class_summary' => method_exists(self::class, 'class_summary'),
            self::class . '::preview_rebuild' => method_exists(self::class, 'preview_rebuild'),
            self::class . '::apply_rebuild' => method_exists(self::class, 'apply_rebuild'),
            repository::class . '::update_retention_state' => method_exists(repository::class, 'update_retention_state'),
        ];
        $missing = array_keys(array_filter($methods, static function(bool $present): bool {
            return !$present;
        }));
        return [
            'valid' => empty($missing),
            'methods' => $methods,
            'missing_methods' => $missing,
        ];
    }

    /**
     * Cache summary counts.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function cache_summary(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        $targets = self::scoped_targets(0, $courseid, $unitcode, $frameworkid, $limit);
        $statecount = 0;
        $ready = 0;
        $reviewdue = 0;
        $uncertain = 0;
        $relearning = 0;

        if (!$targets) {
            $rows = $DB->get_records('flwcupkp_state', [], 'id ASC', '*', 0, $limit);
        } else {
            $rows = [];
            foreach ($targets as $target) {
                foreach ($DB->get_records('flwcupkp_state', [
                    'targettype' => $target['targettype'],
                    'targetid' => $target['targetid'],
                ], 'id ASC') as $row) {
                    $rows[$row->id] = $row;
                }
            }
        }

        foreach ($rows as $row) {
            $statecount++;
            if (!self::retention_metadata_missing($row)) {
                $ready++;
            }
            if ((string)($row->retentionstate ?? '') === 'review_due') {
                $reviewdue++;
            }
            if ((string)($row->retentionstate ?? '') === 'retention_uncertain') {
                $uncertain++;
            }
            if ((string)($row->retentionstate ?? '') === 'relearning') {
                $relearning++;
            }
        }

        return [
            'target_scope_count' => count($targets),
            'state_rows' => $statecount,
            'retention_ready_rows' => $ready,
            'retention_missing_rows' => max(0, $statecount - $ready),
            'review_due_rows' => $reviewdue,
            'retention_uncertain_rows' => $uncertain,
            'relearning_rows' => $relearning,
        ];
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
            'contract' => $dependency['contract']['version'] ?? ($dependency['contract'] ?? ''),
            'next_allowed_gate' => $dependency['next_allowed_gate'] ?? '',
            'findings' => count($dependency['findings'] ?? []),
        ];
    }

    /**
     * Findings from failed criteria/dependencies.
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
                $findings[] = [
                    'severity' => strtolower((string)($finding['severity'] ?? 'warning')),
                    'code' => (string)($finding['code'] ?? 'dependency_finding'),
                    'message' => (string)($finding['message'] ?? json_encode($finding)),
                ];
            }
        }
        return $findings;
    }

    /**
     * Safe dependency wrapper.
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
     * Empty rebuild result when status is blocked.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param bool $write
     * @param array $findings
     * @return array
     */
    private static function empty_rebuild_result(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, bool $write, array $findings): array {
        return [
            'type' => 'CupkpRetentionReviewRebuildResult',
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
            ],
            'summary' => [
                'learners' => 0,
                'targets_seen' => 0,
                'created' => 0,
                'changed' => 0,
                'metadata_refreshed' => 0,
                'current' => 0,
                'missing_current_state' => 0,
                'skipped_unenrolled' => 0,
                'review_due' => 0,
                'retention_uncertain' => 0,
                'relearning' => 0,
                'applied' => 0,
                'skipped' => 0,
            ],
            'changes' => [],
            'findings' => $findings,
            'read_only' => !$write,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Retention state type label.
     *
     * @param string $targettype
     * @return string
     */
    private static function retention_state_type(string $targettype): string {
        $map = [
            'kp' => 'LearnerKPRetentionState',
            'up' => 'LearnerUPRetentionState',
            'competency' => 'LearnerCompetencyRetentionState',
        ];
        return $map[$targettype] ?? 'LearnerRetentionState';
    }

    /**
     * Whether a target state is strong.
     *
     * @param string $targettype
     * @param string $state
     * @return bool
     */
    private static function is_strong_state(string $targettype, string $state): bool {
        return in_array($state, self::STRONG_STATES[$targettype] ?? [], true);
    }

    /**
     * Canonical uppercase label.
     *
     * @param string $state
     * @return string
     */
    private static function canonical_state(string $state): string {
        return self::RETENTION_STATES[$state] ?? strtoupper($state);
    }

    /**
     * Required retention fields in flwcupkp_state.
     *
     * @return array
     */
    private static function required_retention_fields(): array {
        return [
            'retentionstate',
            'retentionconfidence',
            'retentionnextreview',
            'retentionlastretrieval',
            'retentionretrievalcount',
            'retentionpolicyversion',
            'retentionevidencehash',
            'retentionevidenceidsjson',
            'retentioncalculatedtime',
        ];
    }

    /**
     * Target review interval in seconds.
     *
     * @param string $targettype
     * @return int
     */
    private static function review_interval_seconds(string $targettype): int {
        return (self::REVIEW_INTERVAL_DAYS[$targettype] ?? 21) * DAYSECS;
    }

    /**
     * Day-level timestamp used for generated review due dates.
     *
     * @param int $now
     * @return int
     */
    private static function review_anchor(int $now): int {
        return $now - ($now % DAYSECS);
    }

    /**
     * Bounded recency factor for retention confidence.
     *
     * @param int $lastsuccess
     * @param string $targettype
     * @param int $now
     * @return float
     */
    private static function bounded_recency(int $lastsuccess, string $targettype, int $now): float {
        if ($lastsuccess <= 0) {
            return 0.20;
        }
        $age = max(0, $now - $lastsuccess);
        $interval = self::review_interval_seconds($targettype);
        if ($age <= $interval * 0.25) {
            return 1.00;
        }
        if ($age <= $interval * 0.50) {
            return 0.85;
        }
        if ($age <= $interval) {
            return 0.65;
        }
        if ($age <= $interval * 2) {
            return 0.45;
        }
        return 0.30;
    }

    /**
     * Evidence row IDs used by the retention snapshot.
     *
     * @param array $analysis
     * @param array $events
     * @return array
     */
    private static function retention_evidence_ids(array $analysis, array $events): array {
        $ids = array_values(array_filter(array_map('intval', $analysis['retrieval_evidence_ids'] ?? [])));
        if (empty($ids)) {
            foreach ($events as $event) {
                if (!empty($event->id)) {
                    $ids[] = (int)$event->id;
                }
            }
        }
        sort($ids, SORT_NUMERIC);
        return array_values(array_unique($ids));
    }

    /**
     * Hash the normalized evidence and E3 interpretation inputs.
     *
     * @param array $events
     * @param array $analysis
     * @return string
     */
    private static function retention_evidence_hash(array $events, array $analysis): string {
        $fingerprints = [];
        $index = 0;
        foreach ($events as $event) {
            $semantics = self::event_semantics($event);
            $fingerprints[] = [
                'id' => (int)($event->id ?? 0),
                'index' => $index++,
                'timecreated' => (int)($event->timecreated ?? 0),
                'targettype' => (string)($event->targettype ?? ''),
                'targetid' => (int)($event->targetid ?? 0),
                'score' => round((float)($event->normalizedscore ?? 0), 5),
                'confidence' => round((float)($event->confidence ?? 0), 5),
                'strength' => (string)($event->evidencestrength ?? ''),
                'result_state' => (string)$semantics['result_state'],
                'performance_mode' => (string)$semantics['performance_mode'],
                'rubrichash' => sha1((string)($event->rubricjson ?? '')),
            ];
        }
        usort($fingerprints, static function(array $a, array $b): int {
            return [$a['timecreated'], $a['id'], $a['index']] <=> [$b['timecreated'], $b['id'], $b['index']];
        });
        return hash('sha256', json_encode([
            'policy' => self::RETENTION_POLICY_VERSION,
            'analysis' => [
                'targettype' => $analysis['targettype'] ?? '',
                'successful_retrieval_count' => $analysis['successful_retrieval_count'] ?? 0,
                'failed_retrieval_count' => $analysis['failed_retrieval_count'] ?? 0,
                'last_successful_retrieval_time' => $analysis['last_successful_retrieval_time'] ?? 0,
                'latest_failed_retrieval_time' => $analysis['latest_failed_retrieval_time'] ?? 0,
            ],
            'evidence' => $fingerprints,
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * Maximum event time from analysis rows.
     *
     * @param array $rows
     * @return int
     */
    private static function max_event_time(array $rows): int {
        $time = 0;
        foreach ($rows as $row) {
            $time = max($time, (int)($row['timecreated'] ?? 0));
        }
        return $time;
    }

    /**
     * Best quality from analysis rows.
     *
     * @param array $rows
     * @return float
     */
    private static function best_event_quality(array $rows): float {
        $best = 0.0;
        foreach ($rows as $row) {
            $best = max($best, (float)($row['quality'] ?? 0));
        }
        return $best;
    }

    /**
     * Average quality from analysis rows.
     *
     * @param array $rows
     * @return float
     */
    private static function average_event_quality(array $rows): float {
        if (empty($rows)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float)($row['quality'] ?? 0);
        }
        return $sum / count($rows);
    }

    /**
     * Clamp a value to the policy range.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
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

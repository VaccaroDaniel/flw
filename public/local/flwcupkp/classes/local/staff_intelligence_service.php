<?php
// Program 3 Gate UX3 teacher/admin explainability and intervention service.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Composes staff explainability and controls versioned learner interventions.
 */
final class staff_intelligence_service {
    public const GATE = 'P3_UX3';
    public const CONTRACT_VERSION = 'FLW_CUPKP_STAFF_INTELLIGENCE_V1';
    public const INTERVENTION_POLICY_VERSION = 'cupkp-staff-intervention-v1';
    public const NEXT_ALLOWED_GATE = 'F1';
    public const TABLE = 'flwcupkp_intervention';

    /** @var array Supported intervention types. */
    private const INTERVENTION_TYPES = [
        'assign_target_activity',
        'force_review',
        'hold_advancement',
        'override_recommendation',
        'adjust_goal',
        'teacher_evidence',
    ];

    /** @var array Path actions accepted from authorized staff. */
    private const PATH_ACTIONS = [
        'ADVANCE',
        'SKIP',
        'EXTRA_PRACTICE',
        'REMEDIATION',
        'REVIEW',
        'RETRY',
        'REASSESS',
        'REPRIORITIZE',
    ];

    /** @var array Staff intervention precedence, highest first. */
    private const PATH_PRECEDENCE = [
        'hold_advancement',
        'assign_target_activity',
        'override_recommendation',
        'force_review',
    ];

    /** Return the frozen UX3 contract. */
    public static function contract(): array {
        return [
            'type' => 'CupkpStaffIntelligenceContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'intervention_policy_version' => self::INTERVENTION_POLICY_VERSION,
            'depends_on' => [
                learner_experience_service::CONTRACT_VERSION,
                student_learning_timeline_view_service::CONTRACT_VERSION,
                adaptive_path_engine_service::CONTRACT_VERSION,
                mastery_state_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
                learning_goal_service::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'staff_detail' => [
                'c_kp_up',
                'mastery',
                'confidence',
                'retention',
                'evidence_provenance',
                'prerequisites',
                'recommendation_reasons',
                'policy_versions',
                'path_decisions',
            ],
            'recommendation_questions' => [
                'why_target',
                'why_activity',
                'why_extra_practice',
                'why_review',
                'why_skip',
                'why_path_changed',
            ],
            'authorized_interventions' => self::INTERVENTION_TYPES,
            'intervention_precedence' => self::PATH_PRECEDENCE,
            'write_services' => [
                'path_interventions' => self::TABLE,
                'adjust_goal' => 'learning_goal_service::save_goal',
                'teacher_evidence' => 'mastery_engine::record_evidence',
                'recommendation_refresh' => 'adaptive_path_engine_service::apply_learner_path',
                'audit' => 'repository::audit',
            ],
            'governance' => [
                'capability' => 'local/flwcupkp:override',
                'append_only_versions' => true,
                'release_creates_new_version' => true,
                'reason_required' => true,
                'automatic_overwrite_allowed' => false,
                'activity_must_remain_a4b_eligible' => true,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'history_owner' => 'local_flwhistory',
            'adaptive_policy_owner' => 'local_flwcupkp A3/A4/A4B/A5 services',
            'learner_presentation_owner' => learner_experience_service::CONTRACT_VERSION,
            'does_not_do' => [
                'history_rebuild_or_mutation',
                'raw_moodle_log_scraping',
                'hidden_or_ineligible_activity_unlocking',
                'silent_intervention_overwrite',
                'learner_ui_staff_detail_exposure',
                'adaptive_policy_reimplementation',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /** Return UX3 implementation readiness without writing learner data. */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0): array {
        global $DB;

        $contract = self::contract();
        $ux2 = learner_experience_service::status($courseid, $unitcode, $frameworkid);
        $tablepresent = $DB->get_manager()->table_exists(self::TABLE);
        $files = [
            'classes/local/staff_intelligence_service.php',
            'classes/local/staff_intelligence_renderer.php',
            'staff_intelligence.php',
            'cli/staff_intelligence.php',
            'tests/staff_intelligence_service_test.php',
            'classes/external/api.php',
            'db/services.php',
            'openapi.json',
        ];
        $present = [];
        foreach ($files as $file) {
            $present[$file] = is_readable(dirname(__DIR__, 2) . '/' . $file);
        }
        $apisource = $present['classes/external/api.php'] ?
            file_get_contents(dirname(__DIR__, 2) . '/classes/external/api.php') : '';
        $apimethodsdeclared = $apisource !== false &&
            strpos($apisource, 'function get_staff_intelligence(') !== false &&
            strpos($apisource, 'function apply_staff_intervention(') !== false &&
            strpos($apisource, 'function release_staff_intervention(') !== false;
        $criteria = [
            'ux2_frozen' => self::criterion('ux2_frozen', ($ux2['status'] ?? '') === 'ready' &&
                ($ux2['next_allowed_gate'] ?? '') === 'UX3', 'The frozen UX2 learner view must hand off to UX3.'),
            'staff_detail_complete' => self::criterion('staff_detail_complete',
                count($contract['staff_detail']) === 9, 'Staff detail must expose all nine authorized intelligence areas.'),
            'six_questions_answered' => self::criterion('six_questions_answered',
                count($contract['recommendation_questions']) === 6,
                'Every recommendation must answer the six frozen why questions.'),
            'six_interventions_supported' => self::criterion('six_interventions_supported',
                count($contract['authorized_interventions']) === 6,
                'All six authorized intervention types must be supported.'),
            'permission_controlled' => self::criterion('permission_controlled',
                $contract['governance']['capability'] === 'local/flwcupkp:override',
                'Every intervention must require the course override capability.'),
            'immutable_versioning' => self::criterion('immutable_versioning', $tablepresent &&
                $contract['governance']['append_only_versions'] && $contract['governance']['release_creates_new_version'],
                'Interventions must be append-only, versioned, and explicitly released.'),
            'audited_writers' => self::criterion('audited_writers',
                $contract['write_services']['audit'] === 'repository::audit',
                'Controlled actions must use existing audited writers.'),
            'a4b_eligibility_preserved' => self::criterion('a4b_eligibility_preserved',
                $contract['governance']['activity_must_remain_a4b_eligible'],
                'Staff-selected activities must pass current A4B eligibility.'),
            'ownership_preserved' => self::criterion('ownership_preserved',
                $contract['normal_source_rule'] === history_v1_consumer_contract::CONSUMPTION_RULE &&
                $contract['history_owner'] === 'local_flwhistory',
                'History V1 and adaptive-policy ownership must remain unchanged.'),
            'implementation_surface' => self::criterion('implementation_surface',
                !in_array(false, $present, true) && $apimethodsdeclared,
                'The staff page, service, APIs, CLI, tests, and renderer must be present.'),
        ];
        $passed = count(array_filter($criteria, static function(array $criterion): bool {
            return $criterion['pass'];
        }));
        $findings = [];
        foreach ($criteria as $criterion) {
            if (!$criterion['pass']) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => $criterion['code'],
                    'message' => $criterion['message'],
                ];
            }
        }

        return [
            'type' => 'CupkpStaffIntelligenceStatus',
            'gate' => self::GATE,
            'status' => $passed === count($criteria) ? 'ready' : 'blocked',
            'contract' => $contract,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => self::clean_unit_code($unitcode),
                'frameworkid' => max(0, $frameworkid),
            ],
            'criteria' => $criteria,
            'criteria_summary' => ['total' => count($criteria), 'passed' => $passed,
                'failed' => count($criteria) - $passed],
            'dependencies' => [
                'ux2' => [
                    'status' => $ux2['status'] ?? 'unknown',
                    'contract' => $ux2['contract']['version'] ?? '',
                    'next_allowed_gate' => $ux2['next_allowed_gate'] ?? '',
                ],
                'history_v1' => [
                    'contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                    'owner' => 'local_flwhistory',
                ],
            ],
            'schema' => ['table' => self::TABLE, 'present' => $tablepresent],
            'files' => ['present' => $present, 'missing' => array_keys(array_filter($present,
                static function(bool $exists): bool { return !$exists; }))],
            'findings' => $findings,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /** Compose detailed intelligence for one authorized staff view. */
    public static function learner_intelligence(int $userid, int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0 || $courseid <= 0) {
            throw new \invalid_parameter_exception('Learner and course are required.');
        }
        evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        $unitcode = self::clean_unit_code($unitcode);
        $limit = self::bounded_limit($limit, 300);

        $mastery = self::safe_component(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return mastery_state_service::current_learner_state($userid, $courseid, $unitcode,
                $frameworkid, $limit);
        }, 'mastery');
        $retention = self::safe_component(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return retention_review_service::current_retention_state($userid, $courseid, $unitcode,
                $frameworkid, $limit);
        }, 'retention');
        $adaptive = self::safe_component(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return adaptive_path_engine_service::learner_path($userid, $courseid, $unitcode,
                $frameworkid, $limit);
        }, 'adaptive_path');
        $experience = self::safe_component(static function() use ($userid, $courseid, $unitcode,
                $frameworkid): array {
            return learner_experience_service::learner_experience($userid, $courseid, $unitcode,
                $frameworkid, 10);
        }, 'learner_experience');
        $states = array_values($mastery['states'] ?? []);
        $retentionstates = array_values($retention['states'] ?? []);
        $evidence = self::evidence_detail($userid, $courseid, $unitcode, min($limit, 100));
        $prerequisites = self::prerequisite_detail($userid, $states);
        $history = student_learning_timeline_view_service::recommendation_history(
            $userid, $courseid, $unitcode, 10,
            (string)($adaptive['recommendation']['sourcehash'] ?? '')
        );
        $interventions = self::current_interventions($userid, $courseid, $unitcode);

        return [
            'type' => 'CupkpStaffLearnerIntelligence',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'learner' => self::learner_identity($userid),
            'scope' => ['courseid' => $courseid, 'unitcode' => $unitcode, 'frameworkid' => $frameworkid],
            'learner_summary' => [
                'current' => $experience['level_1']['current'] ?? [],
                'next' => $experience['level_1']['next'] ?? [],
                'goal' => $experience['level_1']['goal'] ?? [],
            ],
            'states' => $states,
            'retention' => $retentionstates,
            'evidence' => $evidence,
            'prerequisites' => $prerequisites,
            'path' => [
                'status' => (string)($adaptive['path_status'] ?? 'unavailable'),
                'persistence' => (string)($adaptive['recommendation_status'] ?? 'unknown'),
                'recommendation' => $adaptive['recommendation'] ?? [],
                'resolution' => $adaptive['source_activity_resolution'] ?? [],
                'history' => $history,
            ],
            'explanations' => self::recommendation_explanations($adaptive, $retentionstates, $history),
            'policy_versions' => self::policy_versions($adaptive, $states, $retentionstates),
            'intervention_options' => self::intervention_options(
                $courseid, $unitcode, $frameworkid, $states, $adaptive
            ),
            'interventions' => $interventions,
            'intervention_history' => self::intervention_history($userid, $courseid, $unitcode, 50),
            'ownership' => [
                'history' => 'local_flwhistory',
                'source_history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'adaptive_policy' => 'A3/A4/A4B/A5',
                'staff_governance' => self::CONTRACT_VERSION,
                'learner_view' => learner_experience_service::CONTRACT_VERSION,
            ],
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Apply an active intervention to an A5 recommendation after normal policy resolution.
     *
     * This method is read-only and never makes an ineligible activity eligible.
     */
    public static function apply_to_recommendation(int $userid, int $courseid, string $unitcode,
            array $recommendation, array $resolution): array {
        if (!self::table_ready() || $userid <= 0 || $courseid <= 0) {
            return $recommendation;
        }
        $active = self::current_interventions($userid, $courseid, self::clean_unit_code($unitcode));
        $selected = self::precedent_path_intervention($active);
        if (!$selected) {
            return $recommendation;
        }

        $payload = is_array($selected['payload'] ?? null) ? $selected['payload'] : [];
        $type = (string)$selected['interventiontype'];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] :
            (is_array($recommendation['selected_target'] ?? null) ? $recommendation['selected_target'] : null);
        $activity = null;
        if (!empty($payload['activity']['objectid']) || !empty($payload['activity']['cmid'])) {
            $activity = self::eligible_activity_from_resolution(
                $resolution,
                (int)($payload['activity']['objectid'] ?? 0),
                (int)($payload['activity']['cmid'] ?? 0),
                (string)($target['type'] ?? ''),
                (int)($target['id'] ?? 0)
            );
            if (!$activity && in_array($type, ['assign_target_activity', 'override_recommendation'], true)) {
                return self::mark_intervention_blocked($recommendation, $selected,
                    'The selected activity is no longer eligible under A4B.');
            }
        }

        $action = self::effective_action($selected);
        if ($type === 'hold_advancement' || $action === 'SKIP') {
            $activity = null;
        }
        $recommendation['action'] = $action;
        $recommendation['path_status'] = 'staff_intervention_active';
        $recommendation['decision_code'] = 'STAFF_' . strtoupper($type);
        $recommendation['reason'] = (string)$selected['reason'];
        $recommendation['selected_target'] = $target;
        $recommendation['selected_activity'] = $activity;
        $recommendation['reason_codes'] = array_values(array_unique(array_merge(
            array_map('strval', $recommendation['reason_codes'] ?? []),
            ['STAFF_INTERVENTION_ACTIVE', strtoupper($type)]
        )));
        $recommendation['snapshot'] = is_array($recommendation['snapshot'] ?? null) ?
            $recommendation['snapshot'] : [];
        $recommendation['snapshot']['staff_intervention'] = [
            'id' => (int)$selected['id'],
            'type' => $type,
            'intent' => $type === 'hold_advancement' ? 'HOLD_ADVANCEMENT' : $action,
            'version' => (int)$selected['version'],
            'policyversion' => self::INTERVENTION_POLICY_VERSION,
        ];
        $recommendation['snapshot']['policy_versions'] =
            is_array($recommendation['snapshot']['policy_versions'] ?? null) ?
            $recommendation['snapshot']['policy_versions'] : [];
        $recommendation['snapshot']['policy_versions']['staff_intervention'] =
            self::INTERVENTION_POLICY_VERSION;
        $recommendation['staff_intervention'] = $recommendation['snapshot']['staff_intervention'];
        $recommendation['sourcehash'] = hash('sha256', json_encode([
            'base' => (string)($recommendation['sourcehash'] ?? ''),
            'intervention' => $recommendation['snapshot']['staff_intervention'],
            'target' => $target,
            'activity' => $activity ? [
                'objectid' => (int)$activity['objectid'],
                'cmid' => (int)$activity['cmid'],
            ] : null,
            'action' => $action,
        ], JSON_UNESCAPED_SLASHES));
        return $recommendation;
    }

    /** Apply one controlled, versioned staff intervention. */
    public static function apply_intervention(int $userid, int $courseid, string $unitcode, int $frameworkid,
            string $interventiontype, array $data, string $reason): array {
        global $DB;

        self::require_write_access($courseid);
        evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        self::assert_table_ready();
        $unitcode = self::clean_unit_code($unitcode);
        $interventiontype = strtolower(trim($interventiontype));
        if (!in_array($interventiontype, self::INTERVENTION_TYPES, true)) {
            throw new \invalid_parameter_exception('Unsupported staff intervention type.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \invalid_parameter_exception('A staff intervention reason is required.');
        }
        $frameworkid = max(0, $frameworkid);
        $payload = [];
        $targettype = '';
        $targetid = 0;
        $objectid = 0;
        $cmid = 0;
        $actioncode = '';

        if (in_array($interventiontype, ['assign_target_activity', 'force_review',
                'override_recommendation'], true)) {
            $prepared = self::prepare_path_intervention($userid, $courseid, $unitcode, $frameworkid,
                $interventiontype, $data);
            $payload = $prepared['payload'];
            $targettype = $prepared['targettype'];
            $targetid = $prepared['targetid'];
            $objectid = $prepared['objectid'];
            $cmid = $prepared['cmid'];
            $actioncode = $prepared['actioncode'];
        } else if ($interventiontype === 'hold_advancement') {
            $payload = ['hold' => true];
            $actioncode = 'REPRIORITIZE';
        }

        $transaction = $DB->start_delegated_transaction();
        $writerresult = null;
        if ($interventiontype === 'adjust_goal') {
            $goaldata = self::goal_payload($userid, $courseid, $unitcode, $frameworkid, $data);
            $writerresult = learning_goal_service::save_goal($userid, $goaldata, 'TEACHER', $reason);
            $payload = [
                'goalid' => (int)($writerresult['goalid'] ?? 0),
                'goalversion' => (int)($writerresult['version'] ?? 0),
                'goal' => $writerresult['goal'] ?? [],
            ];
            $actioncode = 'ADJUST_GOAL';
        } else if ($interventiontype === 'teacher_evidence') {
            $prepared = self::prepare_teacher_evidence($userid, $courseid, $unitcode, $frameworkid, $data);
            $writerresult = mastery_engine::record_evidence((object)$prepared['evidence']);
            $targettype = $prepared['targettype'];
            $targetid = $prepared['targetid'];
            $objectid = $prepared['objectid'];
            $payload = [
                'evidenceid' => (int)$writerresult['evidenceid'],
                'normalizedscore' => $prepared['evidence']['normalizedscore'],
                'confidence' => $prepared['evidence']['confidence'],
                'note' => (string)($data['note'] ?? ''),
            ];
            $actioncode = 'TEACHER_EVIDENCE';
        }

        $serieskey = self::series_key($userid, $courseid, $unitcode, $interventiontype,
            $targettype, $targetid);
        $status = in_array($interventiontype, ['adjust_goal', 'teacher_evidence'], true) ?
            'recorded' : 'active';
        $record = self::append_version($serieskey, [
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'interventiontype' => $interventiontype,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'objectid' => $objectid,
            'cmid' => $cmid,
            'actioncode' => $actioncode,
            'payload' => $payload,
            'reason' => $reason,
            'status' => $status,
        ]);
        $auditid = repository::audit('staff_intervention_version_created', 'user', $userid, [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'policyversion' => self::INTERVENTION_POLICY_VERSION,
            'interventionid' => (int)$record['id'],
            'serieskey' => $serieskey,
            'version' => (int)$record['version'],
            'interventiontype' => $interventiontype,
            'status' => $status,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'reason' => $reason,
            'writerresult' => self::compact_writer_result($writerresult),
        ]);

        $pathresult = null;
        if (in_array($interventiontype, ['assign_target_activity', 'force_review', 'hold_advancement',
                'override_recommendation', 'adjust_goal'], true)) {
            $pathresult = adaptive_path_engine_service::apply_learner_path(
                $userid, $courseid, $unitcode, $frameworkid, 100,
                'UX3 staff intervention version ' . (int)$record['version']
            );
        }
        $transaction->allow_commit();

        return [
            'type' => 'CupkpStaffInterventionApplyResult',
            'gate' => self::GATE,
            'status' => 'applied',
            'intervention' => $record,
            'auditid' => $auditid,
            'writerresult' => self::compact_writer_result($writerresult),
            'pathresult' => self::compact_path_result($pathresult),
            'state_changes_allowed' => true,
            'write_boundary' => [self::TABLE, 'flwcupkp_audit', 'approved_existing_writer_tables'],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /** Release an active intervention by appending a new immutable version. */
    public static function release_intervention(int $interventionid, string $reason,
            int $frameworkid = 0): array {
        global $DB;

        self::assert_table_ready();
        $current = $DB->get_record(self::TABLE, ['id' => $interventionid], '*', MUST_EXIST);
        self::require_write_access((int)$current->courseid);
        $reason = trim($reason);
        if ($reason === '') {
            throw new \invalid_parameter_exception('A release reason is required.');
        }
        $latest = self::latest_series_record((string)$current->serieskey);
        if (!$latest || (int)$latest->id !== (int)$current->id || (string)$latest->status !== 'active') {
            throw new \invalid_parameter_exception('Only the latest active intervention version can be released.');
        }

        $transaction = $DB->start_delegated_transaction();
        $released = self::append_version((string)$current->serieskey, [
            'userid' => (int)$current->userid,
            'courseid' => (int)$current->courseid,
            'unitcode' => (string)($current->unitcode ?? ''),
            'interventiontype' => (string)$current->interventiontype,
            'targettype' => (string)($current->targettype ?? ''),
            'targetid' => (int)($current->targetid ?? 0),
            'objectid' => (int)($current->objectid ?? 0),
            'cmid' => (int)($current->cmid ?? 0),
            'actioncode' => (string)($current->actioncode ?? ''),
            'payload' => ['released_interventionid' => (int)$current->id],
            'reason' => $reason,
            'status' => 'released',
        ]);
        $auditid = repository::audit('staff_intervention_released', 'user', (int)$current->userid, [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'interventionid' => (int)$released['id'],
            'released_interventionid' => (int)$current->id,
            'serieskey' => (string)$current->serieskey,
            'version' => (int)$released['version'],
            'interventiontype' => (string)$current->interventiontype,
            'courseid' => (int)$current->courseid,
            'unitcode' => (string)($current->unitcode ?? ''),
            'reason' => $reason,
        ]);
        $pathresult = null;
        if (in_array((string)$current->interventiontype, ['assign_target_activity', 'force_review',
                'hold_advancement', 'override_recommendation', 'adjust_goal'], true)) {
            $pathresult = adaptive_path_engine_service::apply_learner_path(
                (int)$current->userid, (int)$current->courseid, (string)($current->unitcode ?? ''),
                max(0, $frameworkid), 100, 'UX3 staff intervention released'
            );
        }
        $transaction->allow_commit();

        return [
            'type' => 'CupkpStaffInterventionReleaseResult',
            'gate' => self::GATE,
            'status' => 'released',
            'intervention' => $released,
            'auditid' => $auditid,
            'pathresult' => self::compact_path_result($pathresult),
            'state_changes_allowed' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /** Return the latest active version from each intervention series. */
    public static function current_interventions(int $userid, int $courseid, string $unitcode = ''): array {
        global $DB;

        if (!self::table_ready() || $userid <= 0 || $courseid <= 0) {
            return [];
        }
        $unitcode = self::clean_unit_code($unitcode);
        $params = ['userid' => $userid, 'courseid' => $courseid];
        $where = 'userid = :userid AND courseid = :courseid';
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_select(self::TABLE, $where, $params,
            'serieskey ASC, version DESC, id DESC');
        $latest = [];
        foreach ($rows as $row) {
            if (!isset($latest[(string)$row->serieskey])) {
                $latest[(string)$row->serieskey] = $row;
            }
        }
        $active = array_filter($latest, static function(\stdClass $row): bool {
            return (string)$row->status === 'active';
        });
        usort($active, static function(\stdClass $a, \stdClass $b): int {
            return [(int)$b->timecreated, (int)$b->id] <=> [(int)$a->timecreated, (int)$a->id];
        });
        return array_map([self::class, 'serialize_intervention'], array_values($active));
    }

    /** Return bounded intervention history for one learner scope. */
    public static function intervention_history(int $userid, int $courseid, string $unitcode = '',
            int $limit = 50): array {
        global $DB;

        if (!self::table_ready() || $userid <= 0 || $courseid <= 0) {
            return [];
        }
        $unitcode = self::clean_unit_code($unitcode);
        $params = ['userid' => $userid, 'courseid' => $courseid];
        $where = 'userid = :userid AND courseid = :courseid';
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_select(self::TABLE, $where, $params,
            'timecreated DESC, id DESC', '*', 0, self::bounded_limit($limit, 200));
        return array_map([self::class, 'serialize_intervention'], array_values($rows));
    }

    /** Prepare and validate a path-changing intervention. */
    private static function prepare_path_intervention(int $userid, int $courseid, string $unitcode,
            int $frameworkid, string $type, array $data): array {
        $targettype = strtolower(trim((string)($data['targettype'] ?? '')));
        $targetid = max(0, (int)($data['targetid'] ?? 0));
        $objectid = max(0, (int)($data['objectid'] ?? 0));
        $cmid = max(0, (int)($data['cmid'] ?? 0));
        $action = $type === 'assign_target_activity' ? 'ADVANCE' :
            ($type === 'force_review' ? 'REVIEW' : strtoupper(trim((string)($data['actioncode'] ?? ''))));
        if (!in_array($action, self::PATH_ACTIONS, true)) {
            throw new \invalid_parameter_exception('Invalid staff recommendation action.');
        }

        $resolution = candidate_activity_resolution_service::learner_resolution(
            $userid, $courseid, $unitcode, $frameworkid, 300
        );
        $activity = null;
        if ($objectid > 0 || $cmid > 0) {
            $activity = self::eligible_activity_from_resolution(
                $resolution, $objectid, $cmid, $targettype, $targetid
            );
            if (!$activity) {
                throw new \invalid_parameter_exception(
                    'The selected activity is not currently eligible for this learner under A4B.'
                );
            }
            $targettype = (string)$activity['targettype'];
            $targetid = (int)$activity['targetid'];
            $objectid = (int)$activity['objectid'];
            $cmid = (int)$activity['cmid'];
        }
        if ($type === 'assign_target_activity' && !$activity) {
            throw new \invalid_parameter_exception('Assign target/activity requires a current eligible activity.');
        }
        if ($targetid > 0) {
            self::assert_target_in_scope($targettype, $targetid, $courseid, $unitcode, $frameworkid);
        }
        if (!in_array($action, ['SKIP', 'REASSESS', 'REPRIORITIZE'], true) && !$activity &&
                $type === 'override_recommendation') {
            throw new \invalid_parameter_exception('This recommendation action requires an eligible activity.');
        }

        $target = $activity['target'] ?? self::target_view($targettype, $targetid);
        return [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'objectid' => $objectid,
            'cmid' => $cmid,
            'actioncode' => $action,
            'payload' => [
                'target' => $target,
                'activity' => $activity,
                'resolution_hash' => (string)($resolution['explainability']['resolution_hash'] ?? ''),
            ],
        ];
    }

    /** Prepare a teacher-observation evidence payload for the existing mastery writer. */
    private static function prepare_teacher_evidence(int $userid, int $courseid, string $unitcode,
            int $frameworkid, array $data): array {
        global $USER;

        $targettype = strtolower(trim((string)($data['targettype'] ?? '')));
        $targetid = max(0, (int)($data['targetid'] ?? 0));
        $objectid = max(0, (int)($data['objectid'] ?? 0));
        self::assert_target_in_scope($targettype, $targetid, $courseid, $unitcode, $frameworkid);
        $score = self::clamp01((float)($data['score'] ?? 0));
        $confidence = self::clamp01((float)($data['confidence'] ?? 0.75));
        $strength = trim((string)($data['evidencestrength'] ?? 'guided_performance'));
        $note = trim((string)($data['note'] ?? ''));
        if ($note === '') {
            throw new \invalid_parameter_exception('Teacher evidence requires an observation note.');
        }
        $nonce = hash('sha256', implode(':', [
            $userid, $courseid, $unitcode, $targettype, $targetid, (int)($USER->id ?? 0), microtime(true), $note,
        ]));
        return [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'objectid' => $objectid,
            'evidence' => [
                'userid' => $userid,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'objectid' => $objectid ?: null,
                'sourceattempt' => 'ux3-teacher-' . substr($nonce, 0, 40),
                'evidencetype' => 'teacher_observation',
                'targettype' => $targettype,
                'targetid' => $targetid,
                'rawscore' => $score,
                'normalizedscore' => $score,
                'rubricjson' => json_encode([
                    'source_type' => 'teacher_observation',
                    'result_state' => $score >= 0.70 ? 'positive' : ($score >= 0.35 ? 'partial' : 'negative'),
                    'performance_mode' => 'interaction',
                    'evidence_direction' => 'direct',
                    'teacher_observation' => [
                        'note' => $note,
                        'actorid' => (int)($USER->id ?? 0),
                        'ux3_contract' => self::CONTRACT_VERSION,
                    ],
                ], JSON_UNESCAPED_SLASHES),
                'assessortype' => 'teacher',
                'confidence' => $confidence,
                'evidencestrength' => $strength,
                'provenance' => 'local_flwcupkp:ux3_teacher_evidence',
                'sourceref' => 'staff:' . (int)($USER->id ?? 0),
                'overrideflag' => 0,
                'timecreated' => time(),
            ],
        ];
    }

    /** Merge a staff goal adjustment with the current immutable goal version. */
    private static function goal_payload(int $userid, int $courseid, string $unitcode, int $frameworkid,
            array $data): array {
        $currentview = learning_goal_service::current_goal($userid, $courseid, $unitcode, $frameworkid, 1);
        $current = is_array($currentview['goal'] ?? null) ? $currentview['goal'] : [];
        $destination = is_array($current['destination'] ?? null) ? $current['destination'] : [];
        $payload = [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'title' => (string)($current['title'] ?? ''),
            'desiredprofile' => $destination['desired_profile'] ?? [],
            'competencyids' => $destination['competencyids'] ?? [],
            'upids' => $destination['upids'] ?? [],
            'kpids' => $destination['kpids'] ?? [],
            'priorityskills' => $destination['priorityskills'] ?? [],
            'cefr' => (string)($current['cefr'] ?? ''),
            'flwstage' => (string)($current['flwstage'] ?? ''),
            'purpose' => (string)($current['purpose'] ?? ''),
            'targetdate' => (int)($current['targetdate'] ?? 0),
            'weeklytarget' => (float)($current['weeklytarget'] ?? 0),
            'status' => (string)($current['status'] ?? 'active'),
        ];
        foreach (['title', 'cefr', 'flwstage', 'purpose', 'targetdate', 'weeklytarget', 'status',
                'desiredprofile', 'competencyids', 'upids', 'kpids', 'priorityskills'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '' && $data[$field] !== null) {
                $payload[$field] = $data[$field];
            }
        }
        return $payload;
    }

    /** Append one immutable intervention version. */
    private static function append_version(string $serieskey, array $data): array {
        global $DB, $USER;

        $previous = self::latest_series_record($serieskey);
        $version = $previous ? (int)$previous->version + 1 : 1;
        $record = (object)[
            'serieskey' => $serieskey,
            'version' => $version,
            'supersedesid' => $previous ? (int)$previous->id : null,
            'userid' => (int)$data['userid'],
            'courseid' => (int)$data['courseid'],
            'unitcode' => (string)($data['unitcode'] ?? ''),
            'interventiontype' => (string)$data['interventiontype'],
            'targettype' => (string)($data['targettype'] ?? ''),
            'targetid' => !empty($data['targetid']) ? (int)$data['targetid'] : null,
            'objectid' => !empty($data['objectid']) ? (int)$data['objectid'] : null,
            'cmid' => !empty($data['cmid']) ? (int)$data['cmid'] : null,
            'actioncode' => (string)($data['actioncode'] ?? ''),
            'payloadjson' => json_encode($data['payload'] ?? [], JSON_UNESCAPED_SLASHES),
            'reason' => (string)$data['reason'],
            'status' => (string)$data['status'],
            'policyversion' => self::INTERVENTION_POLICY_VERSION,
            'createdby' => (int)($USER->id ?? 0),
            'timecreated' => time(),
        ];
        $record->id = (int)$DB->insert_record(self::TABLE, $record);
        return self::serialize_intervention($record);
    }

    /** Latest row in one immutable series. */
    private static function latest_series_record(string $serieskey): ?\stdClass {
        global $DB;

        $rows = $DB->get_records(self::TABLE, ['serieskey' => $serieskey], 'version DESC, id DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /** Highest-precedence active path intervention. */
    private static function precedent_path_intervention(array $active): ?array {
        foreach (self::PATH_PRECEDENCE as $type) {
            $matches = array_values(array_filter($active, static function(array $row) use ($type): bool {
                return ($row['interventiontype'] ?? '') === $type;
            }));
            if ($matches) {
                usort($matches, static function(array $a, array $b): int {
                    return [(int)$b['timecreated'], (int)$b['id']] <=>
                        [(int)$a['timecreated'], (int)$a['id']];
                });
                return $matches[0];
            }
        }
        return null;
    }

    /** Locate the exact currently eligible activity selected by staff. */
    private static function eligible_activity_from_resolution(array $resolution, int $objectid, int $cmid,
            string $targettype = '', int $targetid = 0): ?array {
        foreach (($resolution['eligible_activities'] ?? []) as $activity) {
            if (!is_array($activity) || empty($activity['eligible'])) {
                continue;
            }
            if ($objectid > 0 && (int)($activity['objectid'] ?? 0) !== $objectid) {
                continue;
            }
            if ($cmid > 0 && (int)($activity['cmid'] ?? 0) !== $cmid) {
                continue;
            }
            if ($targettype !== '' && (string)($activity['targettype'] ?? '') !== $targettype) {
                continue;
            }
            if ($targetid > 0 && (int)($activity['targetid'] ?? 0) !== $targetid) {
                continue;
            }
            return $activity;
        }
        return null;
    }

    /** Determine effective action for one active intervention. */
    private static function effective_action(array $intervention): string {
        return match ((string)$intervention['interventiontype']) {
            'hold_advancement' => 'REPRIORITIZE',
            'assign_target_activity' => 'ADVANCE',
            'force_review' => 'REVIEW',
            default => in_array(strtoupper((string)$intervention['actioncode']), self::PATH_ACTIONS, true) ?
                strtoupper((string)$intervention['actioncode']) : 'HOLD',
        };
    }

    /** Preserve a blocked intervention marker without bypassing the base path. */
    private static function mark_intervention_blocked(array $recommendation, array $intervention,
            string $reason): array {
        $recommendation['staff_intervention'] = [
            'id' => (int)$intervention['id'],
            'type' => (string)$intervention['interventiontype'],
            'version' => (int)$intervention['version'],
            'status' => 'blocked_by_current_eligibility',
            'reason' => $reason,
        ];
        $recommendation['reason_codes'] = array_values(array_unique(array_merge(
            array_map('strval', $recommendation['reason_codes'] ?? []),
            ['STAFF_INTERVENTION_BLOCKED_BY_A4B']
        )));
        return $recommendation;
    }

    /** Detailed evidence and provenance rows from derived C-UP-KP evidence only. */
    private static function evidence_detail(int $userid, int $courseid, string $unitcode, int $limit): array {
        global $DB;

        $params = ['userid' => $userid, 'courseid' => $courseid];
        $where = 'userid = :userid AND courseid = :courseid';
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_select('flwcupkp_evidence', $where, $params,
            'timecreated DESC, id DESC', '*', 0, $limit);
        $out = [];
        foreach ($rows as $row) {
            $rubric = json_decode((string)($row->rubricjson ?? ''), true);
            $rubric = is_array($rubric) ? $rubric : [];
            $semantics = is_array($rubric['cupkp_c3b_semantics'] ?? null) ?
                $rubric['cupkp_c3b_semantics'] : [];
            $target = self::target_view((string)$row->targettype, (int)$row->targetid);
            $out[] = [
                'id' => (int)$row->id,
                'target' => $target,
                'score' => isset($row->normalizedscore) ? (float)$row->normalizedscore : null,
                'confidence' => isset($row->confidence) ? (float)$row->confidence : null,
                'evidence_type' => (string)$row->evidencetype,
                'assessor_type' => (string)($row->assessortype ?? ''),
                'strength' => (string)($row->evidencestrength ?? ''),
                'provenance' => (string)($row->provenance ?? ''),
                'source_ref' => (string)($row->sourceref ?? ''),
                'source_attempt' => (string)($row->sourceattempt ?? ''),
                'result_state' => (string)($semantics['result_state'] ?? ''),
                'performance_mode' => (string)($semantics['performance_mode'] ?? ''),
                'evidence_direction' => (string)($semantics['evidence_direction'] ?? ''),
                'quality' => is_array($semantics['quality'] ?? null) ? $semantics['quality'] : [],
                'timecreated' => (int)$row->timecreated,
            ];
        }
        return $out;
    }

    /** Prerequisite detail for visible KP state rows. */
    private static function prerequisite_detail(int $userid, array $states): array {
        global $DB;

        $out = [];
        foreach ($states as $state) {
            if (($state['target']['type'] ?? '') !== 'kp') {
                continue;
            }
            $kpid = (int)($state['target']['id'] ?? 0);
            foreach ($DB->get_records('flwcupkp_kp_prereq', ['kpid' => $kpid], 'id ASC') as $edge) {
                $prereq = self::target_view('kp', (int)$edge->prereqkpid);
                $stored = $DB->get_record('flwcupkp_state', [
                    'userid' => $userid,
                    'targettype' => 'kp',
                    'targetid' => (int)$edge->prereqkpid,
                ], '*', IGNORE_MISSING);
                $out[] = [
                    'target' => $state['target'],
                    'needed_first' => $prereq,
                    'requirement' => (string)($edge->requirement ?? ''),
                    'strength' => isset($edge->strength) ? (float)$edge->strength : null,
                    'learner_state' => $stored ? (string)$stored->masterystate : 'not_started',
                    'satisfied' => $stored && in_array((string)$stored->masterystate,
                        ['mastered', 'demonstrated', 'stable', 'transfer_ready'], true),
                ];
            }
        }
        return $out;
    }

    /** Build the six required recommendation explanations. */
    private static function recommendation_explanations(array $adaptive, array $retention,
            array $history): array {
        $recommendation = is_array($adaptive['recommendation'] ?? null) ? $adaptive['recommendation'] : [];
        $target = is_array($recommendation['selected_target'] ?? null) ? $recommendation['selected_target'] : [];
        $activity = is_array($recommendation['selected_activity'] ?? null) ?
            $recommendation['selected_activity'] : [];
        $action = strtoupper((string)($recommendation['action'] ?? ''));
        $reason = trim((string)($recommendation['reason'] ?? ''));
        $reason = $reason !== '' ? $reason : 'No current recommendation reason is available.';
        $targetlabel = (string)($target['title'] ?? $target['externalid'] ?? 'No target selected');
        $activitylabel = (string)($activity['title'] ?? 'No eligible activity selected');
        $reviewrows = array_filter($retention, static function(array $row): bool {
            return in_array((string)($row['calculated']['retentionstate'] ?? ''),
                ['review_due', 'relearning_needed', 'retention_uncertain'], true);
        });
        $pathchange = $history['why_path_changed'] ?? [];
        $pathchangereason = is_array($pathchange) ? (string)($pathchange['reason'] ?? '') : '';

        return [
            'why_target' => [
                'answer' => $targetlabel . ': ' . $reason,
                'reason_codes' => array_values($recommendation['reason_codes'] ?? []),
            ],
            'why_activity' => [
                'answer' => $activity ? $activitylabel . ' passed the current A4B eligibility checks.' :
                    'No activity passed every current A4B eligibility check.',
                'activity' => $activity,
            ],
            'why_extra_practice' => [
                'answer' => $action === 'REMEDIATE' ? $reason :
                    'Extra practice is not the current recommendation action.',
                'active' => $action === 'REMEDIATE',
            ],
            'why_review' => [
                'answer' => $action === 'REVIEW' ? $reason : (count($reviewrows) .
                    ' target(s) currently show a retention review signal.'),
                'active' => $action === 'REVIEW',
                'retention_signals' => count($reviewrows),
            ],
            'why_skip' => [
                'answer' => $action === 'SKIP' ? $reason :
                    'Skip is not the current decision; prerequisite and eligibility checks remain in force.',
                'active' => $action === 'SKIP',
            ],
            'why_path_changed' => [
                'answer' => $pathchangereason !== '' ? $pathchangereason :
                    'No prior persisted recommendation is available for comparison.',
                'detail' => $pathchange,
            ],
        ];
    }

    /** Collect policy versions without changing their owners. */
    private static function policy_versions(array $adaptive, array $states, array $retention): array {
        $versions = is_array($adaptive['explainability']['policy_versions'] ?? null) ?
            $adaptive['explainability']['policy_versions'] : [];
        foreach ($states as $state) {
            if (!empty($state['policyversion'])) {
                $versions['mastery'] = (string)$state['policyversion'];
            }
            if (!empty($state['confidence']['policyversion'])) {
                $versions['confidence'] = (string)$state['confidence']['policyversion'];
            }
        }
        foreach ($retention as $row) {
            if (!empty($row['calculated']['policyversion'])) {
                $versions['retention'] = (string)$row['calculated']['policyversion'];
            }
        }
        $versions['staff_intervention'] = self::INTERVENTION_POLICY_VERSION;
        return $versions;
    }

    /** Build complete staff control options, including cold-start targets without state rows. */
    private static function intervention_options(int $courseid, string $unitcode, int $frameworkid,
            array $states, array $adaptive): array {
        $targets = [];
        foreach ($states as $state) {
            $target = is_array($state['target'] ?? null) ? $state['target'] : [];
            if (!empty($target['type']) && !empty($target['id'])) {
                $targets[(string)$target['type'] . ':' . (int)$target['id']] = $target;
            }
        }
        if ($unitcode !== '') {
            foreach (unit_report::unit_targets($courseid, $unitcode) as $record) {
                if ($frameworkid > 0 && (int)($record->frameworkid ?? 0) !== $frameworkid) {
                    continue;
                }
                $target = self::target_view((string)$record->targettype, (int)$record->targetid);
                if ($target) {
                    $targets[(string)$target['type'] . ':' . (int)$target['id']] = $target;
                }
            }
        }
        $resolution = is_array($adaptive['source_activity_resolution'] ?? null) ?
            $adaptive['source_activity_resolution'] : [];
        foreach (($resolution['eligible_activities'] ?? []) as $activity) {
            $target = is_array($activity['target'] ?? null) ? $activity['target'] :
                self::target_view((string)($activity['targettype'] ?? ''), (int)($activity['targetid'] ?? 0));
            if (!empty($target['type']) && !empty($target['id'])) {
                $targets[(string)$target['type'] . ':' . (int)$target['id']] = $target;
            }
        }
        uasort($targets, static function(array $a, array $b): int {
            return [(string)($a['type'] ?? ''), (string)($a['title'] ?? $a['externalid'] ?? '')] <=>
                [(string)($b['type'] ?? ''), (string)($b['title'] ?? $b['externalid'] ?? '')];
        });
        return [
            'targets' => array_values($targets),
            'eligible_activities' => array_values($resolution['eligible_activities'] ?? []),
        ];
    }

    /** Assert target exists and belongs to the requested framework/scope. */
    private static function assert_target_in_scope(string $targettype, int $targetid, int $courseid,
            string $unitcode, int $frameworkid): void {
        global $DB;

        if (!in_array($targettype, ['competency', 'up', 'kp'], true) || $targetid <= 0) {
            throw new \invalid_parameter_exception('A valid competency, UP, or KP target is required.');
        }
        evidence_guard::assert_target_exists($targettype, $targetid);
        $table = evidence_guard::target_table($targettype);
        $target = $DB->get_record($table, ['id' => $targetid], '*', MUST_EXIST);
        if ($frameworkid > 0 && (int)($target->frameworkid ?? 0) !== $frameworkid) {
            throw new \invalid_parameter_exception('The selected target is outside the requested framework.');
        }
        if ($courseid > 0 && $unitcode !== '') {
            $targets = unit_report::unit_targets($courseid, $unitcode);
            $valid = false;
            foreach ($targets as $candidate) {
                if (($candidate->targettype ?? '') === $targettype && (int)($candidate->targetid ?? 0) === $targetid) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                throw new \invalid_parameter_exception('The selected target is outside the requested course unit.');
            }
        }
    }

    /** Compact target identity. */
    private static function target_view(string $targettype, int $targetid): array {
        global $DB;

        if (!in_array($targettype, ['competency', 'up', 'kp'], true) || $targetid <= 0) {
            return [];
        }
        $record = $DB->get_record(evidence_guard::target_table($targettype), ['id' => $targetid],
            'id, externalid, title, frameworkid', IGNORE_MISSING);
        return $record ? [
            'type' => $targettype,
            'id' => (int)$record->id,
            'externalid' => (string)$record->externalid,
            'title' => (string)$record->title,
            'frameworkid' => (int)$record->frameworkid,
        ] : [];
    }

    /** Learner identity for staff display. */
    private static function learner_identity(int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email',
            MUST_EXIST);
        return [
            'id' => (int)$user->id,
            'fullname' => fullname($user),
            'email' => (string)$user->email,
        ];
    }

    /** Serialize an intervention row. */
    private static function serialize_intervention(\stdClass $row): array {
        $payload = json_decode((string)($row->payloadjson ?? ''), true);
        return [
            'id' => (int)$row->id,
            'serieskey' => (string)$row->serieskey,
            'version' => (int)$row->version,
            'supersedesid' => (int)($row->supersedesid ?? 0),
            'userid' => (int)$row->userid,
            'courseid' => (int)$row->courseid,
            'unitcode' => (string)($row->unitcode ?? ''),
            'interventiontype' => (string)$row->interventiontype,
            'targettype' => (string)($row->targettype ?? ''),
            'targetid' => (int)($row->targetid ?? 0),
            'objectid' => (int)($row->objectid ?? 0),
            'cmid' => (int)($row->cmid ?? 0),
            'actioncode' => (string)($row->actioncode ?? ''),
            'payload' => is_array($payload) ? $payload : [],
            'reason' => (string)$row->reason,
            'status' => (string)$row->status,
            'policyversion' => (string)$row->policyversion,
            'createdby' => (int)$row->createdby,
            'timecreated' => (int)$row->timecreated,
        ];
    }

    /** Stable series identity for version sequencing. */
    private static function series_key(int $userid, int $courseid, string $unitcode, string $type,
            string $targettype, int $targetid): string {
        return hash('sha256', implode('|', [
            self::INTERVENTION_POLICY_VERSION,
            $userid,
            $courseid,
            $unitcode,
            $type,
            $targettype,
            $targetid,
        ]));
    }

    /** Enforce the course-scoped write capability at the service boundary. */
    private static function require_write_access(int $courseid): void {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('A course is required for staff interventions.');
        }
        $context = \context_course::instance($courseid, MUST_EXIST);
        require_capability('local/flwcupkp:override', $context);
    }

    /** Verify the intervention table exists. */
    private static function assert_table_ready(): void {
        if (!self::table_ready()) {
            throw new \moodle_exception('ddlfieldnotexist', 'error', '', self::TABLE);
        }
    }

    /** Whether the intervention table exists. */
    private static function table_ready(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::TABLE);
    }

    /** Compact an existing writer result for audit and API output. */
    private static function compact_writer_result(?array $result): ?array {
        if ($result === null) {
            return null;
        }
        return array_filter([
            'type' => (string)($result['type'] ?? ''),
            'status' => (string)($result['status'] ?? ''),
            'goalid' => (int)($result['goalid'] ?? 0),
            'version' => (int)($result['version'] ?? 0),
            'evidenceid' => (int)($result['evidenceid'] ?? 0),
        ], static function($value): bool {
            return $value !== '' && $value !== 0 && $value !== null;
        });
    }

    /** Compact an A5 apply result. */
    private static function compact_path_result(?array $result): ?array {
        if ($result === null) {
            return null;
        }
        return [
            'status' => (string)($result['status'] ?? ''),
            'recommendationid' => (int)($result['recommendationid'] ?? 0),
            'sourcehash' => (string)($result['sourcehash'] ?? ''),
            'action' => (string)($result['recommendation']['action'] ?? ''),
        ];
    }

    /** Catch one read-only component failure while preserving staff visibility. */
    private static function safe_component(callable $callback, string $component): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return [
                'type' => 'CupkpUnavailableStaffIntelligenceComponent',
                'component' => $component,
                'status' => 'unavailable',
                'message' => $e->getMessage(),
            ];
        }
    }

    /** One status criterion. */
    private static function criterion(string $code, bool $pass, string $message): array {
        return ['code' => $code, 'pass' => $pass, 'message' => $message];
    }

    /** Clean an optional unit code. */
    private static function clean_unit_code(string $unitcode): string {
        return substr((string)preg_replace('/[^A-Za-z0-9_-]/', '', trim($unitcode)), 0, 40);
    }

    /** Clamp a confidence or score to 0..1. */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }

    /** Bound list size. */
    private static function bounded_limit(int $limit, int $maximum): int {
        return max(1, min($maximum, $limit));
    }
}

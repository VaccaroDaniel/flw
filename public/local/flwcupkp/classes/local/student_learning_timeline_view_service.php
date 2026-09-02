<?php
// Program 3 Gate UX1 Past, Present, and Future dashboard composition.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only presentation service joining History V1 with frozen Program 3 intelligence.
 */
final class student_learning_timeline_view_service {
    /** Program 3 dashboard integration gate. */
    public const GATE = 'P3_UX1';

    /** Frozen UX1 presentation contract. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_STUDENT_LEARNING_TIMELINE_VIEW_V1';

    /** Versioned presentation composition policy. */
    public const VIEW_POLICY_VERSION = 'cupkp-past-present-future-view-v1';

    /** Approved Program 2 dashboard DTO consumed by UX1. */
    public const HISTORY_DASHBOARD_CONTRACT = 'LearnerHistoryDashboardCore';

    /** Next allowed gate. */
    public const NEXT_ALLOWED_GATE = 'UX2';

    /** Program 2 panels that remain wholly owned by History. */
    public const HISTORY_OWNED_PANELS = [
        'grade_history',
        'learning_history',
        'recent_activity',
        'attempt_history',
        'journey',
    ];

    /** Maximum persisted recommendation rows exposed in one learner view. */
    private const MAX_RECOMMENDATION_HISTORY = 50;

    /**
     * Return the frozen UX1 view contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpStudentLearningTimelineViewContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'view_policy_version' => self::VIEW_POLICY_VERSION,
            'depends_on' => [
                self::HISTORY_DASHBOARD_CONTRACT,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
                progress_goal_readiness_service::CONTRACT_VERSION,
                adaptive_path_engine_service::CONTRACT_VERSION,
                goal_gap_path_service::CONTRACT_VERSION,
                mastery_state_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
            ],
            'composition' => [
                'past' => [
                    'owner' => 'local_flwhistory',
                    'source' => self::HISTORY_DASHBOARD_CONTRACT,
                    'panels' => self::HISTORY_OWNED_PANELS,
                    'rendering' => 'delegated_to_local_flwhistory_dashboard_renderer',
                ],
                'present' => [
                    'owner' => 'local_flwcupkp',
                    'sources' => [progress_goal_readiness_service::CONTRACT_VERSION],
                    'panels' => ['cupkp_mastery', 'skill_mastery_state', 'goal_readiness'],
                ],
                'future' => [
                    'owner' => 'local_flwcupkp',
                    'sources' => [adaptive_path_engine_service::CONTRACT_VERSION,
                        goal_gap_path_service::CONTRACT_VERSION],
                    'panels' => ['adaptive_next', 'projected_future_roadmap',
                        'recommendation_history', 'why_path_changed'],
                ],
            ],
            'presentation_rule' => 'Templates receive History dashboard DTOs or compact presentation DTOs, never raw graph objects.',
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'history_mutation' => false,
            'read_only_surface' => ['contract', 'status', 'compose', 'learner_timeline', 'recommendation_history'],
            'read_only' => true,
            'write_boundary' => [],
            'does_not_do' => [
                'history_rebuild',
                'raw_moodle_log_scraping',
                'raw_relationship_graph_template_payload',
                'mastery_or_retention_recalculation',
                'adaptive_recommendation_write',
                'goal_or_completion_write',
                'ux2_learner_experience_simplification',
                'ux3_teacher_override',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return UX1 implementation readiness.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0): array {
        $unitcode = self::clean_unit_code_optional($unitcode);
        $a5c = self::safe_status(static function() use ($courseid, $unitcode, $frameworkid): array {
            return progress_goal_readiness_service::status($courseid, $unitcode, $frameworkid);
        });
        $history = self::safe_status(static function() use ($courseid): array {
            return history_v1_consumer_contract::contract_status($courseid, 1);
        });
        $files = self::file_status();
        $surface = self::surface_status();
        $contract = self::contract();
        $criteria = [
            'a5c_frozen' => self::criterion('a5c_frozen',
                ($a5c['status'] ?? '') === 'ready' && ($a5c['next_allowed_gate'] ?? '') === 'UX1',
                'The frozen A5C contract must be ready and hand off to UX1.'),
            'history_v1_preserved' => self::criterion('history_v1_preserved',
                in_array(($history['status'] ?? ''), ['ready', 'ready_with_findings'], true) &&
                    ($history['requiredcontract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT,
                'History V1 must remain the trusted Program 2 source boundary.'),
            'history_dashboard_delegated' => self::criterion('history_dashboard_delegated',
                $surface['history_dashboard_service'] && $surface['history_dashboard_renderer'],
                'Past panels must use the approved Program 2 dashboard service and renderer.'),
            'panel_ownership_frozen' => self::criterion('panel_ownership_frozen',
                ($contract['composition']['past']['panels'] ?? []) === self::HISTORY_OWNED_PANELS,
                'History-owned panels must remain explicitly separated from Program 3 enrichments.'),
            'past_present_future_composed' => self::criterion('past_present_future_composed',
                array_keys($contract['composition']) === ['past', 'present', 'future'],
                'The view contract must compose Past, Present, and Future in that order.'),
            'presentation_boundary_preserved' => self::criterion('presentation_boundary_preserved',
                strpos((string)$contract['presentation_rule'], 'never raw graph objects') !== false,
                'Templates must receive presentation DTOs instead of raw relationship graphs.'),
            'read_only_boundary_preserved' => self::criterion('read_only_boundary_preserved',
                !empty($contract['read_only']) && empty($contract['write_boundary']) &&
                    empty($contract['history_mutation']),
                'UX1 must compose existing state without writes or History mutation.'),
            'files_and_surface_present' => self::criterion('files_and_surface_present',
                empty($files['missing']) && $surface['valid'],
                'UX1 service, renderer, page, CLI, and read-only APIs must be present.'),
            'next_gate_frozen' => self::criterion('next_gate_frozen',
                ($contract['next_allowed_gate'] ?? '') === self::NEXT_ALLOWED_GATE,
                'UX1 must stop before UX2 learner-experience simplification.'),
        ];
        $summary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpStudentLearningTimelineViewStatus',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => $contract,
            'scope' => ['courseid' => $courseid, 'unitcode' => $unitcode, 'frameworkid' => $frameworkid],
            'criteria' => $criteria,
            'criteria_summary' => $summary,
            'dependencies' => [
                'a5c' => [
                    'gate' => $a5c['gate'] ?? progress_goal_readiness_service::GATE,
                    'status' => $a5c['status'] ?? 'blocked',
                    'contract' => $a5c['contract']['version'] ?? null,
                    'next_allowed_gate' => $a5c['next_allowed_gate'] ?? null,
                ],
                'history_v1' => [
                    'status' => $history['status'] ?? 'blocked',
                    'contract' => $history['requiredcontract'] ?? null,
                    'dashboard_contract' => self::HISTORY_DASHBOARD_CONTRACT,
                ],
            ],
            'files' => $files,
            'surface' => $surface,
            'findings' => self::status_findings($criteria, $a5c, $history),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build one learner timeline from frozen presentation inputs.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @param array $pagination
     * @return array
     */
    public static function learner_timeline(int $userid, int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20, array $pagination = []): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID is required for the History V1 dashboard.');
        }
        get_course($courseid);
        evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_int($limit, 1, 50);
        $options = self::history_options($pagination, $limit);
        $historyclass = '\\local_flwhistory\\local\\dashboard_service';
        if (!class_exists($historyclass) || !method_exists($historyclass, 'learner_dashboard_core')) {
            throw new \moodle_exception('timelinehistoryunavailable', 'local_flwcupkp');
        }
        $history = $historyclass::learner_dashboard_core($courseid, $userid, $options);
        $progress = self::safe_component(static function() use ($userid, $courseid, $unitcode,
                $frameworkid): array {
            return progress_goal_readiness_service::learner_progress(
                $userid, $courseid, $unitcode, $frameworkid, 200
            );
        }, 'progress_goal_readiness');
        $adaptive = self::safe_component(static function() use ($userid, $courseid, $unitcode,
                $frameworkid): array {
            return adaptive_path_engine_service::learner_path(
                $userid, $courseid, $unitcode, $frameworkid, 200
            );
        }, 'continuous_adaptive_path');
        $recommendations = self::recommendation_history($userid, $courseid, $unitcode, $limit,
            (string)($adaptive['recommendation']['sourcehash'] ?? ''));
        $view = self::compose($history, $progress, $adaptive, $recommendations);
        $view['scope'] = [
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'limit' => $limit,
        ];
        $view['pagination'] = $options;
        return $view;
    }

    /**
     * Pure Past, Present, and Future composition from presentation DTOs.
     *
     * @param array $history Program 2 LearnerHistoryDashboardCore DTO.
     * @param array $progress A5C learner progress DTO.
     * @param array $adaptive A5 learner path DTO.
     * @param array $recommendations Compact recommendation-history DTO.
     * @return array
     */
    public static function compose(array $history, array $progress, array $adaptive,
            array $recommendations = []): array {
        $historycopy = $history;
        $historycopy['program3_placeholders'] = [];
        $progressview = is_array($progress['progress'] ?? null) ? $progress['progress'] : [];
        $metrics = is_array($progressview['metrics'] ?? null) ? $progressview['metrics'] : [];
        $requirements = is_array($progressview['requirements']['details'] ?? null) ?
            $progressview['requirements']['details'] : [];
        $goal = is_array($progress['goal']['goal'] ?? null) ? $progress['goal']['goal'] : null;
        $recommendation = is_array($adaptive['recommendation'] ?? null) ? $adaptive['recommendation'] : [];
        $resolution = is_array($adaptive['source_activity_resolution'] ?? null) ?
            $adaptive['source_activity_resolution'] : [];
        $roadmap = is_array($resolution['projected_roadmap'] ?? null) ?
            $resolution['projected_roadmap'] : [];

        return [
            'type' => 'StudentLearningTimelineView',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'view_policy_version' => self::VIEW_POLICY_VERSION,
            'learner' => self::compact_learner($history['learner'] ?? []),
            'course' => self::compact_course($history['present']['course'] ?? [], $history['courseid'] ?? 0),
            'stages' => [
                ['key' => 'past', 'label' => 'Past', 'meaning' => 'What has happened'],
                ['key' => 'present', 'label' => 'Present', 'meaning' => 'Where the learner is now'],
                ['key' => 'future', 'label' => 'Future', 'meaning' => 'What should happen next'],
            ],
            'past' => [
                'owner' => 'local_flwhistory',
                'dashboard_contract' => (string)($history['type'] ?? ''),
                'panels' => self::HISTORY_OWNED_PANELS,
                'dashboard' => $historycopy,
                'generatedat' => (int)($history['generatedat'] ?? 0),
                'normalization_policy_version' => (string)($history['normpolicyversion'] ?? ''),
            ],
            'present' => [
                'owner' => 'local_flwcupkp',
                'status' => $progressview ? 'available' : 'unavailable',
                'current_location' => self::compact_current_location($history['present']['current'] ?? []),
                'metrics' => $metrics,
                'preferred_metric' => $progressview['preferred_learner_metric'] ??
                    self::unavailable_value('PROGRESS_GOAL_READINESS_UNAVAILABLE'),
                'goal_achievement' => $progressview['goal_achievement'] ??
                    self::unavailable_value('GOAL_ACHIEVEMENT_UNAVAILABLE'),
                'goal' => self::compact_goal($goal),
                'skill_states' => self::compact_skill_states($requirements),
                'source_hash' => (string)($progressview['source_hash'] ?? ''),
            ],
            'future' => [
                'owner' => 'local_flwcupkp',
                'status' => $recommendation ? 'available' : 'insufficient_data',
                'adaptive_next' => self::compact_adaptive_next($recommendation,
                    (string)($adaptive['recommendation_status'] ?? '')),
                'projected_roadmap' => self::compact_roadmap($roadmap),
                'recommendation_history' => array_values($recommendations['records'] ?? []),
                'why_path_changed' => $recommendations['why_path_changed'] ??
                    self::unavailable_value('NO_PERSISTED_RECOMMENDATION_HISTORY'),
                'adaptive_path_hash' => (string)($adaptive['explainability']['adaptive_path_hash'] ?? ''),
            ],
            'source_contracts' => [
                'history_dashboard' => (string)($history['type'] ?? self::HISTORY_DASHBOARD_CONTRACT),
                'history_evidence' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'progress_readiness' => (string)($progress['contract'] ??
                    progress_goal_readiness_service::CONTRACT_VERSION),
                'adaptive_path' => (string)($adaptive['contract'] ??
                    adaptive_path_engine_service::CONTRACT_VERSION),
            ],
            'component_status' => [
                'history' => ($history['type'] ?? '') === self::HISTORY_DASHBOARD_CONTRACT ? 'available' : 'unavailable',
                'present' => $progressview ? 'available' : (string)($progress['status'] ?? 'unavailable'),
                'future' => $recommendation ? 'available' : (string)($adaptive['status'] ?? 'insufficient_data'),
            ],
            'generatedat' => time(),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return bounded A5 recommendation history and a compact path-change explanation.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @param string $previewhash
     * @return array
     */
    public static function recommendation_history(int $userid, int $courseid = 0, string $unitcode = '',
            int $limit = 10, string $previewhash = ''): array {
        global $DB;

        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_int($limit, 1, self::MAX_RECOMMENDATION_HISTORY);
        $params = [
            'userid' => $userid,
            'policyversion' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
        ];
        $conditions = ['userid = :userid', 'policyversion = :policyversion'];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $conditions[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = array_values($DB->get_records_select('flwcupkp_recommend',
            implode(' AND ', $conditions), $params, 'timemodified DESC, id DESC', '*', 0, $limit));
        $records = [];
        $snapshots = [];
        foreach ($rows as $row) {
            $details = json_decode((string)($row->prereqinfo ?? ''), true);
            $details = is_array($details) ? $details : [];
            $snapshot = is_array($details['snapshot'] ?? null) ? $details['snapshot'] : [];
            $snapshots[] = $snapshot;
            $records[] = [
                'id' => (int)$row->id,
                'status' => (string)$row->status,
                'action' => (string)($row->recommendationtype ?? ''),
                'decision_code' => (string)($row->decisioncode ?? ''),
                'reason' => (string)($row->reason ?? ''),
                'reason_codes' => array_values(array_unique(array_map('strval',
                    $details['reason_codes'] ?? []))),
                'target' => self::compact_target([
                    'type' => (string)($row->targettype ?? ''),
                    'id' => (int)($row->targetid ?? 0),
                ]),
                'activity' => self::compact_activity([
                    'objectid' => (int)($row->objectid ?? 0),
                    'cmid' => (int)($row->cmid ?? 0),
                ]),
                'mastery_gap' => isset($row->masterygap) ? (float)$row->masterygap : null,
                'expected_benefit' => isset($row->expectedbenefit) ? (float)$row->expectedbenefit : null,
                'source_hash' => (string)($row->sourcehash ?? ''),
                'time' => (int)($row->timemodified ?? $row->timecreated ?? 0),
                'changed_from_previous' => [],
            ];
        }
        foreach ($records as $index => &$record) {
            if (!isset($records[$index + 1])) {
                continue;
            }
            $record['changed_from_previous'] = self::changed_dimensions(
                $record,
                $records[$index + 1],
                $snapshots[$index] ?? [],
                $snapshots[$index + 1] ?? []
            );
        }
        unset($record);

        $why = self::why_path_changed($records, $previewhash);
        return [
            'type' => 'CupkpRecommendationHistoryView',
            'gate' => self::GATE,
            'policy_version' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'records' => $records,
            'why_path_changed' => $why,
            'read_only' => true,
            'write_boundary' => [],
        ];
    }

    /**
     * Compact one learner identity.
     *
     * @param array $learner
     * @return array
     */
    private static function compact_learner(array $learner): array {
        return [
            'id' => (int)($learner['id'] ?? 0),
            'fullname' => (string)($learner['fullname'] ?? ''),
        ];
    }

    /**
     * Compact course identity.
     *
     * @param array $course
     * @param mixed $fallbackid
     * @return array
     */
    private static function compact_course(array $course, $fallbackid): array {
        return [
            'id' => (int)($course['id'] ?? $fallbackid),
            'fullname' => (string)($course['fullname'] ?? ''),
            'shortname' => (string)($course['shortname'] ?? ''),
        ];
    }

    /**
     * Compact History current-location fact.
     *
     * @param array $current
     * @return array
     */
    private static function compact_current_location(array $current): array {
        return [
            'status' => (string)($current['status'] ?? 'insufficient_data'),
            'unitid' => (string)($current['unitid'] ?? ''),
            'lessonid' => (string)($current['lessonid'] ?? ''),
            'activityid' => (string)($current['activityid'] ?? ''),
            'eventtype' => (string)($current['eventtype'] ?? ''),
            'eventtime' => (int)($current['eventtime'] ?? 0),
        ];
    }

    /**
     * Compact goal for presentation.
     *
     * @param array|null $goal
     * @return array
     */
    private static function compact_goal(?array $goal): array {
        if (!$goal) {
            return self::unavailable_value('GOAL_NOT_SET');
        }
        return [
            'status' => (string)($goal['status'] ?? 'active'),
            'id' => (int)($goal['id'] ?? 0),
            'title' => (string)($goal['title'] ?? ''),
            'cefr' => (string)($goal['cefr'] ?? ''),
            'flwstage' => (string)($goal['flwstage'] ?? ''),
            'purpose' => (string)($goal['purpose'] ?? ''),
            'targetdate' => (int)($goal['targetdate'] ?? 0),
            'currentversion' => (int)($goal['currentversion'] ?? 0),
        ];
    }

    /**
     * Compact current skill/mastery requirements.
     *
     * @param array $requirements
     * @return array
     */
    private static function compact_skill_states(array $requirements): array {
        $rows = [];
        foreach (array_slice($requirements, 0, 50) as $requirement) {
            if (!is_array($requirement)) {
                continue;
            }
            $rows[] = [
                'target' => self::compact_target($requirement['target'] ?? []),
                'gap_status' => (string)($requirement['gap_status'] ?? 'missing'),
                'mastery_score' => (float)($requirement['mastery_score'] ?? 0),
                'confidence' => (float)($requirement['confidence'] ?? 0),
                'evidence_count' => (int)($requirement['evidence_count'] ?? 0),
                'retention_state' => (string)($requirement['retention_state'] ?? 'missing'),
                'readiness' => (float)($requirement['readiness'] ?? 0),
            ];
        }
        return $rows;
    }

    /**
     * Compact adaptive next recommendation.
     *
     * @param array $recommendation
     * @param string $persistence
     * @return array
     */
    private static function compact_adaptive_next(array $recommendation, string $persistence): array {
        if (!$recommendation) {
            return self::unavailable_value('ADAPTIVE_NEXT_UNAVAILABLE');
        }
        return [
            'status' => (string)($recommendation['path_status'] ?? 'insufficient_data'),
            'persistence_status' => $persistence,
            'action' => (string)($recommendation['action'] ?? ''),
            'decision_code' => (string)($recommendation['decision_code'] ?? ''),
            'reason' => (string)($recommendation['reason'] ?? ''),
            'reason_codes' => array_values(array_unique(array_map('strval',
                $recommendation['reason_codes'] ?? []))),
            'target' => self::compact_target($recommendation['selected_target'] ?? []),
            'activity' => self::compact_activity($recommendation['selected_activity'] ?? []),
            'expected_benefit' => (float)($recommendation['expected_benefit'] ?? 0),
            'mastery_gap' => isset($recommendation['mastery_gap']) ?
                (float)$recommendation['mastery_gap'] : null,
        ];
    }

    /**
     * Compact future roadmap steps.
     *
     * @param array $roadmap
     * @return array
     */
    private static function compact_roadmap(array $roadmap): array {
        $steps = [];
        foreach (array_slice($roadmap, 0, 10) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $steps[] = [
                'step' => (int)($row['step'] ?? count($steps) + 1),
                'stage' => (string)($row['stage'] ?? ''),
                'code' => (string)($row['code'] ?? ''),
                'action' => (string)($row['action'] ?? ''),
                'selected' => !empty($row['selected']),
                'reason' => (string)($row['reason'] ?? ''),
                'target' => self::compact_target($row['target'] ?? []),
                'activity' => self::compact_activity($row['activity'] ?? []),
                'activity_resolution' => (string)($row['activity_resolution'] ?? ''),
                'destination' => self::compact_destination($row['destination'] ?? []),
            ];
        }
        return $steps;
    }

    /**
     * Compact target reference.
     *
     * @param mixed $target
     * @return array
     */
    private static function compact_target($target): array {
        if (!is_array($target) || empty($target['type']) || empty($target['id'])) {
            return ['available' => false, 'type' => '', 'id' => 0, 'externalid' => '', 'title' => ''];
        }
        return [
            'available' => true,
            'type' => (string)$target['type'],
            'id' => (int)$target['id'],
            'externalid' => (string)($target['externalid'] ?? ''),
            'title' => (string)($target['title'] ?? ''),
        ];
    }

    /**
     * Compact activity reference.
     *
     * @param mixed $activity
     * @return array
     */
    private static function compact_activity($activity): array {
        if (!is_array($activity) || (empty($activity['cmid']) && empty($activity['objectid']))) {
            return ['available' => false, 'objectid' => 0, 'cmid' => 0, 'title' => '', 'modname' => '', 'url' => ''];
        }
        return [
            'available' => true,
            'objectid' => (int)($activity['objectid'] ?? 0),
            'cmid' => (int)($activity['cmid'] ?? 0),
            'title' => (string)($activity['title'] ?? ''),
            'modname' => (string)($activity['modname'] ?? ''),
            'url' => (string)($activity['url'] ?? ''),
        ];
    }

    /**
     * Compact destination profile.
     *
     * @param mixed $destination
     * @return array
     */
    private static function compact_destination($destination): array {
        if (!is_array($destination) || !$destination) {
            return ['available' => false, 'title' => '', 'cefr' => '', 'flwstage' => ''];
        }
        return [
            'available' => !array_key_exists('available', $destination) || !empty($destination['available']),
            'title' => (string)($destination['title'] ?? ''),
            'cefr' => (string)($destination['cefr'] ?? ''),
            'flwstage' => (string)($destination['flwstage'] ?? ''),
        ];
    }

    /**
     * Explain latest persisted path change.
     *
     * @param array $records
     * @param string $previewhash
     * @return array
     */
    private static function why_path_changed(array $records, string $previewhash): array {
        if (!$records) {
            return self::unavailable_value('NO_PERSISTED_RECOMMENDATION_HISTORY');
        }
        $latest = $records[0];
        if ($previewhash !== '' && !hash_equals((string)$latest['source_hash'], $previewhash)) {
            return [
                'status' => 'refresh_required',
                'changed' => true,
                'dimensions' => ['current_learner_state_or_policy'],
                'reason_codes' => $latest['reason_codes'],
                'reason' => 'The current adaptive preview differs from the latest persisted recommendation.',
            ];
        }
        if (!isset($records[1])) {
            return [
                'status' => 'insufficient_data',
                'changed' => false,
                'dimensions' => [],
                'reason_codes' => $latest['reason_codes'],
                'reason' => 'No earlier persisted recommendation is available for comparison.',
            ];
        }
        $dimensions = $latest['changed_from_previous'];
        return [
            'status' => $dimensions ? 'available' : 'unchanged',
            'changed' => (bool)$dimensions,
            'dimensions' => $dimensions,
            'reason_codes' => $latest['reason_codes'],
            'reason' => $dimensions ? 'The persisted route changed across the listed versioned inputs.' :
                'The latest persisted route has no material compact-view difference from its predecessor.',
        ];
    }

    /**
     * Compare compact recommendation and version snapshots.
     *
     * @param array $new
     * @param array $old
     * @param array $newsnapshot
     * @param array $oldsnapshot
     * @return array
     */
    private static function changed_dimensions(array $new, array $old, array $newsnapshot,
            array $oldsnapshot): array {
        $checks = [
            'goal_version' => [$newsnapshot['goal_version'] ?? [], $oldsnapshot['goal_version'] ?? []],
            'curriculum_version' => [$newsnapshot['curriculum_version'] ?? [],
                $oldsnapshot['curriculum_version'] ?? []],
            'learner_state' => [$newsnapshot['state_snapshot'] ?? [], $oldsnapshot['state_snapshot'] ?? []],
            'policy_versions' => [$newsnapshot['policy_versions'] ?? [], $oldsnapshot['policy_versions'] ?? []],
            'selected_target' => [$new['target'], $old['target']],
            'selected_activity' => [$new['activity'], $old['activity']],
            'adaptive_action' => [[$new['action'], $new['decision_code']], [$old['action'], $old['decision_code']]],
        ];
        $changed = [];
        foreach ($checks as $dimension => [$newvalue, $oldvalue]) {
            if (self::stable_hash($newvalue) !== self::stable_hash($oldvalue)) {
                $changed[] = $dimension;
            }
        }
        return $changed;
    }

    /**
     * Stable JSON hash for compact comparisons.
     *
     * @param mixed $value
     * @return string
     */
    private static function stable_hash($value): string {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * History dashboard pagination options.
     *
     * @param array $pagination
     * @param int $limit
     * @return array
     */
    private static function history_options(array $pagination, int $limit): array {
        return [
            'limit' => self::bounded_int($pagination['limit'] ?? $limit, 1, 100),
            'attemptoffset' => self::bounded_int($pagination['attemptoffset'] ?? 0, 0, PHP_INT_MAX),
            'gradeoffset' => self::bounded_int($pagination['gradeoffset'] ?? 0, 0, PHP_INT_MAX),
            'historyoffset' => self::bounded_int($pagination['historyoffset'] ?? 0, 0, PHP_INT_MAX),
            'activityoffset' => self::bounded_int($pagination['activityoffset'] ?? 0, 0, PHP_INT_MAX),
        ];
    }

    /**
     * Return an unavailable compact value.
     *
     * @param string $reason
     * @return array
     */
    private static function unavailable_value(string $reason): array {
        return ['status' => 'insufficient_data', 'reason' => $reason];
    }

    /**
     * Execute a read component without hiding degraded-mode reason.
     *
     * @param callable $callback
     * @param string $component
     * @return array
     */
    private static function safe_component(callable $callback, string $component): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return [
                'type' => 'CupkpUnavailablePresentationComponent',
                'component' => $component,
                'status' => 'unavailable',
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a status dependency safely.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status(callable $callback): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return ['status' => 'blocked', 'findings' => [[
                'severity' => 'blocker',
                'code' => 'dependency_status_failed',
                'message' => $e->getMessage(),
            ]]];
        }
    }

    /**
     * Runtime file checks.
     *
     * @return array
     */
    private static function file_status(): array {
        $root = dirname(__DIR__, 2);
        $files = [
            'classes/local/student_learning_timeline_view_service.php',
            'classes/local/student_learning_timeline_renderer.php',
            'learning_timeline.php',
            'cli/learning_timeline.php',
            'tests/student_learning_timeline_view_service_test.php',
            'classes/external/api.php',
            'db/services.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            $present[$file] = is_file($root . '/' . $file);
            if (!$present[$file]) {
                $missing[] = $file;
            }
        }
        return ['present' => $present, 'missing' => $missing];
    }

    /**
     * Runtime method checks.
     *
     * @return array
     */
    private static function surface_status(): array {
        global $CFG;

        $historyservice = '\\local_flwhistory\\local\\dashboard_service';
        $historyrenderer = '\\local_flwhistory\\local\\dashboard_renderer';
        $methods = [
            'contract' => method_exists(self::class, 'contract'),
            'status' => method_exists(self::class, 'status'),
            'compose' => method_exists(self::class, 'compose'),
            'learner_timeline' => method_exists(self::class, 'learner_timeline'),
            'recommendation_history' => method_exists(self::class, 'recommendation_history'),
        ];
        $apisource = @file_get_contents($CFG->dirroot . '/local/flwcupkp/classes/external/api.php') ?: '';
        $external = [];
        foreach (['get_learning_timeline_status', 'get_student_learning_timeline'] as $method) {
            $external[$method] = strpos($apisource, 'function ' . $method . '(') !== false;
        }
        return [
            'methods' => $methods,
            'external_api' => $external,
            'history_dashboard_service' => class_exists($historyservice) &&
                method_exists($historyservice, 'learner_dashboard_core'),
            'history_dashboard_renderer' => class_exists($historyrenderer) &&
                method_exists($historyrenderer, 'render'),
            'valid' => !in_array(false, $methods, true) && !in_array(false, $external, true),
        ];
    }

    /**
     * One criterion DTO.
     *
     * @param string $code
     * @param bool $pass
     * @param string $message
     * @return array
     */
    private static function criterion(string $code, bool $pass, string $message): array {
        return ['code' => $code, 'pass' => $pass, 'message' => $message];
    }

    /**
     * Summarize criteria.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $passed = count(array_filter($criteria, static function(array $row): bool {
            return !empty($row['pass']);
        }));
        return ['total' => count($criteria), 'passed' => $passed, 'failed' => count($criteria) - $passed];
    }

    /**
     * Merge dependency and local status findings.
     *
     * @param array $criteria
     * @param array $a5c
     * @param array $history
     * @return array
     */
    private static function status_findings(array $criteria, array $a5c, array $history): array {
        $findings = [];
        foreach ($criteria as $row) {
            if (empty($row['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => $row['code'],
                    'message' => $row['message'],
                    'source' => self::GATE,
                ];
            }
        }
        foreach (($a5c['findings'] ?? []) as $finding) {
            $finding['source'] = $finding['source'] ?? progress_goal_readiness_service::GATE;
            $findings[] = $finding;
        }
        foreach (($history['findings'] ?? []) as $finding) {
            $finding['source'] = $finding['source'] ?? 'HISTORY_V1';
            $findings[] = $finding;
        }
        return $findings;
    }

    /**
     * Clean an optional unit code.
     *
     * @param string $unitcode
     * @return string
     */
    private static function clean_unit_code_optional(string $unitcode): string {
        $unitcode = strtoupper(trim($unitcode));
        if ($unitcode === '') {
            return '';
        }
        $clean = clean_param($unitcode, PARAM_ALPHANUMEXT);
        if ($clean !== $unitcode) {
            throw new \invalid_parameter_exception('Invalid unit code.');
        }
        return $clean;
    }

    /**
     * Bound an integer.
     *
     * @param mixed $value
     * @param int $min
     * @param int $max
     * @return int
     */
    private static function bounded_int($value, int $min, int $max): int {
        return max($min, min($max, (int)$value));
    }
}

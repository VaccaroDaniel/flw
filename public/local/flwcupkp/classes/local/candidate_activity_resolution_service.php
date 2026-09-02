<?php
// Program 3 Gate A4B Candidate Eligibility + Activity Resolution.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves A4 target-level candidates to currently accessible Moodle activities.
 */
final class candidate_activity_resolution_service {
    /** Program 3 candidate eligibility gate. */
    public const GATE = 'P3_A4B';

    /** Frozen A4B service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_CANDIDATE_ACTIVITY_RESOLUTION_V1';

    /** Deterministic activity resolution policy version. */
    public const RESOLUTION_POLICY_VERSION = 'cupkp-candidate-activity-resolution-v1';

    /** Next allowed gate after A4B. */
    public const NEXT_ALLOWED_GATE = 'A5';

    /** @var array Ordered eligibility pipeline from the A4B prompt. */
    private const PIPELINE = [
        'target',
        'curriculum_validity',
        'prerequisite',
        'world_stage_course',
        'enrollment',
        'moodle_availability',
        'visibility',
        'dates',
        'attempts',
        'teacher_restrictions',
        'device_capability',
        'diversity',
        'eligible_activities',
    ];

    /** @var array Candidate target type order. */
    private const TARGET_TYPE_ORDER = [
        'kp' => 10,
        'up' => 20,
        'competency' => 30,
    ];

    /** @var array Object role order for learning-first actions. */
    private const LEARNING_ROLE_ORDER = [
        'TEACHES' => 10,
        'PRACTICES' => 15,
        'EVIDENCE_FOR' => 25,
        'ASSESSES' => 35,
    ];

    /** @var array Object role order for assessment-first actions. */
    private const ASSESSMENT_ROLE_ORDER = [
        'ASSESSES' => 10,
        'EVIDENCE_FOR' => 15,
        'PRACTICES' => 25,
        'TEACHES' => 35,
    ];

    /** @var array Lifecycle states that cannot become a live NEXT target. */
    private const INACTIVE_TARGET_STATUSES = ['deprecated', 'archived', 'retired', 'inactive', 'deleted'];

    /**
     * Return the frozen A4B contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpCandidateActivityResolutionContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                goal_gap_path_service::CONTRACT_VERSION,
                adaptive_decision_policy_service::CONTRACT_VERSION,
                management_v1_contract::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                content_evidence_mapping_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'resolution_policy_version' => self::RESOLUTION_POLICY_VERSION,
            'pipeline' => self::PIPELINE,
            'hard_invariant' => 'inaccessible_activity_can_never_become_next',
            'inputs' => [
                'A4 goal-gap initial path',
                'C3 object-target mappings',
                'Program 1 object identity carried by C3 metadata',
                'Moodle course-module availability for the learner',
                'Moodle quiz attempt limits when the resolved module is a quiz',
            ],
            'outputs' => [
                'target resolution rows',
                'eligible activities',
                'ineligible activities with blocking reasons',
                'NEXT ACTIVITY when accessible',
                'fallback target/activity when first candidate is inaccessible',
                'diagnostic when no activity is eligible',
            ],
            'read_only_surface' => [
                'contract',
                'policy',
                'status',
                'learner_resolution',
                'class_summary',
            ],
            'write_boundary' => [],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'history_v1_source_mutation',
                'learning_goal_mutation',
                'placement_state_mutation',
                'mastery_state_mutation',
                'retention_state_mutation',
                'recommendation_row_writes',
                'persistent_path_generation',
                'continuous_adaptation',
                'activity_unlocking_or_override',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Visible A4B resolution policy.
     *
     * @return array
     */
    public static function policy(): array {
        return [
            'version' => self::RESOLUTION_POLICY_VERSION,
            'source_path_contract' => goal_gap_path_service::CONTRACT_VERSION,
            'content_mapping_contract' => content_evidence_mapping_contract::CONTRACT_VERSION,
            'pipeline' => self::PIPELINE,
            'hard_invariant' => [
                'rule' => 'Only activities passing every blocking eligibility check may become NEXT.',
                'blocked_statuses' => self::INACTIVE_TARGET_STATUSES,
            ],
            'fallback' => [
                'first_choice' => 'A4 rank 1 candidate target',
                'next_best' => 'Next A4 candidate target with at least one eligible mapped activity',
                'otherwise' => 'diagnostic_required',
            ],
            'attempt_policy' => [
                'quiz_attempt_limit' => 'A quiz with a positive attempts limit is blocked once finished attempts reach that limit.',
                'in_progress_attempt' => 'In-progress attempts do not count as exhausted until finished.',
            ],
            'tie_breaking' => [
                'a4_candidate_rank_ascending',
                'eligible_before_ineligible',
                'action_role_priority',
                'difficulty_ascending_when_present',
                'lesson_ascending',
                'object_externalid_ascending',
                'object_id_ascending',
            ],
            'read_only' => true,
            'continuous_adaptation' => 'not_enabled_until_A5',
        ];
    }

    /**
     * Readiness status for A4B.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_limit($limit, 300);

        $a4 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return goal_gap_path_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $mapping = self::safe_status_call(static function() use ($courseid, $unitcode, $limit): array {
            return content_evidence_mapping_contract::content_mapping_status($courseid, $unitcode, $limit);
        });
        $management = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return management_v1_contract::consumer_snapshot($courseid, $unitcode, $frameworkid, $limit);
        });
        $history = self::safe_status_call(static function() use ($courseid): array {
            return history_v1_consumer_contract::contract_status($courseid, 1);
        });
        $files = self::file_status();
        $surface = self::surface_status();
        $criteria = self::criteria($a4, $mapping, $management, $history, $files, $surface);
        $criteriasummary = self::criteria_summary($criteria);
        $classsummary = $courseid > 0 ?
            self::class_summary($courseid, $unitcode, $frameworkid, min($limit, 50))['summary'] :
            self::empty_class_summary();

        return [
            'type' => 'CupkpCandidateActivityResolutionStatus',
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
                'goal_gap_path_service' => self::dependency_summary($a4),
                'content_evidence_mapping_contract' => self::dependency_summary($mapping),
                'management_v1' => self::dependency_summary($management),
                'history_v1' => self::dependency_summary($history),
            ],
            'policy' => self::policy(),
            'files' => $files,
            'surface' => $surface,
            'summary' => $classsummary,
            'findings' => self::status_findings($criteria, [$a4, $mapping, $management, $history]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'path_persistence_allowed' => false,
            'moodle_activity_resolution_allowed' => true,
            'continuous_adaptation_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Resolve one learner's A4 target candidates to accessible activities.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function learner_resolution(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        if ($courseid > 0) {
            evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_limit($limit, 500);

        $path = goal_gap_path_service::learner_path($userid, $courseid, $unitcode, $frameworkid, $limit);
        $resolutions = [];
        $eligible = [];
        $ineligible = [];

        foreach (($path['candidate_next_targets'] ?? []) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $resolution = self::resolve_candidate($candidate, $userid, $courseid, $unitcode, $frameworkid, $limit);
            $resolutions[] = $resolution;
            foreach ($resolution['eligible_activities'] as $activity) {
                $eligible[] = $activity;
            }
            foreach ($resolution['ineligible_activities'] as $activity) {
                $ineligible[] = $activity;
            }
        }

        usort($eligible, [self::class, 'compare_activity_rows']);
        usort($ineligible, [self::class, 'compare_activity_rows']);

        $selected = self::selected_resolution($resolutions);
        $nextactivity = $selected ? $selected['selected_activity'] : null;
        $nexttarget = $selected ? $selected['target'] : null;
        $fallback = self::fallback_summary($path['next_target'] ?? null, $selected, $resolutions);
        $diagnostic = self::diagnostic($path, $resolutions, $nextactivity);
        $roadmap = self::enriched_roadmap($path['projected_roadmap'] ?? [], $resolutions);
        $hash = self::resolution_hash($userid, $courseid, $unitcode, $frameworkid, $path, $resolutions,
            $nextactivity);

        return [
            'type' => 'CupkpLearnerCandidateActivityResolution',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'resolution_policy_version' => self::RESOLUTION_POLICY_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'resolution_status' => self::resolution_status($path, $nextactivity, $diagnostic),
            'source_initial_path' => $path,
            'target_resolutions' => $resolutions,
            'eligible_activities' => $eligible,
            'ineligible_activities' => $ineligible,
            'next_target' => $nexttarget,
            'next_activity' => $nextactivity,
            'fallback' => $fallback,
            'diagnostic' => $diagnostic,
            'projected_roadmap' => $roadmap,
            'destination' => $path['destination'] ?? [],
            'summary' => [
                'candidate_targets' => count($resolutions),
                'targets_with_eligible_activity' => count(array_filter($resolutions,
                    static function(array $resolution): bool {
                        return !empty($resolution['eligible_activities']);
                    })),
                'eligible_activities' => count($eligible),
                'ineligible_activities' => count($ineligible),
                'fallback_used' => !empty($fallback['used']),
                'diagnostic_required' => !empty($diagnostic['required']),
            ],
            'explainability' => [
                'resolution_hash' => $hash,
                'a4_path_hash' => $path['explainability']['path_hash'] ?? '',
                'pipeline' => self::PIPELINE,
                'hard_invariant' => 'inaccessible_activity_can_never_become_next',
                'resolution_policy_version' => self::RESOLUTION_POLICY_VERSION,
                'tie_breaking' => self::policy()['tie_breaking'],
                'non_actions' => [
                    'no_recommendation_row_write',
                    'no_persistent_path_generation',
                    'no_mastery_or_retention_mutation',
                    'no_history_v1_source_mutation',
                    'no_activity_unlocking_or_override',
                    'no_continuous_adaptation',
                ],
            ],
            'read_only' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'path_persistence_allowed' => false,
            'moodle_activity_resolution_allowed' => true,
            'continuous_adaptation_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level A4B activity-resolution summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID is required.');
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_limit($limit, 300);
        $learners = self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $rows = [];
        $summary = self::empty_class_summary();
        $summary['learners'] = count($learners);

        foreach ($learners as $learnerid) {
            try {
                $resolution = self::learner_resolution((int)$learnerid, $courseid, $unitcode, $frameworkid, 120);
            } catch (\invalid_parameter_exception $e) {
                $summary['skipped_unenrolled']++;
                $rows[] = [
                    'userid' => (int)$learnerid,
                    'learner' => self::learner_identity((int)$learnerid),
                    'resolution_status' => 'skipped_unenrolled',
                    'reason' => $e->getMessage(),
                ];
                continue;
            }

            $status = (string)($resolution['resolution_status'] ?? 'unknown');
            $summary['statuses'][$status] = ($summary['statuses'][$status] ?? 0) + 1;
            $summary['eligible_activities'] += (int)($resolution['summary']['eligible_activities'] ?? 0);
            $summary['ineligible_activities'] += (int)($resolution['summary']['ineligible_activities'] ?? 0);
            $summary['candidate_targets'] += (int)($resolution['summary']['candidate_targets'] ?? 0);
            if (!empty($resolution['summary']['fallback_used'])) {
                $summary['fallback_used']++;
            }
            if (!empty($resolution['summary']['diagnostic_required'])) {
                $summary['diagnostic_required']++;
            }
            if (!empty($resolution['next_activity'])) {
                $summary['next_activity_ready']++;
            }

            $rows[] = [
                'userid' => (int)$learnerid,
                'learner' => self::learner_identity((int)$learnerid),
                'resolution_status' => $status,
                'next_target' => $resolution['next_target'],
                'next_activity' => $resolution['next_activity'],
                'fallback' => $resolution['fallback'],
                'diagnostic' => $resolution['diagnostic'],
                'resolution_hash' => $resolution['explainability']['resolution_hash'],
            ];
        }

        arsort($summary['statuses']);
        return [
            'type' => 'CupkpClassCandidateActivityResolutionSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'resolution_policy_version' => self::RESOLUTION_POLICY_VERSION,
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
            'recommendation_writes_allowed' => false,
            'path_persistence_allowed' => false,
            'moodle_activity_resolution_allowed' => true,
            'continuous_adaptation_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Resolve one A4 candidate target.
     *
     * @param array $candidate
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function resolve_candidate(array $candidate, int $userid, int $courseid, string $unitcode,
            int $frameworkid, int $limit): array {
        $targetvalidity = self::target_validity($candidate['target'] ?? [], $frameworkid);
        $target = $targetvalidity['target'];
        $objects = $targetvalidity['valid'] ?
            self::object_rows_for_target((string)$target['type'], (int)$target['id'], $courseid, $unitcode,
                $frameworkid, $limit) : [];
        $eligible = [];
        $ineligible = [];
        $blocking = [];

        foreach ($objects as $objectrow) {
            $activity = self::activity_row($objectrow, $targetvalidity, $candidate, $userid, $courseid, $unitcode,
                $frameworkid);
            if (!empty($activity['eligible'])) {
                $eligible[] = $activity;
            } else {
                $ineligible[] = $activity;
                $blocking = array_merge($blocking, $activity['blocking_codes']);
            }
        }

        if (!$targetvalidity['valid']) {
            $blocking[] = $targetvalidity['code'];
        } else if (!$objects) {
            $blocking[] = 'no_mapped_activities';
        }

        usort($eligible, [self::class, 'compare_activity_rows']);
        usort($ineligible, [self::class, 'compare_activity_rows']);
        $selected = $eligible ? $eligible[0] : null;

        return [
            'candidate_rank' => (int)($candidate['rank'] ?? 0),
            'candidate_code' => (string)($candidate['code'] ?? ''),
            'candidate_action' => (string)($candidate['action'] ?? ''),
            'candidate_reason' => (string)($candidate['reason'] ?? ''),
            'target' => $target,
            'status' => $selected ? 'eligible' : 'ineligible',
            'target_checks' => $targetvalidity['checks'],
            'mapped_activity_count' => count($objects),
            'eligible_activities' => $eligible,
            'ineligible_activities' => $ineligible,
            'selected_activity' => $selected,
            'blocking_codes' => array_values(array_unique(array_filter($blocking))),
        ];
    }

    /**
     * Build an activity-level eligibility row.
     *
     * @param \stdClass $objectrow
     * @param array $targetvalidity
     * @param array $candidate
     * @param int $userid
     * @param int $requestedcourseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function activity_row(\stdClass $objectrow, array $targetvalidity, array $candidate, int $userid,
            int $requestedcourseid, string $unitcode, int $frameworkid): array {
        $object = self::object_from_row($objectrow);
        $map = self::map_from_row($objectrow);
        $checks = [];
        $continue = true;
        $cmrecord = !empty($object->cmid) ? self::course_module_record((int)$object->cmid) : null;
        $resolvedcourseid = self::resolved_courseid($object, $cmrecord, $requestedcourseid);

        self::append_check($checks, self::check_from_target_validity($targetvalidity), $continue);
        self::append_check($checks, self::curriculum_mapping_check($object, $map), $continue);
        self::append_check($checks, self::prerequisite_check($candidate), $continue);
        self::append_check($checks, self::world_stage_course_check($object, $cmrecord, $requestedcourseid,
            $unitcode, $frameworkid), $continue);
        self::append_check($checks, $continue ? self::enrollment_check($userid, $resolvedcourseid) :
            self::not_checked('enrollment'), $continue);

        $activity = ['cm' => null, 'modname' => '', 'url' => ''];
        if ($continue) {
            $activity = self::moodle_activity($object, $cmrecord, $resolvedcourseid, $userid);
        }
        self::append_check($checks, $continue ? $activity['check'] : self::not_checked('moodle_availability'),
            $continue);
        self::append_check($checks, $continue ? self::visibility_check($activity['cm']) :
            self::not_checked('visibility'), $continue);
        self::append_check($checks, $continue ? self::dates_check($activity['cm']) : self::not_checked('dates'),
            $continue);
        self::append_check($checks, $continue ? self::attempts_check($activity['cm'], $userid) :
            self::not_checked('attempts'), $continue);
        self::append_check($checks, $continue ? self::teacher_restrictions_check($activity['cm']) :
            self::not_checked('teacher_restrictions'), $continue);
        self::append_check($checks, $continue ? self::device_capability_check($activity['cm']) :
            self::not_checked('device_capability'), $continue);
        self::append_check($checks, $continue ? self::diversity_check($object, $activity['cm']) :
            self::not_checked('diversity'), $continue);

        $eligible = $continue;
        $checks[] = $eligible ?
            self::check('eligible_activities', 'passed', 'eligible_activity', 'Activity passed every blocking check.') :
            self::check('eligible_activities', 'failed', 'activity_inaccessible',
                'Activity cannot become NEXT because at least one blocking check failed.');

        $blocking = self::blocking_codes($checks);
        $role = self::canonical_role((string)($map->role ?? ''), (string)($object->purpose ?? ''),
            (string)($object->objecttype ?? ''));

        return [
            'eligible' => $eligible,
            'status' => $eligible ? 'eligible' : 'ineligible',
            'objectid' => (int)$object->id,
            'object_externalid' => (string)$object->externalid,
            'title' => (string)$object->title,
            'objecttype' => (string)$object->objecttype,
            'purpose' => (string)$object->purpose,
            'lesson' => (string)$object->lesson,
            'unitcode' => (string)$object->unitcode,
            'courseid' => $resolvedcourseid,
            'cmid' => (int)($object->cmid ?? 0),
            'modname' => (string)($activity['modname'] ?? ''),
            'instanceid' => (int)($activity['instanceid'] ?? 0),
            'url' => (string)($activity['url'] ?? ''),
            'mapid' => (int)$map->id,
            'targettype' => (string)$map->targettype,
            'targetid' => (int)$map->targetid,
            'target' => $targetvalidity['target'],
            'role' => $role,
            'maprole' => (string)$map->role,
            'evidencestrength' => (string)($map->evidencestrength ?: ($object->evidencestrength ?? '')),
            'difficulty' => isset($object->difficulty) ? (float)$object->difficulty : null,
            'program1_identity' => content_evidence_mapping_contract::identity_from_object($object),
            'candidate_rank' => (int)($candidate['rank'] ?? 0),
            'candidate_action' => (string)($candidate['action'] ?? ''),
            'candidate_code' => (string)($candidate['code'] ?? ''),
            'priority' => self::activity_priority($candidate, $object, $map, $activity['cm']),
            'diversity_bucket' => self::diversity_bucket($object, $activity['cm']),
            'checks' => $checks,
            'blocking_codes' => $blocking,
            'blocking_reasons' => self::blocking_reasons($checks),
        ];
    }

    /**
     * Target lifecycle/identity validity.
     *
     * @param mixed $target
     * @param int $frameworkid
     * @return array
     */
    private static function target_validity($target, int $frameworkid): array {
        global $DB;

        $target = self::normalize_target(is_array($target) ? $target : []);
        if (empty($target['type']) || empty($target['id'])) {
            return [
                'valid' => false,
                'code' => 'missing_target',
                'target' => $target,
                'checks' => [self::check('target', 'failed', 'missing_target',
                    'A4 candidate does not contain a valid C-UP-KP target.')],
            ];
        }

        try {
            $table = evidence_guard::target_table((string)$target['type']);
        } catch (\invalid_parameter_exception $e) {
            return [
                'valid' => false,
                'code' => 'unsupported_target_type',
                'target' => $target,
                'checks' => [self::check('target', 'failed', 'unsupported_target_type', $e->getMessage())],
            ];
        }

        $record = $DB->get_record($table, ['id' => (int)$target['id']], '*', IGNORE_MISSING);
        if (!$record) {
            return [
                'valid' => false,
                'code' => 'target_not_found',
                'target' => $target,
                'checks' => [self::check('target', 'failed', 'target_not_found',
                    'C-UP-KP target no longer exists.')],
            ];
        }

        $status = strtolower(trim((string)($record->status ?? 'draft')));
        $status = $status === '' ? 'draft' : $status;
        $target['externalid'] = (string)($record->externalid ?? $target['externalid']);
        $target['title'] = (string)($record->title ?? $target['title']);
        $target['frameworkid'] = (int)($record->frameworkid ?? $target['frameworkid']);
        $target['status'] = $status;
        if (isset($record->cefr)) {
            $target['cefr'] = (string)$record->cefr;
        }
        if (isset($record->stage)) {
            $target['flwstage'] = (string)$record->stage;
        }

        if ($frameworkid > 0 && (int)($record->frameworkid ?? 0) !== $frameworkid) {
            return [
                'valid' => false,
                'code' => 'target_framework_mismatch',
                'target' => $target,
                'checks' => [self::check('target', 'failed', 'target_framework_mismatch',
                    'Target belongs to a different C-UP-KP framework.')],
            ];
        }

        if (in_array($status, self::INACTIVE_TARGET_STATUSES, true)) {
            return [
                'valid' => false,
                'code' => 'inactive_target_status',
                'target' => $target,
                'checks' => [self::check('target', 'failed', 'inactive_target_status',
                    'Deprecated, archived, retired, inactive, or deleted targets cannot become NEXT.',
                    ['status' => $status])],
            ];
        }

        $check = self::check('target', 'passed', 'target_valid', 'Target exists and is not retired.',
            ['status' => $status]);
        if (!in_array($status, ['approved', 'published'], true)) {
            $check['status'] = 'warning';
            $check['message'] = 'Target exists but is not approved/published yet.';
        }

        return [
            'valid' => true,
            'code' => 'target_valid',
            'target' => $target,
            'checks' => [$check],
        ];
    }

    /**
     * Query mapped objects for a target.
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function object_rows_for_target(string $targettype, int $targetid, int $courseid,
            string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        $where = 'm.targettype = :targettype AND m.targetid = :targetid';
        $params = [
            'targettype' => $targettype,
            'targetid' => $targetid,
        ];
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

        return array_values($DB->get_records_sql(
            "SELECT m.id AS id,
                    m.id AS mapid,
                    m.objectid,
                    m.targettype,
                    m.targetid,
                    m.role AS maprole,
                    m.evidencestrength AS mapevidencestrength,
                    o.frameworkid AS objectframeworkid,
                    o.externalid AS objectexternalid,
                    o.courseid AS objectcourseid,
                    o.unitcode AS objectunitcode,
                    o.lesson AS objectlesson,
                    o.objecttype AS objecttype,
                    o.title AS objecttitle,
                    o.cmid AS objectcmid,
                    o.sourceid AS objectsourceid,
                    o.purpose AS objectpurpose,
                    o.evidencestrength AS objectevidencestrength,
                    o.difficulty AS objectdifficulty,
                    o.role AS objectrole,
                    o.metadatajson AS objectmetadatajson
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE {$where}
           ORDER BY o.lesson ASC, o.externalid ASC, o.id ASC",
            $params,
            0,
            self::bounded_limit($limit, 500)
        ));
    }

    /**
     * Convert SQL aliases to an object row.
     *
     * @param \stdClass $row
     * @return \stdClass
     */
    private static function object_from_row(\stdClass $row): \stdClass {
        return (object)[
            'id' => (int)$row->objectid,
            'frameworkid' => (int)$row->objectframeworkid,
            'externalid' => (string)$row->objectexternalid,
            'courseid' => empty($row->objectcourseid) ? null : (int)$row->objectcourseid,
            'unitcode' => (string)($row->objectunitcode ?? ''),
            'lesson' => (string)($row->objectlesson ?? ''),
            'objecttype' => (string)($row->objecttype ?? ''),
            'title' => (string)$row->objecttitle,
            'cmid' => empty($row->objectcmid) ? null : (int)$row->objectcmid,
            'sourceid' => (string)($row->objectsourceid ?? ''),
            'purpose' => (string)($row->objectpurpose ?? ''),
            'evidencestrength' => (string)($row->objectevidencestrength ?? ''),
            'difficulty' => $row->objectdifficulty === null ? null : (float)$row->objectdifficulty,
            'role' => (string)($row->objectrole ?? ''),
            'metadatajson' => (string)($row->objectmetadatajson ?? '{}'),
        ];
    }

    /**
     * Convert SQL aliases to an object-map row.
     *
     * @param \stdClass $row
     * @return \stdClass
     */
    private static function map_from_row(\stdClass $row): \stdClass {
        return (object)[
            'id' => (int)$row->mapid,
            'objectid' => (int)$row->objectid,
            'targettype' => (string)$row->targettype,
            'targetid' => (int)$row->targetid,
            'role' => (string)($row->maprole ?? ''),
            'evidencestrength' => (string)($row->mapevidencestrength ?? ''),
        ];
    }

    /**
     * Return the course module record for a cmid.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    private static function course_module_record(int $cmid): ?\stdClass {
        global $DB;

        if ($cmid <= 0) {
            return null;
        }
        return $DB->get_record('course_modules', ['id' => $cmid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Resolve the object activity course.
     *
     * @param \stdClass $object
     * @param \stdClass|null $cmrecord
     * @param int $requestedcourseid
     * @return int
     */
    private static function resolved_courseid(\stdClass $object, ?\stdClass $cmrecord, int $requestedcourseid): int {
        if (!empty($object->courseid)) {
            return (int)$object->courseid;
        }
        if ($cmrecord && !empty($cmrecord->course)) {
            return (int)$cmrecord->course;
        }
        return $requestedcourseid;
    }

    /**
     * Convert target validity into the first pipeline check.
     *
     * @param array $targetvalidity
     * @return array
     */
    private static function check_from_target_validity(array $targetvalidity): array {
        return $targetvalidity['checks'][0] ?? self::check('target', 'failed', 'missing_target',
            'A4 candidate does not contain a valid C-UP-KP target.');
    }

    /**
     * Validate the object mapping against C3.
     *
     * @param \stdClass $object
     * @param \stdClass $map
     * @return array
     */
    private static function curriculum_mapping_check(\stdClass $object, \stdClass $map): array {
        try {
            content_evidence_mapping_contract::assert_object_map_contract($object, $map);
            return self::check('curriculum_validity', 'passed', 'object_map_valid',
                'Object-target mapping follows the C3 content mapping contract.');
        } catch (\invalid_parameter_exception $e) {
            return self::check('curriculum_validity', 'failed', 'invalid_object_map', $e->getMessage());
        }
    }

    /**
     * Confirm A4 prerequisite semantics selected this candidate.
     *
     * @param array $candidate
     * @return array
     */
    private static function prerequisite_check(array $candidate): array {
        $code = (string)($candidate['code'] ?? '');
        if ($code === 'PREREQUISITE_REQUIRED') {
            return self::check('prerequisite', 'passed', 'a4_prerequisite_repair_target',
                'A4 selected this target to repair a hard prerequisite blocker.');
        }
        return self::check('prerequisite', 'passed', 'a4_candidate_prerequisites_accepted',
            'A4 prerequisite traversal accepted this candidate target.');
    }

    /**
     * Check course, unit, framework, and Moodle cm course alignment.
     *
     * @param \stdClass $object
     * @param \stdClass|null $cmrecord
     * @param int $requestedcourseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function world_stage_course_check(\stdClass $object, ?\stdClass $cmrecord, int $requestedcourseid,
            string $unitcode, int $frameworkid): array {
        if ($unitcode !== '' && (string)($object->unitcode ?? '') !== $unitcode) {
            return self::check('world_stage_course', 'failed', 'unit_mismatch',
                'Learning object belongs to a different unit.',
                ['object_unitcode' => (string)($object->unitcode ?? ''), 'requested_unitcode' => $unitcode]);
        }
        if ($frameworkid > 0 && (int)($object->frameworkid ?? 0) !== $frameworkid) {
            return self::check('world_stage_course', 'failed', 'object_framework_mismatch',
                'Learning object belongs to a different C-UP-KP framework.');
        }
        if ($requestedcourseid > 0 && !empty($object->courseid) && (int)$object->courseid !== $requestedcourseid) {
            return self::check('world_stage_course', 'failed', 'object_course_mismatch',
                'Learning object belongs to a different Moodle course.');
        }
        if ($requestedcourseid > 0 && $cmrecord && (int)$cmrecord->course !== $requestedcourseid) {
            return self::check('world_stage_course', 'failed', 'cm_course_mismatch',
                'Mapped Moodle activity belongs to a different course.');
        }
        if ($cmrecord && !empty($object->courseid) && (int)$cmrecord->course !== (int)$object->courseid) {
            return self::check('world_stage_course', 'failed', 'object_cmid_course_mismatch',
                'Learning object course and Moodle course-module course do not match.');
        }
        return self::check('world_stage_course', 'passed', 'course_scope_valid',
            'Learning object matches the requested course, unit, and framework scope.');
    }

    /**
     * Check learner enrollment for the resolved activity course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    private static function enrollment_check(int $userid, int $courseid): array {
        try {
            evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
            return self::check('enrollment', 'passed', 'learner_enrolled',
                'Learner is enrolled in the resolved activity course.', ['courseid' => $courseid]);
        } catch (\invalid_parameter_exception $e) {
            return self::check('enrollment', 'failed', 'learner_not_enrolled', $e->getMessage(),
                ['courseid' => $courseid]);
        }
    }

    /**
     * Resolve Moodle cm_info for the learner.
     *
     * @param \stdClass $object
     * @param \stdClass|null $cmrecord
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private static function moodle_activity(\stdClass $object, ?\stdClass $cmrecord, int $courseid, int $userid): array {
        if (empty($object->cmid)) {
            return [
                'cm' => null,
                'modname' => '',
                'instanceid' => 0,
                'url' => '',
                'check' => self::check('moodle_availability', 'failed', 'missing_cmid',
                    'Learning object is not linked to a Moodle course module.'),
            ];
        }
        if (!$cmrecord) {
            return [
                'cm' => null,
                'modname' => '',
                'instanceid' => 0,
                'url' => '',
                'check' => self::check('moodle_availability', 'failed', 'cmid_not_found',
                    'Mapped Moodle course module no longer exists.'),
            ];
        }
        if (!empty($cmrecord->deletioninprogress)) {
            return [
                'cm' => null,
                'modname' => '',
                'instanceid' => 0,
                'url' => '',
                'check' => self::check('moodle_availability', 'failed', 'activity_deletion_in_progress',
                    'Mapped Moodle activity is being deleted.'),
            ];
        }
        if ($courseid <= 0) {
            $courseid = (int)$cmrecord->course;
        }
        try {
            $modinfo = get_fast_modinfo($courseid, $userid);
            $cm = $modinfo->get_cm((int)$object->cmid);
        } catch (\Throwable $e) {
            return [
                'cm' => null,
                'modname' => '',
                'instanceid' => 0,
                'url' => '',
                'check' => self::check('moodle_availability', 'failed', 'cmid_not_available',
                    $e->getMessage(), ['courseid' => $courseid, 'cmid' => (int)$object->cmid]),
            ];
        }

        $url = '';
        if (!empty($cm->modname)) {
            $url = (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => (int)$cm->id]))->out(false);
        }

        return [
            'cm' => $cm,
            'modname' => (string)$cm->modname,
            'instanceid' => (int)$cm->instance,
            'url' => $url,
            'check' => self::check('moodle_availability', 'passed', 'cmid_resolved',
                'Mapped Moodle course module exists for this learner.', [
                    'courseid' => $courseid,
                    'cmid' => (int)$cm->id,
                    'modname' => (string)$cm->modname,
                ]),
        ];
    }

    /**
     * Check visibility.
     *
     * @param mixed $cm
     * @return array
     */
    private static function visibility_check($cm): array {
        if (!$cm) {
            return self::check('visibility', 'failed', 'cmid_not_resolved', 'Moodle activity was not resolved.');
        }
        $visible = !empty($cm->visible);
        $visibleoncoursepage = !isset($cm->visibleoncoursepage) || !empty($cm->visibleoncoursepage);
        if (!$visible || !$visibleoncoursepage) {
            return self::check('visibility', 'failed', 'activity_hidden',
                'Activity is hidden from learners.', [
                    'visible' => $visible,
                    'visibleoncoursepage' => $visibleoncoursepage,
                ]);
        }
        return self::check('visibility', 'passed', 'activity_visible', 'Activity is visible.');
    }

    /**
     * Check current date/availability restrictions.
     *
     * @param mixed $cm
     * @return array
     */
    private static function dates_check($cm): array {
        global $DB;

        if (!$cm) {
            return self::check('dates', 'failed', 'cmid_not_resolved', 'Moodle activity was not resolved.');
        }
        if (isset($cm->available) && !$cm->available) {
            return self::check('dates', 'failed', 'availability_restriction_active',
                self::availability_message($cm) ?: 'Moodle availability restrictions are active.');
        }
        if ((string)$cm->modname === 'quiz') {
            $quiz = $DB->get_record('quiz', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
            if ($quiz) {
                $now = time();
                if (!empty($quiz->timeopen) && (int)$quiz->timeopen > $now) {
                    return self::check('dates', 'failed', 'quiz_not_open_yet',
                        'Quiz open date is in the future.', ['timeopen' => (int)$quiz->timeopen]);
                }
                if (!empty($quiz->timeclose) && (int)$quiz->timeclose < $now) {
                    return self::check('dates', 'failed', 'quiz_closed',
                        'Quiz close date is in the past.', ['timeclose' => (int)$quiz->timeclose]);
                }
            }
        }
        return self::check('dates', 'passed', 'activity_in_date_window',
            'No active date or availability window blocks the activity.');
    }

    /**
     * Check attempt availability for quiz modules.
     *
     * @param mixed $cm
     * @param int $userid
     * @return array
     */
    private static function attempts_check($cm, int $userid): array {
        global $DB;

        if (!$cm) {
            return self::check('attempts', 'failed', 'cmid_not_resolved', 'Moodle activity was not resolved.');
        }
        if ((string)$cm->modname !== 'quiz') {
            return self::check('attempts', 'not_applicable', 'not_quiz', 'Activity does not use Moodle quiz attempts.');
        }
        $quiz = $DB->get_record('quiz', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
        if (!$quiz) {
            return self::check('attempts', 'failed', 'quiz_not_found', 'Mapped quiz record no longer exists.');
        }
        $limit = (int)($quiz->attempts ?? 0);
        $finished = (int)$DB->count_records('quiz_attempts', [
            'quiz' => (int)$quiz->id,
            'userid' => $userid,
            'preview' => 0,
            'state' => 'finished',
        ]);
        $inprogress = (int)$DB->count_records('quiz_attempts', [
            'quiz' => (int)$quiz->id,
            'userid' => $userid,
            'preview' => 0,
            'state' => 'inprogress',
        ]);
        if ($limit > 0 && $finished >= $limit && $inprogress <= 0) {
            return self::check('attempts', 'failed', 'quiz_attempts_exhausted',
                'Learner has used all allowed quiz attempts.', [
                    'attempt_limit' => $limit,
                    'finished_attempts' => $finished,
                    'in_progress_attempts' => $inprogress,
                ]);
        }
        return self::check('attempts', 'passed', 'quiz_attempt_available',
            'Quiz attempts remain available, or an attempt can be continued.', [
                'attempt_limit' => $limit,
                'finished_attempts' => $finished,
                'in_progress_attempts' => $inprogress,
            ]);
    }

    /**
     * Check learner-specific restrictions.
     *
     * @param mixed $cm
     * @return array
     */
    private static function teacher_restrictions_check($cm): array {
        if (!$cm) {
            return self::check('teacher_restrictions', 'failed', 'cmid_not_resolved',
                'Moodle activity was not resolved.');
        }
        if (empty($cm->uservisible)) {
            return self::check('teacher_restrictions', 'failed', 'learner_cannot_access_activity',
                self::availability_message($cm) ?: 'Learner-specific Moodle restrictions block this activity.');
        }
        return self::check('teacher_restrictions', 'passed', 'learner_can_access_activity',
            'No learner-specific teacher restriction blocks this activity.');
    }

    /**
     * Check local module launch capability.
     *
     * @param mixed $cm
     * @return array
     */
    private static function device_capability_check($cm): array {
        global $CFG;

        if (!$cm) {
            return self::check('device_capability', 'failed', 'cmid_not_resolved',
                'Moodle activity was not resolved.');
        }
        $viewfile = $CFG->dirroot . '/mod/' . $cm->modname . '/view.php';
        if (!file_exists($viewfile)) {
            return self::check('device_capability', 'failed', 'activity_view_not_available',
                'This Moodle module does not expose a standard learner view page.',
                ['modname' => (string)$cm->modname]);
        }
        return self::check('device_capability', 'passed', 'activity_launchable',
            'Activity has a standard Moodle learner view page.', ['modname' => (string)$cm->modname]);
    }

    /**
     * Surface the diversity bucket used for deterministic tie-breaking.
     *
     * @param \stdClass $object
     * @param mixed $cm
     * @return array
     */
    private static function diversity_check(\stdClass $object, $cm): array {
        return self::check('diversity', 'passed', 'diversity_bucket_recorded',
            'Activity diversity bucket is recorded for deterministic tie-breaking.',
            ['bucket' => self::diversity_bucket($object, $cm)]);
    }

    /**
     * Append one check and stop the pipeline on blocking failure.
     *
     * @param array $checks
     * @param array $check
     * @param bool $continue
     */
    private static function append_check(array &$checks, array $check, bool &$continue): void {
        $checks[] = $check;
        if (($check['status'] ?? '') === 'failed') {
            $continue = false;
        }
    }

    /**
     * Standard check shape.
     *
     * @param string $name
     * @param string $status
     * @param string $code
     * @param string $message
     * @param array $details
     * @return array
     */
    private static function check(string $name, string $status, string $code, string $message,
            array $details = []): array {
        return [
            'name' => $name,
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'blocking' => $status === 'failed',
            'details' => $details,
        ];
    }

    /**
     * Pipeline check that was skipped after an earlier hard failure.
     *
     * @param string $name
     * @return array
     */
    private static function not_checked(string $name): array {
        return self::check($name, 'not_checked', 'not_checked_after_blocker',
            'Not checked because an earlier blocking eligibility check failed.');
    }

    /**
     * Flatten blocking check codes.
     *
     * @param array $checks
     * @return array
     */
    private static function blocking_codes(array $checks): array {
        $codes = [];
        foreach ($checks as $check) {
            if (!empty($check['blocking'])) {
                $codes[] = (string)($check['code'] ?? '');
            }
        }
        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * Flatten blocking check messages.
     *
     * @param array $checks
     * @return array
     */
    private static function blocking_reasons(array $checks): array {
        $reasons = [];
        foreach ($checks as $check) {
            if (!empty($check['blocking'])) {
                $reasons[] = [
                    'code' => (string)($check['code'] ?? ''),
                    'message' => (string)($check['message'] ?? ''),
                ];
            }
        }
        return $reasons;
    }

    /**
     * Select the first candidate with an eligible activity.
     *
     * @param array $resolutions
     * @return array|null
     */
    private static function selected_resolution(array $resolutions): ?array {
        foreach ($resolutions as $resolution) {
            if (!empty($resolution['selected_activity'])) {
                return $resolution;
            }
        }
        return null;
    }

    /**
     * Summarize fallback behavior.
     *
     * @param mixed $a4nexttarget
     * @param array|null $selected
     * @param array $resolutions
     * @return array
     */
    private static function fallback_summary($a4nexttarget, ?array $selected, array $resolutions): array {
        $a4key = self::target_key(is_array($a4nexttarget) ? $a4nexttarget : []);
        if (!$selected) {
            return [
                'used' => false,
                'status' => $resolutions ? 'no_eligible_candidate_activity' : 'no_candidate_target',
                'from_target' => is_array($a4nexttarget) ? $a4nexttarget : null,
                'to_target' => null,
                'reason' => 'No fallback activity was eligible.',
            ];
        }
        $selectedkey = self::target_key($selected['target'] ?? []);
        if ($a4key !== '' && $a4key !== $selectedkey) {
            return [
                'used' => true,
                'status' => 'used_next_best_eligible_target',
                'from_target' => is_array($a4nexttarget) ? $a4nexttarget : null,
                'to_target' => $selected['target'],
                'reason' => 'The first A4 candidate did not have an eligible activity, so the next eligible target was selected.',
            ];
        }
        return [
            'used' => false,
            'status' => 'first_candidate_eligible',
            'from_target' => is_array($a4nexttarget) ? $a4nexttarget : null,
            'to_target' => $selected['target'],
            'reason' => 'The first A4 target with activity resolution remained eligible.',
        ];
    }

    /**
     * Build diagnostic fallback when nothing can become NEXT.
     *
     * @param array $path
     * @param array $resolutions
     * @param array|null $nextactivity
     * @return array
     */
    private static function diagnostic(array $path, array $resolutions, ?array $nextactivity): array {
        if ($nextactivity) {
            return [
                'required' => false,
                'code' => 'NEXT_ACTIVITY_READY',
                'message' => 'An eligible Moodle activity is available.',
            ];
        }
        $pathstatus = (string)($path['path_status'] ?? '');
        if ($pathstatus === 'destination_ready') {
            return [
                'required' => false,
                'code' => 'DESTINATION_READY',
                'message' => 'No next activity is needed because the A4 destination is ready.',
            ];
        }
        if (!$resolutions) {
            return [
                'required' => true,
                'code' => 'A4_SETUP_OR_TARGET_REQUIRED',
                'message' => 'A4 did not provide a candidate target that can be resolved to activity.',
                'recommended_action' => self::diagnostic_action_for_path_status($pathstatus),
            ];
        }
        return [
            'required' => true,
            'code' => 'NO_ELIGIBLE_ACTIVITY',
            'message' => 'All mapped activities for the A4 candidate targets are currently inaccessible.',
            'recommended_action' => 'teacher_review_mapping_availability_or_attempts',
            'blocking_codes' => self::candidate_blocking_codes($resolutions),
        ];
    }

    /**
     * Map A4 path status to a diagnostic action.
     *
     * @param string $pathstatus
     * @return string
     */
    private static function diagnostic_action_for_path_status(string $pathstatus): string {
        if ($pathstatus === 'needs_goal') {
            return 'set_learning_goal';
        }
        if ($pathstatus === 'needs_setup') {
            return 'complete_or_review_placement';
        }
        return 'teacher_review_curriculum_mapping';
    }

    /**
     * Enrich the A4 roadmap with resolved activity state.
     *
     * @param array $roadmap
     * @param array $resolutions
     * @return array
     */
    private static function enriched_roadmap(array $roadmap, array $resolutions): array {
        $index = [];
        foreach ($resolutions as $resolution) {
            $index[self::target_key($resolution['target'] ?? [])] = $resolution;
        }

        $rows = [];
        foreach ($roadmap as $step) {
            if (!is_array($step)) {
                continue;
            }
            $target = is_array($step['target'] ?? null) ? $step['target'] : [];
            $key = self::target_key($target);
            if ($key !== '' && isset($index[$key])) {
                $resolution = $index[$key];
                $step['activity_resolution'] = !empty($resolution['selected_activity']) ?
                    'resolved' : 'diagnostic_required';
                $step['activity'] = $resolution['selected_activity'];
                $step['eligible_activity_count'] = count($resolution['eligible_activities']);
                $step['ineligible_activity_count'] = count($resolution['ineligible_activities']);
                $step['blocking_codes'] = $resolution['blocking_codes'];
            } else if (($step['activity_resolution'] ?? '') === 'not_allowed_until_A4B') {
                $step['activity_resolution'] = 'not_applicable';
            }
            $step['continuous_adaptation'] = 'not_enabled_until_A5';
            $rows[] = $step;
        }
        return $rows;
    }

    /**
     * Determine overall learner resolution status.
     *
     * @param array $path
     * @param array|null $nextactivity
     * @param array $diagnostic
     * @return string
     */
    private static function resolution_status(array $path, ?array $nextactivity, array $diagnostic): string {
        if ($nextactivity) {
            return 'next_activity_ready';
        }
        if (($path['path_status'] ?? '') === 'destination_ready') {
            return 'destination_ready';
        }
        if (!empty($diagnostic['required'])) {
            return 'diagnostic_required';
        }
        return 'unknown';
    }

    /**
     * Codes across candidate resolution blockers.
     *
     * @param array $resolutions
     * @return array
     */
    private static function candidate_blocking_codes(array $resolutions): array {
        $codes = [];
        foreach ($resolutions as $resolution) {
            $codes = array_merge($codes, $resolution['blocking_codes'] ?? []);
        }
        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * Compare activity rows by policy tie-breaking.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private static function compare_activity_rows(array $a, array $b): int {
        $priority = (int)($a['priority'] ?? 999999) <=> (int)($b['priority'] ?? 999999);
        if ($priority !== 0) {
            return $priority;
        }
        $difficulty = (float)($a['difficulty'] ?? 999999) <=> (float)($b['difficulty'] ?? 999999);
        if ($difficulty !== 0) {
            return $difficulty;
        }
        $lesson = strcmp((string)($a['lesson'] ?? ''), (string)($b['lesson'] ?? ''));
        if ($lesson !== 0) {
            return $lesson;
        }
        $external = strcmp((string)($a['object_externalid'] ?? ''), (string)($b['object_externalid'] ?? ''));
        if ($external !== 0) {
            return $external;
        }
        return (int)($a['objectid'] ?? 0) <=> (int)($b['objectid'] ?? 0);
    }

    /**
     * Compute deterministic priority for an activity.
     *
     * @param array $candidate
     * @param \stdClass $object
     * @param \stdClass $map
     * @param mixed $cm
     * @return int
     */
    private static function activity_priority(array $candidate, \stdClass $object, \stdClass $map, $cm): int {
        $rank = max(1, (int)($candidate['rank'] ?? 999));
        $targetorder = self::TARGET_TYPE_ORDER[(string)($map->targettype ?? '')] ?? 999;
        $role = self::canonical_role((string)($map->role ?? ''), (string)($object->purpose ?? ''),
            (string)($object->objecttype ?? ''));
        $action = (string)($candidate['action'] ?? '');
        $roleorder = self::role_order($role, $action);
        $typebonus = $cm && (string)$cm->modname === 'quiz' && in_array($action, ['reassess', 'confirm'], true) ?
            -5 : 0;
        return ($rank * 10000) + ($targetorder * 100) + ($roleorder * 10) + $typebonus;
    }

    /**
     * Canonical C3 role, falling back without blocking display if invalid.
     *
     * @param string $role
     * @param string $purpose
     * @param string $objecttype
     * @return string
     */
    private static function canonical_role(string $role, string $purpose, string $objecttype): string {
        try {
            return content_evidence_mapping_contract::canonical_pedagogical_role($role, $purpose, $objecttype);
        } catch (\invalid_parameter_exception $e) {
            return strtoupper($role ?: 'PRACTICES');
        }
    }

    /**
     * Role tie-break order for the selected action.
     *
     * @param string $role
     * @param string $action
     * @return int
     */
    private static function role_order(string $role, string $action): int {
        $assessmentfirst = in_array($action, ['reassess', 'confirm'], true);
        $orders = $assessmentfirst ? self::ASSESSMENT_ROLE_ORDER : self::LEARNING_ROLE_ORDER;
        return $orders[$role] ?? 999;
    }

    /**
     * Diversity bucket used for stable ordering and explainability.
     *
     * @param \stdClass $object
     * @param mixed $cm
     * @return string
     */
    private static function diversity_bucket(\stdClass $object, $cm): string {
        return implode(':', array_filter([
            $cm ? (string)$cm->modname : (string)($object->objecttype ?? ''),
            strtolower((string)($object->purpose ?? '')),
            strtolower((string)($object->lesson ?? '')),
        ]));
    }

    /**
     * Clean Moodle availability text for display/API output.
     *
     * @param mixed $cm
     * @return string
     */
    private static function availability_message($cm): string {
        if (!$cm || empty($cm->availableinfo)) {
            return '';
        }
        return trim(preg_replace('/\s+/', ' ', strip_tags((string)$cm->availableinfo)));
    }

    /**
     * Deterministic resolution hash.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $path
     * @param array $resolutions
     * @param array|null $nextactivity
     * @return string
     */
    private static function resolution_hash(int $userid, int $courseid, string $unitcode, int $frameworkid,
            array $path, array $resolutions, ?array $nextactivity): string {
        $fingerprint = [
            'policy' => self::RESOLUTION_POLICY_VERSION,
            'a4_path_hash' => $path['explainability']['path_hash'] ?? '',
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'next_activity' => $nextactivity ? [
                'objectid' => (int)($nextactivity['objectid'] ?? 0),
                'cmid' => (int)($nextactivity['cmid'] ?? 0),
                'targettype' => (string)($nextactivity['targettype'] ?? ''),
                'targetid' => (int)($nextactivity['targetid'] ?? 0),
            ] : null,
            'resolutions' => array_map(static function(array $resolution): array {
                return [
                    'rank' => (int)($resolution['candidate_rank'] ?? 0),
                    'target' => $resolution['target'] ?? null,
                    'selected_objectid' => (int)($resolution['selected_activity']['objectid'] ?? 0),
                    'selected_cmid' => (int)($resolution['selected_activity']['cmid'] ?? 0),
                    'blocking_codes' => $resolution['blocking_codes'] ?? [],
                ];
            }, $resolutions),
        ];
        return sha1(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Criteria for A4B readiness.
     *
     * @param array $a4
     * @param array $mapping
     * @param array $management
     * @param array $history
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $a4, array $mapping, array $management, array $history, array $files,
            array $surface): array {
        return [
            'a4_initial_path_ready' => self::criterion('a4_initial_path_ready',
                ($a4['status'] ?? '') === 'ready' &&
                    (($a4['contract']['version'] ?? '') === goal_gap_path_service::CONTRACT_VERSION),
                'A4 goal-gap initial path must be ready.'),
            'content_mapping_contract_frozen' => self::criterion('content_mapping_contract_frozen',
                ($mapping['status'] ?? '') === 'frozen' &&
                    (($mapping['contract']['version'] ?? '') === content_evidence_mapping_contract::CONTRACT_VERSION),
                'C3 content mapping contract must be frozen.'),
            'management_v1_available' => self::criterion('management_v1_available',
                in_array((string)($management['management_status'] ?? ''), ['frozen'], true),
                'FLW_CUPKP_MANAGEMENT_V1 must be consumable.'),
            'history_v1_boundary_preserved' => self::criterion('history_v1_boundary_preserved',
                ($history['status'] ?? '') !== 'blocked' &&
                    (($history['requiredcontract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT),
                'History V1 remains the only normal source-history input.'),
            'resolver_files_present' => self::criterion('resolver_files_present',
                !empty($files['present']['classes/local/candidate_activity_resolution_service.php']) &&
                    !empty($files['present']['activity_resolution.php']) &&
                    !empty($files['present']['cli/activity_resolution.php']),
                'A4B resolver service, page, and CLI must exist.'),
            'resolver_surface_present' => self::criterion('resolver_surface_present',
                !in_array(false, $surface['methods'], true),
                'A4B read-only service methods must be available.'),
            'hard_invariant_declared' => self::criterion('hard_invariant_declared',
                self::contract()['hard_invariant'] === 'inaccessible_activity_can_never_become_next',
                'The inaccessible-activity invariant must be explicit.'),
            'read_only_boundary_preserved' => self::criterion('read_only_boundary_preserved',
                empty(self::contract()['write_boundary']) &&
                    in_array('recommendation_row_writes', self::contract()['does_not_do'], true) &&
                    in_array('continuous_adaptation', self::contract()['does_not_do'], true),
                'A4B may resolve activity eligibility but must not write recommendations or adapt continuously.'),
            'next_gate_is_a5' => self::criterion('next_gate_is_a5',
                self::NEXT_ALLOWED_GATE === 'A5',
                'A4B may only open A5 as the next build gate.'),
        ];
    }

    /**
     * One readiness criterion.
     *
     * @param string $code
     * @param bool $pass
     * @param string $message
     * @param array $details
     * @return array
     */
    private static function criterion(string $code, bool $pass, string $message, array $details = []): array {
        return [
            'code' => $code,
            'pass' => $pass,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * Summarize readiness criteria.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $total = count($criteria);
        $passed = count(array_filter($criteria, static function(array $criterion): bool {
            return !empty($criterion['pass']);
        }));
        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
        ];
    }

    /**
     * File status for A4B.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'classes/local/candidate_activity_resolution_service.php',
            'activity_resolution.php',
            'cli/activity_resolution.php',
            'tests/candidate_activity_resolution_service_test.php',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            if (file_exists($CFG->dirroot . '/local/flwcupkp/' . $file)) {
                $present[$file] = true;
            } else {
                $missing[$file] = false;
            }
        }
        return [
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * Surface method status.
     *
     * @return array
     */
    private static function surface_status(): array {
        $class = self::class;
        $methods = [];
        foreach (['contract', 'policy', 'status', 'learner_resolution', 'class_summary'] as $method) {
            $methods[$class . '::' . $method] = method_exists($class, $method);
        }
        return [
            'class_exists' => class_exists($class),
            'methods' => $methods,
        ];
    }

    /**
     * Summarize dependency status.
     *
     * @param array $status
     * @return array
     */
    private static function dependency_summary(array $status): array {
        return [
            'type' => $status['type'] ?? '',
            'status' => $status['status'] ?? ($status['management_status'] ?? ''),
            'contract' => $status['contract']['version'] ?? ($status['contract'] ?? ''),
            'findings' => count($status['findings'] ?? []),
        ];
    }

    /**
     * Merge dependency and criterion findings.
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
                    'code' => $criterion['code'],
                    'message' => $criterion['message'],
                ];
            }
        }
        foreach ($dependencies as $dependency) {
            foreach (($dependency['findings'] ?? []) as $finding) {
                $findings[] = $finding;
            }
        }
        return self::dedupe_findings($findings);
    }

    /**
     * Deduplicate merged dependency findings.
     *
     * @param array $findings
     * @return array
     */
    private static function dedupe_findings(array $findings): array {
        $seen = [];
        $unique = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }
            $key = strtolower((string)($finding['code'] ?? '') . '|' .
                (string)($finding['message'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $finding;
        }
        return $unique;
    }

    /**
     * Safe dependency status wrapper.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return [
                'type' => 'DependencyError',
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'dependency_error',
                    'message' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Learner IDs for class summaries.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function learner_ids_for_scope(int $courseid, string $unitcode, int $frameworkid,
            int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $userids = [];
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        $users = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname ASC, u.firstname ASC, u.id ASC', 0,
            $limit, true);
        foreach ($users as $user) {
            $userids[(int)$user->id] = (int)$user->id;
        }

        $where = 'courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $goals = $DB->get_records_select('flwcupkp_goal', $where, $params, 'userid ASC', 'DISTINCT userid', 0,
            $limit);
        foreach ($goals as $goal) {
            $userids[(int)$goal->userid] = (int)$goal->userid;
        }

        sort($userids, SORT_NUMERIC);
        return array_slice(array_values($userids), 0, $limit);
    }

    /**
     * Learner identity for summaries.
     *
     * @param int $userid
     * @return array
     */
    private static function learner_identity(int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email',
            IGNORE_MISSING);
        return [
            'id' => $userid,
            'fullname' => $user ? fullname($user) : (string)$userid,
            'email' => $user ? (string)$user->email : '',
        ];
    }

    /**
     * Empty class summary.
     *
     * @return array
     */
    private static function empty_class_summary(): array {
        return [
            'learners' => 0,
            'skipped_unenrolled' => 0,
            'next_activity_ready' => 0,
            'diagnostic_required' => 0,
            'destination_ready' => 0,
            'fallback_used' => 0,
            'eligible_activities' => 0,
            'ineligible_activities' => 0,
            'candidate_targets' => 0,
            'statuses' => [],
        ];
    }

    /**
     * Normalize a target array.
     *
     * @param array $target
     * @return array
     */
    private static function normalize_target(array $target): array {
        $type = self::normalize_target_type((string)($target['type'] ?? ($target['targettype'] ?? '')));
        return [
            'type' => $type,
            'id' => (int)($target['id'] ?? ($target['targetid'] ?? 0)),
            'externalid' => (string)($target['externalid'] ?? ''),
            'title' => (string)($target['title'] ?? ''),
            'frameworkid' => (int)($target['frameworkid'] ?? 0),
        ];
    }

    /**
     * Normalize target type labels.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_target_type(string $type): string {
        $type = strtolower(trim($type));
        if (in_array($type, ['c', 'comp', 'competencies'], true)) {
            return 'competency';
        }
        if (in_array($type, ['usepoint', 'use_point'], true)) {
            return 'up';
        }
        if (in_array($type, ['knowledgepoint', 'knowledge_point'], true)) {
            return 'kp';
        }
        return $type;
    }

    /**
     * Stable target key.
     *
     * @param array $target
     * @return string
     */
    private static function target_key(array $target): string {
        $type = self::normalize_target_type((string)($target['type'] ?? ($target['targettype'] ?? '')));
        $id = (int)($target['id'] ?? ($target['targetid'] ?? 0));
        if ($type === '' || $id <= 0) {
            return '';
        }
        return $type . ':' . $id;
    }

    /**
     * Bound API limits.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }

    /**
     * Clean optional unit code.
     *
     * @param string $unitcode
     * @return string
     */
    private static function clean_unit_code_optional(string $unitcode): string {
        return clean_param(trim($unitcode), PARAM_ALPHANUMEXT);
    }
}

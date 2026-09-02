<?php
// Program 3 Gate F1 full integrated production validation.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/** Read-only integrated validation across Programs 1, 2, and 3. */
final class production_validation_service {
    public const GATE = 'P3_F1';
    public const CONTRACT_VERSION = 'FLW_CUPKP_ADAPTIVE_UX_V3_PRODUCTION_VALIDATION_V1';
    public const FINAL_REPORT = 'FLW_CUPKP_ADAPTIVE_UX_V3_FINAL_REPORT.md';

    /** Performance budgets are reporting thresholds, not hidden timeouts. */
    private const PERFORMANCE_BUDGETS_MS = [
        'history_queries' => 2000.0,
        'evidence_normalization' => 3000.0,
        'state_calculation' => 1000.0,
        'graph_traversal' => 2000.0,
        'eligibility' => 5000.0,
        'recommendation' => 7000.0,
        'timeline_render' => 12000.0,
        'teacher_view' => 15000.0,
    ];

    /** Fields required to reproduce a persisted recommendation. */
    private const REPRODUCIBILITY_FIELDS = [
        'goal_version',
        'curriculum_version',
        'evidence_policy',
        'mastery_policy',
        'retention_policy',
        'adaptive_policy',
        'progress_policy',
        'learner_state_snapshot',
        'eligibility_context',
    ];

    /** Return the frozen F1 contract. */
    public static function contract(): array {
        return [
            'type' => 'CupkpProductionValidationContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                management_v1_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
                history_evidence_adapter::CONTRACT_VERSION,
                mastery_state_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
                adaptive_path_engine_service::CONTRACT_VERSION,
                progress_goal_readiness_service::CONTRACT_VERSION,
                learner_experience_service::CONTRACT_VERSION,
                staff_intelligence_service::CONTRACT_VERSION,
            ],
            'end_to_end' => [
                'content_published',
                'moodle_deployed',
                'learner_acted',
                'history_captured',
                'evidence_interpreted',
                'mastery_updated',
                'retention_updated',
                'adaptive_decision',
                'eligibility_resolved',
                'recommendation_persisted',
                'dashboard_composed',
                'new_history_captured',
                'path_adapted',
            ],
            'historical_reproducibility_fields' => self::REPRODUCIBILITY_FIELDS,
            'ownership' => [
                'content_identity_and_deployment' => 'Program 1 through History V1 content identities',
                'source_history' => 'local_flwhistory',
                'gradebook' => 'Moodle core gradebook',
                'cupkp_semantics' => 'local_flwcupkp Foundation/Management/Evidence/Mastery services',
                'adaptive_policy' => 'local_flwcupkp A3/A4/A4B/A5 services',
                'learner_presentation' => learner_experience_service::CONTRACT_VERSION,
                'staff_governance' => staff_intelligence_service::CONTRACT_VERSION,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'performance_measures' => array_keys(self::PERFORMANCE_BUDGETS_MS),
            'production_ready_rule' => 'No failing invariant and no unresolved BLOCKER or HIGH finding.',
            'validator_read_only' => true,
            'write_boundary' => [],
            'final_gate' => true,
            'next_allowed_gate' => null,
            'final_report' => self::FINAL_REPORT,
        ];
    }

    /** Discover mapped scopes and orphaned deployment references without writing data. */
    public static function discover_scopes(): array {
        global $DB;

        $scopes = array_values($DB->get_records_sql(
            "SELECT MIN(o.id) AS id, o.courseid, o.unitcode, COUNT(1) AS objectcount,
                    c.shortname, c.fullname
               FROM {flwcupkp_object} o
          LEFT JOIN {course} c ON c.id = o.courseid
           GROUP BY o.courseid, o.unitcode, c.shortname, c.fullname
           ORDER BY o.courseid, o.unitcode"
        ));
        $out = [];
        foreach ($scopes as $scope) {
            $out[] = [
                'courseid' => (int)($scope->courseid ?? 0),
                'unitcode' => (string)($scope->unitcode ?? ''),
                'objectcount' => (int)$scope->objectcount,
                'course_exists' => !empty($scope->shortname),
                'shortname' => (string)($scope->shortname ?? ''),
                'fullname' => (string)($scope->fullname ?? ''),
            ];
        }
        return [
            'type' => 'CupkpProductionValidationScopeDiscovery',
            'gate' => self::GATE,
            'scopes' => $out,
            'valid_scopes' => count(array_filter($out, static function(array $scope): bool {
                return $scope['course_exists'];
            })),
            'orphan_scopes' => count(array_filter($out, static function(array $scope): bool {
                return !$scope['course_exists'];
            })),
            'read_only' => true,
        ];
    }

    /** Run the complete read-only F1 validation for one production scope. */
    public static function validate_scope(int $courseid, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100, bool $measureperformance = true): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('F1 production validation requires a course ID.');
        }
        $unitcode = self::clean_unit_code($unitcode);
        $frameworkid = max(0, $frameworkid);
        $limit = max(10, min(500, $limit));
        $before = self::mutation_counts();
        $findings = [];

        $deployment = self::deployment_status($courseid, $unitcode, $frameworkid, $findings);
        $learner = self::select_learner($courseid, $unitcode, $userid, $findings);
        $effectiveuserid = (int)($learner['userid'] ?? 0);
        $history = self::history_status($courseid, $effectiveuserid, $limit, $findings);
        $evidence = self::evidence_status($courseid, $unitcode, $effectiveuserid, $findings);
        $state = self::state_status($courseid, $unitcode, $frameworkid, $effectiveuserid, $limit, $findings);
        $adaptive = self::adaptive_status($courseid, $unitcode, $frameworkid, $effectiveuserid, $limit,
            $findings);
        $ux = self::ux_status($courseid, $unitcode, $frameworkid, $effectiveuserid, $limit, $findings);
        $reproducibility = self::historical_reproducibility($courseid, $unitcode, $effectiveuserid, $limit,
            $findings);
        $ownership = self::ownership_regression($findings);
        $security = self::security_privacy_status($findings);
        $invariants = self::invariant_status($findings);
        $pipeline = self::pipeline_status($deployment, $learner, $history, $evidence, $state, $adaptive, $ux,
            $reproducibility, $findings);
        $performance = $measureperformance ? self::performance_status(
            $courseid, $unitcode, $frameworkid, $effectiveuserid, $limit, $findings
        ) : ['measured' => false, 'metrics' => [], 'within_budget' => null];

        $after = self::mutation_counts();
        if ($before !== $after) {
            self::finding($findings, 'BLOCKER', 'validator_mutated_production_data',
                'The F1 validator changed learner or source-history data.', ['before' => $before, 'after' => $after]);
        }
        $summary = self::finding_summary($findings);
        $productionready = $summary['BLOCKER'] === 0 && $summary['HIGH'] === 0 &&
            !empty($invariants['pass']) && !empty($pipeline['complete']);

        return [
            'type' => 'CupkpFullIntegratedProductionValidation',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => $productionready ? 'production_ready' : 'not_production_ready',
            'production_ready' => $productionready,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'requested_userid' => max(0, $userid),
                'validated_userid' => $effectiveuserid,
            ],
            'deployment' => $deployment,
            'learner' => $learner,
            'history' => $history,
            'evidence' => $evidence,
            'learner_state' => $state,
            'adaptive' => $adaptive,
            'ux' => $ux,
            'pipeline' => $pipeline,
            'historical_reproducibility' => $reproducibility,
            'ownership_regression' => $ownership,
            'security_privacy' => $security,
            'invariants' => $invariants,
            'performance' => $performance,
            'findings_summary' => $summary,
            'findings' => $findings,
            'mutation_counts' => ['before' => $before, 'after' => $after, 'unchanged' => $before === $after],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'read_only' => true,
            'write_boundary' => [],
            'final_gate' => true,
            'next_allowed_gate' => null,
            'final_report' => self::FINAL_REPORT,
        ];
    }

    /** Validate Program 1 identity and Moodle deployment. */
    private static function deployment_status(int $courseid, string $unitcode, int $frameworkid,
            array &$findings): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname, visible', IGNORE_MISSING);
        if (!$course) {
            self::finding($findings, 'BLOCKER', 'course_missing',
                'The requested Moodle course does not exist.', ['courseid' => $courseid]);
        }
        $params = ['courseid' => $courseid];
        $where = 'courseid = :courseid';
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $objects = array_values($DB->get_records_select('flwcupkp_object', $where, $params, 'id ASC'));
        $objectids = array_map(static function(\stdClass $object): int {
            return (int)$object->id;
        }, $objects);
        $mappingcount = 0;
        $mappedobjects = [];
        if ($objectids) {
            [$insql, $inparams] = $DB->get_in_or_equal($objectids, SQL_PARAMS_NAMED, 'obj');
            $maps = $DB->get_records_select('flwcupkp_object_map', "objectid {$insql}", $inparams);
            $mappingcount = count($maps);
            foreach ($maps as $map) {
                $mappedobjects[(int)$map->objectid] = true;
            }
        }
        $invalidcmids = [];
        foreach ($objects as $object) {
            $cmid = (int)($object->cmid ?? 0);
            if ($cmid <= 0) {
                $invalidcmids[] = ['objectid' => (int)$object->id, 'cmid' => 0, 'reason' => 'missing_cmid'];
                continue;
            }
            $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, deletioninprogress',
                IGNORE_MISSING);
            if (!$cm || (int)$cm->course !== $courseid || !empty($cm->deletioninprogress)) {
                $invalidcmids[] = [
                    'objectid' => (int)$object->id,
                    'cmid' => $cmid,
                    'reason' => !$cm ? 'cmid_missing' : 'cmid_outside_scope_or_deleted',
                ];
            }
        }
        $identities = [];
        if (class_exists('\\local_flwhistory\\local\\evidence_source_adapter')) {
            $identitypayload = \local_flwhistory\local\evidence_source_adapter::content_identities_for_course(
                $courseid, 500, 0
            );
            $identities = is_array($identitypayload['records'] ?? null) ? $identitypayload['records'] : [];
        }
        if (!$objects) {
            self::finding($findings, 'BLOCKER', 'no_deployed_cupkp_objects',
                'No C-UP-KP learning objects are linked to this Moodle course/unit.');
        }
        if ($objects && count($mappedobjects) < count($objects)) {
            self::finding($findings, 'BLOCKER', 'unmapped_deployed_objects',
                'One or more deployed C-UP-KP objects have no semantic target mapping.', [
                    'objects' => count($objects), 'mapped_objects' => count($mappedobjects),
                ]);
        }
        if ($invalidcmids) {
            self::finding($findings, 'BLOCKER', 'invalid_deployed_cmids',
                'One or more C-UP-KP objects do not resolve to an active module in the requested course.',
                ['invalid' => $invalidcmids]);
        }
        if (!$identities) {
            self::finding($findings, 'HIGH', 'missing_program1_content_identities',
                'History V1 has no resolved Program 1 content identity for the requested course.');
        }
        $discovery = self::discover_scopes();
        if ($discovery['orphan_scopes'] > 0) {
            self::finding($findings, 'HIGH', 'orphaned_cupkp_course_scopes',
                'C-UP-KP objects still reference deleted Moodle courses.', [
                    'orphan_scopes' => array_values(array_filter($discovery['scopes'],
                        static function(array $scope): bool { return !$scope['course_exists']; })),
                ]);
        }
        return [
            'course_exists' => (bool)$course,
            'course' => $course ? [
                'id' => (int)$course->id,
                'shortname' => (string)$course->shortname,
                'fullname' => (string)$course->fullname,
                'visible' => (bool)$course->visible,
            ] : null,
            'objects' => count($objects),
            'object_mappings' => $mappingcount,
            'mapped_objects' => count($mappedobjects),
            'valid_cmids' => count($objects) - count($invalidcmids),
            'invalid_cmids' => $invalidcmids,
            'program1_content_identities' => count($identities),
            'identity_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'scope_discovery' => $discovery,
            'pass' => (bool)$course && $objects && count($mappedobjects) === count($objects) &&
                !$invalidcmids && !empty($identities),
        ];
    }

    /** Select the requested or best evidence-bearing enrolled learner. */
    private static function select_learner(int $courseid, string $unitcode, int $requesteduserid,
            array &$findings): array {
        global $DB;

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return ['userid' => 0, 'selection' => 'course_missing', 'enrolled' => false];
        }
        if ($requesteduserid > 0) {
            $enrolled = is_enrolled($context, $requesteduserid, '', true);
            if (!$enrolled) {
                self::finding($findings, 'BLOCKER', 'requested_learner_not_enrolled',
                    'The requested F1 learner is not actively enrolled in the course.',
                    ['userid' => $requesteduserid]);
            }
            return ['userid' => $requesteduserid, 'selection' => 'requested', 'enrolled' => $enrolled];
        }
        $users = get_enrolled_users($context, '', 0,
            'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, ' .
            'u.alternatename, u.email', 'u.id ASC');
        $evidenceusers = [];
        if ($unitcode !== '') {
            $evidenceusers = $DB->get_fieldset_select('flwcupkp_evidence', 'DISTINCT userid',
                'courseid = :courseid AND unitcode = :unitcode', ['courseid' => $courseid, 'unitcode' => $unitcode]);
        }
        $historyusers = [];
        if (class_exists('\\local_flwhistory\\local\\evidence_source_adapter')) {
            $attempts = \local_flwhistory\local\evidence_source_adapter::attempts_for_course($courseid, 500, 0);
            $historyusers = array_map(static function(array $row): int {
                return (int)($row['userid'] ?? 0);
            }, $attempts['records'] ?? []);
        }
        $priority = array_values(array_unique(array_filter(array_merge(
            array_map('intval', $evidenceusers), $historyusers
        ))));
        foreach ($priority as $candidateid) {
            if (isset($users[$candidateid])) {
                return ['userid' => $candidateid, 'selection' => 'evidence_or_history', 'enrolled' => true,
                    'fullname' => fullname($users[$candidateid])];
            }
        }
        foreach ($users as $user) {
            if (!has_capability('local/flwcupkp:viewreports', $context, (int)$user->id)) {
                return ['userid' => (int)$user->id, 'selection' => 'enrolled_non_staff', 'enrolled' => true,
                    'fullname' => fullname($user)];
            }
        }
        self::finding($findings, 'BLOCKER', 'no_validated_learner',
            'No enrolled learner is available for the end-to-end F1 validation.');
        return ['userid' => 0, 'selection' => 'none', 'enrolled' => false, 'enrolled_users' => count($users)];
    }

    /** Query trusted History V1 facts only. */
    private static function history_status(int $courseid, int $userid, int $limit, array &$findings): array {
        $contract = history_v1_consumer_contract::contract_status($courseid, 1);
        $facts = self::history_facts($courseid, $userid, $limit);
        // Gradebook source events can be emitted by course restore and do not prove learner activity.
        $activityfacts = count($facts['attempts']) + count($facts['completion']);
        if (($contract['status'] ?? '') !== 'ready') {
            self::finding($findings, 'BLOCKER', 'history_v1_not_ready',
                'The frozen History V1 downstream contract is not ready.', ['status' => $contract['status'] ?? '']);
        }
        if ($userid > 0 && $activityfacts === 0) {
            self::finding($findings, 'BLOCKER', 'no_learner_history_fact',
                'The selected learner has no trusted activity, attempt, or completion fact in History V1.');
        }
        return [
            'contract_status' => $contract['status'] ?? 'blocked',
            'contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'source_events' => count($facts['source_events']),
            'attempts' => count($facts['attempts']),
            'grades' => count($facts['grades']),
            'completion' => count($facts['completion']),
            'placement' => count($facts['placement']),
            'content_identities' => count($facts['content_identities']),
            'activity_facts' => $activityfacts,
            'pass' => ($contract['status'] ?? '') === 'ready' && ($userid <= 0 || $activityfacts > 0),
        ];
    }

    /** Read History V1 contract payloads, then filter to the selected learner. */
    private static function history_facts(int $courseid, int $userid, int $limit): array {
        if (!class_exists('\\local_flwhistory\\local\\evidence_source_adapter')) {
            return array_fill_keys(['source_events', 'attempts', 'grades', 'completion', 'placement',
                'content_identities'], []);
        }
        $adapter = '\\local_flwhistory\\local\\evidence_source_adapter';
        $payloads = [
            'source_events' => $adapter::source_events_for_course($courseid, $limit, 0),
            'attempts' => $adapter::attempts_for_course($courseid, $limit, 0),
            'grades' => $adapter::grades_for_course($courseid, $limit, 0),
            'completion' => $adapter::completions_for_course($courseid, $limit, 0),
            'placement' => $adapter::placements_for_course($courseid, $limit, 0),
            'content_identities' => $adapter::content_identities_for_course($courseid, $limit, 0),
        ];
        $facts = [];
        foreach ($payloads as $type => $payload) {
            $facts[$type] = is_array($payload['records'] ?? null) ? $payload['records'] : [];
        }
        if ($userid > 0) {
            foreach (['source_events', 'attempts', 'grades', 'completion', 'placement'] as $type) {
                $facts[$type] = array_values(array_filter($facts[$type], static function(array $row) use ($userid): bool {
                    return (int)($row['userid'] ?? 0) === $userid;
                }));
            }
        }
        return $facts;
    }

    /** Validate derived evidence and History V1 provenance. */
    private static function evidence_status(int $courseid, string $unitcode, int $userid,
            array &$findings): array {
        global $DB;

        $params = ['courseid' => $courseid];
        $where = 'courseid = :courseid';
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($userid > 0) {
            $where .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }
        $rows = array_values($DB->get_records_select('flwcupkp_evidence', $where, $params,
            'timecreated DESC, id DESC'));
        $historybacked = array_filter($rows, static function(\stdClass $row): bool {
            return strpos((string)($row->provenance ?? ''), 'history_v1') !== false ||
                strpos((string)($row->sourceattempt ?? ''), 'history_v1:') === 0;
        });
        if ($userid > 0 && !$rows) {
            self::finding($findings, 'BLOCKER', 'no_interpreted_cupkp_evidence',
                'No C-UP-KP evidence has been interpreted for the selected learner and scope.');
        } else if ($userid > 0 && !$historybacked) {
            self::finding($findings, 'HIGH', 'no_history_v1_backed_evidence',
                'The selected learner has evidence, but none is traceable to the frozen History V1 contract.');
        }
        return [
            'evidence' => count($rows),
            'history_v1_backed' => count($historybacked),
            'pass' => $userid > 0 && !empty($rows) && !empty($historybacked),
        ];
    }

    /** Validate mastery and retention state. */
    private static function state_status(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, array &$findings): array {
        if ($userid <= 0) {
            return ['states' => 0, 'retention_states' => 0, 'pass' => false];
        }
        $current = self::safe_call(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return mastery_state_service::current_learner_state(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            );
        });
        $states = array_values(array_filter($current['value']['states'] ?? [], static function(array $state): bool {
            return (int)($state['evidence']['count'] ?? 0) > 0;
        }));
        $retention = self::safe_call(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return retention_review_service::current_retention_state(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            );
        });
        $retentionstates = array_values(array_filter($retention['value']['states'] ?? [],
            static function(array $state): bool {
                return (int)($state['evidence']['count'] ?? 0) > 0;
            }));
        if (!$states) {
            self::finding($findings, 'BLOCKER', 'no_current_mastery_state',
                'No current C-UP-KP learner state has been calculated from the interpreted evidence.');
        }
        if (!$retention['ok'] || !$retentionstates) {
            self::finding($findings, 'HIGH', 'no_retention_state',
                'Retention/retrieval state is unavailable for the selected learner.',
                ['error' => $retention['error']]);
        }
        return [
            'states' => count($states),
            'retention_states' => count($retentionstates),
            'mastery_error' => $current['error'],
            'retention_error' => $retention['error'],
            'pass' => $current['ok'] && !empty($states) && $retention['ok'] && !empty($retentionstates),
        ];
    }

    /** Validate adaptive decision, eligibility, and recommendation persistence. */
    private static function adaptive_status(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, array &$findings): array {
        if ($userid <= 0) {
            return ['path_available' => false, 'eligible_activities' => 0, 'recommendations' => 0,
                'pass' => false];
        }
        $path = self::safe_call(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return adaptive_path_engine_service::learner_path(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            );
        });
        $recommendations = adaptive_path_engine_service::current_recommendations(
            $userid, $courseid, $unitcode, min(100, $limit)
        );
        $resolution = is_array($path['value']['source_activity_resolution'] ?? null) ?
            $path['value']['source_activity_resolution'] : [];
        $eligible = array_values($resolution['eligible_activities'] ?? []);
        if (!$path['ok']) {
            self::finding($findings, 'BLOCKER', 'adaptive_path_unavailable',
                'The continuous adaptive path could not be composed.', ['error' => $path['error']]);
        }
        if (!$eligible) {
            self::finding($findings, 'BLOCKER', 'no_eligible_next_activity',
                'A4B found no learner-accessible Moodle activity for the adaptive path.');
        }
        if (!$recommendations) {
            self::finding($findings, 'BLOCKER', 'no_persisted_adaptive_recommendation',
                'No current A5 recommendation has been persisted for the selected learner.');
        }
        return [
            'path_available' => $path['ok'],
            'path_status' => (string)($path['value']['path_status'] ?? ''),
            'action' => (string)($path['value']['recommendation']['action'] ?? ''),
            'eligible_activities' => count($eligible),
            'recommendations' => count($recommendations),
            'error' => $path['error'],
            'pass' => $path['ok'] && !empty($eligible) && !empty($recommendations),
        ];
    }

    /** Validate UX1, UX2, and UX3 composition without rendering staff controls to learners. */
    private static function ux_status(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, array &$findings): array {
        if ($userid <= 0) {
            return ['timeline' => false, 'learner_experience' => false, 'staff_intelligence' => false,
                'pass' => false];
        }
        $timeline = self::safe_call(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return student_learning_timeline_view_service::learner_timeline(
                $userid, $courseid, $unitcode, $frameworkid, min(50, $limit)
            );
        });
        $learner = self::safe_call(static function() use ($userid, $courseid, $unitcode,
                $frameworkid): array {
            return learner_experience_service::learner_experience(
                $userid, $courseid, $unitcode, $frameworkid, 10
            );
        });
        $staff = self::safe_call(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return staff_intelligence_service::learner_intelligence(
                $userid, $courseid, $unitcode, $frameworkid, min(100, $limit)
            );
        });
        foreach (['timeline' => $timeline, 'learner_experience' => $learner, 'staff_intelligence' => $staff]
                as $name => $result) {
            if (!$result['ok']) {
                self::finding($findings, 'HIGH', 'ux_component_unavailable_' . $name,
                    'An integrated learner/staff presentation component is unavailable.',
                    ['component' => $name, 'error' => $result['error']]);
            }
        }
        return [
            'timeline' => $timeline['ok'],
            'learner_experience' => $learner['ok'],
            'staff_intelligence' => $staff['ok'],
            'learner_view_read_only' => !empty($learner['value']['read_only']),
            'staff_view_read_only' => !empty($staff['value']['read_only']),
            'errors' => [
                'timeline' => $timeline['error'],
                'learner_experience' => $learner['error'],
                'staff_intelligence' => $staff['error'],
            ],
            'pass' => $timeline['ok'] && $learner['ok'] && $staff['ok'],
        ];
    }

    /** Verify every persisted A5 recommendation carries the frozen replay snapshot. */
    private static function historical_reproducibility(int $courseid, string $unitcode, int $userid,
            int $limit, array &$findings): array {
        global $DB;

        $params = [
            'courseid' => $courseid,
            'policyversion' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
        ];
        $where = 'courseid = :courseid AND policyversion = :policyversion';
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($userid > 0) {
            $where .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }
        $rows = array_values($DB->get_records_select('flwcupkp_recommend', $where, $params,
            'timecreated DESC, id DESC', '*', 0, min(100, $limit)));
        $reports = [];
        foreach ($rows as $row) {
            $details = json_decode((string)($row->prereqinfo ?? ''), true);
            $details = is_array($details) ? $details : [];
            $snapshot = is_array($details['snapshot'] ?? null) ? $details['snapshot'] : [];
            $policies = is_array($snapshot['policy_versions'] ?? null) ? $snapshot['policy_versions'] : [];
            $checks = [
                'goal_version' => !empty($snapshot['goal_version']['currentversion']) &&
                    !empty($snapshot['goal_version']['checksum']),
                'curriculum_version' => !empty($snapshot['curriculum_version']['management_contract']) &&
                    !empty($snapshot['curriculum_version']['foundation_contract']),
                'evidence_policy' => !empty($policies['evidence_policy']),
                'mastery_policy' => !empty($policies['mastery_policy']),
                'retention_policy' => !empty($policies['retention_policy']),
                'adaptive_policy' => !empty($policies['adaptive_policy']) &&
                    !empty($policies['adaptive_path_policy']),
                'progress_policy' => ($policies['progress_policy'] ?? '') ===
                    progress_goal_readiness_service::PROGRESS_POLICY_VERSION,
                'learner_state_snapshot' => array_key_exists('state_snapshot', $snapshot),
                'eligibility_context' => !empty($snapshot['candidate_summary']['resolution_hash']) &&
                    array_key_exists('selected_activity', $snapshot),
            ];
            $missing = array_keys(array_filter($checks, static function(bool $pass): bool { return !$pass; }));
            $reports[] = [
                'recommendationid' => (int)$row->id,
                'status' => (string)$row->status,
                'sourcehash' => (string)($row->sourcehash ?? ''),
                'checks' => $checks,
                'complete' => !$missing,
                'missing' => $missing,
                'policy_versions' => $policies,
                'staff_intervention' => $snapshot['staff_intervention'] ?? null,
                'timecreated' => (int)$row->timecreated,
            ];
        }
        $complete = count(array_filter($reports, static function(array $row): bool { return $row['complete']; }));
        $hashes = array_values(array_unique(array_filter(array_map(static function(array $row): string {
            return $row['sourcehash'];
        }, $reports))));
        $superseded = count(array_filter($reports, static function(array $row): bool {
            return $row['status'] === 'superseded';
        }));
        if (!$reports) {
            self::finding($findings, 'BLOCKER', 'no_historical_recommendation',
                'No A5 recommendation exists for historical reproducibility validation.');
        } else if ($complete !== count($reports)) {
            self::finding($findings, 'HIGH', 'incomplete_recommendation_snapshot',
                'One or more historical recommendations cannot reproduce all required decision inputs.', [
                    'incomplete' => count($reports) - $complete,
                ]);
        }
        if (count($reports) < 2 || $superseded < 1 || count($hashes) < 2) {
            self::finding($findings, 'HIGH', 'path_adaptation_not_demonstrated',
                'The scope does not yet contain two distinct recommendation states with a preserved superseded row.');
        }
        return [
            'required_fields' => self::REPRODUCIBILITY_FIELDS,
            'recommendations' => count($reports),
            'complete' => $complete,
            'incomplete' => count($reports) - $complete,
            'superseded' => $superseded,
            'distinct_source_hashes' => count($hashes),
            'rows' => $reports,
            'pass' => count($reports) >= 2 && $complete === count($reports) && $superseded >= 1 &&
                count($hashes) >= 2,
        ];
    }

    /** Verify the final ownership model has not been duplicated. */
    private static function ownership_regression(array &$findings): array {
        global $DB;

        $dbman = $DB->get_manager();
        $checks = [
            'history_owner' => class_exists('\\local_flwhistory\\local\\evidence_source_adapter') &&
                history_v1_consumer_contract::CONSUMPTION_RULE === 'use_history_v1_adapter_not_raw_moodle_logs',
            'content_registry_not_duplicated' => !$dbman->table_exists('flwcupkp_content_registry'),
            'history_store_not_duplicated' => !$dbman->table_exists('flwcupkp_history'),
            'gradebook_not_duplicated' => $dbman->table_exists('grade_grades') &&
                !$dbman->table_exists('flwcupkp_gradebook'),
            'cupkp_engine_not_duplicated' => class_exists(mastery_engine::class) &&
                $dbman->table_exists('flwcupkp_state'),
            'adaptive_engine_not_duplicated' => class_exists(adaptive_path_engine_service::class) &&
                $dbman->table_exists('flwcupkp_recommend'),
        ];
        foreach ($checks as $code => $pass) {
            if (!$pass) {
                self::finding($findings, 'BLOCKER', 'ownership_' . $code,
                    'A final ownership or duplication invariant failed.', ['check' => $code]);
            }
        }
        return [
            'checks' => $checks,
            'passed' => count(array_filter($checks)),
            'total' => count($checks),
            'pass' => !in_array(false, $checks, true),
            'owners' => self::contract()['ownership'],
        ];
    }

    /** Verify static role, API, privacy, and learner/staff presentation boundaries. */
    private static function security_privacy_status(array &$findings): array {
        global $CFG, $DB;

        $capabilities = [
            'local/flwcupkp:viewlearnerpath',
            'local/flwcupkp:viewreports',
            'local/flwcupkp:manageframeworks',
            'local/flwcupkp:override',
        ];
        $capabilitychecks = [];
        foreach ($capabilities as $capability) {
            $capabilitychecks[$capability] = $DB->record_exists('capabilities', ['name' => $capability]);
        }
        $externalchecks = [];
        foreach ([
            'local_flwcupkp_get_staff_intelligence' => 'local/flwcupkp:viewreports',
            'local_flwcupkp_apply_staff_intervention' => 'local/flwcupkp:override',
            'local_flwcupkp_release_staff_intervention' => 'local/flwcupkp:override',
        ] as $function => $capability) {
            $externalchecks[$function] = $DB->record_exists('external_functions', [
                'name' => $function,
                'capabilities' => $capability,
            ]);
        }
        $staffpage = @file_get_contents($CFG->dirroot . '/local/flwcupkp/staff_intelligence.php');
        $learnerrenderer = @file_get_contents(
            $CFG->dirroot . '/local/flwcupkp/classes/local/learner_experience_renderer.php'
        );
        $privacyclass = '\\local_flwcupkp\\privacy\\provider';
        $checks = [
            'capabilities_registered' => !in_array(false, $capabilitychecks, true),
            'external_functions_scoped' => !in_array(false, $externalchecks, true),
            'staff_page_requires_reports' => is_string($staffpage) &&
                strpos($staffpage, "require_capability('local/flwcupkp:viewreports'") !== false,
            'staff_writes_require_override' => is_string($staffpage) &&
                strpos($staffpage, "local/flwcupkp:override") !== false,
            'learner_renderer_has_no_staff_controls' => is_string($learnerrenderer) &&
                strpos($learnerrenderer, 'staff_intelligence') === false &&
                strpos($learnerrenderer, 'staffintervention') === false,
            'privacy_provider_registered' => class_exists($privacyclass) &&
                is_subclass_of($privacyclass, '\\core_privacy\\local\\metadata\\provider'),
        ];
        foreach ($checks as $code => $pass) {
            if (!$pass) {
                self::finding($findings, 'BLOCKER', 'security_privacy_' . $code,
                    'A learner/teacher/admin or privacy boundary check failed.', ['check' => $code]);
            }
        }
        return [
            'checks' => $checks,
            'capabilities' => $capabilitychecks,
            'external_functions' => $externalchecks,
            'passed' => count(array_filter($checks)),
            'total' => count($checks),
            'pass' => !in_array(false, $checks, true),
        ];
    }

    /** Run deterministic detector self-tests and a bounded clean suite. */
    private static function invariant_status(array &$findings): array {
        $selftest = trajectory_invariant_service::detector_self_test();
        $suite = trajectory_invariant_service::simulate_suite('f1-production-validation', 64, 24);
        $suitepass = ($suite['status'] ?? '') === 'passed';
        $pass = !empty($selftest['pass']) && $suitepass;
        if (!$pass) {
            self::finding($findings, 'BLOCKER', 'adaptive_invariant_failure',
                'One or more adaptive trajectory invariants failed.', [
                    'detector_self_test' => $selftest,
                    'suite_summary' => $suite['summary'] ?? [],
                ]);
        }
        return [
            'pass' => $pass,
            'detector_self_test' => [
                'pass' => !empty($selftest['pass']),
                'passed' => (int)($selftest['passed'] ?? 0),
                'total' => (int)($selftest['total'] ?? 0),
            ],
            'trajectory_suite' => [
                'pass' => $suitepass,
                'trajectories' => (int)($suite['summary']['trajectories'] ?? 0),
                'steps' => (int)($suite['summary']['simulated_steps'] ?? 0),
                'incidents' => (int)($suite['summary']['failed'] ?? 0),
            ],
        ];
    }

    /** Assemble the 13-step F1 pipeline and add phase findings. */
    private static function pipeline_status(array $deployment, array $learner, array $history, array $evidence,
            array $state, array $adaptive, array $ux, array $reproducibility, array &$findings): array {
        $steps = [
            'content_published' => !empty($deployment['objects']) &&
                !empty($deployment['program1_content_identities']),
            'moodle_deployed' => !empty($deployment['course_exists']) &&
                $deployment['valid_cmids'] === $deployment['objects'] && $deployment['objects'] > 0,
            'learner_acted' => !empty($learner['enrolled']) && $history['activity_facts'] > 0,
            'history_captured' => !empty($history['pass']),
            'evidence_interpreted' => !empty($evidence['pass']),
            'mastery_updated' => $state['states'] > 0,
            'retention_updated' => $state['retention_states'] > 0,
            'adaptive_decision' => !empty($adaptive['path_available']),
            'eligibility_resolved' => $adaptive['eligible_activities'] > 0,
            'recommendation_persisted' => $adaptive['recommendations'] > 0,
            'dashboard_composed' => !empty($ux['pass']),
            'new_history_captured' => $reproducibility['recommendations'] >= 2,
            'path_adapted' => !empty($reproducibility['pass']),
        ];
        foreach ($steps as $step => $pass) {
            if (!$pass && !self::has_finding($findings, 'pipeline_' . $step)) {
                self::finding($findings, in_array($step, ['new_history_captured', 'path_adapted'], true) ?
                    'HIGH' : 'BLOCKER', 'pipeline_' . $step,
                    'The integrated F1 pipeline step is not demonstrated.', ['step' => $step]);
            }
        }
        return [
            'steps' => $steps,
            'passed' => count(array_filter($steps)),
            'total' => count($steps),
            'complete' => !in_array(false, $steps, true),
        ];
    }

    /** Measure all eight required operations without changing source or learner records. */
    private static function performance_status(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, array &$findings): array {
        global $DB;

        $metrics = [];
        $metrics['history_queries'] = self::measure(static function() use ($courseid, $userid, $limit): array {
            return self::history_facts($courseid, $userid, $limit);
        }, self::PERFORMANCE_BUDGETS_MS['history_queries']);
        $metrics['evidence_normalization'] = self::measure(static function() use ($courseid, $unitcode,
                $frameworkid, $limit): array {
            return history_evidence_adapter::preview_reprocess(
                $courseid, $unitcode, $frameworkid, ['attempts', 'grades', 'completion'], $limit, 0
            );
        }, self::PERFORMANCE_BUDGETS_MS['evidence_normalization']);
        $metrics['state_calculation'] = self::measure(static function() use ($DB, $userid): array {
            $states = $userid > 0 ? $DB->get_records('flwcupkp_state', ['userid' => $userid],
                'id ASC', 'targettype, targetid', 0, 1) : [];
            $state = $states ? reset($states) : null;
            $evidence = $state ? array_values($DB->get_records('flwcupkp_evidence', [
                'userid' => $userid,
                'targettype' => (string)$state->targettype,
                'targetid' => (int)$state->targetid,
            ])) : [];
            return mastery_engine::calculate((string)($state->targettype ?? 'kp'), $evidence);
        }, self::PERFORMANCE_BUDGETS_MS['state_calculation']);
        $metrics['graph_traversal'] = self::measure(static function() use ($courseid, $frameworkid, $limit): array {
            return relationship_graph_contract::graph_status($courseid, $frameworkid, $limit);
        }, self::PERFORMANCE_BUDGETS_MS['graph_traversal']);
        $metrics['eligibility'] = self::measure(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return $userid > 0 ? candidate_activity_resolution_service::learner_resolution(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            ) : candidate_activity_resolution_service::status($courseid, $unitcode, $frameworkid, $limit);
        }, self::PERFORMANCE_BUDGETS_MS['eligibility']);
        $metrics['recommendation'] = self::measure(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return $userid > 0 ? adaptive_path_engine_service::learner_path(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            ) : adaptive_path_engine_service::status($courseid, $unitcode, $frameworkid, $limit);
        }, self::PERFORMANCE_BUDGETS_MS['recommendation']);
        $metrics['timeline_render'] = self::measure(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return $userid > 0 ? student_learning_timeline_view_service::learner_timeline(
                $userid, $courseid, $unitcode, $frameworkid, min(50, $limit)
            ) : student_learning_timeline_view_service::status($courseid, $unitcode, $frameworkid);
        }, self::PERFORMANCE_BUDGETS_MS['timeline_render']);
        $metrics['teacher_view'] = self::measure(static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return $userid > 0 ? staff_intelligence_service::learner_intelligence(
                $userid, $courseid, $unitcode, $frameworkid, min(100, $limit)
            ) : staff_intelligence_service::status($courseid, $unitcode, $frameworkid);
        }, self::PERFORMANCE_BUDGETS_MS['teacher_view']);
        foreach ($metrics as $name => $metric) {
            if (!$metric['ok']) {
                self::finding($findings, 'HIGH', 'performance_measure_failed_' . $name,
                    'A required F1 performance operation could not be measured.',
                    ['metric' => $name, 'error' => $metric['error']]);
            } else if (!$metric['within_budget']) {
                self::finding($findings, 'HIGH', 'performance_budget_exceeded_' . $name,
                    'A required F1 operation exceeded its reporting budget.', [
                        'metric' => $name,
                        'duration_ms' => $metric['duration_ms'],
                        'budget_ms' => $metric['budget_ms'],
                    ]);
            }
        }
        return [
            'measured' => true,
            'metrics' => $metrics,
            'within_budget' => count(array_filter($metrics, static function(array $metric): bool {
                return $metric['ok'] && $metric['within_budget'];
            })) === count($metrics),
        ];
    }

    /** Measure one operation and retain errors as validation evidence. */
    private static function measure(callable $callback, float $budgetms): array {
        $start = hrtime(true);
        try {
            $callback();
            $ok = true;
            $error = null;
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }
        $duration = round((hrtime(true) - $start) / 1000000, 3);
        return [
            'duration_ms' => $duration,
            'budget_ms' => $budgetms,
            'ok' => $ok,
            'within_budget' => $ok && $duration <= $budgetms,
            'error' => $error,
        ];
    }

    /** Catch one component read without hiding its validation failure. */
    private static function safe_call(callable $callback): array {
        try {
            return ['ok' => true, 'value' => $callback(), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'value' => [], 'error' => $e->getMessage()];
        }
    }

    /** Record a structured F1 finding. */
    private static function finding(array &$findings, string $severity, string $code, string $message,
            array $evidence = []): void {
        $findings[] = [
            'severity' => strtoupper($severity),
            'code' => $code,
            'message' => $message,
            'evidence' => $evidence,
        ];
    }

    /** Whether a finding code already exists. */
    private static function has_finding(array $findings, string $code): bool {
        foreach ($findings as $finding) {
            if (($finding['code'] ?? '') === $code) {
                return true;
            }
        }
        return false;
    }

    /** Count findings by frozen severity. */
    private static function finding_summary(array $findings): array {
        $summary = ['BLOCKER' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0, 'total' => count($findings)];
        foreach ($findings as $finding) {
            $severity = strtoupper((string)($finding['severity'] ?? 'LOW'));
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }
        return $summary;
    }

    /** Counts that prove validation remains read-only. */
    private static function mutation_counts(): array {
        global $DB;

        $tables = [
            'flwhist_source_event',
            'flwhist_attempt',
            'flwhist_grade_version',
            'flwhist_completion',
            'flwcupkp_evidence',
            'flwcupkp_state',
            'flwcupkp_recommend',
            'flwcupkp_goal',
            'flwcupkp_intervention',
            'flwcupkp_audit',
        ];
        $counts = [];
        foreach ($tables as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $counts[$table] = $DB->count_records($table);
            }
        }
        return $counts;
    }

    /** Clean optional unit scope. */
    private static function clean_unit_code(string $unitcode): string {
        return substr((string)preg_replace('/[^A-Za-z0-9_-]/', '', trim($unitcode)), 0, 40);
    }
}

<?php
// PHPUnit tests for Program 3 Gate UX1 timeline composition.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the read-only Past, Present, and Future presentation boundary.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\student_learning_timeline_view_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\student_learning_timeline_renderer::class)]
class student_learning_timeline_view_service_test extends \advanced_testcase {
    public function test_contract_and_status_freeze_ux1_ownership_and_stop_boundary(): void {
        $this->resetAfterTest(true);
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\student_learning_timeline_view_service::contract();
        $status = \local_flwcupkp\local\student_learning_timeline_view_service::status();

        $this->assertSame('P3_UX1', $contract['gate']);
        $this->assertSame('FLW_CUPKP_STUDENT_LEARNING_TIMELINE_VIEW_V1', $contract['version']);
        $this->assertSame(['past', 'present', 'future'], array_keys($contract['composition']));
        $this->assertSame('local_flwhistory', $contract['composition']['past']['owner']);
        $this->assertSame([
            'grade_history', 'learning_history', 'recent_activity', 'attempt_history', 'journey',
        ], $contract['composition']['past']['panels']);
        $this->assertSame('local_flwcupkp', $contract['composition']['present']['owner']);
        $this->assertSame('local_flwcupkp', $contract['composition']['future']['owner']);
        $this->assertTrue($contract['read_only']);
        $this->assertSame([], $contract['write_boundary']);
        $this->assertSame('UX2', $contract['next_allowed_gate']);

        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(9, $status['criteria_summary']['total']);
        $this->assertSame(9, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertTrue($status['surface']['history_dashboard_service']);
        $this->assertTrue($status['surface']['history_dashboard_renderer']);
        $this->assertTrue($status['files']['present']['learning_timeline.php']);
        $this->assertTrue($status['files']['present']['cli/learning_timeline.php']);
        $this->assertSame('UX2', $status['next_allowed_gate']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_compose_preserves_history_dashboard_and_exposes_only_compact_program3_views(): void {
        $history = $this->history_fixture();
        $progress = $this->progress_fixture();
        $adaptive = $this->adaptive_fixture();
        $recommendations = [
            'records' => [[
                'id' => 17,
                'action' => 'REVIEW',
                'reason' => 'Retrieval is due.',
                'target' => ['available' => true, 'type' => 'kp', 'id' => 41,
                    'externalid' => 'KP-041', 'title' => 'Retrieve a key detail'],
                'activity' => ['available' => true, 'objectid' => 51, 'cmid' => 61,
                    'title' => 'Retrieval practice', 'modname' => 'quiz', 'url' => '/mod/quiz/view.php?id=61'],
                'time' => 123456,
            ]],
            'why_path_changed' => [
                'status' => 'available',
                'changed' => true,
                'dimensions' => ['learner_state'],
                'reason' => 'The current learner state changed.',
            ],
        ];

        $view = \local_flwcupkp\local\student_learning_timeline_view_service::compose(
            $history, $progress, $adaptive, $recommendations
        );

        $this->assertSame('StudentLearningTimelineView', $view['type']);
        $this->assertSame('LearnerHistoryDashboardCore', $view['past']['dashboard_contract']);
        $this->assertSame($history['grade_history'], $view['past']['dashboard']['grade_history']);
        $this->assertSame([], $view['past']['dashboard']['program3_placeholders']);
        $this->assertSame('local_flwhistory', $view['past']['owner']);
        $this->assertSame('local_flwcupkp', $view['present']['owner']);
        $this->assertSame(62.5, $view['present']['metrics']['mastery_progress']['percentage']);
        $this->assertCount(1, $view['present']['skill_states']);
        $this->assertSame('KP-041', $view['present']['skill_states'][0]['target']['externalid']);
        $this->assertSame('REVIEW', $view['future']['adaptive_next']['action']);
        $this->assertSame(61, $view['future']['adaptive_next']['activity']['cmid']);
        $this->assertCount(2, $view['future']['projected_roadmap']);
        $this->assertSame(['learner_state'], $view['future']['why_path_changed']['dimensions']);
        $this->assertTrue($view['read_only']);
        $this->assertSame([], $view['write_boundary']);

        foreach (['relationship_edges', 'prerequisites', 'raw_graph', 'target_resolutions',
                'eligible_activities', 'ineligible_activities'] as $forbidden) {
            $this->assertFalse($this->contains_key($view['present'], $forbidden), $forbidden);
            $this->assertFalse($this->contains_key($view['future'], $forbidden), $forbidden);
        }
    }

    public function test_compose_keeps_qualitative_goal_milestone_when_percentage_is_not_defensible(): void {
        $progress = $this->progress_fixture();
        $progress['progress']['preferred_learner_metric'] = [
            'metric' => 'qualitative_milestone',
            'percentage' => null,
            'milestone' => 'GOAL_NOT_SET',
            'reason' => 'No bounded destination has been selected.',
        ];
        $progress['progress']['goal_achievement'] = [
            'achieved' => false,
            'milestone' => 'GOAL_NOT_SET',
        ];
        $progress['goal']['goal'] = null;

        $view = \local_flwcupkp\local\student_learning_timeline_view_service::compose(
            $this->history_fixture(), $progress, [], []
        );

        $this->assertNull($view['present']['preferred_metric']['percentage']);
        $this->assertSame('GOAL_NOT_SET', $view['present']['preferred_metric']['milestone']);
        $this->assertSame('GOAL_NOT_SET', $view['present']['goal']['reason']);
        $this->assertSame('insufficient_data', $view['present']['goal']['status']);
        $this->assertSame('insufficient_data', $view['future']['adaptive_next']['status']);
    }

    public function test_recommendation_history_explains_versioned_route_changes_without_writes(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $policy = \local_flwcupkp\local\adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION;
        $this->insert_recommendation((int)$user->id, (int)$course->id, 'UTL1', $policy, $now - 60,
            'REMEDIATION', 11, 21, 'oldhash', 1, 1);
        $this->insert_recommendation((int)$user->id, (int)$course->id, 'UTL1', $policy, $now,
            'REVIEW', 12, 22, 'newhash', 2, 2);
        $before = $this->mutation_counts();

        $history = \local_flwcupkp\local\student_learning_timeline_view_service::recommendation_history(
            (int)$user->id, (int)$course->id, 'UTL1', 10, str_pad('newhash', 64, '0')
        );

        $this->assertSame('CupkpRecommendationHistoryView', $history['type']);
        $this->assertCount(2, $history['records']);
        $this->assertSame('REVIEW', $history['records'][0]['action']);
        $this->assertContains('goal_version', $history['records'][0]['changed_from_previous']);
        $this->assertContains('curriculum_version', $history['records'][0]['changed_from_previous']);
        $this->assertContains('learner_state', $history['records'][0]['changed_from_previous']);
        $this->assertContains('selected_target', $history['records'][0]['changed_from_previous']);
        $this->assertContains('selected_activity', $history['records'][0]['changed_from_previous']);
        $this->assertContains('adaptive_action', $history['records'][0]['changed_from_previous']);
        $this->assertTrue($history['why_path_changed']['changed']);
        $this->assertSame('available', $history['why_path_changed']['status']);
        $this->assertSame($before, $this->mutation_counts());
        $this->assertSame(2, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_empty_course_integration_delegates_history_and_renders_all_three_stages_read_only(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'UX1 Integration Course']);
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Timeline', 'lastname' => 'Learner']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $before = $this->mutation_counts();

        $view = \local_flwcupkp\local\student_learning_timeline_view_service::learner_timeline(
            (int)$user->id, (int)$course->id, 'UTL1', 0, 10
        );
        $html = \local_flwcupkp\local\student_learning_timeline_renderer::render(
            $view,
            new \moodle_url('/local/flwcupkp/learning_timeline.php', [
                'courseid' => $course->id,
                'unitcode' => 'UTL1',
                'userid' => $user->id,
            ])
        );

        $this->assertSame('LearnerHistoryDashboardCore', $view['past']['dashboard']['type']);
        $this->assertSame('local_flwhistory', $view['past']['owner']);
        $this->assertSame('local_flwcupkp', $view['present']['owner']);
        $this->assertSame('local_flwcupkp', $view['future']['owner']);
        $this->assertStringContainsString('local-flwcupkp-ux1-past', $html);
        $this->assertStringContainsString('local-flwcupkp-ux1-present', $html);
        $this->assertStringContainsString('local-flwcupkp-ux1-future', $html);
        $this->assertStringContainsString('local-flwhistory-dashboard', $html);
        $this->assertStringContainsString('data-owner="local_flwhistory"', $html);
        $this->assertSame($before, $this->mutation_counts());
    }

    /**
     * Compact History dashboard fixture.
     *
     * @return array
     */
    private function history_fixture(): array {
        return [
            'type' => 'LearnerHistoryDashboardCore',
            'userid' => 7,
            'courseid' => 8,
            'learner' => ['id' => 7, 'fullname' => 'Example Learner'],
            'present' => [
                'course' => ['id' => 8, 'fullname' => 'Example Course', 'shortname' => 'EXAMPLE'],
                'current' => ['status' => 'available', 'unitid' => 'U038', 'lessonid' => 'L02',
                    'activityid' => 'A03', 'eventtype' => 'quiz', 'eventtime' => 123456],
            ],
            'journey' => ['items' => [['state' => 'completed']]],
            'standard_next_action' => ['status' => 'insufficient_data'],
            'grade_distinctions' => [],
            'trend' => [],
            'attempt_history' => ['records' => [], 'pagination' => []],
            'grade_history' => ['records' => [['id' => 1]], 'pagination' => []],
            'learning_history' => ['records' => [], 'pagination' => []],
            'recent_activity' => ['records' => [], 'pagination' => []],
            'program3_placeholders' => [['key' => 'adaptive_path']],
            'pagination' => ['limit' => 20],
            'generatedat' => 123456,
            'normpolicyversion' => 'history-normalization-v1',
        ];
    }

    /**
     * Frozen A5C presentation fixture.
     *
     * @return array
     */
    private function progress_fixture(): array {
        return [
            'type' => 'CupkpLearnerProgressGoalReadiness',
            'contract' => \local_flwcupkp\local\progress_goal_readiness_service::CONTRACT_VERSION,
            'progress' => [
                'metrics' => [
                    'mastery_progress' => ['percentage' => 62.5, 'numerator' => 5, 'denominator' => 8,
                        'mandatory_gaps' => [41]],
                    'goal_readiness' => ['percentage' => 50.0, 'numerator' => 4, 'denominator' => 8,
                        'mandatory_gaps' => [41, 42]],
                    'path_progress' => ['percentage' => 33.3, 'numerator' => 1, 'denominator' => 3,
                        'mandatory_gaps' => []],
                ],
                'preferred_learner_metric' => ['metric' => 'goal_readiness', 'percentage' => 50.0,
                    'milestone' => 'GOAL_IN_PROGRESS'],
                'goal_achievement' => ['achieved' => false, 'milestone' => 'GOAL_IN_PROGRESS'],
                'requirements' => ['details' => [[
                    'target' => ['type' => 'kp', 'id' => 41, 'externalid' => 'KP-041',
                        'title' => 'Retrieve a key detail'],
                    'gap_status' => 'active_gap',
                    'mastery_score' => 0.62,
                    'confidence' => 0.71,
                    'evidence_count' => 3,
                    'retention_state' => 'review_due',
                    'readiness' => 0.50,
                ]]],
                'source_hash' => 'progresshash',
            ],
            'goal' => ['goal' => [
                'id' => 4,
                'status' => 'active',
                'title' => 'Independent reading',
                'cefr' => 'B1',
                'flwstage' => 'Stage 3',
                'purpose' => 'Academic reading',
                'currentversion' => 2,
            ]],
        ];
    }

    /**
     * Frozen A5/A4B presentation fixture with raw fields that UX1 must compact away.
     *
     * @return array
     */
    private function adaptive_fixture(): array {
        return [
            'type' => 'CupkpContinuousAdaptiveLearnerPath',
            'contract' => \local_flwcupkp\local\adaptive_path_engine_service::CONTRACT_VERSION,
            'recommendation_status' => 'preview',
            'recommendation' => [
                'path_status' => 'next_activity_ready',
                'action' => 'REVIEW',
                'decision_code' => 'RETENTION_REVIEW_DUE',
                'reason' => 'Retrieval is due.',
                'reason_codes' => ['REVIEW_DUE'],
                'selected_target' => ['type' => 'kp', 'id' => 41, 'externalid' => 'KP-041',
                    'title' => 'Retrieve a key detail'],
                'selected_activity' => ['objectid' => 51, 'cmid' => 61, 'title' => 'Retrieval practice',
                    'modname' => 'quiz', 'url' => '/mod/quiz/view.php?id=61'],
                'expected_benefit' => 0.9,
                'mastery_gap' => 0.38,
                'sourcehash' => 'previewhash',
                'eligible_activities' => [['cmid' => 61]],
            ],
            'source_activity_resolution' => [
                'projected_roadmap' => [
                    ['step' => 1, 'stage' => 'current_gap', 'action' => 'REVIEW', 'selected' => true,
                        'target' => ['type' => 'kp', 'id' => 41, 'externalid' => 'KP-041',
                            'title' => 'Retrieve a key detail'],
                        'activity' => ['objectid' => 51, 'cmid' => 61, 'title' => 'Retrieval practice'],
                        'eligible_activities' => [['cmid' => 61]]],
                    ['step' => 2, 'stage' => 'goal', 'action' => 'ADVANCE',
                        'destination' => ['available' => true, 'title' => 'Independent reading',
                            'cefr' => 'B1', 'flwstage' => 'Stage 3']],
                ],
                'relationship_edges' => [['from' => 41, 'to' => 42]],
                'target_resolutions' => [['targetid' => 41]],
            ],
            'explainability' => ['adaptive_path_hash' => 'pathhash'],
        ];
    }

    /**
     * Insert one A5-owned recommendation row.
     */
    private function insert_recommendation(int $userid, int $courseid, string $unitcode,
            string $policy, int $time, string $action, int $targetid, int $cmid,
            string $sourcehash, int $goalversion, int $stateversion): void {
        global $DB;

        $snapshot = [
            'goal_version' => ['currentversion' => $goalversion],
            'curriculum_version' => ['frameworkid' => 1, 'unitcode' => $unitcode,
                'revision' => $goalversion],
            'state_snapshot' => ['version' => $stateversion],
            'policy_versions' => ['adaptive_policy' => 'adaptive-policy-v1'],
        ];
        $DB->insert_record('flwcupkp_recommend', (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'objectid' => $cmid + 100,
            'cmid' => $cmid,
            'targettype' => 'kp',
            'targetid' => $targetid,
            'recommendationtype' => $action,
            'policyversion' => $policy,
            'sourcehash' => str_pad($sourcehash, 64, '0'),
            'decisioncode' => $action . '_DECISION',
            'reason' => 'Versioned recommendation ' . $action,
            'prereqinfo' => json_encode([
                'reason_codes' => [$action . '_REASON'],
                'snapshot' => $snapshot,
            ]),
            'masterygap' => 0.25,
            'expectedbenefit' => 0.75,
            'status' => 'recommended',
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
    }

    /**
     * Recursively check for a key in a presentation subtree.
     */
    private function contains_key(array $value, string $key): bool {
        if (array_key_exists($key, $value)) {
            return true;
        }
        foreach ($value as $child) {
            if (is_array($child) && $this->contains_key($child, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Plugin-owned learner-state mutation counters.
     *
     * @return array
     */
    private function mutation_counts(): array {
        global $DB;

        return [
            'audit' => $DB->count_records('flwcupkp_audit'),
            'evidence' => $DB->count_records('flwcupkp_evidence'),
            'state' => $DB->count_records('flwcupkp_state'),
            'recommend' => $DB->count_records('flwcupkp_recommend'),
            'goal' => $DB->count_records('flwcupkp_goal'),
            'placement' => $DB->count_records('flwcupkp_placement_state'),
        ];
    }
}

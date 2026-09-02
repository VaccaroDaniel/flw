<?php
// PHPUnit tests for Program 3 Gate UX2 learner UX simplification.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the read-only learner-safe presentation boundary.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\learner_experience_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\learner_experience_renderer::class)]
class learner_experience_service_test extends \advanced_testcase {
    public function test_contract_and_status_freeze_ux2_and_stop_before_ux3(): void {
        $this->resetAfterTest(true);
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\learner_experience_service::contract();
        $status = \local_flwcupkp\local\learner_experience_service::status();

        $this->assertSame('P3_UX2', $contract['gate']);
        $this->assertSame('FLW_CUPKP_LEARNER_EXPERIENCE_V1', $contract['version']);
        $this->assertSame('FLW_CUPKP_STUDENT_LEARNING_TIMELINE_VIEW_V1', $contract['source_view_contract']);
        $this->assertSame([
            'history', 'current', 'next', 'coming_up', 'milestone', 'goal',
        ], $contract['progressive_disclosure']['level_1']);
        $this->assertSame(['show_history', 'show_roadmap'], $contract['progressive_disclosure']['level_2']);
        $this->assertSame(['why_this_activity', 'more_details'], $contract['progressive_disclosure']['level_3']);
        $this->assertSame(1, $contract['limits']['primary_actions']);
        $this->assertFalse($contract['continue_learning']['hidden_blocked_expired_allowed']);
        $this->assertTrue($contract['read_only']);
        $this->assertSame([], $contract['write_boundary']);
        $this->assertSame('UX3', $contract['next_allowed_gate']);

        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(10, $status['criteria_summary']['total']);
        $this->assertSame(10, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertSame('ready', $status['dependencies']['ux1']['status']);
        $this->assertSame('UX2', $status['dependencies']['ux1']['next_allowed_gate']);
        $this->assertTrue($status['files']['present']['cli/learner_experience.php']);
        $this->assertTrue($status['surface']['external_api']['get_simplified_learner_experience']);
        $this->assertSame('UX3', $status['next_allowed_gate']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_simplify_builds_six_level_one_sections_and_compresses_history(): void {
        $view = \local_flwcupkp\local\learner_experience_service::simplify($this->timeline_fixture());

        $this->assertSame('SimplifiedLearnerExperienceView', $view['type']);
        $this->assertSame([
            'history', 'current', 'next', 'coming_up', 'milestone', 'goal',
        ], array_keys($view['level_1']));
        $this->assertSame(14, $view['level_1']['history']['learning_events']);
        $this->assertSame(4, $view['level_1']['history']['attempts']);
        $this->assertSame(2, $view['level_1']['history']['completed_steps']);
        $this->assertArrayNotHasKey('dashboard', $view['level_1']['history']);
        $this->assertSame('Goal readiness', $view['level_1']['current']['progress']['label']);
        $this->assertSame(58.0, $view['level_1']['current']['progress']['percentage']);
        $this->assertCount(3, $view['level_1']['current']['ability_highlights']);
        $this->assertTrue($view['level_1']['next']['available']);
        $this->assertSame('/mod/quiz/view.php?id=61', $view['level_1']['next']['url']);
        $this->assertCount(3, $view['level_1']['coming_up']);
        $this->assertCount(5, $view['level_2']['roadmap']);
        $this->assertSame('Working toward your goal', $view['level_1']['milestone']['label']);
        $this->assertSame('Independent reading', $view['level_1']['goal']['title']);
        $this->assertFalse($view['display_rules']['internal_ids_visible']);
        $this->assertTrue($view['read_only']);
        $this->assertSame([], $view['write_boundary']);

        foreach (['id', 'cmid', 'objectid', 'targetid', 'externalid', 'policyversion', 'sourcehash'] as $key) {
            $this->assertFalse($this->contains_key($view['level_1'], $key), $key);
            $this->assertFalse($this->contains_key($view['level_2'], $key), $key);
            $this->assertFalse($this->contains_key($view['level_3'], $key), $key);
        }
    }

    public function test_continue_learning_rejects_non_activity_external_and_mismatched_urls(): void {
        $timeline = $this->timeline_fixture();

        $timeline['future']['adaptive_next']['activity']['url'] = 'javascript:alert(1)';
        $view = \local_flwcupkp\local\learner_experience_service::simplify($timeline);
        $this->assertFalse($view['level_1']['next']['available']);
        $this->assertSame('', $view['level_1']['next']['url']);

        $timeline['future']['adaptive_next']['activity']['url'] = 'https://example.net/mod/quiz/view.php?id=61';
        $view = \local_flwcupkp\local\learner_experience_service::simplify($timeline);
        $this->assertFalse($view['level_1']['next']['available']);

        $timeline['future']['adaptive_next']['activity']['url'] = '/mod/quiz/view.php?id=999';
        $view = \local_flwcupkp\local\learner_experience_service::simplify($timeline);
        $this->assertFalse($view['level_1']['next']['available']);

        $timeline['future']['adaptive_next']['activity']['url'] = '/mod/quiz/view.php?id=61';
        $timeline['future']['adaptive_next']['status'] = 'insufficient_data';
        $view = \local_flwcupkp\local\learner_experience_service::simplify($timeline);
        $this->assertFalse($view['level_1']['next']['available']);
    }

    public function test_learner_terminology_and_future_summary_hide_internal_language(): void {
        $timeline = $this->timeline_fixture();
        $timeline['future']['adaptive_next']['action'] = 'REMEDIATION';
        $timeline['future']['adaptive_next']['reason'] =
            'Remediation for KP prerequisite because mastery evidence is incomplete.';

        $view = \local_flwcupkp\local\learner_experience_service::simplify($timeline);
        $reason = $view['level_3']['why_this_activity']['reason'];

        $this->assertSame('Extra practice', $view['level_1']['next']['action']);
        $this->assertStringContainsString('extra practice', strtolower($reason));
        $this->assertStringContainsString('learning point', strtolower($reason));
        $this->assertStringContainsString('needed first', strtolower($reason));
        $this->assertStringContainsString('ability progress', strtolower($reason));
        $this->assertStringContainsString('learning results', strtolower($reason));
        $this->assertStringNotContainsString('remediation', strtolower($reason));
        $this->assertStringNotContainsString('prerequisite', strtolower($reason));
        $this->assertStringNotContainsString('mastery', strtolower($reason));
        $this->assertStringNotContainsString('evidence', strtolower($reason));
        $this->assertLessThanOrEqual(3, count($view['level_1']['coming_up']));
    }

    public function test_renderer_has_one_primary_action_and_native_progressive_disclosure(): void {
        $view = \local_flwcupkp\local\learner_experience_service::simplify($this->timeline_fixture());
        $html = \local_flwcupkp\local\learner_experience_renderer::render($view);

        $this->assertStringContainsString('My History', $html);
        $this->assertStringContainsString('Where I Am Now', $html);
        $this->assertStringContainsString('What I Should Do Next', $html);
        $this->assertStringContainsString('Coming Up', $html);
        $this->assertStringContainsString('My Milestone', $html);
        $this->assertStringContainsString('My Goal', $html);
        $this->assertSame(4, substr_count($html, '<details'));
        $this->assertStringContainsString('Show History', $html);
        $this->assertStringContainsString('Show Roadmap', $html);
        $this->assertStringContainsString('Why This Activity?', $html);
        $this->assertStringContainsString('More Details', $html);
        $this->assertSame(1, substr_count($html, 'btn btn-primary'));
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('KP-041', $html);
        $this->assertStringNotContainsString('REMEDIATION', $html);
        $this->assertStringNotContainsString('policyversion', strtolower($html));
        $this->assertTrue(strpos($html, 'Where I Am Now') < strpos($html, 'What I Should Do Next'));
        $this->assertTrue(strpos($html, 'What I Should Do Next') < strpos($html, 'Coming Up'));
    }

    public function test_empty_course_integration_is_read_only_and_renders_honest_states(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'UX2 Integration Course']);
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Simple', 'lastname' => 'Learner']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $before = $this->mutation_counts();

        $view = \local_flwcupkp\local\learner_experience_service::learner_experience(
            (int)$user->id, (int)$course->id, 'UX2', 0, 10
        );
        $html = \local_flwcupkp\local\learner_experience_renderer::render($view);

        $this->assertSame('SimplifiedLearnerExperienceView', $view['type']);
        $this->assertSame('starting', $view['level_1']['history']['status']);
        $this->assertFalse($view['level_1']['next']['available']);
        $this->assertSame(0, $view['display_rules']['primary_action_count']);
        $this->assertStringContainsString('Your next activity is being prepared.', $html);
        $this->assertSame(0, substr_count($html, 'btn btn-primary'));
        $this->assertSame($before, $this->mutation_counts());
    }

    /** Frozen UX1 fixture with deliberately technical source fields. */
    private function timeline_fixture(): array {
        $roadmap = [];
        for ($index = 1; $index <= 7; $index++) {
            $roadmap[] = [
                'step' => $index,
                'stage' => $index === 1 ? 'current_gap' : 'goal_path',
                'action' => $index === 1 ? 'REVIEW' : 'ADVANCE',
                'selected' => $index === 1,
                'target' => [
                    'available' => true,
                    'type' => 'kp',
                    'id' => 40 + $index,
                    'externalid' => 'KP-0' . (40 + $index),
                    'title' => 'Reading skill ' . $index,
                ],
                'activity' => [
                    'available' => true,
                    'objectid' => 50 + $index,
                    'cmid' => 60 + $index,
                    'title' => 'Learning activity ' . $index,
                    'modname' => 'quiz',
                    'url' => '/mod/quiz/view.php?id=' . (60 + $index),
                ],
                'destination' => ['available' => false],
            ];
        }
        $skills = [];
        for ($index = 1; $index <= 6; $index++) {
            $skills[] = [
                'target' => [
                    'available' => true,
                    'type' => $index % 3 === 0 ? 'comp' : ($index % 2 === 0 ? 'up' : 'kp'),
                    'id' => 100 + $index,
                    'externalid' => 'KP-' . (100 + $index),
                    'title' => 'Skill focus ' . $index,
                ],
                'gap_status' => $index === 1 ? 'active_gap' : 'satisfied',
                'mastery_score' => .4 + ($index * .05),
                'confidence' => .7,
                'evidence_count' => $index,
                'retention_state' => $index === 1 ? 'review_due' : 'secure',
                'readiness' => .6,
            ];
        }
        return [
            'type' => 'StudentLearningTimelineView',
            'gate' => 'P3_UX1',
            'contract' => \local_flwcupkp\local\student_learning_timeline_view_service::CONTRACT_VERSION,
            'view_policy_version' => 'cupkp-past-present-future-view-v1',
            'learner' => ['id' => 7, 'fullname' => 'Example Learner'],
            'course' => ['id' => 8, 'fullname' => 'Example Course', 'shortname' => 'EXAMPLE'],
            'scope' => ['userid' => 7, 'courseid' => 8, 'unitcode' => 'U038', 'frameworkid' => 2],
            'past' => [
                'owner' => 'local_flwhistory',
                'dashboard_contract' => 'LearnerHistoryDashboardCore',
                'dashboard' => [
                    'type' => 'LearnerHistoryDashboardCore',
                    'journey' => ['items' => [
                        ['state' => 'completed'], ['state' => 'completed'], ['state' => 'current'],
                    ]],
                    'learning_history' => ['records' => [], 'pagination' => ['total' => 14]],
                    'attempt_history' => ['records' => [], 'pagination' => ['total' => 4]],
                    'grade_history' => ['records' => [], 'pagination' => ['total' => 3]],
                    'recent_activity' => ['records' => [['eventtime' => 123456]],
                        'pagination' => ['total' => 6]],
                ],
            ],
            'present' => [
                'owner' => 'local_flwcupkp',
                'current_location' => ['status' => 'available', 'unitid' => 'U038',
                    'lessonid' => 'L02', 'activityid' => 'A03'],
                'metrics' => [
                    'mastery_progress' => ['percentage' => 62.5],
                    'goal_readiness' => ['percentage' => 58.0],
                    'path_progress' => ['percentage' => 33.3],
                ],
                'preferred_metric' => ['metric' => 'goal_readiness', 'percentage' => 58.0,
                    'milestone' => 'GOAL_IN_PROGRESS'],
                'goal_achievement' => ['achieved' => false, 'milestone' => 'GOAL_IN_PROGRESS'],
                'goal' => ['status' => 'active', 'id' => 4, 'title' => 'Independent reading',
                    'cefr' => 'B1', 'flwstage' => 'Stage 3', 'purpose' => 'Academic reading',
                    'targetdate' => 0, 'currentversion' => 2],
                'skill_states' => $skills,
            ],
            'future' => [
                'owner' => 'local_flwcupkp',
                'adaptive_next' => [
                    'status' => 'next_activity_ready',
                    'action' => 'REVIEW',
                    'reason' => 'Review is due to protect mastery evidence.',
                    'target' => ['available' => true, 'type' => 'kp', 'id' => 41,
                        'externalid' => 'KP-041', 'title' => 'Retrieve a key detail'],
                    'activity' => ['available' => true, 'objectid' => 51, 'cmid' => 61,
                        'title' => 'Retrieval practice', 'modname' => 'quiz',
                        'url' => '/mod/quiz/view.php?id=61'],
                ],
                'projected_roadmap' => $roadmap,
                'recommendation_history' => [[
                    'id' => 9,
                    'action' => 'REMEDIATION',
                    'reason' => 'Remediation was selected.',
                    'target' => ['type' => 'kp', 'id' => 41, 'externalid' => 'KP-041',
                        'title' => 'Retrieve a key detail'],
                    'time' => 123456,
                ]],
                'why_path_changed' => [
                    'changed' => true,
                    'reason' => 'The mastery evidence changed the recommendation.',
                    'dimensions' => ['policy_versions'],
                ],
            ],
            'component_status' => ['history' => 'available', 'present' => 'available', 'future' => 'available'],
            'read_only' => true,
            'write_boundary' => [],
        ];
    }

    /** Recursively check for one array key. */
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

    /** Plugin-owned learner-state mutation counters. */
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

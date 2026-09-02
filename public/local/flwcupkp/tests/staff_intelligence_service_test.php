<?php
// PHPUnit tests for Program 3 Gate UX3 staff explainability and controlled overrides.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/** UX3 staff intelligence and intervention tests. */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\staff_intelligence_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\staff_intelligence_renderer::class)]
class staff_intelligence_service_test extends \advanced_testcase {
    public function test_contract_and_status_freeze_ux3_without_writes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\staff_intelligence_service::contract();
        $status = \local_flwcupkp\local\staff_intelligence_service::status((int)$course->id, 'UX3');

        $this->assertSame('P3_UX3', $contract['gate']);
        $this->assertSame('FLW_CUPKP_STAFF_INTELLIGENCE_V1', $contract['version']);
        $this->assertCount(9, $contract['staff_detail']);
        $this->assertSame([
            'why_target',
            'why_activity',
            'why_extra_practice',
            'why_review',
            'why_skip',
            'why_path_changed',
        ], $contract['recommendation_questions']);
        $this->assertSame([
            'assign_target_activity',
            'force_review',
            'hold_advancement',
            'override_recommendation',
            'adjust_goal',
            'teacher_evidence',
        ], $contract['authorized_interventions']);
        $this->assertSame('local/flwcupkp:override', $contract['governance']['capability']);
        $this->assertTrue($contract['governance']['append_only_versions']);
        $this->assertFalse($contract['governance']['automatic_overwrite_allowed']);
        $this->assertSame('local_flwhistory', $contract['history_owner']);
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
        $this->assertSame('F1', $contract['next_allowed_gate']);

        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(10, $status['criteria_summary']['total']);
        $this->assertSame(10, $status['criteria_summary']['passed']);
        $this->assertSame(0, $status['criteria_summary']['failed']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_staff_detail_answers_six_questions_and_remains_read_only(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UX3VIEW');
        $this->save_goal($fixture);
        $this->insert_placement_state($fixture);
        $this->create_mapped_page($fixture, 'UX3 View Page', true);
        $before = $this->mutation_counts();

        $view = \local_flwcupkp\local\staff_intelligence_service::learner_intelligence(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50
        );

        $this->assertSame('CupkpStaffLearnerIntelligence', $view['type']);
        foreach (['states', 'retention', 'evidence', 'prerequisites', 'path', 'policy_versions',
                'intervention_options', 'interventions', 'intervention_history'] as $key) {
            $this->assertArrayHasKey($key, $view);
        }
        $this->assertSame($fixture['kpid'], $view['intervention_options']['targets'][0]['id']);
        $this->assertNotEmpty($view['intervention_options']['eligible_activities']);
        foreach (\local_flwcupkp\local\staff_intelligence_service::contract()['recommendation_questions'] as $key) {
            $this->assertArrayHasKey($key, $view['explanations']);
            $this->assertNotEmpty($view['explanations'][$key]['answer']);
        }
        $this->assertSame('local_flwhistory', $view['ownership']['history']);
        $this->assertTrue($view['read_only']);
        $this->assertFalse($view['state_changes_allowed']);

        $url = new \moodle_url('/local/flwcupkp/staff_intelligence.php');
        $readonlyhtml = \local_flwcupkp\local\staff_intelligence_renderer::render($view, false, $url);
        $editablehtml = \local_flwcupkp\local\staff_intelligence_renderer::render($view, true, $url);
        $this->assertStringContainsString(get_string('staffwhytarget', 'local_flwcupkp'), $readonlyhtml);
        $this->assertStringContainsString(get_string('staffwhypathchanged', 'local_flwcupkp'), $readonlyhtml);
        $this->assertStringNotContainsString('local-flwcupkp-ux3-form', $readonlyhtml);
        $this->assertStringContainsString('local-flwcupkp-ux3-form', $editablehtml);
        $this->assertStringContainsString('UX3 Knowledge Point', $editablehtml);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_intervention_write_requires_override_capability(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture('UX3AUTH');
        $this->setUser($fixture['userid']);

        $this->expectException(\required_capability_exception::class);
        \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'],
            'hold_advancement', [], 'Unauthorized hold'
        );
    }

    public function test_hold_and_release_are_audited_immutable_versions(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UX3HOLD');
        $this->save_goal($fixture);
        $this->insert_placement_state($fixture);
        $this->create_mapped_page($fixture, 'UX3 Hold Page', true);

        $applied = \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'],
            'hold_advancement', [], 'Wait for a teacher conference'
        );

        $this->assertSame('applied', $applied['status']);
        $this->assertSame(1, $applied['intervention']['version']);
        $this->assertSame('active', $applied['intervention']['status']);
        $this->assertSame('REPRIORITIZE', $applied['intervention']['actioncode']);
        $this->assertSame('REPRIORITIZE', $applied['pathresult']['action']);
        $path = \local_flwcupkp\local\adaptive_path_engine_service::learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50
        );
        $this->assertSame('REPRIORITIZE', $path['recommendation']['action']);
        $this->assertNull($path['recommendation']['selected_activity']);
        $this->assertSame('hold_advancement', $path['recommendation']['staff_intervention']['type']);
        $this->assertSame('HOLD_ADVANCEMENT',
            $path['recommendation']['snapshot']['staff_intervention']['intent']);

        $released = \local_flwcupkp\local\staff_intelligence_service::release_intervention(
            $applied['intervention']['id'], 'Conference completed', $fixture['frameworkid']
        );

        $this->assertSame('released', $released['status']);
        $this->assertSame(2, $released['intervention']['version']);
        $this->assertSame($applied['intervention']['id'], $released['intervention']['supersedesid']);
        $this->assertCount(0, \local_flwcupkp\local\staff_intelligence_service::current_interventions(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode']
        ));
        $this->assertCount(2, \local_flwcupkp\local\staff_intelligence_service::intervention_history(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode']
        ));
        $this->assertSame(2, $DB->count_records('flwcupkp_intervention'));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'staff_intervention_version_created',
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'staff_intervention_released',
        ]));
    }

    public function test_activity_assignment_never_bypasses_current_a4b_eligibility(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UX3ELIG');
        $this->save_goal($fixture);
        $this->insert_placement_state($fixture);
        $hidden = $this->create_mapped_page($fixture, 'UX3 Hidden Page', false);

        try {
            \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
                $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'],
                'assign_target_activity', [
                    'targettype' => 'kp',
                    'targetid' => $fixture['kpid'],
                    'objectid' => $hidden['objectid'],
                    'cmid' => $hidden['cmid'],
                ], 'Attempt hidden assignment'
            );
            $this->fail('A hidden activity must not be assignable.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('not currently eligible', $e->getMessage());
        }

        $visible = $this->create_mapped_page($fixture, 'UX3 Eligible Page', true);
        $applied = \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'],
            'assign_target_activity', [
                'targettype' => 'kp',
                'targetid' => $fixture['kpid'],
                'objectid' => $visible['objectid'],
                'cmid' => $visible['cmid'],
            ], 'Use the teacher-selected eligible practice'
        );
        $this->assertSame($visible['cmid'], $applied['intervention']['cmid']);
        $this->assertSame('ADVANCE', $applied['pathresult']['action']);

        require_once($CFG->dirroot . '/course/lib.php');
        set_coursemodule_visible($visible['cmid'], 0);
        rebuild_course_cache($fixture['courseid'], true);
        $path = \local_flwcupkp\local\adaptive_path_engine_service::learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50
        );
        $this->assertSame('blocked_by_current_eligibility',
            $path['recommendation']['staff_intervention']['status']);
        $this->assertContains('STAFF_INTERVENTION_BLOCKED_BY_A4B',
            $path['recommendation']['reason_codes']);
        $this->assertNotSame($visible['cmid'], (int)($path['recommendation']['selected_activity']['cmid'] ?? 0));
    }

    public function test_teacher_evidence_and_goal_adjustment_use_existing_audited_writers(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UX3WRITE');
        $this->save_goal($fixture);
        $this->insert_placement_state($fixture);
        $page = $this->create_mapped_page($fixture, 'UX3 Evidence Page', true);

        $evidence = \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'],
            'teacher_evidence', [
                'targettype' => 'kp',
                'targetid' => $fixture['kpid'],
                'objectid' => $page['objectid'],
                'score' => 0.84,
                'confidence' => 0.91,
                'note' => 'Learner explained and applied the reading strategy independently.',
            ], 'Record direct teacher observation'
        );
        $evidencerow = $DB->get_record('flwcupkp_evidence', [
            'id' => $evidence['writerresult']['evidenceid'],
        ], '*', MUST_EXIST);
        $this->assertSame('teacher_observation', $evidencerow->evidencetype);
        $this->assertSame('teacher', $evidencerow->assessortype);
        $this->assertSame('local_flwcupkp:ux3_teacher_evidence', $evidencerow->provenance);
        $this->assertSame('recorded', $evidence['intervention']['status']);

        $goal = \local_flwcupkp\local\staff_intelligence_service::apply_intervention(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'],
            'adjust_goal', [
                'title' => 'Teacher-confirmed UX3 reading goal',
                'purpose' => 'Prepare for independent unit reading',
            ], 'Align the learner goal after conference'
        );
        $this->assertSame(2, $goal['writerresult']['version']);
        $this->assertSame('recorded', $goal['intervention']['status']);
        $this->assertSame(2, $DB->count_records('flwcupkp_goal_version'));
        $this->assertSame('Teacher-confirmed UX3 reading goal', $DB->get_field('flwcupkp_goal', 'title', [
            'id' => $goal['writerresult']['goalid'],
        ], MUST_EXIST));
        $this->assertCount(0, \local_flwcupkp\local\staff_intelligence_service::current_interventions(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode']
        ));
        $this->assertSame(2, $DB->count_records('flwcupkp_intervention'));
    }

    /** Build a minimal published learner/activity scope. */
    private function create_fixture(string $unitcode): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-' . $suffix,
            'name' => 'UX3 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-' . $suffix,
            'title' => 'UX3 Knowledge Point',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$learner->id,
            'frameworkid' => $frameworkid,
            'kpid' => $kpid,
            'unitcode' => $unitcode,
        ];
    }

    /** Store a versioned learner goal. */
    private function save_goal(array $fixture): void {
        \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'title' => 'UX3 Reading Goal',
            'desiredprofile' => ['profile' => 'Read the mapped unit text independently.'],
            'cefr' => 'B1',
            'flwstage' => 'FLW-STAGE-03',
            'purpose' => 'UX3 test fixture',
            'weeklytarget' => 2,
            'kpids' => [$fixture['kpid']],
        ], 'TEACHER', 'PHPUnit UX3 goal');
    }

    /** Store a valid diagnostic placement fact. */
    private function insert_placement_state(array $fixture): void {
        global $DB, $USER;

        $now = time();
        $DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'sourcekey' => 'phpunit-ux3-placement-' . $fixture['userid'],
            'sourcefactkey' => 'phpunit-ux3-placement-' . $fixture['userid'] . '-' . $now,
            'placementstatus' => 'recorded',
            'policystate' => 'VALID',
            'sourcecategory' => 'imported_history',
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'score' => 0.82,
            'confidence' => 0.90,
            'placementtime' => $now,
            'staleafter' => $now + (180 * DAYSECS),
            'assesseddimensionsjson' => json_encode([[
                'key' => 'reading',
                'score' => 0.82,
                'targettype' => 'kp',
                'targetid' => $fixture['kpid'],
            ]], JSON_UNESCAPED_SLASHES),
            'evidenceidsjson' => '[]',
            'diagnosticjson' => '{"policy_case":"imported_history"}',
            'policyversion' => \local_flwcupkp\local\placement_diagnostic_service::POLICY_VERSION,
            'checksum' => sha1($fixture['unitcode'] . $fixture['userid'] . $now),
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int)($USER->id ?? 0),
        ]);
    }

    /** Create and map a Moodle page to the fixture KP. */
    private function create_mapped_page(array $fixture, string $name, bool $visible): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $fixture['courseid'],
            'name' => $name,
            'content' => 'Mapped UX3 C-UP-KP practice.',
        ]);
        if (!$visible) {
            set_coursemodule_visible((int)$page->cmid, 0);
            rebuild_course_cache($fixture['courseid'], true);
        }
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $fixture['frameworkid'],
            'externalid' => 'OBJ-' . $fixture['unitcode'] . '-' . (int)$page->cmid,
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'lesson' => 'UX3',
            'objecttype' => 'page',
            'title' => $name,
            'cmid' => (int)$page->cmid,
            'sourceid' => 'OBJ-' . $fixture['unitcode'] . '-' . (int)$page->cmid,
            'purpose' => 'practice',
            'evidencestrength' => 'guided_performance',
            'difficulty' => 0.4,
            'role' => 'practice',
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
            'role' => 'practice',
            'evidencestrength' => 'guided_performance',
        ]);
        return ['objectid' => $objectid, 'cmid' => (int)$page->cmid];
    }

    /** Counts used to prove read-only surfaces make no hidden writes. */
    private function mutation_counts(): array {
        global $DB;

        return [
            'audit' => $DB->count_records('flwcupkp_audit'),
            'evidence' => $DB->count_records('flwcupkp_evidence'),
            'state' => $DB->count_records('flwcupkp_state'),
            'recommend' => $DB->count_records('flwcupkp_recommend'),
            'goal' => $DB->count_records('flwcupkp_goal'),
            'goal_version' => $DB->count_records('flwcupkp_goal_version'),
            'placement' => $DB->count_records('flwcupkp_placement_state'),
            'intervention' => $DB->count_records('flwcupkp_intervention'),
        ];
    }
}

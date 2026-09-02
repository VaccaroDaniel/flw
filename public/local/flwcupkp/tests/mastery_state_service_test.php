<?php
// PHPUnit tests for Program 3 Gate E2 mastery/confidence/current learner state.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * E2 current-state service tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\mastery_state_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\mastery_engine::class)]
class mastery_state_service_test extends \advanced_testcase {
    public function test_contract_exposes_current_state_without_adaptive_or_raw_log_logic(): void {
        $contract = \local_flwcupkp\local\mastery_state_service::contract();

        $this->assertSame('P3_E2', $contract['gate']);
        $this->assertSame('FLW_CUPKP_MASTERY_CONFIDENCE_STATE_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\management_v1_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\history_evidence_adapter::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains('LearnerCompetencyState', $contract['supported_learner_states']);
        $this->assertContains('LearnerKPState', $contract['supported_learner_states']);
        $this->assertContains('LearnerUPState', $contract['supported_learner_states']);
        $this->assertContains('evidencehash', $contract['snapshot_fields']);
        $this->assertSame(\local_flwcupkp\local\mastery_engine::POLICY_VERSION,
            $contract['mastery_policy_version']);
        $this->assertSame(\local_flwcupkp\local\mastery_engine::CONFIDENCE_POLICY_VERSION,
            $contract['confidence_policy_version']);
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertContains('retention_decay', $contract['does_not_do']);
    }

    public function test_status_is_read_only_ready_and_points_to_e3(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $status = \local_flwcupkp\local\mastery_state_service::status(0, '', 0, 20);

        $this->assertSame('CupkpMasteryConfidenceStateStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame('E3', $status['next_allowed_gate']);
        $this->assertSame(6, $status['criteria_summary']['total']);
        $this->assertSame(6, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertTrue($status['schema']['present']['evidencehash']);
        $this->assertTrue($status['files']['present']['mastery_state.php']);
        $this->assertTrue($status['files']['present']['cli/mastery_state.php']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_current_learner_state_consumes_e1_history_backed_evidence(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE2', 'ASSESSMENT', 'assesses', 'independent_performance');
        $this->record_history_attempt($fixture['courseid'], $fixture['cmid'], $fixture['userid'],
            $fixture['activityid'], $fixture['assessmentid'], 'UE2');

        $apply = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE2',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0,
            'phpunit E2 evidence'
        );
        $this->assertSame(1, $apply['summary']['created'], json_encode($apply));

        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $fixture['userid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ], '*', MUST_EXIST);
        $this->assertSame(\local_flwcupkp\local\mastery_engine::POLICY_VERSION, $state->policyversion);
        $this->assertNotEmpty($state->evidencehash);
        $this->assertNotEmpty($state->evidenceidsjson);
        $this->assertGreaterThan(0, (int)$state->calculatedtime);
        $this->assertGreaterThan(0, (float)$state->confidence);
        $this->assertNotEquals((float)$state->masteryscore, (float)$state->confidence);

        $current = \local_flwcupkp\local\mastery_state_service::current_learner_state(
            $fixture['userid'],
            $fixture['courseid'],
            'UE2',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame('CupkpCurrentLearnerState', $current['type']);
        $this->assertSame('E3', $current['next_allowed_gate']);
        $this->assertSame(1, $current['summary']['kp']);
        $this->assertSame(1, $current['summary']['history_v1_evidence']);
        $this->assertSame('LearnerKPState', $current['states'][0]['type']);
        $this->assertSame(\local_flwcupkp\local\history_evidence_adapter::PROVENANCE,
            $current['states'][0]['evidence']['latest']['provenance']);
        $this->assertSame(\local_flwcupkp\local\mastery_engine::CONFIDENCE_POLICY_VERSION,
            $current['states'][0]['confidence']['policyversion']);
        $this->assertArrayHasKey('minimum_sufficiency', $current['states'][0]['confidence']['inputs']);
    }

    public function test_rebuild_preview_is_read_only_and_apply_repairs_stale_cache_with_audit(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE2R', 'ASSESSMENT', 'assesses', 'independent_performance');
        $this->record_history_attempt($fixture['courseid'], $fixture['cmid'], $fixture['userid'],
            $fixture['activityid'], $fixture['assessmentid'], 'UE2R');
        \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE2R',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0,
            'phpunit E2 repair seed'
        );

        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $fixture['userid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ], '*', MUST_EXIST);
        $state->masteryscore = 0.1;
        $state->masterystate = 'introduced';
        $state->confidence = 0.1;
        $state->policyversion = null;
        $state->evidencehash = null;
        $state->evidenceidsjson = null;
        $state->calculatedtime = null;
        $DB->update_record('flwcupkp_state', $state);

        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $preview = \local_flwcupkp\local\mastery_state_service::preview_rebuild(
            $fixture['courseid'],
            'UE2R',
            $fixture['frameworkid'],
            $fixture['userid'],
            20
        );

        $this->assertSame('preview', $preview['mode']);
        $this->assertTrue($preview['read_only']);
        $this->assertFalse($preview['state_changes_allowed']);
        $this->assertSame(1, $preview['summary']['changed'], json_encode($preview));
        $this->assertSame(0, $preview['summary']['applied']);
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $unchanged = $DB->get_record('flwcupkp_state', ['id' => $state->id], '*', MUST_EXIST);
        $this->assertSame('introduced', $unchanged->masterystate);

        $apply = \local_flwcupkp\local\mastery_state_service::apply_rebuild(
            $fixture['courseid'],
            'UE2R',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            'phpunit E2 apply'
        );

        $this->assertSame('apply', $apply['mode']);
        $this->assertFalse($apply['read_only']);
        $this->assertTrue($apply['state_changes_allowed']);
        $this->assertSame(1, $apply['summary']['applied'], json_encode($apply));
        $repaired = $DB->get_record('flwcupkp_state', ['id' => $state->id], '*', MUST_EXIST);
        $this->assertSame('mastered', $repaired->masterystate);
        $this->assertSame(\local_flwcupkp\local\mastery_engine::POLICY_VERSION, $repaired->policyversion);
        $this->assertNotEmpty($repaired->evidencehash);
        $this->assertGreaterThan(0, (int)$repaired->calculatedtime);
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'mastery_state_rebuild_completed',
            'targettype' => 'course',
            'targetid' => $fixture['courseid'],
        ]));
    }

    public function test_rebuild_preserves_manual_override_rows(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE2M', 'ASSESSMENT', 'assesses', 'independent_performance');
        $this->record_history_attempt($fixture['courseid'], $fixture['cmid'], $fixture['userid'],
            $fixture['activityid'], $fixture['assessmentid'], 'UE2M');
        \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE2M',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0,
            'phpunit E2 manual seed'
        );

        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $fixture['userid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ], '*', MUST_EXIST);
        $state->masterystate = 'practiced';
        $state->masteryscore = 0.4;
        $state->manualoverride = 1;
        $state->overridereason = 'Teacher judgment';
        $DB->update_record('flwcupkp_state', $state);

        $preview = \local_flwcupkp\local\mastery_state_service::preview_rebuild(
            $fixture['courseid'],
            'UE2M',
            $fixture['frameworkid'],
            $fixture['userid'],
            20
        );
        $this->assertSame(1, $preview['summary']['manual_overrides'], json_encode($preview));
        $this->assertSame([], $preview['changes']);

        $apply = \local_flwcupkp\local\mastery_state_service::apply_rebuild(
            $fixture['courseid'],
            'UE2M',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            'phpunit E2 manual apply'
        );
        $this->assertSame(0, $apply['summary']['applied'], json_encode($apply));
        $preserved = $DB->get_record('flwcupkp_state', ['id' => $state->id], '*', MUST_EXIST);
        $this->assertSame('practiced', $preserved->masterystate);
        $this->assertSame(1, (int)$preserved->manualoverride);
        $this->assertSame('Teacher judgment', $preserved->overridereason);
    }

    public function test_scope_discovery_reports_unenrolled_state_users_without_rebuild_writes(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE2S', 'ASSESSMENT', 'assesses', 'independent_performance');
        $orphan = $this->getDataGenerator()->create_user();
        $now = time();
        $DB->insert_record('flwcupkp_state', (object)[
            'userid' => (int)$orphan->id,
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
            'masteryscore' => 0.7,
            'masterystate' => 'practiced',
            'confidence' => 0.4,
            'evidencecount' => 1,
            'manualoverride' => 0,
            'ruleversion' => 'default-v1',
            'timemodified' => $now,
        ]);

        $summary = \local_flwcupkp\local\mastery_state_service::class_summary(
            $fixture['courseid'],
            'UE2S',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame(2, $summary['summary']['learners'], json_encode($summary));
        $this->assertSame(1, $summary['summary']['skipped_unenrolled'], json_encode($summary));

        $preview = \local_flwcupkp\local\mastery_state_service::preview_rebuild(
            $fixture['courseid'],
            'UE2S',
            $fixture['frameworkid'],
            0,
            20
        );

        $this->assertSame('preview', $preview['mode']);
        $this->assertSame(2, $preview['summary']['learners'], json_encode($preview));
        $this->assertSame(1, $preview['summary']['skipped_unenrolled'], json_encode($preview));
        $this->assertSame(0, $preview['summary']['applied'], json_encode($preview));
    }

    private function create_mapped_fixture(string $unitcode, string $purpose, string $role, string $strength): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $cmid = 8200 + (int)$course->id;
        $activityid = 'FLW-E2-ACT-' . $unitcode . '-' . (int)$course->id;
        $assessmentid = 'ASSESS-E2-' . $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-E2-' . $unitcode . '-' . (int)$course->id,
            'name' => 'E2 Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-E2-' . $unitcode . '-' . (int)$course->id,
            'title' => 'E2 Knowledge Point',
            'domain' => 'READ',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $activityid,
            'courseid' => (int)$course->id,
            'unitcode' => $unitcode,
            'lesson' => 'E2',
            'objecttype' => 'quiz',
            'title' => 'E2 Activity',
            'cmid' => $cmid,
            'sourceid' => $activityid,
            'purpose' => $purpose,
            'evidencestrength' => $strength,
            'role' => $role,
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'sourcekey' => 'PROGRAM1-E2-' . $unitcode . '-' . (int)$course->id,
                    'unitid' => $unitcode,
                    'lessonid' => 'LESSON-E2',
                    'activityid' => $activityid,
                    'assessmentid' => $assessmentid,
                    'cmid' => $cmid,
                ],
                'content_evidence_mapping_contract' =>
                    \local_flwcupkp\local\content_evidence_mapping_contract::CONTRACT_VERSION,
                'source_history_contract' => \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            ], JSON_UNESCAPED_SLASHES),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $mapid = (int)$DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => $role,
            'evidencestrength' => $strength,
        ]);

        \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => (int)$course->id,
            'cmid' => $cmid,
            'worldid' => 'W-E2',
            'stageid' => 'S-E2',
            'unitid' => $unitcode,
            'lessonid' => 'LESSON-E2',
            'componentid' => 'COMP-E2',
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'sourcerevision' => 'e2-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);

        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'kpid' => $kpid,
            'objectid' => $objectid,
            'mapid' => $mapid,
            'cmid' => $cmid,
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
        ];
    }

    private function record_history_attempt(int $courseid, int $cmid, int $userid, string $activityid,
            string $assessmentid, string $unitcode): int {
        $finish = time();
        $start = $finish - 120;
        $sourceeventid = \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$userid . '-' . $unitcode . '-e2-attempt',
            'sourceversion' => (string)$finish,
            'eventtype' => 'ASSESSMENT_COMPLETED',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => $unitcode,
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'eventtime' => $finish,
            'status' => 'recorded',
            'normalizer' => 'e2_test',
            'summaryjson' => ['e2' => true],
        ]);

        return \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'quiz_attempt',
                (string)$userid . '-' . $unitcode . '-e2-attempt',
                (string)$finish,
                'finished'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcefamily' => 'quiz',
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$userid . '-' . $unitcode . '-e2-attempt',
            'sourceversion' => (string)$finish,
            'sourceattemptid' => '9',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => $unitcode,
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'attemptno' => 9,
            'attemptstate' => 'finished',
            'rawscore' => 8.5,
            'maxscore' => 10,
            'scaledscore' => 0.85,
            'timestart' => $start,
            'timefinish' => $finish,
            'summaryjson' => ['e2' => true],
        ]);
    }
}

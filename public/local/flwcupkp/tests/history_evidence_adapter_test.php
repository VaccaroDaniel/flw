<?php
// PHPUnit tests for Program 3 Gate E1 History V1 evidence adapter.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * History V1 evidence adapter tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\history_evidence_adapter::class)]
class history_evidence_adapter_test extends \advanced_testcase {
    public function test_contract_consumes_management_v1_and_history_v1_without_raw_logs(): void {
        $contract = \local_flwcupkp\local\history_evidence_adapter::contract();

        $this->assertSame('P3_E1', $contract['gate']);
        $this->assertSame('FLW_CUPKP_HISTORY_EVIDENCE_ADAPTER_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\management_v1_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['depends_on']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
            $contract['normal_source_rule']);
        $this->assertContains('attempts', $contract['supported_fact_types']);
        $this->assertContains('completion', $contract['supported_fact_types']);
        $this->assertContains('grades', $contract['preserved_read_only_fact_types']);
        $this->assertFalse($contract['missing_mapping_rule']['fabricate_evidence']);
        $this->assertTrue($contract['reprocessing']['preview_is_read_only']);
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertFalse($contract['state_changes_allowed']);
    }

    public function test_status_is_ready_read_only_and_points_to_e2(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');

        $status = \local_flwcupkp\local\history_evidence_adapter::status(0, '', 0, 20);

        $this->assertSame('CupkpHistoryEvidenceAdapterStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame('E2', $status['next_allowed_gate']);
        $this->assertSame(6, $status['criteria_summary']['total']);
        $this->assertSame(6, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertTrue($status['files']['present']['history_evidence.php']);
        $this->assertTrue($status['files']['present']['cli/history_evidence.php']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
    }

    public function test_preview_is_read_only_and_marks_unresolved_mapping_without_fabricating_evidence(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->record_history_attempt((int)$course->id, 9090, (int)$user->id, 'UNMAPPED-ACT', 'UNMAPPED-ASSESS');

        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');

        $preview = \local_flwcupkp\local\history_evidence_adapter::preview_reprocess(
            (int)$course->id,
            'UE1',
            0,
            ['attempts'],
            20,
            0
        );

        $this->assertSame('preview', $preview['mode']);
        $this->assertSame(1, $preview['summary']['records_seen']);
        $this->assertSame(0, $preview['summary']['planned']);
        $this->assertSame(1, $preview['summary']['unresolved']);
        $this->assertSame('no_cupkp_object_for_history_fact', $preview['unresolved'][0]['reason']);
        $this->assertTrue($preview['read_only']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
    }

    public function test_apply_reprocess_creates_versioned_history_backed_evidence_idempotently(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('ASSESSMENT', 'assesses', 'recognition');
        $this->record_history_attempt($fixture['courseid'], $fixture['cmid'], $fixture['userid'],
            $fixture['activityid'], $fixture['assessmentid']);

        $preview = \local_flwcupkp\local\history_evidence_adapter::preview_reprocess(
            $fixture['courseid'],
            'UE1',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0
        );
        $this->assertSame(1, $preview['summary']['planned'], json_encode($preview));
        $this->assertSame('would_create', $preview['plans'][0]['status']);

        $apply = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE1',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0,
            'phpunit E1'
        );

        $this->assertSame('apply', $apply['mode']);
        $this->assertSame(1, $apply['summary']['created'], json_encode($apply));
        $this->assertSame(1, count($apply['created_evidenceids']));

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $apply['created_evidenceids'][0]], '*',
            MUST_EXIST);
        $this->assertSame('history_v1_attempt', $evidence->evidencetype);
        $this->assertSame(\local_flwcupkp\local\history_evidence_adapter::PROVENANCE, $evidence->provenance);
        $this->assertStringStartsWith('history_v1:', $evidence->sourceattempt);
        $this->assertSame(0.85, (float)$evidence->normalizedscore);

        $rubric = json_decode((string)$evidence->rubricjson, true);
        $this->assertSame(\local_flwcupkp\local\history_evidence_adapter::CONTRACT_VERSION,
            $rubric['history_source']['adapter_contract']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $rubric['history_source']['history_contract']);
        $this->assertFalse($rubric['history_source']['legacy_direct_capture']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $rubric['cupkp_c3b_semantics']['history_contract']);
        $this->assertFalse($rubric['cupkp_c3b_semantics']['source_key']['legacy_direct_capture']);

        $this->assertTrue($DB->record_exists('flwcupkp_state', [
            'userid' => $fixture['userid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'history_evidence_reprocess_completed',
            'targettype' => 'course',
            'targetid' => $fixture['courseid'],
        ]));

        $second = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE1',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0,
            'phpunit E1 repeat'
        );
        $this->assertSame(0, $second['summary']['created'], json_encode($second));
        $this->assertSame(1, $second['summary']['existing'], json_encode($second));
        $this->assertSame(1, $DB->count_records('flwcupkp_evidence', [
            'objectid' => $fixture['objectid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ]));

        $DB->set_field('flwcupkp_object_map', 'evidencestrength', 'guided_performance', ['id' => $fixture['mapid']]);
        $third = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE1',
            $fixture['frameworkid'],
            ['attempts'],
            20,
            0,
            'phpunit E1 mapping correction'
        );
        $this->assertSame(1, $third['summary']['created'], json_encode($third));
        $this->assertSame(2, $DB->count_records('flwcupkp_evidence', [
            'objectid' => $fixture['objectid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ]));
    }

    public function test_completion_reprocess_respects_completion_evidence_policy(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('ASSESSMENT', 'assesses', 'recognition');
        $this->record_history_completion($fixture['courseid'], $fixture['cmid'], $fixture['userid']);

        $apply = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'],
            'UE1',
            $fixture['frameworkid'],
            ['completion'],
            20,
            0,
            'phpunit completion'
        );

        $this->assertSame(1, $apply['summary']['created'], json_encode($apply));
        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $apply['created_evidenceids'][0]], '*',
            MUST_EXIST);
        $this->assertSame('history_v1_completion', $evidence->evidencetype);
        $this->assertSame('recognition', $evidence->evidencestrength);
        $this->assertSame(1.0, (float)$evidence->normalizedscore);
    }

    private function create_mapped_fixture(string $purpose, string $role, string $strength): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $cmid = 8000 + (int)$course->id;
        $activityid = 'FLW-E1-ACT-' . (int)$course->id;
        $assessmentid = 'ASSESS-E1-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-E1-' . (int)$course->id,
            'name' => 'E1 Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-E1-' . (int)$course->id,
            'title' => 'E1 Knowledge Point',
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
            'unitcode' => 'UE1',
            'lesson' => 'E1',
            'objecttype' => 'quiz',
            'title' => 'E1 Activity',
            'cmid' => $cmid,
            'sourceid' => $activityid,
            'purpose' => $purpose,
            'evidencestrength' => $strength,
            'role' => $role,
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'sourcekey' => 'PROGRAM1-E1-' . (int)$course->id,
                    'unitid' => 'UE1',
                    'lessonid' => 'LESSON-E1',
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
            'worldid' => 'W-E1',
            'stageid' => 'S-E1',
            'unitid' => 'UE1',
            'lessonid' => 'LESSON-E1',
            'componentid' => 'COMP-E1',
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'sourcerevision' => 'e1-test',
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
            string $assessmentid): int {
        $sourceeventid = \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$userid . '-e1-attempt',
            'sourceversion' => '4100',
            'eventtype' => 'ASSESSMENT_COMPLETED',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => 'UE1',
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'eventtime' => 4100,
            'status' => 'recorded',
            'normalizer' => 'e1_test',
            'summaryjson' => ['e1' => true],
        ]);

        return \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'quiz_attempt',
                (string)$userid . '-e1-attempt',
                '4100',
                'finished'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcefamily' => 'quiz',
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$userid . '-e1-attempt',
            'sourceversion' => '4100',
            'sourceattemptid' => '9',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => 'UE1',
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'attemptno' => 9,
            'attemptstate' => 'finished',
            'rawscore' => 8.5,
            'maxscore' => 10,
            'scaledscore' => 0.85,
            'timestart' => 4000,
            'timefinish' => 4100,
            'summaryjson' => ['e1' => true],
        ]);
    }

    private function record_history_completion(int $courseid, int $cmid, int $userid): int {
        return \local_flwhistory\local\completion_service::record_completion([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'course_module_completion',
                (string)$userid . '-' . (string)$cmid,
                '4200',
                'complete'
            ),
            'sourcefamily' => 'completion',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'completionstate' => defined('COMPLETION_COMPLETE') ? COMPLETION_COMPLETE : 1,
            'viewed' => 1,
            'completiontime' => 4200,
            'detailsjson' => ['e1' => true],
        ]);
    }
}

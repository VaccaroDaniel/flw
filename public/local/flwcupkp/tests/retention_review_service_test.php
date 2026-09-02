<?php
// PHPUnit tests for Program 3 Gate E3 retention/retrieval/review.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * E3 retention/review service tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\retention_review_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\repository::class)]
class retention_review_service_test extends \advanced_testcase {
    public function test_contract_and_status_are_ready_without_adaptive_logic(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $contract = \local_flwcupkp\local\retention_review_service::contract();
        $status = \local_flwcupkp\local\retention_review_service::status(0, '', 0, 20);

        $this->assertSame('P3_E3', $contract['gate']);
        $this->assertSame('FLW_CUPKP_RETENTION_RETRIEVAL_REVIEW_V1', $contract['version']);
        $this->assertContains('REVIEW_DUE', $contract['retention_states']);
        $this->assertContains('RETENTION_UNCERTAIN', $contract['retention_states']);
        $this->assertContains('RELEARNING', $contract['retention_states']);
        $this->assertTrue($contract['rules']['time_triggers_review_not_mastery_decay']);
        $this->assertTrue($contract['rules']['failed_review_sets_retention_state_not_mastery_state']);
        $this->assertContains('mastery_decay', $contract['does_not_do']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);

        $this->assertSame('CupkpRetentionReviewStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame('A1', $status['next_allowed_gate']);
        $this->assertSame(6, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertTrue($status['schema']['present']['retentionpolicyversion']);
        $this->assertTrue($status['files']['present']['retention_review.php']);
        $this->assertTrue($status['files']['present']['cli/retention_review.php']);
        $this->assertFalse($status['state_changes_allowed']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_kp_and_up_can_have_different_retention_states_from_same_mode(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE3D', ['kp', 'up']);
        $time = time() - DAYSECS;

        $this->insert_state($fixture['userid'], 'kp', $fixture['kpid'], 'mastered', 0.91, 0.86, 2, $time);
        $this->insert_state($fixture['userid'], 'up', $fixture['upid'], 'transfer_ready', 0.93, 0.86, 2, $time);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.91, 'controlled_production',
            'controlled_recall', 'positive', $time - 60);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.92, 'controlled_production',
            'controlled_recall', 'positive', $time);
        $this->insert_evidence($fixture, 'up', $fixture['upid'], 0.91, 'controlled_production',
            'controlled_recall', 'positive', $time - 60);
        $this->insert_evidence($fixture, 'up', $fixture['upid'], 0.92, 'controlled_production',
            'controlled_recall', 'positive', $time);

        $state = \local_flwcupkp\local\retention_review_service::current_retention_state(
            $fixture['userid'],
            $fixture['courseid'],
            'UE3D',
            $fixture['frameworkid'],
            20
        );
        $bytype = $this->rows_by_type($state['states']);

        $this->assertSame('retained', $bytype['kp']['calculated']['retentionstate']);
        $this->assertSame('retention_uncertain', $bytype['up']['calculated']['retentionstate']);
        $this->assertSame('mastered', $bytype['kp']['mastery']['state']);
        $this->assertSame('transfer_ready', $bytype['up']['mastery']['state']);
    }

    public function test_review_due_does_not_decay_mastery_and_apply_is_audited(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE3R', ['kp']);
        $old = time() - (30 * DAYSECS);
        $stateid = $this->insert_state($fixture['userid'], 'kp', $fixture['kpid'], 'mastered', 0.90, 0.85, 2, $old);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.90, 'controlled_production',
            'controlled_recall', 'positive', $old - 60);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.91, 'controlled_production',
            'controlled_recall', 'positive', $old);

        $preview = \local_flwcupkp\local\retention_review_service::preview_rebuild(
            $fixture['courseid'],
            'UE3R',
            $fixture['frameworkid'],
            $fixture['userid'],
            20
        );
        $this->assertSame('preview', $preview['mode']);
        $this->assertTrue($preview['read_only']);
        $this->assertSame(1, $preview['summary']['created'], json_encode($preview));
        $this->assertSame(1, $preview['summary']['review_due'], json_encode($preview));
        $unchanged = $DB->get_record('flwcupkp_state', ['id' => $stateid], '*', MUST_EXIST);
        $this->assertSame('', (string)($unchanged->retentionstate ?? ''));

        $apply = \local_flwcupkp\local\retention_review_service::apply_rebuild(
            $fixture['courseid'],
            'UE3R',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            'phpunit E3 apply'
        );
        $this->assertSame('apply', $apply['mode']);
        $this->assertSame(1, $apply['summary']['applied'], json_encode($apply));

        $stored = $DB->get_record('flwcupkp_state', ['id' => $stateid], '*', MUST_EXIST);
        $this->assertSame('mastered', $stored->masterystate);
        $this->assertEquals(0.90, (float)$stored->masteryscore);
        $this->assertSame('review_due', $stored->retentionstate);
        $this->assertSame(\local_flwcupkp\local\retention_review_service::RETENTION_POLICY_VERSION,
            $stored->retentionpolicyversion);
        $this->assertNotEmpty($stored->retentionevidencehash);
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'retention_review_rebuild_completed',
            'targettype' => 'course',
            'targetid' => $fixture['courseid'],
        ]));
    }

    public function test_failed_review_sets_relearning_without_erasing_mastery(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_mapped_fixture('UE3F', ['kp']);
        $success = time() - (2 * DAYSECS);
        $failure = time() - 60;
        $stateid = $this->insert_state($fixture['userid'], 'kp', $fixture['kpid'], 'mastered', 0.88, 0.80, 2,
            $success);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.88, 'controlled_production',
            'controlled_recall', 'positive', $success);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.20, 'controlled_production',
            'controlled_recall', 'negative', $failure);

        $apply = \local_flwcupkp\local\retention_review_service::apply_rebuild(
            $fixture['courseid'],
            'UE3F',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            'phpunit E3 failed review'
        );

        $this->assertSame(1, $apply['summary']['relearning'], json_encode($apply));
        $stored = $DB->get_record('flwcupkp_state', ['id' => $stateid], '*', MUST_EXIST);
        $this->assertSame('relearning', $stored->retentionstate);
        $this->assertSame('mastered', $stored->masterystate);
        $this->assertEquals(0.88, (float)$stored->masteryscore);
        $this->assertGreaterThan(0, (int)$stored->retentionlastretrieval);
    }

    private function create_mapped_fixture(string $unitcode, array $targettypes): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $cmid = 9300 + (int)$course->id;
        $activityid = 'FLW-E3-ACT-' . $unitcode . '-' . (int)$course->id;
        $assessmentid = 'ASSESS-E3-' . $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-E3-' . $unitcode . '-' . (int)$course->id,
            'name' => 'E3 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $competencyid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'COMP-E3-' . $unitcode . '-' . (int)$course->id,
            'title' => 'E3 Competency',
            'description' => 'Retain and retrieve the target language over time.',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-E3-' . $unitcode . '-' . (int)$course->id,
            'title' => 'E3 Knowledge Point',
            'domain' => 'READ',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'UP-E3-' . $unitcode . '-' . (int)$course->id,
            'title' => 'E3 Use Point',
            'purpose' => 'retrieval test',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $competencyid,
            'upid' => $upid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid,
            'kpid' => $kpid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $activityid,
            'courseid' => (int)$course->id,
            'unitcode' => $unitcode,
            'lesson' => 'E3',
            'objecttype' => 'quiz',
            'title' => 'E3 Retrieval Object',
            'cmid' => $cmid,
            'sourceid' => $activityid,
            'purpose' => 'REVIEW',
            'evidencestrength' => 'controlled_production',
            'role' => 'review',
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'sourcekey' => 'PROGRAM1-E3-' . $unitcode . '-' . (int)$course->id,
                    'unitid' => $unitcode,
                    'lessonid' => 'LESSON-E3',
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
        $kpmapid = 0;
        $upmapid = 0;
        if (in_array('kp', $targettypes, true)) {
            $kpmapid = (int)$DB->insert_record('flwcupkp_object_map', (object)[
                'objectid' => $objectid,
                'targettype' => 'kp',
                'targetid' => $kpid,
                'role' => 'review',
                'evidencestrength' => 'controlled_production',
            ]);
        }
        if (in_array('up', $targettypes, true)) {
            $upmapid = (int)$DB->insert_record('flwcupkp_object_map', (object)[
                'objectid' => $objectid,
                'targettype' => 'up',
                'targetid' => $upid,
                'role' => 'review',
                'evidencestrength' => 'controlled_production',
            ]);
        }

        \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => (int)$course->id,
            'cmid' => $cmid,
            'worldid' => 'W-E3',
            'stageid' => 'S-E3',
            'unitid' => $unitcode,
            'lessonid' => 'LESSON-E3',
            'componentid' => 'COMP-E3',
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'sourcerevision' => 'e3-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);

        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'competencyid' => $competencyid,
            'kpid' => $kpid,
            'upid' => $upid,
            'objectid' => $objectid,
            'kpmapid' => $kpmapid,
            'upmapid' => $upmapid,
            'unitcode' => $unitcode,
            'cmid' => $cmid,
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
        ];
    }

    private function insert_state(int $userid, string $targettype, int $targetid, string $masterystate,
            float $masteryscore, float $confidence, int $evidencecount, int $lastsuccess): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_state', (object)[
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'masteryscore' => $masteryscore,
            'masterystate' => $masterystate,
            'confidence' => $confidence,
            'evidencecount' => $evidencecount,
            'lastevidence' => $lastsuccess,
            'lastsuccess' => $lastsuccess,
            'manualoverride' => 0,
            'ruleversion' => 'default-v1',
            'policyversion' => \local_flwcupkp\local\mastery_engine::POLICY_VERSION,
            'evidencehash' => sha1($targettype . ':' . $targetid . ':' . $lastsuccess),
            'evidenceidsjson' => '[]',
            'calculatedtime' => $now,
            'timemodified' => $now,
        ]);
    }

    private function insert_evidence(array $fixture, string $targettype, int $targetid, float $score, string $strength,
            string $mode, string $result, int $time): int {
        global $DB;

        $object = $DB->get_record('flwcupkp_object', ['id' => $fixture['objectid']], '*', MUST_EXIST);
        $mapid = $targettype === 'up' ? (int)$fixture['upmapid'] : (int)$fixture['kpmapid'];
        $map = $DB->get_record('flwcupkp_object_map', ['id' => $mapid], '*', MUST_EXIST);
        $sourceattempt = 'history_v1:e3:' . $fixture['unitcode'] . ':' . $targettype . ':' . $targetid . ':' . $time;
        $record = (object)[
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'objectid' => $fixture['objectid'],
            'sourceattempt' => $sourceattempt,
            'evidencetype' => 'history_v1_attempt',
            'targettype' => $targettype,
            'targetid' => $targetid,
            'rawscore' => $score,
            'normalizedscore' => $score,
            'rubricjson' => json_encode([
                'result_state' => $result,
                'performance_mode' => $mode,
                'evidence_direction' => 'direct',
                'evidence_role' => 'practice_evidence',
                'quality' => [
                    'validity' => 0.90,
                    'reliability' => 0.90,
                    'independence' => 0.90,
                    'authenticity' => 0.85,
                    'production_demand' => 0.85,
                    'contextual_transfer' => $mode === 'transfer' ? 0.95 : 0.75,
                    'support_level' => 0.90,
                    'difficulty' => 0.80,
                    'recency' => 0.90,
                    'confidence' => 0.90,
                ],
                'history_source' => [
                    'adapter_contract' => \local_flwcupkp\local\history_evidence_adapter::CONTRACT_VERSION,
                    'history_contract' => \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
                    'normal_source_rule' => \local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
                    'history_source_key' => $sourceattempt,
                    'source_type' => 'program2_attempt',
                    'source_attempt_id' => $sourceattempt,
                    'source_ref' => 'phpunit-e3',
                    'provenance' => \local_flwcupkp\local\history_evidence_adapter::PROVENANCE,
                    'legacy_direct_capture' => false,
                ],
                'occurred_at' => $time,
                'recorded_at' => $time,
            ], JSON_UNESCAPED_SLASHES),
            'assessortype' => 'system',
            'confidence' => 0.90,
            'evidencestrength' => $strength,
            'provenance' => \local_flwcupkp\local\history_evidence_adapter::PROVENANCE,
            'sourceref' => 'phpunit-e3',
            'overrideflag' => 0,
            'timecreated' => $time,
            'usermodified' => $fixture['userid'],
        ];

        $record = \local_flwcupkp\local\evidence_semantics_quality_contract::augment_evidence_payload(
            $record,
            $object,
            $map
        );

        return (int)$DB->insert_record('flwcupkp_evidence', $record);
    }

    private function rows_by_type(array $rows): array {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['target']['type']] = $row;
        }
        return $indexed;
    }
}

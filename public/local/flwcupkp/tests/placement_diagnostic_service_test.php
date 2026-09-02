<?php
// PHPUnit tests for Program 3 Gate A2 Placement + Diagnostic + Cold Start.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A2 placement diagnostic service tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\placement_diagnostic_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\mastery_engine::class)]
class placement_diagnostic_service_test extends \advanced_testcase {
    public function test_contract_and_status_are_ready_for_a3_without_adaptive_logic(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforeplacement = $DB->count_records('flwcupkp_placement_state');

        $contract = \local_flwcupkp\local\placement_diagnostic_service::contract();
        $status = \local_flwcupkp\local\placement_diagnostic_service::status((int)$course->id, 'UA2', 0, 20);

        $this->assertSame('P3_A2', $contract['gate']);
        $this->assertSame('FLW_CUPKP_PLACEMENT_DIAGNOSTIC_COLD_START_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\learning_goal_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['depends_on']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
            $contract['normal_source_rule']);
        $this->assertContains('NOT_TAKEN', $contract['states']);
        $this->assertContains('TEACHER_OVERRIDE', $contract['states']);
        $this->assertTrue($contract['placement_enters_pipeline']['only_for_explicit_assessed_dimensions']);
        $this->assertContains('fabricate_unassessed_dimensions', $contract['does_not_do']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertSame('A3', $contract['next_allowed_gate']);

        $this->assertSame('CupkpPlacementDiagnosticColdStartStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(7, $status['criteria_summary']['total']);
        $this->assertSame(7, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertTrue($status['schema']['tables']['flwcupkp_placement_state']);
        $this->assertTrue($status['files']['present']['placement_diagnostic.php']);
        $this->assertTrue($status['files']['present']['cli/placement_diagnostic.php']);
        $this->assertTrue($status['source_adapter']['contract_supports_placement']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertSame('A3', $status['next_allowed_gate']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforeplacement, $DB->count_records('flwcupkp_placement_state'));
    }

    public function test_current_placement_returns_cold_start_when_no_placement_exists(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $current = \local_flwcupkp\local\placement_diagnostic_service::current_placement(
            (int)$user->id,
            (int)$course->id,
            'UA2',
            0,
            20
        );

        $this->assertSame('CupkpLearnerPlacementDiagnosticState', $current['type']);
        $this->assertFalse($current['has_processed_state']);
        $this->assertSame('NOT_TAKEN', $current['state']['policystate']);
        $this->assertSame('no_placement', $current['state']['policycase']);
        $this->assertSame([], $current['state']['evidenceids']);
        $this->assertFalse($current['state']['placement_is_permanent_truth']);
        $this->assertFalse($current['state']['fabricated_dimensions']);
        $this->assertTrue($current['read_only']);
        $this->assertFalse($current['state_changes_allowed']);
        $this->assertSame('A3', $current['next_allowed_gate']);
    }

    public function test_object_mapped_placement_creates_pipeline_evidence_idempotently(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA2M', true);
        $this->record_placement($fixture, 'recorded', [
            'dimension_scores' => [
                'reading' => [
                    'score' => 0.86,
                    'level' => 'B1',
                ],
            ],
        ], time(), 0.86, 0.92);

        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $preview = \local_flwcupkp\local\placement_diagnostic_service::preview_reprocess(
            $fixture['courseid'],
            'UA2M',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            0
        );

        $this->assertSame('preview', $preview['mode']);
        $this->assertTrue($preview['read_only']);
        $this->assertFalse($preview['state_changes_allowed']);
        $this->assertSame(1, $preview['summary']['records_seen'], json_encode($preview));
        $this->assertSame(1, $preview['summary']['evidence_planned'], json_encode($preview));
        $this->assertSame(0, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame(0, $DB->count_records('flwcupkp_placement_state'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));

        $apply = \local_flwcupkp\local\placement_diagnostic_service::apply_reprocess(
            $fixture['courseid'],
            'UA2M',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            0,
            'phpunit A2'
        );

        $this->assertSame('apply', $apply['mode']);
        $this->assertFalse($apply['read_only']);
        $this->assertTrue($apply['state_changes_allowed']);
        $this->assertSame(1, $apply['summary']['evidence_created'], json_encode($apply));
        $this->assertSame(1, $apply['summary']['state_records_written'], json_encode($apply));
        $this->assertSame(1, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame(1, $DB->count_records('flwcupkp_placement_state'));

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $apply['created_evidenceids'][0]], '*',
            MUST_EXIST);
        $this->assertSame('history_v1_placement', $evidence->evidencetype);
        $this->assertSame(\local_flwcupkp\local\placement_diagnostic_service::PROVENANCE, $evidence->provenance);
        $this->assertSame('kp', $evidence->targettype);
        $this->assertSame($fixture['kpid'], (int)$evidence->targetid);
        $this->assertSame($fixture['objectid'], (int)$evidence->objectid);
        $this->assertSame(0.86, (float)$evidence->normalizedscore);
        $rubric = json_decode((string)$evidence->rubricjson, true);
        $this->assertSame('VALID', $rubric['a2_placement_diagnostic']['policy_state']);
        $this->assertFalse($rubric['a2_placement_diagnostic']['placement_is_permanent_truth']);
        $this->assertFalse($rubric['a2_placement_diagnostic']['fabricated_dimension']);

        $state = $DB->get_record('flwcupkp_placement_state', ['userid' => $fixture['userid']], '*', MUST_EXIST);
        $this->assertSame('VALID', $state->policystate);
        $this->assertSame('imported_history', $state->sourcecategory);
        $this->assertSame(\local_flwcupkp\local\placement_diagnostic_service::POLICY_VERSION,
            $state->policyversion);
        $statepayload = json_decode((string)$state->diagnosticjson, true);
        $this->assertSame('imported_history', $statepayload['policy_case']);
        $this->assertFalse($statepayload['placement_is_permanent_truth']);
        $this->assertFalse($statepayload['fabricated_dimensions']);

        $second = \local_flwcupkp\local\placement_diagnostic_service::apply_reprocess(
            $fixture['courseid'],
            'UA2M',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            0,
            'phpunit A2 repeat'
        );
        $this->assertSame(0, $second['summary']['evidence_created'], json_encode($second));
        $this->assertSame(1, $second['summary']['evidence_existing'], json_encode($second));
        $this->assertSame(1, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame(1, $DB->count_records('flwcupkp_placement_state'));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'placement_diagnostic_reprocess_completed',
            'targettype' => 'course',
            'targetid' => $fixture['courseid'],
        ]));
    }

    public function test_overall_only_placement_records_diagnostic_state_without_fabricating_target_evidence(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA2O', false);
        $this->record_placement($fixture, 'recorded', [], time(), 0.78, 0.91);

        $preview = \local_flwcupkp\local\placement_diagnostic_service::preview_reprocess(
            $fixture['courseid'],
            'UA2O',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            0
        );
        $this->assertSame(1, $preview['summary']['records_seen'], json_encode($preview));
        $this->assertSame(1, $preview['summary']['dimensions_assessed'], json_encode($preview));
        $this->assertSame(1, $preview['summary']['no_mapped_target'], json_encode($preview));
        $this->assertSame(0, $preview['summary']['evidence_planned'], json_encode($preview));
        $this->assertSame([], $preview['plans']);

        $apply = \local_flwcupkp\local\placement_diagnostic_service::apply_reprocess(
            $fixture['courseid'],
            'UA2O',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            0,
            'phpunit overall diagnostic only'
        );

        $this->assertSame(0, $apply['summary']['evidence_created'], json_encode($apply));
        $this->assertSame(1, $apply['summary']['state_records_written'], json_encode($apply));
        $this->assertSame(0, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame(1, $DB->count_records('flwcupkp_placement_state'));
        $state = $DB->get_record('flwcupkp_placement_state', ['userid' => $fixture['userid']], '*', MUST_EXIST);
        $this->assertSame('VALID', $state->policystate);
        $this->assertSame([], json_decode((string)$state->evidenceidsjson, true));
        $dimensions = json_decode((string)$state->assesseddimensionsjson, true);
        $this->assertSame('overall', $dimensions[0]['key']);
        $this->assertSame('no_mapped_target', $dimensions[0]['status']);
    }

    public function test_policy_states_cover_prompt_cases_without_writing_in_preview(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA2P', false);
        $profile = [
            'target_scores' => [
                'explicit-kp' => [
                    'score' => 0.72,
                    'targettype' => 'kp',
                    'targetid' => $fixture['kpid'],
                ],
            ],
        ];
        $now = time();
        $this->record_placement($fixture, 'refused', $profile, $now - 10, 0.70, 0.80, 'refused');
        $this->record_placement($fixture, 'abandoned', $profile, $now - 20, 0.70, 0.80, 'abandoned');
        $this->record_placement($fixture, 'partial', $profile, $now - 30, 0.70, 0.80, 'partial');
        $this->record_placement($fixture, 'recorded', $profile, $now - 40, 0.70, 0.30, 'low');
        $this->record_placement($fixture, 'teacher_override', $profile, $now - 50, 0.70, 0.90, 'override');
        $this->record_placement($fixture, 'recorded', $profile, $now - (181 * DAYSECS), 0.70, 0.90, 'stale');
        $this->record_placement($fixture, 'recorded', $profile, $now - 60, 0.70, 0.90, 'valid');

        $preview = \local_flwcupkp\local\placement_diagnostic_service::preview_reprocess(
            $fixture['courseid'],
            'UA2P',
            $fixture['frameworkid'],
            $fixture['userid'],
            20,
            0
        );

        $this->assertSame(7, $preview['summary']['records_seen'], json_encode($preview));
        $this->assertSame(1, $preview['summary']['policy_states']['NOT_TAKEN']);
        $this->assertSame(1, $preview['summary']['policy_states']['VALID']);
        $this->assertSame(1, $preview['summary']['policy_states']['STALE']);
        $this->assertSame(2, $preview['summary']['policy_states']['INCOMPLETE']);
        $this->assertSame(1, $preview['summary']['policy_states']['LOW_CONFIDENCE']);
        $this->assertSame(1, $preview['summary']['policy_states']['TEACHER_OVERRIDE']);
        $this->assertSame(1, $preview['summary']['policy_cases']['refused']);
        $this->assertSame(1, $preview['summary']['policy_cases']['abandoned']);
        $this->assertSame(1, $preview['summary']['policy_cases']['partial']);
        $this->assertSame(1, $preview['summary']['policy_cases']['teacher_override']);
        $this->assertSame(1, $preview['summary']['policy_cases']['stale_placement']);
        $this->assertGreaterThanOrEqual(3, $preview['summary']['skipped_by_policy']);
        $this->assertSame(0, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame(0, $DB->count_records('flwcupkp_placement_state'));
    }

    /**
     * Create a C-UP-KP fixture.
     *
     * @param string $unitcode
     * @param bool $placementobject
     * @return array
     */
    private function create_fixture(string $unitcode, bool $placementobject): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;

        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-A2-' . $suffix,
            'name' => 'A2 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-A2-' . $suffix,
            'title' => 'A2 Knowledge Point',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $objectid = 0;
        $mapid = 0;
        if ($placementobject) {
            $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
                'frameworkid' => $frameworkid,
                'externalid' => 'PLACEMENT-A2-' . $suffix,
                'courseid' => (int)$course->id,
                'unitcode' => $unitcode,
                'lesson' => 'A2',
                'objecttype' => 'placement',
                'title' => 'A2 Placement Diagnostic',
                'cmid' => 0,
                'sourceid' => 'PLACEMENT-A2-' . $suffix,
                'purpose' => 'placement',
                'evidencestrength' => 'recognition',
                'role' => 'assessment',
                'metadatajson' => json_encode([
                    'placement_dimensions' => ['reading'],
                ]),
            ]);
            $mapid = (int)$DB->insert_record('flwcupkp_object_map', (object)[
                'objectid' => $objectid,
                'targettype' => 'kp',
                'targetid' => $kpid,
                'role' => 'assessment',
                'evidencestrength' => 'recognition',
            ]);
        }

        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'kpid' => $kpid,
            'unitcode' => $unitcode,
            'objectid' => $objectid,
            'mapid' => $mapid,
        ];
    }

    /**
     * Record one History V1 placement fact.
     *
     * @param array $fixture
     * @param string $status
     * @param array $profile
     * @param int $time
     * @param float $score
     * @param float $confidence
     * @param string $suffix
     * @return int
     */
    private function record_placement(array $fixture, string $status, array $profile, int $time, float $score,
            float $confidence, string $suffix = 'one'): int {
        $sourcekey = \local_flwhistory\local\source_identity::make_key(
            'flwplacement',
            'placement',
            (string)$fixture['userid'] . ':' . (string)$fixture['courseid'] . ':' . $suffix,
            (string)$time,
            $status
        );
        return \local_flwhistory\local\placement_history_service::record_placement([
            'sourcekey' => $sourcekey,
            'sourcefactkey' => $sourcekey,
            'sourcesystem' => 'flwplacement',
            'sourcefamily' => 'placement',
            'sourcetype' => 'placement',
            'sourceid' => (string)$fixture['userid'],
            'sourceversion' => (string)$time,
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'placementstatus' => $status,
            'score' => $score,
            'confidence' => $confidence,
            'profilejson' => $profile,
            'placementtime' => $time,
        ]);
    }
}

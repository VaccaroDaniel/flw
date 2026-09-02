<?php
// PHPUnit tests for Program 3 Gate A5 continuous adaptive paths.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A5 controlled adaptive recommendation tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\adaptive_path_engine_service::class)]
class adaptive_path_engine_service_test extends \advanced_testcase {
    public function test_contract_and_status_freeze_a5_boundary(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\adaptive_path_engine_service::contract();
        $status = \local_flwcupkp\local\adaptive_path_engine_service::status(
            (int)$course->id, 'UA5S', 0, 20
        );

        $this->assertSame('P3_A5', $contract['gate']);
        $this->assertSame('FLW_CUPKP_CONTINUOUS_ADAPTIVE_PATH_ENGINE_V1', $contract['version']);
        $this->assertSame('cupkp-continuous-adaptive-path-engine-v1', $contract['policy_version']);
        $this->assertSame([
            'ADVANCE', 'SKIP', 'EXTRA_PRACTICE', 'REMEDIATION', 'REVIEW', 'RETRY', 'REASSESS', 'REPRIORITIZE',
        ], $contract['actions']);
        $this->assertSame(['flwcupkp_recommend', 'flwcupkp_audit'], $contract['write_boundary']);
        $this->assertContains('inaccessible_activity_can_never_become_next', $contract['hard_invariants']);
        $this->assertContains('mastery_state_mutation', $contract['does_not_do']);
        $this->assertContains('history_v1_source_mutation', $contract['does_not_do']);
        $this->assertSame('A5B', $contract['next_allowed_gate']);

        $this->assertSame('CupkpContinuousAdaptivePathEngineStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertTrue($status['schema']['ready']);
        $this->assertTrue($status['files']['present']['adaptive_path.php']);
        $this->assertTrue($status['files']['present']['cli/adaptive_path.php']);
        $this->assertTrue($status['surface']['methods']
            [\local_flwcupkp\local\adaptive_path_engine_service::class . '::apply_learner_path']);
        $this->assertTrue($status['controlled_apply_supported']);
        $this->assertTrue($status['continuous_adaptation_allowed']);
        $this->assertSame('A5B', $status['next_allowed_gate']);
        $this->assertSame($before, $this->mutation_counts());
        $this->assertSame(0, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_preview_is_read_only_and_respects_a4b_eligible_activity(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA5P');
        $this->save_goal($fixture, $fixture['kpids']);
        $this->insert_placement_state($fixture);
        $page = $this->create_mapped_page($fixture, $fixture['kpids'][0], 'A5 Preview Page', true);
        $before = $this->mutation_counts();

        $path = \local_flwcupkp\local\adaptive_path_engine_service::learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50
        );

        $this->assertSame('CupkpLearnerContinuousAdaptivePath', $path['type']);
        $this->assertSame('next_activity_ready', $path['path_status']);
        $this->assertSame('ready_to_apply', $path['recommendation_status']);
        $this->assertContains($path['recommendation']['action'],
            \local_flwcupkp\local\adaptive_path_engine_service::ACTIONS);
        $this->assertSame($page['objectid'], $path['recommendation']['selected_activity']['objectid']);
        $this->assertSame($page['cmid'], $path['recommendation']['selected_activity']['cmid']);
        $this->assertTrue($path['recommendation']['selected_activity']['eligible']);
        $this->assertSame(64, strlen($path['recommendation']['sourcehash']));
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $path['recommendation']['snapshot']['policy_versions']['history_contract']
        );
        $this->assertSame(
            \local_flwcupkp\local\candidate_activity_resolution_service::RESOLUTION_POLICY_VERSION,
            $path['recommendation']['snapshot']['policy_versions']['activity_resolution_policy']
        );
        $this->assertTrue($path['read_only']);
        $this->assertFalse($path['recommendation_writes_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_controlled_apply_is_idempotent_and_preserves_full_snapshot(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA5I');
        $this->save_goal($fixture, $fixture['kpids']);
        $this->insert_placement_state($fixture);
        $page = $this->create_mapped_page($fixture, $fixture['kpids'][0], 'A5 Apply Page', true);
        $before = $this->mutation_counts();

        $first = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50,
            'PHPUnit controlled A5 apply'
        );
        $afterfirst = $this->mutation_counts();
        $second = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50,
            'PHPUnit repeated A5 apply'
        );

        $this->assertSame('applied', $first['status']);
        $this->assertSame('unchanged', $second['status']);
        $this->assertSame($first['recommendationid'], $second['recommendationid']);
        $this->assertSame($first['sourcehash'], $second['sourcehash']);
        $this->assertSame(1, $afterfirst['recommend'] - $before['recommend']);
        $this->assertSame(1, $afterfirst['audit'] - $before['audit']);
        $this->assertSame($afterfirst, $this->mutation_counts());
        $this->assertSame($before['evidence'], $afterfirst['evidence']);
        $this->assertSame($before['state'], $afterfirst['state']);
        $this->assertSame($before['goal'], $afterfirst['goal']);
        $this->assertSame($before['placement'], $afterfirst['placement']);

        $row = $DB->get_record('flwcupkp_recommend', ['id' => $first['recommendationid']], '*', MUST_EXIST);
        $snapshot = json_decode((string)$row->prereqinfo, true);
        $this->assertSame($fixture['courseid'], (int)$row->courseid);
        $this->assertSame($fixture['unitcode'], $row->unitcode);
        $this->assertSame($page['objectid'], (int)$row->objectid);
        $this->assertSame($page['cmid'], (int)$row->cmid);
        $this->assertSame(
            \local_flwcupkp\local\adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            $row->policyversion
        );
        $this->assertSame(64, strlen($row->sourcehash));
        $this->assertArrayHasKey('goal_version', $snapshot['snapshot']);
        $this->assertArrayHasKey('curriculum_version', $snapshot['snapshot']);
        $this->assertArrayHasKey('state_snapshot', $snapshot['snapshot']);
        $this->assertArrayHasKey('policy_versions', $snapshot['snapshot']);
        $this->assertArrayHasKey('selected_activity', $snapshot['snapshot']);
        $this->assertArrayHasKey('candidate_summary', $snapshot['snapshot']);
        $this->assertNotEmpty($snapshot['reason_codes']);
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'adaptive_path_recommendation_applied',
            'targettype' => 'user',
            'targetid' => $fixture['userid'],
        ]));
    }

    public function test_changed_resolution_supersedes_only_a5_rows_and_never_selects_hidden_activity(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA5R', 2);
        $this->save_goal($fixture, $fixture['kpids']);
        $this->insert_placement_state($fixture);
        $firstpage = $this->create_mapped_page($fixture, $fixture['kpids'][0], 'A5 First Page', true);
        $secondpage = $this->create_mapped_page($fixture, $fixture['kpids'][1], 'A5 Fallback Page', true);
        $now = time();
        $legacyid = (int)$DB->insert_record('flwcupkp_recommend', (object)[
            'userid' => $fixture['userid'],
            'reason' => 'Legacy recommendation owned by the pre-A5 engine.',
            'status' => 'recommended',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $first = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50,
            'Initial A5 recommendation'
        );
        require_once($CFG->dirroot . '/course/lib.php');
        set_coursemodule_visible($firstpage['cmid'], 0);
        rebuild_course_cache($fixture['courseid'], true);
        $second = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50,
            'Eligibility changed after activity became hidden'
        );

        $this->assertSame('applied', $second['status']);
        $this->assertSame(1, $second['superseded']);
        $this->assertNotSame($first['sourcehash'], $second['sourcehash']);
        $this->assertSame($secondpage['cmid'], $second['recommendation']['cmid']);
        $this->assertNotSame($firstpage['cmid'], $second['recommendation']['cmid']);
        $this->assertSame('superseded', $DB->get_field('flwcupkp_recommend', 'status',
            ['id' => $first['recommendationid']]));
        $this->assertSame('recommended', $DB->get_field('flwcupkp_recommend', 'status', ['id' => $legacyid]));
        $current = \local_flwcupkp\local\adaptive_path_engine_service::current_recommendations(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode']
        );
        $this->assertCount(1, $current);
        $this->assertSame($second['recommendationid'], $current[0]['id']);
    }

    public function test_no_eligible_activity_persists_diagnostic_without_cmid(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA5D');
        $this->save_goal($fixture, $fixture['kpids']);
        $this->insert_placement_state($fixture);
        $beforestate = $DB->count_records('flwcupkp_state');

        $result = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 50,
            'Persist the bounded no-eligible-activity diagnostic'
        );

        $this->assertSame('applied', $result['status']);
        $this->assertSame('diagnostic_required', $result['preview']['path_status']);
        $this->assertContains($result['recommendation']['action'], ['REASSESS', 'REMEDIATION', 'REVIEW',
            'RETRY', 'REPRIORITIZE']);
        $this->assertSame(0, $result['recommendation']['objectid']);
        $this->assertSame(0, $result['recommendation']['cmid']);
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
    }

    /**
     * Create a course, learner, framework, and one or more KPs.
     *
     * @param string $unitcode
     * @param int $kpcount
     * @return array
     */
    private function create_fixture(string $unitcode, int $kpcount = 1): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-A5-' . $suffix,
            'name' => 'A5 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpids = [];
        for ($i = 0; $i < $kpcount; $i++) {
            $number = str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $kpids[] = (int)$DB->insert_record('flwcupkp_kp', (object)[
                'frameworkid' => $frameworkid,
                'externalid' => 'KP-A5-' . $number . '-' . $suffix,
                'title' => 'A5 Knowledge Point ' . $number,
                'language' => 'en',
                'cefr' => 'B1',
                'domain' => 'READ',
                'status' => 'published',
                'version' => '1.0',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'kpids' => $kpids,
            'unitcode' => $unitcode,
        ];
    }

    /**
     * Save a versioned A1 goal for the fixture.
     *
     * @param array $fixture
     * @param array $kpids
     */
    private function save_goal(array $fixture, array $kpids): void {
        \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'title' => 'A5 Adaptive Path Goal',
            'desiredprofile' => ['profile' => 'Open and complete the next eligible learning activity.'],
            'cefr' => 'B1',
            'flwstage' => 'FLW-STAGE-03',
            'purpose' => 'A5 PHPUnit path fixture',
            'weeklytarget' => 2,
            'kpids' => $kpids,
        ], 'TEACHER', 'PHPUnit A5 goal');
    }

    /**
     * Insert a valid A2 placement state.
     *
     * @param array $fixture
     */
    private function insert_placement_state(array $fixture): void {
        global $DB, $USER;

        $now = time();
        $payload = [
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'policystate' => 'VALID',
        ];
        $DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'sourcekey' => 'phpunit-a5-placement-' . $fixture['userid'],
            'sourcefactkey' => 'phpunit-a5-placement-' . $fixture['userid'] . '-' . $now,
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
                'targetid' => $fixture['kpids'][0],
            ]], JSON_UNESCAPED_SLASHES),
            'evidenceidsjson' => '[]',
            'diagnosticjson' => json_encode(['policy_case' => 'imported_history'], JSON_UNESCAPED_SLASHES),
            'policyversion' => \local_flwcupkp\local\placement_diagnostic_service::POLICY_VERSION,
            'checksum' => sha1(json_encode($payload, JSON_UNESCAPED_SLASHES)),
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int)($USER->id ?? 0),
        ]);
    }

    /**
     * Create and map a real Moodle page activity.
     *
     * @param array $fixture
     * @param int $kpid
     * @param string $name
     * @param bool $visible
     * @return array
     */
    private function create_mapped_page(array $fixture, int $kpid, string $name, bool $visible): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $fixture['courseid'],
            'name' => $name,
            'content' => 'Mapped Program 3 A5 C-UP-KP page.',
        ]);
        $cmid = (int)$page->cmid;
        if (!$visible) {
            set_coursemodule_visible($cmid, 0);
            rebuild_course_cache($fixture['courseid'], true);
        }
        $suffix = $fixture['unitcode'] . '-' . $kpid . '-' . $cmid;
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $fixture['frameworkid'],
            'externalid' => 'OBJ-A5-' . $suffix,
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'lesson' => 'A5',
            'objecttype' => 'page',
            'title' => $name,
            'cmid' => $cmid,
            'sourceid' => 'OBJ-A5-' . $suffix,
            'purpose' => 'practice',
            'evidencestrength' => 'guided_performance',
            'difficulty' => 0.4,
            'role' => 'practice',
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'practice',
            'evidencestrength' => 'guided_performance',
        ]);
        return ['objectid' => $objectid, 'cmid' => $cmid];
    }

    /**
     * Counts that prove the A5 write boundary.
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

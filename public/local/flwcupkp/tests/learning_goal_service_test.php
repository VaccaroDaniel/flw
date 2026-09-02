<?php
// PHPUnit tests for Program 3 Gate A1 competency-centered learning goals.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A1 learning-goal service tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\learning_goal_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\repository::class)]
class learning_goal_service_test extends \advanced_testcase {
    public function test_contract_and_status_are_ready_for_a2_without_adaptive_logic(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $contract = \local_flwcupkp\local\learning_goal_service::contract();
        $status = \local_flwcupkp\local\learning_goal_service::status((int)$course->id, 'UA1', 0, 20);

        $this->assertSame('P3_A1', $contract['gate']);
        $this->assertSame('FLW_CUPKP_COMPETENCY_CENTERED_LEARNING_GOAL_V1', $contract['version']);
        $this->assertSame('desired competency/skill profile', $contract['destination_model']['preferred']);
        $this->assertContains('STUDENT', $contract['sources']);
        $this->assertContains('TEACHER', $contract['sources']);
        $this->assertContains('INSTITUTION', $contract['sources']);
        $this->assertTrue($contract['versioning']['goal_changes_create_versions']);
        $this->assertTrue($contract['versioning']['versions_do_not_erase_history_or_mastery']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertContains('mastery_state_mutation', $contract['does_not_do']);
        $this->assertSame('A2', $contract['next_allowed_gate']);

        $this->assertSame('CupkpLearningGoalStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(6, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertTrue($status['schema']['tables']['flwcupkp_goal']);
        $this->assertTrue($status['schema']['tables']['flwcupkp_goal_version']);
        $this->assertTrue($status['surface']['methods'][\local_flwcupkp\local\learning_goal_service::class . '::save_goal']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertSame('A2', $status['next_allowed_gate']);

        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_goal_changes_create_versions_without_erasing_mastery_or_history(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA1V');
        $now = time();
        $evidenceid = $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'unitcode' => 'UA1V',
            'evidencetype' => 'manual',
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
            'rawscore' => 0.9,
            'normalizedscore' => 0.9,
            'confidence' => 0.8,
            'evidencestrength' => 'controlled_recall',
            'provenance' => 'phpunit',
            'timecreated' => $now,
            'usermodified' => $fixture['userid'],
        ]);
        $stateid = $DB->insert_record('flwcupkp_state', (object)[
            'userid' => $fixture['userid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
            'masteryscore' => 0.9,
            'masterystate' => 'mastered',
            'confidence' => 0.8,
            'evidencecount' => 1,
            'retentionstate' => 'retained',
            'retentionconfidence' => 0.7,
            'timemodified' => $now,
        ]);
        $recommendid = $DB->insert_record('flwcupkp_recommend', (object)[
            'userid' => $fixture['userid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
            'reason' => 'Keep practicing',
            'status' => 'recommended',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $payload = [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => 'UA1V',
            'title' => 'A1 Reading Goal',
            'desiredprofile' => [
                'profile' => 'Read short academic texts independently',
                'skill' => 'main idea and detail tracking',
            ],
            'competencyids' => [$fixture['compid']],
            'upids' => [$fixture['upid']],
            'kpids' => [$fixture['kpid']],
            'cefr' => 'B1',
            'flwstage' => 'Stage-3',
            'purpose' => 'Prepare for unit reading tasks',
            'priorityskills' => ['reading', 'vocabulary'],
            'targetdate' => $now + (14 * DAYSECS),
            'weeklytarget' => 3.5,
        ];

        $first = \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], $payload,
            'STUDENT', 'initial learner goal');
        $unchanged = \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], $payload,
            'STUDENT', 'duplicate save');
        $payload['weeklytarget'] = 4.0;
        $payload['purpose'] = 'Teacher confirmed the reading target';
        $second = \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], $payload,
            'TEACHER', 'teacher confirmation');
        $current = \local_flwcupkp\local\learning_goal_service::current_goal(
            $fixture['userid'],
            $fixture['courseid'],
            'UA1V',
            $fixture['frameworkid'],
            10
        );

        $this->assertSame('saved', $first['status']);
        $this->assertSame(1, $first['version']);
        $this->assertSame('unchanged', $unchanged['status']);
        $this->assertSame(1, $unchanged['version']);
        $this->assertSame('saved', $second['status']);
        $this->assertSame(2, $second['version']);
        $this->assertSame(1, $DB->count_records('flwcupkp_goal'));
        $this->assertSame(2, $DB->count_records('flwcupkp_goal_version'));
        $this->assertSame(2, $DB->count_records('flwcupkp_audit',
            ['action' => 'learning_goal_version_created']));

        $this->assertTrue($current['has_goal']);
        $this->assertSame('TEACHER', $current['goal']['source']);
        $this->assertSame(2, $current['goal']['currentversion']);
        $this->assertSame(4.0, $current['goal']['weeklytarget']);
        $this->assertSame([$fixture['compid']], $current['goal']['destination']['competencyids']);
        $this->assertSame([$fixture['upid']], $current['goal']['destination']['upids']);
        $this->assertSame([$fixture['kpid']], $current['goal']['destination']['kpids']);
        $this->assertSame(['reading', 'vocabulary'], $current['goal']['destination']['priorityskills']);
        $this->assertCount(2, $current['versions']);
        $this->assertSame(2, $current['versions'][0]['version']);
        $this->assertSame(1, $current['versions'][1]['version']);

        $state = $DB->get_record('flwcupkp_state', ['id' => $stateid], '*', MUST_EXIST);
        $this->assertSame('mastered', $state->masterystate);
        $this->assertSame('retained', $state->retentionstate);
        $this->assertTrue($DB->record_exists('flwcupkp_evidence', ['id' => $evidenceid]));
        $this->assertTrue($DB->record_exists('flwcupkp_recommend', ['id' => $recommendid]));
        $this->assertSame($first['before']['evidence'], $second['after']['evidence']);
        $this->assertSame($first['before']['state'], $second['after']['state']);
        $this->assertSame($first['before']['retention_state_rows'], $second['after']['retention_state_rows']);
        $this->assertFalse($second['state_changes_allowed']);
        $this->assertSame('A2', $second['next_allowed_gate']);
    }

    public function test_goal_options_and_class_summary_use_cupkp_targets_and_sources(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA1O');

        \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => 'UA1O',
            'desiredprofile' => ['profile' => 'Use target structures in short responses'],
            'competencyids' => [$fixture['compid']],
            'priorityskills' => ['speaking'],
            'weeklytarget' => 2,
        ], 'INSTITUTION', 'institution target');

        $options = \local_flwcupkp\local\learning_goal_service::goal_options(
            $fixture['courseid'],
            'UA1O',
            $fixture['frameworkid'],
            'UA1O',
            20
        );
        $summary = \local_flwcupkp\local\learning_goal_service::class_summary(
            $fixture['courseid'],
            'UA1O',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame(['STUDENT', 'TEACHER', 'INSTITUTION'], $options['sources']);
        $this->assertSame('A2', $options['next_allowed_gate']);
        $this->assertSame($fixture['compid'], $options['competencies'][0]['id']);
        $this->assertSame($fixture['upid'], $options['use_points'][0]['id']);
        $this->assertSame($fixture['kpid'], $options['knowledge_points'][0]['id']);
        $this->assertSame(1, $summary['summary']['goals']);
        $this->assertSame(1, $summary['summary']['active']);
        $this->assertSame(1, $summary['summary']['versions']);
        $this->assertSame(1, $summary['summary']['institutionsourced']);
        $this->assertSame(1, $summary['summary']['competency_targets']);
        $this->assertSame(1, $summary['summary']['priority_skill_targets']);
        $this->assertTrue($summary['read_only']);
        $this->assertFalse($summary['state_changes_allowed']);
    }

    private function create_fixture(string $unitcode): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;

        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-A1-' . $suffix,
            'name' => 'A1 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'COMP-A1-' . $suffix,
            'title' => 'A1 Competency',
            'cefr' => 'B1',
            'stage' => 'Stage-3',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'UP-A1-' . $suffix,
            'title' => 'A1 Use Point',
            'cefr' => 'B1',
            'languagemode' => 'reading',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-A1-' . $suffix,
            'title' => 'A1 Knowledge Point',
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
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'kpid' => $kpid,
        ];
    }
}

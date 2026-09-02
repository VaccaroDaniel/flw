<?php
// PHPUnit tests for Program 3 Gate A3 adaptive decision policy.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A3 adaptive decision policy tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\adaptive_decision_policy_service::class)]
class adaptive_decision_policy_service_test extends \advanced_testcase {
    public function test_contract_and_status_are_ready_for_a4_and_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\adaptive_decision_policy_service::contract();
        $status = \local_flwcupkp\local\adaptive_decision_policy_service::status((int)$course->id, 'UA3S', 0, 20);

        $this->assertSame('P3_A3', $contract['gate']);
        $this->assertSame('FLW_CUPKP_ADAPTIVE_DECISION_POLICY_V1', $contract['version']);
        $this->assertSame('cupkp-adaptive-decision-policy-v1', $contract['adaptive_policy_version']);
        $this->assertContains(\local_flwcupkp\local\learning_goal_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\placement_diagnostic_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\mastery_state_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\retention_review_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['depends_on']);
        $this->assertContains('arbitrary_hidden_thresholds', $contract['does_not_do']);
        $this->assertContains('moodle_activity_resolution', $contract['does_not_do']);
        $this->assertSame('A4', $contract['next_allowed_gate']);

        $policy = $contract['visible_policy'];
        $this->assertArrayHasKey('kp', $policy['thresholds']['mastery']);
        $this->assertArrayHasKey('low_below', $policy['thresholds']['confidence']);
        $this->assertContains('decision_priority_ascending', $policy['tie_breaking']);
        foreach ([
            'GOAL_REQUIRED',
            'PLACEMENT_REQUIRED',
            'REVIEW_REQUIRED',
            'REMEDIATION_REQUIRED',
            'RETRY_RECOMMENDED',
            'REASSESSMENT_RECOMMENDED',
            'PREREQUISITE_REQUIRED',
            'ADVANCE_READY',
            'FALLBACK_TEACHER_REVIEW',
        ] as $state) {
            $this->assertArrayHasKey($state, $policy['decision_states']);
        }

        $this->assertSame('CupkpAdaptiveDecisionPolicyStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertTrue($status['files']['present']['adaptive_decision.php']);
        $this->assertTrue($status['files']['present']['cli/adaptive_decision.php']);
        $this->assertTrue($status['surface']['methods']
            [\local_flwcupkp\local\adaptive_decision_policy_service::class . '::learner_decision']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertFalse($status['recommendation_writes_allowed']);
        $this->assertFalse($status['moodle_activity_resolution_allowed']);
        $this->assertSame('A4', $status['next_allowed_gate']);

        $this->assertSame($before, $this->mutation_counts());
        $this->assertSame(0, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_learner_without_goal_gets_goal_required_before_path_generation(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $before = $this->mutation_counts();

        $decision = \local_flwcupkp\local\adaptive_decision_policy_service::learner_decision(
            (int)$user->id,
            (int)$course->id,
            'UA3G',
            0,
            20
        );

        $this->assertSame('CupkpLearnerAdaptiveDecision', $decision['type']);
        $this->assertSame('GOAL_REQUIRED', $decision['decision']['code']);
        $this->assertSame('set_destination', $decision['decision']['action']);
        $this->assertNull($decision['next_target']);
        $this->assertFalse($decision['destination']['available']);
        $this->assertSame('not_allowed_until_A4B', $decision['decision']['activity_resolution']);
        $this->assertSame(40, strlen($decision['explainability']['decision_hash']));
        $this->assertContains('no_recommendation_row_write', $decision['explainability']['non_actions']);
        $this->assertTrue($decision['read_only']);
        $this->assertFalse($decision['state_changes_allowed']);
        $this->assertSame('A4', $decision['next_allowed_gate']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_goal_without_placement_gets_placement_required(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA3P');
        $this->save_goal($fixture, $fixture['userids'][0]);
        $before = $this->mutation_counts();

        $decision = \local_flwcupkp\local\adaptive_decision_policy_service::learner_decision(
            $fixture['userids'][0],
            $fixture['courseid'],
            'UA3P',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame('PLACEMENT_REQUIRED', $decision['decision']['code']);
        $this->assertSame('take_placement', $decision['decision']['action']);
        $this->assertTrue($decision['destination']['available']);
        $this->assertSame([$fixture['kpid']], $decision['destination']['targets']['kpids']);
        $this->assertSame(0, $DB->count_records('flwcupkp_recommend'));
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_low_confidence_placement_gets_explainable_teacher_review(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA3L');
        $this->save_goal($fixture, $fixture['userids'][0]);
        $this->insert_placement_state($fixture, $fixture['userids'][0], 'LOW_CONFIDENCE', 0.54);
        $before = $this->mutation_counts();

        $decision = \local_flwcupkp\local\adaptive_decision_policy_service::learner_decision(
            $fixture['userids'][0],
            $fixture['courseid'],
            'UA3L',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame('PLACEMENT_REVIEW', $decision['decision']['code']);
        $this->assertSame('teacher_review', $decision['decision']['action']);
        $this->assertSame('urgent', $decision['decision']['urgency']);
        $this->assertSame(0.60,
            $decision['explainability']['thresholds_used']['placement']['low_confidence_below']);
        $this->assertSame('LOW_CONFIDENCE',
            $decision['explainability']['source_snapshots']['placement']['policy_state']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_review_due_beats_mastery_gap_and_preserves_no_writes(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA3R');
        $this->save_goal($fixture, $fixture['userids'][0]);
        $this->insert_placement_state($fixture, $fixture['userids'][0], 'VALID', 0.86);
        $this->insert_state($fixture, $fixture['userids'][0], 'kp', $fixture['kpid'], 'introduced', 0.40, 0.82,
            'review_due');
        $before = $this->mutation_counts();

        $decision = \local_flwcupkp\local\adaptive_decision_policy_service::learner_decision(
            $fixture['userids'][0],
            $fixture['courseid'],
            'UA3R',
            $fixture['frameworkid'],
            20
        );

        $codes = array_column($decision['signals'], 'code');
        $this->assertSame('REVIEW_REQUIRED', $decision['decision']['code'], json_encode($decision));
        $this->assertContains('REMEDIATION_REQUIRED', $codes);
        $this->assertSame('kp', $decision['next_target']['type']);
        $this->assertSame($fixture['kpid'], $decision['next_target']['id']);
        $this->assertSame('not_allowed_until_A4B', $decision['projected_roadmap'][0]['activity_resolution']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_class_summary_counts_attention_and_ready_decisions(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA3C', 2);
        $this->save_goal($fixture, $fixture['userids'][1]);
        $this->insert_placement_state($fixture, $fixture['userids'][1], 'VALID', 0.91);
        $this->insert_state($fixture, $fixture['userids'][1], 'kp', $fixture['kpid'], 'mastered', 0.92, 0.86,
            'retained');
        $before = $this->mutation_counts();

        $summary = \local_flwcupkp\local\adaptive_decision_policy_service::class_summary(
            $fixture['courseid'],
            'UA3C',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame('CupkpClassAdaptiveDecisionSummary', $summary['type']);
        $this->assertSame(2, $summary['summary']['learners']);
        $this->assertSame(1, $summary['summary']['decisions']['GOAL_REQUIRED']);
        $this->assertSame(1, $summary['summary']['decisions']['ADVANCE_READY']);
        $this->assertSame(1, $summary['summary']['needs_goal']);
        $this->assertSame(1, $summary['summary']['advance_ready']);
        $this->assertSame(1, $summary['summary']['urgency']['attention']);
        $this->assertSame(1, $summary['summary']['urgency']['ready']);
        $this->assertTrue($summary['read_only']);
        $this->assertFalse($summary['recommendation_writes_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    private function create_fixture(string $unitcode, int $usercount = 1): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $userids = [];
        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $userids[] = (int)$user->id;
        }

        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-A3-' . $suffix,
            'name' => 'A3 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'COMP-A3-' . $suffix,
            'title' => 'A3 Competency',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'UP-A3-' . $suffix,
            'title' => 'A3 Use Point',
            'cefr' => 'B1',
            'languagemode' => 'reading',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-A3-' . $suffix,
            'title' => 'A3 Knowledge Point',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'OBJ-A3-' . $suffix,
            'courseid' => (int)$course->id,
            'unitcode' => $unitcode,
            'lesson' => 'A3',
            'objecttype' => 'quiz',
            'title' => 'A3 Mapped Object',
            'cmid' => 12000 + (int)$course->id,
            'sourceid' => 'OBJ-A3-' . $suffix,
            'purpose' => 'ASSESSMENT',
            'evidencestrength' => 'independent_performance',
            'role' => 'assesses',
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'assesses',
            'evidencestrength' => 'independent_performance',
        ]);

        return [
            'courseid' => (int)$course->id,
            'userids' => $userids,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'kpid' => $kpid,
            'objectid' => $objectid,
        ];
    }

    private function save_goal(array $fixture, int $userid): void {
        \local_flwcupkp\local\learning_goal_service::save_goal($userid, [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $this->unitcode_from_fixture($fixture),
            'title' => 'A3 Reading Goal',
            'desiredprofile' => [
                'profile' => 'Read mapped unit texts independently',
            ],
            'kpids' => [$fixture['kpid']],
            'cefr' => 'B1',
            'flwstage' => 'FLW-STAGE-03',
            'purpose' => 'A3 decision fixture',
            'weeklytarget' => 2,
        ], 'TEACHER', 'phpunit A3 goal');
    }

    private function unitcode_from_fixture(array $fixture): string {
        global $DB;

        $object = $DB->get_record('flwcupkp_object', ['id' => $fixture['objectid']], 'unitcode', MUST_EXIST);
        return (string)$object->unitcode;
    }

    private function insert_placement_state(array $fixture, int $userid, string $policystate, float $confidence): int {
        global $DB, $USER;

        $now = time();
        $state = strtoupper($policystate);
        $payload = [
            'userid' => $userid,
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $this->unitcode_from_fixture($fixture),
            'policystate' => $state,
            'confidence' => $confidence,
        ];
        return (int)$DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $userid,
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $payload['unitcode'],
            'sourcekey' => 'phpunit-a3-placement-' . $userid,
            'sourcefactkey' => 'phpunit-a3-placement-' . $userid . '-' . $now . '-' . $state,
            'placementstatus' => 'recorded',
            'policystate' => $state,
            'sourcecategory' => $state === 'TEACHER_OVERRIDE' ? 'teacher_override' : 'imported_history',
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'score' => 0.82,
            'confidence' => $confidence,
            'placementtime' => $now,
            'staleafter' => $now + (180 * DAYSECS),
            'assesseddimensionsjson' => json_encode([[
                'key' => 'reading',
                'score' => 0.82,
                'targettype' => 'kp',
                'targetid' => $fixture['kpid'],
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

    private function insert_state(array $fixture, int $userid, string $targettype, int $targetid, string $masterystate,
            float $score, float $confidence, string $retentionstate): int {
        global $DB;

        $now = time();
        $hash = sha1($userid . ':' . $targettype . ':' . $targetid . ':' . $masterystate . ':' . $score);
        return (int)$DB->insert_record('flwcupkp_state', (object)[
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'masteryscore' => $score,
            'masterystate' => $masterystate,
            'confidence' => $confidence,
            'evidencecount' => 1,
            'lastevidence' => $now - DAYSECS,
            'lastsuccess' => $score >= 0.80 ? $now - DAYSECS : null,
            'nextreview' => $retentionstate === 'review_due' ? $now - HOURSECS : $now + (7 * DAYSECS),
            'manualoverride' => 0,
            'overridereason' => null,
            'ruleversion' => 'default-v1',
            'policyversion' => \local_flwcupkp\local\mastery_engine::POLICY_VERSION,
            'trend' => 'stable',
            'evidencehash' => $hash,
            'evidenceidsjson' => '[]',
            'calculatedtime' => $now,
            'retentionstate' => $retentionstate,
            'retentionconfidence' => 0.78,
            'retentionnextreview' => $retentionstate === 'review_due' ? $now - HOURSECS : $now + (7 * DAYSECS),
            'retentionlastretrieval' => $now - DAYSECS,
            'retentionretrievalcount' => 1,
            'retentionpolicyversion' => \local_flwcupkp\local\retention_review_service::RETENTION_POLICY_VERSION,
            'retentionevidencehash' => sha1('retention:' . $hash),
            'retentionevidenceidsjson' => '[]',
            'retentioncalculatedtime' => $now,
            'timemodified' => $now,
        ]);
    }

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

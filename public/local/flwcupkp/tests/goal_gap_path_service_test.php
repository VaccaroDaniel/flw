<?php
// PHPUnit tests for Program 3 Gate A4 goal-gap initial path.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A4 goal-gap and initial personalized path tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\goal_gap_path_service::class)]
class goal_gap_path_service_test extends \advanced_testcase {
    public function test_contract_and_status_are_ready_for_a4b_and_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\goal_gap_path_service::contract();
        $status = \local_flwcupkp\local\goal_gap_path_service::status((int)$course->id, 'UA4S', 0, 20);

        $this->assertSame('P3_A4', $contract['gate']);
        $this->assertSame('FLW_CUPKP_GOAL_GAP_INITIAL_PATH_V1', $contract['version']);
        $this->assertSame('cupkp-goal-gap-initial-path-v1', $contract['path_policy_version']);
        $this->assertContains(\local_flwcupkp\local\adaptive_decision_policy_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['depends_on']);
        foreach ([
            'mastery_deficit',
            'confidence_deficit',
            'missing_performance_mode',
            'retention_verification',
            'missing_prerequisite',
            'goal_priority',
        ] as $dimension) {
            $this->assertContains($dimension, $contract['gap_dimensions']);
        }
        $this->assertContains('moodle_activity_resolution', $contract['does_not_do']);
        $this->assertContains('continuous_adaptation', $contract['does_not_do']);
        $this->assertSame('A4B', $contract['next_allowed_gate']);

        $this->assertSame('CupkpGoalGapInitialPathStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertTrue($status['files']['present']['initial_path.php']);
        $this->assertTrue($status['files']['present']['cli/initial_path.php']);
        $this->assertTrue($status['surface']['methods']
            [\local_flwcupkp\local\goal_gap_path_service::class . '::learner_path']);
        $this->assertFalse($status['moodle_activity_resolution_allowed']);
        $this->assertFalse($status['continuous_adaptation_allowed']);
        $this->assertFalse($status['path_persistence_allowed']);
        $this->assertSame('A4B', $status['next_allowed_gate']);

        $this->assertSame($before, $this->mutation_counts());
        $this->assertSame(0, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_missing_mandatory_prerequisite_becomes_explainable_next_target(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4P');
        $this->save_goal($fixture, 'competency');
        $this->insert_placement_state($fixture, 'VALID', 0.88);
        $before = $this->mutation_counts();

        $path = \local_flwcupkp\local\goal_gap_path_service::learner_path(
            $fixture['userid'],
            $fixture['courseid'],
            'UA4P',
            $fixture['frameworkid'],
            50
        );

        $this->assertSame('CupkpLearnerGoalGapInitialPath', $path['type']);
        $this->assertSame('blocked_by_prerequisite', $path['path_status'], json_encode($path));
        $this->assertSame('kp', $path['next_target']['type']);
        $this->assertSame($fixture['prereqkpid'], $path['next_target']['id']);
        $this->assertSame(1, $path['goal_gap_analysis']['summary']['blocked_by_prerequisite']['kp']);
        $this->assertSame(1, $path['goal_gap_analysis']['summary']['blocked_by_prerequisite']['up']);
        $this->assertSame(1, $path['goal_gap_analysis']['summary']['blocked_by_prerequisite']['competency']);
        $this->assertNotEmpty($path['goal_gap_analysis']['blocked_by_prerequisite']['kp'][0]['prerequisites']['blocking']);
        $this->assertSame('PREREQUISITE_REQUIRED', $path['candidate_next_targets'][0]['code']);
        $this->assertSame('repair_prerequisite', $path['candidate_next_targets'][0]['action']);
        $this->assertSame('not_allowed_until_A4B', $path['candidate_next_targets'][0]['activity_resolution']);
        $this->assertSame('not_enabled_until_A5', $path['candidate_next_targets'][0]['continuous_adaptation']);
        $this->assertContains('no_moodle_activity_resolution', $path['explainability']['non_actions']);
        $this->assertContains('no_continuous_adaptation', $path['explainability']['non_actions']);
        $this->assertSame(40, strlen($path['explainability']['path_hash']));
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_satisfied_goal_path_is_destination_ready(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4D', false);
        $this->save_goal($fixture, 'kp');
        $this->insert_placement_state($fixture, 'VALID', 0.93);
        $this->insert_evidence($fixture, 'kp', $fixture['kpid'], 0.91, 'independent_production');
        $this->insert_state($fixture, 'kp', $fixture['kpid'], 'mastered', 0.91, 0.86, 'retained');
        $before = $this->mutation_counts();

        $path = \local_flwcupkp\local\goal_gap_path_service::learner_path(
            $fixture['userid'],
            $fixture['courseid'],
            'UA4D',
            $fixture['frameworkid'],
            50
        );

        $this->assertSame('destination_ready', $path['path_status'], json_encode($path));
        $this->assertNull($path['next_target']);
        $this->assertSame(1, $path['goal_gap_analysis']['summary']['satisfied']['kp']);
        $this->assertSame(0, $path['goal_gap_analysis']['summary']['missing_total']);
        $this->assertSame('DESTINATION_READY', $path['projected_roadmap'][0]['code']);
        $this->assertSame('DESTINATION', $path['projected_roadmap'][1]['code']);
        $this->assertTrue($path['read_only']);
        $this->assertFalse($path['recommendation_writes_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_class_summary_counts_path_statuses_and_gap_totals(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4C', true, 2);
        $this->save_goal($fixture, 'competency', $fixture['userids'][0]);
        $this->insert_placement_state($fixture, 'VALID', 0.88, $fixture['userids'][0]);
        $this->save_goal($fixture, 'prereqkp', $fixture['userids'][1]);
        $this->insert_placement_state($fixture, 'VALID', 0.92, $fixture['userids'][1]);
        $this->insert_evidence($fixture, 'kp', $fixture['prereqkpid'], 0.91, 'independent_production',
            $fixture['userids'][1]);
        $this->insert_state($fixture, 'kp', $fixture['prereqkpid'], 'mastered', 0.91, 0.86, 'retained',
            $fixture['userids'][1]);
        $before = $this->mutation_counts();

        $summary = \local_flwcupkp\local\goal_gap_path_service::class_summary(
            $fixture['courseid'],
            'UA4C',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame('CupkpClassGoalGapInitialPathSummary', $summary['type']);
        $this->assertSame(2, $summary['summary']['learners']);
        $this->assertSame(1, $summary['summary']['blocked_by_prerequisite']);
        $this->assertSame(1, $summary['summary']['destination_ready']);
        $this->assertSame(1, $summary['summary']['blocked_kp']);
        $this->assertSame(1, $summary['summary']['satisfied_kp']);
        $this->assertSame(1, $summary['summary']['next_target_count']);
        $this->assertFalse($summary['moodle_activity_resolution_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    private function create_fixture(string $unitcode, bool $withprereq = true, int $usercount = 1): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $users = [];
        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $users[] = (int)$user->id;
        }

        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-A4-' . $suffix,
            'name' => 'A4 Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'COMP-A4-' . $suffix,
            'title' => 'A4 Competency',
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
            'externalid' => 'UP-A4-' . $suffix,
            'title' => 'A4 Use Point',
            'cefr' => 'B1',
            'languagemode' => 'reading',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-A4-' . $suffix,
            'title' => 'A4 Knowledge Point',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $prereqkpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-A4-PREREQ-' . $suffix,
            'title' => 'A4 Prerequisite Knowledge Point',
            'language' => 'en',
            'cefr' => 'A2',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $compid,
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
        if ($withprereq) {
            $DB->insert_record('flwcupkp_kp_prereq', (object)[
                'kpid' => $kpid,
                'prereqkpid' => $prereqkpid,
                'relationshiptype' => 'prerequisite',
                'strength' => 1,
                'requirement' => 'mandatory',
            ]);
        }
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'OBJ-A4-' . $suffix,
            'courseid' => (int)$course->id,
            'unitcode' => $unitcode,
            'lesson' => 'A4',
            'objecttype' => 'quiz',
            'title' => 'A4 Mapped Object',
            'cmid' => 14000 + (int)$course->id,
            'sourceid' => 'OBJ-A4-' . $suffix,
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
            'userid' => $users[0],
            'userids' => $users,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'kpid' => $kpid,
            'prereqkpid' => $prereqkpid,
            'objectid' => $objectid,
            'unitcode' => $unitcode,
        ];
    }

    private function save_goal(array $fixture, string $targettype, ?int $userid = null): void {
        $userid = $userid ?? $fixture['userid'];
        $payload = [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'title' => 'A4 Reading Goal',
            'desiredprofile' => [
                'profile' => 'Read mapped unit texts independently',
            ],
            'cefr' => 'B1',
            'flwstage' => 'FLW-STAGE-03',
            'purpose' => 'A4 path fixture',
            'weeklytarget' => 2,
        ];
        if ($targettype === 'competency') {
            $payload['competencyids'] = [$fixture['compid']];
        } else if ($targettype === 'up') {
            $payload['upids'] = [$fixture['upid']];
        } else if ($targettype === 'prereqkp') {
            $payload['kpids'] = [$fixture['prereqkpid']];
        } else {
            $payload['kpids'] = [$fixture['kpid']];
        }
        \local_flwcupkp\local\learning_goal_service::save_goal($userid, $payload, 'TEACHER', 'phpunit A4 goal');
    }

    private function insert_placement_state(array $fixture, string $policystate, float $confidence,
            ?int $userid = null): int {
        global $DB, $USER;

        $userid = $userid ?? $fixture['userid'];
        $now = time();
        $state = strtoupper($policystate);
        $payload = [
            'userid' => $userid,
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'policystate' => $state,
            'confidence' => $confidence,
        ];
        return (int)$DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $userid,
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'sourcekey' => 'phpunit-a4-placement-' . $userid,
            'sourcefactkey' => 'phpunit-a4-placement-' . $userid . '-' . $now . '-' . $state,
            'placementstatus' => 'recorded',
            'policystate' => $state,
            'sourcecategory' => 'imported_history',
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

    private function insert_evidence(array $fixture, string $targettype, int $targetid, float $score,
            string $mode, ?int $userid = null): int {
        global $DB;

        $userid = $userid ?? $fixture['userid'];
        return (int)$DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $userid,
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'objectid' => $fixture['objectid'],
            'sourceattempt' => 'phpunit-a4-attempt-' . $userid . '-' . $targettype . '-' . $targetid,
            'evidencetype' => 'history_v1_quiz_attempt',
            'targettype' => $targettype,
            'targetid' => $targetid,
            'rawscore' => $score,
            'normalizedscore' => $score,
            'rubricjson' => json_encode([
                'cupkp_c3b_semantics' => [
                    'result_state' => 'positive',
                    'performance_mode' => $mode,
                ],
            ], JSON_UNESCAPED_SLASHES),
            'assessortype' => 'system',
            'confidence' => 0.86,
            'evidencestrength' => 'independent_performance',
            'provenance' => \local_flwcupkp\local\history_evidence_adapter::PROVENANCE,
            'sourceref' => 'phpunit-a4',
            'overrideflag' => 0,
            'timecreated' => time(),
            'usermodified' => 0,
        ]);
    }

    private function insert_state(array $fixture, string $targettype, int $targetid, string $masterystate,
            float $score, float $confidence, string $retentionstate, ?int $userid = null): int {
        global $DB;

        $userid = $userid ?? $fixture['userid'];
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
            'retentionretrievalcount' => 2,
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

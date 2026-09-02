<?php
// PHPUnit tests for Program 3 Gate A4B candidate activity resolution.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A4B candidate eligibility and Moodle activity resolution tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\candidate_activity_resolution_service::class)]
class candidate_activity_resolution_service_test extends \advanced_testcase {
    public function test_contract_and_status_are_ready_for_a5_and_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\candidate_activity_resolution_service::contract();
        $status = \local_flwcupkp\local\candidate_activity_resolution_service::status((int)$course->id, 'UA4BS',
            0, 20);

        $this->assertSame('P3_A4B', $contract['gate']);
        $this->assertSame('FLW_CUPKP_CANDIDATE_ACTIVITY_RESOLUTION_V1', $contract['version']);
        $this->assertSame('cupkp-candidate-activity-resolution-v1', $contract['resolution_policy_version']);
        $this->assertContains(\local_flwcupkp\local\goal_gap_path_service::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\content_evidence_mapping_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        foreach ([
            'target',
            'curriculum_validity',
            'prerequisite',
            'world_stage_course',
            'enrollment',
            'moodle_availability',
            'visibility',
            'dates',
            'attempts',
            'teacher_restrictions',
            'device_capability',
            'diversity',
            'eligible_activities',
        ] as $stage) {
            $this->assertContains($stage, $contract['pipeline']);
        }
        $this->assertSame('inaccessible_activity_can_never_become_next', $contract['hard_invariant']);
        $this->assertContains('recommendation_row_writes', $contract['does_not_do']);
        $this->assertContains('continuous_adaptation', $contract['does_not_do']);
        $this->assertSame('A5', $contract['next_allowed_gate']);

        $this->assertSame('CupkpCandidateActivityResolutionStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertTrue($status['files']['present']['activity_resolution.php']);
        $this->assertTrue($status['files']['present']['cli/activity_resolution.php']);
        $this->assertTrue($status['surface']['methods']
            [\local_flwcupkp\local\candidate_activity_resolution_service::class . '::learner_resolution']);
        $this->assertTrue($status['moodle_activity_resolution_allowed']);
        $this->assertFalse($status['continuous_adaptation_allowed']);
        $this->assertFalse($status['path_persistence_allowed']);
        $this->assertSame('A5', $status['next_allowed_gate']);

        $this->assertSame($before, $this->mutation_counts());
        $this->assertSame(0, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_status_findings_are_deduplicated_across_dependency_sources(): void {
        $method = new \ReflectionMethod(
            \local_flwcupkp\local\candidate_activity_resolution_service::class,
            'dedupe_findings'
        );
        $method->setAccessible(true);

        $findings = [[
            'severity' => 'WARNING',
            'source' => 'c4_lifecycle_governance',
            'code' => 'missing_evidence_route',
            'message' => 'UP FLW-REW-B1-UP-038-01 is published without an object evidence route.',
        ], [
            'severity' => 'MEDIUM',
            'source' => 'foundation_v1',
            'code' => 'missing_evidence_route',
            'message' => 'UP FLW-REW-B1-UP-038-01 is published without an object evidence route.',
        ], [
            'severity' => 'LOW',
            'source' => 'cm3_coverage_matrix',
            'code' => 'coverage_imbalance',
            'message' => 'Coverage areas are uneven enough to require governance review.',
        ]];

        $unique = $method->invoke(null, $findings);

        $this->assertCount(2, $unique);
        $this->assertSame('c4_lifecycle_governance', $unique[0]['source']);
        $this->assertSame('coverage_imbalance', $unique[1]['code']);
    }

    public function test_accessible_real_moodle_activity_becomes_next_activity(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4BE');
        $this->save_goal($fixture, [$fixture['kpid']]);
        $this->insert_placement_state($fixture, 'VALID', 0.90);
        $this->create_mapped_page($fixture, $fixture['kpid'], 'Eligible Page', true);
        $before = $this->mutation_counts();

        $resolution = \local_flwcupkp\local\candidate_activity_resolution_service::learner_resolution(
            $fixture['userid'],
            $fixture['courseid'],
            'UA4BE',
            $fixture['frameworkid'],
            50
        );

        $this->assertSame('CupkpLearnerCandidateActivityResolution', $resolution['type']);
        $this->assertSame('next_activity_ready', $resolution['resolution_status'], json_encode($resolution));
        $this->assertSame('kp', $resolution['next_target']['type']);
        $this->assertSame($fixture['kpid'], $resolution['next_target']['id']);
        $this->assertNotEmpty($resolution['next_activity']);
        $this->assertSame($fixture['pagecmids'][0], $resolution['next_activity']['cmid']);
        $this->assertSame('page', $resolution['next_activity']['modname']);
        $this->assertStringContainsString('/mod/page/view.php', $resolution['next_activity']['url']);
        $this->assertCount(1, $resolution['eligible_activities']);
        $this->assertCount(0, $resolution['ineligible_activities']);
        $this->assertFalse($resolution['fallback']['used']);
        $this->assertFalse($resolution['diagnostic']['required']);
        $this->assertSame('resolved', $resolution['projected_roadmap'][0]['activity_resolution']);
        $this->assertSame(40, strlen($resolution['explainability']['resolution_hash']));
        $this->assertContains('no_recommendation_row_write', $resolution['explainability']['non_actions']);
        $this->assertTrue($resolution['read_only']);
        $this->assertFalse($resolution['recommendation_writes_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_hidden_first_candidate_is_rejected_and_next_best_activity_is_selected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4BF', 2);
        $this->save_goal($fixture, [$fixture['kpids'][0], $fixture['kpids'][1]]);
        $this->insert_placement_state($fixture, 'VALID', 0.90);
        $hiddenobject = $this->create_mapped_page($fixture, $fixture['kpids'][0], 'Hidden Page', false);
        $visibleobject = $this->create_mapped_page($fixture, $fixture['kpids'][1], 'Visible Page', true);
        $before = $this->mutation_counts();

        $resolution = \local_flwcupkp\local\candidate_activity_resolution_service::learner_resolution(
            $fixture['userid'],
            $fixture['courseid'],
            'UA4BF',
            $fixture['frameworkid'],
            50
        );

        $this->assertSame('next_activity_ready', $resolution['resolution_status'], json_encode($resolution));
        $this->assertTrue($resolution['fallback']['used']);
        $this->assertSame($fixture['kpids'][1], $resolution['next_target']['id']);
        $this->assertSame($visibleobject['cmid'], $resolution['next_activity']['cmid']);
        $this->assertCount(1, $resolution['eligible_activities']);
        $this->assertCount(1, $resolution['ineligible_activities']);
        $this->assertSame($hiddenobject['objectid'], $resolution['ineligible_activities'][0]['objectid']);
        $this->assertContains('activity_hidden', $resolution['ineligible_activities'][0]['blocking_codes']);
        $this->assertNotSame($hiddenobject['cmid'], $resolution['next_activity']['cmid']);
        $this->assertSame('diagnostic_required',
            $resolution['target_resolutions'][0]['status'] === 'ineligible' ?
                $resolution['projected_roadmap'][0]['activity_resolution'] : 'unexpected');
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_exhausted_quiz_attempts_cannot_become_next_activity(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4BQ');
        $this->save_goal($fixture, [$fixture['kpid']]);
        $this->insert_placement_state($fixture, 'VALID', 0.90);
        $quizobject = $this->create_mapped_quiz($fixture, $fixture['kpid'], 1);
        $this->insert_finished_quiz_attempt($quizobject['quizid'], $fixture['userid'], $quizobject['cmid']);
        $before = $this->mutation_counts();

        $resolution = \local_flwcupkp\local\candidate_activity_resolution_service::learner_resolution(
            $fixture['userid'],
            $fixture['courseid'],
            'UA4BQ',
            $fixture['frameworkid'],
            50
        );

        $this->assertSame('diagnostic_required', $resolution['resolution_status'], json_encode($resolution));
        $this->assertNull($resolution['next_activity']);
        $this->assertCount(0, $resolution['eligible_activities']);
        $this->assertCount(1, $resolution['ineligible_activities']);
        $this->assertContains('quiz_attempts_exhausted', $resolution['ineligible_activities'][0]['blocking_codes']);
        $this->assertSame('NO_ELIGIBLE_ACTIVITY', $resolution['diagnostic']['code']);
        $this->assertSame(0, $DB->count_records('flwcupkp_recommend'));
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_class_summary_counts_next_activity_diagnostic_and_fallback(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture('UA4BC', 2, 2);
        $this->save_goal($fixture, [$fixture['kpids'][0]], $fixture['userids'][0]);
        $this->insert_placement_state($fixture, 'VALID', 0.90, $fixture['userids'][0]);
        $this->create_mapped_page($fixture, $fixture['kpids'][0], 'Class Eligible Page', true);

        $this->save_goal($fixture, [$fixture['kpids'][1]], $fixture['userids'][1]);
        $this->insert_placement_state($fixture, 'VALID', 0.90, $fixture['userids'][1]);
        $this->create_mapped_page($fixture, $fixture['kpids'][1], 'Class Hidden Page', false);
        $before = $this->mutation_counts();

        $summary = \local_flwcupkp\local\candidate_activity_resolution_service::class_summary(
            $fixture['courseid'],
            'UA4BC',
            $fixture['frameworkid'],
            20
        );

        $this->assertSame('CupkpClassCandidateActivityResolutionSummary', $summary['type']);
        $this->assertSame(2, $summary['summary']['learners']);
        $this->assertSame(1, $summary['summary']['next_activity_ready']);
        $this->assertSame(1, $summary['summary']['diagnostic_required']);
        $this->assertSame(1, $summary['summary']['eligible_activities']);
        $this->assertSame(1, $summary['summary']['ineligible_activities']);
        $this->assertSame(2, $summary['summary']['candidate_targets']);
        $this->assertFalse($summary['continuous_adaptation_allowed']);
        $this->assertSame($before, $this->mutation_counts());
    }

    private function create_fixture(string $unitcode, int $kpcount = 1, int $usercount = 1): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $userids = [];
        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $userids[] = (int)$user->id;
        }

        $now = time();
        $suffix = $unitcode . '-' . (int)$course->id;
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-A4B-' . $suffix,
            'name' => 'A4B Framework',
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
                'externalid' => 'KP-A4B-' . $number . '-' . $suffix,
                'title' => 'A4B Knowledge Point ' . $number,
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
            'userid' => $userids[0],
            'userids' => $userids,
            'frameworkid' => $frameworkid,
            'kpid' => $kpids[0],
            'kpids' => $kpids,
            'unitcode' => $unitcode,
            'pagecmids' => [],
        ];
    }

    private function save_goal(array $fixture, array $kpids, ?int $userid = null): void {
        $userid = $userid ?? $fixture['userid'];
        \local_flwcupkp\local\learning_goal_service::save_goal($userid, [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'title' => 'A4B Reading Goal',
            'desiredprofile' => [
                'profile' => 'Open the next resolved C-UP-KP activity.',
            ],
            'cefr' => 'B1',
            'flwstage' => 'FLW-STAGE-03',
            'purpose' => 'A4B path fixture',
            'weeklytarget' => 2,
            'kpids' => $kpids,
        ], 'TEACHER', 'phpunit A4B goal');
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
            'sourcekey' => 'phpunit-a4b-placement-' . $userid,
            'sourcefactkey' => 'phpunit-a4b-placement-' . $userid . '-' . $now . '-' . $state,
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

    private function create_mapped_page(array &$fixture, int $kpid, string $name, bool $visible): array {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $fixture['courseid'],
            'name' => $name,
            'content' => 'Mapped A4B C-UP-KP page.',
        ]);
        $cmid = (int)$page->cmid;
        if (!$visible) {
            set_coursemodule_visible($cmid, 0);
            rebuild_course_cache($fixture['courseid'], true);
        }
        $objectid = $this->insert_object($fixture, $kpid, $cmid, 'page', $name, 'practice', 'practice');
        $fixture['pagecmids'][] = $cmid;
        return [
            'objectid' => $objectid,
            'cmid' => $cmid,
        ];
    }

    private function create_mapped_quiz(array $fixture, int $kpid, int $attempts): array {
        global $DB;

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $fixture['courseid'],
            'name' => 'A4B Quiz ' . $kpid,
            'attempts' => $attempts,
            'sumgrades' => 1,
            'grade' => 1,
        ]);
        $DB->set_field('quiz', 'attempts', $attempts, ['id' => (int)$quiz->id]);
        $objectid = $this->insert_object($fixture, $kpid, (int)$quiz->cmid, 'quiz', $quiz->name, 'assessment',
            'assesses');
        return [
            'objectid' => $objectid,
            'cmid' => (int)$quiz->cmid,
            'quizid' => (int)$quiz->id,
        ];
    }

    private function insert_object(array $fixture, int $kpid, int $cmid, string $objecttype, string $title,
            string $purpose, string $role): int {
        global $DB;

        $suffix = $fixture['unitcode'] . '-' . $kpid . '-' . $cmid;
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $fixture['frameworkid'],
            'externalid' => 'OBJ-A4B-' . $suffix,
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'lesson' => 'A4B',
            'objecttype' => $objecttype,
            'title' => $title,
            'cmid' => $cmid,
            'sourceid' => 'OBJ-A4B-' . $suffix,
            'purpose' => $purpose,
            'evidencestrength' => $objecttype === 'quiz' ? 'independent_performance' : 'guided_performance',
            'difficulty' => 0.4,
            'role' => $role,
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => $role,
            'evidencestrength' => $objecttype === 'quiz' ? 'independent_performance' : 'guided_performance',
        ]);
        return $objectid;
    }

    private function insert_finished_quiz_attempt(int $quizid, int $userid, int $cmid): int {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/question/engine/lib.php');
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', \context_module::instance($cmid));
        $quba->set_preferred_behaviour('deferredfeedback');
        \question_engine::save_questions_usage_by_activity($quba);
        $now = time();
        return (int)$DB->insert_record('quiz_attempts', [
            'quiz' => $quizid,
            'userid' => $userid,
            'attempt' => 1,
            'uniqueid' => $quba->get_id(),
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => $now - HOURSECS,
            'timefinish' => $now - MINSECS,
            'timemodified' => $now - MINSECS,
            'timemodifiedoffline' => 0,
            'timecheckstate' => 0,
            'sumgrades' => 1,
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

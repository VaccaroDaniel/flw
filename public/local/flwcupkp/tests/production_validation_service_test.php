<?php
// PHPUnit tests for Program 3 Gate F1 integrated production validation.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\production_validation_service::class)]
class production_validation_service_test extends \advanced_testcase {
    public function test_contract_freezes_final_read_only_gate(): void {
        $contract = \local_flwcupkp\local\production_validation_service::contract();

        $this->assertSame('P3_F1', $contract['gate']);
        $this->assertSame('FLW_CUPKP_ADAPTIVE_UX_V3_PRODUCTION_VALIDATION_V1', $contract['version']);
        $this->assertCount(13, $contract['end_to_end']);
        $this->assertCount(9, $contract['historical_reproducibility_fields']);
        $this->assertCount(8, $contract['performance_measures']);
        $this->assertTrue($contract['validator_read_only']);
        $this->assertSame([], $contract['write_boundary']);
        $this->assertTrue($contract['final_gate']);
        $this->assertNull($contract['next_allowed_gate']);
        $this->assertSame('FLW_CUPKP_ADAPTIVE_UX_V3_FINAL_REPORT.md', $contract['final_report']);
    }

    public function test_empty_course_is_blocked_and_validation_is_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $before = $this->mutation_counts();

        $result = \local_flwcupkp\local\production_validation_service::validate_scope(
            (int)$course->id, 'F1EMPTY', 0, (int)$user->id, 50, false
        );

        $this->assertSame('not_production_ready', $result['status']);
        $this->assertFalse($result['production_ready']);
        $this->assertFalse($result['pipeline']['complete']);
        $this->assertSame(13, $result['pipeline']['total']);
        $this->assertGreaterThan(0, $result['findings_summary']['BLOCKER']);
        $this->assertTrue($result['mutation_counts']['unchanged']);
        $this->assertSame($before, $this->mutation_counts());
        $this->assertSame(0, $DB->count_records('flwcupkp_object'));
    }

    public function test_grade_event_without_attempt_or_completion_does_not_prove_learner_action(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'gradebook',
            'sourcetype' => 'grade_event',
            'sourceid' => 'f1-grade-only-' . $course->id,
            'sourceversion' => '1',
            'eventtype' => 'OFFICIAL_GRADE_CHANGED',
            'userid' => $user->id,
            'courseid' => $course->id,
            'eventtime' => time(),
            'status' => 'recorded',
            'normalizer' => 'f1_grade_only_test',
            'summaryjson' => ['source' => 'course_restore'],
        ]);

        $result = \local_flwcupkp\local\production_validation_service::validate_scope(
            (int)$course->id, 'F1GRADE', 0, (int)$user->id, 50, false
        );

        $this->assertSame(1, $result['history']['source_events']);
        $this->assertSame(0, $result['history']['attempts']);
        $this->assertSame(0, $result['history']['completion']);
        $this->assertSame(0, $result['history']['activity_facts']);
        $this->assertFalse($result['history']['pass']);
        $this->assertFalse($result['pipeline']['steps']['learner_acted']);
    }

    public function test_full_pipeline_is_reproducible_performant_and_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture();
        $this->save_goal($fixture);
        $this->insert_placement_state($fixture);

        $this->record_history_attempt($fixture, 1, 0.48, 1000);
        $this->apply_learning_updates($fixture, 'F1 first learner action');
        $firstpath = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 100,
            'F1 first adaptive decision'
        );
        $this->assertContains($firstpath['status'], ['applied', 'created', 'updated']);

        $this->record_history_attempt($fixture, 2, 0.92, 2000);
        $this->apply_learning_updates($fixture, 'F1 second learner action');
        $secondpath = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
            $fixture['userid'], $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], 100,
            'F1 adapted decision after new History V1 fact'
        );
        $this->assertContains($secondpath['status'], ['applied', 'created', 'updated']);

        $recommendations = $DB->get_records('flwcupkp_recommend', [
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'policyversion' => \local_flwcupkp\local\adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
        ]);
        $this->assertGreaterThanOrEqual(2, count($recommendations));
        $this->assertTrue($DB->record_exists('flwcupkp_recommend', [
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'unitcode' => $fixture['unitcode'],
            'status' => 'superseded',
        ]));

        $before = $this->mutation_counts();
        $result = \local_flwcupkp\local\production_validation_service::validate_scope(
            $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], $fixture['userid'], 100, true
        );

        $this->assertSame('production_ready', $result['status'], json_encode($result['findings']));
        $this->assertTrue($result['production_ready']);
        $this->assertTrue($result['pipeline']['complete']);
        $this->assertSame(13, $result['pipeline']['passed']);
        $this->assertTrue($result['historical_reproducibility']['pass']);
        $this->assertSame(0, $result['findings_summary']['BLOCKER']);
        $this->assertSame(0, $result['findings_summary']['HIGH']);
        $this->assertTrue($result['ownership_regression']['pass']);
        $this->assertTrue($result['security_privacy']['pass']);
        $this->assertTrue($result['invariants']['pass']);
        $this->assertTrue($result['performance']['within_budget']);
        $this->assertSame([
            'history_queries', 'evidence_normalization', 'state_calculation', 'graph_traversal',
            'eligibility', 'recommendation', 'timeline_render', 'teacher_view',
        ], array_keys($result['performance']['metrics']));
        $this->assertTrue($result['mutation_counts']['unchanged']);
        $this->assertSame($before, $this->mutation_counts());
    }

    private function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $unitcode = 'F1U' . (int)$course->id;
        $now = time();
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-' . $unitcode,
            'name' => 'F1 Integrated Framework',
            'version' => '1.0',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'COMP-' . $unitcode,
            'title' => 'F1 Integrated Competency',
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
            'externalid' => 'UP-' . $unitcode,
            'title' => 'F1 Integrated Use Point',
            'cefr' => 'B1',
            'languagemode' => 'reading',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-' . $unitcode,
            'title' => 'F1 Integrated Knowledge Point',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'READ',
            'status' => 'published',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $compid, 'upid' => $upid, 'role' => 'required', 'weight' => 1, 'sortorder' => 1,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid, 'kpid' => $kpid, 'role' => 'required', 'weight' => 1, 'sortorder' => 1,
        ]);

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'F1 Evidence Quiz',
            'sumgrades' => 10,
            'grade' => 10,
        ]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'F1 Follow-up Practice',
            'content' => 'Complete the adaptive follow-up practice.',
        ]);
        $activityid = 'ACT-' . $unitcode;
        $assessmentid = 'ASM-' . $unitcode;
        $quizobjectid = $this->insert_object(
            $frameworkid, (int)$course->id, $unitcode, (int)$quiz->cmid, $activityid,
            'quiz', 'assessment', 'assesses', 'independent_performance', $kpid
        );
        $pageactivityid = 'PRACTICE-' . $unitcode;
        $this->insert_object(
            $frameworkid, (int)$course->id, $unitcode, (int)$page->cmid, $pageactivityid,
            'page', 'practice', 'practice', 'guided_performance', $kpid
        );
        $this->cache_identity((int)$course->id, (int)$quiz->cmid, $unitcode, $activityid, $assessmentid);
        $this->cache_identity((int)$course->id, (int)$page->cmid, $unitcode, $pageactivityid, '');

        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'kpid' => $kpid,
            'unitcode' => $unitcode,
            'cmid' => (int)$quiz->cmid,
            'objectid' => $quizobjectid,
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
        ];
    }

    private function insert_object(int $frameworkid, int $courseid, string $unitcode, int $cmid,
            string $externalid, string $objecttype, string $purpose, string $role, string $strength,
            int $kpid): int {
        global $DB;

        $now = time();
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'lesson' => 'F1',
            'objecttype' => $objecttype,
            'title' => $externalid,
            'cmid' => $cmid,
            'sourceid' => $externalid,
            'purpose' => $purpose,
            'evidencestrength' => $strength,
            'difficulty' => 0.5,
            'role' => $role,
            'metadatajson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => $role,
            'evidencestrength' => $strength,
        ]);
        return $objectid;
    }

    private function cache_identity(int $courseid, int $cmid, string $unitcode, string $activityid,
            string $assessmentid): void {
        \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => $courseid,
            'cmid' => $cmid,
            'worldid' => 'WORLD-F1',
            'stageid' => 'STAGE-F1',
            'unitid' => $unitcode,
            'lessonid' => 'LESSON-F1',
            'componentid' => 'COMPONENT-F1',
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'sourcerevision' => 'f1-test-v1',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);
    }

    private function save_goal(array $fixture): void {
        \local_flwcupkp\local\learning_goal_service::save_goal($fixture['userid'], [
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'title' => 'F1 integrated learning goal',
            'desiredprofile' => ['profile' => 'Demonstrate the integrated F1 competency.'],
            'cefr' => 'B1',
            'flwstage' => 'FLW-STAGE-03',
            'purpose' => 'F1 production validation',
            'weeklytarget' => 2,
            'kpids' => [$fixture['kpid']],
        ], 'TEACHER', 'F1 integrated validation goal');
    }

    private function insert_placement_state(array $fixture): void {
        global $DB, $USER;

        $now = time();
        $payload = ['userid' => $fixture['userid'], 'courseid' => $fixture['courseid'], 'state' => 'VALID'];
        $DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'frameworkid' => $fixture['frameworkid'],
            'unitcode' => $fixture['unitcode'],
            'sourcekey' => 'f1-placement-' . $fixture['userid'],
            'sourcefactkey' => 'f1-placement-fact-' . $fixture['userid'],
            'placementstatus' => 'recorded',
            'policystate' => 'VALID',
            'sourcecategory' => 'imported_history',
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'score' => 0.75,
            'confidence' => 0.90,
            'placementtime' => $now,
            'staleafter' => $now + (180 * DAYSECS),
            'assesseddimensionsjson' => json_encode([[
                'key' => 'reading', 'score' => 0.75, 'targettype' => 'kp', 'targetid' => $fixture['kpid'],
            ]], JSON_UNESCAPED_SLASHES),
            'evidenceidsjson' => '[]',
            'diagnosticjson' => json_encode(['policy_case' => 'imported_history'], JSON_UNESCAPED_SLASHES),
            'policyversion' => \local_flwcupkp\local\placement_diagnostic_service::POLICY_VERSION,
            'checksum' => sha1(json_encode($payload, JSON_UNESCAPED_SLASHES)),
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int)$USER->id,
        ]);
    }

    private function record_history_attempt(array $fixture, int $attemptno, float $score, int $time): void {
        $sourceid = $fixture['userid'] . '-f1-attempt-' . $attemptno;
        $sourceversion = (string)$time;
        $sourceeventid = \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => $sourceid,
            'sourceversion' => $sourceversion,
            'eventtype' => 'ASSESSMENT_COMPLETED',
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'cmid' => $fixture['cmid'],
            'unitid' => $fixture['unitcode'],
            'activityid' => $fixture['activityid'],
            'assessmentid' => $fixture['assessmentid'],
            'eventtime' => $time,
            'status' => 'recorded',
            'normalizer' => 'f1_integrated_test',
            'summaryjson' => ['attemptno' => $attemptno],
        ]);
        \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle', 'quiz_attempt', $sourceid, $sourceversion, 'finished'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcefamily' => 'quiz',
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => $sourceid,
            'sourceversion' => $sourceversion,
            'sourceattemptid' => (string)$attemptno,
            'userid' => $fixture['userid'],
            'courseid' => $fixture['courseid'],
            'cmid' => $fixture['cmid'],
            'unitid' => $fixture['unitcode'],
            'activityid' => $fixture['activityid'],
            'assessmentid' => $fixture['assessmentid'],
            'attemptno' => $attemptno,
            'attemptstate' => 'finished',
            'rawscore' => $score * 10,
            'maxscore' => 10,
            'scaledscore' => $score,
            'timestart' => $time - 100,
            'timefinish' => $time,
            'summaryjson' => ['f1' => true, 'attemptno' => $attemptno],
        ]);
    }

    private function apply_learning_updates(array $fixture, string $reason): void {
        \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], ['attempts'], 100, 0, $reason
        );
        \local_flwcupkp\local\mastery_state_service::apply_rebuild(
            $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], $fixture['userid'], 100, $reason
        );
        \local_flwcupkp\local\retention_review_service::apply_rebuild(
            $fixture['courseid'], $fixture['unitcode'], $fixture['frameworkid'], $fixture['userid'], 100, $reason
        );
    }

    private function mutation_counts(): array {
        global $DB;

        return [
            'source_events' => $DB->count_records('flwhist_source_event'),
            'attempts' => $DB->count_records('flwhist_attempt'),
            'evidence' => $DB->count_records('flwcupkp_evidence'),
            'state' => $DB->count_records('flwcupkp_state'),
            'recommendations' => $DB->count_records('flwcupkp_recommend'),
            'goals' => $DB->count_records('flwcupkp_goal'),
            'interventions' => $DB->count_records('flwcupkp_intervention'),
            'audit' => $DB->count_records('flwcupkp_audit'),
        ];
    }
}

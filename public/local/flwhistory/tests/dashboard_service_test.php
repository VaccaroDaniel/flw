<?php
// PHPUnit tests for local_flwhistory H5 learner dashboard core.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * H5 dashboard service and renderer tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\dashboard_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\dashboard_renderer::class)]
class dashboard_service_test extends \advanced_testcase {
    public function test_own_dashboard_composes_history_and_non_adaptive_journey(): void {
        $this->resetAfterTest(true);
        [$course, $user, $quiz, $page, $futurepage] = $this->create_history_fixture(true);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $now = time();

        $this->cache_content_link($course->id, $page->cmid, 'U038', 'FLW-READ-038-001');
        $this->cache_content_link($course->id, $quiz->cmid, 'U038', 'FLW-READ-038-CHK', 'ASSESS-038');
        $this->cache_content_link($course->id, $futurepage->cmid, 'U039', 'FLW-READ-039-001');
        $this->record_completion($page->cmid, $user->id, COMPLETION_COMPLETE, $now - DAYSECS);
        $this->record_source_event($course->id, $user->id, $page->cmid, 'completion', 'CHECKPOINT_COMPLETED',
            $now - DAYSECS, 'U038');
        $this->record_source_event($course->id, $user->id, $quiz->cmid, 'quiz', 'ASSESSMENT_STARTED',
            $now - 300, 'U038');
        $this->record_attempt($course->id, $quiz->cmid, $user->id, 1, 0.50, $now - 900);
        $this->record_attempt($course->id, $quiz->cmid, $user->id, 2, 0.80, $now - 600);
        $this->record_attempt($course->id, $quiz->cmid, $user->id, 3, 0.70, $now - 200);
        $this->record_grade_version($course->id, $quiz->cmid, $user->id, 70, $now - 500, null);
        $latestgradeid = $this->record_grade_version($course->id, $quiz->cmid, $user->id, 86, $now - 100, 70);
        $this->record_grade_summary($course->id, $quiz->cmid, $user->id, 101, 0.70, 0.80, 88, $latestgradeid, $now);

        $this->setUser($user);
        $dashboard = \local_flwhistory\local\dashboard_service::learner_dashboard_for_request(
            $course->id,
            0,
            ['limit' => 2]
        );

        $this->assertSame('LearnerHistoryDashboardCore', $dashboard['type']);
        $this->assertSame((int)$user->id, (int)$dashboard['userid']);
        $this->assertSame('PresentSummaryCore', $dashboard['present']['type']);
        $this->assertSame('U038', $dashboard['present']['current']['unitid']);
        $this->assertSame('LearningJourneyCore', $dashboard['journey']['type']);
        $this->assertCount(3, $dashboard['journey']['items']);
        $this->assertSame('completed', $dashboard['journey']['items'][0]['state']);
        $this->assertSame('current', $dashboard['journey']['items'][1]['state']);
        $this->assertSame('future', $dashboard['journey']['items'][2]['state']);
        $this->assertFalse($dashboard['standard_next_action']['adaptive']);
        $this->assertSame('continue_current_unit', $dashboard['standard_next_action']['type']);
        $this->assertSame(3, $dashboard['attempt_history']['pagination']['total']);
        $this->assertSame(2, $dashboard['attempt_history']['pagination']['limit']);
        $this->assertSame(3, $dashboard['attempt_history']['records'][0]['attemptno']);
        $this->assertSame(2, $dashboard['grade_history']['pagination']['total']);
        $this->assertSame(2, $dashboard['learning_history']['pagination']['total']);
        $this->assertSame(2, $dashboard['recent_activity']['pagination']['total']);
        $this->assertSame('available', $dashboard['trend']['attempt_score']['status']);
        $this->assertSame('available', $dashboard['trend']['official_grade']['status']);
        $this->assertSame('insufficient_data', $dashboard['trend']['skill']['status']);
    }

    public function test_dashboard_blocks_unauthorized_other_learner(): void {
        $this->resetAfterTest(true);
        [$course, $studentone] = $this->create_history_fixture();
        $studenttwo = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studentone->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($studenttwo->id, $course->id, 'student');

        $this->setUser($studentone);
        $this->expectException(\required_capability_exception::class);
        \local_flwhistory\local\dashboard_service::learner_dashboard_for_request($course->id, $studenttwo->id);
    }

    public function test_dashboard_handles_empty_history_without_fabricated_program3_values(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $dashboard = \local_flwhistory\local\dashboard_service::learner_dashboard_for_request($course->id);

        $this->assertSame('insufficient_data', $dashboard['present']['current']['status']);
        $this->assertSame(0, $dashboard['attempt_history']['pagination']['total']);
        $this->assertSame(0, $dashboard['grade_history']['pagination']['total']);
        $this->assertSame(0, $dashboard['learning_history']['pagination']['total']);
        $this->assertSame(0, $dashboard['recent_activity']['pagination']['total']);
        $this->assertSame('insufficient_data', $dashboard['grade_distinctions']['status']);
        $this->assertSame('insufficient_data', $dashboard['trend']['attempt_score']['status']);
        $this->assertSame('insufficient_data', $dashboard['trend']['official_grade']['status']);
        $this->assertSame('insufficient_data', $dashboard['trend']['skill']['status']);
        foreach ($dashboard['program3_placeholders'] as $placeholder) {
            $this->assertSame('not_available_yet', $placeholder['status']);
            $this->assertArrayNotHasKey('value', $placeholder);
            $this->assertArrayNotHasKey('score', $placeholder);
        }
    }

    public function test_dashboard_preserves_grade_distinctions(): void {
        $this->resetAfterTest(true);
        [$course, $user, $quiz] = $this->create_history_fixture();
        $now = time();
        $oldgradeid = $this->record_grade_version($course->id, $quiz->cmid, $user->id, 79, $now - 200, null);
        $latestgradeid = $this->record_grade_version($course->id, $quiz->cmid, $user->id, 83, $now - 50, 79);
        $this->record_grade_summary($course->id, $quiz->cmid, $user->id, 501, 0.60, 0.96, 75, $oldgradeid,
            $now - 200);
        $this->record_grade_summary($course->id, $quiz->cmid, $user->id, 502, 0.92, 0.92, 81, $latestgradeid,
            $now - 50);

        $dashboard = \local_flwhistory\local\dashboard_service::learner_dashboard_core($course->id, $user->id);

        $distinctions = $dashboard['grade_distinctions'];
        $this->assertSame('available', $distinctions['status']);
        $this->assertEquals(92.0, $distinctions['latest_attempt']['value']);
        $this->assertEquals(96.0, $distinctions['best_attempt']['value']);
        $this->assertEquals(81.0, $distinctions['official_moodle_grade']['value']);
        $this->assertEquals(83.0, $distinctions['latest_grade_version']['value']);
        $this->assertNotEquals($distinctions['latest_attempt']['value'], $distinctions['best_attempt']['value']);
        $this->assertNotEquals($distinctions['official_moodle_grade']['value'],
            $distinctions['latest_grade_version']['value']);
    }

    public function test_renderer_outputs_core_sections_and_placeholders(): void {
        $this->resetAfterTest(true);
        [$course, $user] = $this->create_history_fixture();
        $dashboard = \local_flwhistory\local\dashboard_service::learner_dashboard_core($course->id, $user->id);
        $html = \local_flwhistory\local\dashboard_renderer::render(
            $dashboard,
            new \moodle_url('/local/flwhistory/dashboard.php', ['courseid' => $course->id, 'userid' => $user->id])
        );

        $this->assertStringContainsString('Learning and Grade History', $html);
        $this->assertStringContainsString('Learning journey', $html);
        $this->assertStringContainsString('Attempt details', $html);
        $this->assertStringContainsString('Reserved for Program 3', $html);
        $this->assertStringContainsString('Not available yet', $html);
    }

    private function create_history_fixture(bool $withfuturepage = false): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Reading intro',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Checkpoint',
            'sumgrades' => 10,
            'grade' => 100,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        if (!$withfuturepage) {
            return [$course, $user, $quiz, $page];
        }
        $futurepage = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Future practice',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        return [$course, $user, $quiz, $page, $futurepage];
    }

    private function record_source_event(
        int $courseid,
        int $userid,
        int $cmid,
        string $sourcefamily,
        string $eventtype,
        int $eventtime,
        string $unitid
    ): int {
        return \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => $sourcefamily,
            'sourcetype' => 'h5_test',
            'sourceid' => (string)$eventtime . '-' . $eventtype,
            'sourceversion' => (string)$eventtime,
            'eventtype' => $eventtype,
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid > 0 ? $cmid : null,
            'unitid' => $unitid,
            'activityid' => 'ACT-' . $unitid,
            'eventtime' => $eventtime,
            'status' => 'recorded',
            'normalizer' => 'h5_test',
            'summaryjson' => ['visible' => true],
        ]);
    }

    private function record_attempt(int $courseid, int $cmid, int $userid, int $attemptno, float $score, int $timefinish): int {
        return \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'quiz_attempt',
                (string)$cmid . ':' . (string)$attemptno,
                (string)$timefinish,
                'finished'
            ),
            'sourcefamily' => 'quiz',
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$cmid,
            'sourceversion' => (string)$timefinish,
            'sourceattemptid' => (string)$attemptno,
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => 'U038',
            'activityid' => 'ACT-U038',
            'attemptno' => $attemptno,
            'attemptstate' => 'finished',
            'rawscore' => $score * 10,
            'maxscore' => 10,
            'scaledscore' => $score,
            'timestart' => $timefinish - 100,
            'timefinish' => $timefinish,
            'summaryjson' => ['result' => 'recorded'],
        ]);
    }

    private function record_grade_version(
        int $courseid,
        int $cmid,
        int $userid,
        float $finalgrade,
        int $gradetime,
        ?float $previousgrade
    ): int {
        return \local_flwhistory\local\grade_history_service::record_grade_version([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'grade_grades_history',
                (string)$cmid . ':' . (string)$gradetime,
                (string)$gradetime,
                'GRADE_CHANGE'
            ),
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'gradeitemid' => 101,
            'gradegradeid' => 202,
            'previousgrade' => $previousgrade,
            'finalgrade' => $finalgrade,
            'action' => \local_flwhistory\local\grade_history_service::ACTION_OTHER,
            'gradetime' => $gradetime,
            'summaryjson' => ['test' => 'h5'],
        ]);
    }

    private function record_grade_summary(
        int $courseid,
        int $cmid,
        int $userid,
        int $gradeitemid,
        float $latestattemptscore,
        float $bestattemptscore,
        float $officialgrade,
        int $latestgradeversionid,
        int $time
    ): int {
        return \local_flwhistory\local\repository::upsert_grade_summary([
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'gradeitemid' => $gradeitemid,
            'latestattemptscore' => $latestattemptscore,
            'latestattempttime' => $time,
            'bestattemptscore' => $bestattemptscore,
            'bestattempttime' => $time,
            'officialfinalgrade' => $officialgrade,
            'officialgradetime' => $time,
            'latestgradeversionid' => $latestgradeversionid,
            'reconciliationstatus' => 'current',
        ]);
    }

    private function record_completion(int $cmid, int $userid, int $state, int $time): int {
        global $DB;

        return (int)$DB->insert_record('course_modules_completion', (object)[
            'coursemoduleid' => $cmid,
            'userid' => $userid,
            'completionstate' => $state,
            'overrideby' => null,
            'timemodified' => $time,
        ]);
    }

    private function cache_content_link(
        int $courseid,
        int $cmid,
        string $unitid,
        string $activityid,
        string $assessmentid = ''
    ): int {
        return \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => $courseid,
            'cmid' => $cmid,
            'worldid' => 'W-B1',
            'stageid' => 'S-READ',
            'unitid' => $unitid,
            'lessonid' => 'L-' . $unitid,
            'componentid' => 'C-' . $unitid,
            'activityid' => $activityid,
            'assessmentid' => $assessmentid,
            'sourcerevision' => 'h5-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);
    }
}

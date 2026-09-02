<?php
// PHPUnit tests for local_flwhistory H4 secure history APIs.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * H4 summary, query, and external access tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\history_api_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\external\api::class)]
class history_api_service_test extends \advanced_testcase {
    public function test_present_summary_uses_trusted_history_and_marks_study_time_insufficient(): void {
        $this->resetAfterTest(true);
        [$course, $user, $quiz, $page] = $this->create_history_fixture();
        $now = time();
        $this->record_completion($page->cmid, $user->id, COMPLETION_COMPLETE, $now - DAYSECS);
        $this->record_source_event($course->id, $user->id, $page->cmid, 'completion', 'CHECKPOINT_COMPLETED', $now - DAYSECS, 'U038');
        $this->record_source_event($course->id, $user->id, $quiz->cmid, 'quiz', 'ASSESSMENT_COMPLETED', $now - 100, 'U039');
        $this->record_attempt($course->id, $quiz->cmid, $user->id, 1, 0.74, $now - 90);
        \local_flwhistory\local\repository::upsert_grade_summary([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $quiz->cmid,
            'gradeitemid' => 77,
            'officialfinalgrade' => 86,
            'reconciliationstatus' => 'current',
        ]);

        $summary = \local_flwhistory\local\history_api_service::present_summary_core($course->id, $user->id);

        $this->assertSame('PresentSummaryCore', $summary['type']);
        $this->assertSame('U039', $summary['current']['unitid']);
        $this->assertSame('available', $summary['completion']['status']);
        $this->assertSame(1, $summary['completion']['completed']);
        $this->assertSame(2, $summary['completion']['total']);
        $this->assertSame(50.0, $summary['completion']['percent']);
        $this->assertSame(2, $summary['active_days']['count']);
        $this->assertEquals(86.0, $summary['scores']['official_moodle_grade']['average']);
        $this->assertEquals(0.74, $summary['scores']['assessment_attempt_score']['average']);
        $this->assertSame('insufficient_data', $summary['study_time']['status']);
    }

    public function test_learning_journey_marks_completed_current_future_and_checkpoint(): void {
        $this->resetAfterTest(true);
        [$course, $user, $quiz, $page, $futurepage] = $this->create_history_fixture(true);
        $this->cache_content_link($course->id, $page->cmid, 'U038', 'FLW-READ-038-001');
        $this->cache_content_link($course->id, $quiz->cmid, 'U038', 'FLW-READ-038-CHK', 'ASSESS-038');
        $this->cache_content_link($course->id, $futurepage->cmid, 'U039', 'FLW-READ-039-001');
        $this->record_completion($page->cmid, $user->id, COMPLETION_COMPLETE, 1700);
        $this->record_source_event($course->id, $user->id, $quiz->cmid, 'quiz', 'ASSESSMENT_STARTED', 1800, 'U038');

        $journey = \local_flwhistory\local\history_api_service::learning_journey_core($course->id, $user->id);

        $this->assertSame('LearningJourneyCore', $journey['type']);
        $this->assertCount(3, $journey['items']);
        $this->assertSame('completed', $journey['items'][0]['state']);
        $this->assertSame('current', $journey['items'][1]['state']);
        $this->assertTrue($journey['items'][1]['checkpoint']);
        $this->assertSame('future', $journey['items'][2]['state']);
        $this->assertSame('not_in_scope', $journey['adaptive']['status']);
    }

    public function test_query_services_paginate_and_redact_grade_audit_fields_by_default(): void {
        $this->resetAfterTest(true);
        [$course, $user, $quiz] = $this->create_history_fixture();
        $this->record_source_event($course->id, $user->id, $quiz->cmid, 'quiz', 'ASSESSMENT_COMPLETED', 2100, 'U038');
        $this->record_attempt($course->id, $quiz->cmid, $user->id, 1, 0.55, 2000);
        $this->record_attempt($course->id, $quiz->cmid, $user->id, 2, 0.85, 2200);
        $this->record_grade_version($course->id, $quiz->cmid, $user->id, 61, 2200);

        $learning = \local_flwhistory\local\history_api_service::learning_history_query($course->id, $user->id, 1, 0);
        $this->assertSame(1, $learning['pagination']['limit']);
        $this->assertSame(1, $learning['pagination']['total']);
        $this->assertArrayNotHasKey('sourcekey', $learning['records'][0]);

        $attempts = \local_flwhistory\local\history_api_service::attempt_history_query($course->id, $user->id, 1, 0);
        $this->assertSame(2, $attempts['pagination']['total']);
        $this->assertSame(2, $attempts['records'][0]['attemptno']);
        $this->assertArrayNotHasKey('sourceattemptid', $attempts['records'][0]);

        $grades = \local_flwhistory\local\history_api_service::grade_history_query($course->id, $user->id);
        $this->assertSame(1, $grades['pagination']['total']);
        $this->assertArrayNotHasKey('audit', $grades['records'][0]);

        $gradeswithaudit = \local_flwhistory\local\history_api_service::grade_history_query(
            $course->id,
            $user->id,
            50,
            0,
            0,
            0,
            0,
            true
        );
        $this->assertArrayHasKey('audit', $gradeswithaudit['records'][0]);
        $this->assertSame(9, $gradeswithaudit['records'][0]['audit']['graderid']);
        $this->assertSame('teacher override', $gradeswithaudit['records'][0]['audit']['reason']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_external_api_resolves_current_user_and_blocks_other_student(): void {
        $this->resetAfterTest(true);
        [$course, $studentone] = $this->create_history_fixture();
        $studenttwo = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studentone->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($studenttwo->id, $course->id, 'student');
        $this->record_source_event($course->id, $studentone->id, 0, 'completion', 'COURSE_VIEWED', 2500, 'U038');

        $this->setUser($studentone);
        $response = \local_flwhistory\external\api::get_learning_history($course->id);
        $payload = json_decode($response['datajson'], true);
        $this->assertSame((int)$studentone->id, (int)$payload['userid']);
        $this->assertSame(1, $payload['pagination']['total']);

        $this->expectException(\required_capability_exception::class);
        \local_flwhistory\external\api::get_learning_history($course->id, $studenttwo->id);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_external_api_allows_teacher_audit_view_for_authorized_learner(): void {
        $this->resetAfterTest(true);
        [$course, $student, $quiz] = $this->create_history_fixture();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->record_grade_version($course->id, $quiz->cmid, $student->id, 88, 2600, $teacher->id);

        $this->setUser($teacher);
        $response = \local_flwhistory\external\api::get_grade_history($course->id, $student->id, 20, 0, 0, 0, 0, true);
        $payload = json_decode($response['datajson'], true);

        $this->assertSame((int)$student->id, (int)$payload['userid']);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertArrayHasKey('audit', $payload['records'][0]);
        $this->assertSame((int)$teacher->id, (int)$payload['records'][0]['audit']['graderid']);
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
            'sourcetype' => 'h4_test',
            'sourceid' => (string)$eventtime,
            'sourceversion' => (string)$eventtime,
            'eventtype' => $eventtype,
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid > 0 ? $cmid : null,
            'unitid' => $unitid,
            'activityid' => 'ACT-' . $unitid,
            'eventtime' => $eventtime,
            'status' => 'recorded',
            'normalizer' => 'h4_test',
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
        int $graderid = 9
    ): int {
        return \local_flwhistory\local\grade_history_service::record_grade_version([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'grade_grades_history',
                (string)$cmid . ':' . (string)$gradetime,
                (string)$gradetime,
                'TEACHER_OVERRIDE'
            ),
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'gradeitemid' => 101,
            'gradegradeid' => 202,
            'finalgrade' => $finalgrade,
            'graderid' => $graderid,
            'action' => \local_flwhistory\local\grade_history_service::ACTION_TEACHER_OVERRIDE,
            'reason' => 'teacher override',
            'gradetime' => $gradetime,
            'summaryjson' => ['test' => 'h4'],
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
            'sourcerevision' => 'h4-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);
    }
}

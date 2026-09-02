<?php
// PHPUnit tests for local_flwhistory H6 teacher analytics.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * H6 teacher analytics service and renderer tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\teacher_analytics_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\teacher_analytics_renderer::class)]
class teacher_analytics_service_test extends \advanced_testcase {
    public function test_teacher_dashboard_summarizes_class_and_allowed_signals(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $active, $stale, $missing, $quiz, $page] = $this->create_teacher_fixture();
        $now = time();
        $oldtime = $now - (20 * DAYSECS);

        $this->record_completion($page->cmid, $active->id, COMPLETION_COMPLETE, $now - 200);
        $this->record_source_event($course->id, $active->id, $quiz->cmid, 'quiz', 'CHECKPOINT_COMPLETED',
            $now - 100, 'U038', 'ASSESS-038');
        $this->record_source_event($course->id, $stale->id, $quiz->cmid, 'quiz', 'ASSESSMENT_STARTED',
            $oldtime, 'U038');
        $this->record_attempt($course->id, $quiz->cmid, $active->id, 1, 0.50, $now - 600);
        $this->record_attempt($course->id, $quiz->cmid, $active->id, 2, 0.80, $now - 100);
        $this->record_attempt($course->id, $quiz->cmid, $stale->id, 1, 0.40, $oldtime + 50);
        $this->record_attempt($course->id, $quiz->cmid, $stale->id, 2, 0.50, $oldtime + 100);
        $this->record_grade_version($course->id, $quiz->cmid, $active->id, 84, $now - 90, null, $teacher->id);
        $this->record_grade_version($course->id, $quiz->cmid, $stale->id, 90, $oldtime + 120, null, $teacher->id);
        $this->record_grade_version($course->id, $quiz->cmid, $stale->id, 70, $oldtime + 180, 90, $teacher->id);
        $this->record_grade_summary($course->id, $quiz->cmid, $active->id, 101, 88, $now - 90);
        $this->record_grade_summary($course->id, $quiz->cmid, $stale->id, 102, 70, $oldtime + 180);
        $this->record_placement($course->id, $active->id, 'A2', $now - 700);

        $this->setUser($teacher);
        $dashboard = \local_flwhistory\local\teacher_analytics_service::teacher_dashboard_for_request($course->id, [
            'limit' => 10,
        ]);

        $this->assertSame('TeacherHistoryAnalyticsCore', $dashboard['type']);
        $this->assertSame(3, $dashboard['pagination']['total']);
        $this->assertSame(3, $dashboard['class_summary']['learnercount']);
        $this->assertSame(1, $dashboard['class_summary']['completion']['completed']);
        $this->assertSame(6, $dashboard['class_summary']['completion']['possible']);
        $this->assertSame(1, $dashboard['class_summary']['activity']['activecount']);
        $this->assertSame(1, $dashboard['class_summary']['activity']['missingactivitycount']);
        $this->assertSame(2, $dashboard['class_summary']['official_grade']['count']);
        $this->assertSame(4, $dashboard['class_summary']['attempts']['attemptcount']);
        $this->assertSame(1, $dashboard['class_summary']['attention_counts']['inactive']);
        $this->assertSame(1, $dashboard['class_summary']['attention_counts']['repeated_unsuccessful_attempts']);
        $this->assertSame(1, $dashboard['class_summary']['attention_counts']['grade_decline_with_enough_comparable_data']);
        $this->assertSame(1, $dashboard['class_summary']['attention_counts']['stalled_completion']);
        $this->assertSame(1, $dashboard['class_summary']['attention_counts']['missing_activity_evidence']);

        $activerow = $this->row_for_user($dashboard, $active->id);
        $stalerow = $this->row_for_user($dashboard, $stale->id);
        $missingrow = $this->row_for_user($dashboard, $missing->id);
        $this->assertSame('available', $activerow['attempt_trend']['trend']['status']);
        $this->assertSame(1, $activerow['checkpoint_history']['count']);
        $this->assertSame('A2', $activerow['placement_history']['currentlevel']);
        $this->assertStringContainsString('/local/flwhistory/dashboard.php', $activerow['drilldownurl']);
        $this->assertContains('inactive', array_column($stalerow['attention_signals'], 'key'));
        $this->assertContains('repeated_unsuccessful_attempts', array_column($stalerow['attention_signals'], 'key'));
        $this->assertContains('grade_decline_with_enough_comparable_data', array_column($stalerow['attention_signals'], 'key'));
        $this->assertContains('stalled_completion', array_column($stalerow['attention_signals'], 'key'));
        $this->assertContains('missing_activity_evidence', array_column($missingrow['attention_signals'], 'key'));
        $this->assertSame('available', $dashboard['grade_audit']['status']);
        $this->assertNotEmpty($dashboard['grade_audit']['records']);

        $encodedrows = json_encode($dashboard['learners']);
        $this->assertStringNotContainsString('C-UP-KP weakness', $encodedrows);
        $this->assertStringNotContainsString('mastery deficit', $encodedrows);
        $this->assertStringNotContainsString('retention risk', $encodedrows);
        $this->assertStringNotContainsString('adaptive priority', $encodedrows);
    }

    public function test_student_cannot_open_teacher_dashboard(): void {
        $this->resetAfterTest(true);
        [$course, , $student] = $this->create_teacher_fixture();

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        \local_flwhistory\local\teacher_analytics_service::teacher_dashboard_for_request($course->id);
    }

    public function test_teacher_dashboard_paginates_learners(): void {
        $this->resetAfterTest(true);
        [$course] = $this->create_teacher_fixture();

        $dashboard = \local_flwhistory\local\teacher_analytics_service::teacher_dashboard_core($course->id, [
            'limit' => 1,
            'offset' => 1,
        ], false);

        $this->assertSame(1, $dashboard['pagination']['limit']);
        $this->assertSame(1, $dashboard['pagination']['offset']);
        $this->assertSame(3, $dashboard['pagination']['total']);
        $this->assertTrue($dashboard['pagination']['hasmore']);
        $this->assertCount(1, $dashboard['learners']);
        $this->assertSame('capability_required', $dashboard['grade_audit']['status']);
    }

    public function test_empty_class_returns_insufficient_states(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $dashboard = \local_flwhistory\local\teacher_analytics_service::teacher_dashboard_core($course->id);

        $this->assertSame(0, $dashboard['pagination']['total']);
        $this->assertSame(0, $dashboard['class_summary']['learnercount']);
        $this->assertSame('insufficient_data', $dashboard['class_summary']['completion']['status']);
        $this->assertSame('insufficient_data', $dashboard['class_summary']['activity']['status']);
        $this->assertSame('insufficient_data', $dashboard['class_summary']['official_grade']['status']);
        $this->assertEmpty($dashboard['learners']);
    }

    public function test_renderer_outputs_teacher_sections_without_adaptive_labels(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $active, , , $quiz] = $this->create_teacher_fixture();
        $now = time();
        $this->record_source_event($course->id, $active->id, $quiz->cmid, 'quiz', 'CHECKPOINT_COMPLETED',
            $now - 100, 'U038', 'ASSESS-038');
        $this->record_grade_version($course->id, $quiz->cmid, $active->id, 92, $now - 50, null, $teacher->id);

        $dashboard = \local_flwhistory\local\teacher_analytics_service::teacher_dashboard_core($course->id, [
            'limit' => 10,
        ], true);
        $html = \local_flwhistory\local\teacher_analytics_renderer::render(
            $dashboard,
            new \moodle_url('/local/flwhistory/teacher.php', ['courseid' => $course->id])
        );

        $this->assertStringContainsString('Teacher History Analytics', $html);
        $this->assertStringContainsString('Attention signal overview', $html);
        $this->assertStringContainsString('Individual history drill-down', $html);
        $this->assertStringContainsString('Grade audit', $html);
        $this->assertStringContainsString('History-only analytics', $html);
        $this->assertStringNotContainsString('C-UP-KP weakness', $html);
        $this->assertStringNotContainsString('mastery deficit', $html);
        $this->assertStringNotContainsString('retention risk', $html);
        $this->assertStringNotContainsString('adaptive priority', $html);
    }

    private function create_teacher_fixture(): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $teacher = $this->getDataGenerator()->create_user([
            'firstname' => 'Tara',
            'lastname' => 'Teacher',
        ]);
        $active = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Active',
            'username' => 'h6_active',
        ]);
        $stale = $this->getDataGenerator()->create_user([
            'firstname' => 'Sam',
            'lastname' => 'Stale',
            'username' => 'h6_stale',
        ]);
        $missing = $this->getDataGenerator()->create_user([
            'firstname' => 'Mina',
            'lastname' => 'Missing',
            'username' => 'h6_missing',
        ]);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($active->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($stale->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($missing->id, $course->id, 'student');
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
        return [$course, $teacher, $active, $stale, $missing, $quiz, $page];
    }

    private function row_for_user(array $dashboard, int $userid): array {
        foreach ($dashboard['learners'] as $row) {
            if ((int)$row['userid'] === $userid) {
                return $row;
            }
        }
        $this->fail('Learner row not found: ' . $userid);
    }

    private function record_source_event(
        int $courseid,
        int $userid,
        int $cmid,
        string $sourcefamily,
        string $eventtype,
        int $eventtime,
        string $unitid,
        string $assessmentid = ''
    ): int {
        return \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => $sourcefamily,
            'sourcetype' => 'h6_test',
            'sourceid' => (string)$userid . '-' . (string)$eventtime . '-' . $eventtype,
            'sourceversion' => (string)$eventtime,
            'eventtype' => $eventtype,
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid > 0 ? $cmid : null,
            'unitid' => $unitid,
            'activityid' => 'ACT-' . $unitid,
            'assessmentid' => $assessmentid !== '' ? $assessmentid : null,
            'eventtime' => $eventtime,
            'status' => 'recorded',
            'normalizer' => 'h6_test',
            'summaryjson' => ['visible' => true],
        ]);
    }

    private function record_attempt(int $courseid, int $cmid, int $userid, int $attemptno, float $score, int $timefinish): int {
        return \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'quiz_attempt',
                (string)$userid . ':' . (string)$cmid . ':' . (string)$attemptno,
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
            'assessmentid' => 'ASSESS-038',
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
        ?float $previousgrade,
        int $graderid
    ): int {
        return \local_flwhistory\local\grade_history_service::record_grade_version([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'grade_grades_history',
                (string)$userid . ':' . (string)$cmid . ':' . (string)$gradetime,
                (string)$gradetime,
                'H6_GRADE'
            ),
            'sourcefamily' => 'gradebook',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'gradeitemid' => 101,
            'gradegradeid' => 202,
            'previousgrade' => $previousgrade,
            'finalgrade' => $finalgrade,
            'graderid' => $graderid,
            'action' => \local_flwhistory\local\grade_history_service::ACTION_OTHER,
            'reason' => 'h6 evidence review',
            'gradetime' => $gradetime,
            'summaryjson' => ['test' => 'h6'],
        ]);
    }

    private function record_grade_summary(
        int $courseid,
        int $cmid,
        int $userid,
        int $gradeitemid,
        float $officialgrade,
        int $time
    ): int {
        return \local_flwhistory\local\repository::upsert_grade_summary([
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'gradeitemid' => $gradeitemid,
            'officialfinalgrade' => $officialgrade,
            'officialgradetime' => $time,
            'reconciliationstatus' => 'current',
        ]);
    }

    private function record_placement(int $courseid, int $userid, string $level, int $time): int {
        return \local_flwhistory\local\placement_history_service::record_placement([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'flwplacement',
                'placement',
                (string)$userid . ':' . (string)$courseid,
                (string)$time,
                'placed'
            ),
            'sourcesystem' => 'flwplacement',
            'sourcefamily' => 'placement',
            'sourcetype' => 'placement',
            'sourceid' => (string)$userid,
            'sourceversion' => (string)$time,
            'userid' => $userid,
            'courseid' => $courseid,
            'previouslevel' => 'A1',
            'currentlevel' => $level,
            'placementstatus' => 'recorded',
            'score' => 0.80,
            'confidence' => 0.70,
            'placementtime' => $time,
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
}

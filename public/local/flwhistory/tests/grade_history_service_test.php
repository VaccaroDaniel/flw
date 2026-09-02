<?php
// PHPUnit tests for local_flwhistory H3 grade version history.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/gradelib.php');
require_once($GLOBALS['CFG']->libdir . '/grade/grade_grade.php');
require_once($GLOBALS['CFG']->libdir . '/grade/grade_item.php');

/**
 * Grade version capture and reconciliation tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\grade_history_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\repository::class)]
class grade_history_service_test extends \advanced_testcase {
    public function test_multiple_attempts_and_one_official_grade_remain_separate(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(85.0);

        $bestattemptid = $this->record_local_attempt($course, $quiz, $user, 1, 0.90, 1100);
        $this->record_local_attempt($course, $quiz, $user, 2, 0.70, 1200);
        $latestattemptid = $this->record_local_attempt($course, $quiz, $user, 3, 0.60, 1300);

        $gradeversionid = \local_flwhistory\local\grade_history_service::record_moodle_grade_version($grade, [
            'action' => \local_flwhistory\local\grade_history_service::ACTION_INITIAL,
        ]);
        $result = \local_flwhistory\local\grade_history_service::reconcile_grade_summary(
            (int)$user->id,
            (int)$gradeitem->id,
            ['latestgradeversionid' => $gradeversionid]
        );

        $this->assertSame('current', $result['status']);
        $this->assertSame(3, $DB->count_records('flwhist_attempt', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('flwhist_grade_version', ['userid' => $user->id]));

        $summary = \local_flwhistory\local\grade_history_service::get_grade_summary((int)$user->id, (int)$gradeitem->id);
        $this->assertNotEmpty($summary);
        $this->assertSame($latestattemptid, (int)$summary->latestattemptid);
        $this->assertEquals(0.60, (float)$summary->latestattemptscore);
        $this->assertSame($bestattemptid, (int)$summary->bestattemptid);
        $this->assertEquals(0.90, (float)$summary->bestattemptscore);
        $this->assertSame((int)$grade->id, (int)$summary->officialgradegradeid);
        $this->assertEquals(85.0, (float)$summary->officialfinalgrade);
        $this->assertSame($gradeversionid, (int)$summary->latestgradeversionid);

        $payload = json_decode($summary->summaryjson, true);
        $this->assertSame((int)$grade->id, $payload['official_moodle_grade']['gradegradeid']);
        $this->assertSame($latestattemptid, $payload['latest_attempt']['id']);
        $this->assertSame($bestattemptid, $payload['best_attempt']['id']);
        $this->assertNotSame((int)$summary->latestattemptid, (int)$summary->bestattemptid);
        $this->assertNotSame((int)$summary->latestattemptid, (int)$summary->latestgradeversionid);
    }

    public function test_user_graded_event_records_teacher_override_and_actor(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(72.0);
        $grade = $this->override_official_grade($gradeitem, $user, $teacher, 78.0, 2200);

        $event = \core\event\user_graded::create_from_grade($grade, $teacher->id);
        $result = \local_flwhistory\local\grade_history_service::capture_user_graded_event($event);

        $this->assertSame('captured', $result['status']);
        $version = $DB->get_record('flwhist_grade_version', ['id' => $result['gradeversionid']], '*', MUST_EXIST);
        $this->assertSame(\local_flwhistory\local\grade_history_service::ACTION_TEACHER_OVERRIDE, $version->action);
        $this->assertSame((int)$teacher->id, (int)$version->graderid);
        $this->assertEquals(78.0, (float)$version->finalgrade);

        $source = $DB->get_record('flwhist_source_event', ['id' => $result['sourceeventid']], '*', MUST_EXIST);
        $this->assertSame('OFFICIAL_GRADE_CHANGED', $source->eventtype);
        $this->assertSame('gradebook', $source->sourcefamily);
        $this->assertSame((int)$teacher->id, (int)$source->usermodified);

        $summary = \local_flwhistory\local\grade_history_service::get_grade_summary((int)$user->id, (int)$gradeitem->id);
        $this->assertNotEmpty($summary);
        $this->assertEquals(78.0, (float)$summary->officialfinalgrade);
        $this->assertSame((int)$version->id, (int)$summary->latestgradeversionid);
    }

    public function test_duplicate_grade_source_does_not_duplicate_history(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(70.0);

        $event = \core\event\user_graded::create_from_grade($grade, $teacher->id);
        $first = \local_flwhistory\local\grade_history_service::capture_user_graded_event($event);
        $second = \local_flwhistory\local\grade_history_service::capture_user_graded_event($event);

        $this->assertSame($first['sourceeventid'], $second['sourceeventid']);
        $this->assertSame($first['gradeversionid'], $second['gradeversionid']);
        $this->assertSame(1, $DB->count_records('flwhist_source_event', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('flwhist_grade_version', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('flwhist_grade_summary', ['userid' => $user->id]));
    }

    public function test_regrade_correction_links_versions_without_merging_attempts(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(60.0);
        $this->record_local_attempt($course, $quiz, $user, 1, 0.60, 1100);
        $initialhistory = $this->latest_grade_history_row($grade);
        $oldid = \local_flwhistory\local\grade_history_service::record_grade_history_row($initialhistory);

        $grade = $this->update_official_grade($course, $quiz, $gradeitem, $user, $teacher, 82.0, 'regrade', 2300);
        $regradehistory = $this->latest_grade_history_row($grade);
        $newid = \local_flwhistory\local\grade_history_service::record_grade_history_row($regradehistory);
        \local_flwhistory\local\grade_history_service::record_grade_correction(
            $newid,
            $oldid,
            'Verified Moodle regrade',
            $teacher->id
        );

        $old = $DB->get_record('flwhist_grade_version', ['id' => $oldid], '*', MUST_EXIST);
        $new = $DB->get_record('flwhist_grade_version', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame($newid, (int)$old->supersededby);
        $this->assertSame($oldid, (int)$new->correctionof);
        $this->assertSame(\local_flwhistory\local\grade_history_service::ACTION_REGRADE, $new->action);
        $this->assertEquals(60.0, (float)$new->previousgrade);
        $this->assertEquals(82.0, (float)$new->finalgrade);
        $this->assertSame(1, $DB->count_records('flwhist_attempt', ['userid' => $user->id]));
        $this->assertSame(2, $DB->count_records('flwhist_grade_version', ['userid' => $user->id]));
    }

    public function test_reconciliation_repairs_stale_summary_without_rewriting_grade_versions_or_moodle_grade(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(75.0);
        $gradeversionid = \local_flwhistory\local\grade_history_service::record_moodle_grade_version($grade);
        \local_flwhistory\local\grade_history_service::reconcile_grade_summary((int)$user->id, (int)$gradeitem->id, [
            'latestgradeversionid' => $gradeversionid,
        ]);
        $summary = \local_flwhistory\local\grade_history_service::get_grade_summary((int)$user->id, (int)$gradeitem->id);
        $DB->set_field('flwhist_grade_summary', 'officialfinalgrade', 20.0, ['id' => $summary->id]);
        $versioncount = $DB->count_records('flwhist_grade_version', ['userid' => $user->id]);

        $result = \local_flwhistory\local\grade_history_service::reconcile_grade_summary(
            (int)$user->id,
            (int)$gradeitem->id
        );

        $this->assertTrue($result['changed']);
        $summary = \local_flwhistory\local\grade_history_service::get_grade_summary((int)$user->id, (int)$gradeitem->id);
        $this->assertEquals(75.0, (float)$summary->officialfinalgrade);
        $this->assertSame($versioncount, $DB->count_records('flwhist_grade_version', ['userid' => $user->id]));

        $moodlegrade = \grade_grade::fetch(['userid' => $user->id, 'itemid' => $gradeitem->id]);
        $this->assertInstanceOf(\grade_grade::class, $moodlegrade);
        $this->assertEquals(75.0, (float)$moodlegrade->finalgrade);
    }

    public function test_grade_deleted_event_can_capture_payload_after_core_grade_is_missing(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(66.0);
        $event = \core\event\grade_deleted::create([
            'objectid' => $grade->id,
            'context' => \context_course::instance($course->id),
            'relateduserid' => $user->id,
            'other' => [
                'itemid' => $gradeitem->id,
                'overridden' => false,
                'finalgrade' => 66.0,
            ],
        ]);
        $DB->delete_records('grade_grades', ['id' => $grade->id]);

        $result = \local_flwhistory\local\grade_history_service::capture_grade_deleted_event($event);

        $this->assertSame('captured', $result['status']);
        $version = $DB->get_record('flwhist_grade_version', ['id' => $result['gradeversionid']], '*', MUST_EXIST);
        $this->assertSame(\local_flwhistory\local\grade_history_service::ACTION_OTHER, $version->action);
        $this->assertSame('grade_deleted', $version->reason);
        $this->assertEquals(66.0, (float)$version->finalgrade);
        $summary = \local_flwhistory\local\grade_history_service::get_grade_summary((int)$user->id, (int)$gradeitem->id);
        $this->assertSame('official_grade_missing', $summary->reconciliationstatus);
    }

    private function create_quiz_grade_fixture(float $officialgrade): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'sumgrades' => 10,
            'grade' => 100,
        ]);
        $gradeitem = $this->get_quiz_grade_item($course, $quiz);
        $grade = $this->update_official_grade($course, $quiz, $gradeitem, $user, $teacher, $officialgrade, 'mod/quiz', 2000);

        return [$course, $user, $teacher, $quiz, $gradeitem, $grade];
    }

    private function get_quiz_grade_item(\stdClass $course, \stdClass $quiz): \grade_item {
        $gradeitem = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'itemnumber' => 0,
        ]);
        $this->assertInstanceOf(\grade_item::class, $gradeitem);
        return $gradeitem;
    }

    private function update_official_grade(
        \stdClass $course,
        \stdClass $quiz,
        \grade_item $gradeitem,
        \stdClass $user,
        \stdClass $teacher,
        float $officialgrade,
        string $source,
        int $timemodified
    ): \grade_grade {
        $sink = $this->redirectEvents();
        $result = grade_update($source, $course->id, 'mod', 'quiz', $quiz->id, 0, [
            'userid' => $user->id,
            'rawgrade' => $officialgrade,
            'usermodified' => $teacher->id,
            'dategraded' => $timemodified,
        ]);
        $sink->close();

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $grade = \grade_grade::fetch(['userid' => $user->id, 'itemid' => $gradeitem->id]);
        $this->assertInstanceOf(\grade_grade::class, $grade);
        $grade->grade_item = $gradeitem;
        return $grade;
    }

    private function override_official_grade(
        \grade_item $gradeitem,
        \stdClass $user,
        \stdClass $teacher,
        float $officialgrade,
        int $timemodified
    ): \grade_grade {
        $sink = $this->redirectEvents();
        $gradeitem->update_final_grade(
            $user->id,
            $officialgrade,
            'gradebook',
            '',
            FORMAT_MOODLE,
            $teacher->id,
            $timemodified
        );
        $sink->close();

        $grade = \grade_grade::fetch(['userid' => $user->id, 'itemid' => $gradeitem->id]);
        $this->assertInstanceOf(\grade_grade::class, $grade);
        $grade->grade_item = $gradeitem;
        $this->assertTrue($grade->is_overridden());
        return $grade;
    }

    private function latest_grade_history_row(\grade_grade $grade): \stdClass {
        global $DB;

        $records = $DB->get_records('grade_grades_history', [
            'oldid' => $grade->id,
            'itemid' => $grade->itemid,
            'userid' => $grade->userid,
        ], 'timemodified DESC, id DESC', '*', 0, 1);
        $this->assertNotEmpty($records);
        return reset($records);
    }

    private function record_local_attempt(
        \stdClass $course,
        \stdClass $quiz,
        \stdClass $user,
        int $attemptno,
        float $scaledscore,
        int $timefinish
    ): int {
        return \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'quiz_attempt',
                (string)$quiz->id . ':' . (string)$attemptno,
                (string)$timefinish,
                'finished'
            ),
            'sourcefamily' => 'quiz',
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$quiz->id,
            'sourceversion' => (string)$timefinish,
            'sourceattemptid' => (string)$attemptno,
            'userid' => (int)$user->id,
            'courseid' => (int)$course->id,
            'cmid' => (int)$quiz->cmid,
            'attemptno' => $attemptno,
            'attemptstate' => 'finished',
            'rawscore' => $scaledscore * 10,
            'maxscore' => 10,
            'scaledscore' => $scaledscore,
            'timestart' => $timefinish - 100,
            'timefinish' => $timefinish,
            'normpolicyversion' => \local_flwhistory\local\history_policy::NORMALIZATION_POLICY_VERSION,
            'summaryjson' => ['test' => 'h3'],
        ]);
    }
}

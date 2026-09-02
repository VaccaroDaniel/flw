<?php
// PHPUnit tests for local_flwhistory H7 History V1 freeze.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/gradelib.php');
require_once($GLOBALS['CFG']->libdir . '/grade/grade_grade.php');
require_once($GLOBALS['CFG']->libdir . '/grade/grade_item.php');

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * H7 migration, reconciliation, privacy, performance, and downstream contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\history_v1_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\evidence_source_adapter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\privacy\provider::class)]
class history_v1_service_test extends \advanced_testcase {
    public function test_backfill_dryrun_execute_resume_and_idempotency(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt] = $this->create_quiz_attempt(1, 7.5, 10.0, true);
        $this->cache_content_link((int)$course->id, (int)$quiz->cmid);

        $dryrun = \local_flwhistory\local\history_v1_service::backfill_course((int)$course->id, [
            'dryrun' => true,
            'batchsize' => 1,
            'sources' => ['quiz_attempts'],
            'sourcelabel' => 'h7-test',
        ]);

        $this->assertSame('dry_run_complete', $dryrun['status']);
        $this->assertSame(1, $dryrun['sources']['quiz_attempts']['recordsseen']);
        $this->assertGreaterThanOrEqual(3, $dryrun['sources']['quiz_attempts']['recordscreated']);
        $this->assertSame((int)$attempt->id, $dryrun['nextcursors']['quiz_attempts']);
        $this->assertSame(0, $DB->count_records('flwhist_source_event'));
        $this->assertSame(0, $DB->count_records('flwhist_attempt'));
        $this->assertSame('not_created', $dryrun['fabrication_policy']['mastery']);

        $execute = \local_flwhistory\local\history_v1_service::backfill_course((int)$course->id, [
            'dryrun' => false,
            'batchsize' => 1,
            'sources' => ['quiz_attempts'],
            'sourcelabel' => 'h7-test',
        ]);

        $this->assertSame('complete', $execute['status']);
        $this->assertSame(1, $DB->count_records('flwhist_source_event'));
        $this->assertSame(1, $DB->count_records('flwhist_attempt'));
        $this->assertSame(1, $DB->count_records('flwhist_question_attempt'));

        $repeat = \local_flwhistory\local\history_v1_service::backfill_course((int)$course->id, [
            'dryrun' => false,
            'batchsize' => 1,
            'sources' => ['quiz_attempts'],
            'sourcelabel' => 'h7-test',
        ]);

        $this->assertSame('complete', $repeat['status']);
        $this->assertGreaterThanOrEqual(3, $repeat['sources']['quiz_attempts']['recordsupdated']);
        $this->assertSame(1, $DB->count_records('flwhist_source_event'));
        $this->assertSame(1, $DB->count_records('flwhist_attempt'));
        $this->assertSame(1, $DB->count_records('flwhist_question_attempt'));
    }

    public function test_reconciliation_repairs_grade_summary_without_rewriting_source_facts(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $teacher, $quiz, $gradeitem, $grade] = $this->create_quiz_grade_fixture(84.0);
        $this->cache_content_link((int)$course->id, (int)$quiz->cmid);

        $summaryid = \local_flwhistory\local\repository::upsert_grade_summary([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $quiz->cmid,
            'gradeitemid' => $gradeitem->id,
            'gradegradeid' => $grade->id,
            'officialgradegradeid' => $grade->id,
            'officialrawgrade' => 12.0,
            'officialfinalgrade' => 12.0,
            'officialgradetime' => 1000,
            'reconciliationstatus' => 'stale',
        ]);

        $dryrun = \local_flwhistory\local\history_v1_service::reconcile_course((int)$course->id, [
            'dryrun' => true,
            'batchsize' => 25,
        ]);

        $this->assertStringStartsWith('dry_run_complete', $dryrun['status']);
        $this->assertGreaterThanOrEqual(1, $dryrun['checks']['official_grade_summary']['recordsupdated']);
        $stale = $DB->get_record('flwhist_grade_summary', ['id' => $summaryid], '*', MUST_EXIST);
        $this->assertEquals(12.0, (float)$stale->officialfinalgrade);

        $sourcecount = $DB->count_records('flwhist_source_event');
        $gradeversioncount = $DB->count_records('flwhist_grade_version');
        $execute = \local_flwhistory\local\history_v1_service::reconcile_course((int)$course->id, [
            'dryrun' => false,
            'batchsize' => 25,
        ]);

        $this->assertStringStartsWith('complete', $execute['status']);
        $fixed = $DB->get_record('flwhist_grade_summary', ['id' => $summaryid], '*', MUST_EXIST);
        $this->assertEquals(84.0, (float)$fixed->officialfinalgrade);
        $this->assertSame($sourcecount, $DB->count_records('flwhist_source_event'));
        $this->assertSame($gradeversioncount, $DB->count_records('flwhist_grade_version'));
    }

    public function test_grade_history_backfill_migrates_legacy_other_sourcekey_without_duplication(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, , , $gradeitem, $grade] = $this->create_quiz_grade_fixture(77.0);
        $history = $DB->get_record('grade_grades_history', [
            'userid' => $user->id,
            'itemid' => $gradeitem->id,
            'oldid' => $grade->id,
        ], '*', MUST_EXIST);
        $classifiedkey = \local_flwhistory\local\grade_history_service::grade_history_row_sourcekey($history);
        $legacykey = \local_flwhistory\local\source_identity::make_key(
            'moodle',
            'grade_grades_history',
            (string)$history->id,
            (string)$history->timemodified,
            \local_flwhistory\local\grade_history_service::ACTION_OTHER
        );
        $this->assertNotSame($legacykey, $classifiedkey);

        \local_flwhistory\local\repository::upsert_grade_version([
            'sourcekey' => $legacykey,
            'sourcefactkey' => 'legacy-grade-fact',
            'sourcefamily' => 'gradebook',
            'userid' => $user->id,
            'courseid' => $course->id,
            'gradeitemid' => $gradeitem->id,
            'gradegradeid' => $grade->id,
            'gradehistoryid' => $history->id,
            'finalgrade' => 77.0,
            'action' => \local_flwhistory\local\grade_history_service::ACTION_OTHER,
            'gradetime' => (int)$history->timemodified,
        ]);

        $dryrun = \local_flwhistory\local\history_v1_service::backfill_course((int)$course->id, [
            'dryrun' => true,
            'batchsize' => 1,
            'sources' => ['grade_history'],
        ]);
        $this->assertSame(1, $dryrun['sources']['grade_history']['recordscreated']);
        $this->assertSame(1, $dryrun['sources']['grade_history']['recordsupdated']);
        $this->assertSame(1, $DB->count_records('flwhist_grade_version'));

        $execute = \local_flwhistory\local\history_v1_service::backfill_course((int)$course->id, [
            'dryrun' => false,
            'batchsize' => 1,
            'sources' => ['grade_history'],
        ]);

        $this->assertSame('complete', $execute['status']);
        $this->assertSame(1, $DB->count_records('flwhist_grade_version'));
        $this->assertTrue($DB->record_exists('flwhist_grade_version', ['sourcekey' => $classifiedkey]));
        $version = $DB->get_record('flwhist_grade_version', ['sourcekey' => $classifiedkey], '*', MUST_EXIST);
        $this->assertSame(\local_flwhistory\local\grade_history_service::ACTION_INITIAL, $version->action);
    }

    public function test_downstream_adapter_exposes_history_v1_facts_without_adaptive_payloads(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->cache_content_link((int)$course->id, (int)$page->cmid);

        $sourceeventid = $this->record_source_event((int)$course->id, (int)$user->id, (int)$page->cmid);
        $this->record_attempt((int)$course->id, (int)$page->cmid, (int)$user->id, $sourceeventid);
        $this->record_grade_version((int)$course->id, (int)$page->cmid, (int)$user->id, $sourceeventid);
        $this->record_completion((int)$course->id, (int)$page->cmid, (int)$user->id, $sourceeventid);
        $this->record_placement((int)$course->id, (int)$user->id, $sourceeventid);

        $contract = \local_flwhistory\local\history_v1_service::downstream_contract();
        $this->assertSame(\local_flwhistory\local\evidence_source_adapter::CONTRACT_VERSION, $contract['version']);
        $this->assertContains('attempts', $contract['facttypes']);
        $this->assertContains('content_identities', $contract['facttypes']);

        $sources = \local_flwhistory\local\evidence_source_adapter::source_events_for_course((int)$course->id, 10);
        $attempts = \local_flwhistory\local\evidence_source_adapter::attempts_for_course((int)$course->id, 10);
        $grades = \local_flwhistory\local\evidence_source_adapter::grades_for_course((int)$course->id, 10);
        $completions = \local_flwhistory\local\evidence_source_adapter::completions_for_course((int)$course->id, 10);
        $placements = \local_flwhistory\local\evidence_source_adapter::placements_for_course((int)$course->id, 10);
        $identities = \local_flwhistory\local\evidence_source_adapter::content_identities_for_course((int)$course->id, 10);

        $this->assertSame('source_event', $sources['records'][0]['facttype']);
        $this->assertSame('attempt', $attempts['records'][0]['facttype']);
        $this->assertSame('grade', $grades['records'][0]['facttype']);
        $this->assertSame('completion', $completions['records'][0]['facttype']);
        $this->assertSame('placement', $placements['records'][0]['facttype']);
        $this->assertSame('content_identity', $identities['records'][0]['facttype']);

        $encodedrecords = json_encode([
            $sources['records'],
            $attempts['records'],
            $grades['records'],
            $completions['records'],
            $placements['records'],
            $identities['records'],
        ]);
        $this->assertStringNotContainsString('mastery', $encodedrecords);
        $this->assertStringNotContainsString('recommendation', $encodedrecords);
        $this->assertStringNotContainsString('adaptive', $encodedrecords);
        $this->assertSame(1, $DB->count_records('flwhist_content_link', ['moodlecourseid' => $course->id]));
    }

    public function test_privacy_export_includes_coverage_and_delete_removes_it(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        \local_flwhistory\local\coverage_service::record_coverage([
            'sourcefamily' => 'quiz',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 100,
            'timerangeend' => 200,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_COMPLETE,
            'eventcount' => 1,
        ]);

        writer::reset();
        \local_flwhistory\privacy\provider::export_user_data(new approved_contextlist(
            $user,
            'local_flwhistory',
            [$context->id]
        ));
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_flwhistory')]);

        $this->assertObjectHasProperty('coverage', $data);
        $this->assertCount(1, $data->coverage);
        $this->assertSame('quiz', $data->coverage[0]->sourcefamily);

        \local_flwhistory\privacy\provider::delete_data_for_user(new approved_contextlist(
            $user,
            'local_flwhistory',
            [$context->id]
        ));

        $this->assertFalse($DB->record_exists('flwhist_coverage', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_performance_and_freeze_status_are_reported_for_history_v1(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $performance = \local_flwhistory\local\history_v1_service::performance_snapshot((int)$course->id, (int)$user->id, [
            'limit' => 5,
        ]);
        $this->assertSame('HistoryV1PerformanceSnapshot', $performance['type']);
        $this->assertSame('measured', $performance['status']);
        $this->assertArrayHasKey('summary', $performance['measurements']);
        $this->assertArrayHasKey('class_history_view', $performance['measurements']);

        $freeze = \local_flwhistory\local\history_v1_service::freeze_status((int)$course->id, [
            'userid' => $user->id,
            'limit' => 5,
        ]);
        $this->assertSame('HistoryV1FreezeStatus', $freeze['type']);
        $this->assertSame(\local_flwhistory\local\history_v1_service::HISTORY_VERSION, $freeze['historyversion']);
        $this->assertSame(\local_flwhistory\local\evidence_source_adapter::CONTRACT_VERSION, $freeze['contract']['version']);
        $this->assertContains($freeze['status'], ['frozen', 'blocked']);
        $this->assertNotSame('fail', $freeze['checks']['schema']['status']);
        $this->assertNotSame('fail', $freeze['checks']['downstream_contract']['status']);
    }

    private function create_quiz_attempt(
        int $attemptno,
        float $score,
        float $maxscore = 10.0,
        bool $withquestion = false,
        ?\stdClass $course = null,
        ?\stdClass $user = null,
        ?\stdClass $quiz = null
    ): array {
        global $DB;

        $course = $course ?: $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $user ?: $this->getDataGenerator()->create_user();
        $quiz = $quiz ?: $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'sumgrades' => $maxscore,
            'grade' => 100,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $context = \context_module::instance($quiz->cmid);
        $time = 1000 + ($attemptno * 100);
        $questionusageid = $DB->insert_record('question_usages', (object)[
            'contextid' => $context->id,
            'component' => 'mod_quiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        if ($withquestion) {
            $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
            $questioncategory = $questiongenerator->create_question_category(['contextid' => $context->id]);
            $question = $questiongenerator->create_question('truefalse', null, ['category' => $questioncategory->id]);
            $questionattemptid = $DB->insert_record('question_attempts', (object)[
                'questionusageid' => $questionusageid,
                'slot' => 1,
                'behaviour' => 'deferredfeedback',
                'questionid' => $question->id,
                'variant' => 1,
                'maxmark' => $maxscore,
                'minfraction' => 0,
                'maxfraction' => 1,
                'flagged' => 0,
                'questionsummary' => 'Question summary',
                'rightanswer' => 'True',
                'responsesummary' => 'True',
                'timemodified' => $time + 200,
            ]);
            $DB->insert_record('question_attempt_steps', (object)[
                'questionattemptid' => $questionattemptid,
                'sequencenumber' => 1,
                'state' => 'gradedright',
                'fraction' => $score / $maxscore,
                'timecreated' => $time + 200,
                'userid' => $user->id,
            ]);
        }

        $attempt = (object)[
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'attempt' => $attemptno,
            'uniqueid' => $questionusageid,
            'layout' => $withquestion ? '1,0' : '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => $time,
            'timefinish' => $time + 200,
            'timemodified' => $time + 200,
            'timemodifiedoffline' => 0,
            'timecheckstate' => null,
            'sumgrades' => $score,
        ];
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

        return [$course, $user, $quiz, $attempt];
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
        $gradeitem = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'itemnumber' => 0,
        ]);
        $this->assertInstanceOf(\grade_item::class, $gradeitem);
        $grade = $this->update_official_grade($course, $quiz, $gradeitem, $user, $teacher, $officialgrade);

        return [$course, $user, $teacher, $quiz, $gradeitem, $grade];
    }

    private function update_official_grade(
        \stdClass $course,
        \stdClass $quiz,
        \grade_item $gradeitem,
        \stdClass $user,
        \stdClass $teacher,
        float $officialgrade
    ): \grade_grade {
        $sink = $this->redirectEvents();
        $result = grade_update('mod/quiz', $course->id, 'mod', 'quiz', $quiz->id, 0, [
            'userid' => $user->id,
            'rawgrade' => $officialgrade,
            'usermodified' => $teacher->id,
            'dategraded' => 2000,
        ]);
        $sink->close();

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $grade = \grade_grade::fetch(['userid' => $user->id, 'itemid' => $gradeitem->id]);
        $this->assertInstanceOf(\grade_grade::class, $grade);
        $grade->grade_item = $gradeitem;
        return $grade;
    }

    private function cache_content_link(int $courseid, int $cmid): int {
        return \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => $courseid,
            'cmid' => $cmid,
            'worldid' => 'W-B1',
            'stageid' => 'S-READ',
            'unitid' => 'U038',
            'lessonid' => 'L-038',
            'componentid' => 'C-038',
            'activityid' => 'FLW-EN-B1-READ-038-001',
            'assessmentid' => 'ASSESS-038',
            'sourcerevision' => 'h7-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);
    }

    private function record_source_event(int $courseid, int $userid, int $cmid): int {
        return \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'h7_test',
            'sourceid' => (string)$userid . '-source',
            'sourceversion' => '3000',
            'eventtype' => 'ASSESSMENT_COMPLETED',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => 'U038',
            'activityid' => 'FLW-EN-B1-READ-038-001',
            'assessmentid' => 'ASSESS-038',
            'eventtime' => 3000,
            'status' => 'recorded',
            'normalizer' => 'h7_test',
            'summaryjson' => ['visible' => true],
        ]);
    }

    private function record_attempt(int $courseid, int $cmid, int $userid, int $sourceeventid): int {
        return \local_flwhistory\local\attempt_service::record_attempt([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'quiz_attempt',
                (string)$userid . '-attempt',
                '3100',
                'finished'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcefamily' => 'quiz',
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$userid . '-attempt',
            'sourceversion' => '3100',
            'sourceattemptid' => (string)$userid . '-attempt',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'unitid' => 'U038',
            'activityid' => 'FLW-EN-B1-READ-038-001',
            'assessmentid' => 'ASSESS-038',
            'attemptno' => 1,
            'attemptstate' => 'finished',
            'rawscore' => 8,
            'maxscore' => 10,
            'scaledscore' => 0.8,
            'timestart' => 3000,
            'timefinish' => 3100,
            'summaryjson' => ['history_v1' => true],
        ]);
    }

    private function record_grade_version(int $courseid, int $cmid, int $userid, int $sourceeventid): int {
        return \local_flwhistory\local\grade_history_service::record_grade_version([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'grade_grades_history',
                (string)$userid . '-grade',
                '3200',
                'OTHER'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcefamily' => 'gradebook',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'gradeitemid' => 101,
            'gradegradeid' => 202,
            'finalgrade' => 82,
            'action' => \local_flwhistory\local\grade_history_service::ACTION_OTHER,
            'reason' => null,
            'gradetime' => 3200,
            'summaryjson' => ['history_v1' => true],
        ]);
    }

    private function record_completion(int $courseid, int $cmid, int $userid, int $sourceeventid): int {
        return \local_flwhistory\local\completion_service::record_completion([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'moodle',
                'course_module_completion',
                (string)$userid . '-' . (string)$cmid,
                '3300',
                'complete'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcefamily' => 'completion',
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'completionstate' => COMPLETION_COMPLETE,
            'viewed' => 1,
            'completiontime' => 3300,
            'detailsjson' => ['history_v1' => true],
        ]);
    }

    private function record_placement(int $courseid, int $userid, int $sourceeventid): int {
        return \local_flwhistory\local\placement_history_service::record_placement([
            'sourcekey' => \local_flwhistory\local\source_identity::make_key(
                'flwplacement',
                'placement',
                (string)$userid . '-placement',
                '3400',
                'recorded'
            ),
            'sourceeventid' => $sourceeventid,
            'sourcesystem' => 'flwplacement',
            'sourcefamily' => 'placement',
            'sourcetype' => 'placement',
            'sourceid' => (string)$userid . '-placement',
            'sourceversion' => '3400',
            'userid' => $userid,
            'courseid' => $courseid,
            'currentlevel' => 'B1',
            'placementstatus' => 'recorded',
            'score' => 0.82,
            'confidence' => 0.9,
            'placementtime' => 3400,
            'profilejson' => ['history_v1' => true],
        ]);
    }
}

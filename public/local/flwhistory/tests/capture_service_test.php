<?php
// PHPUnit tests for local_flwhistory H2 source capture.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * Core event, attempt, completion, and custom FLW capture tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\capture_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\observer::class)]
class capture_service_test extends \advanced_testcase {
    public function test_first_quiz_attempt_captures_source_attempt_questions_scores_and_mapping(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt] = $this->create_quiz_attempt(1, 7.5, 10.0, true);
        $this->cache_activity_mapping((int)$course->id, (int)$quiz->cmid);
        $this->set_quiz_gradepass((int)$course->id, (int)$quiz->id, 50.0);

        $event = $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt, 1200);
        $result = \local_flwhistory\local\capture_service::capture_quiz_attempt_event($event);

        $this->assertSame('captured', $result['status']);
        $this->assertSame(1, $DB->count_records('flwhist_source_event'));
        $this->assertSame(1, $DB->count_records('flwhist_attempt'));
        $this->assertSame(1, $DB->count_records('flwhist_question_attempt'));

        $source = $DB->get_record('flwhist_source_event', [], '*', MUST_EXIST);
        $this->assertSame('ASSESSMENT_COMPLETED', $source->eventtype);
        $this->assertSame('recorded', $source->status);
        $this->assertSame('U038', $source->unitid);
        $this->assertSame('FLW-EN-B1-READ-038-001', $source->activityid);
        $this->assertSame(\local_flwhistory\local\history_policy::NORMALIZATION_POLICY_VERSION, $source->normpolicyversion);

        $captured = $DB->get_record('flwhist_attempt', ['sourceattemptid' => (string)$attempt->id], '*', MUST_EXIST);
        $this->assertSame(1, (int)$captured->attemptno);
        $this->assertEquals(7.5, (float)$captured->rawscore);
        $this->assertEquals(10.0, (float)$captured->maxscore);
        $this->assertEquals(0.75, (float)$captured->scaledscore);
        $this->assertSame($source->sourcefactkey, $captured->sourcefactkey);
        $summary = json_decode($captured->summaryjson, true);
        $this->assertSame(200, $summary['durationseconds']);
        $this->assertSame('passed', $summary['result']);
        $this->assertSame(1, $summary['pass']);

        $question = $DB->get_record('flwhist_question_attempt', [], '*', MUST_EXIST);
        $this->assertEquals(0.75, (float)$question->fraction);
        $this->assertEquals(7.5, (float)$question->rawmark);
    }

    public function test_duplicate_quiz_event_retry_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt] = $this->create_quiz_attempt(1, 6.0);
        $event = $this->create_quiz_event(\mod_quiz\event\attempt_submitted::class, $course, $user, $quiz, $attempt, 1300);

        \local_flwhistory\local\capture_service::capture_quiz_attempt_event($event);
        \local_flwhistory\local\capture_service::capture_quiz_attempt_event($event);

        $this->assertSame(1, $DB->count_records('flwhist_source_event'));
        $this->assertSame(1, $DB->count_records('flwhist_attempt'));
    }

    public function test_multiple_quiz_attempts_remain_separate_and_ordered(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt1] = $this->create_quiz_attempt(1, 5.4);
        [, , , $attempt2] = $this->create_quiz_attempt(2, 6.7, 10.0, false, $course, $user, $quiz);
        [, , , $attempt3] = $this->create_quiz_attempt(3, 8.2, 10.0, false, $course, $user, $quiz);

        \local_flwhistory\local\capture_service::capture_quiz_attempt_event(
            $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt1, 1100)
        );
        \local_flwhistory\local\capture_service::capture_quiz_attempt_event(
            $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt2, 1200)
        );
        \local_flwhistory\local\capture_service::capture_quiz_attempt_event(
            $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt3, 1300)
        );

        $records = $DB->get_records('flwhist_attempt', ['userid' => $user->id], 'attemptno ASC');
        $this->assertCount(3, $records);
        $this->assertSame([1, 2, 3], array_map(fn($record) => (int)$record->attemptno, array_values($records)));
        $this->assertSame(['5.40000', '6.70000', '8.20000'],
            array_map(fn($record) => sprintf('%.5f', (float)$record->rawscore), array_values($records)));
    }

    public function test_completion_capture_uses_moodle_completion_row(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $cm = get_coursemodule_from_id('page', $page->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $time = 1500;
        $completionid = $DB->insert_record('course_modules_completion', (object)[
            'coursemoduleid' => $cm->id,
            'userid' => $user->id,
            'completionstate' => COMPLETION_COMPLETE,
            'overrideby' => null,
            'timemodified' => $time,
        ]);

        $event = \core\event\course_module_completion_updated::create([
            'objectid' => $completionid,
            'context' => $context,
            'courseid' => $course->id,
            'userid' => $user->id,
            'relateduserid' => $user->id,
            'other' => [
                'relateduserid' => $user->id,
                'completionstate' => COMPLETION_COMPLETE,
            ],
        ]);
        $result = \local_flwhistory\local\capture_service::capture_course_module_completion($event);

        $this->assertSame('captured', $result['status']);
        $record = $DB->get_record('flwhist_completion', ['userid' => $user->id, 'cmid' => $cm->id], '*', MUST_EXIST);
        $this->assertSame(COMPLETION_COMPLETE, (int)$record->completionstate);
        $this->assertSame($time, (int)$record->completiontime);
        $source = $DB->get_record('flwhist_source_event', ['id' => $result['sourceeventid']], '*', MUST_EXIST);
        $this->assertSame('CHECKPOINT_COMPLETED', $source->eventtype);
    }

    public function test_missing_program1_mapping_preserves_source_fact_as_unresolved(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt] = $this->create_quiz_attempt(1, 9.0);

        $event = $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt, 1400);
        $result = \local_flwhistory\local\capture_service::capture_quiz_attempt_event($event);

        $this->assertSame('captured', $result['status']);
        $source = $DB->get_record('flwhist_source_event', ['id' => $result['sourceeventid']], '*', MUST_EXIST);
        $this->assertSame('unresolved_mapping', $source->status);
        $this->assertTrue($source->activityid === null || $source->activityid === '');
        $this->assertSame(1, $DB->count_records('flwhist_attempt'));
    }

    public function test_post_processing_failure_keeps_source_fact_and_records_diagnostic_run(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt] = $this->create_quiz_attempt(1, 7.0);
        $event = $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt, 1500);

        $result = \local_flwhistory\local\capture_service::capture_quiz_attempt_event($event, [
            'simulatepostsourcefailure' => true,
        ]);

        $this->assertSame('failed_after_source', $result['status']);
        $this->assertSame(1, $DB->count_records('flwhist_source_event'));
        $this->assertSame(0, $DB->count_records('flwhist_attempt'));
        $this->assertSame(1, $DB->count_records('flwhist_reconcile_run', ['status' => 'failed_after_source']));
    }

    public function test_representative_custom_flwvrroom_event_is_captured(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $carrier = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('page', $carrier->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $roomid = $DB->insert_record('flwvrroom', (object)[
            'course' => $course->id,
            'name' => 'H2 VR Room',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'grade' => 100,
            'timecreated' => 1600,
            'timemodified' => 1600,
        ]);
        $attemptid = $DB->insert_record('flwvrroom_attempts', (object)[
            'flwvrroomid' => $roomid,
            'userid' => $user->id,
            'score' => 82,
            'completedobjects' => 'door,desk',
            'kpcodes' => 'KP-1',
            'speakingtext' => 'hello',
            'aifeedback' => 'ok',
            'hotspotsjson' => '{}',
            'roleturnsjson' => '{}',
            'speakingjson' => '{}',
            'taskcomplete' => 1,
            'durationseconds' => 90,
            'timecreated' => 1600,
        ]);
        $event = \mod_flwvrroom\event\attempt_submitted::create([
            'objectid' => $attemptid,
            'context' => $context,
            'courseid' => $course->id,
            'userid' => $user->id,
            'relateduserid' => $user->id,
            'other' => [
                'attemptid' => $attemptid,
                'cmid' => $cm->id,
                'courseid' => $course->id,
                'userid' => $user->id,
                'score' => 82,
                'maxscore' => 100,
                'kpcodes' => 'KP-1',
                'xrmode' => 'panorama',
                'scenario' => 'At the Cafe',
            ],
        ]);

        $result = \local_flwhistory\local\capture_service::capture_flwvrroom_attempt_submitted($event);

        $this->assertSame('captured', $result['status']);
        $source = $DB->get_record('flwhist_source_event', ['id' => $result['sourceeventid']], '*', MUST_EXIST);
        $this->assertSame('flwvrroom', $source->sourcesystem);
        $this->assertSame('SPEAKING_ATTEMPTED', $source->eventtype);
        $attempt = $DB->get_record('flwhist_attempt', ['sourceattemptid' => (string)$attemptid], '*', MUST_EXIST);
        $this->assertEquals(82.0, (float)$attempt->rawscore);
        $this->assertEquals(0.82, (float)$attempt->scaledscore);
    }

    public function test_capture_coverage_refresh_creates_not_backfilled_course_coverage(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user, $quiz, $attempt] = $this->create_quiz_attempt(1, 8.0);
        $event = $this->create_quiz_event(\mod_quiz\event\attempt_graded::class, $course, $user, $quiz, $attempt, 1700);
        \local_flwhistory\local\capture_service::capture_quiz_attempt_event($event);

        $result = \local_flwhistory\local\capture_service::refresh_capture_coverage();

        $this->assertSame('complete', $result['status']);
        $coverage = $DB->get_record('flwhist_coverage', [
            'scopelevel' => 'course',
            'sourcefamily' => 'quiz',
            'courseid' => $course->id,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_NOT_BACKFILLED,
        ], '*', MUST_EXIST);
        $this->assertSame('EVENT_AVAILABLE', $coverage->eventavailability);
        $this->assertSame(1, $DB->count_records('flwhist_reconcile_run', ['status' => 'complete']));
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

        $course = $course ?: $this->getDataGenerator()->create_course();
        $user = $user ?: $this->getDataGenerator()->create_user();
        $quiz = $quiz ?: $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'sumgrades' => $maxscore,
            'grade' => 100,
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

    private function create_quiz_event(
        string $eventclass,
        \stdClass $course,
        \stdClass $user,
        \stdClass $quiz,
        \stdClass $attempt,
        int $timecreated
    ): \core\event\base {
        $context = \context_module::instance($quiz->cmid);
        $other = ['quizid' => $quiz->id];
        if (in_array($eventclass, [
            \mod_quiz\event\attempt_submitted::class,
            \mod_quiz\event\attempt_graded::class,
        ], true)) {
            $other['submitterid'] = $user->id;
            $other['studentisonline'] = false;
        }

        return $eventclass::create([
            'objectid' => $attempt->id,
            'context' => $context,
            'courseid' => $course->id,
            'userid' => $user->id,
            'relateduserid' => $user->id,
            'other' => $other,
        ]);
    }

    private function cache_activity_mapping(int $courseid, int $cmid): void {
        \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => $courseid,
            'cmid' => $cmid,
            'worldid' => 'W-B1',
            'stageid' => 'S-READ',
            'unitid' => 'U038',
            'lessonid' => 'L-038',
            'componentid' => 'C-038',
            'activityid' => 'FLW-EN-B1-READ-038-001',
            'sourcerevision' => 'h2-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);
    }

    private function set_quiz_gradepass(int $courseid, int $quizid, float $gradepass): void {
        global $DB;

        $DB->set_field('grade_items', 'gradepass', $gradepass, [
            'courseid' => $courseid,
            'itemmodule' => 'quiz',
            'iteminstance' => $quizid,
        ]);
    }
}

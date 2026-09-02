<?php
// PHPUnit tests for local_flwhistory H1B coverage and normalization policy.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * Coverage and normalization policy tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\history_policy::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\coverage_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\history_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\evidence_source_adapter::class)]
class coverage_policy_test extends \advanced_testcase {
    public function test_fresh_install_without_backfill_returns_unknown_no_event_available(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $coverage = \local_flwhistory\local\coverage_service::get_coverage([
            'sourcefamily' => 'quiz',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 100,
            'timerangeend' => 200,
        ]);

        $this->assertNull($coverage['id']);
        $this->assertSame(\local_flwhistory\local\history_policy::COVERAGE_UNKNOWN, $coverage['coveragestatus']);
        $this->assertSame(\local_flwhistory\local\history_policy::NO_EVENT_AVAILABLE, $coverage['eventavailability']);
        $this->assertFalse($coverage['sufficient']);
        $this->assertFalse(\local_flwhistory\local\coverage_service::can_evaluate_inactivity(
            $user->id,
            $course->id,
            'quiz',
            100,
            200
        ));
    }

    public function test_partial_quiz_and_complete_grade_coverage_are_queryable(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        \local_flwhistory\local\coverage_service::record_coverage([
            'sourcefamily' => 'quiz',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 100,
            'timerangeend' => 500,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_PARTIAL,
            'eventcount' => 4,
            'earliestreliableeventat' => 200,
            'latestreconciledat' => 500,
            'backfillstartedat' => 100,
            'capturestartedat' => 150,
        ]);
        \local_flwhistory\local\coverage_service::record_coverage([
            'sourcefamily' => 'gradebook',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 100,
            'timerangeend' => 500,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_COMPLETE,
            'eventcount' => 0,
            'earliestreliableeventat' => 100,
            'latestreconciledat' => 500,
            'backfillcompletedat' => 500,
        ]);

        $quiz = \local_flwhistory\local\coverage_service::get_coverage([
            'sourcefamily' => 'quiz',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 150,
            'timerangeend' => 450,
        ]);
        $grade = \local_flwhistory\local\coverage_service::get_coverage([
            'sourcefamily' => 'gradebook',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 150,
            'timerangeend' => 450,
        ]);

        $this->assertSame(\local_flwhistory\local\history_policy::COVERAGE_PARTIAL, $quiz['coveragestatus']);
        $this->assertSame(\local_flwhistory\local\history_policy::EVENT_AVAILABLE, $quiz['eventavailability']);
        $this->assertFalse($quiz['sufficient']);
        $this->assertSame(\local_flwhistory\local\history_policy::COVERAGE_COMPLETE, $grade['coveragestatus']);
        $this->assertSame(\local_flwhistory\local\history_policy::NO_EVENT_OCCURRED, $grade['eventavailability']);
        $this->assertTrue($grade['sufficient']);
    }

    public function test_source_family_unavailable_is_no_event_available(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        \local_flwhistory\local\coverage_service::record_coverage([
            'sourcefamily' => 'h5p',
            'courseid' => $course->id,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_SOURCE_LIMITED,
            'sourceavailable' => 0,
            'eventcount' => 0,
            'reasoncode' => 'SOURCE_PLUGIN_DISABLED',
        ]);

        $coverage = \local_flwhistory\local\coverage_service::get_coverage([
            'sourcefamily' => 'h5p',
            'courseid' => $course->id,
        ]);

        $this->assertSame(\local_flwhistory\local\history_policy::COVERAGE_SOURCE_LIMITED, $coverage['coveragestatus']);
        $this->assertSame(\local_flwhistory\local\history_policy::NO_EVENT_AVAILABLE, $coverage['eventavailability']);
        $this->assertSame(0, $coverage['sourceavailable']);
        $this->assertSame('SOURCE_PLUGIN_DISABLED', $coverage['reasoncode']);
    }

    public function test_inactivity_query_outside_coverage_is_not_evaluable(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        \local_flwhistory\local\coverage_service::record_coverage([
            'sourcefamily' => 'quiz',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 100,
            'timerangeend' => 200,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_COMPLETE,
            'eventcount' => 0,
            'earliestreliableeventat' => 100,
            'latestreconciledat' => 200,
        ]);

        $this->assertFalse(\local_flwhistory\local\coverage_service::can_evaluate_inactivity(
            $user->id,
            $course->id,
            'quiz',
            50,
            150
        ));
        $this->assertTrue(\local_flwhistory\local\coverage_service::can_evaluate_inactivity(
            $user->id,
            $course->id,
            'quiz',
            110,
            190
        ));
    }

    public function test_normalization_policy_version_change_preserves_source_fact_key(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $oldid = \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => '9',
            'sourceversion' => '1000',
            'eventtype' => 'attempt_submitted',
            'userid' => $user->id,
            'courseid' => $course->id,
            'eventtime' => 1000,
            'summaryjson' => ['score' => 0.7],
        ]);
        $newid = \local_flwhistory\local\history_service::record_normalization_supersession(
            $oldid,
            ['score' => 0.75],
            'H1B-20260827.2',
            'Policy precision change',
            $user->id
        );

        $old = $DB->get_record('flwhist_source_event', ['id' => $oldid], '*', MUST_EXIST);
        $new = $DB->get_record('flwhist_source_event', ['id' => $newid], '*', MUST_EXIST);

        $this->assertNotSame($old->sourcekey, $new->sourcekey);
        $this->assertSame($old->sourcefactkey, $new->sourcefactkey);
        $this->assertSame($old->sourceid, $new->sourceid);
        $this->assertSame($old->sourceversion, $new->sourceversion);
        $this->assertSame(\local_flwhistory\local\history_policy::NORMALIZATION_POLICY_VERSION, $old->normpolicyversion);
        $this->assertSame('H1B-20260827.2', $new->normpolicyversion);
        $this->assertSame($newid, (int)$old->supersededby);
        $this->assertSame($oldid, (int)$new->correctionof);
    }

    public function test_corrected_normalization_supersession_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();

        $oldid = \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => '10',
            'sourceversion' => '1000',
            'eventtype' => 'attempt_graded',
            'userid' => $user->id,
            'eventtime' => 1000,
            'summaryjson' => ['state' => 'graded'],
        ]);

        $first = \local_flwhistory\local\history_service::record_normalization_supersession(
            $oldid,
            ['state' => 'graded', 'normalised' => true],
            'H1B-20260827.2',
            'Normalize state label',
            $user->id
        );
        $second = \local_flwhistory\local\history_service::record_normalization_supersession(
            $oldid,
            ['state' => 'graded', 'normalised' => true],
            'H1B-20260827.2',
            'Normalize state label',
            $user->id
        );

        $this->assertSame($first, $second);
        $this->assertSame(2, $DB->count_records('flwhist_source_event'));
        $this->assertSame(1, $DB->count_records('flwhist_correction'));
    }

    public function test_program3_adapter_includes_coverage_status(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        \local_flwhistory\local\coverage_service::record_coverage([
            'sourcefamily' => 'quiz',
            'userid' => $user->id,
            'courseid' => $course->id,
            'timerangestart' => 900,
            'timerangeend' => 1200,
            'coveragestatus' => \local_flwhistory\local\history_policy::COVERAGE_COMPLETE,
            'eventcount' => 1,
            'earliestreliableeventat' => 900,
            'latestreconciledat' => 1200,
        ]);
        $sourceeventid = \local_flwhistory\local\history_service::record_source_event([
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => '11',
            'sourceversion' => '1000',
            'eventtype' => 'attempt_submitted',
            'userid' => $user->id,
            'courseid' => $course->id,
            'eventtime' => 1000,
            'summaryjson' => ['attempt' => 11],
        ]);
        $sourceevent = \local_flwhistory\local\repository::get_source_event($sourceeventid);

        $payload = \local_flwhistory\local\evidence_source_adapter::source_event_to_payload($sourceevent);

        $this->assertSame('local_flwhistory', $payload['source']);
        $this->assertSame('quiz', $payload['sourcefamily']);
        $this->assertSame(\local_flwhistory\local\history_policy::NORMALIZATION_POLICY_VERSION,
            $payload['normpolicyversion']);
        $this->assertSame(\local_flwhistory\local\history_policy::COVERAGE_COMPLETE,
            $payload['coverage']['coveragestatus']);
        $this->assertSame(\local_flwhistory\local\history_policy::EVENT_AVAILABLE,
            $payload['coverage']['eventavailability']);
    }
}


<?php
// PHPUnit tests for local_flwhistory repository and services.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository and service contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\repository::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\history_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\attempt_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\grade_history_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\placement_history_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\p1_resolver::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\normalizer::class)]
class repository_test extends \advanced_testcase {
    public function test_source_event_upsert_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $data = [
            'sourcesystem' => 'moodle',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => '9',
            'sourceversion' => '1001',
            'eventtype' => 'attempt_submitted',
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => 123,
            'unitid' => 'U038',
            'activityid' => 'FLW-READ-038-001',
            'eventtime' => 1001,
            'summaryjson' => ['attempt' => 9, 'state' => 'finished'],
        ];

        $firstid = \local_flwhistory\local\history_service::record_source_event($data);
        $data['status'] = 'replayed';
        $secondid = \local_flwhistory\local\history_service::record_source_event($data);

        $this->assertSame($firstid, $secondid);
        $this->assertSame(1, $DB->count_records('flwhist_source_event'));
        $record = $DB->get_record('flwhist_source_event', ['id' => $firstid], '*', MUST_EXIST);
        $this->assertSame('replayed', $record->status);
        $this->assertSame('U038', $record->unitid);
    }

    public function test_attempt_and_normalizer_round_trip_program1_ids(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $quizattempt = (object)[
            'id' => 9,
            'quiz' => 77,
            'userid' => $user->id,
            'attempt' => 2,
            'uniqueid' => 555,
            'layout' => '1,2,0',
            'state' => 'finished',
            'sumgrades' => 8.5,
            'timestart' => 900,
            'timefinish' => 1000,
            'timemodified' => 1001,
        ];

        $dto = \local_flwhistory\local\normalizer::quiz_attempt_to_attempt($quizattempt, [
            'courseid' => $course->id,
            'cmid' => 222,
            'worldid' => 'W1',
            'stageid' => 'B1',
            'unitid' => 'U038',
            'activityid' => 'FLW-EN-B1-READ-038-001',
        ]);
        $attemptid = \local_flwhistory\local\attempt_service::record_attempt($dto);

        $attempt = $DB->get_record('flwhist_attempt', ['id' => $attemptid], '*', MUST_EXIST);
        $this->assertSame('U038', $attempt->unitid);
        $this->assertSame('FLW-EN-B1-READ-038-001', $attempt->activityid);
        $this->assertSame('9', $attempt->sourceattemptid);
        $this->assertEquals(8.5, (float)$attempt->rawscore);
    }

    public function test_p1_content_link_cache_resolves_cmid(): void {
        $this->resetAfterTest(true);

        \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => 124,
            'moodlesectionid' => 99,
            'cmid' => 2123,
            'scoidentifier' => 'read-038-001',
            'worldid' => 'FLW-EN',
            'stageid' => 'B1',
            'unitid' => 'U038',
            'activityid' => 'FLW-EN-B1-READ-038-001',
            'sourcerevision' => 'rev-001',
            'freshness' => 'CURRENT',
            'status' => 'resolved',
        ]);

        $result = \local_flwhistory\local\p1_resolver::resolve_cmid(2123);
        $this->assertSame('resolved', $result['status']);
        $this->assertSame('CURRENT', $result['freshness']);
        $this->assertSame('U038', $result['unitid']);
        $this->assertSame('FLW-EN-B1-READ-038-001', $result['activityid']);
    }

    public function test_grade_correction_links_versions(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $oldid = \local_flwhistory\local\grade_history_service::record_grade_version([
            'sourcekey' => 'moodle:grade_grade:88:1000',
            'userid' => $user->id,
            'courseid' => $course->id,
            'gradeitemid' => 44,
            'gradegradeid' => 88,
            'finalgrade' => 70,
            'action' => 'recorded',
            'gradetime' => 1000,
        ]);
        $newid = \local_flwhistory\local\grade_history_service::record_grade_version([
            'sourcekey' => 'moodle:grade_grade:88:1100',
            'userid' => $user->id,
            'courseid' => $course->id,
            'gradeitemid' => 44,
            'gradegradeid' => 88,
            'previousgrade' => 70,
            'finalgrade' => 82,
            'action' => 'corrected',
            'gradetime' => 1100,
        ]);

        \local_flwhistory\local\grade_history_service::record_grade_correction($newid, $oldid, 'Manual correction', $user->id);

        $old = $DB->get_record('flwhist_grade_version', ['id' => $oldid], '*', MUST_EXIST);
        $new = $DB->get_record('flwhist_grade_version', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame($newid, (int)$old->supersededby);
        $this->assertSame($oldid, (int)$new->correctionof);
        $this->assertSame(1, $DB->count_records('flwhist_correction'));
    }

    public function test_placement_history_records_level_transition(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $id = \local_flwhistory\local\placement_history_service::record_placement([
            'sourcesystem' => 'flwplacement',
            'sourcetype' => 'placement',
            'sourceid' => '501',
            'sourceversion' => '1200',
            'userid' => $user->id,
            'courseid' => $course->id,
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'score' => 0.78,
            'confidence' => 0.9,
            'profilejson' => ['reason' => 'placement test'],
            'placementtime' => 1200,
        ]);

        $history = \local_flwhistory\local\placement_history_service::get_placement_history($user->id, $course->id);
        $this->assertCount(1, $history);
        $this->assertSame($id, (int)$history[0]->id);
        $this->assertSame('B1', $history[0]->currentlevel);
    }
}


<?php
// PHPUnit coverage for History V1 SCORM capture and repair.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * SCORM source capture remains replay-safe across observer and repair paths.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\capture_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\observer::class)]
class scorm_capture_service_test extends \advanced_testcase {
    public function test_controlled_repair_normalizes_completed_scorm_attempt_idempotently(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_scorm');

        $generator->create_attempt([
            'scormid' => $scorm->id,
            'userid' => $user->id,
            'attempt' => 1,
            'element' => 'cmi.core.score.raw',
            'value' => '84',
        ]);
        $generator->create_attempt([
            'scormid' => $scorm->id,
            'userid' => $user->id,
            'attempt' => 1,
            'element' => 'cmi.core.lesson_status',
            'value' => 'completed',
        ]);

        $attempt = $DB->get_record('scorm_attempt', [
            'scormid' => $scorm->id,
            'userid' => $user->id,
            'attempt' => 1,
        ], '*', MUST_EXIST);
        $this->cache_activity_mapping((int)$course->id, (int)$scorm->cmid);

        $first = \local_flwhistory\local\capture_service::repair_scorm_attempt((int)$attempt->id);
        $second = \local_flwhistory\local\capture_service::repair_scorm_attempt((int)$attempt->id);

        $this->assertSame('captured', $first['status']);
        $this->assertSame($first['attemptrecordid'], $second['attemptrecordid']);
        $this->assertSame(1, $DB->count_records('flwhist_attempt', [
            'sourcefamily' => 'scorm',
            'sourceattemptid' => (string)$attempt->id,
        ]));

        $history = $DB->get_record('flwhist_attempt', [
            'sourcefamily' => 'scorm',
            'sourceattemptid' => (string)$attempt->id,
        ], '*', MUST_EXIST);
        $this->assertSame('complete', $history->attemptstate);
        $this->assertEquals(84.0, (float)$history->rawscore);
        $this->assertEquals(0.84, (float)$history->scaledscore);
        $this->assertSame('U001', $history->unitid);
        $this->assertSame(\local_flwhistory\local\history_policy::NORMALIZATION_POLICY_VERSION,
            $history->normpolicyversion);
    }

    /** Cache a resolved Program 1 activity identity for the generated SCORM module. */
    private function cache_activity_mapping(int $courseid, int $cmid): void {
        \local_flwhistory\local\p1_resolver::cache_content_link([
            'moodlecourseid' => $courseid,
            'cmid' => $cmid,
            'worldid' => 'REW-A1',
            'stageid' => 'A1',
            'unitid' => 'U001',
            'lessonid' => 'U001',
            'componentid' => 'UNITSCORM',
            'activityid' => 'FLW_REW_U001_UNITSCORM',
            'sourcerevision' => 'scorm-history-test',
            'freshness' => 'current',
            'status' => 'resolved',
        ]);
    }
}

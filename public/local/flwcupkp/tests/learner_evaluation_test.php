<?php
// PHPUnit tests for local_flwcupkp learner evaluation.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Learner evaluation subsystem tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\learner_evaluation::class)]
class learner_evaluation_test extends \advanced_testcase {
    public function test_period_self_evaluation_diagnostics_and_snapshot(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);

        $frameworkid = $this->create_framework($course->id);
        $masteredkp = $this->create_kp($frameworkid, 'LE-KP-MASTERED');
        $gapkp = $this->create_kp($frameworkid, 'LE-KP-GAP');
        $objectid = $this->create_object($frameworkid, $course->id, 'U038', 'Learner evaluation practice');
        $this->map_object($objectid, 'kp', $masteredkp);
        $this->map_object($objectid, 'kp', $gapkp);

        \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $learner->id,
            'courseid' => $course->id,
            'unitcode' => 'U038',
            'objectid' => $objectid,
            'evidencetype' => 'manual_teacher_evidence',
            'targettype' => 'kp',
            'targetid' => $masteredkp,
            'rawscore' => 0.95,
            'normalizedscore' => 0.95,
            'confidence' => 0.85,
            'evidencestrength' => 'independent_performance',
            'provenance' => 'phpunit',
        ]);

        $periodid = \local_flwcupkp\local\learner_evaluation::save_period([
            'courseid' => $course->id,
            'frameworkid' => $frameworkid,
            'name' => 'U038 V4 checkpoint',
            'periodtype' => 'unit',
            'unitcode' => 'U038',
            'cefr' => 'B1',
            'status' => 'active',
        ]);
        $this->assertGreaterThan(0, $periodid);

        $selfeval = \local_flwcupkp\local\learner_evaluation::record_self_evaluation(
            $learner->id,
            $course->id,
            $periodid,
            'kp',
            $gapkp,
            0.90,
            'I think I can do this but need evidence.'
        );
        $this->assertSame('saved', $selfeval['status']);

        $profile = \local_flwcupkp\local\learner_evaluation::profile($learner->id, $course->id, $periodid);
        $this->assertSame(2, $profile['summary']['kp_total']);
        $this->assertSame(1, $profile['summary']['kp_mastered']);
        $this->assertGreaterThanOrEqual(1, $profile['summary']['diagnostic_count']);

        $categories = array_map(static function($diagnostic): string {
            return (string)$diagnostic->gapcategory;
        }, $profile['diagnostics']);
        $this->assertContains('mastery_gap', $categories);
        $this->assertContains('self_eval_mismatch', $categories);

        $snapshot = \local_flwcupkp\local\learner_evaluation::create_snapshot(
            $learner->id,
            $course->id,
            $frameworkid,
            $periodid,
            'unit'
        );
        $this->assertGreaterThan(0, $snapshot['snapshotid']);
        $this->assertSame(2, $snapshot['summary']['kp_total']);
        $unitprofile = \local_flwcupkp\local\learner_evaluation::profile($learner->id, $course->id, 0, 'U038');
        $this->assertSame($snapshot['snapshotid'], $unitprofile['latest_snapshot']['id']);
        $this->assertSame(1, $DB->count_records('flwcupkp_eval_snapshot', ['userid' => $learner->id]));
        $this->assertGreaterThanOrEqual(1, $DB->count_records('flwcupkp_diagnostic', [
            'userid' => $learner->id,
            'courseid' => $course->id,
            'periodid' => $periodid,
            'status' => 'active',
        ]));
    }

    public function test_class_summary_counts_enrolled_learners(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $frameworkid = $this->create_framework($course->id, 'LE-FW-CLASS');
        $kpid = $this->create_kp($frameworkid, 'LE-KP-CLASS');
        $objectid = $this->create_object($frameworkid, $course->id, 'U777', 'Class summary practice');
        $this->map_object($objectid, 'kp', $kpid);

        $summary = \local_flwcupkp\local\learner_evaluation::class_summary($course->id, 'U777');
        $this->assertEquals($course->id, $summary['courseid']);
        $this->assertSame(1, $summary['learner_count']);
        $this->assertArrayHasKey('diagnostic_counts', $summary);
    }

    /**
     * Create framework.
     *
     * @param int $courseid
     * @param string $externalid
     * @return int
     */
    private function create_framework(int $courseid, string $externalid = 'LE-FW'): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => $externalid,
            'name' => 'Learner Evaluation Framework',
            'courseid' => $courseid,
            'coursecode' => 'LE',
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create KP.
     *
     * @param int $frameworkid
     * @param string $externalid
     * @return int
     */
    private function create_kp(int $frameworkid, string $externalid): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => 'Knowledge point ' . $externalid,
            'domain' => 'READ',
            'status' => 'active',
            'version' => '1.0',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create learning object.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $unitcode
     * @param string $title
     * @return int
     */
    private function create_object(int $frameworkid, int $courseid, string $unitcode, string $title): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => clean_param($unitcode . '-' . $title, PARAM_ALPHANUMEXT),
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'lesson' => '1',
            'objecttype' => 'page',
            'title' => $title,
            'purpose' => 'practice',
            'evidencestrength' => 'recognition',
            'role' => 'practice',
            'metadatajson' => '{}',
        ]);
    }

    /**
     * Map object to target.
     *
     * @param int $objectid
     * @param string $targettype
     * @param int $targetid
     */
    private function map_object(int $objectid, string $targettype, int $targetid): void {
        global $DB;

        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'role' => 'practice',
            'evidencestrength' => 'recognition',
        ]);
    }
}

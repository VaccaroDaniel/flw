<?php
// PHPUnit tests for local_flwcupkp production hardening.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Production hardening tests.
 *
 * @covers \local_flwcupkp\local\curriculum_manager
 * @covers \local_flwcupkp\local\evidence_guard
 * @covers \local_flwcupkp\local\mastery_engine
 */
class production_hardening_test extends \advanced_testcase {
    public function test_cross_framework_mapping_is_rejected(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $frameworkone = $this->create_framework('FW-ONE');
        $frameworktwo = $this->create_framework('FW-TWO');
        $upid = $DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkone,
            'externalid' => 'UP-ONE',
            'title' => 'Use point one',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $kpid = $this->create_kp($frameworktwo, 'KP-TWO');

        $this->expectException(\invalid_parameter_exception::class);
        \local_flwcupkp\local\curriculum_manager::save_mapping('up_kp', [
            'upid' => $upid,
            'kpid' => $kpid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
    }

    public function test_course_evidence_requires_enrolled_learner(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $frameworkid = $this->create_framework('FW-EVIDENCE');
        $kpid = $this->create_kp($frameworkid, 'KP-EVIDENCE');

        $this->expectException(\invalid_parameter_exception::class);
        \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $learner->id,
            'courseid' => $course->id,
            'unitcode' => 'U000',
            'evidencetype' => 'manual_teacher_evidence',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 1,
            'normalizedscore' => 1,
            'confidence' => 0.75,
            'evidencestrength' => 'recognition',
            'provenance' => 'phpunit',
        ]);
    }

    public function test_evidence_scores_are_clamped_before_storage(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $frameworkid = $this->create_framework('FW-CLAMP');
        $kpid = $this->create_kp($frameworkid, 'KP-CLAMP');

        $result = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $learner->id,
            'courseid' => $course->id,
            'unitcode' => 'U000',
            'evidencetype' => 'manual_teacher_evidence',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 2,
            'normalizedscore' => 1.5,
            'confidence' => -0.25,
            'evidencestrength' => 'recognition',
            'provenance' => 'phpunit',
        ]);

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $result['evidenceid']], '*', MUST_EXIST);
        $this->assertEquals(1.0, (float)$evidence->normalizedscore);
        $this->assertEquals(0.0, (float)$evidence->confidence);
    }

    public function test_sync_readiness_requires_all_moodle_links(): void {
        global $DB;

        $this->resetAfterTest(true);

        $frameworkid = $this->create_framework('FW-SYNC');
        $compid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'COMP-SYNC',
            'title' => 'Sync competency',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $readiness = \local_flwcupkp\local\curriculum_manager::sync_readiness();
        $this->assertFalse($readiness['readyforwrites']);

        $DB->set_field('flwcupkp_framework', 'moodleframeworkid', 123, ['id' => $frameworkid]);
        $DB->set_field('flwcupkp_comp', 'moodlecompetencyid', 456, ['id' => $compid]);

        $readiness = \local_flwcupkp\local\curriculum_manager::sync_readiness();
        $this->assertTrue($readiness['readyforwrites']);
    }

    /**
     * Create a test framework row.
     *
     * @param string $externalid
     * @return int
     */
    private function create_framework(string $externalid): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => $externalid,
            'name' => 'Framework ' . $externalid,
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create a test KP row.
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
            'domain' => 'LEX',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}

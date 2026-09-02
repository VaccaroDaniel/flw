<?php
// Regression tests for C-UP-KP visual data composition.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Visual renderer tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\visuals::class)]
class visuals_test extends \advanced_testcase {
    public function test_hierarchy_map_accepts_program1_unit_lesson_identity(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $now = time();
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'VIS-FW-UNIT',
            'name' => 'Visual hierarchy framework',
            'courseid' => $course->id,
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $competencyid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'VIS-C-UNIT',
            'title' => 'Unit competency',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'VIS-UP-UNIT',
            'title' => 'Unit use point',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'VIS-KP-UNIT',
            'title' => 'Unit knowledge point',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'VIS-OBJECT-UNIT',
            'courseid' => $course->id,
            'unitcode' => 'U001',
            'lesson' => 'unit',
            'objecttype' => 'scorm',
            'title' => 'Unit SCORM activity',
            'purpose' => 'assessment',
            'role' => 'assessment',
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $competencyid,
            'upid' => $upid,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid,
            'kpid' => $kpid,
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'assessment',
        ]);

        $html = \local_flwcupkp\local\visuals::hierarchy_map(
            (int)$course->id,
            'U001',
            (int)$learner->id
        );

        $this->assertStringContainsString('VIS-C-UNIT', $html);
        $this->assertStringContainsString('VIS-UP-UNIT', $html);
        $this->assertStringContainsString('VIS-KP-UNIT', $html);
        $this->assertStringContainsString('Unit SCORM activity', $html);
    }
}

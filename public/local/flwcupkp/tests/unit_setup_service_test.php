<?php
// PHPUnit tests for local_flwcupkp unit setup.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit setup service tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\unit_setup_service::class)]
class unit_setup_service_test extends \advanced_testcase {
    public function test_link_course_updates_object_and_activation_status(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Lesson 1 - Setup Activity',
            'content' => 'Mapped C-UP-KP page.',
        ]);

        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'SETUP-FW',
            'name' => 'Setup Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'SETUP-KP',
            'title' => 'Setup Knowledge Point',
            'domain' => 'READ',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $objectid = $DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'SETUP-OBJECT',
            'unitcode' => 'U777',
            'lesson' => '1',
            'objecttype' => 'page',
            'title' => 'Setup Activity',
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'practice',
            'evidencestrength' => 'recognition',
        ]);

        $before = \local_flwcupkp\local\unit_setup_service::status('U777', $course->id);
        $this->assertSame(0, $before['counts']['linked']);
        $this->assertSame(1, $before['counts']['ready_to_link']);
        $this->assertFalse($before['activation']['ready']);

        $result = \local_flwcupkp\local\unit_setup_service::link_course('U777', $course->id);
        $this->assertSame('linked', $result['linked'][0]['status']);

        $after = \local_flwcupkp\local\unit_setup_service::status('U777', $course->id);
        $this->assertSame(1, $after['counts']['linked']);
        $this->assertSame(0, $after['counts']['missing_activity']);
        $this->assertTrue($after['activation']['ready']);
        $this->assertEquals($course->id, $DB->get_field('flwcupkp_object', 'courseid', ['id' => $objectid]));
    }

    public function test_infers_unit_code_from_package(): void {
        $package = [
            'learning_objects' => [[
                'externalid' => 'OBJ',
                'title' => 'Object',
                'unit_code' => 'U778',
            ]],
        ];

        $this->assertSame('U778', \local_flwcupkp\local\unit_setup_service::infer_unit_code_from_package($package));
    }
}

<?php
// PHPUnit tests for local_flwcupkp curriculum management.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Curriculum manager tests.
 *
 * @covers \local_flwcupkp\local\curriculum_manager
 */
class curriculum_manager_test extends \advanced_testcase {
    public function test_save_entity_creates_audited_competency(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'TEST-FW',
            'name' => 'Test Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $id = \local_flwcupkp\local\curriculum_manager::save_entity('competency', [
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-COMP-001',
            'title' => 'Test competency',
            'cando' => 'Can do the test task.',
            'status' => 'draft',
            'version' => '1.0',
        ]);

        $this->assertTrue($DB->record_exists('flwcupkp_comp', ['id' => $id]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'curriculum_entity_saved',
            'targettype' => 'competency',
            'targetid' => $id,
        ]));
    }

    public function test_export_package_includes_mapping_graph(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $now = time();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'TEST-FW',
            'name' => 'Test Framework',
            'coursecode' => 'TEST',
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-COMP',
            'title' => 'Test competency',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = $DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-UP',
            'title' => 'Test use point',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-KP',
            'title' => 'Test knowledge point',
            'domain' => 'LEX',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = $DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-OBJ',
            'unitcode' => 'U000',
            'lesson' => '1',
            'objecttype' => 'page',
            'title' => 'Test object',
        ]);
        $DB->insert_record('flwcupkp_comp_up', (object)['competencyid' => $compid, 'upid' => $upid, 'role' => 'required', 'weight' => 1, 'sortorder' => 1]);
        $DB->insert_record('flwcupkp_up_kp', (object)['upid' => $upid, 'kpid' => $kpid, 'role' => 'required', 'weight' => 1, 'sortorder' => 1]);
        $DB->insert_record('flwcupkp_object_map', (object)['objectid' => $objectid, 'targettype' => 'kp', 'targetid' => $kpid, 'role' => 'practice', 'evidencestrength' => 'recognition']);

        $package = \local_flwcupkp\local\curriculum_manager::export_package($frameworkid);

        $this->assertSame('TEST-FW', $package['frameworks'][0]['externalid']);
        $this->assertSame('TEST-COMP', $package['competency_up_mappings'][0]['competency_externalid']);
        $this->assertSame('TEST-UP', $package['up_kp_mappings'][0]['up_externalid']);
        $this->assertSame('TEST-OBJ', $package['activity_mappings'][0]['object_externalid']);
    }
}

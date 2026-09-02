<?php
// PHPUnit tests for local_flwcupkp curriculum management.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Curriculum manager tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\curriculum_manager::class)]
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

    public function test_bulk_update_status_updates_framework_scope_and_audits(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $now = time();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'BULK-FW',
            'name' => 'Bulk Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $otherframeworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'BULK-OTHER-FW',
            'name' => 'Other Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'BULK-COMP',
            'title' => 'Bulk competency',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $othercompid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $otherframeworkid,
            'externalid' => 'BULK-OTHER-COMP',
            'title' => 'Other competency',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = \local_flwcupkp\local\curriculum_manager::bulk_update_status('competency', $frameworkid, 'validated');

        $this->assertSame(1, $result['count']);
        $this->assertSame('approved', $DB->get_field('flwcupkp_comp', 'status', ['id' => $compid], MUST_EXIST));
        $this->assertSame('draft', $DB->get_field('flwcupkp_comp', 'status', ['id' => $othercompid], MUST_EXIST));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'curriculum_bulk_status_updated',
            'targettype' => 'competency',
            'targetid' => $frameworkid,
        ]));
    }

    public function test_clone_framework_version_copies_curriculum_graph_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $now = time();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'CLONE-FW',
            'name' => 'Clone Framework',
            'coursecode' => 'CLONE',
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'active',
            'moodleframeworkid' => 123,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CLONE-COMP',
            'title' => 'Clone competency',
            'status' => 'active',
            'version' => '1.0',
            'moodlecompetencyid' => 456,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = $DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CLONE-UP',
            'title' => 'Clone use point',
            'status' => 'active',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CLONE-KP',
            'title' => 'Clone knowledge point',
            'domain' => 'LEX',
            'status' => 'active',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $prereqid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CLONE-PREREQ',
            'title' => 'Clone prerequisite',
            'domain' => 'GRAM',
            'status' => 'active',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = $DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CLONE-OBJ',
            'courseid' => 321,
            'unitcode' => 'U999',
            'lesson' => 'Project',
            'objecttype' => 'project',
            'title' => 'Clone object',
            'cmid' => 789,
            'role' => 'assessment',
        ]);
        $DB->insert_record('flwcupkp_comp_up', (object)['competencyid' => $compid, 'upid' => $upid, 'role' => 'required', 'weight' => 1, 'sortorder' => 1]);
        $DB->insert_record('flwcupkp_up_kp', (object)['upid' => $upid, 'kpid' => $kpid, 'role' => 'required', 'weight' => 1, 'sortorder' => 1]);
        $DB->insert_record('flwcupkp_kp_prereq', (object)['kpid' => $kpid, 'prereqkpid' => $prereqid, 'relationshiptype' => 'prerequisite', 'strength' => 1, 'requirement' => 'mandatory']);
        $DB->insert_record('flwcupkp_object_map', (object)['objectid' => $objectid, 'targettype' => 'kp', 'targetid' => $kpid, 'role' => 'assessment', 'evidencestrength' => 'guided_performance']);

        $result = \local_flwcupkp\local\curriculum_manager::clone_framework_version($frameworkid, '1.1', 'v11');

        $this->assertSame('CLONE-FW-v11', $result['externalid']);
        $newframework = $DB->get_record('flwcupkp_framework', ['id' => $result['frameworkid']], '*', MUST_EXIST);
        $this->assertSame('1.1', $newframework->version);
        $this->assertSame('draft', $newframework->status);
        $this->assertNull($newframework->moodleframeworkid);

        $newcomp = $DB->get_record('flwcupkp_comp', ['externalid' => 'CLONE-COMP-v11'], '*', MUST_EXIST);
        $newup = $DB->get_record('flwcupkp_up', ['externalid' => 'CLONE-UP-v11'], '*', MUST_EXIST);
        $newkp = $DB->get_record('flwcupkp_kp', ['externalid' => 'CLONE-KP-v11'], '*', MUST_EXIST);
        $newprereq = $DB->get_record('flwcupkp_kp', ['externalid' => 'CLONE-PREREQ-v11'], '*', MUST_EXIST);
        $newobject = $DB->get_record('flwcupkp_object', ['externalid' => 'CLONE-OBJ-v11'], '*', MUST_EXIST);

        $this->assertSame((int)$newframework->id, (int)$newcomp->frameworkid);
        $this->assertNull($newcomp->moodlecompetencyid);
        $this->assertSame('draft', $newkp->status);
        $this->assertNull($newobject->courseid);
        $this->assertNull($newobject->cmid);
        $this->assertTrue($DB->record_exists('flwcupkp_comp_up', [
            'competencyid' => $newcomp->id,
            'upid' => $newup->id,
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_up_kp', [
            'upid' => $newup->id,
            'kpid' => $newkp->id,
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_kp_prereq', [
            'kpid' => $newkp->id,
            'prereqkpid' => $newprereq->id,
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_object_map', [
            'objectid' => $newobject->id,
            'targettype' => 'kp',
            'targetid' => $newkp->id,
        ]));
        $this->assertFalse($DB->record_exists('flwcupkp_evidence', ['objectid' => $newobject->id]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'curriculum_framework_version_cloned',
            'targettype' => 'framework',
            'targetid' => $newframework->id,
        ]));
    }
}

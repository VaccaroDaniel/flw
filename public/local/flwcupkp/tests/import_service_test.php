<?php
// PHPUnit tests for local_flwcupkp imports.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Import service tests.
 *
 * @covers \local_flwcupkp\local\import_service
 */
class import_service_test extends \advanced_testcase {
    public function test_json_import_supports_lesson_and_project_mapping_aliases(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $package = [
            'cupkp_schema_version' => '1.0',
            'frameworks' => [[
                'externalid' => 'ALIAS-FW',
                'name' => 'Alias Framework',
                'version' => '1.0',
                'status' => 'draft',
            ]],
            'competencies' => [[
                'externalid' => 'ALIAS-COMP',
                'title' => 'Alias competency',
                'status' => 'draft',
                'version' => '1.0',
            ]],
            'use_points' => [[
                'externalid' => 'ALIAS-UP',
                'title' => 'Alias Use Point',
                'status' => 'draft',
                'version' => '1.0',
            ]],
            'knowledge_points' => [[
                'externalid' => 'ALIAS-KP',
                'title' => 'Alias Knowledge Point',
                'domain' => 'LEX',
                'status' => 'draft',
                'version' => '1.0',
            ]],
            'learning_objects' => [[
                'externalid' => 'ALIAS-PROJECT',
                'title' => 'Alias project',
                'unit_code' => 'U999',
                'lesson' => 'Project',
                'object_type' => 'project',
            ]],
            'lesson_mappings' => [[
                'object_externalid' => 'ALIAS-LESSON',
                'title' => 'Alias lesson',
                'unit_code' => 'U999',
                'lesson' => '1',
                'object_type' => 'lesson',
                'kp_externalids' => ['ALIAS-KP'],
                'up_externalids' => ['ALIAS-UP'],
                'evidence_strength' => 'controlled_production',
            ]],
            'project_competency_mappings' => [[
                'object_externalid' => 'ALIAS-PROJECT',
                'competency_externalid' => 'ALIAS-COMP',
                'evidence_strength' => 'independent_performance',
            ]],
            'project_evidence' => [[
                'object_externalid' => 'ALIAS-PROJECT',
                'competency_externalid' => 'ALIAS-COMP',
            ]],
        ];

        $result = \local_flwcupkp\local\import_service::import_json(json_encode($package), 'aliases.json');

        $this->assertSame('imported', $result['status']);
        $lesson = $DB->get_record('flwcupkp_object', ['externalid' => 'ALIAS-LESSON'], '*', MUST_EXIST);
        $project = $DB->get_record('flwcupkp_object', ['externalid' => 'ALIAS-PROJECT'], '*', MUST_EXIST);
        $kp = $DB->get_record('flwcupkp_kp', ['externalid' => 'ALIAS-KP'], '*', MUST_EXIST);
        $up = $DB->get_record('flwcupkp_up', ['externalid' => 'ALIAS-UP'], '*', MUST_EXIST);
        $comp = $DB->get_record('flwcupkp_comp', ['externalid' => 'ALIAS-COMP'], '*', MUST_EXIST);

        $this->assertSame('1', $lesson->lesson);
        $this->assertTrue($DB->record_exists('flwcupkp_object_map', [
            'objectid' => $lesson->id,
            'targettype' => 'kp',
            'targetid' => $kp->id,
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_object_map', [
            'objectid' => $lesson->id,
            'targettype' => 'up',
            'targetid' => $up->id,
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_object_map', [
            'objectid' => $project->id,
            'targettype' => 'competency',
            'targetid' => $comp->id,
        ]));
    }

    public function test_quiz_kp_csv_import_is_validated_idempotent_and_traceable(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $now = time();

        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'CSV-FW',
            'name' => 'CSV Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = $DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CSV-QUIZ-OBJECT',
            'unitcode' => 'U999',
            'lesson' => '3',
            'objecttype' => 'quiz',
            'title' => 'CSV quiz object',
            'metadatajson' => '{}',
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CSV-KP-001',
            'title' => 'CSV Knowledge Point',
            'domain' => 'READ',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $csv = "item_id,object_externalid,kp_externalid,evidence_strength,notes\n" .
            "Q001,CSV-QUIZ-OBJECT,CSV-KP-001,recognition,Skimming item\n";

        $validation = \local_flwcupkp\local\import_service::validate_csv($csv, 'quiz_kp_mappings');
        $this->assertTrue($validation['valid']);

        $result = \local_flwcupkp\local\import_service::import_csv($csv, 'quiz_kp_mappings', 'phpunit.csv');
        $this->assertSame('imported', $result['status']);
        $this->assertSame(1, $result['entitycount']);
        $this->assertTrue($DB->record_exists('flwcupkp_object_map', [
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
        ]));

        $metadata = json_decode($DB->get_field('flwcupkp_object', 'metadatajson', ['id' => $objectid]), true);
        $this->assertSame('Q001', $metadata['quiz_kp_mappings'][0]['item_id']);
        $this->assertSame('CSV-KP-001', $metadata['quiz_kp_mappings'][0]['kp_externalid']);

        $again = \local_flwcupkp\local\import_service::import_csv($csv, 'quiz_kp_mappings', 'phpunit.csv');
        $this->assertSame('already_imported', $again['status']);
        $this->assertSame($result['importid'], $again['importid']);
    }
}

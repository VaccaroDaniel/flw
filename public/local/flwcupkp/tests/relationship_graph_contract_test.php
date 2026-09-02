<?php
// PHPUnit tests for Program 3 Gate C2 relationship graph semantics.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Relationship graph contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\relationship_graph_contract::class)]
class relationship_graph_contract_test extends \advanced_testcase {
    public function test_contract_freezes_all_relation_attributes_and_boundaries(): void {
        $contract = \local_flwcupkp\local\relationship_graph_contract::contract();

        $this->assertSame('P3_C2', $contract['gate']);
        $this->assertSame('FLW_CUPKP_RELATIONSHIP_GRAPH_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\canonical_domain_model::CONTRACT_VERSION, $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\ontology_boundary::CONTRACT_VERSION, $contract['depends_on']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['normal_source_history_input']
        );

        foreach ([
            'SUPPORTS',
            'REQUIRES',
            'EVIDENCE_FOR',
            'TRAINS',
            'EXTENDS',
            'ALTERNATIVE_TO',
            'REVIEW_OF',
            'REPLACED_BY',
        ] as $relation) {
            $this->assertArrayHasKey($relation, $contract['relations']);
            foreach ([
                'allowed_source_types',
                'allowed_target_types',
                'direction',
                'cardinality',
                'symmetry',
                'transitivity',
                'cycles',
                'inference',
                'version_behavior',
                'deprecation_behavior',
            ] as $attribute) {
                $this->assertArrayHasKey($attribute, $contract['relations'][$relation]);
            }
        }
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
    }

    public function test_existing_mapping_shapes_resolve_to_frozen_semantics(): void {
        $this->assertSame('REQUIRES',
            \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping('comp_up', ['role' => 'required']));
        $this->assertSame('SUPPORTS',
            \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping('up_kp', ['role' => 'supporting']));
        $this->assertSame('SUPPORTS',
            \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping('up_kp', ['role' => 'extension']));
        $this->assertSame('REQUIRES',
            \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping('kp_prereq', [
                'relationship_type' => 'language_resource',
                'requirement' => 'mandatory',
            ]));
        $this->assertSame('EVIDENCE_FOR',
            \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping('object_map', ['role' => 'assessment']));
        $this->assertSame('TRAINS',
            \local_flwcupkp\local\relationship_graph_contract::semantic_for_mapping('object_map', ['role' => 'practice']));
    }

    public function test_package_graph_rejects_hard_prerequisite_cycle(): void {
        $package = self::valid_base_package();
        $package['knowledge_points'][] = [
            'externalid' => 'KP-FR-A2-GRAM-032',
            'title' => 'Conditional form choices',
            'domain' => 'GRAM',
            'cefr' => 'A2',
            'status' => 'draft',
        ];
        $package['kp_prerequisites'] = [
            [
                'kp_externalid' => 'KP-FR-A2-FUNC-031',
                'prereq_kp_externalid' => 'KP-FR-A2-GRAM-032',
                'relationship_type' => 'language_resource',
                'requirement' => 'mandatory',
            ],
            [
                'kp_externalid' => 'KP-FR-A2-GRAM-032',
                'prereq_kp_externalid' => 'KP-FR-A2-FUNC-031',
                'relationship_type' => 'prerequisite',
                'requirement' => 'mandatory',
            ],
        ];

        $result = \local_flwcupkp\local\relationship_graph_contract::validate_package_graph($package);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Hard prerequisite cycle detected', implode(' ', $result['errors']));
    }

    public function test_package_graph_accepts_current_support_prerequisite_vocabulary(): void {
        $package = self::valid_base_package();
        $package['kp_prerequisites'] = [[
            'kp_externalid' => 'KP-FR-A2-FUNC-031',
            'prereq_kp_externalid' => 'KP-FR-A2-LEX-033',
            'relationship_type' => 'meaning_support',
            'requirement' => 'recommended',
        ]];
        $package['knowledge_points'][] = [
            'externalid' => 'KP-FR-A2-LEX-033',
            'title' => 'Decision vocabulary',
            'domain' => 'LEX',
            'cefr' => 'A2',
            'status' => 'draft',
        ];

        $result = \local_flwcupkp\local\relationship_graph_contract::validate_package_graph($package);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_validator_exposes_c2_graph_result(): void {
        $package = self::valid_base_package();
        $package['knowledge_points'][] = [
            'externalid' => 'KP-FR-A2-GRAM-032',
            'title' => 'Conditional form choices',
            'domain' => 'GRAM',
            'cefr' => 'A2',
            'status' => 'draft',
        ];
        $package['kp_prerequisites'] = [
            [
                'kp_externalid' => 'KP-FR-A2-FUNC-031',
                'prereq_kp_externalid' => 'KP-FR-A2-GRAM-032',
                'relationship_type' => 'prerequisite',
                'requirement' => 'mandatory',
            ],
            [
                'kp_externalid' => 'KP-FR-A2-GRAM-032',
                'prereq_kp_externalid' => 'KP-FR-A2-FUNC-031',
                'relationship_type' => 'prerequisite',
                'requirement' => 'mandatory',
            ],
        ];

        $validation = \local_flwcupkp\local\validator::validate_package($package);

        $this->assertFalse($validation['valid']);
        $this->assertArrayHasKey('graph', $validation);
        $this->assertFalse($validation['graph']['valid']);
        $this->assertSame('FLW_CUPKP_RELATIONSHIP_GRAPH_V1', $validation['graph']['contract']);
    }

    public function test_manual_mapping_save_rejects_new_hard_prerequisite_cycle(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = self::create_framework('C2-FW-CYCLE');
        $kpa = self::create_kp($frameworkid, 'KP-C2-A');
        $kpb = self::create_kp($frameworkid, 'KP-C2-B');

        \local_flwcupkp\local\curriculum_manager::save_mapping('kp_prereq', [
            'kpid' => $kpa,
            'prereqkpid' => $kpb,
            'relationshiptype' => 'prerequisite',
            'requirement' => 'mandatory',
            'strength' => 1,
        ]);

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Hard prerequisite cycle detected');
        \local_flwcupkp\local\curriculum_manager::save_mapping('kp_prereq', [
            'kpid' => $kpb,
            'prereqkpid' => $kpa,
            'relationshiptype' => 'prerequisite',
            'requirement' => 'mandatory',
            'strength' => 1,
        ]);
    }

    public function test_adjacency_dependencies_and_where_used_are_centralized(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = self::create_framework('C2-FW-GRAPH');
        $compid = self::create_comp($frameworkid, 'C-C2-GRAPH');
        $upid = self::create_up($frameworkid, 'UP-C2-GRAPH');
        $kpid = self::create_kp($frameworkid, 'KP-C2-GRAPH');
        $objectid = self::create_object($frameworkid, 'OBJ-C2-GRAPH');

        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $compid,
            'upid' => $upid,
            'role' => 'required',
            'weight' => 1,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid,
            'kpid' => $kpid,
            'role' => 'required',
            'weight' => 1,
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'assessment',
            'evidencestrength' => 'recognition',
        ]);

        $adjacency = \local_flwcupkp\local\relationship_graph_contract::adjacency($frameworkid);
        $this->assertCount(3, $adjacency);

        $dependencies = \local_flwcupkp\local\relationship_graph_contract::dependencies_for_target(
            'competency', $compid, $frameworkid);
        $this->assertContains('up:' . $upid, $dependencies['nodes']);
        $this->assertContains('kp:' . $kpid, $dependencies['nodes']);
        $this->assertCount(2, $dependencies['edges']);

        $whereused = \local_flwcupkp\local\relationship_graph_contract::where_used('kp', $kpid, $frameworkid);
        $this->assertContains('up:' . $upid, $whereused['nodes']);
        $this->assertContains('competency:' . $compid, $whereused['nodes']);
        $this->assertContains('object:' . $objectid, $whereused['nodes']);
    }

    public function test_graph_status_is_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforeaudit = $DB->count_records('flwcupkp_audit');

        $status = \local_flwcupkp\local\relationship_graph_contract::graph_status((int)$course->id, 0, 10);

        $this->assertSame('CupkpRelationshipGraphStatus', $status['type']);
        $this->assertSame('P3_C2', $status['gate']);
        $this->assertSame('FLW_CUPKP_RELATIONSHIP_GRAPH_V1', $status['contract']['version']);
        $this->assertSame('frozen', $status['status']);
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
    }

    private static function valid_base_package(): array {
        return [
            'cupkp_schema_version' => '1.0',
            'frameworks' => [[
                'externalid' => 'FW-C2',
                'name' => 'C2 Framework',
                'cefr_range' => 'A2-B1',
                'status' => 'draft',
            ]],
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-004',
                'title' => 'Discuss a local problem and agree on a next step',
                'can_do' => 'Can explain a problem, compare options, and agree on action.',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C2',
                'status' => 'draft',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Negotiate a group decision politely',
                'action_statement' => 'Use known suggestion language to move the group to a decision.',
                'observable_action' => 'Compares two options and summarizes the chosen action.',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C2',
                'status' => 'draft',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Suggestion expressions for alternatives',
                'description' => 'Expressions for suggesting alternatives politely.',
                'domain' => 'FUNC',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C2',
                'status' => 'draft',
            ]],
            'competency_up_mappings' => [[
                'competency_externalid' => 'C-FR-A2-SI-004',
                'up_externalid' => 'UP-FR-A2-SI-031-04',
                'role' => 'required',
                'weight' => 1,
            ]],
            'up_kp_mappings' => [[
                'up_externalid' => 'UP-FR-A2-SI-031-04',
                'kp_externalid' => 'KP-FR-A2-FUNC-031',
                'role' => 'required',
                'weight' => 1,
            ]],
            'project_evidence' => [[
                'object_externalid' => 'PROJECT-C2',
                'competency_externalid' => 'C-FR-A2-SI-004',
            ]],
        ];
    }

    private static function create_framework(string $externalid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => $externalid,
            'name' => $externalid,
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_comp(int $frameworkid, string $externalid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_up(int $frameworkid, string $externalid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_kp(int $frameworkid, string $externalid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'domain' => 'LEX',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_object(int $frameworkid, string $externalid): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'objecttype' => 'quiz',
            'title' => $externalid,
            'purpose' => 'assessment',
            'role' => 'assessment',
            'metadatajson' => '{}',
        ]);
    }
}

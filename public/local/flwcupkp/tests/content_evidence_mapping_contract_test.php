<?php
// PHPUnit tests for Program 3 Gate C3 content/evidence mapping contracts.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Content/evidence mapping contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\content_evidence_mapping_contract::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\evidence_guard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\validator::class)]
class content_evidence_mapping_contract_test extends \advanced_testcase {
    public function test_contract_freezes_identity_source_roles_and_stop_boundary(): void {
        $contract = \local_flwcupkp\local\content_evidence_mapping_contract::contract();

        $this->assertSame('P3_C3', $contract['gate']);
        $this->assertSame('FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\canonical_domain_model::CONTRACT_VERSION, $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\ontology_boundary::CONTRACT_VERSION, $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\relationship_graph_contract::CONTRACT_VERSION, $contract['depends_on']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['normal_source_history_input']
        );
        $this->assertFalse($contract['pedagogical_roles']['TEACHES']['can_create_evidence']);
        $this->assertFalse($contract['completion_rule']['completion_is_mastery']);
        $this->assertContains('teacher_observation', $contract['source_types']);
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
    }

    public function test_roles_and_source_types_normalize_to_c3_contract(): void {
        $contract = \local_flwcupkp\local\content_evidence_mapping_contract::class;

        $this->assertSame('TEACHES', $contract::canonical_pedagogical_role('lesson'));
        $this->assertSame('PRACTICES', $contract::canonical_pedagogical_role('review_of'));
        $this->assertSame('ASSESSES', $contract::canonical_pedagogical_role('checkpoint'));
        $this->assertSame('EVIDENCE_FOR', $contract::canonical_pedagogical_role('teacher_observation'));

        $this->assertSame('completion', $contract::source_type_for_evidence_type('activity_completion'));
        $this->assertSame('grade_linked_assessment',
            $contract::source_type_for_evidence_type('assignment_grade', 'moodle_assignment_grade'));
        $this->assertSame('program2_attempt',
            $contract::source_type_for_evidence_type('quiz_attempt_submitted', 'moodle_quiz'));
        $this->assertSame('teacher_observation',
            $contract::source_type_for_evidence_type('manual_teacher_evidence', 'teacher'));
    }

    public function test_validator_exposes_c3_content_evidence_result(): void {
        $package = self::valid_base_package();
        $package['activity_mappings'] = [[
            'object_title' => 'Warm-up quiz',
            'target_type' => 'kp',
            'target_externalid' => 'KP-FR-A2-FUNC-031',
            'role' => 'assessment',
            'evidence_strength' => 'recognition',
        ]];

        $validation = \local_flwcupkp\local\validator::validate_package($package);

        $this->assertFalse($validation['valid']);
        $this->assertArrayHasKey('content_evidence', $validation);
        $this->assertFalse($validation['content_evidence']['valid']);
        $this->assertSame('FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1',
            $validation['content_evidence']['contract']);
        $this->assertStringContainsString('not object_title', implode(' ', $validation['content_evidence']['errors']));
    }

    public function test_completion_counts_only_when_mapping_is_pedagogically_valid(): void {
        $lesson = (object)[
            'purpose' => 'lesson',
            'objecttype' => 'lesson',
            'metadatajson' => '{}',
        ];
        $assessment = (object)[
            'purpose' => 'assessment',
            'objecttype' => 'quiz',
            'metadatajson' => '{}',
        ];
        $overridepractice = (object)[
            'purpose' => 'lesson',
            'objecttype' => 'lesson',
            'metadatajson' => json_encode(['completion_evidence_map_overrides' => ['kp:12' => true]]),
        ];
        $practice = (object)['role' => 'practice'];
        $practicewithoverride = (object)['role' => 'practice', 'targettype' => 'kp', 'targetid' => 12];
        $assesses = (object)['role' => 'assessment'];

        $this->assertFalse(\local_flwcupkp\local\content_evidence_mapping_contract::source_can_count(
            'completion',
            $lesson,
            $practice
        ));
        $this->assertTrue(\local_flwcupkp\local\content_evidence_mapping_contract::source_can_count(
            'completion',
            $overridepractice,
            $practicewithoverride
        ));
        $this->assertTrue(\local_flwcupkp\local\content_evidence_mapping_contract::source_can_count(
            'completion',
            $assessment,
            $assesses
        ));
        $this->assertTrue(\local_flwcupkp\local\content_evidence_mapping_contract::source_can_count(
            'program2_attempt',
            $lesson,
            $practice
        ));
    }

    public function test_evidence_guard_rejects_completion_for_practice_lesson_mapping(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$courseid, $userid, $objectid, $kpid] = $this->create_mapped_object('lesson', 'practice', 'lesson');

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('completion is not pedagogically valid');
        \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => 'U-C3',
            'objectid' => $objectid,
            'sourceattempt' => 'completion:practice',
            'evidencetype' => 'activity_completion',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 1,
            'normalizedscore' => 1,
            'rubricjson' => '{}',
            'assessortype' => 'moodle_completion',
            'confidence' => 0.55,
            'evidencestrength' => 'recognition',
            'provenance' => 'phpunit',
        ]);

        $this->assertSame(0, $DB->count_records('flwcupkp_evidence'));
    }

    public function test_evidence_guard_allows_valid_completion_and_augments_rubric(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$courseid, $userid, $objectid, $kpid] = $this->create_mapped_object('assessment', 'assessment', 'quiz');

        $result = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => 'U-C3',
            'objectid' => $objectid,
            'sourceattempt' => 'completion:assessment',
            'evidencetype' => 'activity_completion',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 1,
            'normalizedscore' => 1,
            'rubricjson' => json_encode(['cmid' => 42]),
            'assessortype' => 'moodle_completion',
            'confidence' => 0.55,
            'evidencestrength' => 'recognition',
            'provenance' => 'phpunit',
        ]);

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $result['evidenceid']], '*', MUST_EXIST);
        $rubric = json_decode((string)$evidence->rubricjson, true);

        $this->assertSame('FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1',
            $rubric['cupkp_c3_mapping']['contract']);
        $this->assertSame('completion', $rubric['cupkp_c3_mapping']['source_type']);
        $this->assertFalse($rubric['cupkp_c3_mapping']['completion_is_mastery']);
        $this->assertSame('ASSESSES', $rubric['cupkp_c3_mapping']['pedagogical_role']);
        $this->assertSame('OBJ-C3-assessment', $rubric['cupkp_c3_mapping']['object_externalid']);
        $this->assertSame('ACT-C3-assessment', $rubric['cupkp_c3_mapping']['content_identity']['activityid']);
    }

    public function test_import_metadata_preserves_program1_identity_contracts(): void {
        $metadata = \local_flwcupkp\local\content_evidence_mapping_contract::normalize_object_metadata_from_row([
            'externalid' => 'OBJ-C3-META',
            'unit_id' => 'UNIT-C3',
            'lesson_id' => 'LESSON-C3',
            'activity_id' => 'ACT-C3',
            'assessment_id' => 'ASM-C3',
            'question_id' => 'Q-C3',
            'completion_counts_as_evidence' => 'yes',
        ], ['existing' => 'kept']);

        $this->assertSame('kept', $metadata['existing']);
        $this->assertSame('FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1',
            $metadata['content_evidence_mapping_contract']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $metadata['source_history_contract']);
        $this->assertSame('UNIT-C3', $metadata['program1_identity']['unitid']);
        $this->assertSame('LESSON-C3', $metadata['program1_identity']['lessonid']);
        $this->assertSame('ACT-C3', $metadata['program1_identity']['activityid']);
        $this->assertSame('ASM-C3', $metadata['program1_identity']['assessmentid']);
        $this->assertSame('Q-C3', $metadata['program1_identity']['questionid']);
        $this->assertTrue($metadata['completion_counts_as_evidence']);
    }

    public function test_content_mapping_status_is_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforeaudit = $DB->count_records('flwcupkp_audit');

        $status = \local_flwcupkp\local\content_evidence_mapping_contract::content_mapping_status(
            (int)$course->id,
            'U-C3',
            10
        );

        $this->assertSame('CupkpContentEvidenceMappingStatus', $status['type']);
        $this->assertSame('P3_C3', $status['gate']);
        $this->assertSame('FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1', $status['contract']['version']);
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
    }

    private static function valid_base_package(): array {
        return [
            'cupkp_schema_version' => '1.0',
            'frameworks' => [[
                'externalid' => 'FW-C3',
                'name' => 'C3 Framework',
                'cefr_range' => 'A2-B1',
                'status' => 'draft',
            ]],
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-004',
                'title' => 'Discuss a local problem and agree on a next step',
                'can_do' => 'Can explain a problem, compare options, and agree on action.',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C3',
                'status' => 'draft',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Negotiate a group decision politely',
                'action_statement' => 'Use known suggestion language to move the group to a decision.',
                'observable_action' => 'Compares two options and summarizes the chosen action.',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C3',
                'status' => 'draft',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Suggestion expressions for alternatives',
                'description' => 'Expressions for suggesting alternatives politely.',
                'domain' => 'FUNC',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C3',
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
                'object_externalid' => 'PROJECT-C3',
                'competency_externalid' => 'C-FR-A2-SI-004',
            ]],
        ];
    }

    private function create_mapped_object(string $purpose, string $role, string $objecttype): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $now = time();
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-C3-' . $purpose,
            'name' => 'Framework C3 ' . $purpose,
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-C3-' . $purpose,
            'title' => 'Knowledge point ' . $purpose,
            'domain' => 'LEX',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'OBJ-C3-' . $purpose,
            'courseid' => (int)$course->id,
            'unitcode' => 'U-C3',
            'lesson' => '1',
            'objecttype' => $objecttype,
            'title' => 'Object ' . $purpose,
            'purpose' => $purpose,
            'role' => $role,
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'unitid' => 'UNIT-C3',
                    'lessonid' => 'LESSON-C3',
                    'activityid' => 'ACT-C3-' . $purpose,
                ],
                'content_evidence_mapping_contract' =>
                    \local_flwcupkp\local\content_evidence_mapping_contract::CONTRACT_VERSION,
                'source_history_contract' => \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            ]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => $role,
            'evidencestrength' => 'recognition',
        ]);

        return [(int)$course->id, (int)$learner->id, $objectid, $kpid];
    }
}

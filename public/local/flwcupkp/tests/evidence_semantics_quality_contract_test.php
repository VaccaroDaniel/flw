<?php
// PHPUnit tests for Program 3 Gate C3B evidence semantics and quality.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Evidence semantics and quality contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\evidence_semantics_quality_contract::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\evidence_guard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\mastery_engine::class)]
class evidence_semantics_quality_contract_test extends \advanced_testcase {
    public function test_contract_freezes_semantics_quality_and_history_boundary(): void {
        $contract = \local_flwcupkp\local\evidence_semantics_quality_contract::contract();

        $this->assertSame('P3_C3B', $contract['gate']);
        $this->assertSame('FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1', $contract['version']);
        $this->assertSame('cupkp-evidence-quality-v1', $contract['evidence_policy_version']);
        $this->assertContains(\local_flwcupkp\local\content_evidence_mapping_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['normal_source_history_input']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
            $contract['normal_source_rule']);
        $this->assertArrayHasKey('inconclusive', $contract['result_states']);
        $this->assertArrayHasKey('transfer', $contract['performance_modes']);
        $this->assertArrayHasKey('direct', $contract['direct_inferred']);
        $this->assertArrayHasKey('contextual_transfer', $contract['quality_dimensions']);
        $this->assertArrayHasKey('support_level', $contract['quality_dimensions']);
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
        $this->assertSame('not_created_by_c3b', $contract['quality_normalization']['single_quality_weight']);
    }

    public function test_semantic_normalization_is_deterministic(): void {
        $evidence = (object)[
            'userid' => 3,
            'courseid' => 7,
            'unitcode' => 'U-C3B',
            'objectid' => 5,
            'sourceattempt' => 'attempt:42',
            'evidencetype' => 'quiz_attempt_submitted',
            'targettype' => 'kp',
            'targetid' => 11,
            'rawscore' => 8.5,
            'normalizedscore' => 0.85,
            'rubricjson' => json_encode(['occurred_at' => 1000, 'recorded_at' => 1000]),
            'assessortype' => 'moodle_quiz',
            'confidence' => 0.9,
            'evidencestrength' => 'independent_performance',
            'provenance' => 'phpunit',
            'sourceref' => 'quiz:attempt:42',
            'timecreated' => 1000,
        ];

        $first = \local_flwcupkp\local\evidence_semantics_quality_contract::semantics_for_evidence($evidence);
        $second = \local_flwcupkp\local\evidence_semantics_quality_contract::semantics_for_evidence($evidence);

        $this->assertSame($first, $second);
        $this->assertSame('positive', $first['result_state']);
        $this->assertSame('independent_production', $first['performance_mode']);
        $this->assertSame('direct', $first['evidence_direction']);
        $this->assertSame('practice_evidence', $first['evidence_role']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $first['source_key']['history_contract']);
        $this->assertTrue($first['source_key']['legacy_direct_capture']);
        $this->assertSame('cupkp-evidence-quality-v1', $first['policy_version']);
        foreach (\local_flwcupkp\local\evidence_semantics_quality_contract::quality_dimensions() as $dimension) {
            $this->assertArrayHasKey($dimension, $first['quality']);
            $this->assertGreaterThanOrEqual(0, $first['quality'][$dimension]);
            $this->assertLessThanOrEqual(1, $first['quality'][$dimension]);
        }
    }

    public function test_result_states_separate_positive_partial_negative_and_inconclusive(): void {
        $contract = \local_flwcupkp\local\evidence_semantics_quality_contract::class;

        $this->assertSame('positive', $contract::infer_result_state((object)[
            'normalizedscore' => 0.9,
            'evidencetype' => 'quiz_attempt_submitted',
        ]));
        $this->assertSame('partial', $contract::infer_result_state((object)[
            'normalizedscore' => 0.5,
            'evidencetype' => 'quiz_attempt_submitted',
        ]));
        $this->assertSame('negative', $contract::infer_result_state((object)[
            'normalizedscore' => 0.2,
            'evidencetype' => 'quiz_attempt_submitted',
        ]));
        $this->assertSame('inconclusive', $contract::infer_result_state((object)[
            'evidencetype' => 'quiz_attempt_submitted',
        ]));
        $this->assertSame('inconclusive', $contract::infer_result_state((object)[
            'normalizedscore' => 0,
            'evidencetype' => 'quiz_attempt_submitted',
            'rubricjson' => json_encode(['technical_failure' => true]),
        ]));
    }

    public function test_retry_and_explicit_quality_semantics_are_preserved(): void {
        $normal = (object)[
            'evidencetype' => 'quiz_attempt_submitted',
            'normalizedscore' => 0.88,
            'confidence' => 0.8,
            'evidencestrength' => 'guided_performance',
            'rubricjson' => json_encode(['occurred_at' => 1000, 'recorded_at' => 1000]),
        ];
        $retry = clone($normal);
        $retry->rubricjson = json_encode(['hint_shown' => true, 'attempt_number' => 2]);

        $normalquality = \local_flwcupkp\local\evidence_semantics_quality_contract::semantics_for_evidence($normal);
        $retryquality = \local_flwcupkp\local\evidence_semantics_quality_contract::semantics_for_evidence($retry);

        $this->assertLessThan($normalquality['quality']['independence'], $retryquality['quality']['independence']);
        $this->assertLessThan($normalquality['quality']['support_level'], $retryquality['quality']['support_level']);
        $this->assertTrue($retryquality['attempt_semantics']['hint_or_answer_exposure']);
        $this->assertFalse($retryquality['attempt_semantics']['retry_collapse_allowed']);

        $override = clone($normal);
        $override->rubricjson = json_encode([
            'quality' => [
                'support' => 'UNSUPPORTED',
                'transfer' => 'FAR_TRANSFER',
            ],
        ]);
        $semantics = \local_flwcupkp\local\evidence_semantics_quality_contract::semantics_for_evidence($override);
        $this->assertSame(0.9, $semantics['quality']['support_level']);
        $this->assertSame(0.8, $semantics['quality']['contextual_transfer']);
    }

    public function test_evidence_guard_augments_stored_evidence_and_keeps_policy_versions_separate(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$courseid, $userid, $objectid, $kpid] = $this->create_mapped_object('assessment', 'assessment', 'quiz');

        $result = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => 'U-C3B',
            'objectid' => $objectid,
            'sourceattempt' => 'quiz-attempt:9001',
            'evidencetype' => 'quiz_attempt_submitted',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 9,
            'normalizedscore' => 0.9,
            'rubricjson' => json_encode(['occurred_at' => 12345]),
            'assessortype' => 'moodle_quiz',
            'confidence' => 0.92,
            'evidencestrength' => 'independent_performance',
            'provenance' => 'phpunit',
            'sourceref' => 'mod_quiz:attempt:9001',
            'timecreated' => 12345,
        ]);

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => $result['evidenceid']], '*', MUST_EXIST);
        $rubric = json_decode((string)$evidence->rubricjson, true);
        $semantics = $rubric['cupkp_c3b_semantics'];

        $this->assertSame('FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1',
            $rubric['cupkp_c3_mapping']['contract']);
        $this->assertSame('FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1', $semantics['contract']);
        $this->assertSame('cupkp-evidence-quality-v1', $semantics['policy_version']);
        $this->assertSame('default-v1', $result['state']['ruleversion']);
        $this->assertSame('positive', $semantics['result_state']);
        $this->assertSame('assessment_evidence', $semantics['evidence_role']);
        $this->assertSame('independent_production', $semantics['performance_mode']);
        $this->assertSame('direct', $semantics['evidence_direction']);
        $this->assertSame('quiz-attempt:9001', $semantics['source_key']['source_attempt_id']);
        $this->assertTrue($semantics['source_key']['legacy_direct_capture']);
        $this->assertSame('quality_dimensions_are_not_mastery_thresholds_in_c3b',
            $semantics['mastery_policy_boundary']);
    }

    public function test_inconclusive_evidence_does_not_directly_reduce_mastery(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$courseid, $userid, $objectid, $kpid] = $this->create_mapped_object('assessment', 'assessment', 'quiz');

        \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => 'U-C3B',
            'objectid' => $objectid,
            'sourceattempt' => 'quiz-attempt:1',
            'evidencetype' => 'quiz_attempt_submitted',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 9,
            'normalizedscore' => 0.9,
            'rubricjson' => '{}',
            'assessortype' => 'moodle_quiz',
            'confidence' => 0.9,
            'evidencestrength' => 'independent_performance',
            'provenance' => 'phpunit',
            'timecreated' => 1000,
        ]);
        $result = \local_flwcupkp\local\mastery_engine::record_evidence((object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => 'U-C3B',
            'objectid' => $objectid,
            'sourceattempt' => 'quiz-attempt:2',
            'evidencetype' => 'quiz_attempt_submitted',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 0,
            'normalizedscore' => 0,
            'rubricjson' => json_encode(['result_state' => 'INCONCLUSIVE', 'technical_failure' => true]),
            'assessortype' => 'moodle_quiz',
            'confidence' => 0.1,
            'evidencestrength' => 'independent_performance',
            'provenance' => 'phpunit',
            'timecreated' => 1100,
        ]);

        $this->assertSame(0.9, $result['state']['masteryscore']);
        $this->assertSame(2, $result['state']['evidencecount']);
    }

    public function test_evidence_semantics_status_is_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforeaudit = $DB->count_records('flwcupkp_audit');

        $status = \local_flwcupkp\local\evidence_semantics_quality_contract::evidence_semantics_status(
            (int)$course->id,
            'U-C3B',
            10
        );

        $this->assertSame('CupkpEvidenceSemanticsQualityStatus', $status['type']);
        $this->assertSame('P3_C3B', $status['gate']);
        $this->assertSame('FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1', $status['contract']['version']);
        $this->assertTrue($status['read_only']);
        $this->assertSame('C4', $status['next_allowed_gate']);
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
    }

    private function create_mapped_object(string $purpose, string $role, string $objecttype): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $now = time();
        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'FW-C3B-' . $purpose,
            'name' => 'Framework C3B ' . $purpose,
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-C3B-' . $purpose,
            'title' => 'Knowledge point ' . $purpose,
            'domain' => 'LEX',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'OBJ-C3B-' . $purpose,
            'courseid' => (int)$course->id,
            'unitcode' => 'U-C3B',
            'lesson' => '1',
            'objecttype' => $objecttype,
            'title' => 'Object ' . $purpose,
            'purpose' => $purpose,
            'role' => $role,
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'unitid' => 'UNIT-C3B',
                    'lessonid' => 'LESSON-C3B',
                    'activityid' => 'ACT-C3B-' . $purpose,
                ],
                'difficulty' => 0.62,
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
            'evidencestrength' => 'independent_performance',
        ]);

        return [(int)$course->id, (int)$learner->id, $objectid, $kpid];
    }
}

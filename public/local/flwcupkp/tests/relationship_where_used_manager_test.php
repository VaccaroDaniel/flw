<?php
// PHPUnit tests for Program 3 Gate CM2 relationship editor and where-used impact.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Relationship where-used manager tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\relationship_where_used_manager::class)]
class relationship_where_used_manager_test extends \advanced_testcase {
    public function test_contract_preserves_foundation_and_excludes_adaptive_logic(): void {
        $contract = \local_flwcupkp\local\relationship_where_used_manager::contract();

        $this->assertSame('P3_CM2', $contract['gate']);
        $this->assertSame('FLW_CUPKP_RELATIONSHIP_WHERE_USED_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\foundation_v1_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\relationship_graph_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains('preview_before_save', $contract['editor_controls']);
        $this->assertContains('learner_state_references', $contract['where_used_shows']);
        $this->assertContains('mastery_state_recalculation', $contract['does_not_do']);
        $this->assertFalse($contract['state_changes_allowed']);
    }

    public function test_status_is_ready_and_points_to_e2_after_e1(): void {
        $this->resetAfterTest(true);

        $status = \local_flwcupkp\local\relationship_where_used_manager::status(0, '', 0, 20);

        $this->assertSame('CupkpRelationshipWhereUsedStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame('frozen', $status['foundation']['status']);
        $this->assertSame('E2', $status['next_allowed_gate']);
        $this->assertTrue($status['files']['mappings.php']);
        $this->assertFalse($status['state_changes_allowed']);
    }

    public function test_where_used_impact_counts_content_evidence_and_state_references(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture();

        $impact = \local_flwcupkp\local\relationship_where_used_manager::where_used_impact(
            'kp',
            $fixture['kpid'],
            $fixture['courseid'],
            'UCM2',
            50
        );

        $this->assertSame('CupkpCm2WhereUsedImpact', $impact['type']);
        $this->assertSame('kp', $impact['entity']['type']);
        $this->assertGreaterThanOrEqual(1, $impact['counts']['competencies']);
        $this->assertGreaterThanOrEqual(1, $impact['counts']['use_points']);
        $this->assertSame(1, $impact['counts']['learning_objects']);
        $this->assertSame(1, $impact['counts']['courses']);
        $this->assertSame(1, $impact['counts']['units']);
        $this->assertSame(1, $impact['counts']['lessons']);
        $this->assertSame(1, $impact['counts']['activities']);
        $this->assertSame(1, $impact['counts']['questions']);
        $this->assertGreaterThanOrEqual(1, $impact['counts']['checkpoints']);
        $this->assertSame(1, $impact['counts']['evidence_count']);
        $this->assertSame(1, $impact['counts']['learner_state_references']);
        $this->assertFalse($impact['state_changes_allowed']);
    }

    public function test_preview_mapping_change_validates_without_writing(): void {
        global $DB;

        $this->resetAfterTest(true);
        $fixture = $this->create_fixture();
        $newkp = $this->create_kp($fixture['frameworkid'], 'KP-CM2-PREVIEW');
        $before = $DB->count_records('flwcupkp_up_kp');

        $preview = \local_flwcupkp\local\relationship_where_used_manager::preview_mapping_change('up_kp', [
            'upid' => $fixture['upid'],
            'kpid' => $newkp,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 2,
        ], 'save', $fixture['courseid'], 'UCM2', 50);

        $this->assertTrue($preview['valid'], json_encode($preview['errors']));
        $this->assertSame('REQUIRES', $preview['semantic']);
        $this->assertFalse($preview['would_write']);
        $this->assertFalse($preview['state_changes_allowed']);
        $this->assertSame($before, $DB->count_records('flwcupkp_up_kp'));
    }

    public function test_apply_mapping_change_writes_and_audits_after_preview(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture();
        $newkp = $this->create_kp($fixture['frameworkid'], 'KP-CM2-APPLY');

        $result = \local_flwcupkp\local\relationship_where_used_manager::apply_mapping_change('up_kp', [
            'upid' => $fixture['upid'],
            'kpid' => $newkp,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 3,
        ], 'save', $fixture['courseid'], 'UCM2', 50);

        $this->assertTrue($result['applied']);
        $this->assertTrue($DB->record_exists('flwcupkp_up_kp', [
            'upid' => $fixture['upid'],
            'kpid' => $newkp,
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'cm2_relationship_change_applied',
            'targettype' => 'up_kp',
            'targetid' => $result['appliedid'],
        ]));
    }

    public function test_preview_delete_blocks_object_map_with_evidence(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture();

        $preview = \local_flwcupkp\local\relationship_where_used_manager::preview_mapping_change('object_map', [
            'id' => $fixture['objectmapid'],
        ], 'delete', $fixture['courseid'], 'UCM2', 50);

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('evidence', implode(' ', $preview['errors']));
        $this->assertSame(1, $preview['impact']['counts']['evidence_count']);
        $this->assertFalse($preview['state_changes_allowed']);
    }

    /**
     * Create compact CM2 fixture.
     *
     * @return array
     */
    private function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'CM2-FW',
            'name' => 'CM2 Framework',
            'courseid' => $course->id,
            'coursecode' => 'CM2',
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'C-CM2',
            'title' => 'CM2 competency',
            'cando' => 'Can complete the CM2 task.',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'domain' => 'speaking',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'UP-CM2',
            'title' => 'CM2 use point',
            'actionstatement' => 'Use the CM2 pattern.',
            'cefr' => 'B1',
            'languagemode' => 'speaking',
            'interactiontype' => 'pair',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = $this->create_kp($frameworkid, 'KP-CM2');
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'OBJ-CM2',
            'courseid' => $course->id,
            'unitcode' => 'UCM2',
            'lesson' => '1',
            'objecttype' => 'checkpoint',
            'title' => 'CM2 checkpoint object',
            'cmid' => 222,
            'sourceid' => 'CM2-ACT-1',
            'purpose' => 'assessment',
            'evidencestrength' => 'guided_performance',
            'role' => 'assessment',
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'activityid' => 'CM2-ACT-1',
                    'questionid' => 'Q-CM2-1',
                ],
            ]),
        ]);

        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $compid,
            'upid' => $upid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid,
            'kpid' => $kpid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        $objectmapid = (int)$DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'assessment',
            'evidencestrength' => 'guided_performance',
        ]);
        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'unitcode' => 'UCM2',
            'objectid' => $objectid,
            'sourceattempt' => 'cm2-attempt-1',
            'evidencetype' => 'quiz_attempt',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 0.9,
            'normalizedscore' => 0.9,
            'assessortype' => 'system',
            'confidence' => 0.92,
            'evidencestrength' => 'guided_performance',
            'provenance' => 'test',
            'timecreated' => $now,
        ]);
        $DB->insert_record('flwcupkp_state', (object)[
            'userid' => $user->id,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'masteryscore' => 0.9,
            'masterystate' => 'mastered',
            'confidence' => 0.92,
            'evidencecount' => 1,
            'lastevidence' => $now,
            'ruleversion' => 'test-v1',
            'timemodified' => $now,
        ]);

        return [
            'courseid' => (int)$course->id,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'kpid' => $kpid,
            'objectid' => $objectid,
            'objectmapid' => $objectmapid,
        ];
    }

    /**
     * Create one KP row.
     *
     * @param int $frameworkid
     * @param string $externalid
     * @return int
     */
    private function create_kp(int $frameworkid, string $externalid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'description' => 'CM2 knowledge point.',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'FUNC',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}

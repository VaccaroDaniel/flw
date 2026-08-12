<?php
// PHPUnit tests for specialized evidence adapters.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Specialized evidence adapter tests.
 *
 * @covers \local_flwcupkp\local\specialized_evidence_adapter
 */
class specialized_evidence_adapter_test extends \advanced_testcase {
    public function test_trusted_stt_result_records_scored_evidence_without_raw_audio(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);

        $ids = $this->create_mapped_object((int)$course->id);
        $result = \local_flwcupkp\local\specialized_evidence_adapter::record_stt_result((object)[
            'objectid' => $ids['object'],
            'courseid' => $course->id,
            'userid' => $learner->id,
            'sourceref' => 'phpunit-stt',
            'expectedresponse' => 'I think we should ask the manager.',
            'recognizedresponse' => 'I think we should ask the manager',
            'similarity' => 0.95,
            'taskcompletion' => 0.90,
            'intelligibility' => 0.85,
            'confidence' => 0.80,
        ]);

        $this->assertSame('processed', $result['status']);
        $this->assertCount(1, $result['evidenceids']);

        $evidence = $DB->get_record('flwcupkp_evidence', ['id' => reset($result['evidenceids'])], '*', MUST_EXIST);
        $this->assertSame('speaking_stt', $evidence->evidencetype);
        $this->assertSame('trusted_stt', $evidence->assessortype);
        $this->assertSame('server_side_stt', $evidence->provenance);
        $this->assertEqualsWithDelta(0.915, (float)$evidence->normalizedscore, 0.00001);

        $rubric = json_decode($evidence->rubricjson, true);
        $this->assertSame('I think we should ask the manager.', $rubric['expected_response']);
        $this->assertSame('I think we should ask the manager', $rubric['recognized_response']);
        $this->assertArrayNotHasKey('audio', $rubric);
        $this->assertArrayNotHasKey('raw_audio', $rubric);
        $this->assertArrayNotHasKey('audio_blob', $rubric);
    }

    /**
     * Create one object mapped to one KP.
     *
     * @param int $courseid
     * @return array
     */
    private function create_mapped_object(int $courseid): array {
        global $DB;

        $now = time();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'TEST-FW-STT',
            'name' => 'STT framework',
            'courseid' => $courseid,
            'coursecode' => 'TEST',
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'test',
            'description' => '',
            'parentid' => null,
            'moodleframeworkid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-KP-STT',
            'title' => 'STT speaking KP',
            'description' => '',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'SPEAK',
            'formtext' => '',
            'meaningfunction' => '',
            'usageconstraints' => '',
            'difficulty' => 0.5,
            'learningload' => 1.0,
            'evidencerequirements' => '[]',
            'status' => 'test',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $objectid = $DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-OBJ-STT',
            'courseid' => $courseid,
            'unitcode' => 'T001',
            'lesson' => 'Project',
            'objecttype' => 'speaking_task',
            'title' => 'STT speaking task',
            'cmid' => null,
            'sourceid' => '',
            'purpose' => 'assessment',
            'evidencestrength' => 'guided_performance',
            'difficulty' => 0.5,
            'role' => 'assessment',
            'metadatajson' => '{}',
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'assessment',
            'evidencestrength' => 'guided_performance',
        ]);

        return ['framework' => (int)$frameworkid, 'kp' => (int)$kpid, 'object' => (int)$objectid];
    }
}

<?php
// PHPUnit tests for calibration recalculation runs.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Calibration recalculation tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\calibration_proposal::class)]
class calibration_recalculation_test extends \advanced_testcase {
    public function test_queued_recalculation_updates_changed_kp_state(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $now = time();
        $user = $this->getDataGenerator()->create_user();
        $frameworkid = $this->create_framework();
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-KP-CALC',
            'title' => 'Calibration KP',
            'description' => '',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'LEX',
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

        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => 0,
            'unitcode' => 'T001',
            'objectid' => 0,
            'sourceattempt' => 'phpunit-calibration',
            'evidencetype' => 'phpunit_evidence',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 0.92,
            'normalizedscore' => 0.92,
            'rubricjson' => '{}',
            'assessortype' => 'phpunit',
            'confidence' => 0.80,
            'evidencestrength' => 'controlled_production',
            'provenance' => 'phpunit',
            'sourceref' => 'phpunit',
            'overrideflag' => 0,
            'timecreated' => $now,
            'usermodified' => 0,
        ]);

        \local_flwcupkp\local\repository::upsert_state((int)$user->id, 'kp', (int)$kpid, [
            'masteryscore' => 0.40,
            'masterystate' => 'practiced',
            'confidence' => 0.40,
            'evidencecount' => 1,
            'lastevidence' => $now,
            'lastsuccess' => $now,
            'ruleversion' => 'old-rule',
        ]);

        $snapshotid = $this->create_snapshot((int)$user->id, (int)$kpid);
        $proposalid = \local_flwcupkp\local\calibration_proposal::save($snapshotid, 'kp', 'PHPUnit proposal', '', [
            'introduced' => 0.10,
            'practiced' => 0.20,
            'controlled_use' => 0.45,
            'independent_use' => 0.70,
            'mastered' => 0.85,
        ]);
        \local_flwcupkp\local\calibration_proposal::activate($proposalid);

        $runid = \local_flwcupkp\local\calibration_proposal::queue_recalculation($proposalid);
        $processed = \local_flwcupkp\local\calibration_proposal::process_next_recalculation(1);

        $this->assertSame($runid, $processed[0]['runid']);
        $this->assertSame(1, $processed[0]['applied']);
        $run = $DB->get_record('flwcupkp_calrecalc', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame('completed', $run->status);
        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $user->id,
            'targettype' => 'kp',
            'targetid' => $kpid,
        ], '*', MUST_EXIST);
        $this->assertSame('mastered', $state->masterystate);
    }

    /**
     * Create a test framework.
     *
     * @return int
     */
    private function create_framework(): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'TEST-FW-CALC',
            'name' => 'Calibration framework',
            'courseid' => null,
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
    }

    /**
     * Create a saved calibration snapshot.
     *
     * @param int $userid
     * @param int $kpid
     * @return int
     */
    private function create_snapshot(int $userid, int $kpid): int {
        global $DB, $USER;

        $payload = [
            'filters' => ['courseid' => 0, 'unitcode' => '', 'targettype' => 'kp'],
            'report' => ['summary' => ['states' => 1]],
            'state_details' => [[
                'userid' => $userid,
                'targettype' => 'kp',
                'targetid' => $kpid,
                'masteryscore' => 0.40,
                'masterystate' => 'practiced',
            ]],
        ];
        $reportjson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return (int)$DB->insert_record('flwcupkp_calsnapshot', (object)[
            'name' => 'PHPUnit snapshot',
            'courseid' => null,
            'unitcode' => null,
            'targettype' => 'kp',
            'summaryjson' => json_encode($payload['report']['summary'], JSON_UNESCAPED_SLASHES),
            'reportjson' => $reportjson,
            'checksum' => hash('sha256', $reportjson),
            'note' => '',
            'userid' => $USER->id ?? 0,
            'timecreated' => time(),
        ]);
    }
}

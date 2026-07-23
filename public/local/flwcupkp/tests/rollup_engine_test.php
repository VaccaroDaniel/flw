<?php
// PHPUnit tests for local_flwcupkp roll-up engine.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Roll-up engine tests.
 *
 * @covers \local_flwcupkp\local\rollup_engine
 */
class rollup_engine_test extends \advanced_testcase {
    public function test_kp_mastery_rolls_up_to_provisional_competency(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $ids = $this->create_chain();
        $now = time();

        \local_flwcupkp\local\repository::upsert_state((int)$user->id, 'kp', $ids['kp'], [
            'masteryscore' => 0.90,
            'masterystate' => 'mastered',
            'confidence' => 0.80,
            'evidencecount' => 1,
            'lastevidence' => $now,
            'lastsuccess' => $now,
            'ruleversion' => 'test-v1',
        ]);

        \local_flwcupkp\local\rollup_engine::recalculate_dependents((int)$user->id, 'kp', $ids['kp'], false);

        $upstate = $DB->get_record('flwcupkp_state', [
            'userid' => $user->id,
            'targettype' => 'up',
            'targetid' => $ids['up'],
        ], '*', MUST_EXIST);
        $compstate = $DB->get_record('flwcupkp_state', [
            'userid' => $user->id,
            'targettype' => 'competency',
            'targetid' => $ids['competency'],
        ], '*', MUST_EXIST);

        $this->assertSame('transfer_ready', $upstate->masterystate);
        $this->assertSame('provisionally_achieved', $compstate->masterystate);
    }

    public function test_direct_up_performance_can_achieve_competency_rollup(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $ids = $this->create_chain();
        $now = time();

        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => 0,
            'unitcode' => 'T001',
            'objectid' => 0,
            'sourceattempt' => 'phpunit-direct-up',
            'evidencetype' => 'performance_task',
            'targettype' => 'up',
            'targetid' => $ids['up'],
            'rawscore' => 0.86,
            'normalizedscore' => 0.86,
            'rubricjson' => '{}',
            'assessortype' => 'phpunit',
            'confidence' => 0.85,
            'evidencestrength' => 'guided_performance',
            'provenance' => 'phpunit',
            'sourceref' => 'phpunit',
            'overrideflag' => 0,
            'timecreated' => $now,
            'usermodified' => 0,
        ]);

        \local_flwcupkp\local\rollup_engine::recalculate_dependents((int)$user->id, 'up', $ids['up'], false);

        $compstate = $DB->get_record('flwcupkp_state', [
            'userid' => $user->id,
            'targettype' => 'competency',
            'targetid' => $ids['competency'],
        ], '*', MUST_EXIST);

        $this->assertSame('achieved', $compstate->masterystate);
    }

    /**
     * Create one competency -> UP -> KP chain.
     *
     * @return array
     */
    private function create_chain(): array {
        global $DB;

        $now = time();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'TEST-FW-' . $now . random_int(1, 9999),
            'name' => 'Test framework',
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
        $competencyid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-C-' . $now . random_int(1, 9999),
            'title' => 'Test competency',
            'cando' => '',
            'description' => '',
            'cefr' => 'B1',
            'stage' => null,
            'domain' => 'test',
            'scope' => 'unit',
            'evidencerule' => json_encode([
                'minimum_direct_events' => 1,
                'required_strength' => 'guided_performance',
                'minimum_score' => 0.78,
            ]),
            'moodlecompetencyid' => null,
            'status' => 'test',
            'version' => '1.0',
            'validfrom' => null,
            'validto' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $upid = $DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-UP-' . $now . random_int(1, 9999),
            'title' => 'Test UP',
            'actionstatement' => '',
            'intention' => '',
            'context' => '',
            'observableaction' => '',
            'conditions' => '',
            'successcriteria' => '',
            'cefr' => 'B1',
            'languagemode' => '',
            'interactiontype' => '',
            'evidencerequirements' => '[]',
            'rubricref' => null,
            'status' => 'test',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'TEST-KP-' . $now . random_int(1, 9999),
            'title' => 'Test KP',
            'description' => '',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'TEST',
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

        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $competencyid,
            'upid' => $upid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
            'minmastery' => 0.70,
            'evidencerule' => '[]',
            'notes' => null,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid,
            'kpid' => $kpid,
            'role' => 'required',
            'weight' => 1,
            'minreadiness' => 0.70,
            'sortorder' => 1,
            'notes' => null,
        ]);

        return [
            'framework' => (int)$frameworkid,
            'competency' => (int)$competencyid,
            'up' => (int)$upid,
            'kp' => (int)$kpid,
        ];
    }
}

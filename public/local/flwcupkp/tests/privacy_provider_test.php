<?php
// PHPUnit tests for local_flwcupkp privacy provider.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_userlist;
use local_flwcupkp\privacy\provider;

/**
 * Privacy provider tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\privacy\provider::class)]
class privacy_provider_test extends \advanced_testcase {
    public function test_delete_data_for_users_removes_learner_data_and_anonymizes_operational_logs(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'PRIV-FW',
            'name' => 'Privacy Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = $DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'PRIV-KP',
            'title' => 'Privacy KP',
            'domain' => 'LEX',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $evidenceid = $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'evidencetype' => 'manual',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'normalizedscore' => 0.8,
            'confidence' => 0.7,
            'evidencestrength' => 'recognition',
            'timecreated' => $now,
            'usermodified' => $user->id,
        ]);
        $DB->insert_record('flwcupkp_state', (object)[
            'userid' => $user->id,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'masteryscore' => 0.8,
            'masterystate' => 'mastered',
            'confidence' => 0.7,
            'evidencecount' => 1,
            'timemodified' => $now,
        ]);
        $DB->insert_record('flwcupkp_recommend', (object)[
            'userid' => $user->id,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'reason' => 'Practice',
            'status' => 'recommended',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $importid = $DB->insert_record('flwcupkp_import', (object)[
            'sourcefile' => 'privacy.json',
            'schemaversion' => '1.0',
            'checksum' => sha1('privacy'),
            'validationstatus' => 'valid',
            'entitycount' => 0,
            'rollbackstatus' => 'not_rolled_back',
            'userid' => $user->id,
            'timecreated' => $now,
        ]);
        $auditid = $DB->insert_record('flwcupkp_audit', (object)[
            'action' => 'privacy_test',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'detailsjson' => '{}',
            'userid' => $user->id,
            'timecreated' => $now,
        ]);

        $systemcontext = \context_system::instance(0, MUST_EXIST, false);
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertContains($systemcontext->id, array_map('intval', $contextlist->get_contextids()));

        provider::delete_data_for_users(new approved_userlist($systemcontext, 'local_flwcupkp', [$user->id]));

        $this->assertFalse($DB->record_exists('flwcupkp_evidence', ['id' => $evidenceid]));
        $this->assertFalse($DB->record_exists('flwcupkp_state', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('flwcupkp_recommend', ['userid' => $user->id]));
        $this->assertNull($DB->get_field('flwcupkp_import', 'userid', ['id' => $importid], MUST_EXIST));
        $this->assertNull($DB->get_field('flwcupkp_audit', 'userid', ['id' => $auditid], MUST_EXIST));
    }
}

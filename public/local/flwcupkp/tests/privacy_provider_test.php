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
        $goalid = $DB->insert_record('flwcupkp_goal', (object)[
            'userid' => $user->id,
            'title' => 'Privacy Goal',
            'desiredprofilejson' => '{"profile":"Read unit texts independently"}',
            'competencyidsjson' => '[]',
            'upidsjson' => '[]',
            'kpidsjson' => '[]',
            'priorityskillsjson' => '[]',
            'source' => 'STUDENT',
            'status' => 'active',
            'currentversion' => 1,
            'goalpolicyversion' => 'privacy-test',
            'checksum' => sha1('learner-goal'),
            'timecreated' => $now,
            'timemodified' => $now,
            'useridcreated' => $user->id,
            'usermodified' => $user->id,
        ]);
        $goalversionid = $DB->insert_record('flwcupkp_goal_version', (object)[
            'goalid' => $goalid,
            'version' => 1,
            'userid' => $user->id,
            'title' => 'Privacy Goal',
            'desiredprofilejson' => '{"profile":"Read unit texts independently"}',
            'competencyidsjson' => '[]',
            'upidsjson' => '[]',
            'kpidsjson' => '[]',
            'priorityskillsjson' => '[]',
            'source' => 'STUDENT',
            'status' => 'active',
            'goalpolicyversion' => 'privacy-test',
            'checksum' => sha1('learner-goal-version'),
            'useridcreated' => $user->id,
            'timecreated' => $now,
        ]);
        $otheruser = $this->getDataGenerator()->create_user();
        $actorgoalid = $DB->insert_record('flwcupkp_goal', (object)[
            'userid' => $otheruser->id,
            'title' => 'Actor Goal',
            'desiredprofilejson' => '{"profile":"Teacher updated goal"}',
            'competencyidsjson' => '[]',
            'upidsjson' => '[]',
            'kpidsjson' => '[]',
            'priorityskillsjson' => '[]',
            'source' => 'TEACHER',
            'status' => 'active',
            'currentversion' => 1,
            'goalpolicyversion' => 'privacy-test',
            'checksum' => sha1('actor-goal'),
            'timecreated' => $now,
            'timemodified' => $now,
            'useridcreated' => $otheruser->id,
            'usermodified' => $user->id,
        ]);
        $actorversionid = $DB->insert_record('flwcupkp_goal_version', (object)[
            'goalid' => $actorgoalid,
            'version' => 1,
            'userid' => $otheruser->id,
            'title' => 'Actor Goal',
            'desiredprofilejson' => '{"profile":"Teacher updated goal"}',
            'competencyidsjson' => '[]',
            'upidsjson' => '[]',
            'kpidsjson' => '[]',
            'priorityskillsjson' => '[]',
            'source' => 'TEACHER',
            'status' => 'active',
            'goalpolicyversion' => 'privacy-test',
            'checksum' => sha1('actor-goal-version'),
            'useridcreated' => $user->id,
            'timecreated' => $now,
        ]);
        $placementstateid = $DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $user->id,
            'courseid' => 0,
            'frameworkid' => $frameworkid,
            'unitcode' => 'PRIV',
            'sourcekey' => 'privacy-placement',
            'sourcefactkey' => 'privacy-placement',
            'placementstatus' => 'recorded',
            'policystate' => 'VALID',
            'sourcecategory' => 'imported_history',
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'score' => 0.75,
            'confidence' => 0.8,
            'placementtime' => $now,
            'staleafter' => $now + DAYSECS,
            'assesseddimensionsjson' => '[]',
            'evidenceidsjson' => '[]',
            'diagnosticjson' => '{}',
            'policyversion' => 'privacy-test',
            'checksum' => sha1('privacy-placement'),
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $user->id,
        ]);
        $actorplacementstateid = $DB->insert_record('flwcupkp_placement_state', (object)[
            'userid' => $otheruser->id,
            'courseid' => 0,
            'frameworkid' => $frameworkid,
            'unitcode' => 'PRIV',
            'sourcekey' => 'privacy-placement-actor',
            'sourcefactkey' => 'privacy-placement-actor',
            'placementstatus' => 'recorded',
            'policystate' => 'VALID',
            'sourcecategory' => 'teacher_override',
            'previouslevel' => 'A2',
            'currentlevel' => 'B1',
            'score' => 0.75,
            'confidence' => 0.8,
            'placementtime' => $now,
            'staleafter' => $now + DAYSECS,
            'assesseddimensionsjson' => '[]',
            'evidenceidsjson' => '[]',
            'diagnosticjson' => '{}',
            'policyversion' => 'privacy-test',
            'checksum' => sha1('privacy-placement-actor'),
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $user->id,
        ]);
        $learnerinterventionid = $DB->insert_record('flwcupkp_intervention', (object)[
            'serieskey' => hash('sha256', 'privacy-learner-intervention'),
            'version' => 1,
            'userid' => $user->id,
            'courseid' => 0,
            'unitcode' => 'PRIV',
            'interventiontype' => 'hold_advancement',
            'actioncode' => 'HOLD',
            'payloadjson' => '{"hold":true}',
            'reason' => 'Privacy learner intervention',
            'status' => 'active',
            'policyversion' => 'privacy-test',
            'createdby' => $otheruser->id,
            'timecreated' => $now,
        ]);
        $actorinterventionid = $DB->insert_record('flwcupkp_intervention', (object)[
            'serieskey' => hash('sha256', 'privacy-actor-intervention'),
            'version' => 1,
            'userid' => $otheruser->id,
            'courseid' => 0,
            'unitcode' => 'PRIV',
            'interventiontype' => 'teacher_evidence',
            'actioncode' => 'TEACHER_EVIDENCE',
            'payloadjson' => '{}',
            'reason' => 'Privacy actor intervention',
            'status' => 'recorded',
            'policyversion' => 'privacy-test',
            'createdby' => $user->id,
            'timecreated' => $now,
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
        $this->assertFalse($DB->record_exists('flwcupkp_goal', ['id' => $goalid]));
        $this->assertFalse($DB->record_exists('flwcupkp_goal_version', ['id' => $goalversionid]));
        $this->assertFalse($DB->record_exists('flwcupkp_placement_state', ['id' => $placementstateid]));
        $this->assertFalse($DB->record_exists('flwcupkp_intervention', ['id' => $learnerinterventionid]));
        $this->assertNull($DB->get_field('flwcupkp_goal', 'usermodified', ['id' => $actorgoalid], MUST_EXIST));
        $this->assertNull($DB->get_field('flwcupkp_goal_version', 'useridcreated',
            ['id' => $actorversionid], MUST_EXIST));
        $this->assertNull($DB->get_field('flwcupkp_placement_state', 'usermodified',
            ['id' => $actorplacementstateid], MUST_EXIST));
        $this->assertSame(0, (int)$DB->get_field('flwcupkp_intervention', 'createdby',
            ['id' => $actorinterventionid], MUST_EXIST));
        $this->assertNull($DB->get_field('flwcupkp_import', 'userid', ['id' => $importid], MUST_EXIST));
        $this->assertNull($DB->get_field('flwcupkp_audit', 'userid', ['id' => $auditid], MUST_EXIST));
    }
}

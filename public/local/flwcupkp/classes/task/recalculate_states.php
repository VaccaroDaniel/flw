<?php
// Scheduled state recalculation.

namespace local_flwcupkp\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_flwcupkp\local\mastery_engine;
use local_flwcupkp\local\repository;
use local_flwcupkp\local\rollup_engine;

/**
 * Recalculate changed learner states.
 */
class recalculate_states extends scheduled_task {
    public function get_name(): string {
        return get_string('pluginname', 'local_flwcupkp') . ': recalculate learner states';
    }

    public function execute(): void {
        global $DB;

        $groups = $DB->get_records_sql("SELECT DISTINCT userid, targettype, targetid FROM {flwcupkp_evidence}");
        foreach ($groups as $group) {
            $events = $DB->get_records('flwcupkp_evidence', [
                'userid' => $group->userid,
                'targettype' => $group->targettype,
                'targetid' => $group->targetid,
            ], 'timecreated ASC');
            $state = mastery_engine::calculate($group->targettype, array_values($events));
            repository::upsert_state((int)$group->userid, $group->targettype, (int)$group->targetid, $state);
            try {
                rollup_engine::recalculate_dependents(
                    (int)$group->userid,
                    (string)$group->targettype,
                    (int)$group->targetid,
                    true
                );
            } catch (\Throwable $e) {
                repository::audit('rollup_state_sync_failed', (string)$group->targettype, (int)$group->targetid, [
                    'userid' => (int)$group->userid,
                    'message' => $e->getMessage(),
                    'source' => 'scheduled_recalculate_states',
                ]);
            }
        }
    }
}

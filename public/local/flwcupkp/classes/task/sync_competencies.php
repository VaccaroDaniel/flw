<?php
// Scheduled Moodle competency sync.

namespace local_flwcupkp\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_flwcupkp\local\curriculum_manager;
use local_flwcupkp\local\moodle_competency_writer;
use local_flwcupkp\local\repository;

/**
 * Dry-run Moodle competency synchronization.
 */
class sync_competencies extends scheduled_task {
    public function get_name(): string {
        return get_string('pluginname', 'local_flwcupkp') . ': Moodle competency sync';
    }

    public function execute(): void {
        $writeenabled = (bool)get_config('local_flwcupkp', 'enablesyncwrites');
        $summary = curriculum_manager::sync_readiness();
        $dryrun = !$writeenabled || empty($summary['readyforwrites']);
        $writeresult = moodle_competency_writer::sync_all($dryrun);
        repository::audit('competency_sync_checked', null, null, [
            'dryrun' => $dryrun,
            'writeenabled' => $writeenabled,
            'readyforwrites' => !empty($summary['readyforwrites']),
            'summary' => $summary,
            'writeresult' => $writeresult,
            'note' => $dryrun ? 'Write mode requires verified target Moodle framework and competency IDs.' :
                'Write mode readiness verified and Moodle competency ratings synchronized.',
        ]);
    }
}

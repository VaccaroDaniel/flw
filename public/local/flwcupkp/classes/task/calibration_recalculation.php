<?php
// Scheduled processing for queued calibration recalculation runs.

namespace local_flwcupkp\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_flwcupkp\local\calibration_proposal;

/**
 * Processes queued threshold-calibration recalculation runs in small batches.
 */
class calibration_recalculation extends scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_flwcupkp') . ': process calibration recalculations';
    }

    /**
     * Execute queued recalculation runs.
     */
    public function execute(): void {
        calibration_proposal::process_next_recalculation(3);
    }
}

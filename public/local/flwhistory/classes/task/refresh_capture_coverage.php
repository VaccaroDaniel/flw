<?php
// Scheduled capture coverage refresh for local_flwhistory.

namespace local_flwhistory\task;

defined('MOODLE_INTERNAL') || die();

use local_flwhistory\local\capture_service;

/**
 * Refresh aggregate coverage facts for captured H2 source events.
 */
class refresh_capture_coverage extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_capture_coverage', 'local_flwhistory');
    }

    /**
     * Execute the coverage refresh.
     */
    public function execute(): void {
        capture_service::refresh_capture_coverage();
    }
}

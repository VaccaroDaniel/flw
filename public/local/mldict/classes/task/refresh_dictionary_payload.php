<?php
// Refresh FLW dictionary startup payload caches.

namespace local_mldict\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_mldict\local\dictionary;

/**
 * Scheduled task to rebuild dictionary startup cache payloads.
 */
class refresh_dictionary_payload extends scheduled_task {
    public function get_name(): string {
        return get_string('task_refresh_dictionary_payload', 'local_mldict');
    }

    public function execute(): void {
        $filterlimit = (int)get_config('filter_mldict', 'maxterms');
        dictionary::refresh_payload_cache($filterlimit);
    }
}

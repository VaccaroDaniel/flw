<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwaiassessment\task;

use local_flwaiassessment\service\result_repository;
use local_flwaiassessment\service\scoring_client;

defined('MOODLE_INTERNAL') || die();

/**
 * Process pending FLW speaking and writing assessment records.
 */
class process_pending extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_flwaiassessment');
    }

    /**
     * Execute scheduled processing.
     */
    public function execute(): void {
        if (!get_config('local_flwaiassessment', 'enableprocessing')) {
            return;
        }

        $client = new scoring_client();
        foreach (result_repository::get_pending_for_processing(10) as $record) {
            result_repository::update_status((int) $record->id, result_repository::STATUS_PROCESSING);

            try {
                if ($record->skilltype === 'writing' && trim((string) $record->rawtext) !== '') {
                    $response = $client->estimate_writing($record);
                } else if ($record->skilltype === 'speaking' && trim((string) $record->transcript) !== '') {
                    $response = $client->estimate_speaking($record);
                } else {
                    result_repository::update_status(
                        (int) $record->id,
                        result_repository::STATUS_NEEDS_INPUT,
                        'No text or transcript is available for scoring.'
                    );
                    continue;
                }

                result_repository::save_ai_response((int) $record->id, $response);
            } catch (\Throwable $exception) {
                result_repository::update_status(
                    (int) $record->id,
                    result_repository::STATUS_FAILED,
                    $exception->getMessage()
                );
            }
        }
    }
}

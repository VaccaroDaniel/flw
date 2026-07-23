<?php
// Event observers for local_flwcupkp.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

use local_flwcupkp\local\repository;
use local_flwcupkp\local\activity_evidence_adapter;
use local_flwcupkp\local\quiz_evidence_adapter;

/**
 * Conservative Moodle event observers.
 */
class observer {
    /**
     * Completion is audit-only until explicit activity mappings are present.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event): void {
        $result = activity_evidence_adapter::process_completion($event);
        repository::audit('course_module_completion_updated', 'cmid', (int)$event->contextinstanceid, [
            'userid' => $event->relateduserid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }

    /**
     * Convert mapped quiz attempts into C-UP-KP evidence.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        repository::audit('quiz_attempt_submitted', 'quiz_attempt', (int)$event->objectid, [
            'userid' => $event->relateduserid,
            'courseid' => $event->courseid,
            'adapter_result' => ['status' => 'waiting_for_attempt_graded'],
        ]);
    }

    /**
     * Convert graded mapped quiz attempts into C-UP-KP evidence.
     *
     * @param \mod_quiz\event\attempt_graded $event
     */
    public static function quiz_attempt_graded(\mod_quiz\event\attempt_graded $event): void {
        $result = quiz_evidence_adapter::process_attempt_graded($event);
        repository::audit('quiz_attempt_graded', 'quiz_attempt', (int)$event->objectid, [
            'userid' => $event->relateduserid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }
}

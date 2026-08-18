<?php
// Event observers for local_flwcupkp.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

use local_flwcupkp\local\repository;
use local_flwcupkp\local\activity_evidence_adapter;
use local_flwcupkp\local\flwvrroom_evidence_adapter;
use local_flwcupkp\local\quiz_evidence_adapter;
use local_flwcupkp\local\specialized_evidence_adapter;

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

    /**
     * Convert mapped assignment submissions into conservative C-UP-KP evidence.
     *
     * @param \mod_assign\event\assessable_submitted $event
     */
    public static function assign_assessable_submitted(\mod_assign\event\assessable_submitted $event): void {
        $result = specialized_evidence_adapter::process_assign_submission($event);
        repository::audit('assign_assessable_submitted', 'assign_submission', (int)$event->objectid, [
            'userid' => $event->relateduserid ?: $event->userid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }

    /**
     * Convert mapped assignment grades into C-UP-KP evidence.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function assign_submission_graded(\mod_assign\event\submission_graded $event): void {
        $result = specialized_evidence_adapter::process_assign_grade($event);
        repository::audit('assign_submission_graded', 'assign_grade', (int)$event->objectid, [
            'userid' => $event->relateduserid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }

    /**
     * Convert mapped H5P xAPI statements into C-UP-KP evidence.
     *
     * @param \mod_h5pactivity\event\statement_received $event
     */
    public static function h5p_statement_received(\mod_h5pactivity\event\statement_received $event): void {
        $result = specialized_evidence_adapter::process_h5p_statement($event);
        repository::audit('h5p_statement_received', 'h5pactivity', (int)$event->objectid, [
            'userid' => $event->userid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }

    /**
     * Convert mapped SCORM status events into C-UP-KP evidence.
     *
     * @param \mod_scorm\event\status_submitted $event
     */
    public static function scorm_status_submitted(\mod_scorm\event\status_submitted $event): void {
        $result = specialized_evidence_adapter::process_scorm_status($event);
        repository::audit('scorm_status_submitted', 'scorm_status', (int)$event->objectid, [
            'userid' => $event->userid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }

    /**
     * Convert mapped SCORM raw score events into C-UP-KP evidence.
     *
     * @param \mod_scorm\event\scoreraw_submitted $event
     */
    public static function scorm_scoreraw_submitted(\mod_scorm\event\scoreraw_submitted $event): void {
        $result = specialized_evidence_adapter::process_scorm_score($event);
        repository::audit('scorm_scoreraw_submitted', 'scorm_score', (int)$event->objectid, [
            'userid' => $event->userid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }

    /**
     * Convert mapped FLW VR Room attempts into C-UP-KP evidence.
     *
     * @param \core\event\base $event
     */
    public static function flwvrroom_attempt_submitted(\core\event\base $event): void {
        $result = flwvrroom_evidence_adapter::process_attempt_submitted($event);
        repository::audit('flwvrroom_attempt_submitted', 'flwvrroom_attempt', (int)$event->objectid, [
            'userid' => $event->relateduserid ?: $event->userid,
            'courseid' => $event->courseid,
            'adapter_result' => $result,
        ]);
    }
}

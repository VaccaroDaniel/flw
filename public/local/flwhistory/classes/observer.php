<?php
// Lightweight source capture observers for local_flwhistory.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

use local_flwhistory\local\capture_service;
use local_flwhistory\local\grade_history_service;

/**
 * Production-safe source capture observers.
 */
class observer {
    /**
     * Capture a verified Moodle quiz attempt lifecycle event.
     *
     * @param \core\event\base $event Moodle event.
     */
    public static function quiz_attempt_event(\core\event\base $event): void {
        self::safely(function() use ($event): void {
            capture_service::capture_quiz_attempt_event($event);
        });
    }

    /**
     * Capture a verified Moodle SCORM score or status transition.
     *
     * @param \core\event\base $event Moodle event.
     */
    public static function scorm_attempt_event(\core\event\base $event): void {
        self::safely(function() use ($event): void {
            capture_service::capture_scorm_attempt_event($event);
        });
    }

    /**
     * Capture a course module completion transition from Moodle completion.
     *
     * @param \core\event\course_module_completion_updated $event Moodle event.
     */
    public static function course_module_completion_updated(
        \core\event\course_module_completion_updated $event
    ): void {
        self::safely(function() use ($event): void {
            capture_service::capture_course_module_completion($event);
        });
    }

    /**
     * Capture a course completion event as a source fact.
     *
     * @param \core\event\course_completion_updated $event Moodle event.
     */
    public static function course_completion_updated(\core\event\course_completion_updated $event): void {
        self::safely(function() use ($event): void {
            capture_service::capture_course_completion_event($event);
        });
    }

    /**
     * Capture the verified custom FLW VR Room attempt event.
     *
     * @param \core\event\base $event Moodle event.
     */
    public static function flwvrroom_attempt_submitted(\core\event\base $event): void {
        self::safely(function() use ($event): void {
            capture_service::capture_flwvrroom_attempt_submitted($event);
        });
    }

    /**
     * Capture official Moodle Gradebook grade changes.
     *
     * @param \core\event\base $event Moodle event.
     */
    public static function user_graded(\core\event\base $event): void {
        self::safely(function() use ($event): void {
            grade_history_service::capture_user_graded_event($event);
        });
    }

    /**
     * Capture official Moodle Gradebook grade deletion facts.
     *
     * @param \core\event\base $event Moodle event.
     */
    public static function grade_deleted(\core\event\base $event): void {
        self::safely(function() use ($event): void {
            grade_history_service::capture_grade_deleted_event($event);
        });
    }

    /**
     * Keep normal Moodle actions independent from FLW history diagnostics.
     *
     * @param callable $callback Capture callback.
     */
    private static function safely(callable $callback): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            debugging('local_flwhistory observer capture failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

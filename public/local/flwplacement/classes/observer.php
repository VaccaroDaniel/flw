<?php
// Event observers for local_flwplacement.

namespace local_flwplacement;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle event observers for FLW Placement.
 */
class observer {
    /**
     * Sync a linked Moodle Quiz placement attempt after Moodle submits it.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::sync_quiz_attempt($event, 'quiz_attempt_submitted');
    }

    /**
     * Sync a linked Moodle Quiz placement attempt after Moodle grades it.
     *
     * @param \mod_quiz\event\attempt_graded $event
     */
    public static function quiz_attempt_graded(\mod_quiz\event\attempt_graded $event): void {
        self::sync_quiz_attempt($event, 'quiz_attempt_graded');
    }

    /**
     * Sync a linked Moodle Quiz placement attempt after manual grading completes.
     *
     * @param \mod_quiz\event\attempt_manual_grading_completed $event
     */
    public static function quiz_attempt_manual_grading_completed(
        \mod_quiz\event\attempt_manual_grading_completed $event
    ): void {
        self::sync_quiz_attempt($event, 'quiz_attempt_manual_grading_completed');
    }

    /**
     * Import the event attempt when it belongs to a configured placement quiz.
     *
     * @param \core\event\base $event
     * @param string $sourceevent
     */
    protected static function sync_quiz_attempt(\core\event\base $event, string $sourceevent): void {
        global $CFG, $DB;

        $attemptid = (int)$event->objectid;
        if ($attemptid <= 0) {
            return;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt || (int)$attempt->preview === 1 || $attempt->state !== 'finished' || $attempt->sumgrades === null) {
            return;
        }

        $quizid = (int)($event->other['quizid'] ?? $attempt->quiz);
        $userid = (int)($event->relateduserid ?? $attempt->userid);
        if ($quizid <= 0 || $userid <= 0 || (int)$attempt->quiz !== $quizid || (int)$attempt->userid !== $userid) {
            return;
        }

        require_once($CFG->dirroot . '/local/flwplacement/locallib.php');
        $language = local_flwplacement_get_quiz_language_for_quiz_id($quizid);
        if (!$language) {
            return;
        }

        try {
            local_flwplacement_save_quiz_attempt_result(
                $quizid,
                $userid,
                $attemptid,
                $language['code'],
                $language['label']
            );
        } catch (\Throwable $e) {
            debugging(
                'local_flwplacement could not sync Moodle Quiz attempt ' . $attemptid . ' from ' . $sourceevent .
                    ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}

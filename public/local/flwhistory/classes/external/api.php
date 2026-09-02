<?php
// External service API for local_flwhistory.

namespace local_flwhistory\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use local_flwhistory\local\history_api_service;
use local_flwhistory\local\source_identity;

require_once($CFG->libdir . '/externallib.php');

/**
 * Secure Moodle external functions for Program 2 history reads.
 */
class api extends external_api {
    /**
     * Present summary parameters.
     *
     * @return external_function_parameters
     */
    public static function get_present_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'Learner ID, or 0 for current user', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get trusted present summary.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id or 0 for current user.
     * @return array
     */
    public static function get_present_summary(int $courseid, int $userid = 0): array {
        $params = self::validate_parameters(self::get_present_summary_parameters(), compact('courseid', 'userid'));
        $userid = self::require_history_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(history_api_service::present_summary_core((int)$params['courseid'], $userid));
    }

    /**
     * Present summary returns.
     *
     * @return external_single_structure
     */
    public static function get_present_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    /**
     * Learning history parameters.
     *
     * @return external_function_parameters
     */
    public static function get_learning_history_parameters(): external_function_parameters {
        return self::common_history_parameters([
            'sourcefamily' => new external_value(PARAM_ALPHANUMEXT, 'Optional source family filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Query learning history.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id or 0 for current user.
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @param string $sourcefamily Optional source family.
     * @return array
     */
    public static function get_learning_history(
        int $courseid,
        int $userid = 0,
        int $limit = 50,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0,
        string $sourcefamily = ''
    ): array {
        $params = self::validate_parameters(self::get_learning_history_parameters(),
            compact('courseid', 'userid', 'limit', 'offset', 'timestart', 'timeend', 'sourcefamily'));
        $userid = self::require_history_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(history_api_service::learning_history_query(
            (int)$params['courseid'],
            $userid,
            (int)$params['limit'],
            (int)$params['offset'],
            (int)$params['timestart'],
            (int)$params['timeend'],
            (string)$params['sourcefamily']
        ));
    }

    /**
     * Learning history returns.
     *
     * @return external_single_structure
     */
    public static function get_learning_history_returns(): external_single_structure {
        return self::json_returns();
    }

    /**
     * Attempt history parameters.
     *
     * @return external_function_parameters
     */
    public static function get_attempt_history_parameters(): external_function_parameters {
        return self::common_history_parameters([
            'cmid' => new external_value(PARAM_INT, 'Optional course module ID filter', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Query attempt history.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id or 0 for current user.
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @param int $cmid Optional course module id.
     * @return array
     */
    public static function get_attempt_history(
        int $courseid,
        int $userid = 0,
        int $limit = 50,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0,
        int $cmid = 0
    ): array {
        $params = self::validate_parameters(self::get_attempt_history_parameters(),
            compact('courseid', 'userid', 'limit', 'offset', 'timestart', 'timeend', 'cmid'));
        $userid = self::require_history_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(history_api_service::attempt_history_query(
            (int)$params['courseid'],
            $userid,
            (int)$params['limit'],
            (int)$params['offset'],
            (int)$params['timestart'],
            (int)$params['timeend'],
            (int)$params['cmid']
        ));
    }

    /**
     * Attempt history returns.
     *
     * @return external_single_structure
     */
    public static function get_attempt_history_returns(): external_single_structure {
        return self::json_returns();
    }

    /**
     * Grade history parameters.
     *
     * @return external_function_parameters
     */
    public static function get_grade_history_parameters(): external_function_parameters {
        return self::common_history_parameters([
            'gradeitemid' => new external_value(PARAM_INT, 'Optional grade item ID filter', VALUE_DEFAULT, 0),
            'includeaudit' => new external_value(PARAM_BOOL, 'Include teacher/admin audit fields', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Query grade history.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id or 0 for current user.
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @param int $gradeitemid Optional grade item id.
     * @param bool $includeaudit Include audit fields.
     * @return array
     */
    public static function get_grade_history(
        int $courseid,
        int $userid = 0,
        int $limit = 50,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0,
        int $gradeitemid = 0,
        bool $includeaudit = false
    ): array {
        $params = self::validate_parameters(self::get_grade_history_parameters(),
            compact('courseid', 'userid', 'limit', 'offset', 'timestart', 'timeend', 'gradeitemid', 'includeaudit'));
        $userid = self::require_history_access((int)$params['courseid'], (int)$params['userid'],
            (bool)$params['includeaudit']);
        return self::json_response(history_api_service::grade_history_query(
            (int)$params['courseid'],
            $userid,
            (int)$params['limit'],
            (int)$params['offset'],
            (int)$params['timestart'],
            (int)$params['timeend'],
            (int)$params['gradeitemid'],
            (bool)$params['includeaudit']
        ));
    }

    /**
     * Grade history returns.
     *
     * @return external_single_structure
     */
    public static function get_grade_history_returns(): external_single_structure {
        return self::json_returns();
    }

    /**
     * Recent activity parameters.
     *
     * @return external_function_parameters
     */
    public static function get_recent_activity_parameters(): external_function_parameters {
        return self::common_history_parameters();
    }

    /**
     * Query recent activity.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id or 0 for current user.
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param int $timestart Optional start time.
     * @param int $timeend Optional end time.
     * @return array
     */
    public static function get_recent_activity(
        int $courseid,
        int $userid = 0,
        int $limit = 20,
        int $offset = 0,
        int $timestart = 0,
        int $timeend = 0
    ): array {
        $params = self::validate_parameters(self::get_recent_activity_parameters(),
            compact('courseid', 'userid', 'limit', 'offset', 'timestart', 'timeend'));
        $userid = self::require_history_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(history_api_service::recent_activity_query(
            (int)$params['courseid'],
            $userid,
            (int)$params['limit'],
            (int)$params['offset'],
            (int)$params['timestart'],
            (int)$params['timeend']
        ));
    }

    /**
     * Recent activity returns.
     *
     * @return external_single_structure
     */
    public static function get_recent_activity_returns(): external_single_structure {
        return self::json_returns();
    }

    /**
     * Learning journey parameters.
     *
     * @return external_function_parameters
     */
    public static function get_learning_journey_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'Learner ID, or 0 for current user', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get learning journey core.
     *
     * @param int $courseid Course id.
     * @param int $userid Learner id or 0 for current user.
     * @return array
     */
    public static function get_learning_journey(int $courseid, int $userid = 0): array {
        $params = self::validate_parameters(self::get_learning_journey_parameters(), compact('courseid', 'userid'));
        $userid = self::require_history_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(history_api_service::learning_journey_core((int)$params['courseid'], $userid));
    }

    /**
     * Learning journey returns.
     *
     * @return external_single_structure
     */
    public static function get_learning_journey_returns(): external_single_structure {
        return self::json_returns();
    }

    /**
     * Common bounded history query parameters.
     *
     * @param array $extra Extra fields.
     * @return external_function_parameters
     */
    private static function common_history_parameters(array $extra = []): external_function_parameters {
        return new external_function_parameters(array_merge([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'Learner ID, or 0 for current user', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Page size, max 100', VALUE_DEFAULT, 50),
            'offset' => new external_value(PARAM_INT, 'Pagination offset', VALUE_DEFAULT, 0),
            'timestart' => new external_value(PARAM_INT, 'Optional start timestamp', VALUE_DEFAULT, 0),
            'timeend' => new external_value(PARAM_INT, 'Optional end timestamp', VALUE_DEFAULT, 0),
        ], $extra));
    }

    /**
     * Resolve and authorize requested learner access.
     *
     * @param int $courseid Course id.
     * @param int $requesteduserid Learner id or 0.
     * @param bool $includeaudit Whether audit fields are requested.
     * @return int Authorized learner id.
     */
    private static function require_history_access(int $courseid, int $requesteduserid = 0, bool $includeaudit = false): int {
        global $DB, $USER;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('A course ID is required.');
        }
        $context = context_course::instance($courseid);
        self::validate_context($context);

        $userid = $requesteduserid > 0 ? $requesteduserid : (int)$USER->id;
        if ($userid <= 0 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new \invalid_parameter_exception('The requested learner does not exist.');
        }

        if ($userid === (int)$USER->id) {
            require_capability('local/flwhistory:viewown', $context);
        } else if (!has_capability('local/flwhistory:viewall', context_system::instance())) {
            require_capability('local/flwhistory:viewcourse', $context);
        }

        if ($includeaudit && !has_capability('local/flwhistory:viewall', context_system::instance())) {
            require_capability('local/flwhistory:viewgradeaudit', $context);
        }

        return $userid;
    }

    /**
     * Build JSON wrapper response.
     *
     * @param array $payload Payload.
     * @return array
     */
    private static function json_response(array $payload): array {
        return [
            'status' => 'ok',
            'datajson' => source_identity::stable_json($payload),
        ];
    }

    /**
     * Common JSON wrapper return definition.
     *
     * @return external_single_structure
     */
    private static function json_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status'),
            'datajson' => new external_value(PARAM_RAW, 'JSON payload'),
        ]);
    }
}

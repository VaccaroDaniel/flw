<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_flwexam\service\exam_service;

/**
 * Legacy external API wrapper for FLW Exam.
 */
class local_flwexam_external extends external_api {
    /**
     * Parameters for get_my_history.
     *
     * @return external_function_parameters
     */
    public static function get_my_history_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return current user history.
     *
     * @return array
     */
    public static function get_my_history(): array {
        global $USER;

        self::validate_parameters(self::get_my_history_parameters(), []);
        $context = context_system::instance();
        self::validate_context($context);
        require_login();
        require_capability('local/flwexam:viewown', $context);

        exam_service::audit('api_get_my_history', (int)$USER->id, (int)$USER->id, 0, 0, 'success');
        return ['results' => exam_service::get_history((int)$USER->id)];
    }

    /**
     * Return definition for get_my_history.
     *
     * @return external_single_structure
     */
    public static function get_my_history_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(self::history_structure()),
        ]);
    }

    /**
     * Parameters for get_result.
     *
     * @return external_function_parameters
     */
    public static function get_result_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Result id'),
        ]);
    }

    /**
     * Return one result when permitted.
     *
     * @param int $id
     * @return array
     */
    public static function get_result(int $id): array {
        global $USER;

        $params = self::validate_parameters(self::get_result_parameters(), ['id' => $id]);
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        $result = exam_service::get_result_package((int)$params['id'], (int)$USER->id, true);
        exam_service::audit('api_get_result', (int)$USER->id, (int)$result['userid'], (int)$result['id'], (int)$result['certificate_id'], 'success');
        return self::flatten_result($result);
    }

    /**
     * Return definition for get_result.
     *
     * @return external_single_structure
     */
    public static function get_result_returns(): external_single_structure {
        return self::result_structure();
    }

    /**
     * Parameters for verify_certificate.
     *
     * @return external_function_parameters
     */
    public static function verify_certificate_parameters(): external_function_parameters {
        return new external_function_parameters([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Verification code'),
        ]);
    }

    /**
     * Verify certificate.
     *
     * @param string $code
     * @return array
     */
    public static function verify_certificate(string $code): array {
        global $USER;

        $params = self::validate_parameters(self::verify_certificate_parameters(), ['code' => $code]);
        $viewerid = isloggedin() && !isguestuser() ? (int)$USER->id : 0;
        return exam_service::verify_certificate($params['code'], $viewerid);
    }

    /**
     * Return definition for verify_certificate.
     *
     * @return external_single_structure
     */
    public static function verify_certificate_returns(): external_single_structure {
        return new external_single_structure([
            'valid' => new external_value(PARAM_BOOL, 'Whether certificate is currently valid'),
            'certificate_id' => new external_value(PARAM_INT, 'Certificate id'),
            'certificate_code' => new external_value(PARAM_RAW, 'Certificate code'),
            'learner_name' => new external_value(PARAM_RAW, 'Safe learner display name'),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code'),
            'learning_course_category' => new external_value(PARAM_ALPHANUMEXT, 'Internal exam category'),
            'cefr_level' => new external_value(PARAM_ALPHANUMEXT, 'CEFR level'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Certificate status'),
            'timeissued' => new external_value(PARAM_INT, 'Issue time'),
            'timeexpires' => new external_value(PARAM_INT, 'Expiry time'),
        ]);
    }

    /**
     * Parameters for submit_result.
     *
     * @return external_function_parameters
     */
    public static function submit_result_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user id'),
            'examid' => new external_value(PARAM_INT, 'Exam definition id'),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code', VALUE_DEFAULT, ''),
            'learning_course_category' => new external_value(PARAM_ALPHANUMEXT, 'Internal exam category', VALUE_DEFAULT, ''),
            'cefr_level' => new external_value(PARAM_ALPHANUMEXT, 'CEFR level', VALUE_DEFAULT, ''),
            'overall_score' => new external_value(PARAM_FLOAT, 'Overall score'),
            'skill_scores' => new external_multiple_structure(new external_single_structure([
                'skill' => new external_value(PARAM_ALPHANUMEXT, 'Skill key'),
                'score' => new external_value(PARAM_FLOAT, 'Skill score'),
            ])),
            'kp_results' => new external_multiple_structure(new external_single_structure([
                'kpcode' => new external_value(PARAM_ALPHANUMEXT, 'Knowledge-point code'),
                'score' => new external_value(PARAM_FLOAT, 'Knowledge-point score', VALUE_DEFAULT, 0),
                'passed' => new external_value(PARAM_BOOL, 'Gate passed', VALUE_DEFAULT, false),
                'critical' => new external_value(PARAM_BOOL, 'Critical gate', VALUE_DEFAULT, false),
            ]), 'Knowledge-point results', VALUE_DEFAULT, []),
            'integrity_status' => new external_value(PARAM_ALPHANUMEXT, 'Integrity status', VALUE_DEFAULT, 'clear'),
            'moderation_status' => new external_value(PARAM_ALPHANUMEXT, 'Moderation status', VALUE_DEFAULT, 'approved'),
            'attempt_metadata_json' => new external_value(PARAM_RAW, 'Attempt metadata JSON', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Submit an official result.
     *
     * @param int $userid
     * @param int $examid
     * @param string $language
     * @param string $learningcoursecategory
     * @param string $cefrlevel
     * @param float $overallscore
     * @param array $skillscores
     * @param array $kpresults
     * @param string $integritystatus
     * @param string $moderationstatus
     * @param string $attemptmetadatajson
     * @return array
     */
    public static function submit_result(
        int $userid,
        int $examid,
        string $language,
        string $learningcoursecategory,
        string $cefrlevel,
        float $overallscore,
        array $skillscores,
        array $kpresults = [],
        string $integritystatus = 'clear',
        string $moderationstatus = 'approved',
        string $attemptmetadatajson = '{}'
    ): array {
        global $USER;

        $params = self::validate_parameters(self::submit_result_parameters(), [
            'userid' => $userid,
            'examid' => $examid,
            'language' => $language,
            'learning_course_category' => $learningcoursecategory,
            'cefr_level' => $cefrlevel,
            'overall_score' => $overallscore,
            'skill_scores' => $skillscores,
            'kp_results' => $kpresults,
            'integrity_status' => $integritystatus,
            'moderation_status' => $moderationstatus,
            'attempt_metadata_json' => $attemptmetadatajson,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_login();
        require_capability('local/flwexam:submitresult', $context);

        $metadata = json_decode($params['attempt_metadata_json'], true);
        if (!is_array($metadata)) {
            throw new invalid_parameter_exception('Attempt metadata must be valid JSON object.');
        }
        $params['attempt_metadata'] = $metadata;

        $result = exam_service::submit_result($params, (int)$USER->id);
        return self::flatten_result($result);
    }

    /**
     * Return definition for submit_result.
     *
     * @return external_single_structure
     */
    public static function submit_result_returns(): external_single_structure {
        return self::result_structure();
    }

    /**
     * History structure.
     *
     * @return external_single_structure
     */
    protected static function history_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Result id'),
            'examid' => new external_value(PARAM_INT, 'Exam id'),
            'examname' => new external_value(PARAM_RAW, 'Exam name'),
            'examcode' => new external_value(PARAM_RAW, 'Exam code'),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code'),
            'learning_course_category' => new external_value(PARAM_ALPHANUMEXT, 'Internal exam category'),
            'cefr_level' => new external_value(PARAM_ALPHANUMEXT, 'CEFR level'),
            'overall_score' => new external_value(PARAM_FLOAT, 'Overall score'),
            'pass_status' => new external_value(PARAM_ALPHANUMEXT, 'Pass status'),
            'certificate_status' => new external_value(PARAM_ALPHANUMEXT, 'Certificate status'),
            'certificate_id' => new external_value(PARAM_INT, 'Certificate id'),
            'verify_code' => new external_value(PARAM_RAW, 'Verification code'),
            'timecreated' => new external_value(PARAM_INT, 'Creation time'),
        ]);
    }

    /**
     * Result structure.
     *
     * @return external_single_structure
     */
    protected static function result_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Result id'),
            'userid' => new external_value(PARAM_INT, 'Learner user id'),
            'learnername' => new external_value(PARAM_RAW, 'Learner name'),
            'examid' => new external_value(PARAM_INT, 'Exam id'),
            'examname' => new external_value(PARAM_RAW, 'Exam name'),
            'examcode' => new external_value(PARAM_RAW, 'Exam code'),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code'),
            'learning_course_category' => new external_value(PARAM_ALPHANUMEXT, 'Internal exam category'),
            'cefr_level' => new external_value(PARAM_ALPHANUMEXT, 'CEFR level'),
            'overall_score' => new external_value(PARAM_FLOAT, 'Overall score'),
            'pass_status' => new external_value(PARAM_ALPHANUMEXT, 'Pass status'),
            'certificate_status' => new external_value(PARAM_ALPHANUMEXT, 'Certificate status'),
            'certificate_id' => new external_value(PARAM_INT, 'Certificate id'),
            'certificate_code' => new external_value(PARAM_RAW, 'Certificate code'),
            'verify_code' => new external_value(PARAM_RAW, 'Verification code'),
            'timecreated' => new external_value(PARAM_INT, 'Creation time'),
            'skill_scores' => new external_multiple_structure(new external_single_structure([
                'skill' => new external_value(PARAM_ALPHANUMEXT, 'Skill key'),
                'score' => new external_value(PARAM_FLOAT, 'Skill score'),
                'passed' => new external_value(PARAM_INT, 'Passed flag'),
            ])),
            'kp_results' => new external_multiple_structure(new external_single_structure([
                'kpcode' => new external_value(PARAM_ALPHANUMEXT, 'Knowledge-point code'),
                'score' => new external_value(PARAM_FLOAT, 'Knowledge-point score'),
                'passed' => new external_value(PARAM_INT, 'Passed flag'),
                'critical' => new external_value(PARAM_INT, 'Critical flag'),
            ])),
            'decision_json' => new external_value(PARAM_RAW, 'Certificate decision JSON'),
            'integrity_status' => new external_value(PARAM_ALPHANUMEXT, 'Private integrity status, blank when not permitted'),
            'moderation_status' => new external_value(PARAM_ALPHANUMEXT, 'Private moderation status, blank when not permitted'),
        ]);
    }

    /**
     * Flatten a result package for external output.
     *
     * @param array $result
     * @return array
     */
    protected static function flatten_result(array $result): array {
        return [
            'id' => $result['id'],
            'userid' => $result['userid'],
            'learnername' => $result['learnername'],
            'examid' => $result['examid'],
            'examname' => $result['examname'],
            'examcode' => $result['examcode'],
            'language' => $result['language'],
            'learning_course_category' => $result['learning_course_category'],
            'cefr_level' => $result['cefr_level'],
            'overall_score' => $result['overall_score'],
            'pass_status' => $result['pass_status'],
            'certificate_status' => $result['certificate_status'],
            'certificate_id' => $result['certificate_id'],
            'certificate_code' => $result['certificate_code'],
            'verify_code' => $result['verify_code'],
            'timecreated' => $result['timecreated'],
            'skill_scores' => $result['skills'],
            'kp_results' => $result['kp_results'],
            'decision_json' => json_encode($result['decision']),
            'integrity_status' => $result['private']['integrity_status'] ?? '',
            'moderation_status' => $result['private']['moderation_status'] ?? '',
        ];
    }
}

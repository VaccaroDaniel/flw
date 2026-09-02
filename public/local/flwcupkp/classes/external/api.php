<?php
// External service API for local_flwcupkp.

namespace local_flwcupkp\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use local_flwcupkp\local\adaptive_decision_policy_service;
use local_flwcupkp\local\adaptive_path_engine_service;
use local_flwcupkp\local\audit_service;
use local_flwcupkp\local\candidate_activity_resolution_service;
use local_flwcupkp\local\curriculum_manager;
use local_flwcupkp\local\flwvrroom_evidence_adapter;
use local_flwcupkp\local\goal_gap_path_service;
use local_flwcupkp\local\history_evidence_adapter;
use local_flwcupkp\local\import_service;
use local_flwcupkp\local\learner_evaluation;
use local_flwcupkp\local\learner_experience_service;
use local_flwcupkp\local\learning_goal_service;
use local_flwcupkp\local\management_v1_contract;
use local_flwcupkp\local\mastery_engine;
use local_flwcupkp\local\mastery_state_service;
use local_flwcupkp\local\moodle_competency_writer;
use local_flwcupkp\local\placement_diagnostic_service;
use local_flwcupkp\local\progress_goal_readiness_service;
use local_flwcupkp\local\recommendation_engine;
use local_flwcupkp\local\retention_review_service;
use local_flwcupkp\local\student_learning_timeline_view_service;
use local_flwcupkp\local\staff_intelligence_service;
use local_flwcupkp\local\trajectory_invariant_service;
use local_flwcupkp\local\validator;

require_once($CFG->libdir . '/externallib.php');

/**
 * Moodle external functions.
 */
class api extends external_api {
    public static function get_frameworks_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function get_frameworks(): array {
        global $DB;
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        return array_values($DB->get_records('flwcupkp_framework', null, 'name ASC'));
    }

    public static function get_frameworks_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID'),
            'externalid' => new external_value(PARAM_TEXT, 'External ID'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'coursecode' => new external_value(PARAM_TEXT, 'Course code', VALUE_OPTIONAL),
            'language' => new external_value(PARAM_TEXT, 'Language', VALUE_OPTIONAL),
            'cefrrange' => new external_value(PARAM_TEXT, 'CEFR range', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]));
    }

    public static function save_framework_parameters(): external_function_parameters {
        return self::save_entity_parameters();
    }

    public static function save_framework(int $id = 0, string $datajson = ''): array {
        return self::save_entity('framework', $id, $datajson);
    }

    public static function save_framework_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_competencies_parameters(): external_function_parameters {
        return self::list_entity_parameters();
    }

    public static function get_competencies(int $frameworkid = 0, string $query = '', int $limit = 100,
            int $offset = 0): array {
        return self::list_entity('competency', $frameworkid, $query, $limit, $offset);
    }

    public static function get_competencies_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function save_competency_parameters(): external_function_parameters {
        return self::save_entity_parameters();
    }

    public static function save_competency(int $id = 0, string $datajson = ''): array {
        return self::save_entity('competency', $id, $datajson);
    }

    public static function save_competency_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_use_points_parameters(): external_function_parameters {
        return self::list_entity_parameters();
    }

    public static function get_use_points(int $frameworkid = 0, string $query = '', int $limit = 100,
            int $offset = 0): array {
        return self::list_entity('up', $frameworkid, $query, $limit, $offset);
    }

    public static function get_use_points_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function save_use_point_parameters(): external_function_parameters {
        return self::save_entity_parameters();
    }

    public static function save_use_point(int $id = 0, string $datajson = ''): array {
        return self::save_entity('up', $id, $datajson);
    }

    public static function save_use_point_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_knowledge_points_parameters(): external_function_parameters {
        return self::list_entity_parameters();
    }

    public static function get_knowledge_points(int $frameworkid = 0, string $query = '', int $limit = 100,
            int $offset = 0): array {
        return self::list_entity('kp', $frameworkid, $query, $limit, $offset);
    }

    public static function get_knowledge_points_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function save_knowledge_point_parameters(): external_function_parameters {
        return self::save_entity_parameters();
    }

    public static function save_knowledge_point(int $id = 0, string $datajson = ''): array {
        return self::save_entity('kp', $id, $datajson);
    }

    public static function save_knowledge_point_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_mappings_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Mapping type'),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Limit', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Offset', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_mappings(string $type, int $frameworkid = 0, int $limit = 100, int $offset = 0): array {
        self::validate_parameters(self::get_mappings_parameters(), compact('type', 'frameworkid', 'limit', 'offset'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        $rows = curriculum_manager::list_mappings($type, $frameworkid);
        return self::json_response([
            'type' => $type,
            'total' => count($rows),
            'records' => array_slice(array_values($rows), max(0, $offset), max(1, min(500, $limit))),
        ]);
    }

    public static function get_mappings_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function save_mapping_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Mapping type'),
            'id' => new external_value(PARAM_INT, 'Mapping ID', VALUE_DEFAULT, 0),
            'datajson' => new external_value(PARAM_RAW, 'Mapping data as JSON object'),
        ]);
    }

    public static function save_mapping(string $type, int $id = 0, string $datajson = ''): array {
        $params = self::validate_parameters(self::save_mapping_parameters(), compact('type', 'id', 'datajson'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('save_mapping');
        $data = self::decode_object_json($params['datajson']);
        if ((int)$params['id'] > 0) {
            $data['id'] = (int)$params['id'];
        }
        $mappingid = curriculum_manager::save_mapping($params['type'], $data);
        return self::json_response(['id' => $mappingid, 'status' => 'saved']);
    }

    public static function save_mapping_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function delete_mapping_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Mapping type'),
            'id' => new external_value(PARAM_INT, 'Mapping ID'),
        ]);
    }

    public static function delete_mapping(string $type, int $id): array {
        self::validate_parameters(self::delete_mapping_parameters(), compact('type', 'id'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('delete_mapping');
        curriculum_manager::delete_mapping($type, $id);
        return self::json_response(['id' => $id, 'status' => 'deleted']);
    }

    public static function delete_mapping_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function validate_import_parameters(): external_function_parameters {
        return new external_function_parameters([
            'json' => new external_value(PARAM_RAW, 'C-UP-KP JSON package'),
        ]);
    }

    public static function validate_import(string $json): array {
        $params = self::validate_parameters(self::validate_import_parameters(), ['json' => $json]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:import', context_system::instance());
        $package = json_decode($params['json'], true);
        if (!is_array($package)) {
            return self::json_response([
                'valid' => false,
                'errors' => ['Invalid JSON package.'],
                'warnings' => [],
            ]);
        }
        return self::json_response(validator::validate_package($package));
    }

    public static function validate_import_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function import_package_parameters(): external_function_parameters {
        return new external_function_parameters([
            'json' => new external_value(PARAM_RAW, 'C-UP-KP JSON package'),
            'sourcefile' => new external_value(PARAM_TEXT, 'Source file', VALUE_DEFAULT, ''),
        ]);
    }

    public static function import_package(string $json, string $sourcefile = ''): array {
        $params = self::validate_parameters(self::import_package_parameters(), ['json' => $json, 'sourcefile' => $sourcefile]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:import', context_system::instance());
        self::assert_write_rate_limit('import_package');
        return import_service::import_json($params['json'], $params['sourcefile']);
    }

    public static function import_package_returns(): external_single_structure {
        return new external_single_structure([
            'importid' => new external_value(PARAM_INT, 'Import ID'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'entitycount' => new external_value(PARAM_INT, 'Entity count', VALUE_OPTIONAL),
            'validation' => new external_single_structure([
                'valid' => new external_value(PARAM_BOOL, 'Valid'),
                'errors' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Error')),
                'warnings' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Warning')),
            ]),
        ]);
    }

    public static function validate_csv_import_parameters(): external_function_parameters {
        return new external_function_parameters([
            'csv' => new external_value(PARAM_RAW, 'C-UP-KP CSV artifact'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'CSV type', VALUE_DEFAULT, 'activity_mappings'),
        ]);
    }

    public static function validate_csv_import(string $csv, string $type = 'activity_mappings'): array {
        $params = self::validate_parameters(self::validate_csv_import_parameters(), compact('csv', 'type'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:import', context_system::instance());
        return self::json_response(import_service::validate_csv($params['csv'], $params['type']));
    }

    public static function validate_csv_import_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function import_csv_parameters(): external_function_parameters {
        return new external_function_parameters([
            'csv' => new external_value(PARAM_RAW, 'C-UP-KP CSV artifact'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'CSV type', VALUE_DEFAULT, 'activity_mappings'),
            'sourcefile' => new external_value(PARAM_TEXT, 'Source file', VALUE_DEFAULT, ''),
        ]);
    }

    public static function import_csv(string $csv, string $type = 'activity_mappings', string $sourcefile = ''): array {
        $params = self::validate_parameters(self::import_csv_parameters(), compact('csv', 'type', 'sourcefile'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:import', context_system::instance());
        self::assert_write_rate_limit('import_csv');
        return import_service::import_csv($params['csv'], $params['type'], $params['sourcefile']);
    }

    public static function import_csv_returns(): external_single_structure {
        return self::import_package_returns();
    }

    public static function record_evidence_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_TEXT, 'Unit code', VALUE_DEFAULT, ''),
            'evidencetype' => new external_value(PARAM_TEXT, 'Evidence type'),
            'targettype' => new external_value(PARAM_ALPHA, 'Target type'),
            'targetid' => new external_value(PARAM_INT, 'Target ID'),
            'rawscore' => new external_value(PARAM_FLOAT, 'Raw score', VALUE_DEFAULT, 0),
            'normalizedscore' => new external_value(PARAM_FLOAT, 'Normalized score'),
            'confidence' => new external_value(PARAM_FLOAT, 'Confidence', VALUE_DEFAULT, 0.5),
            'evidencestrength' => new external_value(PARAM_TEXT, 'Evidence strength'),
            'provenance' => new external_value(PARAM_TEXT, 'Provenance', VALUE_DEFAULT, 'manual'),
        ]);
    }

    public static function record_evidence(int $userid, int $courseid, string $unitcode, string $evidencetype, string $targettype, int $targetid, float $rawscore, float $normalizedscore, float $confidence, string $evidencestrength, string $provenance): array {
        $params = self::validate_parameters(self::record_evidence_parameters(), compact('userid', 'courseid', 'unitcode', 'evidencetype', 'targettype', 'targetid', 'rawscore', 'normalizedscore', 'confidence', 'evidencestrength', 'provenance'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:override', context_system::instance());
        self::assert_write_rate_limit('record_evidence');
        return mastery_engine::record_evidence((object)$params);
    }

    public static function record_evidence_returns(): external_single_structure {
        return new external_single_structure([
            'evidenceid' => new external_value(PARAM_INT, 'Evidence ID'),
            'state' => new external_single_structure([
                'masteryscore' => new external_value(PARAM_FLOAT, 'Mastery score'),
                'masterystate' => new external_value(PARAM_TEXT, 'Mastery state'),
                'confidence' => new external_value(PARAM_FLOAT, 'Confidence'),
                'evidencecount' => new external_value(PARAM_INT, 'Evidence count'),
                'ruleversion' => new external_value(PARAM_TEXT, 'Rule version'),
            ]),
        ]);
    }

    public static function record_flwvrroom_attempt_parameters(): external_function_parameters {
        return new external_function_parameters([
            'payloadjson' => new external_value(PARAM_RAW, 'Structured FLW VR Room attempt JSON payload'),
        ]);
    }

    public static function record_flwvrroom_attempt(string $payloadjson = ''): array {
        $params = self::validate_parameters(self::record_flwvrroom_attempt_parameters(), compact('payloadjson'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:override', context_system::instance());
        self::assert_write_rate_limit('record_flwvrroom_attempt');

        $payload = (object)self::decode_object_json($params['payloadjson']);
        return self::json_response(flwvrroom_evidence_adapter::process_payload($payload));
    }

    public static function record_flwvrroom_attempt_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_states_parameters(): external_function_parameters {
        return new external_function_parameters(['userid' => new external_value(PARAM_INT, 'Learner ID')]);
    }

    public static function get_learner_states(int $userid): array {
        global $DB;
        self::validate_parameters(self::get_learner_states_parameters(), ['userid' => $userid]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewlearnerpath', context_system::instance());
        return array_values($DB->get_records('flwcupkp_state', ['userid' => $userid], 'targettype ASC, targetid ASC'));
    }

    public static function get_learner_states_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'targettype' => new external_value(PARAM_TEXT, 'Target type'),
            'targetid' => new external_value(PARAM_INT, 'Target ID'),
            'masteryscore' => new external_value(PARAM_FLOAT, 'Mastery score'),
            'masterystate' => new external_value(PARAM_TEXT, 'Mastery state'),
            'confidence' => new external_value(PARAM_FLOAT, 'Confidence'),
            'evidencecount' => new external_value(PARAM_INT, 'Evidence count'),
        ]));
    }

    public static function get_recommendations_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_recommendations(int $userid, int $courseid = 0): array {
        self::validate_parameters(self::get_recommendations_parameters(), ['userid' => $userid, 'courseid' => $courseid]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewlearnerpath', context_system::instance());
        return recommendation_engine::generate($userid, $courseid ?: null);
    }

    public static function get_recommendations_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'objectid' => new external_value(PARAM_INT, 'Object ID', VALUE_OPTIONAL),
            'targettype' => new external_value(PARAM_TEXT, 'Target type', VALUE_OPTIONAL),
            'targetid' => new external_value(PARAM_INT, 'Target ID', VALUE_OPTIONAL),
            'reason' => new external_value(PARAM_TEXT, 'Reason'),
            'masterygap' => new external_value(PARAM_FLOAT, 'Mastery gap', VALUE_OPTIONAL),
            'expectedbenefit' => new external_value(PARAM_TEXT, 'Expected benefit'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]));
    }

    public static function get_learner_learning_path_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_learner_learning_path(int $userid, int $courseid = 0): array {
        global $DB;

        self::validate_parameters(self::get_learner_learning_path_parameters(), compact('userid', 'courseid'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewlearnerpath', context_system::instance());
        return self::json_response([
            'userid' => $userid,
            'courseid' => $courseid,
            'states' => array_values($DB->get_records('flwcupkp_state', ['userid' => $userid],
                'targettype ASC, targetid ASC')),
            'recommendations' => recommendation_engine::generate($userid, $courseid ?: null),
        ]);
    }

    public static function get_learner_learning_path_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_evaluation_periods_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_TEXT, 'Unit code', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Period status', VALUE_DEFAULT, 'active'),
        ]);
    }

    public static function get_evaluation_periods(int $courseid = 0, int $frameworkid = 0, string $unitcode = '',
            string $status = 'active'): array {
        $params = self::validate_parameters(self::get_evaluation_periods_parameters(),
            compact('courseid', 'frameworkid', 'unitcode', 'status'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response([
            'periods' => learner_evaluation::periods(
                (int)$params['courseid'],
                (int)$params['frameworkid'],
                (string)$params['unitcode'],
                (string)$params['status']
            ),
        ]);
    }

    public static function get_evaluation_periods_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function save_evaluation_period_parameters(): external_function_parameters {
        return new external_function_parameters([
            'datajson' => new external_value(PARAM_RAW, 'Evaluation period payload as JSON object'),
        ]);
    }

    public static function save_evaluation_period(string $datajson): array {
        $params = self::validate_parameters(self::save_evaluation_period_parameters(), ['datajson' => $datajson]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('save_evaluation_period');
        $data = self::decode_object_json($params['datajson']);
        $id = learner_evaluation::save_period($data);
        return self::json_response(['id' => $id, 'status' => 'saved']);
    }

    public static function save_evaluation_period_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_evaluation_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'periodid' => new external_value(PARAM_INT, 'Evaluation period ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_TEXT, 'Unit code', VALUE_DEFAULT, ''),
        ]);
    }

    public static function get_learner_evaluation(int $userid, int $courseid = 0, int $periodid = 0,
            string $unitcode = ''): array {
        $params = self::validate_parameters(self::get_learner_evaluation_parameters(),
            compact('userid', 'courseid', 'periodid', 'unitcode'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(learner_evaluation::profile(
            (int)$params['userid'],
            (int)$params['courseid'],
            (int)$params['periodid'],
            (string)$params['unitcode']
        ));
    }

    public static function get_learner_evaluation_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function create_evaluation_snapshot_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'periodid' => new external_value(PARAM_INT, 'Evaluation period ID', VALUE_DEFAULT, 0),
            'evaluationtype' => new external_value(PARAM_ALPHANUMEXT, 'Evaluation type', VALUE_DEFAULT, 'unit'),
            'evidencecutoff' => new external_value(PARAM_INT, 'Evidence cutoff timestamp', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_TEXT, 'Unit code', VALUE_DEFAULT, ''),
        ]);
    }

    public static function create_evaluation_snapshot(int $userid, int $courseid = 0, int $frameworkid = 0,
            int $periodid = 0, string $evaluationtype = 'unit', int $evidencecutoff = 0, string $unitcode = ''): array {
        $params = self::validate_parameters(self::create_evaluation_snapshot_parameters(),
            compact('userid', 'courseid', 'frameworkid', 'periodid', 'evaluationtype', 'evidencecutoff', 'unitcode'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], true);
        self::assert_write_rate_limit('create_evaluation_snapshot');
        return self::json_response(learner_evaluation::create_snapshot(
            (int)$params['userid'],
            (int)$params['courseid'],
            (int)$params['frameworkid'],
            (int)$params['periodid'],
            (string)$params['evaluationtype'],
            (int)$params['evidencecutoff'],
            (string)$params['unitcode']
        ));
    }

    public static function create_evaluation_snapshot_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function record_self_evaluation_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'periodid' => new external_value(PARAM_INT, 'Evaluation period ID', VALUE_DEFAULT, 0),
            'targettype' => new external_value(PARAM_ALPHA, 'Target type'),
            'targetid' => new external_value(PARAM_INT, 'Target ID'),
            'selfrating' => new external_value(PARAM_FLOAT, 'Self rating from 0 to 1'),
            'reflection' => new external_value(PARAM_RAW, 'Learner reflection', VALUE_DEFAULT, ''),
        ]);
    }

    public static function record_self_evaluation(int $userid, int $courseid, int $periodid, string $targettype,
            int $targetid, float $selfrating, string $reflection = ''): array {
        $params = self::validate_parameters(self::record_self_evaluation_parameters(),
            compact('userid', 'courseid', 'periodid', 'targettype', 'targetid', 'selfrating', 'reflection'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], true);
        self::assert_write_rate_limit('record_self_evaluation');
        return self::json_response(learner_evaluation::record_self_evaluation(
            (int)$params['userid'],
            (int)$params['courseid'],
            (int)$params['periodid'],
            (string)$params['targettype'],
            (int)$params['targetid'],
            (float)$params['selfrating'],
            (string)$params['reflection']
        ));
    }

    public static function record_self_evaluation_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_course_evaluation_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_TEXT, 'Unit code', VALUE_DEFAULT, ''),
            'periodid' => new external_value(PARAM_INT, 'Evaluation period ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_course_evaluation_summary(int $courseid, string $unitcode = '', int $periodid = 0): array {
        $params = self::validate_parameters(self::get_course_evaluation_summary_parameters(),
            compact('courseid', 'unitcode', 'periodid'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(learner_evaluation::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['periodid']
        ));
    }

    public static function get_course_evaluation_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_coverage_report_parameters(): external_function_parameters {
        return new external_function_parameters(['frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0)]);
    }

    public static function get_coverage_report(int $frameworkid = 0): array {
        self::validate_parameters(self::get_coverage_report_parameters(), ['frameworkid' => $frameworkid]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        return audit_service::coverage($frameworkid ?: null);
    }

    public static function get_coverage_report_returns(): external_single_structure {
        return new external_single_structure([
            'competencies' => new external_value(PARAM_INT, 'Competency count'),
            'use_points' => new external_value(PARAM_INT, 'Use Point count'),
            'knowledge_points' => new external_value(PARAM_INT, 'Knowledge Point count'),
            'competencies_linked_to_up_percent' => new external_value(PARAM_FLOAT, 'Competency coverage'),
            'use_points_linked_to_kp_percent' => new external_value(PARAM_FLOAT, 'UP coverage'),
            'kps_linked_to_learning_objects_percent' => new external_value(PARAM_FLOAT, 'KP object coverage'),
            'competencies_with_direct_evidence_percent' => new external_value(PARAM_FLOAT, 'Direct evidence coverage'),
            'warnings' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Warning')),
        ]);
    }

    public static function get_orphans_report_parameters(): external_function_parameters {
        return self::report_parameters();
    }

    public static function get_orphans_report(int $frameworkid = 0, int $limit = 100): array {
        self::validate_parameters(self::report_parameters(), compact('frameworkid', 'limit'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        return self::json_response(self::orphans_report($frameworkid, $limit));
    }

    public static function get_orphans_report_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_evidence_gaps_report_parameters(): external_function_parameters {
        return self::report_parameters();
    }

    public static function get_evidence_gaps_report(int $frameworkid = 0, int $limit = 100): array {
        self::validate_parameters(self::report_parameters(), compact('frameworkid', 'limit'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        return self::json_response(self::evidence_gaps_report($frameworkid, $limit));
    }

    public static function get_evidence_gaps_report_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_cefr_alignment_report_parameters(): external_function_parameters {
        return self::report_parameters();
    }

    public static function get_cefr_alignment_report(int $frameworkid = 0, int $limit = 100): array {
        self::validate_parameters(self::report_parameters(), compact('frameworkid', 'limit'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        return self::json_response(self::cefr_alignment_report($frameworkid, $limit));
    }

    public static function get_cefr_alignment_report_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_import_validation_parameters(): external_function_parameters {
        return new external_function_parameters([
            'importid' => new external_value(PARAM_INT, 'Import batch ID'),
        ]);
    }

    public static function get_import_validation(int $importid): array {
        global $DB;

        self::validate_parameters(self::get_import_validation_parameters(), ['importid' => $importid]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:import', context_system::instance());
        $record = $DB->get_record('flwcupkp_import', ['id' => $importid], '*', MUST_EXIST);
        return self::json_response([
            'id' => (int)$record->id,
            'sourcefile' => (string)$record->sourcefile,
            'schemaversion' => (string)$record->schemaversion,
            'checksum' => (string)$record->checksum,
            'validationstatus' => (string)$record->validationstatus,
            'warnings' => json_decode((string)$record->warningsjson, true) ?: [],
            'errors' => json_decode((string)$record->errorsjson, true) ?: [],
            'entitycount' => (int)$record->entitycount,
            'rollbackstatus' => (string)$record->rollbackstatus,
            'timecreated' => (int)$record->timecreated,
        ]);
    }

    public static function get_import_validation_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function sync_moodle_competencies_parameters(): external_function_parameters {
        return new external_function_parameters(['dryrun' => new external_value(PARAM_BOOL, 'Dry run', VALUE_DEFAULT, true)]);
    }

    public static function sync_moodle_competencies(bool $dryrun = true): array {
        self::validate_parameters(self::sync_moodle_competencies_parameters(), ['dryrun' => $dryrun]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:synccompetencies', context_system::instance());
        self::assert_write_rate_limit('sync_moodle_competencies');
        $writeenabled = (bool)get_config('local_flwcupkp', 'enablesyncwrites');
        $summary = curriculum_manager::sync_readiness();
        $effectivewrite = !$dryrun && $writeenabled && !empty($summary['readyforwrites']);
        $writeresult = moodle_competency_writer::sync_all(!$effectivewrite);
        return [
            'dryrun' => !$effectivewrite,
            'status' => $effectivewrite ? 'ready_for_write_mode' : 'dry_run_only',
            'message' => $effectivewrite ?
                'Competency sync wrote ' . $writeresult['written'] . ' Moodle rating(s).' :
                'Competency sync dry-run scanned ' . $writeresult['scanned'] . ' C-UP-KP state row(s).',
        ];
    }

    public static function sync_moodle_competencies_returns(): external_single_structure {
        return new external_single_structure([
            'dryrun' => new external_value(PARAM_BOOL, 'Dry run'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    public static function get_sync_status_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function get_sync_status(): array {
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        return self::json_response([
            'writeenabled' => (bool)get_config('local_flwcupkp', 'enablesyncwrites'),
            'readiness' => curriculum_manager::sync_readiness(),
        ]);
    }

    public static function get_sync_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_management_v1_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_management_v1_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_management_v1_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(management_v1_contract::consumer_snapshot(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_management_v1_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_history_evidence_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_history_evidence_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_history_evidence_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(history_evidence_adapter::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_history_evidence_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function preview_history_evidence_reprocess_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'facttypesjson' => new external_value(PARAM_RAW, 'Fact type JSON array', VALUE_DEFAULT, '[]'),
            'limit' => new external_value(PARAM_INT, 'Record limit', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Record offset', VALUE_DEFAULT, 0),
        ]);
    }

    public static function preview_history_evidence_reprocess(int $courseid, string $unitcode = '',
            int $frameworkid = 0, string $facttypesjson = '[]', int $limit = 100, int $offset = 0): array {
        $params = self::validate_parameters(self::preview_history_evidence_reprocess_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'facttypesjson', 'limit', 'offset'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(history_evidence_adapter::preview_reprocess(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            self::decode_json_array((string)$params['facttypesjson']),
            (int)$params['limit'],
            (int)$params['offset']
        ));
    }

    public static function preview_history_evidence_reprocess_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_history_evidence_reprocess_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'facttypesjson' => new external_value(PARAM_RAW, 'Fact type JSON array', VALUE_DEFAULT, '[]'),
            'limit' => new external_value(PARAM_INT, 'Record limit', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Record offset', VALUE_DEFAULT, 0),
            'reason' => new external_value(PARAM_TEXT, 'Operator reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function apply_history_evidence_reprocess(int $courseid, string $unitcode = '',
            int $frameworkid = 0, string $facttypesjson = '[]', int $limit = 100, int $offset = 0,
            string $reason = ''): array {
        $params = self::validate_parameters(self::apply_history_evidence_reprocess_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'facttypesjson', 'limit', 'offset', 'reason'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('apply_history_evidence_reprocess');
        return self::json_response(history_evidence_adapter::apply_reprocess(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            self::decode_json_array((string)$params['facttypesjson']),
            (int)$params['limit'],
            (int)$params['offset'],
            (string)$params['reason']
        ));
    }

    public static function apply_history_evidence_reprocess_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_mastery_state_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_mastery_state_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_mastery_state_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(mastery_state_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_mastery_state_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_current_learner_state_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_current_learner_state(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_current_learner_state_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(mastery_state_service::current_learner_state(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_current_learner_state_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_current_state_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_current_state_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_current_state_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(mastery_state_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_current_state_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function preview_mastery_state_rebuild_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Learner ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function preview_mastery_state_rebuild(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $userid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::preview_mastery_state_rebuild_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'userid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(mastery_state_service::preview_rebuild(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['userid'],
            (int)$params['limit']
        ));
    }

    public static function preview_mastery_state_rebuild_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_mastery_state_rebuild_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Learner ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
            'reason' => new external_value(PARAM_TEXT, 'Operator reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function apply_mastery_state_rebuild(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $userid = 0, int $limit = 100, string $reason = ''): array {
        $params = self::validate_parameters(self::apply_mastery_state_rebuild_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'userid', 'limit', 'reason'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('apply_mastery_state_rebuild');
        return self::json_response(mastery_state_service::apply_rebuild(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['userid'],
            (int)$params['limit'],
            (string)$params['reason']
        ));
    }

    public static function apply_mastery_state_rebuild_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_retention_review_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_retention_review_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_retention_review_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(retention_review_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_retention_review_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_current_retention_state_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_current_retention_state(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_current_retention_state_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(retention_review_service::current_retention_state(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_current_retention_state_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_retention_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_retention_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_retention_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(retention_review_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_retention_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function preview_retention_review_rebuild_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Learner ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function preview_retention_review_rebuild(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $userid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::preview_retention_review_rebuild_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'userid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(retention_review_service::preview_rebuild(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['userid'],
            (int)$params['limit']
        ));
    }

    public static function preview_retention_review_rebuild_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_retention_review_rebuild_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Learner ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
            'reason' => new external_value(PARAM_TEXT, 'Operator reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function apply_retention_review_rebuild(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $userid = 0, int $limit = 100, string $reason = ''): array {
        $params = self::validate_parameters(self::apply_retention_review_rebuild_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'userid', 'limit', 'reason'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('apply_retention_review_rebuild');
        return self::json_response(retention_review_service::apply_rebuild(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['userid'],
            (int)$params['limit'],
            (string)$params['reason']
        ));
    }

    public static function apply_retention_review_rebuild_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learning_goal_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Goal sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learning_goal_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_learning_goal_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(learning_goal_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_learning_goal_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_current_learning_goal_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Version limit', VALUE_DEFAULT, 20),
        ]);
    }

    public static function get_current_learning_goal(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20): array {
        $params = self::validate_parameters(self::get_current_learning_goal_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(learning_goal_service::current_goal(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_current_learning_goal_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_learning_goal_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner goal limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_learning_goal_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_learning_goal_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(learning_goal_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_learning_goal_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learning_goal_options_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'query' => new external_value(PARAM_TEXT, 'Target search query', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Option limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learning_goal_options(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, string $query = '', int $limit = 100): array {
        $params = self::validate_parameters(self::get_learning_goal_options_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'query', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        if (!has_capability('local/flwcupkp:viewreports', $context)) {
            require_capability('local/flwcupkp:viewlearnerpath', $context);
        }
        return self::json_response(learning_goal_service::goal_options(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (string)$params['query'],
            (int)$params['limit']
        ));
    }

    public static function get_learning_goal_options_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function save_learning_goal_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'datajson' => new external_value(PARAM_RAW, 'Learning goal JSON payload'),
            'source' => new external_value(PARAM_ALPHA, 'STUDENT, TEACHER, or INSTITUTION', VALUE_DEFAULT, 'STUDENT'),
            'reason' => new external_value(PARAM_TEXT, 'Version reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function save_learning_goal(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, string $datajson = '', string $source = 'STUDENT', string $reason = ''): array {
        $params = self::validate_parameters(self::save_learning_goal_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'datajson', 'source', 'reason'));
        self::require_learning_goal_write_access((int)$params['userid'], (int)$params['courseid'],
            (string)$params['source']);
        self::assert_write_rate_limit('save_learning_goal');
        $data = self::decode_object_json((string)$params['datajson']);
        $data['courseid'] = (int)$params['courseid'];
        $data['unitcode'] = (string)$params['unitcode'];
        $data['frameworkid'] = (int)$params['frameworkid'];
        $data['source'] = (string)$params['source'];
        return self::json_response(learning_goal_service::save_goal(
            (int)$params['userid'],
            $data,
            (string)$params['source'],
            (string)$params['reason']
        ));
    }

    public static function save_learning_goal_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_placement_diagnostic_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_placement_diagnostic_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_placement_diagnostic_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(placement_diagnostic_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_placement_diagnostic_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_current_placement_diagnostic_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 20),
        ]);
    }

    public static function get_current_placement_diagnostic(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20): array {
        $params = self::validate_parameters(self::get_current_placement_diagnostic_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(placement_diagnostic_service::current_placement(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_current_placement_diagnostic_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_placement_diagnostic_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_placement_diagnostic_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_placement_diagnostic_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(placement_diagnostic_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_placement_diagnostic_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function preview_placement_diagnostic_reprocess_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Learner ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Placement fact limit', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Placement fact offset', VALUE_DEFAULT, 0),
        ]);
    }

    public static function preview_placement_diagnostic_reprocess(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $userid = 0, int $limit = 100, int $offset = 0): array {
        $params = self::validate_parameters(self::preview_placement_diagnostic_reprocess_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'userid', 'limit', 'offset'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(placement_diagnostic_service::preview_reprocess(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['userid'],
            (int)$params['limit'],
            (int)$params['offset']
        ));
    }

    public static function preview_placement_diagnostic_reprocess_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_placement_diagnostic_reprocess_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Learner ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Placement fact limit', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Placement fact offset', VALUE_DEFAULT, 0),
            'reason' => new external_value(PARAM_TEXT, 'Operator reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function apply_placement_diagnostic_reprocess(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $userid = 0, int $limit = 100, int $offset = 0, string $reason = ''): array {
        $params = self::validate_parameters(self::apply_placement_diagnostic_reprocess_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'userid', 'limit', 'offset', 'reason'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('apply_placement_diagnostic_reprocess');
        return self::json_response(placement_diagnostic_service::apply_reprocess(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['userid'],
            (int)$params['limit'],
            (int)$params['offset'],
            (string)$params['reason']
        ));
    }

    public static function apply_placement_diagnostic_reprocess_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_adaptive_decision_policy_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Decision sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_adaptive_decision_policy_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_adaptive_decision_policy_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(adaptive_decision_policy_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_adaptive_decision_policy_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_adaptive_decision_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'State row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learner_adaptive_decision(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_learner_adaptive_decision_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(adaptive_decision_policy_service::learner_decision(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_learner_adaptive_decision_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_adaptive_decision_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_adaptive_decision_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_adaptive_decision_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(adaptive_decision_policy_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_adaptive_decision_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_goal_gap_path_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Path sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_goal_gap_path_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_goal_gap_path_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(goal_gap_path_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_goal_gap_path_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_initial_path_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Requirement row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learner_initial_path(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_learner_initial_path_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(goal_gap_path_service::learner_path(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_learner_initial_path_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_initial_path_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_initial_path_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_initial_path_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(goal_gap_path_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_initial_path_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_candidate_activity_resolution_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Resolution sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_candidate_activity_resolution_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_candidate_activity_resolution_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(candidate_activity_resolution_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_candidate_activity_resolution_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_activity_resolution_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Candidate/activity row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learner_activity_resolution(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_learner_activity_resolution_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(candidate_activity_resolution_service::learner_resolution(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_learner_activity_resolution_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_activity_resolution_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_activity_resolution_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_activity_resolution_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(candidate_activity_resolution_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_activity_resolution_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_adaptive_path_engine_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Status sample limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_adaptive_path_engine_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_adaptive_path_engine_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(adaptive_path_engine_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_adaptive_path_engine_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_adaptive_path_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Candidate/activity row limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learner_adaptive_path(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_learner_adaptive_path_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(adaptive_path_engine_service::learner_path(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_learner_adaptive_path_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_learner_adaptive_path_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Candidate/activity row limit', VALUE_DEFAULT, 100),
            'reason' => new external_value(PARAM_TEXT, 'Controlled refresh reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function apply_learner_adaptive_path(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100, string $reason = ''): array {
        $params = self::validate_parameters(self::apply_learner_adaptive_path_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit', 'reason'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], true);
        self::assert_write_rate_limit('apply_learner_adaptive_path');
        return self::json_response(adaptive_path_engine_service::apply_learner_path(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit'],
            (string)$params['reason']
        ));
    }

    public static function apply_learner_adaptive_path_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_adaptive_path_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_adaptive_path_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_adaptive_path_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(adaptive_path_engine_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_adaptive_path_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_class_adaptive_paths_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
            'reason' => new external_value(PARAM_TEXT, 'Controlled class refresh reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function apply_class_adaptive_paths(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100, string $reason = ''): array {
        $params = self::validate_parameters(self::apply_class_adaptive_paths_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit', 'reason'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:override', $context);
        self::assert_write_rate_limit('apply_class_adaptive_paths');
        return self::json_response(adaptive_path_engine_service::apply_class_paths(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit'],
            (string)$params['reason']
        ));
    }

    public static function apply_class_adaptive_paths_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_trajectory_simulation_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_trajectory_simulation_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0): array {
        $params = self::validate_parameters(self::get_trajectory_simulation_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(trajectory_invariant_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid']
        ));
    }

    public static function get_trajectory_simulation_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function run_trajectory_simulation_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID for access scope', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code for access scope', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID for access scope', VALUE_DEFAULT, 0),
            'seed' => new external_value(PARAM_TEXT, 'Deterministic simulation seed', VALUE_DEFAULT,
                'flw-cupkp-a5b-v1'),
            'trajectorycount' => new external_value(PARAM_INT, 'Trajectory count, maximum 2000', VALUE_DEFAULT, 512),
            'steps' => new external_value(PARAM_INT, 'Steps per trajectory, maximum 100', VALUE_DEFAULT, 24),
            'scenariosjson' => new external_value(PARAM_RAW, 'Scenario names as a JSON list', VALUE_DEFAULT, '[]'),
            'samplelimit' => new external_value(PARAM_INT, 'Returned sample limit, maximum 20', VALUE_DEFAULT, 8),
        ]);
    }

    public static function run_trajectory_simulation(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, string $seed = 'flw-cupkp-a5b-v1', int $trajectorycount = 512,
            int $steps = 24, string $scenariosjson = '[]', int $samplelimit = 8): array {
        $params = self::validate_parameters(self::run_trajectory_simulation_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'seed', 'trajectorycount', 'steps',
                'scenariosjson', 'samplelimit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        $scenarios = self::decode_json_array((string)$params['scenariosjson']);
        foreach ($scenarios as $scenario) {
            if (!is_string($scenario)) {
                throw new \invalid_parameter_exception('Every simulation scenario must be a string.');
            }
        }
        return self::json_response(trajectory_invariant_service::simulate_suite(
            (string)$params['seed'],
            (int)$params['trajectorycount'],
            (int)$params['steps'],
            $scenarios,
            (int)$params['samplelimit']
        ));
    }

    public static function run_trajectory_simulation_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_trajectory_projection_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'seed' => new external_value(PARAM_TEXT, 'Deterministic simulation seed', VALUE_DEFAULT,
                'flw-cupkp-a5b-v1'),
            'steps' => new external_value(PARAM_INT, 'Projection step count, maximum 100', VALUE_DEFAULT, 24),
        ]);
    }

    public static function get_learner_trajectory_projection(int $userid, int $courseid = 0,
            string $unitcode = '', int $frameworkid = 0, string $seed = 'flw-cupkp-a5b-v1',
            int $steps = 24): array {
        $params = self::validate_parameters(self::get_learner_trajectory_projection_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'seed', 'steps'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(trajectory_invariant_service::learner_projection(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (string)$params['seed'],
            (int)$params['steps']
        ));
    }

    public static function get_learner_trajectory_projection_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_progress_readiness_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_progress_readiness_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0): array {
        $params = self::validate_parameters(self::get_progress_readiness_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(progress_goal_readiness_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid']
        ));
    }

    public static function get_progress_readiness_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_progress_readiness_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Requirement limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_learner_progress_readiness(int $userid, int $courseid = 0,
            string $unitcode = '', int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_learner_progress_readiness_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        return self::json_response(progress_goal_readiness_service::learner_progress(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_learner_progress_readiness_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_class_progress_readiness_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Learner limit', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_class_progress_readiness_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_class_progress_readiness_summary_parameters(),
            compact('courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(progress_goal_readiness_service::class_summary(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit']
        ));
    }

    public static function get_class_progress_readiness_summary_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learning_timeline_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_learning_timeline_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0): array {
        $params = self::validate_parameters(self::get_learning_timeline_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(student_learning_timeline_view_service::status(
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid']
        ));
    }

    public static function get_learning_timeline_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_student_learning_timeline_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Per-panel limit', VALUE_DEFAULT, 10),
            'attemptoffset' => new external_value(PARAM_INT, 'Attempt history offset', VALUE_DEFAULT, 0),
            'gradeoffset' => new external_value(PARAM_INT, 'Grade history offset', VALUE_DEFAULT, 0),
            'historyoffset' => new external_value(PARAM_INT, 'Learning history offset', VALUE_DEFAULT, 0),
            'activityoffset' => new external_value(PARAM_INT, 'Recent activity offset', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_student_learning_timeline(int $userid, int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 10, int $attemptoffset = 0, int $gradeoffset = 0,
            int $historyoffset = 0, int $activityoffset = 0): array {
        $params = self::validate_parameters(self::get_student_learning_timeline_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit', 'attemptoffset',
                'gradeoffset', 'historyoffset', 'activityoffset'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        $historyservice = '\\local_flwhistory\\local\\dashboard_service';
        if (!class_exists($historyservice) || !method_exists($historyservice, 'require_learner_access')) {
            throw new \moodle_exception('timelinehistoryunavailable', 'local_flwcupkp');
        }
        $historyservice::require_learner_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(student_learning_timeline_view_service::learner_timeline(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            (int)$params['limit'],
            [
                'attemptoffset' => (int)$params['attemptoffset'],
                'gradeoffset' => (int)$params['gradeoffset'],
                'historyoffset' => (int)$params['historyoffset'],
                'activityoffset' => (int)$params['activityoffset'],
            ]
        ));
    }

    public static function get_student_learning_timeline_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_learner_experience_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_learner_experience_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0): array {
        $params = self::validate_parameters(self::get_learner_experience_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(learner_experience_service::status(
            (int)$params['courseid'], (string)$params['unitcode'], (int)$params['frameworkid']
        ));
    }

    public static function get_learner_experience_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_simplified_learner_experience_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'History input limit', VALUE_DEFAULT, 20),
        ]);
    }

    public static function get_simplified_learner_experience(int $userid, int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20): array {
        $params = self::validate_parameters(self::get_simplified_learner_experience_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        self::require_learner_evaluation_access((int)$params['userid'], (int)$params['courseid'], false);
        $historyservice = '\\local_flwhistory\\local\\dashboard_service';
        if (!class_exists($historyservice) || !method_exists($historyservice, 'require_learner_access')) {
            throw new \moodle_exception('timelinehistoryunavailable', 'local_flwcupkp');
        }
        $historyservice::require_learner_access((int)$params['courseid'], (int)$params['userid']);
        return self::json_response(learner_experience_service::learner_experience(
            (int)$params['userid'],
            (int)$params['courseid'],
            (string)$params['unitcode'],
            (int)$params['frameworkid'],
            max(1, min(50, (int)$params['limit']))
        ));
    }

    public static function get_simplified_learner_experience_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_staff_intelligence_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_staff_intelligence_status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0): array {
        $params = self::validate_parameters(self::get_staff_intelligence_status_parameters(),
            compact('courseid', 'unitcode', 'frameworkid'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(staff_intelligence_service::status(
            (int)$params['courseid'], (string)$params['unitcode'], (int)$params['frameworkid']
        ));
    }

    public static function get_staff_intelligence_status_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function get_staff_intelligence_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum detail rows', VALUE_DEFAULT, 100),
        ]);
    }

    public static function get_staff_intelligence(int $userid, int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::get_staff_intelligence_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'limit'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:viewreports', $context);
        return self::json_response(staff_intelligence_service::learner_intelligence(
            (int)$params['userid'], (int)$params['courseid'], (string)$params['unitcode'],
            (int)$params['frameworkid'], (int)$params['limit']
        ));
    }

    public static function get_staff_intelligence_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function apply_staff_intervention_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'unitcode' => new external_value(PARAM_ALPHANUMEXT, 'Unit code', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'interventiontype' => new external_value(PARAM_ALPHANUMEXT, 'Intervention type'),
            'datajson' => new external_value(PARAM_RAW, 'Intervention fields as a JSON object', VALUE_DEFAULT, '{}'),
            'reason' => new external_value(PARAM_TEXT, 'Required staff reason'),
        ]);
    }

    public static function apply_staff_intervention(int $userid, int $courseid, string $unitcode,
            int $frameworkid, string $interventiontype, string $datajson, string $reason): array {
        $params = self::validate_parameters(self::apply_staff_intervention_parameters(),
            compact('userid', 'courseid', 'unitcode', 'frameworkid', 'interventiontype', 'datajson', 'reason'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:override', $context);
        self::assert_write_rate_limit('apply_staff_intervention');
        return self::json_response(staff_intelligence_service::apply_intervention(
            (int)$params['userid'], (int)$params['courseid'], (string)$params['unitcode'],
            (int)$params['frameworkid'], (string)$params['interventiontype'],
            self::decode_object_json((string)$params['datajson']), (string)$params['reason']
        ));
    }

    public static function apply_staff_intervention_returns(): external_single_structure {
        return self::json_returns();
    }

    public static function release_staff_intervention_parameters(): external_function_parameters {
        return new external_function_parameters([
            'interventionid' => new external_value(PARAM_INT, 'Latest active intervention ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'reason' => new external_value(PARAM_TEXT, 'Required release reason'),
        ]);
    }

    public static function release_staff_intervention(int $interventionid, int $courseid,
            int $frameworkid, string $reason): array {
        $params = self::validate_parameters(self::release_staff_intervention_parameters(),
            compact('interventionid', 'courseid', 'frameworkid', 'reason'));
        $context = self::evaluation_context((int)$params['courseid']);
        self::validate_context($context);
        require_capability('local/flwcupkp:override', $context);
        self::assert_write_rate_limit('release_staff_intervention');
        return self::json_response(staff_intelligence_service::release_intervention(
            (int)$params['interventionid'], (string)$params['reason'], (int)$params['frameworkid']
        ));
    }

    public static function release_staff_intervention_returns(): external_single_structure {
        return self::json_returns();
    }

    private static function list_entity_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'query' => new external_value(PARAM_TEXT, 'Search query', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Limit', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Offset', VALUE_DEFAULT, 0),
        ]);
    }

    private static function save_entity_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Record ID', VALUE_DEFAULT, 0),
            'datajson' => new external_value(PARAM_RAW, 'Entity data as JSON object'),
        ]);
    }

    private static function report_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Limit', VALUE_DEFAULT, 100),
        ]);
    }

    private static function list_entity(string $type, int $frameworkid, string $query, int $limit, int $offset): array {
        self::validate_parameters(self::list_entity_parameters(), compact('frameworkid', 'query', 'limit', 'offset'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:viewreports', context_system::instance());
        $rows = curriculum_manager::list_entities($type, $frameworkid, $query);
        return self::json_response([
            'type' => $type,
            'total' => count($rows),
            'records' => array_slice(array_values($rows), max(0, $offset), max(1, min(500, $limit))),
        ]);
    }

    private static function save_entity(string $type, int $id, string $datajson): array {
        self::validate_parameters(self::save_entity_parameters(), compact('id', 'datajson'));
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:manageframeworks', context_system::instance());
        self::assert_write_rate_limit('save_entity');
        $data = self::decode_object_json($datajson);
        if ($id > 0) {
            $data['id'] = $id;
        }
        $recordid = curriculum_manager::save_entity($type, $data);
        return self::json_response(['id' => $recordid, 'status' => 'saved']);
    }

    private static function decode_object_json(string $json): array {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \invalid_parameter_exception('JSON object payload is required.');
        }
        return $data;
    }

    private static function decode_json_array(string $json): array {
        $data = json_decode($json, true);
        if ($json !== '' && !is_array($data)) {
            throw new \invalid_parameter_exception('JSON array payload is required.');
        }
        if (!$data) {
            return [];
        }
        if (array_keys($data) !== range(0, count($data) - 1)) {
            throw new \invalid_parameter_exception('JSON array payload is required.');
        }
        return $data;
    }

    private static function json_returns(): external_single_structure {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'JSON response payload'),
        ]);
    }

    private static function json_response(array $payload): array {
        return ['json' => json_encode($payload, JSON_UNESCAPED_SLASHES)];
    }

    private static function evaluation_context(int $courseid): \context {
        if ($courseid <= 0) {
            return context_system::instance();
        }
        return \context_course::instance($courseid, IGNORE_MISSING) ?: context_system::instance();
    }

    private static function require_learner_evaluation_access(int $userid, int $courseid, bool $write): void {
        global $USER;

        $context = self::evaluation_context($courseid);
        self::validate_context($context);

        if ((int)($USER->id ?? 0) === $userid) {
            require_capability('local/flwcupkp:viewlearnerpath', $context);
            return;
        }

        require_capability($write ? 'local/flwcupkp:override' : 'local/flwcupkp:viewreports', $context);
    }

    private static function require_learning_goal_write_access(int $userid, int $courseid, string $source): void {
        global $USER;

        $context = self::evaluation_context($courseid);
        $systemcontext = context_system::instance();
        self::validate_context($context);
        $source = learning_goal_service::normalize_source($source);

        if ($source === 'INSTITUTION') {
            require_capability('local/flwcupkp:manageframeworks', $systemcontext);
            return;
        }

        if ((int)($USER->id ?? 0) !== $userid || $source === 'TEACHER') {
            if (has_capability('local/flwcupkp:manageframeworks', $systemcontext)) {
                return;
            }
            require_capability('local/flwcupkp:override', $context);
            return;
        }

        require_capability('local/flwcupkp:viewlearnerpath', $context);
    }

    /**
     * Session-scoped rate limit for external write calls.
     *
     * @param string $action
     */
    private static function assert_write_rate_limit(string $action): void {
        global $USER;

        $userid = (int)($USER->id ?? 0);
        $bucket = (int)floor(time() / 60);
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($action)) . '_' . $userid . '_' . $bucket;
        $cache = \cache::make('local_flwcupkp', 'externalwrites');
        $count = (int)($cache->get($key) ?: 0);
        if ($count >= 120) {
            throw new \moodle_exception('ratelimitexceeded', 'local_flwcupkp');
        }
        $cache->set($key, $count + 1);
    }

    private static function orphans_report(int $frameworkid, int $limit): array {
        global $DB;

        $frameworksql = $frameworkid > 0 ? ' AND c.frameworkid = :frameworkid' : '';
        $params = $frameworkid > 0 ? ['frameworkid' => $frameworkid] : [];
        $competencies = $DB->get_records_sql(
            "SELECT c.id, c.externalid, c.title
               FROM {flwcupkp_comp} c
          LEFT JOIN {flwcupkp_comp_up} m ON m.competencyid = c.id
              WHERE m.id IS NULL{$frameworksql}
           ORDER BY c.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        $frameworksql = $frameworkid > 0 ? ' AND u.frameworkid = :frameworkid' : '';
        $ups = $DB->get_records_sql(
            "SELECT u.id, u.externalid, u.title
               FROM {flwcupkp_up} u
          LEFT JOIN {flwcupkp_up_kp} m ON m.upid = u.id
              WHERE m.id IS NULL{$frameworksql}
           ORDER BY u.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        $frameworksql = $frameworkid > 0 ? ' AND kp.frameworkid = :frameworkid' : '';
        $kps = $DB->get_records_sql(
            "SELECT kp.id, kp.externalid, kp.title
               FROM {flwcupkp_kp} kp
          LEFT JOIN {flwcupkp_object_map} m ON m.targettype = 'kp' AND m.targetid = kp.id
              WHERE m.id IS NULL{$frameworksql}
           ORDER BY kp.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        $objects = $DB->get_records_sql(
            "SELECT o.id, o.externalid, o.title
               FROM {flwcupkp_object} o
          LEFT JOIN {flwcupkp_object_map} m ON m.objectid = o.id
              WHERE m.id IS NULL" . ($frameworkid > 0 ? ' AND o.frameworkid = :frameworkid' : '') . "
           ORDER BY o.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        return [
            'frameworkid' => $frameworkid,
            'competencies_without_use_points' => array_values($competencies),
            'use_points_without_knowledge_points' => array_values($ups),
            'knowledge_points_without_learning_objects' => array_values($kps),
            'learning_objects_without_targets' => array_values($objects),
        ];
    }

    private static function evidence_gaps_report(int $frameworkid, int $limit): array {
        global $DB;

        $params = $frameworkid > 0 ? ['frameworkid' => $frameworkid] : [];
        $frameworksql = $frameworkid > 0 ? ' AND c.frameworkid = :frameworkid' : '';
        $competencies = $DB->get_records_sql(
            "SELECT c.id, c.externalid, c.title
               FROM {flwcupkp_comp} c
          LEFT JOIN {flwcupkp_evidence} e ON e.targettype = 'competency'
                    AND e.targetid = c.id
                    AND e.evidencestrength IN ('guided_performance','independent_performance','transfer_performance')
              WHERE e.id IS NULL{$frameworksql}
           ORDER BY c.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        $frameworksql = $frameworkid > 0 ? ' AND u.frameworkid = :frameworkid' : '';
        $ups = $DB->get_records_sql(
            "SELECT u.id, u.externalid, u.title
               FROM {flwcupkp_up} u
          LEFT JOIN {flwcupkp_evidence} e ON e.targettype = 'up' AND e.targetid = u.id
              WHERE e.id IS NULL{$frameworksql}
           ORDER BY u.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        $frameworksql = $frameworkid > 0 ? ' AND kp.frameworkid = :frameworkid' : '';
        $kps = $DB->get_records_sql(
            "SELECT kp.id, kp.externalid, kp.title
               FROM {flwcupkp_kp} kp
          LEFT JOIN {flwcupkp_evidence} e ON e.targettype = 'kp' AND e.targetid = kp.id
              WHERE e.id IS NULL{$frameworksql}
           ORDER BY kp.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        return [
            'frameworkid' => $frameworkid,
            'competencies_without_direct_performance_evidence' => array_values($competencies),
            'use_points_without_direct_evidence' => array_values($ups),
            'knowledge_points_without_evidence' => array_values($kps),
        ];
    }

    private static function cefr_alignment_report(int $frameworkid, int $limit): array {
        global $DB;

        $params = $frameworkid > 0 ? ['frameworkid' => $frameworkid] : [];
        $frameworksql = $frameworkid > 0 ? ' AND c.frameworkid = :frameworkid' : '';
        $compup = $DB->get_records_sql(
            "SELECT m.id, c.externalid AS competency, c.cefr AS competency_cefr,
                    u.externalid AS use_point, u.cefr AS use_point_cefr
               FROM {flwcupkp_comp_up} m
               JOIN {flwcupkp_comp} c ON c.id = m.competencyid
               JOIN {flwcupkp_up} u ON u.id = m.upid
              WHERE c.cefr <> '' AND u.cefr <> '' AND c.cefr <> u.cefr{$frameworksql}
           ORDER BY c.externalid ASC, u.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        $frameworksql = $frameworkid > 0 ? ' AND u.frameworkid = :frameworkid' : '';
        $upkp = $DB->get_records_sql(
            "SELECT m.id, u.externalid AS use_point, u.cefr AS use_point_cefr,
                    kp.externalid AS knowledge_point, kp.cefr AS knowledge_point_cefr
               FROM {flwcupkp_up_kp} m
               JOIN {flwcupkp_up} u ON u.id = m.upid
               JOIN {flwcupkp_kp} kp ON kp.id = m.kpid
              WHERE u.cefr <> '' AND kp.cefr <> '' AND u.cefr <> kp.cefr{$frameworksql}
           ORDER BY u.externalid ASC, kp.externalid ASC",
            $params,
            0,
            max(1, $limit)
        );

        return [
            'frameworkid' => $frameworkid,
            'competency_use_point_mismatches' => array_values($compup),
            'use_point_knowledge_point_mismatches' => array_values($upkp),
        ];
    }
}

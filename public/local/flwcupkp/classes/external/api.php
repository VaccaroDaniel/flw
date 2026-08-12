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
use local_flwcupkp\local\audit_service;
use local_flwcupkp\local\curriculum_manager;
use local_flwcupkp\local\import_service;
use local_flwcupkp\local\mastery_engine;
use local_flwcupkp\local\moodle_competency_writer;
use local_flwcupkp\local\recommendation_engine;
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

    private static function json_returns(): external_single_structure {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'JSON response payload'),
        ]);
    }

    private static function json_response(array $payload): array {
        return ['json' => json_encode($payload, JSON_UNESCAPED_SLASHES)];
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

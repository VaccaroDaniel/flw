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

    public static function sync_moodle_competencies_parameters(): external_function_parameters {
        return new external_function_parameters(['dryrun' => new external_value(PARAM_BOOL, 'Dry run', VALUE_DEFAULT, true)]);
    }

    public static function sync_moodle_competencies(bool $dryrun = true): array {
        self::validate_parameters(self::sync_moodle_competencies_parameters(), ['dryrun' => $dryrun]);
        self::validate_context(context_system::instance());
        require_capability('local/flwcupkp:synccompetencies', context_system::instance());
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
}

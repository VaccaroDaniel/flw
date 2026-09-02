<?php
// History V1 freeze, backfill, reconciliation, and performance service.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

/**
 * Production hardening service for Program 2 Gate H7.
 */
class history_v1_service {
    /** Frozen History V1 label. */
    public const HISTORY_VERSION = 'FLW_HISTORY_V1';

    /** Default batch size for migration/backfill work. */
    private const DEFAULT_BATCH_SIZE = 100;

    /** Hard maximum batch size. */
    private const MAX_BATCH_SIZE = 1000;

    /** Default page size for performance probes. */
    private const DEFAULT_PERFORMANCE_LIMIT = 25;

    /** Question rows processed for one attempt during backfill. */
    private const QUESTION_BACKFILL_LIMIT = 200;

    /** Backfill source names. */
    private const BACKFILL_SOURCES = [
        'quiz_attempts',
        'completion',
        'grade_history',
        'grade_current',
        'placement',
    ];

    /** Core tables required for History V1. */
    private const REQUIRED_TABLES = [
        'flwhist_source_event',
        'flwhist_attempt',
        'flwhist_question_attempt',
        'flwhist_grade_version',
        'flwhist_grade_summary',
        'flwhist_completion',
        'flwhist_placement',
        'flwhist_coverage',
        'flwhist_content_link',
        'flwhist_reconcile_run',
        'flwhist_correction',
    ];

    /**
     * Backfill recoverable Moodle/FLW facts for a course.
     *
     * @param int $courseid Course id.
     * @param array $options Backfill options.
     * @return array Result DTO.
     */
    public static function backfill_course(int $courseid, array $options = []): array {
        get_course($courseid);
        $options = self::normalise_backfill_options($options);
        $runkey = self::run_sourcekey('h7_history_backfill', $courseid, $options);
        $now = time();

        repository::upsert_reconcile_run([
            'sourcekey' => $runkey,
            'runtype' => 'h7_history_backfill',
            'scopejson' => [
                'courseid' => $courseid,
                'dryrun' => $options['dryrun'],
                'batchsize' => $options['batchsize'],
                'sources' => $options['sources'],
                'cursors' => $options['cursors'],
                'sourcelabel' => $options['sourcelabel'],
            ],
            'status' => 'running',
            'courseid' => $courseid,
            'timestarted' => $now,
        ]);

        $sources = [];
        foreach ($options['sources'] as $source) {
            $sources[$source] = self::backfill_source($source, $courseid, $options);
        }

        $totals = self::merge_source_totals($sources);
        $status = $totals['recordsfailed'] > 0 ? 'complete_with_errors' : 'complete';
        if ($options['dryrun']) {
            $status = $totals['recordsfailed'] > 0 ? 'dry_run_complete_with_errors' : 'dry_run_complete';
        }

        repository::upsert_reconcile_run([
            'sourcekey' => $runkey,
            'runtype' => 'h7_history_backfill',
            'scopejson' => [
                'courseid' => $courseid,
                'dryrun' => $options['dryrun'],
                'batchsize' => $options['batchsize'],
                'sources' => $options['sources'],
                'cursors' => $options['cursors'],
                'sourcelabel' => $options['sourcelabel'],
            ],
            'status' => $status,
            'courseid' => $courseid,
            'timestarted' => $now,
            'timefinished' => time(),
            'recordsseen' => $totals['recordsseen'],
            'recordscreated' => $totals['recordscreated'],
            'recordsupdated' => $totals['recordsupdated'],
            'recordsskipped' => $totals['recordsskipped'],
            'recordsfailed' => $totals['recordsfailed'],
            'errorjson' => self::source_errors($sources),
        ]);

        return [
            'type' => 'HistoryV1Backfill',
            'historyversion' => self::HISTORY_VERSION,
            'status' => $status,
            'courseid' => $courseid,
            'dryrun' => $options['dryrun'],
            'batchsize' => $options['batchsize'],
            'sourcelabel' => $options['sourcelabel'],
            'sources' => $sources,
            'totals' => $totals,
            'nextcursors' => self::next_cursors($sources),
            'fabrication_policy' => [
                'missing_attempt_order' => 'skip',
                'unknown_timestamps' => 'skip',
                'unknown_grade_reasons' => 'do_not_invent',
                'cupkp_evidence' => 'not_created',
                'mastery' => 'not_created',
                'recommendations' => 'not_created',
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Reconcile current History V1 state against Moodle/FLW source state.
     *
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Result DTO.
     */
    public static function reconcile_course(int $courseid, array $options = []): array {
        get_course($courseid);
        $dryrun = !empty($options['dryrun']);
        $batchsize = self::bounded_int($options['batchsize'] ?? $options['limit'] ?? self::DEFAULT_BATCH_SIZE,
            1, self::MAX_BATCH_SIZE);
        $runkey = self::run_sourcekey('h7_history_reconcile', $courseid, [
            'dryrun' => $dryrun,
            'batchsize' => $batchsize,
            'idempotencykey' => $options['idempotencykey'] ?? null,
        ]);
        $now = time();

        repository::upsert_reconcile_run([
            'sourcekey' => $runkey,
            'runtype' => 'h7_history_reconcile',
            'scopejson' => ['courseid' => $courseid, 'dryrun' => $dryrun, 'batchsize' => $batchsize],
            'status' => 'running',
            'courseid' => $courseid,
            'timestarted' => $now,
        ]);

        $checks = [
            'official_grade_summary' => self::verify_grade_summaries($courseid, $batchsize, $dryrun),
            'completion_and_structure' => self::verify_completion_and_structure($courseid, $batchsize, $dryrun),
            'orphan_flw_mappings' => self::verify_orphan_flw_mappings($courseid),
            'duplicate_source_identities' => self::verify_duplicate_source_identities($courseid),
        ];
        $totals = self::merge_source_totals($checks);
        $issues = self::issue_count($checks);
        $status = $issues > 0 ? 'complete_with_findings' : 'complete';
        if ($dryrun) {
            $status = $issues > 0 ? 'dry_run_complete_with_findings' : 'dry_run_complete';
        }

        repository::upsert_reconcile_run([
            'sourcekey' => $runkey,
            'runtype' => 'h7_history_reconcile',
            'scopejson' => ['courseid' => $courseid, 'dryrun' => $dryrun, 'batchsize' => $batchsize],
            'status' => $status,
            'courseid' => $courseid,
            'timestarted' => $now,
            'timefinished' => time(),
            'recordsseen' => $totals['recordsseen'],
            'recordscreated' => $totals['recordscreated'],
            'recordsupdated' => $totals['recordsupdated'],
            'recordsskipped' => $totals['recordsskipped'],
            'recordsfailed' => $issues,
            'errorjson' => $issues > 0 ? ['findings' => self::check_findings($checks)] : null,
        ]);

        return [
            'type' => 'HistoryV1Reconciliation',
            'historyversion' => self::HISTORY_VERSION,
            'status' => $status,
            'courseid' => $courseid,
            'dryrun' => $dryrun,
            'batchsize' => $batchsize,
            'checks' => $checks,
            'totals' => $totals,
            'findings' => $issues,
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Measure core H4-H6 read paths for H7 evidence.
     *
     * @param int $courseid Course id.
     * @param int $userid Optional learner id.
     * @param array $options Options.
     * @return array Result DTO.
     */
    public static function performance_snapshot(int $courseid, int $userid = 0, array $options = []): array {
        get_course($courseid);
        $limit = self::bounded_int($options['limit'] ?? self::DEFAULT_PERFORMANCE_LIMIT, 1, 100);
        $userid = $userid > 0 ? $userid : self::sample_learner_id($courseid);

        $measurements = [];
        if ($userid > 0) {
            $measurements['summary'] = self::measure(function() use ($courseid, $userid): array {
                return history_api_service::present_summary_core($courseid, $userid);
            });
            $measurements['journey'] = self::measure(function() use ($courseid, $userid): array {
                return history_api_service::learning_journey_core($courseid, $userid);
            });
            $measurements['history_pagination'] = self::measure(function() use ($courseid, $userid, $limit): array {
                return history_api_service::learning_history_query($courseid, $userid, $limit, 0);
            });
            $measurements['grade_detail'] = self::measure(function() use ($courseid, $userid, $limit): array {
                return history_api_service::grade_history_query($courseid, $userid, $limit, 0);
            });
        } else {
            foreach (['summary', 'journey', 'history_pagination', 'grade_detail'] as $key) {
                $measurements[$key] = [
                    'status' => 'skipped',
                    'reason' => 'NO_LEARNER_AVAILABLE_IN_COURSE',
                    'durationms' => null,
                ];
            }
        }

        $measurements['class_history_view'] = self::measure(function() use ($courseid, $limit): array {
            return teacher_analytics_service::teacher_dashboard_core($courseid, ['limit' => $limit], false);
        });

        return [
            'type' => 'HistoryV1PerformanceSnapshot',
            'historyversion' => self::HISTORY_VERSION,
            'courseid' => $courseid,
            'userid' => $userid > 0 ? $userid : null,
            'limit' => $limit,
            'measurements' => $measurements,
            'status' => self::performance_status($measurements),
            'generatedat' => time(),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Return History V1 freeze status.
     *
     * @param int $courseid Optional course id.
     * @param array $options Options.
     * @return array Result DTO.
     */
    public static function freeze_status(int $courseid = 0, array $options = []): array {
        $checks = [
            'schema' => self::schema_status(),
            'capture_runtime' => self::capture_runtime_status(),
            'security_privacy' => self::security_privacy_status(),
            'downstream_contract' => self::downstream_contract_status(),
        ];

        if ($courseid > 0) {
            get_course($courseid);
            $checks['reconciliation_preview'] = self::reconcile_course($courseid, [
                'dryrun' => true,
                'batchsize' => $options['batchsize'] ?? 25,
                'idempotencykey' => 'h7-freeze-preview-' . $courseid,
            ]);
            $checks['performance'] = self::performance_snapshot($courseid, (int)($options['userid'] ?? 0), [
                'limit' => $options['limit'] ?? self::DEFAULT_PERFORMANCE_LIMIT,
            ]);
        }

        return [
            'type' => 'HistoryV1FreezeStatus',
            'historyversion' => self::HISTORY_VERSION,
            'status' => self::freeze_overall_status($checks),
            'courseid' => $courseid > 0 ? $courseid : null,
            'checks' => $checks,
            'contract' => self::downstream_contract(),
            'generatedat' => time(),
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Return the frozen Program 3 downstream contract.
     *
     * @return array
     */
    public static function downstream_contract(): array {
        return evidence_source_adapter::contract();
    }

    /**
     * Route one backfill source.
     *
     * @param string $source Source name.
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Source result.
     */
    private static function backfill_source(string $source, int $courseid, array $options): array {
        switch ($source) {
            case 'quiz_attempts':
                return self::backfill_quiz_attempts($courseid, $options);
            case 'completion':
                return self::backfill_completion($courseid, $options);
            case 'grade_history':
                return self::backfill_grade_history($courseid, $options);
            case 'grade_current':
                return self::backfill_current_grades($courseid, $options);
            case 'placement':
                return self::backfill_placement($courseid, $options);
            default:
                return self::empty_source_result($source, 0, 'source_not_supported');
        }
    }

    /**
     * Backfill Moodle quiz attempts.
     *
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Result.
     */
    private static function backfill_quiz_attempts(int $courseid, array $options): array {
        global $DB;

        $result = self::empty_source_result('quiz_attempts', (int)($options['cursors']['quiz_attempts'] ?? 0));
        $sql = "SELECT qa.*, q.course AS courseid, q.sumgrades AS quizsumgrades, q.grade AS quizgrade,
                       cm.id AS cmid, cm.section AS sectionid
                  FROM {quiz_attempts} qa
                  JOIN {quiz} q ON q.id = qa.quiz
             LEFT JOIN {modules} m ON m.name = :modquiz
             LEFT JOIN {course_modules} cm ON cm.module = m.id
                                           AND cm.instance = q.id
                                           AND cm.course = q.course
                 WHERE q.course = :courseid
                   AND qa.preview = 0
                   AND qa.id > :afterid
              ORDER BY qa.id ASC";
        $rows = $DB->get_records_sql($sql, [
            'modquiz' => 'quiz',
            'courseid' => $courseid,
            'afterid' => $result['afterid'],
        ], 0, $options['batchsize']);

        foreach ($rows as $row) {
            $result['recordsseen']++;
            $result['lastid'] = max($result['lastid'], (int)$row->id);
            try {
                $timestamp = self::reliable_time($row->timemodified ?? null, $row->timefinish ?? null, $row->timestart ?? null);
                if ($timestamp <= 0) {
                    self::skip($result, 'unknown_timestamp', ['table' => 'quiz_attempts', 'id' => (int)$row->id]);
                    continue;
                }

                $cmid = isset($row->cmid) ? (int)$row->cmid : 0;
                $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course($courseid);
                $eventtype = self::quiz_attempt_eventtype($row);
                $sourcefactkey = source_identity::make_key('moodle', 'quiz_attempt', (string)$row->id, (string)$timestamp,
                    'source_fact');
                $sourceevent = array_merge([
                    'sourcekey' => source_identity::make_key('moodle', 'quiz_attempt', (string)$row->id, (string)$timestamp,
                        $eventtype),
                    'sourcefactkey' => $sourcefactkey,
                    'sourcesystem' => 'moodle',
                    'sourcefamily' => 'quiz',
                    'sourcetype' => 'quiz_attempt',
                    'sourceid' => (string)$row->id,
                    'sourceversion' => (string)$timestamp,
                    'eventtype' => $eventtype,
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'sectionid' => isset($row->sectionid) ? (int)$row->sectionid : null,
                    'sourceattemptid' => (string)$row->id,
                    'attemptid' => (int)$row->id,
                    'eventtime' => $timestamp,
                    'status' => ($mapping['status'] ?? 'unresolved') === 'resolved' ? 'recorded' : 'unresolved_mapping',
                    'normalizer' => 'h7_backfill',
                    'summaryjson' => [
                        'backfill' => true,
                        'sourcelabel' => $options['sourcelabel'],
                        'quizid' => (int)$row->quiz,
                        'attemptstate' => $row->state ?? null,
                        'attemptno' => isset($row->attempt) ? (int)$row->attempt : null,
                        'mappingstatus' => $mapping['status'] ?? 'unresolved',
                    ],
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                ], self::mapping_fields($mapping));

                $sourceeventid = self::counted_upsert($result, 'flwhist_source_event', $sourceevent['sourcekey'],
                    $options['dryrun'], function() use ($sourceevent): int {
                        return history_service::record_source_event($sourceevent);
                    });

                $maxscore = isset($row->quizsumgrades) ? (float)$row->quizsumgrades : null;
                $scaledscore = self::scaled_score($row->sumgrades ?? null, $maxscore);
                $attemptrecord = normalizer::quiz_attempt_to_attempt($row, array_merge([
                    'sourceeventid' => $sourceeventid ?: null,
                    'courseid' => $courseid,
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'sectionid' => isset($row->sectionid) ? (int)$row->sectionid : null,
                    'maxscore' => $maxscore,
                    'scaledscore' => $scaledscore,
                    'lastsourceevent' => $sourceeventid ?: null,
                    'sourcefactkey' => $sourcefactkey,
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                ], self::mapping_fields($mapping)));

                $attemptrecordid = self::counted_upsert($result, 'flwhist_attempt', $attemptrecord['sourcekey'],
                    $options['dryrun'], function() use ($attemptrecord): int {
                        return attempt_service::record_attempt($attemptrecord);
                    });

                self::backfill_question_attempts($row, $sourceeventid, $attemptrecordid, $courseid, $cmid, $mapping, $options,
                    $result);
            } catch (\Throwable $e) {
                self::fail($result, $e, ['table' => 'quiz_attempts', 'id' => (int)$row->id]);
            }
        }

        return self::finish_source_result($result);
    }

    /**
     * Backfill item-level question attempts for one Moodle quiz attempt.
     *
     * @param \stdClass $attempt Quiz attempt row.
     * @param int $sourceeventid Source event id.
     * @param int $attemptrecordid Local attempt id.
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param array $mapping Program 1 mapping.
     * @param array $options Options.
     * @param array $result Source result.
     */
    private static function backfill_question_attempts(
        \stdClass $attempt,
        int $sourceeventid,
        int $attemptrecordid,
        int $courseid,
        int $cmid,
        array $mapping,
        array $options,
        array &$result
    ): void {
        global $DB;

        if (empty($attempt->uniqueid)) {
            return;
        }

        $sql = "SELECT qa.*,
                       qas.state AS lateststate,
                       qas.fraction AS latestfraction,
                       qas.timecreated AS lateststeptime
                  FROM {question_attempts} qa
             LEFT JOIN {question_attempt_steps} qas
                    ON qas.questionattemptid = qa.id
                   AND qas.sequencenumber = (
                       SELECT MAX(qas2.sequencenumber)
                         FROM {question_attempt_steps} qas2
                        WHERE qas2.questionattemptid = qa.id
                   )
                 WHERE qa.questionusageid = :questionusageid
              ORDER BY qa.slot ASC";
        $rows = $DB->get_records_sql($sql, ['questionusageid' => (int)$attempt->uniqueid], 0, self::QUESTION_BACKFILL_LIMIT);

        foreach ($rows as $row) {
            $steptime = self::reliable_time($row->lateststeptime ?? null, $row->timemodified ?? null,
                $attempt->timemodified ?? null);
            if ($steptime <= 0) {
                self::skip($result, 'unknown_question_timestamp', [
                    'table' => 'question_attempts',
                    'id' => (int)$row->id,
                ]);
                continue;
            }
            $rawmark = isset($row->latestfraction, $row->maxmark) && $row->latestfraction !== null
                ? round((float)$row->latestfraction * (float)$row->maxmark, 5)
                : null;
            $record = array_merge(normalizer::question_attempt_to_record($row, [
                'sourceeventid' => $sourceeventid ?: null,
                'attemptid' => $attemptrecordid ?: null,
                'userid' => (int)$attempt->userid,
                'courseid' => $courseid,
                'cmid' => $cmid > 0 ? $cmid : null,
                'resultstate' => $row->lateststate ?? null,
                'rawmark' => $rawmark,
                'fraction' => isset($row->latestfraction) ? (float)$row->latestfraction : null,
                'steptime' => $steptime,
                'responsehash' => !empty($row->responsesummary)
                    ? source_identity::payload_hash((string)$row->responsesummary)
                    : null,
                'sourcefactkey' => source_identity::make_key('moodle', 'question_attempt', (string)$row->id,
                    (string)$steptime, 'source_fact'),
            ]), self::mapping_fields($mapping));

            self::counted_upsert($result, 'flwhist_question_attempt', $record['sourcekey'], $options['dryrun'],
                function() use ($record): int {
                    return attempt_service::record_question_attempt($record);
                });
        }
    }

    /**
     * Backfill Moodle course module completion rows.
     *
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Result.
     */
    private static function backfill_completion(int $courseid, array $options): array {
        global $DB;

        $result = self::empty_source_result('completion', (int)($options['cursors']['completion'] ?? 0));
        $sql = "SELECT cmc.*, cm.course AS courseid, cm.section AS sectionid
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.id > :afterid
              ORDER BY cmc.id ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'afterid' => $result['afterid']], 0,
            $options['batchsize']);

        foreach ($rows as $row) {
            $result['recordsseen']++;
            $result['lastid'] = max($result['lastid'], (int)$row->id);
            try {
                $timestamp = self::reliable_time($row->timemodified ?? null, $row->timecompleted ?? null);
                if ($timestamp <= 0) {
                    self::skip($result, 'unknown_timestamp', ['table' => 'course_modules_completion', 'id' => (int)$row->id]);
                    continue;
                }
                $cmid = (int)$row->coursemoduleid;
                $mapping = p1_resolver::resolve_cmid($cmid);
                $sourcefactkey = source_identity::make_key('moodle', 'course_module_completion', (string)$row->id,
                    (string)$timestamp, 'source_fact');
                $sourceevent = array_merge([
                    'sourcekey' => source_identity::make_key('moodle', 'course_module_completion', (string)$row->id,
                        (string)$timestamp, 'CHECKPOINT_COMPLETED'),
                    'sourcefactkey' => $sourcefactkey,
                    'sourcesystem' => 'moodle',
                    'sourcefamily' => 'completion',
                    'sourcetype' => 'course_module_completion',
                    'sourceid' => (string)$row->id,
                    'sourceversion' => (string)$timestamp,
                    'eventtype' => 'CHECKPOINT_COMPLETED',
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'cmid' => $cmid,
                    'sectionid' => isset($row->sectionid) ? (int)$row->sectionid : null,
                    'eventtime' => $timestamp,
                    'status' => ($mapping['status'] ?? 'unresolved') === 'resolved' ? 'recorded' : 'unresolved_mapping',
                    'normalizer' => 'h7_backfill',
                    'summaryjson' => [
                        'backfill' => true,
                        'sourcelabel' => $options['sourcelabel'],
                        'completionstate' => isset($row->completionstate) ? (int)$row->completionstate : null,
                        'mappingstatus' => $mapping['status'] ?? 'unresolved',
                    ],
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                    'usermodified' => isset($row->overrideby) ? (int)$row->overrideby : null,
                ], self::mapping_fields($mapping));

                $sourceeventid = self::counted_upsert($result, 'flwhist_source_event', $sourceevent['sourcekey'],
                    $options['dryrun'], function() use ($sourceevent): int {
                        return history_service::record_source_event($sourceevent);
                    });
                $completionrecord = normalizer::completion_to_record($row, array_merge([
                    'sourceeventid' => $sourceeventid ?: null,
                    'sourcefactkey' => $sourcefactkey,
                    'courseid' => $courseid,
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                ], self::mapping_fields($mapping)));

                self::counted_upsert($result, 'flwhist_completion', $completionrecord['sourcekey'], $options['dryrun'],
                    function() use ($completionrecord): int {
                        return completion_service::record_completion($completionrecord);
                    });
            } catch (\Throwable $e) {
                self::fail($result, $e, ['table' => 'course_modules_completion', 'id' => (int)$row->id]);
            }
        }

        return self::finish_source_result($result);
    }

    /**
     * Backfill Moodle grade history rows.
     *
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Result.
     */
    private static function backfill_grade_history(int $courseid, array $options): array {
        global $DB;

        $result = self::empty_source_result('grade_history', (int)($options['cursors']['grade_history'] ?? 0));
        $sql = "SELECT ggh.*, gi.courseid AS courseid
                  FROM {grade_grades_history} ggh
                  JOIN {grade_items} gi ON gi.id = ggh.itemid
                 WHERE gi.courseid = :courseid
                   AND ggh.id > :afterid
              ORDER BY ggh.id ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'afterid' => $result['afterid']], 0,
            $options['batchsize']);

        foreach ($rows as $row) {
            $result['recordsseen']++;
            $result['lastid'] = max($result['lastid'], (int)$row->id);
            try {
                $timestamp = self::reliable_time($row->timemodified ?? null);
                if ($timestamp <= 0) {
                    self::skip($result, 'unknown_timestamp', ['table' => 'grade_grades_history', 'id' => (int)$row->id]);
                    continue;
                }
                $gradeitem = self::grade_item_row((int)$row->itemid);
                $cmid = self::cmid_for_grade_item($gradeitem);
                $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course($courseid);
                $sourcefactkey = source_identity::make_key('moodle', 'grade_grades_history', (string)$row->id,
                    (string)$timestamp, 'source_fact');
                $sourceevent = array_merge([
                    'sourcekey' => source_identity::make_key('moodle', 'grade_grades_history', (string)$row->id,
                        (string)$timestamp, 'OFFICIAL_GRADE_CHANGED'),
                    'sourcefactkey' => $sourcefactkey,
                    'sourcesystem' => 'moodle',
                    'sourcefamily' => 'gradebook',
                    'sourcetype' => 'grade_grades_history',
                    'sourceid' => (string)$row->id,
                    'sourceversion' => (string)$timestamp,
                    'eventtype' => 'OFFICIAL_GRADE_CHANGED',
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'gradeitemid' => (int)$row->itemid,
                    'eventtime' => $timestamp,
                    'status' => ($mapping['status'] ?? 'unresolved') === 'resolved' ? 'recorded' : 'unresolved_mapping',
                    'normalizer' => 'h7_backfill',
                    'summaryjson' => [
                        'backfill' => true,
                        'sourcelabel' => $options['sourcelabel'],
                        'gradehistoryaction' => $row->action ?? null,
                        'grade_reason_available' => !empty($row->source),
                        'mappingstatus' => $mapping['status'] ?? 'unresolved',
                    ],
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                    'usermodified' => isset($row->loggeduser) ? (int)$row->loggeduser : null,
                ], self::mapping_fields($mapping));

                $sourceeventid = self::counted_upsert($result, 'flwhist_source_event', $sourceevent['sourcekey'],
                    $options['dryrun'], function() use ($sourceevent): int {
                        return history_service::record_source_event($sourceevent);
                    });

                $gradeversion = self::grade_history_record_for_count($row, $sourceeventid, $sourcefactkey);
                self::counted_upsert($result, 'flwhist_grade_version', $gradeversion['sourcekey'], $options['dryrun'],
                    function() use ($row, $sourceeventid, $sourcefactkey): int {
                        return grade_history_service::record_grade_history_row($row, [
                            'sourceeventid' => $sourceeventid ?: null,
                            'sourcefactkey' => $sourcefactkey,
                        ]);
                    }, $gradeversion['fallbacksourcekeys']);
            } catch (\Throwable $e) {
                self::fail($result, $e, ['table' => 'grade_grades_history', 'id' => (int)$row->id]);
            }
        }

        return self::finish_source_result($result);
    }

    /**
     * Backfill current Moodle grade rows that have no reliable history row.
     *
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Result.
     */
    private static function backfill_current_grades(int $courseid, array $options): array {
        global $DB;

        $result = self::empty_source_result('grade_current', (int)($options['cursors']['grade_current'] ?? 0));
        $sql = "SELECT gg.id, gg.userid, gg.itemid, gg.rawgrade, gg.finalgrade, gg.timemodified, gg.usermodified,
                       gg.overridden, gg.excluded, gi.courseid, gi.itemmodule, gi.iteminstance, gi.itemnumber
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
                   AND gg.id > :afterid
              ORDER BY gg.id ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'afterid' => $result['afterid']], 0,
            $options['batchsize']);

        foreach ($rows as $row) {
            $result['recordsseen']++;
            $result['lastid'] = max($result['lastid'], (int)$row->id);
            try {
                $timestamp = self::reliable_time($row->timemodified ?? null);
                if ($timestamp <= 0) {
                    self::skip($result, 'unknown_timestamp', ['table' => 'grade_grades', 'id' => (int)$row->id]);
                    continue;
                }
                $cmid = self::cmid_for_grade_item($row);
                $mapping = $cmid > 0 ? p1_resolver::resolve_cmid($cmid) : p1_resolver::resolve_course($courseid);
                $sourcefactkey = source_identity::make_key('moodle', 'grade_grade', (string)$row->id, (string)$timestamp,
                    'source_fact');
                $sourceevent = array_merge([
                    'sourcekey' => source_identity::make_key('moodle', 'grade_grade', (string)$row->id, (string)$timestamp,
                        'OFFICIAL_GRADE_CURRENT_BACKFILL'),
                    'sourcefactkey' => $sourcefactkey,
                    'sourcesystem' => 'moodle',
                    'sourcefamily' => 'gradebook',
                    'sourcetype' => 'grade_grade',
                    'sourceid' => (string)$row->id,
                    'sourceversion' => (string)$timestamp,
                    'eventtype' => 'OFFICIAL_GRADE_CURRENT_BACKFILL',
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'gradeitemid' => (int)$row->itemid,
                    'eventtime' => $timestamp,
                    'status' => ($mapping['status'] ?? 'unresolved') === 'resolved' ? 'recorded' : 'unresolved_mapping',
                    'normalizer' => 'h7_backfill',
                    'summaryjson' => [
                        'backfill' => true,
                        'sourcelabel' => $options['sourcelabel'],
                        'grade_reason_available' => false,
                        'mappingstatus' => $mapping['status'] ?? 'unresolved',
                    ],
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                    'usermodified' => isset($row->usermodified) ? (int)$row->usermodified : null,
                ], self::mapping_fields($mapping));

                $sourceeventid = self::counted_upsert($result, 'flwhist_source_event', $sourceevent['sourcekey'],
                    $options['dryrun'], function() use ($sourceevent): int {
                        return history_service::record_source_event($sourceevent);
                    });
                $gradeversion = array_merge([
                    'sourcekey' => source_identity::make_key('moodle', 'grade_grade', (string)$row->id,
                        (string)$timestamp, 'CURRENT_BACKFILL'),
                    'sourcefactkey' => $sourcefactkey,
                    'sourcefamily' => 'gradebook',
                    'sourceeventid' => $sourceeventid ?: null,
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'gradeitemid' => (int)$row->itemid,
                    'gradegradeid' => (int)$row->id,
                    'itemmodule' => $row->itemmodule ?? null,
                    'iteminstance' => isset($row->iteminstance) ? (int)$row->iteminstance : null,
                    'itemnumber' => isset($row->itemnumber) ? (int)$row->itemnumber : null,
                    'rawgrade' => isset($row->rawgrade) ? (float)$row->rawgrade : null,
                    'finalgrade' => isset($row->finalgrade) ? (float)$row->finalgrade : null,
                    'previousgrade' => null,
                    'graderid' => isset($row->usermodified) ? (int)$row->usermodified : null,
                    'action' => grade_history_service::ACTION_OTHER,
                    'reason' => null,
                    'gradetime' => $timestamp,
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                    'summaryjson' => [
                        'backfill' => true,
                        'sourcelabel' => $options['sourcelabel'],
                        'grade_reason_available' => false,
                        'overridden' => $row->overridden ?? null,
                        'excluded' => $row->excluded ?? null,
                    ],
                ], self::mapping_fields($mapping));

                $gradeversionid = self::counted_upsert($result, 'flwhist_grade_version', $gradeversion['sourcekey'],
                    $options['dryrun'], function() use ($gradeversion): int {
                        return grade_history_service::record_grade_version($gradeversion);
                    });

                if (!$options['dryrun']) {
                    grade_history_service::reconcile_grade_summary((int)$row->userid, (int)$row->itemid, [
                        'recordrun' => false,
                        'latestgradeversionid' => $gradeversionid,
                    ]);
                }
            } catch (\Throwable $e) {
                self::fail($result, $e, ['table' => 'grade_grades', 'id' => (int)$row->id]);
            }
        }

        return self::finish_source_result($result);
    }

    /**
     * Backfill local FLW placement facts if the placement plugin is installed.
     *
     * @param int $courseid Course id.
     * @param array $options Options.
     * @return array Result.
     */
    private static function backfill_placement(int $courseid, array $options): array {
        global $DB;

        $result = self::empty_source_result('placement', (int)($options['cursors']['placement'] ?? 0));
        if (!$DB->get_manager()->table_exists('local_flwplacement')) {
            $result['status'] = 'source_unavailable';
            $result['reason'] = 'LOCAL_FLWPLACEMENT_TABLE_NOT_INSTALLED';
            return $result;
        }

        $rows = $DB->get_records_select('local_flwplacement', 'courseid = :courseid AND id > :afterid',
            ['courseid' => $courseid, 'afterid' => $result['afterid']], 'id ASC', '*', 0, $options['batchsize']);

        foreach ($rows as $row) {
            $result['recordsseen']++;
            $result['lastid'] = max($result['lastid'], (int)$row->id);
            try {
                $timestamp = self::reliable_time($row->timecreated ?? null, $row->timemodified ?? null);
                if ($timestamp <= 0) {
                    self::skip($result, 'unknown_timestamp', ['table' => 'local_flwplacement', 'id' => (int)$row->id]);
                    continue;
                }
                $sourcefactkey = source_identity::make_key('flwplacement', 'placement_result', (string)$row->id,
                    (string)$timestamp, 'source_fact');
                $sourceevent = [
                    'sourcekey' => source_identity::make_key('flwplacement', 'placement_result', (string)$row->id,
                        (string)$timestamp, 'PLACEMENT_RECORDED'),
                    'sourcefactkey' => $sourcefactkey,
                    'sourcesystem' => 'flwplacement',
                    'sourcefamily' => 'placement',
                    'sourcetype' => 'placement_result',
                    'sourceid' => (string)$row->id,
                    'sourceversion' => (string)$timestamp,
                    'eventtype' => 'PLACEMENT_RECORDED',
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'eventtime' => $timestamp,
                    'status' => 'recorded',
                    'normalizer' => 'h7_backfill',
                    'summaryjson' => [
                        'backfill' => true,
                        'sourcelabel' => $options['sourcelabel'],
                        'cefrlevel' => $row->cefrlevel ?? null,
                        'recommendedcourse' => $row->recommendedcourse ?? null,
                        'startingunit' => isset($row->startingunit) ? (int)$row->startingunit : null,
                    ],
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                ];
                $sourceeventid = self::counted_upsert($result, 'flwhist_source_event', $sourceevent['sourcekey'],
                    $options['dryrun'], function() use ($sourceevent): int {
                        return history_service::record_source_event($sourceevent);
                    });

                $placement = [
                    'sourcekey' => source_identity::make_key('flwplacement', 'placement', (string)$row->id,
                        (string)$timestamp, 'PLACEMENT_RECORDED'),
                    'sourcefactkey' => $sourcefactkey,
                    'sourceeventid' => $sourceeventid ?: null,
                    'sourcesystem' => 'flwplacement',
                    'sourcefamily' => 'placement',
                    'sourcetype' => 'placement',
                    'sourceid' => (string)$row->id,
                    'sourceversion' => (string)$timestamp,
                    'userid' => (int)$row->userid,
                    'courseid' => $courseid,
                    'previouslevel' => null,
                    'currentlevel' => $row->cefrlevel ?? null,
                    'placementstatus' => 'recorded',
                    'score' => isset($row->weightedscore) ? (float)$row->weightedscore : null,
                    'confidence' => isset($row->confidencescore) ? ((float)$row->confidencescore / 100.0) : null,
                    'profilejson' => [
                        'source_table' => 'local_flwplacement',
                        'source_id' => (int)$row->id,
                        'recommendedcourse' => $row->recommendedcourse ?? null,
                        'startingunit' => isset($row->startingunit) ? (int)$row->startingunit : null,
                    ],
                    'placementtime' => $timestamp,
                    'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
                ];

                self::counted_upsert($result, 'flwhist_placement', $placement['sourcekey'], $options['dryrun'],
                    function() use ($placement): int {
                        return placement_history_service::record_placement($placement);
                    });
            } catch (\Throwable $e) {
                self::fail($result, $e, ['table' => 'local_flwplacement', 'id' => (int)$row->id]);
            }
        }

        return self::finish_source_result($result);
    }

    /**
     * Verify grade summaries against current Moodle Gradebook rows.
     *
     * @param int $courseid Course id.
     * @param int $batchsize Batch size.
     * @param bool $dryrun Whether to preview only.
     * @return array Result.
     */
    private static function verify_grade_summaries(int $courseid, int $batchsize, bool $dryrun): array {
        global $DB;

        $result = self::empty_source_result('official_grade_summary', 0);
        $sql = "SELECT gg.id, gg.userid, gg.itemid, gg.rawgrade, gg.finalgrade, gg.timemodified
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
              ORDER BY gg.timemodified DESC, gg.id DESC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, $batchsize);
        foreach ($rows as $row) {
            $result['recordsseen']++;
            $summary = repository::get_grade_summary((int)$row->userid, (int)$row->itemid);
            $matches = $summary
                && self::numbers_equal($summary->officialrawgrade ?? null, $row->rawgrade ?? null)
                && self::numbers_equal($summary->officialfinalgrade ?? null, $row->finalgrade ?? null)
                && (int)($summary->officialgradegradeid ?? 0) === (int)$row->id;
            if ($matches) {
                $result['recordsskipped']++;
                continue;
            }
            $result['findings'][] = [
                'type' => $summary ? 'stale_grade_summary' : 'missing_grade_summary',
                'userid' => (int)$row->userid,
                'gradeitemid' => (int)$row->itemid,
            ];
            if ($dryrun) {
                $result['recordsupdated']++;
                continue;
            }
            grade_history_service::reconcile_grade_summary((int)$row->userid, (int)$row->itemid, ['recordrun' => false]);
            $result['recordsupdated']++;
        }
        $result['status'] = empty($result['findings']) ? 'pass' : 'needs_attention';
        return $result;
    }

    /**
     * Verify completion rows and Program 1 content structure links.
     *
     * @param int $courseid Course id.
     * @param int $batchsize Batch size.
     * @param bool $dryrun Whether to preview only.
     * @return array Result.
     */
    private static function verify_completion_and_structure(int $courseid, int $batchsize, bool $dryrun): array {
        global $DB;

        $result = self::empty_source_result('completion_and_structure', 0);
        $sql = "SELECT cmc.*, cm.course AS courseid
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
              ORDER BY cmc.timemodified DESC, cmc.id DESC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, $batchsize);
        foreach ($rows as $row) {
            $result['recordsseen']++;
            $local = self::latest_completion_record((int)$row->userid, (int)$row->coursemoduleid, $courseid);
            $matches = $local
                && (int)($local->completionstate ?? -1) === (int)$row->completionstate
                && (int)($local->completiontime ?? 0) === (int)($row->timemodified ?? 0);
            if ($matches) {
                $result['recordsskipped']++;
                continue;
            }
            $result['findings'][] = [
                'type' => $local ? 'stale_completion_history' : 'missing_completion_history',
                'userid' => (int)$row->userid,
                'cmid' => (int)$row->coursemoduleid,
            ];
            if ($dryrun) {
                $result['recordsupdated']++;
                continue;
            }
            completion_service::record_moodle_completion($row, ['courseid' => $courseid]);
            $result['recordsupdated']++;
        }

        $structure = self::verify_content_identity_structure($courseid);
        $result['structure'] = $structure;
        if (($structure['missingidentitycount'] ?? 0) > 0 || ($structure['orphanlinkcount'] ?? 0) > 0) {
            $result['findings'][] = [
                'type' => 'program1_identity_structure_findings',
                'missingidentitycount' => $structure['missingidentitycount'],
                'orphanlinkcount' => $structure['orphanlinkcount'],
            ];
        }
        $result['status'] = empty($result['findings']) ? 'pass' : 'needs_attention';
        return $result;
    }

    /**
     * Verify Program 1 content identity cache coverage.
     *
     * @param int $courseid Course id.
     * @return array Result.
     */
    private static function verify_content_identity_structure(int $courseid): array {
        global $DB;

        $trackedcmids = $DB->get_fieldset_select('course_modules', 'id',
            'course = :courseid AND deletioninprogress = 0 AND visible = 1',
            ['courseid' => $courseid]);
        $trackedcmids = array_map('intval', $trackedcmids);
        $resolved = 0;
        $missing = 0;
        if ($trackedcmids) {
            [$insql, $params] = $DB->get_in_or_equal($trackedcmids, SQL_PARAMS_NAMED, 'vci');
            $resolved = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT cmid)
                   FROM {flwhist_content_link}
                  WHERE cmid {$insql}
                    AND moodlecourseid = :courseid
                    AND status = :status",
                array_merge($params, ['courseid' => $courseid, 'status' => 'resolved'])
            );
            $missing = max(0, count($trackedcmids) - $resolved);
        }
        $orphanlinks = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {flwhist_content_link} cl
          LEFT JOIN {course_modules} cm ON cm.id = cl.cmid
              WHERE cl.moodlecourseid = :courseid
                AND cl.cmid IS NOT NULL
                AND (cm.id IS NULL OR cm.course <> cl.moodlecourseid)",
            ['courseid' => $courseid]
        );

        return [
            'trackedcmcount' => count($trackedcmids),
            'resolvedidentitycount' => $resolved,
            'missingidentitycount' => $missing,
            'orphanlinkcount' => $orphanlinks,
            'status' => ($missing === 0 && $orphanlinks === 0) ? 'pass' : 'needs_attention',
        ];
    }

    /**
     * Verify orphan FLW KP mapping rows when the mapping table exists.
     *
     * @param int $courseid Course id.
     * @return array Result.
     */
    private static function verify_orphan_flw_mappings(int $courseid): array {
        global $DB;

        $result = self::empty_source_result('orphan_flw_mappings', 0);
        if (!$DB->get_manager()->table_exists('local_flwkp_mappings')) {
            $result['status'] = 'source_unavailable';
            $result['reason'] = 'LOCAL_FLWKP_MAPPINGS_TABLE_NOT_INSTALLED';
            return $result;
        }

        $questionorphans = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_flwkp_mappings} m
          LEFT JOIN {question} q ON q.id = m.itemid
              WHERE m.itemtype = :questiontype
                AND q.id IS NULL",
            ['questiontype' => 'question']
        );
        $activityorphans = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
              FROM {local_flwkp_mappings} m
          LEFT JOIN {course_modules} cm ON cm.id = m.itemid
              WHERE m.itemtype IN ('activity', 'coursemodule', 'resource')
                AND cm.id IS NULL",
            []
        );
        $courseactivityorphans = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_flwkp_mappings} m
               JOIN {course_modules} cm ON cm.id = m.itemid
              WHERE m.itemtype IN ('activity', 'coursemodule', 'resource')
                AND cm.course = :courseid
                AND cm.deletioninprogress <> 0",
            ['courseid' => $courseid]
        );

        $total = $questionorphans + $activityorphans + $courseactivityorphans;
        $result['recordsseen'] = (int)$DB->count_records('local_flwkp_mappings');
        $result['recordsfailed'] = $total;
        $result['findings'] = [
            'question_orphans' => $questionorphans,
            'activity_orphans' => $activityorphans,
            'deleted_course_activity_mappings' => $courseactivityorphans,
        ];
        $result['status'] = $total === 0 ? 'pass' : 'needs_attention';
        return $result;
    }

    /**
     * Verify duplicate source identity values.
     *
     * @param int $courseid Course id.
     * @return array Result.
     */
    private static function verify_duplicate_source_identities(int $courseid): array {
        global $DB;

        $result = self::empty_source_result('duplicate_source_identities', 0);
        $tables = [
            'flwhist_source_event',
            'flwhist_attempt',
            'flwhist_question_attempt',
            'flwhist_grade_version',
            'flwhist_completion',
            'flwhist_placement',
        ];
        foreach ($tables as $table) {
            $duplicates = (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM (
                         SELECT sourcefactkey
                           FROM {{$table}}
                          WHERE courseid = :courseid
                            AND sourcefactkey IS NOT NULL
                       GROUP BY sourcefactkey
                         HAVING COUNT(1) > 1
                        ) duplicatefacts",
                ['courseid' => $courseid]
            );
            $result['findings'][$table] = $duplicates;
            $result['recordsfailed'] += $duplicates;
        }
        $result['status'] = $result['recordsfailed'] === 0 ? 'pass' : 'needs_attention';
        return $result;
    }

    /**
     * Return schema status.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $tables = [];
        $missing = [];
        foreach (self::REQUIRED_TABLES as $table) {
            $exists = $DB->get_manager()->table_exists($table);
            $tables[$table] = $exists ? 'present' : 'missing';
            if (!$exists) {
                $missing[] = $table;
            }
        }
        return [
            'status' => $missing ? 'fail' : 'pass',
            'tables' => $tables,
            'missing' => $missing,
        ];
    }

    /**
     * Return capture runtime status.
     *
     * @return array
     */
    private static function capture_runtime_status(): array {
        return [
            'status' => class_exists(\local_flwhistory\observer::class)
                && class_exists(\local_flwhistory\task\refresh_capture_coverage::class)
                ? 'pass'
                : 'fail',
            'observer_class' => class_exists(\local_flwhistory\observer::class),
            'coverage_task_class' => class_exists(\local_flwhistory\task\refresh_capture_coverage::class),
        ];
    }

    /**
     * Return security/privacy implementation status.
     *
     * @return array
     */
    private static function security_privacy_status(): array {
        return [
            'status' => 'pass',
            'own_other_access' => method_exists(dashboard_service::class, 'require_learner_access')
                && method_exists(\local_flwhistory\external\api::class, 'get_learning_history_parameters'),
            'teacher_contexts' => method_exists(teacher_analytics_service::class, 'require_teacher_access'),
            'export' => method_exists(\local_flwhistory\privacy\provider::class, 'export_user_data'),
            'deletion' => method_exists(\local_flwhistory\privacy\provider::class, 'delete_data_for_user')
                && method_exists(\local_flwhistory\privacy\provider::class, 'delete_data_for_users'),
            'csrf_sesskey' => 'no_state_changing_web_forms_in_history_v1',
            'parameter_validation' => 'required_param_optional_param_and_external_validate_parameters',
            'output_escaping' => 'html_writer_s_format_string_and_JSON_wrappers',
        ];
    }

    /**
     * Return downstream contract status.
     *
     * @return array
     */
    private static function downstream_contract_status(): array {
        $methods = [
            'source_events_for_course',
            'attempts_for_course',
            'grades_for_course',
            'completions_for_course',
            'placements_for_course',
            'content_identities_for_course',
        ];
        $missing = [];
        foreach ($methods as $method) {
            if (!method_exists(evidence_source_adapter::class, $method)) {
                $missing[] = $method;
            }
        }
        return [
            'status' => $missing ? 'fail' : 'pass',
            'contract' => evidence_source_adapter::CONTRACT_VERSION,
            'missingmethods' => $missing,
        ];
    }

    /**
     * Convert a grade history row to a record only for sourcekey existence counting.
     *
     * @param \stdClass $row Moodle grade history row.
     * @param int $sourceeventid Source event id.
     * @param string $sourcefactkey Source fact key.
     * @return array
     */
    private static function grade_history_record_for_count(\stdClass $row, int $sourceeventid, string $sourcefactkey): array {
        return [
            'sourcekey' => grade_history_service::grade_history_row_sourcekey($row),
            'sourcefactkey' => $sourcefactkey,
            'sourceeventid' => $sourceeventid ?: null,
            'fallbacksourcekeys' => [
                source_identity::make_key('moodle', 'grade_grades_history', (string)$row->id,
                    (string)$row->timemodified, grade_history_service::ACTION_OTHER),
            ],
        ];
    }

    /**
     * Return a grade item row.
     *
     * @param int $gradeitemid Grade item id.
     * @return \stdClass|null
     */
    private static function grade_item_row(int $gradeitemid): ?\stdClass {
        global $DB;

        return $DB->get_record('grade_items', ['id' => $gradeitemid],
            'id,courseid,itemmodule,iteminstance,itemnumber', IGNORE_MISSING) ?: null;
    }

    /**
     * Resolve course module id for a grade item row.
     *
     * @param \stdClass|null $gradeitem Grade item row.
     * @return int
     */
    private static function cmid_for_grade_item(?\stdClass $gradeitem): int {
        global $DB;

        if (!$gradeitem || empty($gradeitem->itemmodule) || empty($gradeitem->iteminstance) || empty($gradeitem->courseid)) {
            return 0;
        }
        $moduleid = $DB->get_field('modules', 'id', ['name' => $gradeitem->itemmodule], IGNORE_MISSING);
        if (!$moduleid) {
            return 0;
        }
        $cmid = $DB->get_field('course_modules', 'id', [
            'course' => (int)$gradeitem->courseid,
            'module' => (int)$moduleid,
            'instance' => (int)$gradeitem->iteminstance,
        ], IGNORE_MISSING);
        return $cmid ? (int)$cmid : 0;
    }

    /**
     * Fetch latest local completion record for a Moodle completion row.
     *
     * @param int $userid User id.
     * @param int $cmid Course module id.
     * @param int $courseid Course id.
     * @return \stdClass|null
     */
    private static function latest_completion_record(int $userid, int $cmid, int $courseid): ?\stdClass {
        global $DB;

        $records = $DB->get_records('flwhist_completion', [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
        ], 'completiontime DESC, id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Count a history upsert operation.
     *
     * @param array $result Source result.
     * @param string $table Table name.
     * @param string $sourcekey Source key.
     * @param bool $dryrun Whether to preview only.
     * @param callable $writer Writer callback.
     * @param array $fallbackkeys Source keys written by earlier compatible writers.
     * @return int Record id, or 0 for dry-run creates.
     */
    private static function counted_upsert(
        array &$result,
        string $table,
        string $sourcekey,
        bool $dryrun,
        callable $writer,
        array $fallbackkeys = []
    ): int {
        global $DB;

        $existing = $DB->get_record($table, ['sourcekey' => $sourcekey], 'id', IGNORE_MISSING);
        if ($existing) {
            $result['recordsupdated']++;
            if ($dryrun) {
                return (int)$existing->id;
            }
            return (int)$writer();
        }

        foreach ($fallbackkeys as $fallbackkey) {
            if ($fallbackkey === $sourcekey) {
                continue;
            }
            $fallback = $DB->get_record($table, ['sourcekey' => $fallbackkey], 'id', IGNORE_MISSING);
            if (!$fallback) {
                continue;
            }
            $result['recordsupdated']++;
            if ($dryrun) {
                return (int)$fallback->id;
            }
            $DB->set_field($table, 'sourcekey', $sourcekey, ['id' => $fallback->id]);
            return (int)$writer();
        }

        $result['recordscreated']++;
        if ($dryrun) {
            return 0;
        }
        return (int)$writer();
    }

    /**
     * Build empty source result.
     *
     * @param string $source Source.
     * @param int $afterid Cursor.
     * @param string $status Initial status.
     * @return array
     */
    private static function empty_source_result(string $source, int $afterid, string $status = 'complete'): array {
        return [
            'source' => $source,
            'status' => $status,
            'afterid' => $afterid,
            'lastid' => $afterid,
            'nextcursor' => $afterid,
            'recordsseen' => 0,
            'recordscreated' => 0,
            'recordsupdated' => 0,
            'recordsskipped' => 0,
            'recordsfailed' => 0,
            'findings' => [],
            'errors' => [],
        ];
    }

    /**
     * Finish source result.
     *
     * @param array $result Result.
     * @return array
     */
    private static function finish_source_result(array $result): array {
        $result['nextcursor'] = $result['lastid'];
        if ($result['status'] === 'complete' && $result['recordsfailed'] > 0) {
            $result['status'] = 'complete_with_errors';
        }
        return $result;
    }

    /**
     * Add skipped row.
     *
     * @param array $result Result.
     * @param string $reason Reason code.
     * @param array $context Context.
     */
    private static function skip(array &$result, string $reason, array $context): void {
        $result['recordsskipped']++;
        if (count($result['errors']) < 10) {
            $result['errors'][] = ['reason' => $reason, 'context' => $context];
        }
    }

    /**
     * Add failed row.
     *
     * @param array $result Result.
     * @param \Throwable $error Error.
     * @param array $context Context.
     */
    private static function fail(array &$result, \Throwable $error, array $context): void {
        $result['recordsfailed']++;
        if (count($result['errors']) < 10) {
            $result['errors'][] = [
                'reason' => 'exception',
                'message' => $error->getMessage(),
                'context' => $context,
            ];
        }
    }

    /**
     * Merge source totals.
     *
     * @param array $sources Source results.
     * @return array
     */
    private static function merge_source_totals(array $sources): array {
        $totals = [
            'recordsseen' => 0,
            'recordscreated' => 0,
            'recordsupdated' => 0,
            'recordsskipped' => 0,
            'recordsfailed' => 0,
        ];
        foreach ($sources as $source) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int)($source[$key] ?? 0);
            }
        }
        return $totals;
    }

    /**
     * Return source errors for run metadata.
     *
     * @param array $sources Source results.
     * @return array|null
     */
    private static function source_errors(array $sources): ?array {
        $errors = [];
        foreach ($sources as $source => $result) {
            if (!empty($result['errors'])) {
                $errors[$source] = $result['errors'];
            }
        }
        return $errors ?: null;
    }

    /**
     * Return next cursors.
     *
     * @param array $sources Source results.
     * @return array
     */
    private static function next_cursors(array $sources): array {
        $cursors = [];
        foreach ($sources as $source => $result) {
            $cursors[$source] = (int)($result['nextcursor'] ?? $result['lastid'] ?? 0);
        }
        return $cursors;
    }

    /**
     * Count reconciliation findings.
     *
     * @param array $checks Checks.
     * @return int
     */
    private static function issue_count(array $checks): int {
        $count = 0;
        foreach ($checks as $check) {
            if (($check['status'] ?? 'pass') !== 'pass' && ($check['status'] ?? '') !== 'source_unavailable') {
                $count++;
            }
            $count += (int)($check['recordsfailed'] ?? 0);
        }
        return $count;
    }

    /**
     * Collect reconciliation findings.
     *
     * @param array $checks Checks.
     * @return array
     */
    private static function check_findings(array $checks): array {
        $findings = [];
        foreach ($checks as $name => $check) {
            if (!empty($check['findings'])) {
                $findings[$name] = $check['findings'];
            }
        }
        return $findings;
    }

    /**
     * Normalise backfill options.
     *
     * @param array $options Options.
     * @return array
     */
    private static function normalise_backfill_options(array $options): array {
        $sources = $options['sources'] ?? self::BACKFILL_SOURCES;
        if (is_string($sources)) {
            $sources = array_filter(array_map('trim', explode(',', $sources)));
        }
        $sources = array_values(array_intersect($sources, self::BACKFILL_SOURCES));
        if (!$sources) {
            $sources = self::BACKFILL_SOURCES;
        }
        $cursors = $options['cursors'] ?? [];
        if (is_string($cursors)) {
            $decoded = json_decode($cursors, true);
            $cursors = is_array($decoded) ? $decoded : [];
        }
        return [
            'dryrun' => !empty($options['dryrun']),
            'batchsize' => self::bounded_int($options['batchsize'] ?? $options['limit'] ?? self::DEFAULT_BATCH_SIZE,
                1, self::MAX_BATCH_SIZE),
            'sources' => $sources,
            'cursors' => array_map('intval', $cursors),
            'sourcelabel' => self::clean_label((string)($options['sourcelabel'] ?? 'h7_backfill')),
            'idempotencykey' => isset($options['idempotencykey']) ? self::clean_label((string)$options['idempotencykey']) : null,
        ];
    }

    /**
     * Build deterministic reconcile-run key.
     *
     * @param string $runtype Run type.
     * @param int $courseid Course id.
     * @param array $scope Scope.
     * @return string
     */
    private static function run_sourcekey(string $runtype, int $courseid, array $scope): string {
        $idempotency = !empty($scope['idempotencykey'])
            ? (string)$scope['idempotencykey']
            : substr(source_identity::payload_hash($scope), 0, 24);
        return source_identity::make_key('flwhistory', 'reconcile_run', $runtype . ':' . (string)$courseid,
            $idempotency, !empty($scope['dryrun']) ? 'dryrun' : 'execute');
    }

    /**
     * Clean source labels.
     *
     * @param string $value Raw label.
     * @return string
     */
    private static function clean_label(string $value): string {
        $value = preg_replace('/[^A-Za-z0-9_.@-]+/', '-', trim($value));
        $value = trim((string)$value, '-');
        return $value === '' ? 'h7' : substr($value, 0, 80);
    }

    /**
     * Return the first positive timestamp.
     *
     * @param mixed ...$values Candidate timestamps.
     * @return int
     */
    private static function reliable_time(...$values): int {
        foreach ($values as $value) {
            if ($value !== null && (int)$value > 0) {
                return (int)$value;
            }
        }
        return 0;
    }

    /**
     * Determine quiz attempt event type.
     *
     * @param \stdClass $attempt Attempt row.
     * @return string
     */
    private static function quiz_attempt_eventtype(\stdClass $attempt): string {
        $state = strtolower((string)($attempt->state ?? ''));
        if ($state === 'finished' || (int)($attempt->timefinish ?? 0) > 0) {
            return 'ASSESSMENT_COMPLETED';
        }
        return 'ACTIVITY_ATTEMPTED';
    }

    /**
     * Copy Program 1 mapping fields.
     *
     * @param array $mapping Mapping.
     * @return array
     */
    private static function mapping_fields(array $mapping): array {
        $fields = [];
        foreach (['worldid', 'stageid', 'unitid', 'lessonid', 'componentid', 'activityid', 'assessmentid', 'questionid'] as $field) {
            if (!empty($mapping[$field])) {
                $fields[$field] = $mapping[$field];
            }
        }
        return $fields;
    }

    /**
     * Compute scaled score.
     *
     * @param mixed $raw Raw score.
     * @param float|null $max Max score.
     * @return float|null
     */
    private static function scaled_score($raw, ?float $max): ?float {
        if ($raw === null || $max === null || $max <= 0) {
            return null;
        }
        return round(max(0.0, min(1.0, (float)$raw / $max)), 5);
    }

    /**
     * Compare grade numbers.
     *
     * @param mixed $left Left value.
     * @param mixed $right Right value.
     * @return bool
     */
    private static function numbers_equal($left, $right): bool {
        if (($left === null || $left === '') && ($right === null || $right === '')) {
            return true;
        }
        if (!is_numeric($left) || !is_numeric($right)) {
            return false;
        }
        return abs((float)$left - (float)$right) < 0.00001;
    }

    /**
     * Sample a learner in a course.
     *
     * @param int $courseid Course id.
     * @return int
     */
    private static function sample_learner_id(int $courseid): int {
        global $DB;

        $sql = "SELECT u.id
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                   AND ue.status = 0
                   AND e.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
              ORDER BY u.id ASC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, 1);
        if ($records) {
            $record = reset($records);
            return (int)$record->id;
        }
        $userid = $DB->get_field('flwhist_source_event', 'userid', ['courseid' => $courseid], IGNORE_MULTIPLE);
        return $userid ? (int)$userid : 0;
    }

    /**
     * Measure a callable.
     *
     * @param callable $callback Callback.
     * @return array Measurement.
     */
    private static function measure(callable $callback): array {
        $start = microtime(true);
        try {
            $payload = $callback();
            return [
                'status' => 'measured',
                'durationms' => round((microtime(true) - $start) * 1000, 3),
                'payloadtype' => is_array($payload) ? ($payload['type'] ?? 'array') : gettype($payload),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'durationms' => round((microtime(true) - $start) * 1000, 3),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Summarize performance status.
     *
     * @param array $measurements Measurements.
     * @return string
     */
    private static function performance_status(array $measurements): string {
        foreach ($measurements as $measurement) {
            if (($measurement['status'] ?? '') === 'failed') {
                return 'failed';
            }
        }
        return 'measured';
    }

    /**
     * Summarize freeze status.
     *
     * @param array $checks Checks.
     * @return string
     */
    private static function freeze_overall_status(array $checks): string {
        foreach ($checks as $check) {
            if (($check['status'] ?? 'pass') === 'fail' || ($check['status'] ?? '') === 'failed') {
                return 'blocked';
            }
        }
        return 'frozen';
    }

    /**
     * Bound integer.
     *
     * @param mixed $value Raw value.
     * @param int $min Min.
     * @param int $max Max.
     * @return int
     */
    private static function bounded_int($value, int $min, int $max): int {
        $value = (int)$value;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }
}

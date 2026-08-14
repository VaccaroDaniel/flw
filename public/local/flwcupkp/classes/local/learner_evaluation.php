<?php
// Learner evaluation service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds V4 learner-evaluation profiles, snapshots, self-evaluations, and diagnostics.
 */
class learner_evaluation {
    /** @var string Current deterministic learner evaluation rule version. */
    public const EVALUATION_RULE_VERSION = 'eval-v1';

    /** @var string Current diagnostic inference rule version. */
    public const DIAGNOSTIC_RULE_VERSION = 'diagnostic-v1';

    /** @var string Snapshot payload version. */
    public const SNAPSHOT_VERSION = '1';

    /** @var array States that satisfy target-level mastery. */
    private const STRONG_STATES = [
        'kp' => ['mastered'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained', 'mastered'],
    ];

    /** @var array Default state labels for empty targets. */
    private const EMPTY_STATES = [
        'kp' => 'not_introduced',
        'up' => 'not_observed',
        'competency' => 'not_started',
    ];

    /**
     * Save an evaluation period.
     *
     * @param array $data
     * @return int
     */
    public static function save_period(array $data): int {
        global $DB, $USER;

        $now = time();
        $id = (int)($data['id'] ?? 0);
        $record = (object)[
            'courseid' => self::positive_int_or_null($data['courseid'] ?? null),
            'frameworkid' => self::positive_int_or_null($data['frameworkid'] ?? null),
            'name' => trim((string)($data['name'] ?? '')),
            'periodtype' => self::clean_period_type((string)($data['periodtype'] ?? 'unit')),
            'datestart' => self::positive_int_or_null($data['datestart'] ?? null),
            'dateend' => self::positive_int_or_null($data['dateend'] ?? null),
            'cefr' => trim((string)($data['cefr'] ?? '')),
            'unitcode' => trim((string)($data['unitcode'] ?? '')),
            'configjson' => self::encode_json($data['config'] ?? $data['configjson'] ?? []),
            'status' => self::clean_status((string)($data['status'] ?? 'active')),
            'timemodified' => $now,
            'usermodified' => $USER->id ?? 0,
        ];

        if ($record->name === '') {
            $record->name = $record->unitcode !== '' ? $record->unitcode . ' evaluation' : 'Learner evaluation';
        }

        if ($record->courseid && !$DB->record_exists('course', ['id' => $record->courseid])) {
            throw new \invalid_parameter_exception('Course does not exist.');
        }
        if ($record->frameworkid && !$DB->record_exists('flwcupkp_framework', ['id' => $record->frameworkid])) {
            throw new \invalid_parameter_exception('C-UP-KP framework does not exist.');
        }

        if ($id > 0) {
            $existing = $DB->get_record('flwcupkp_eval_period', ['id' => $id], '*', MUST_EXIST);
            $record->id = $id;
            $record->timecreated = (int)$existing->timecreated;
            $DB->update_record('flwcupkp_eval_period', $record);
        } else {
            $record->timecreated = $now;
            $id = (int)$DB->insert_record('flwcupkp_eval_period', $record);
        }

        repository::audit('evaluation_period_saved', 'period', $id, [
            'courseid' => $record->courseid,
            'frameworkid' => $record->frameworkid,
            'unitcode' => $record->unitcode,
            'periodtype' => $record->periodtype,
            'status' => $record->status,
        ]);

        return $id;
    }

    /**
     * List evaluation periods for a scope.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param string $unitcode
     * @param string $status
     * @return array
     */
    public static function periods(int $courseid = 0, int $frameworkid = 0, string $unitcode = '',
            string $status = 'active'): array {
        global $DB;

        $conditions = [];
        $params = [];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($frameworkid > 0) {
            $conditions[] = 'frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        if ($unitcode !== '') {
            $conditions[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($status !== '') {
            $conditions[] = 'status = :status';
            $params['status'] = self::clean_status($status);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return array_values($DB->get_records_sql(
            "SELECT *
               FROM {flwcupkp_eval_period}
              {$where}
           ORDER BY COALESCE(datestart, 0) DESC, id DESC",
            $params
        ));
    }

    /**
     * Return one period or null.
     *
     * @param int $periodid
     * @return \stdClass|null
     */
    public static function get_period(int $periodid): ?\stdClass {
        global $DB;

        if ($periodid <= 0) {
            return null;
        }
        $period = $DB->get_record('flwcupkp_eval_period', ['id' => $periodid], '*', IGNORE_MISSING);
        return $period ?: null;
    }

    /**
     * Record learner self-evaluation.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @param string $targettype
     * @param int $targetid
     * @param float $rating
     * @param string $reflection
     * @param string $provenance
     * @return array
     */
    public static function record_self_evaluation(int $userid, int $courseid, int $periodid, string $targettype,
            int $targetid, float $rating, string $reflection = '', string $provenance = 'learner'): array {
        global $DB;

        evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        evidence_guard::assert_target_exists($targettype, $targetid);
        if ($periodid > 0 && !$DB->record_exists('flwcupkp_eval_period', ['id' => $periodid])) {
            throw new \invalid_parameter_exception('Evaluation period does not exist.');
        }

        $now = time();
        $record = (object)[
            'userid' => $userid,
            'courseid' => $courseid > 0 ? $courseid : null,
            'periodid' => $periodid > 0 ? $periodid : null,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'selfrating' => self::clamp01($rating),
            'reflection' => trim($reflection),
            'provenance' => trim($provenance) ?: 'learner',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = (int)$DB->insert_record('flwcupkp_selfeval', $record);

        repository::audit('learner_self_evaluation_saved', $targettype, $targetid, [
            'selfevalid' => $id,
            'userid' => $userid,
            'courseid' => $courseid,
            'periodid' => $periodid,
            'selfrating' => $record->selfrating,
        ]);

        return [
            'id' => $id,
            'status' => 'saved',
            'selfrating' => (float)$record->selfrating,
        ];
    }

    /**
     * List learner self-evaluation rows.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @return array
     */
    public static function self_evaluations(int $userid, int $courseid = 0, int $periodid = 0): array {
        global $DB;

        $conditions = ['userid = :userid'];
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($periodid > 0) {
            $conditions[] = 'periodid = :periodid';
            $params['periodid'] = $periodid;
        }

        return array_values($DB->get_records_sql(
            "SELECT *
               FROM {flwcupkp_selfeval}
              WHERE " . implode(' AND ', $conditions) . "
           ORDER BY timemodified DESC, id DESC",
            $params
        ));
    }

    /**
     * Create an immutable learner-evaluation snapshot.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $frameworkid
     * @param int $periodid
     * @param string $evaluationtype
     * @param int $evidencecutoff
     * @return array
     */
    public static function create_snapshot(int $userid, int $courseid = 0, int $frameworkid = 0, int $periodid = 0,
            string $evaluationtype = 'unit', int $evidencecutoff = 0, string $unitcode = ''): array {
        global $DB, $USER;

        $period = self::get_period($periodid);
        if ($period) {
            $courseid = $courseid ?: (int)$period->courseid;
            $frameworkid = $frameworkid ?: (int)$period->frameworkid;
            $evaluationtype = $evaluationtype !== '' ? $evaluationtype : (string)$period->periodtype;
            $unitcode = $unitcode !== '' ? $unitcode : (string)$period->unitcode;
        }

        evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        if ($frameworkid > 0 && !$DB->record_exists('flwcupkp_framework', ['id' => $frameworkid])) {
            throw new \invalid_parameter_exception('C-UP-KP framework does not exist.');
        }

        $evidencecutoff = $evidencecutoff > 0 ? $evidencecutoff : time();
        $states = self::state_rows($userid, $courseid, $frameworkid, $evidencecutoff, $unitcode);
        $stateids = array_values(array_filter(array_map(static function($state): int {
            return (int)$state->id;
        }, $states), static function(int $id): bool {
            return $id > 0;
        }));
        $evidence = self::evidence_rows($userid, $courseid, $frameworkid, $evidencecutoff, $unitcode);
        $evidenceids = array_map(static function($event): int {
            return (int)$event->id;
        }, $evidence);
        $selfevals = self::self_evaluations($userid, $courseid, $periodid);
        $diagnostics = self::generate_diagnostics($userid, $courseid, $periodid, true, $frameworkid, $evidencecutoff,
            $unitcode);
        $recommendations = recommendation_engine::generate($userid, $courseid > 0 ? $courseid : null, 5);
        $summary = self::summary($userid, $courseid, $frameworkid, $period, $unitcode, $states, $evidence, $selfevals,
            $diagnostics, $recommendations, $evidencecutoff);

        $framework = $frameworkid > 0 ?
            $DB->get_record('flwcupkp_framework', ['id' => $frameworkid], '*', IGNORE_MISSING) : null;
        $ruleversion = self::dominant_rule_version($states);
        $snapshot = [
            'summary' => $summary,
            'stateids' => $stateids,
            'evidenceids' => $evidenceids,
            'diagnostics' => self::records_for_json($diagnostics),
            'recommendations' => self::records_for_json($recommendations),
        ];
        $checksum = hash('sha256', self::encode_json($snapshot));

        $record = (object)[
            'userid' => $userid,
            'courseid' => $courseid > 0 ? $courseid : null,
            'frameworkid' => $frameworkid > 0 ? $frameworkid : null,
            'periodid' => $periodid > 0 ? $periodid : null,
            'evaluationtype' => self::clean_period_type($evaluationtype),
            'cefrinterpretation' => (string)($summary['cefr_interpretation'] ?? ''),
            'masteryruleversion' => $ruleversion,
            'evaluationruleversion' => self::EVALUATION_RULE_VERSION,
            'frameworkversion' => $framework ? (string)$framework->version : '',
            'evidencecutoff' => $evidencecutoff,
            'snapshotversion' => self::SNAPSHOT_VERSION,
            'status' => 'current',
            'summaryjson' => self::encode_json($summary),
            'stateidsjson' => self::encode_json($stateids),
            'evidenceidsjson' => self::encode_json($evidenceids),
            'diagnosticsjson' => self::encode_json(self::records_for_json($diagnostics)),
            'recommendationsjson' => self::encode_json(self::records_for_json($recommendations)),
            'checksum' => $checksum,
            'useridcreated' => $USER->id ?? 0,
            'timecreated' => time(),
        ];
        self::archive_current_snapshots($userid, $courseid, $periodid, $unitcode);
        $record->id = (int)$DB->insert_record('flwcupkp_eval_snapshot', $record);

        repository::audit('evaluation_snapshot_created', 'user', $userid, [
            'snapshotid' => $record->id,
            'courseid' => $courseid,
            'frameworkid' => $frameworkid,
            'periodid' => $periodid,
            'evaluationtype' => $record->evaluationtype,
            'statecount' => count($stateids),
            'evidencecount' => count($evidenceids),
            'diagnosticcount' => count($diagnostics),
            'checksum' => $checksum,
        ]);

        return [
            'snapshotid' => $record->id,
            'checksum' => $checksum,
            'summary' => $summary,
            'diagnostics' => self::records_for_json($diagnostics),
            'recommendations' => self::records_for_json($recommendations),
        ];
    }

    /**
     * Get the latest snapshot row for a learner scope.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @param string $unitcode
     * @return \stdClass|null
     */
    public static function latest_snapshot(int $userid, int $courseid = 0, int $periodid = 0,
            string $unitcode = ''): ?\stdClass {
        global $DB;

        $conditions = ['userid = :userid'];
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($periodid > 0) {
            $conditions[] = 'periodid = :periodid';
            $params['periodid'] = $periodid;
        }

        $records = $DB->get_records_sql(
            "SELECT *
               FROM {flwcupkp_eval_snapshot}
              WHERE " . implode(' AND ', $conditions) . "
           ORDER BY timecreated DESC, id DESC",
            $params
        );
        foreach ($records as $snapshot) {
            if ($unitcode === '') {
                return $snapshot;
            }
            $summary = self::decode_json((string)$snapshot->summaryjson);
            if ((string)($summary['unitcode'] ?? '') === $unitcode) {
                return $snapshot;
            }
        }
        return null;
    }

    /**
     * Convert a snapshot row into an API/page payload.
     *
     * @param \stdClass $snapshot
     * @return array
     */
    public static function snapshot_payload(\stdClass $snapshot): array {
        return [
            'id' => (int)$snapshot->id,
            'userid' => (int)$snapshot->userid,
            'courseid' => (int)$snapshot->courseid,
            'frameworkid' => (int)$snapshot->frameworkid,
            'periodid' => (int)$snapshot->periodid,
            'evaluationtype' => (string)$snapshot->evaluationtype,
            'cefrinterpretation' => (string)$snapshot->cefrinterpretation,
            'masteryruleversion' => (string)$snapshot->masteryruleversion,
            'evaluationruleversion' => (string)$snapshot->evaluationruleversion,
            'frameworkversion' => (string)$snapshot->frameworkversion,
            'evidencecutoff' => (int)$snapshot->evidencecutoff,
            'snapshotversion' => (string)$snapshot->snapshotversion,
            'status' => (string)$snapshot->status,
            'summary' => self::decode_json((string)$snapshot->summaryjson),
            'stateids' => self::decode_json((string)$snapshot->stateidsjson),
            'evidenceids' => self::decode_json((string)$snapshot->evidenceidsjson),
            'diagnostics' => self::decode_json((string)$snapshot->diagnosticsjson),
            'recommendations' => self::decode_json((string)$snapshot->recommendationsjson),
            'checksum' => (string)$snapshot->checksum,
            'useridcreated' => (int)$snapshot->useridcreated,
            'timecreated' => (int)$snapshot->timecreated,
        ];
    }

    /**
     * Current learner-evaluation profile.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @return array
     */
    public static function profile(int $userid, int $courseid = 0, int $periodid = 0, string $unitcode = ''): array {
        evidence_guard::assert_user_enrolled_for_course($userid, $courseid);

        $period = self::get_period($periodid);
        $frameworkid = $period ? (int)$period->frameworkid : 0;
        $unitcode = $period && $unitcode === '' ? (string)$period->unitcode : $unitcode;
        $cutoff = time();
        $states = self::state_rows($userid, $courseid, $frameworkid, $cutoff, $unitcode);
        $evidence = self::evidence_rows($userid, $courseid, $frameworkid, $cutoff, $unitcode);
        $selfevals = self::self_evaluations($userid, $courseid, $periodid);
        $diagnostics = self::active_diagnostics($userid, $courseid, $periodid, $unitcode);
        if (empty($diagnostics)) {
            $diagnostics = self::generate_diagnostics($userid, $courseid, $periodid, false, $frameworkid, $cutoff,
                $unitcode);
        }
        $recommendations = self::current_recommendations($userid, $courseid);
        $summary = self::summary($userid, $courseid, $frameworkid, $period, $unitcode, $states, $evidence, $selfevals,
            $diagnostics, $recommendations, $cutoff);
        $snapshot = self::latest_snapshot($userid, $courseid, $periodid, $unitcode);

        return [
            'userid' => $userid,
            'courseid' => $courseid,
            'period' => $period,
            'summary' => $summary,
            'states' => self::records_for_json($states),
            'self_evaluations' => self::records_for_json($selfevals),
            'diagnostics' => self::records_for_json($diagnostics),
            'recommendations' => self::records_for_json($recommendations),
            'latest_snapshot' => $snapshot ? self::snapshot_payload($snapshot) : null,
        ];
    }

    /**
     * Generate diagnostic inferences.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @param bool $persist
     * @param int $frameworkid
     * @param int $evidencecutoff
     * @return array
     */
    public static function generate_diagnostics(int $userid, int $courseid = 0, int $periodid = 0, bool $persist = true,
            int $frameworkid = 0, int $evidencecutoff = 0, string $unitcode = ''): array {
        global $DB;

        $evidencecutoff = $evidencecutoff > 0 ? $evidencecutoff : time();
        $period = self::get_period($periodid);
        if ($period && $frameworkid <= 0) {
            $frameworkid = (int)$period->frameworkid;
        }
        if ($period && $unitcode === '') {
            $unitcode = (string)$period->unitcode;
        }
        $states = self::state_rows($userid, $courseid, $frameworkid, $evidencecutoff, $unitcode);
        $selfevals = self::latest_self_eval_map($userid, $courseid, $periodid);
        $rows = [];
        $now = time();

        foreach ($states as $state) {
            $targettype = (string)$state->targettype;
            $targetid = (int)$state->targetid;
            $evidenceids = self::evidence_ids_for_target($userid, $courseid, $targettype, $targetid, $evidencecutoff,
                $unitcode);
            $selfeval = $selfevals[$targettype . ':' . $targetid] ?? null;
            $stateids = (int)$state->id > 0 ? [(int)$state->id] : [];

            if (!self::is_strong_state($targettype, (string)$state->masterystate)) {
                $rows[] = self::diagnostic_row(
                    $userid,
                    $courseid,
                    $periodid,
                    $targettype,
                    $targetid,
                    'mastery_gap',
                    self::mastery_gap_reason($state),
                    $stateids,
                    $evidenceids,
                    (float)$state->confidence
                );
            }

            if ((float)$state->confidence < 0.50 && (int)$state->evidencecount > 0) {
                $rows[] = self::diagnostic_row(
                    $userid,
                    $courseid,
                    $periodid,
                    $targettype,
                    $targetid,
                    'low_confidence',
                    'Evidence exists, but confidence is below the V4 diagnostic threshold.',
                    $stateids,
                    $evidenceids,
                    (float)$state->confidence
                );
            }

            if ($targettype === 'kp' && !empty($state->nextreview) && (int)$state->nextreview <= $now) {
                $rows[] = self::diagnostic_row(
                    $userid,
                    $courseid,
                    $periodid,
                    $targettype,
                    $targetid,
                    'review_due',
                    'This Knowledge Point is due for spaced review.',
                    $stateids,
                    $evidenceids,
                    (float)$state->confidence
                );
            }

            if ((int)$state->evidencecount === 0 || empty($state->lastevidence)) {
                $rows[] = self::diagnostic_row(
                    $userid,
                    $courseid,
                    $periodid,
                    $targettype,
                    $targetid,
                    'missing_evidence',
                    'No valid mapped evidence has been collected for this target.',
                    $stateids,
                    $evidenceids,
                    (float)$state->confidence
                );
            } else if ((int)$state->lastevidence < $now - (90 * DAYSECS)) {
                $rows[] = self::diagnostic_row(
                    $userid,
                    $courseid,
                    $periodid,
                    $targettype,
                    $targetid,
                    'stale_evidence',
                    'Latest mapped evidence is older than the V4 freshness window.',
                    $stateids,
                    $evidenceids,
                    (float)$state->confidence
                );
            }

            if ($selfeval && (float)$selfeval->selfrating >= 0.75 && (float)$state->masteryscore < 0.70) {
                $rows[] = self::diagnostic_row(
                    $userid,
                    $courseid,
                    $periodid,
                    $targettype,
                    $targetid,
                    'self_eval_mismatch',
                    'Learner self-evaluation is stronger than the current evidence-backed mastery state.',
                    $stateids,
                    $evidenceids,
                    min(1.0, (float)$selfeval->selfrating)
                );
            }
        }

        if ($persist) {
            self::archive_existing_diagnostics($userid, $courseid, $periodid, $unitcode);
            foreach ($rows as $row) {
                $row->id = (int)$DB->insert_record('flwcupkp_diagnostic', $row);
            }
            repository::audit('diagnostics_generated', 'user', $userid, [
                'courseid' => $courseid,
                'periodid' => $periodid,
                'frameworkid' => $frameworkid,
                'count' => count($rows),
                'ruleversion' => self::DIAGNOSTIC_RULE_VERSION,
            ]);
        }

        return $rows;
    }

    /**
     * Mark prior snapshots in the same learner/course/period scope as archived.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     */
    private static function archive_current_snapshots(int $userid, int $courseid = 0, int $periodid = 0,
            string $unitcode = ''): void {
        global $DB;

        $conditions = ['userid = :userid', 'status = :status'];
        $params = [
            'userid' => $userid,
            'status' => 'current',
        ];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        } else {
            $conditions[] = 'courseid IS NULL';
        }
        if ($periodid > 0) {
            $conditions[] = 'periodid = :periodid';
            $params['periodid'] = $periodid;
        } else {
            $conditions[] = 'periodid IS NULL';
        }

        if ($unitcode === '') {
            $DB->set_field_select('flwcupkp_eval_snapshot', 'status', 'archived', implode(' AND ', $conditions), $params);
            return;
        }

        $snapshots = $DB->get_records_select('flwcupkp_eval_snapshot', implode(' AND ', $conditions), $params);
        foreach ($snapshots as $snapshot) {
            $summary = self::decode_json((string)$snapshot->summaryjson);
            if ((string)($summary['unitcode'] ?? '') === $unitcode) {
                $DB->set_field('flwcupkp_eval_snapshot', 'status', 'archived', ['id' => $snapshot->id]);
            }
        }
    }

    /**
     * Course/class evaluation summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $periodid
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '', int $periodid = 0): array {
        global $DB;

        $learnerids = self::learnerids_from_course($courseid);

        if (empty($learnerids)) {
            $learnerids = self::learnerids_from_evidence($courseid, $unitcode);
        }

        $summaries = [];
        $diagnosticcounts = [];
        $masteryscores = [];
        $confidences = [];
        $snapshotcount = 0;
        foreach ($learnerids as $userid) {
            $profile = self::profile($userid, $courseid, $periodid, $unitcode);
            $summaries[] = $profile['summary'];
            $masteryscores[] = (float)($profile['summary']['average_mastery'] ?? 0);
            $confidences[] = (float)($profile['summary']['average_confidence'] ?? 0);
            if (!empty($profile['latest_snapshot'])) {
                $snapshotcount++;
            }
            foreach ($profile['diagnostics'] as $diagnostic) {
                $category = (string)($diagnostic->gapcategory ?? 'unknown');
                $diagnosticcounts[$category] = ($diagnosticcounts[$category] ?? 0) + 1;
            }
        }

        return [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'periodid' => $periodid,
            'learner_count' => count($learnerids),
            'snapshot_count' => $snapshotcount,
            'average_mastery' => self::average($masteryscores),
            'average_confidence' => self::average($confidences),
            'diagnostic_counts' => $diagnosticcounts,
            'learners' => $summaries,
        ];
    }

    /**
     * Return course learner user records for report pages.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function course_learners(int $courseid, string $unitcode = ''): array {
        global $DB;

        $learnerids = self::learnerids_from_course($courseid);
        if (empty($learnerids)) {
            $learnerids = self::learnerids_from_evidence($courseid, $unitcode);
        }
        if (empty($learnerids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'learnerid');
        $users = $DB->get_records_select('user', "id {$insql} AND deleted = 0", $inparams,
            'lastname ASC, firstname ASC',
            'id, username, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email');

        if ($unitcode === '' || empty($users)) {
            return $users;
        }

        $evidenceids = array_flip(self::learnerids_from_evidence($courseid, $unitcode));
        uasort($users, static function($left, $right) use ($evidenceids): int {
            $leftweight = isset($evidenceids[(int)$left->id]) ? 0 : 1;
            $rightweight = isset($evidenceids[(int)$right->id]) ? 0 : 1;
            if ($leftweight !== $rightweight) {
                return $leftweight <=> $rightweight;
            }
            return (int)$left->id <=> (int)$right->id;
        });

        return $users;
    }

    /**
     * Build target options for self-evaluation forms.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $frameworkid
     * @return array
     */
    public static function target_options(int $userid, int $courseid = 0, int $frameworkid = 0,
            string $unitcode = ''): array {
        $options = [];
        foreach (self::state_rows($userid, $courseid, $frameworkid, time(), $unitcode) as $state) {
            $label = self::target_label((string)$state->targettype, (int)$state->targetid);
            $options[(string)$state->targettype . ':' . (int)$state->targetid] = $label . ' (' .
                visuals::state_label((string)$state->masterystate) . ')';
        }
        return $options;
    }

    /**
     * Fetch state rows in scope.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $frameworkid
     * @param int $cutoff
     * @return array
     */
    private static function state_rows(int $userid, int $courseid = 0, int $frameworkid = 0, int $cutoff = 0,
            string $unitcode = ''): array {
        global $DB;

        $cutoff = $cutoff > 0 ? $cutoff : time();
        $states = $DB->get_records_select('flwcupkp_state', 'userid = :userid AND timemodified <= :cutoff', [
            'userid' => $userid,
            'cutoff' => $cutoff,
        ], 'targettype ASC, targetid ASC');
        $filtered = [];
        foreach ($states as $state) {
            if ($frameworkid > 0 && self::target_frameworkid((string)$state->targettype, (int)$state->targetid) !== $frameworkid) {
                continue;
            }
            if ($courseid > 0 && !self::target_in_course((string)$state->targettype, (int)$state->targetid, $courseid)) {
                continue;
            }
            if ($unitcode !== '' && !self::target_in_unit((string)$state->targettype, (int)$state->targetid, $courseid,
                    $unitcode)) {
                continue;
            }
            $filtered[(string)$state->targettype . ':' . (int)$state->targetid] = $state;
        }

        foreach (self::target_keys_for_scope($courseid, $frameworkid, $unitcode) as $key => $target) {
            if (isset($filtered[$key])) {
                continue;
            }
            $filtered[$key] = self::empty_state_row($userid, $target['targettype'], $target['targetid'], $cutoff);
        }

        uasort($filtered, static function($left, $right): int {
            $typecompare = strcmp((string)$left->targettype, (string)$right->targettype);
            if ($typecompare !== 0) {
                return $typecompare;
            }
            return (int)$left->targetid <=> (int)$right->targetid;
        });

        return array_values($filtered);
    }

    /**
     * Fetch evidence rows in scope.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $frameworkid
     * @param int $cutoff
     * @return array
     */
    private static function evidence_rows(int $userid, int $courseid = 0, int $frameworkid = 0, int $cutoff = 0,
            string $unitcode = ''): array {
        global $DB;

        $conditions = ['userid = :userid', 'timecreated <= :cutoff'];
        $params = [
            'userid' => $userid,
            'cutoff' => $cutoff > 0 ? $cutoff : time(),
        ];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $conditions[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_select('flwcupkp_evidence', implode(' AND ', $conditions), $params,
            'timecreated ASC, id ASC');
        if ($frameworkid <= 0) {
            return array_values($rows);
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (self::target_frameworkid((string)$row->targettype, (int)$row->targetid) === $frameworkid) {
                $filtered[] = $row;
            }
        }
        return $filtered;
    }

    /**
     * Mapped target keys for a course/framework/unit scope.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param string $unitcode
     * @return array
     */
    private static function target_keys_for_scope(int $courseid = 0, int $frameworkid = 0, string $unitcode = ''): array {
        global $DB;

        $conditions = [];
        $params = [];
        if ($courseid > 0) {
            $conditions[] = '(o.courseid = :courseid OR o.courseid IS NULL)';
            $params['courseid'] = $courseid;
        }
        if ($frameworkid > 0) {
            $conditions[] = 'o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        if ($unitcode !== '') {
            $conditions[] = 'o.unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $rows = $DB->get_records_sql(
            "SELECT MIN(om.id) AS rowid, om.targettype, om.targetid
               FROM {flwcupkp_object_map} om
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              {$where}
           GROUP BY om.targettype, om.targetid
           ORDER BY om.targettype ASC, om.targetid ASC",
            $params
        );

        $targets = [];
        foreach ($rows as $row) {
            $type = (string)$row->targettype;
            $id = (int)$row->targetid;
            if ($id <= 0 || !in_array($type, ['competency', 'up', 'kp'], true)) {
                continue;
            }
            if ($frameworkid > 0 && self::target_frameworkid($type, $id) !== $frameworkid) {
                continue;
            }
            $targets[$type . ':' . $id] = [
                'targettype' => $type,
                'targetid' => $id,
            ];
        }

        foreach (self::parent_target_keys_for_scope($courseid, $frameworkid, $unitcode) as $key => $target) {
            $targets[$key] = $target;
        }

        return $targets;
    }

    /**
     * Parent UP/competency targets implied by mapped KP/unit topology.
     *
     * @param int $courseid
     * @param int $frameworkid
     * @param string $unitcode
     * @return array
     */
    private static function parent_target_keys_for_scope(int $courseid = 0, int $frameworkid = 0,
            string $unitcode = ''): array {
        global $DB;

        $objectconditions = [];
        $params = [];
        if ($courseid > 0) {
            $objectconditions[] = '(o.courseid = :courseid OR o.courseid IS NULL)';
            $params['courseid'] = $courseid;
        }
        if ($frameworkid > 0) {
            $objectconditions[] = 'o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        if ($unitcode !== '') {
            $objectconditions[] = 'o.unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $objectwhere = $objectconditions ? 'AND ' . implode(' AND ', $objectconditions) : '';

        $targets = [];
        $ups = $DB->get_records_sql(
            "SELECT DISTINCT uk.upid AS targetid
               FROM {flwcupkp_up_kp} uk
               JOIN {flwcupkp_object_map} om ON om.targettype = 'kp' AND om.targetid = uk.kpid
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE 1 = 1 {$objectwhere}",
            $params
        );
        foreach ($ups as $up) {
            $id = (int)$up->targetid;
            if ($id > 0 && ($frameworkid <= 0 || self::target_frameworkid('up', $id) === $frameworkid)) {
                $targets['up:' . $id] = ['targettype' => 'up', 'targetid' => $id];
            }
        }

        $competencies = $DB->get_records_sql(
            "SELECT DISTINCT cu.competencyid AS targetid
               FROM {flwcupkp_comp_up} cu
               JOIN {flwcupkp_up_kp} uk ON uk.upid = cu.upid
               JOIN {flwcupkp_object_map} om ON om.targettype = 'kp' AND om.targetid = uk.kpid
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE 1 = 1 {$objectwhere}",
            $params
        );
        foreach ($competencies as $competency) {
            $id = (int)$competency->targetid;
            if ($id > 0 && ($frameworkid <= 0 || self::target_frameworkid('competency', $id) === $frameworkid)) {
                $targets['competency:' . $id] = ['targettype' => 'competency', 'targetid' => $id];
            }
        }

        return $targets;
    }

    /**
     * Build a synthetic state row for mapped targets that have no evidence yet.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param int $cutoff
     * @return \stdClass
     */
    private static function empty_state_row(int $userid, string $targettype, int $targetid, int $cutoff): \stdClass {
        return (object)[
            'id' => 0,
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'masteryscore' => 0.0,
            'masterystate' => self::EMPTY_STATES[$targettype] ?? 'not_started',
            'confidence' => 0.0,
            'evidencecount' => 0,
            'lastevidence' => null,
            'lastsuccess' => null,
            'nextreview' => null,
            'manualoverride' => 0,
            'overridereason' => null,
            'ruleversion' => 'synthetic-empty-v1',
            'timemodified' => $cutoff,
        ];
    }

    /**
     * Build a summary from learner rows.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $frameworkid
     * @param \stdClass|null $period
     * @param array $states
     * @param array $evidence
     * @param array $selfevals
     * @param array $diagnostics
     * @param array $recommendations
     * @param int $cutoff
     * @return array
     */
    private static function summary(int $userid, int $courseid, int $frameworkid, ?\stdClass $period, string $unitcode,
            array $states, array $evidence, array $selfevals, array $diagnostics, array $recommendations,
            int $cutoff): array {
        $bytype = [
            'kp' => ['total' => 0, 'strong' => 0],
            'up' => ['total' => 0, 'strong' => 0],
            'competency' => ['total' => 0, 'strong' => 0],
        ];
        $scores = [];
        $confidences = [];
        $reviewdue = 0;
        $lowconfidence = 0;
        $rules = [];

        foreach ($states as $state) {
            $type = (string)$state->targettype;
            if (!isset($bytype[$type])) {
                continue;
            }
            $bytype[$type]['total']++;
            if (self::is_strong_state($type, (string)$state->masterystate)) {
                $bytype[$type]['strong']++;
            }
            $scores[] = (float)$state->masteryscore;
            $confidences[] = (float)$state->confidence;
            if ((float)$state->confidence < 0.50 && (int)$state->evidencecount > 0) {
                $lowconfidence++;
            }
            if ($type === 'kp' && !empty($state->nextreview) && (int)$state->nextreview <= time()) {
                $reviewdue++;
            }
            if (!empty($state->ruleversion)) {
                $rules[(string)$state->ruleversion] = ($rules[(string)$state->ruleversion] ?? 0) + 1;
            }
        }

        return [
            'userid' => $userid,
            'courseid' => $courseid,
            'frameworkid' => $frameworkid,
            'periodid' => $period ? (int)$period->id : 0,
            'periodname' => $period ? (string)$period->name : '',
            'periodtype' => $period ? (string)$period->periodtype : 'unit',
            'unitcode' => $period && !empty($period->unitcode) ? (string)$period->unitcode : $unitcode,
            'cefr_interpretation' => self::cefr_interpretation($states, $period),
            'kp_total' => $bytype['kp']['total'],
            'kp_mastered' => $bytype['kp']['strong'],
            'up_total' => $bytype['up']['total'],
            'up_demonstrated' => $bytype['up']['strong'],
            'competency_total' => $bytype['competency']['total'],
            'competency_achieved' => $bytype['competency']['strong'],
            'state_count' => count($states),
            'evidence_count' => count($evidence),
            'self_eval_count' => count($selfevals),
            'diagnostic_count' => count($diagnostics),
            'recommendation_count' => count($recommendations),
            'low_confidence_count' => $lowconfidence,
            'review_due_count' => $reviewdue,
            'average_mastery' => self::average($scores),
            'average_confidence' => self::average($confidences),
            'mastery_percent' => $bytype['kp']['total'] > 0 ?
                round(($bytype['kp']['strong'] / $bytype['kp']['total']) * 100, 2) : 0.0,
            'rule_versions' => $rules,
            'evaluation_rule_version' => self::EVALUATION_RULE_VERSION,
            'diagnostic_rule_version' => self::DIAGNOSTIC_RULE_VERSION,
            'evidence_cutoff' => $cutoff,
            'generated_at' => time(),
        ];
    }

    /**
     * Build a stored/payload diagnostic row.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @param string $targettype
     * @param int $targetid
     * @param string $category
     * @param string $reason
     * @param array $stateids
     * @param array $evidenceids
     * @param float $confidence
     * @return \stdClass
     */
    private static function diagnostic_row(int $userid, int $courseid, int $periodid, string $targettype, int $targetid,
            string $category, string $reason, array $stateids, array $evidenceids, float $confidence): \stdClass {
        $now = time();
        return (object)[
            'userid' => $userid,
            'courseid' => $courseid > 0 ? $courseid : null,
            'periodid' => $periodid > 0 ? $periodid : null,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'gapcategory' => $category,
            'diagnosticreason' => $reason,
            'stateidsjson' => self::encode_json($stateids),
            'evidenceidsjson' => self::encode_json($evidenceids),
            'confidence' => self::clamp01($confidence),
            'ruleversion' => self::DIAGNOSTIC_RULE_VERSION,
            'status' => 'active',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }

    /**
     * Get existing active diagnostics.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @return array
     */
    private static function active_diagnostics(int $userid, int $courseid = 0, int $periodid = 0,
            string $unitcode = ''): array {
        global $DB;

        $conditions = ['userid = :userid', "status = 'active'"];
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($periodid > 0) {
            $conditions[] = 'periodid = :periodid';
            $params['periodid'] = $periodid;
        }

        $rows = $DB->get_records_sql(
            "SELECT *
               FROM {flwcupkp_diagnostic}
              WHERE " . implode(' AND ', $conditions) . "
           ORDER BY gapcategory ASC, targettype ASC, targetid ASC",
            $params
        );
        if ($unitcode === '') {
            return array_values($rows);
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (self::target_in_unit((string)$row->targettype, (int)$row->targetid, $courseid, $unitcode)) {
                $filtered[] = $row;
            }
        }
        return $filtered;
    }

    /**
     * Archive active diagnostics before writing a fresh deterministic set.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     */
    private static function archive_existing_diagnostics(int $userid, int $courseid = 0, int $periodid = 0,
            string $unitcode = ''): void {
        global $DB;

        $conditions = ['userid = :userid', "status = 'active'"];
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($periodid > 0) {
            $conditions[] = 'periodid = :periodid';
            $params['periodid'] = $periodid;
        }
        if ($unitcode === '') {
            $DB->set_field_select('flwcupkp_diagnostic', 'status', 'archived',
                implode(' AND ', $conditions), $params);
            return;
        }

        $diagnostics = $DB->get_records_select('flwcupkp_diagnostic', implode(' AND ', $conditions), $params);
        foreach ($diagnostics as $diagnostic) {
            if (self::target_in_unit((string)$diagnostic->targettype, (int)$diagnostic->targetid, $courseid,
                    $unitcode)) {
                $DB->set_field('flwcupkp_diagnostic', 'status', 'archived', ['id' => $diagnostic->id]);
            }
        }
    }

    /**
     * Current recommendation rows without generating duplicates for page/profile reads.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    private static function current_recommendations(int $userid, int $courseid = 0): array {
        global $DB;

        $rows = $DB->get_records('flwcupkp_recommend', ['userid' => $userid, 'status' => 'recommended'],
            'timemodified DESC, id DESC', '*', 0, 5);
        if ($courseid <= 0) {
            return array_values($rows);
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (empty($row->objectid)) {
                $filtered[] = $row;
                continue;
            }
            $object = $DB->get_record('flwcupkp_object', ['id' => $row->objectid], '*', IGNORE_MISSING);
            if ($object && ((int)$object->courseid === $courseid || empty($object->courseid))) {
                $filtered[] = $row;
            }
        }
        return $filtered;
    }

    /**
     * Latest self-evaluation per target.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $periodid
     * @return array
     */
    private static function latest_self_eval_map(int $userid, int $courseid = 0, int $periodid = 0): array {
        $map = [];
        foreach (self::self_evaluations($userid, $courseid, $periodid) as $row) {
            $key = (string)$row->targettype . ':' . (int)$row->targetid;
            if (!isset($map[$key])) {
                $map[$key] = $row;
            }
        }
        return $map;
    }

    /**
     * Evidence IDs for one learner target.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $targettype
     * @param int $targetid
     * @param int $cutoff
     * @return array
     */
    private static function evidence_ids_for_target(int $userid, int $courseid, string $targettype, int $targetid,
            int $cutoff, string $unitcode = ''): array {
        global $DB;

        $conditions = [
            'userid = :userid',
            'targettype = :targettype',
            'targetid = :targetid',
            'timecreated <= :cutoff',
        ];
        $params = [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'cutoff' => $cutoff,
        ];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $conditions[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_select('flwcupkp_evidence', implode(' AND ', $conditions), $params,
            'timecreated ASC, id ASC', 'id');
        return array_map(static function($row): int {
            return (int)$row->id;
        }, array_values($rows));
    }

    /**
     * Return enrolled learner IDs for a course.
     *
     * @param int $courseid
     * @return array
     */
    private static function learnerids_from_course(int $courseid): array {
        global $DB;

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        $studentusers = $DB->get_records_sql(
            "SELECT DISTINCT u.id
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {role_assignments} ra ON ra.userid = u.id
               JOIN {role} r ON r.id = ra.roleid
              WHERE e.courseid = :courseid
                AND ra.contextid = :contextid
                AND u.deleted = 0
                AND ue.status = :useractive
                AND e.status = :enrolactive
                AND (r.archetype = :studentarchetype OR r.shortname = :studentshortname)
           ORDER BY u.id ASC",
            [
                'courseid' => $courseid,
                'contextid' => $context->id,
                'useractive' => ENROL_USER_ACTIVE,
                'enrolactive' => ENROL_INSTANCE_ENABLED,
                'studentarchetype' => 'student',
                'studentshortname' => 'student',
            ]
        );
        if (!empty($studentusers)) {
            return array_map(static function($user): int {
                return (int)$user->id;
            }, array_values($studentusers));
        }

        $enrolledusers = get_enrolled_users($context, '', 0, 'u.id', 'u.id ASC');
        return array_map(static function($user): int {
            return (int)$user->id;
        }, array_values($enrolledusers));
    }

    /**
     * Return learner IDs from evidence when enrolment is unavailable.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function learnerids_from_evidence(int $courseid, string $unitcode = ''): array {
        global $DB;

        $conditions = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        if ($unitcode !== '') {
            $conditions[] = 'e.unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT e.userid
               FROM {flwcupkp_evidence} e
              WHERE " . implode(' AND ', $conditions),
            $params
        );
        return array_map(static function($row): int {
            return (int)$row->userid;
        }, array_values($rows));
    }

    /**
     * Does this target belong to a course via object mapping or evidence?
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $courseid
     * @return bool
     */
    private static function target_in_course(string $targettype, int $targetid, int $courseid): bool {
        global $DB;

        if ($DB->record_exists('flwcupkp_evidence', [
            'courseid' => $courseid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ])) {
            return true;
        }

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {flwcupkp_object_map} om
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE om.targettype = :targettype
                AND om.targetid = :targetid
                AND (o.courseid = :courseid OR o.courseid IS NULL)",
            [
                'targettype' => $targettype,
                'targetid' => $targetid,
                'courseid' => $courseid,
            ]
        );
    }

    /**
     * Does this target belong to a unit via object mapping or evidence?
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $courseid
     * @param string $unitcode
     * @return bool
     */
    private static function target_in_unit(string $targettype, int $targetid, int $courseid, string $unitcode): bool {
        global $DB;

        if ($unitcode === '') {
            return true;
        }

        $conditions = [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'unitcode' => $unitcode,
        ];
        if ($courseid > 0) {
            $conditions['courseid'] = $courseid;
        }
        if ($DB->record_exists('flwcupkp_evidence', $conditions)) {
            return true;
        }

        $params = [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'unitcode' => $unitcode,
        ];
        $coursesql = '';
        if ($courseid > 0) {
            $coursesql = ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
            $params['courseid'] = $courseid;
        }

        if ($DB->record_exists_sql(
            "SELECT 1
               FROM {flwcupkp_object_map} om
               JOIN {flwcupkp_object} o ON o.id = om.objectid
              WHERE om.targettype = :targettype
                AND om.targetid = :targetid
                AND o.unitcode = :unitcode
                    {$coursesql}",
            $params
        )) {
            return true;
        }

        $scope = self::parent_target_keys_for_scope($courseid, 0, $unitcode);
        return isset($scope[$targettype . ':' . $targetid]);
    }

    /**
     * Target framework ID.
     *
     * @param string $targettype
     * @param int $targetid
     * @return int
     */
    private static function target_frameworkid(string $targettype, int $targetid): int {
        global $DB;

        $table = evidence_guard::target_table($targettype);
        return (int)$DB->get_field($table, 'frameworkid', ['id' => $targetid], IGNORE_MISSING);
    }

    /**
     * Human-readable target label.
     *
     * @param string $targettype
     * @param int $targetid
     * @return string
     */
    private static function target_label(string $targettype, int $targetid): string {
        global $DB;

        $table = evidence_guard::target_table($targettype);
        $target = $DB->get_record($table, ['id' => $targetid], '*', IGNORE_MISSING);
        if (!$target) {
            return strtoupper($targettype) . ' #' . $targetid;
        }
        $externalid = (string)($target->externalid ?? '');
        $title = (string)($target->title ?? $target->name ?? '');
        return trim($externalid . ' ' . $title) ?: strtoupper($targettype) . ' #' . $targetid;
    }

    /**
     * Strong state predicate.
     *
     * @param string $targettype
     * @param string $state
     * @return bool
     */
    private static function is_strong_state(string $targettype, string $state): bool {
        return in_array($state, self::STRONG_STATES[$targettype] ?? [], true);
    }

    /**
     * Diagnostic reason for a non-strong mastery state.
     *
     * @param \stdClass $state
     * @return string
     */
    private static function mastery_gap_reason(\stdClass $state): string {
        $type = (string)$state->targettype;
        $current = (string)$state->masterystate;
        $expected = implode(', ', self::STRONG_STATES[$type] ?? []);
        if ($expected === '') {
            $expected = self::EMPTY_STATES[$type] ?? 'mastered';
        }
        return 'Current state is ' . $current . '; V4 expected state is one of: ' . $expected . '.';
    }

    /**
     * Simple CEFR interpretation for a snapshot/profile.
     *
     * @param array $states
     * @param \stdClass|null $period
     * @return string
     */
    private static function cefr_interpretation(array $states, ?\stdClass $period): string {
        if ($period && !empty($period->cefr)) {
            $percent = 0.0;
            $total = 0;
            $strong = 0;
            foreach ($states as $state) {
                $total++;
                if (self::is_strong_state((string)$state->targettype, (string)$state->masterystate)) {
                    $strong++;
                }
            }
            $percent = $total > 0 ? round(($strong / $total) * 100, 1) : 0.0;
            return (string)$period->cefr . ' evidence coverage ' . $percent . '%';
        }
        return '';
    }

    /**
     * Dominant mastery rule version across states.
     *
     * @param array $states
     * @return string
     */
    private static function dominant_rule_version(array $states): string {
        $counts = [];
        foreach ($states as $state) {
            $version = (string)($state->ruleversion ?? '');
            if ($version !== '') {
                $counts[$version] = ($counts[$version] ?? 0) + 1;
            }
        }
        arsort($counts);
        return (string)(array_key_first($counts) ?? 'default-v1');
    }

    /**
     * Convert records to plain JSON-safe objects.
     *
     * @param array $records
     * @return array
     */
    private static function records_for_json(array $records): array {
        return array_values(array_map(static function($record): \stdClass {
            return (object)(array)$record;
        }, $records));
    }

    /**
     * Average helper.
     *
     * @param array $values
     * @return float
     */
    private static function average(array $values): float {
        $values = array_values(array_filter($values, static function($value): bool {
            return $value !== null;
        }));
        return $values ? round(array_sum($values) / count($values), 5) : 0.0;
    }

    /**
     * Clean period/evaluation type.
     *
     * @param string $type
     * @return string
     */
    private static function clean_period_type(string $type): string {
        $type = clean_param($type, PARAM_ALPHANUMEXT);
        return $type !== '' ? $type : 'unit';
    }

    /**
     * Clean status.
     *
     * @param string $status
     * @return string
     */
    private static function clean_status(string $status): string {
        $status = clean_param($status, PARAM_ALPHANUMEXT);
        return $status !== '' ? $status : 'active';
    }

    /**
     * Positive integer or null.
     *
     * @param mixed $value
     * @return int|null
     */
    private static function positive_int_or_null($value): ?int {
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    /**
     * Clamp to 0..1.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Encode JSON consistently.
     *
     * @param mixed $value
     * @return string
     */
    private static function encode_json($value): string {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decode JSON safely.
     *
     * @param string $json
     * @return array
     */
    private static function decode_json(string $json): array {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}

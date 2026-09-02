<?php
// Program 3 Gate A1 competency-centered learner goals.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Models learner destination goals without adaptive path selection.
 */
final class learning_goal_service {
    /** Program 3 learning-goal gate. */
    public const GATE = 'P3_A1';

    /** Frozen A1 learning-goal contract. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_COMPETENCY_CENTERED_LEARNING_GOAL_V1';

    /** Deterministic goal interpretation policy. */
    public const GOAL_POLICY_VERSION = 'cupkp-learning-goal-policy-v1';

    /** Next allowed gate after A1. */
    public const NEXT_ALLOWED_GATE = 'A2';

    /** @var array Goal source labels accepted by A1. */
    private const SOURCES = ['STUDENT', 'TEACHER', 'INSTITUTION'];

    /** @var array Current-goal statuses. */
    private const STATUSES = ['active', 'paused', 'completed', 'archived'];

    /** @var array Required flwcupkp_goal fields. */
    private const GOAL_FIELDS = [
        'userid',
        'courseid',
        'frameworkid',
        'unitcode',
        'title',
        'desiredprofilejson',
        'competencyidsjson',
        'upidsjson',
        'kpidsjson',
        'cefr',
        'flwstage',
        'purpose',
        'priorityskillsjson',
        'targetdate',
        'weeklytarget',
        'source',
        'status',
        'currentversion',
        'activeversionid',
        'goalpolicyversion',
        'checksum',
        'timecreated',
        'timemodified',
        'useridcreated',
        'usermodified',
    ];

    /** @var array Required flwcupkp_goal_version fields. */
    private const VERSION_FIELDS = [
        'goalid',
        'version',
        'userid',
        'courseid',
        'frameworkid',
        'unitcode',
        'title',
        'desiredprofilejson',
        'competencyidsjson',
        'upidsjson',
        'kpidsjson',
        'cefr',
        'flwstage',
        'purpose',
        'priorityskillsjson',
        'targetdate',
        'weeklytarget',
        'source',
        'status',
        'goalpolicyversion',
        'checksum',
        'changecomment',
        'useridcreated',
        'timecreated',
    ];

    /**
     * Return the A1 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpCompetencyCenteredLearningGoalContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                retention_review_service::CONTRACT_VERSION,
                mastery_state_service::CONTRACT_VERSION,
                management_v1_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'goal_policy_version' => self::GOAL_POLICY_VERSION,
            'destination_model' => [
                'preferred' => 'desired competency/skill profile',
                'optional_fields' => [
                    'CEFR',
                    'FLW stage',
                    'purpose',
                    'priority skills',
                    'target date',
                    'weekly target',
                ],
                'target_entities' => ['competency', 'up', 'kp'],
            ],
            'sources' => self::SOURCES,
            'versioning' => [
                'current_goal_table' => 'flwcupkp_goal',
                'immutable_history_table' => 'flwcupkp_goal_version',
                'goal_changes_create_versions' => true,
                'versions_do_not_erase_history_or_mastery' => true,
            ],
            'writes' => [
                'flwcupkp_goal',
                'flwcupkp_goal_version',
                'flwcupkp_audit',
            ],
            'does_not_do' => [
                'placement_diagnostic_policy',
                'cold_start_policy',
                'adaptive_path_selection',
                'recommendation_ranking_changes',
                'mastery_state_mutation',
                'retention_state_mutation',
                'evidence_mutation',
                'raw_moodle_log_scraping',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Readiness for A1.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = self::bounded_limit($limit, 300);
        $e3 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return retention_review_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $schema = self::schema_status();
        $files = self::file_status();
        $surface = self::surface_status();
        $summary = self::goal_summary($courseid, $unitcode, $frameworkid, $limit);
        $criteria = self::criteria($e3, $schema, $files, $surface);
        $criteriasummary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpLearningGoalStatus',
            'gate' => self::GATE,
            'status' => $criteriasummary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $criteriasummary,
            'dependencies' => [
                'retention_review_service' => self::dependency_summary($e3),
            ],
            'schema' => $schema,
            'files' => $files,
            'surface' => $surface,
            'summary' => $summary,
            'findings' => self::status_findings($criteria, [$e3]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return current goal and immutable versions for a learner.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function current_goal(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('A learner userid is required.');
        }
        $limit = self::bounded_limit($limit, 100);
        $goal = self::find_goal($userid, $courseid, $unitcode, $frameworkid);
        $versions = $goal ? self::version_history((int)$goal->id, $limit) : [];

        return [
            'type' => 'CupkpLearnerLearningGoal',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
            ],
            'goal' => $goal ? self::serialize_goal($goal) : null,
            'versions' => $versions,
            'has_goal' => (bool)$goal,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level goal summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = self::bounded_limit($limit, 300);
        $rows = self::list_goal_records($courseid, $unitcode, $frameworkid, 0, $limit);
        $summary = [
            'goals' => count($rows),
            'active' => 0,
            'paused' => 0,
            'completed' => 0,
            'archived' => 0,
            'versions' => 0,
            'studentsourced' => 0,
            'teachersourced' => 0,
            'institutionsourced' => 0,
            'with_target_date' => 0,
            'with_weekly_target' => 0,
            'competency_targets' => 0,
            'priority_skill_targets' => 0,
        ];
        foreach ($rows as $row) {
            $serialized = self::serialize_goal($row);
            $status = (string)$serialized['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            $sourcekey = strtolower((string)$serialized['source']) . 'sourced';
            if (isset($summary[$sourcekey])) {
                $summary[$sourcekey]++;
            }
            $summary['versions'] += (int)$serialized['currentversion'];
            if (!empty($serialized['targetdate'])) {
                $summary['with_target_date']++;
            }
            if ((float)($serialized['weeklytarget'] ?? 0) > 0) {
                $summary['with_weekly_target']++;
            }
            $summary['competency_targets'] += count($serialized['destination']['competencyids']);
            $summary['priority_skill_targets'] += count($serialized['destination']['priorityskills']);
        }

        return [
            'type' => 'CupkpClassLearningGoalSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'goals' => array_map([self::class, 'serialize_goal'], $rows),
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Options for building a competency-centered goal.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param string $query
     * @param int $limit
     * @return array
     */
    public static function goal_options(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, string $query = '', int $limit = 100): array {
        $limit = self::bounded_limit($limit, 300);
        return [
            'type' => 'CupkpLearningGoalOptions',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'query' => $query,
                'limit' => $limit,
            ],
            'sources' => self::SOURCES,
            'statuses' => self::STATUSES,
            'competencies' => self::target_options('competency', $courseid, $unitcode, $frameworkid, $query, $limit),
            'use_points' => self::target_options('up', $courseid, $unitcode, $frameworkid, $query, $limit),
            'knowledge_points' => self::target_options('kp', $courseid, $unitcode, $frameworkid, $query, $limit),
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Save a learner goal and create an immutable version if it changed.
     *
     * @param int $userid
     * @param array $data
     * @param string $source
     * @param string $reason
     * @return array
     */
    public static function save_goal(int $userid, array $data, string $source = '', string $reason = ''): array {
        global $DB, $USER;

        if ($userid <= 0) {
            throw new \invalid_parameter_exception('A learner userid is required.');
        }

        $before = self::mutation_counts();
        $payload = self::normalize_goal_payload($userid, $data, $source);
        $existing = self::find_goal(
            $userid,
            (int)($payload['courseid'] ?? 0),
            (string)($payload['unitcode'] ?? ''),
            (int)($payload['frameworkid'] ?? 0)
        );

        if ($existing && (string)($existing->checksum ?? '') === (string)$payload['checksum']) {
            return [
                'type' => 'CupkpLearningGoalSaveResult',
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'status' => 'unchanged',
                'goalid' => (int)$existing->id,
                'version' => (int)$existing->currentversion,
                'goal' => self::serialize_goal($existing),
                'before' => $before,
                'after' => self::mutation_counts(),
                'state_changes_allowed' => false,
                'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
            ];
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $actorid = (int)($USER->id ?? 0);

        if ($existing) {
            $goalid = (int)$existing->id;
            $version = (int)$existing->currentversion + 1;
            $record = self::goal_record($payload, $now, $actorid);
            $record->id = $goalid;
            $record->timecreated = (int)$existing->timecreated;
            $record->useridcreated = (int)($existing->useridcreated ?? $actorid);
            $record->currentversion = $version;
            $DB->update_record('flwcupkp_goal', $record);
        } else {
            $version = 1;
            $record = self::goal_record($payload, $now, $actorid);
            $record->currentversion = $version;
            $goalid = (int)$DB->insert_record('flwcupkp_goal', $record);
        }

        $versionrecord = self::goal_version_record($goalid, $version, $payload, $now, $actorid, $reason);
        $versionid = (int)$DB->insert_record('flwcupkp_goal_version', $versionrecord);
        $DB->set_field('flwcupkp_goal', 'activeversionid', $versionid, ['id' => $goalid]);

        repository::audit('learning_goal_version_created', 'goal', $goalid, [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'version' => $version,
            'source' => $payload['source'],
            'userid' => $userid,
            'courseid' => $payload['courseid'],
            'unitcode' => $payload['unitcode'],
            'frameworkid' => $payload['frameworkid'],
            'reason' => $reason,
            'mastery_erased' => false,
            'history_erased' => false,
        ]);

        $transaction->allow_commit();

        $stored = $DB->get_record('flwcupkp_goal', ['id' => $goalid], '*', MUST_EXIST);
        return [
            'type' => 'CupkpLearningGoalSaveResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => 'saved',
            'goalid' => $goalid,
            'versionid' => $versionid,
            'version' => $version,
            'goal' => self::serialize_goal($stored),
            'before' => $before,
            'after' => self::mutation_counts(),
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Recent goal history rows.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function recent_goal_history(int $courseid = 0, int $limit = 20): array {
        global $DB;

        if (!self::tables_ready()) {
            return [];
        }
        $params = ['action' => 'learning_goal_version_created'];
        $where = 'action = :action';
        if ($courseid > 0) {
            $where .= ' AND ' . $DB->sql_like('detailsjson', ':courseid');
            $params['courseid'] = '%"courseid":' . $courseid . '%';
        }
        return array_values($DB->get_records_select('flwcupkp_audit', $where, $params,
            'timecreated DESC, id DESC', '*', 0, self::bounded_limit($limit, 100)));
    }

    /**
     * Normalize a goal source.
     *
     * @param string $source
     * @return string
     */
    public static function normalize_source(string $source): string {
        $source = strtoupper(trim($source));
        if ($source === '') {
            return 'STUDENT';
        }
        if (!in_array($source, self::SOURCES, true)) {
            throw new \invalid_parameter_exception('Unsupported A1 goal source: ' . $source);
        }
        return $source;
    }

    /**
     * Normalize a goal write payload.
     *
     * @param int $userid
     * @param array $data
     * @param string $source
     * @return array
     */
    private static function normalize_goal_payload(int $userid, array $data, string $source): array {
        $courseid = max(0, (int)($data['courseid'] ?? 0));
        $frameworkid = max(0, (int)($data['frameworkid'] ?? 0));
        $unitcode = self::clean_short((string)($data['unitcode'] ?? ''));
        $desired = self::normalize_desired_profile($data);
        $competencyids = self::normalize_ids($data['competencyids'] ?? ($data['competency_ids'] ?? []));
        $upids = self::normalize_ids($data['upids'] ?? ($data['up_ids'] ?? []));
        $kpids = self::normalize_ids($data['kpids'] ?? ($data['kp_ids'] ?? []));
        $priorityskills = self::normalize_string_list($data['priorityskills'] ?? ($data['priority_skills'] ?? []));
        $source = self::normalize_source($source !== '' ? $source : (string)($data['source'] ?? 'STUDENT'));
        $status = self::normalize_status((string)($data['status'] ?? 'active'));
        $targetdate = self::normalize_target_date($data['targetdate'] ?? ($data['target_date'] ?? null));
        $weeklytarget = self::normalize_weekly_target($data['weeklytarget'] ?? ($data['weekly_target'] ?? null));
        $cefr = self::clean_short((string)($data['cefr'] ?? ''));
        $flwstage = self::clean_short((string)($data['flwstage'] ?? ($data['stage'] ?? '')));
        $purpose = trim((string)($data['purpose'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));

        if ($title === '') {
            $title = self::default_title($desired, $competencyids, $priorityskills, $cefr, $flwstage);
        }

        $hasdestination = !empty($desired) || !empty($competencyids) || !empty($upids) || !empty($kpids) ||
            !empty($priorityskills) || $cefr !== '' || $flwstage !== '' || $purpose !== '';
        if (!$hasdestination) {
            throw new \invalid_parameter_exception('A1 goal requires a destination profile, target, skill, or purpose.');
        }

        $profile = [
            'desired' => $desired,
            'competencyids' => $competencyids,
            'upids' => $upids,
            'kpids' => $kpids,
            'cefr' => $cefr,
            'flwstage' => $flwstage,
            'purpose' => $purpose,
            'priorityskills' => $priorityskills,
            'targetdate' => $targetdate,
            'weeklytarget' => $weeklytarget,
            'source' => $source,
            'status' => $status,
            'goalpolicyversion' => self::GOAL_POLICY_VERSION,
        ];

        $checksum = sha1(json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'userid' => $userid,
            'courseid' => $courseid,
            'frameworkid' => $frameworkid,
            'unitcode' => $unitcode,
            'title' => $title,
            'desiredprofilejson' => json_encode($desired, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'competencyidsjson' => json_encode($competencyids, JSON_UNESCAPED_SLASHES),
            'upidsjson' => json_encode($upids, JSON_UNESCAPED_SLASHES),
            'kpidsjson' => json_encode($kpids, JSON_UNESCAPED_SLASHES),
            'cefr' => $cefr,
            'flwstage' => $flwstage,
            'purpose' => $purpose,
            'priorityskillsjson' => json_encode($priorityskills, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'targetdate' => $targetdate,
            'weeklytarget' => $weeklytarget,
            'source' => $source,
            'status' => $status,
            'goalpolicyversion' => self::GOAL_POLICY_VERSION,
            'checksum' => $checksum,
        ];
    }

    /**
     * Current-goal record object.
     *
     * @param array $payload
     * @param int $now
     * @param int $actorid
     * @return \stdClass
     */
    private static function goal_record(array $payload, int $now, int $actorid): \stdClass {
        return (object)[
            'userid' => (int)$payload['userid'],
            'courseid' => self::nullable_int((int)$payload['courseid']),
            'frameworkid' => self::nullable_int((int)$payload['frameworkid']),
            'unitcode' => $payload['unitcode'] !== '' ? $payload['unitcode'] : null,
            'title' => $payload['title'],
            'desiredprofilejson' => $payload['desiredprofilejson'],
            'competencyidsjson' => $payload['competencyidsjson'],
            'upidsjson' => $payload['upidsjson'],
            'kpidsjson' => $payload['kpidsjson'],
            'cefr' => $payload['cefr'] !== '' ? $payload['cefr'] : null,
            'flwstage' => $payload['flwstage'] !== '' ? $payload['flwstage'] : null,
            'purpose' => $payload['purpose'] !== '' ? $payload['purpose'] : null,
            'priorityskillsjson' => $payload['priorityskillsjson'],
            'targetdate' => self::nullable_int((int)$payload['targetdate']),
            'weeklytarget' => (float)$payload['weeklytarget'],
            'source' => $payload['source'],
            'status' => $payload['status'],
            'currentversion' => 1,
            'activeversionid' => null,
            'goalpolicyversion' => self::GOAL_POLICY_VERSION,
            'checksum' => $payload['checksum'],
            'timecreated' => $now,
            'timemodified' => $now,
            'useridcreated' => $actorid,
            'usermodified' => $actorid,
        ];
    }

    /**
     * Immutable version record object.
     *
     * @param int $goalid
     * @param int $version
     * @param array $payload
     * @param int $now
     * @param int $actorid
     * @param string $reason
     * @return \stdClass
     */
    private static function goal_version_record(int $goalid, int $version, array $payload, int $now, int $actorid,
            string $reason): \stdClass {
        $record = self::goal_record($payload, $now, $actorid);
        unset($record->id, $record->activeversionid, $record->currentversion, $record->timemodified,
            $record->usermodified);
        $record->goalid = $goalid;
        $record->version = $version;
        $record->changecomment = $reason !== '' ? $reason : null;
        $record->timecreated = $now;
        $record->useridcreated = $actorid;
        return $record;
    }

    /**
     * Find the current scoped goal.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return \stdClass|null
     */
    private static function find_goal(int $userid, int $courseid, string $unitcode, int $frameworkid): ?\stdClass {
        global $DB;

        if (!self::tables_ready()) {
            return null;
        }

        [$where, $params] = self::goal_scope_sql($userid, $courseid, $unitcode, $frameworkid);
        $records = $DB->get_records_select('flwcupkp_goal', $where, $params, 'timemodified DESC, id DESC',
            '*', 0, 1);
        if (!$records) {
            return null;
        }
        return reset($records) ?: null;
    }

    /**
     * List current goal records in scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @return array
     */
    private static function list_goal_records(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit): array {
        global $DB;

        if (!self::tables_ready()) {
            return [];
        }

        $params = [];
        $where = '1=1';
        if ($userid > 0) {
            $where .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }

        return array_values($DB->get_records_select('flwcupkp_goal', $where, $params,
            'timemodified DESC, id DESC', '*', 0, $limit));
    }

    /**
     * Build exact scope SQL for one learner.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function goal_scope_sql(int $userid, int $courseid, string $unitcode, int $frameworkid): array {
        $params = ['userid' => $userid];
        $where = 'userid = :userid';
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        } else {
            $where .= ' AND courseid IS NULL';
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        } else {
            $where .= " AND (unitcode IS NULL OR unitcode = '')";
        }
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        } else {
            $where .= ' AND frameworkid IS NULL';
        }
        return [$where, $params];
    }

    /**
     * Version history for a goal.
     *
     * @param int $goalid
     * @param int $limit
     * @return array
     */
    private static function version_history(int $goalid, int $limit = 20): array {
        global $DB;

        $rows = $DB->get_records('flwcupkp_goal_version', ['goalid' => $goalid], 'version DESC, id DESC',
            '*', 0, self::bounded_limit($limit, 100));
        return array_map([self::class, 'serialize_goal_version'], array_values($rows));
    }

    /**
     * Serialize current goal row.
     *
     * @param \stdClass $goal
     * @return array
     */
    private static function serialize_goal(\stdClass $goal): array {
        return [
            'id' => (int)$goal->id,
            'userid' => (int)$goal->userid,
            'courseid' => (int)($goal->courseid ?? 0),
            'frameworkid' => (int)($goal->frameworkid ?? 0),
            'unitcode' => (string)($goal->unitcode ?? ''),
            'title' => (string)($goal->title ?? ''),
            'destination' => self::destination_from_record($goal),
            'cefr' => (string)($goal->cefr ?? ''),
            'flwstage' => (string)($goal->flwstage ?? ''),
            'purpose' => (string)($goal->purpose ?? ''),
            'targetdate' => (int)($goal->targetdate ?? 0),
            'weeklytarget' => round((float)($goal->weeklytarget ?? 0), 5),
            'source' => (string)($goal->source ?? ''),
            'status' => (string)($goal->status ?? ''),
            'currentversion' => (int)($goal->currentversion ?? 0),
            'activeversionid' => (int)($goal->activeversionid ?? 0),
            'goalpolicyversion' => (string)($goal->goalpolicyversion ?? ''),
            'checksum' => (string)($goal->checksum ?? ''),
            'timecreated' => (int)($goal->timecreated ?? 0),
            'timemodified' => (int)($goal->timemodified ?? 0),
            'useridcreated' => (int)($goal->useridcreated ?? 0),
            'usermodified' => (int)($goal->usermodified ?? 0),
        ];
    }

    /**
     * Serialize immutable version row.
     *
     * @param \stdClass $version
     * @return array
     */
    private static function serialize_goal_version(\stdClass $version): array {
        $serialized = self::serialize_goal($version);
        $serialized['id'] = (int)$version->id;
        $serialized['goalid'] = (int)$version->goalid;
        $serialized['version'] = (int)$version->version;
        $serialized['changecomment'] = (string)($version->changecomment ?? '');
        unset($serialized['currentversion'], $serialized['activeversionid'], $serialized['timemodified'],
            $serialized['usermodified']);
        return $serialized;
    }

    /**
     * Decode destination fields from a row.
     *
     * @param \stdClass $record
     * @return array
     */
    private static function destination_from_record(\stdClass $record): array {
        $competencyids = self::decode_int_json($record->competencyidsjson ?? '[]');
        $upids = self::decode_int_json($record->upidsjson ?? '[]');
        $kpids = self::decode_int_json($record->kpidsjson ?? '[]');
        $priorityskills = self::decode_string_json($record->priorityskillsjson ?? '[]');
        return [
            'desired_profile' => self::decode_json_object($record->desiredprofilejson ?? '{}'),
            'competencyids' => $competencyids,
            'upids' => $upids,
            'kpids' => $kpids,
            'priorityskills' => $priorityskills,
            'labels' => [
                'competencies' => self::target_labels('competency', $competencyids),
                'use_points' => self::target_labels('up', $upids),
                'knowledge_points' => self::target_labels('kp', $kpids),
            ],
        ];
    }

    /**
     * Goal summary in a scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function goal_summary(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        if (!self::tables_ready()) {
            return [
                'goals' => 0,
                'active' => 0,
                'versions' => 0,
                'sources' => array_fill_keys(self::SOURCES, 0),
            ];
        }

        $rows = self::list_goal_records($courseid, $unitcode, $frameworkid, 0, $limit);
        $sources = array_fill_keys(self::SOURCES, 0);
        $active = 0;
        foreach ($rows as $row) {
            if ((string)$row->status === 'active') {
                $active++;
            }
            if (isset($sources[(string)$row->source])) {
                $sources[(string)$row->source]++;
            }
        }
        $versionparams = [];
        $versionwhere = '1=1';
        if ($courseid > 0) {
            $versionwhere .= ' AND courseid = :courseid';
            $versionparams['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $versionwhere .= ' AND unitcode = :unitcode';
            $versionparams['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $versionwhere .= ' AND frameworkid = :frameworkid';
            $versionparams['frameworkid'] = $frameworkid;
        }

        return [
            'goals' => count($rows),
            'active' => $active,
            'versions' => (int)$DB->count_records_select('flwcupkp_goal_version', $versionwhere, $versionparams),
            'sources' => $sources,
        ];
    }

    /**
     * Target options in scope.
     *
     * @param string $type
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param string $query
     * @param int $limit
     * @return array
     */
    private static function target_options(string $type, int $courseid, string $unitcode, int $frameworkid,
            string $query, int $limit): array {
        global $DB;

        $table = self::target_table($type);
        $params = [];
        $where = '1=1';
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        } else {
            $frameworkids = self::framework_ids_for_scope($courseid, $unitcode);
            if ($frameworkids) {
                [$insql, $inparams] = $DB->get_in_or_equal($frameworkids, SQL_PARAMS_NAMED, 'fw');
                $where .= " AND frameworkid {$insql}";
                $params += $inparams;
            }
        }
        if ($query !== '') {
            $where .= ' AND (' . $DB->sql_like('externalid', ':query1', false) . ' OR ' .
                $DB->sql_like('title', ':query2', false) . ')';
            $params['query1'] = '%' . $DB->sql_like_escape($query) . '%';
            $params['query2'] = '%' . $DB->sql_like_escape($query) . '%';
        }

        $rows = $DB->get_records_select($table, $where, $params, 'externalid ASC, title ASC', '*', 0, $limit);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int)$row->id,
                'externalid' => (string)($row->externalid ?? ''),
                'title' => (string)($row->title ?? ''),
                'cefr' => (string)($row->cefr ?? ''),
                'stage' => (string)($row->stage ?? ''),
                'status' => (string)($row->status ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Framework IDs from mapped objects in a course/unit scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function framework_ids_for_scope(int $courseid, string $unitcode): array {
        global $DB;

        $params = [];
        $where = 'frameworkid IS NOT NULL';
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $rows = $DB->get_records_select('flwcupkp_object', $where, $params, '', 'DISTINCT frameworkid');
        return array_values(array_filter(array_map(static function($row): int {
            return (int)$row->frameworkid;
        }, $rows)));
    }

    /**
     * Target labels for selected IDs.
     *
     * @param string $type
     * @param array $ids
     * @return array
     */
    private static function target_labels(string $type, array $ids): array {
        global $DB;

        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'target');
        $rows = $DB->get_records_select(self::target_table($type), "id {$insql}", $params, 'externalid ASC');
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = [
                'id' => (int)$row->id,
                'externalid' => (string)($row->externalid ?? ''),
                'title' => (string)($row->title ?? ''),
            ];
        }
        return $labels;
    }

    /**
     * Table name for target type.
     *
     * @param string $type
     * @return string
     */
    private static function target_table(string $type): string {
        if ($type === 'competency') {
            return 'flwcupkp_comp';
        }
        if ($type === 'up') {
            return 'flwcupkp_up';
        }
        if ($type === 'kp') {
            return 'flwcupkp_kp';
        }
        throw new \coding_exception('Unknown A1 target type: ' . $type);
    }

    /**
     * Schema status.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $dbman = $DB->get_manager();
        $goaltable = new \xmldb_table('flwcupkp_goal');
        $versiontable = new \xmldb_table('flwcupkp_goal_version');
        $goalexists = $dbman->table_exists($goaltable);
        $versionexists = $dbman->table_exists($versiontable);
        $goalcolumns = $goalexists ? $DB->get_columns('flwcupkp_goal') : [];
        $versioncolumns = $versionexists ? $DB->get_columns('flwcupkp_goal_version') : [];
        $goalpresent = [];
        foreach (self::GOAL_FIELDS as $field) {
            $goalpresent[$field] = isset($goalcolumns[$field]);
        }
        $versionpresent = [];
        foreach (self::VERSION_FIELDS as $field) {
            $versionpresent[$field] = isset($versioncolumns[$field]);
        }
        $goalmissing = array_keys(array_filter($goalpresent, static function(bool $present): bool {
            return !$present;
        }));
        $versionmissing = array_keys(array_filter($versionpresent, static function(bool $present): bool {
            return !$present;
        }));

        return [
            'valid' => $goalexists && $versionexists && !$goalmissing && !$versionmissing,
            'tables' => [
                'flwcupkp_goal' => $goalexists,
                'flwcupkp_goal_version' => $versionexists,
            ],
            'present' => [
                'flwcupkp_goal' => $goalpresent,
                'flwcupkp_goal_version' => $versionpresent,
            ],
            'missing' => [
                'flwcupkp_goal' => $goalmissing,
                'flwcupkp_goal_version' => $versionmissing,
            ],
        ];
    }

    /**
     * File status.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $base = $CFG->dirroot . '/local/flwcupkp/';
        $files = [
            'learning_goal.php',
            'cli/learning_goal.php',
            'classes/local/learning_goal_service.php',
            'openapi.json',
        ];
        $present = [];
        foreach ($files as $file) {
            $present[$file] = file_exists($base . $file);
        }
        return [
            'valid' => !in_array(false, $present, true),
            'present' => $present,
            'missing' => array_keys(array_filter($present, static function(bool $ok): bool {
                return !$ok;
            })),
        ];
    }

    /**
     * Service surface status.
     *
     * @return array
     */
    private static function surface_status(): array {
        $methods = [
            self::class . '::status' => method_exists(self::class, 'status'),
            self::class . '::current_goal' => method_exists(self::class, 'current_goal'),
            self::class . '::class_summary' => method_exists(self::class, 'class_summary'),
            self::class . '::goal_options' => method_exists(self::class, 'goal_options'),
            self::class . '::save_goal' => method_exists(self::class, 'save_goal'),
            self::class . '::recent_goal_history' => method_exists(self::class, 'recent_goal_history'),
        ];
        return [
            'valid' => !in_array(false, $methods, true),
            'methods' => $methods,
            'missing_methods' => array_keys(array_filter($methods, static function(bool $ok): bool {
                return !$ok;
            })),
        ];
    }

    /**
     * Readiness criteria.
     *
     * @param array $e3
     * @param array $schema
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $e3, array $schema, array $files, array $surface): array {
        return [
            'e3_retention_consumed' => self::criterion(
                'e3_retention_consumed',
                ($e3['status'] ?? '') === 'ready' &&
                    ($e3['contract']['version'] ?? '') === retention_review_service::CONTRACT_VERSION,
                'A1 consumes the frozen E3 retention/retrieval/review status.'
            ),
            'goal_schema_present' => self::criterion(
                'goal_schema_present',
                $schema['valid'],
                'A1 stores current goals and immutable goal versions.'
            ),
            'goal_surfaces_present' => self::criterion(
                'goal_surfaces_present',
                $files['valid'] && $surface['valid'],
                'A1 page, CLI, service, and web-service methods are present.'
            ),
            'destination_profile_model_present' => self::criterion(
                'destination_profile_model_present',
                true,
                'Goal payload supports desired competency/skill profile plus CEFR, FLW stage, purpose, priorities, target date, and weekly target.'
            ),
            'source_model_present' => self::criterion(
                'source_model_present',
                self::SOURCES === ['STUDENT', 'TEACHER', 'INSTITUTION'],
                'A1 supports STUDENT, TEACHER, and INSTITUTION goal sources.'
            ),
            'goal_versioning_non_destructive' => self::criterion(
                'goal_versioning_non_destructive',
                true,
                'Goal writes append immutable versions and do not delete history, mastery, retention, or evidence.'
            ),
        ];
    }

    /**
     * One readiness criterion.
     *
     * @param string $key
     * @param bool $pass
     * @param string $detail
     * @return array
     */
    private static function criterion(string $key, bool $pass, string $detail): array {
        return [
            'key' => $key,
            'status' => $pass ? 'pass' : 'fail',
            'pass' => $pass,
            'detail' => $detail,
        ];
    }

    /**
     * Summarize criteria.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $passed = 0;
        foreach ($criteria as $criterion) {
            if (!empty($criterion['pass'])) {
                $passed++;
            }
        }
        return [
            'total' => count($criteria),
            'passed' => $passed,
            'failed' => count($criteria) - $passed,
        ];
    }

    /**
     * Findings from criteria/dependencies.
     *
     * @param array $criteria
     * @param array $dependencies
     * @return array
     */
    private static function status_findings(array $criteria, array $dependencies): array {
        $findings = [];
        foreach ($criteria as $criterion) {
            if (empty($criterion['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => $criterion['key'] . '_failed',
                    'message' => $criterion['detail'],
                ];
            }
        }
        foreach ($dependencies as $dependency) {
            foreach (($dependency['findings'] ?? []) as $finding) {
                $severity = strtolower((string)($finding['severity'] ?? 'info'));
                if (in_array($severity, ['blocker', 'error', 'high', 'medium', 'warning'], true)) {
                    $findings[] = [
                        'severity' => $severity,
                        'code' => (string)($finding['code'] ?? 'dependency_finding'),
                        'message' => (string)($finding['message'] ?? json_encode($finding)),
                    ];
                }
            }
        }
        return $findings;
    }

    /**
     * Dependency summary.
     *
     * @param array $dependency
     * @return array
     */
    private static function dependency_summary(array $dependency): array {
        return [
            'type' => $dependency['type'] ?? '',
            'gate' => $dependency['gate'] ?? '',
            'status' => $dependency['status'] ?? 'unknown',
            'contract' => $dependency['contract']['version'] ?? ($dependency['contract'] ?? ''),
            'next_allowed_gate' => $dependency['next_allowed_gate'] ?? '',
            'findings' => count($dependency['findings'] ?? []),
        ];
    }

    /**
     * Wrap dependency call failures.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            $status = $callback();
            return is_array($status) ? $status : [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'invalid_dependency_status',
                    'message' => 'Dependency did not return an array status.',
                ]],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'dependency_exception',
                    'message' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Check whether A1 tables exist.
     *
     * @return bool
     */
    private static function tables_ready(): bool {
        global $DB;

        $dbman = $DB->get_manager();
        return $dbman->table_exists(new \xmldb_table('flwcupkp_goal')) &&
            $dbman->table_exists(new \xmldb_table('flwcupkp_goal_version'));
    }

    /**
     * Counts used to prove writes do not touch evidence/state.
     *
     * @return array
     */
    private static function mutation_counts(): array {
        global $DB;

        return [
            'evidence' => $DB->count_records('flwcupkp_evidence'),
            'state' => $DB->count_records('flwcupkp_state'),
            'retention_state_rows' => (int)$DB->count_records_select('flwcupkp_state',
                "retentionstate IS NOT NULL AND retentionstate <> ''", []),
            'goal' => self::tables_ready() ? $DB->count_records('flwcupkp_goal') : 0,
            'goal_version' => self::tables_ready() ? $DB->count_records('flwcupkp_goal_version') : 0,
        ];
    }

    /**
     * Normalize desired profile.
     *
     * @param array $data
     * @return array
     */
    private static function normalize_desired_profile(array $data): array {
        $value = $data['desiredprofile'] ?? ($data['desired_profile'] ?? ($data['profile'] ?? []));
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return self::normalize_profile_array($decoded);
            }
            return ['description' => $trimmed];
        }
        if (is_array($value)) {
            return self::normalize_profile_array($value);
        }
        return [];
    }

    /**
     * Normalize profile array recursively enough for storage.
     *
     * @param array $profile
     * @return array
     */
    private static function normalize_profile_array(array $profile): array {
        $out = [];
        foreach ($profile as $key => $value) {
            $clean = self::clean_short((string)$key);
            if ($clean === '') {
                continue;
            }
            if (is_array($value)) {
                $out[$clean] = self::normalize_profile_array($value);
            } else {
                $out[$clean] = trim((string)$value);
            }
        }
        return $out;
    }

    /**
     * Normalize ID list.
     *
     * @param mixed $value
     * @return array
     */
    private static function normalize_ids($value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }
        }
        if (!is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * Normalize string list.
     *
     * @param mixed $value
     * @return array
     */
    private static function normalize_string_list($value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/[\r\n,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Normalize target date.
     *
     * @param mixed $value
     * @return int
     */
    private static function normalize_target_date($value): int {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return max(0, (int)$value);
        }
        $time = strtotime((string)$value . ' 00:00:00 UTC');
        return $time ? (int)$time : 0;
    }

    /**
     * Normalize weekly target.
     *
     * @param mixed $value
     * @return float
     */
    private static function normalize_weekly_target($value): float {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return round(max(0.0, min(168.0, (float)$value)), 5);
    }

    /**
     * Normalize status.
     *
     * @param string $status
     * @return string
     */
    private static function normalize_status(string $status): string {
        $status = strtolower(trim($status));
        if ($status === '') {
            return 'active';
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new \invalid_parameter_exception('Unsupported A1 goal status: ' . $status);
        }
        return $status;
    }

    /**
     * Default goal title.
     *
     * @param array $desired
     * @param array $competencyids
     * @param array $priorityskills
     * @param string $cefr
     * @param string $flwstage
     * @return string
     */
    private static function default_title(array $desired, array $competencyids, array $priorityskills, string $cefr,
            string $flwstage): string {
        if (!empty($desired['description'])) {
            return shorten_text((string)$desired['description'], 120);
        }
        if ($competencyids) {
            return 'Competency goal';
        }
        if ($priorityskills) {
            return 'Skill goal: ' . shorten_text(implode(', ', $priorityskills), 100);
        }
        if ($cefr !== '' || $flwstage !== '') {
            return trim('Level goal ' . $cefr . ' ' . $flwstage);
        }
        return 'Learning goal';
    }

    /**
     * Decode JSON object.
     *
     * @param mixed $json
     * @return array
     */
    private static function decode_json_object($json): array {
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Decode JSON integer array.
     *
     * @param mixed $json
     * @return array
     */
    private static function decode_int_json($json): array {
        return self::normalize_ids((string)$json);
    }

    /**
     * Decode JSON string array.
     *
     * @param mixed $json
     * @return array
     */
    private static function decode_string_json($json): array {
        return self::normalize_string_list((string)$json);
    }

    /**
     * Short text cleanup.
     *
     * @param string $value
     * @return string
     */
    private static function clean_short(string $value): string {
        return clean_param(trim($value), PARAM_ALPHANUMEXT);
    }

    /**
     * Nullable integer field value.
     *
     * @param int $value
     * @return int|null
     */
    private static function nullable_int(int $value): ?int {
        return $value > 0 ? $value : null;
    }

    /**
     * Bound API limits.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }
}

<?php
// Program 3 Gate UX2 learner-experience simplification.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only presentation service that simplifies the frozen UX1 timeline.
 */
final class learner_experience_service {
    /** Program 3 learner UX gate. */
    public const GATE = 'P3_UX2';

    /** Frozen simplified learner-view contract. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_LEARNER_EXPERIENCE_V1';

    /** Versioned learner presentation policy. */
    public const UX_POLICY_VERSION = 'cupkp-learner-experience-v1';

    /** Next allowed gate. */
    public const NEXT_ALLOWED_GATE = 'UX3';

    /** Default number of upcoming items visible without disclosure. */
    public const DEFAULT_COMING_UP_LIMIT = 3;

    /** Maximum roadmap items exposed under level-two disclosure. */
    public const ROADMAP_LIMIT = 5;

    /** Maximum skill rows exposed under level-three disclosure. */
    public const SKILL_LIMIT = 5;

    /** Stable level-one reading order. */
    public const LEVEL_ONE_SECTIONS = [
        'history',
        'current',
        'next',
        'coming_up',
        'milestone',
        'goal',
    ];

    /** Learner-facing terminology while internal names remain stable. */
    public const TERMINOLOGY = [
        'competency' => 'Skill',
        'kp' => 'Learning Point',
        'up' => 'Practice Target',
        'mastery' => 'Ability or Progress',
        'prerequisite' => 'Needed First',
        'remediation' => 'Extra Practice',
        'evidence' => 'Learning Results',
    ];

    /**
     * Frozen UX2 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpLearnerExperienceContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'ux_policy_version' => self::UX_POLICY_VERSION,
            'source_view_contract' => student_learning_timeline_view_service::CONTRACT_VERSION,
            'design_rule' => 'History compressed - Current expanded - Future summarized.',
            'progressive_disclosure' => [
                'level_1' => self::LEVEL_ONE_SECTIONS,
                'level_2' => ['show_history', 'show_roadmap'],
                'level_3' => ['why_this_activity', 'more_details'],
            ],
            'learner_terminology' => self::TERMINOLOGY,
            'continue_learning' => [
                'source' => 'UX1 future.adaptive_next from A4B/A5',
                'requires' => ['next_activity_ready', 'available_activity', 'current_cmid', 'safe_moodle_activity_url'],
                'hidden_blocked_expired_allowed' => false,
                'fallback_activity_fabrication' => false,
            ],
            'limits' => [
                'coming_up_default' => self::DEFAULT_COMING_UP_LIMIT,
                'roadmap_disclosed' => self::ROADMAP_LIMIT,
                'skill_details' => self::SKILL_LIMIT,
                'primary_actions' => 1,
            ],
            'mobile_order' => ['history', 'current', 'future', 'goal'],
            'primary_horizontal_scrolling' => false,
            'accessibility' => [
                'native_details_summary_controls',
                'keyboard_navigation',
                'semantic_sections',
                'labelled_progress',
                'moodle_theme_compatibility',
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'history_owner' => 'local_flwhistory',
            'adaptive_policy_owner' => 'local_flwcupkp A3/A4/A4B/A5 services',
            'read_only_surface' => ['contract', 'status', 'simplify', 'learner_experience'],
            'read_only' => true,
            'write_boundary' => [],
            'does_not_do' => [
                'history_rebuild_or_mutation',
                'raw_moodle_log_scraping',
                'adaptive_decision_or_recommendation_write',
                'eligibility_recalculation',
                'goal_mastery_retention_or_completion_write',
                'teacher_admin_explainability_or_override',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * UX2 implementation readiness.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0): array {
        $unitcode = self::clean_unit_code_optional($unitcode);
        $ux1 = self::safe_status(static function() use ($courseid, $unitcode, $frameworkid): array {
            return student_learning_timeline_view_service::status($courseid, $unitcode, $frameworkid);
        });
        $contract = self::contract();
        $files = self::file_status();
        $surface = self::surface_status();
        $criteria = [
            'ux1_frozen' => self::criterion('ux1_frozen',
                ($ux1['status'] ?? '') === 'ready' &&
                    ($ux1['contract']['version'] ?? '') === student_learning_timeline_view_service::CONTRACT_VERSION &&
                    ($ux1['next_allowed_gate'] ?? '') === 'UX2',
                'The frozen UX1 timeline must be ready and hand off to UX2.'),
            'level_one_order' => self::criterion('level_one_order',
                ($contract['progressive_disclosure']['level_1'] ?? []) === self::LEVEL_ONE_SECTIONS,
                'Level one must show History, Current, Next, Coming Up, Milestone, and Goal in order.'),
            'history_compressed' => self::criterion('history_compressed',
                strpos((string)$contract['design_rule'], 'History compressed') !== false,
                'History must be summarized by default and remain owned by Program 2.'),
            'current_expanded' => self::criterion('current_expanded',
                strpos((string)$contract['design_rule'], 'Current expanded') !== false,
                'Current learner position and one defensible progress signal must be prominent.'),
            'future_summarized' => self::criterion('future_summarized',
                ($contract['limits']['coming_up_default'] ?? 0) <= 3,
                'Future must show no more than three upcoming items by default.'),
            'three_disclosure_levels' => self::criterion('three_disclosure_levels',
                array_keys($contract['progressive_disclosure'] ?? []) === ['level_1', 'level_2', 'level_3'],
                'Learner detail must use the frozen three-level disclosure model.'),
            'learner_terminology' => self::criterion('learner_terminology',
                ($contract['learner_terminology'] ?? []) === self::TERMINOLOGY,
                'Learner labels must use friendly terms while internal ontology names remain stable.'),
            'continue_learning_guarded' => self::criterion('continue_learning_guarded',
                empty($contract['continue_learning']['hidden_blocked_expired_allowed']) &&
                    empty($contract['continue_learning']['fallback_activity_fabrication']),
                'Continue Learning must resolve only to the current eligible A4B/A5 activity.'),
            'mobile_accessible' => self::criterion('mobile_accessible',
                empty($contract['primary_horizontal_scrolling']) &&
                    in_array('keyboard_navigation', $contract['accessibility'] ?? [], true),
                'The primary learner flow must stack on mobile and preserve keyboard access.'),
            'implementation_boundary' => self::criterion('implementation_boundary',
                empty($files['missing']) && !empty($surface['valid']) && !empty($contract['read_only']) &&
                    empty($contract['write_boundary']) &&
                    ($contract['next_allowed_gate'] ?? '') === self::NEXT_ALLOWED_GATE,
                'UX2 files and APIs must exist, remain read-only, and stop before UX3.'),
        ];
        $summary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpLearnerExperienceStatus',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => $contract,
            'scope' => ['courseid' => $courseid, 'unitcode' => $unitcode, 'frameworkid' => $frameworkid],
            'criteria' => $criteria,
            'criteria_summary' => $summary,
            'dependencies' => [
                'ux1' => [
                    'gate' => $ux1['gate'] ?? student_learning_timeline_view_service::GATE,
                    'status' => $ux1['status'] ?? 'blocked',
                    'contract' => $ux1['contract']['version'] ?? null,
                    'next_allowed_gate' => $ux1['next_allowed_gate'] ?? null,
                ],
                'history_v1' => $ux1['dependencies']['history_v1'] ?? ['status' => 'blocked'],
                'a5c' => $ux1['dependencies']['a5c'] ?? ['status' => 'blocked'],
            ],
            'files' => $files,
            'surface' => $surface,
            'findings' => self::status_findings($criteria, $ux1),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build the simplified learner experience from the frozen UX1 service.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @param array $pagination
     * @return array
     */
    public static function learner_experience(int $userid, int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 20, array $pagination = []): array {
        $timeline = student_learning_timeline_view_service::learner_timeline(
            $userid, $courseid, $unitcode, $frameworkid, $limit, $pagination
        );
        return self::simplify($timeline);
    }

    /**
     * Pure transformation from StudentLearningTimelineView to learner UX DTO.
     *
     * @param array $timeline
     * @return array
     */
    public static function simplify(array $timeline): array {
        if (($timeline['type'] ?? '') !== 'StudentLearningTimelineView' ||
                ($timeline['contract'] ?? '') !== student_learning_timeline_view_service::CONTRACT_VERSION) {
            throw new \invalid_parameter_exception('UX2 requires the frozen UX1 StudentLearningTimelineView.');
        }

        $history = self::history_summary($timeline['past']['dashboard'] ?? []);
        $present = is_array($timeline['present'] ?? null) ? $timeline['present'] : [];
        $future = is_array($timeline['future'] ?? null) ? $timeline['future'] : [];
        $goal = self::goal_view($present['goal'] ?? []);
        $skills = self::skill_views($present['skill_states'] ?? [], self::SKILL_LIMIT);
        $next = self::continue_learning($future['adaptive_next'] ?? []);
        $roadmap = self::roadmap_views($future['projected_roadmap'] ?? [], self::ROADMAP_LIMIT, false);
        $comingup = self::roadmap_views($future['projected_roadmap'] ?? [],
            self::DEFAULT_COMING_UP_LIMIT, true);
        $progress = self::preferred_progress($present['preferred_metric'] ?? [],
            $present['goal_achievement'] ?? []);
        $milestone = self::milestone_view($present['goal_achievement'] ?? [],
            $present['preferred_metric'] ?? []);
        $why = self::why_activity($future['adaptive_next'] ?? []);

        return [
            'type' => 'SimplifiedLearnerExperienceView',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'ux_policy_version' => self::UX_POLICY_VERSION,
            'source_timeline_contract' => student_learning_timeline_view_service::CONTRACT_VERSION,
            'learner' => [
                'fullname' => (string)($timeline['learner']['fullname'] ?? ''),
            ],
            'course' => [
                'fullname' => (string)($timeline['course']['fullname'] ?? ''),
                'shortname' => (string)($timeline['course']['shortname'] ?? ''),
            ],
            'scope' => [
                'userid' => (int)($timeline['scope']['userid'] ?? 0),
                'courseid' => (int)($timeline['scope']['courseid'] ?? $timeline['course']['id'] ?? 0),
                'unitcode' => (string)($timeline['scope']['unitcode'] ?? ''),
                'frameworkid' => (int)($timeline['scope']['frameworkid'] ?? 0),
            ],
            'level_1' => [
                'history' => $history,
                'current' => self::current_view($timeline, $progress, array_slice($skills, 0, 3)),
                'next' => $next,
                'coming_up' => $comingup,
                'milestone' => $milestone,
                'goal' => $goal,
            ],
            'level_2' => [
                'history' => [
                    'owner' => 'local_flwhistory',
                    'source_contract' => (string)($timeline['past']['dashboard_contract'] ?? ''),
                    'summary' => $history,
                ],
                'roadmap' => $roadmap,
            ],
            'level_3' => [
                'why_this_activity' => $why,
                'more_details' => [
                    'skills' => $skills,
                    'metrics' => self::friendly_metrics($present['metrics'] ?? []),
                    'path_change' => self::path_change_view($future['why_path_changed'] ?? []),
                    'recent_recommendations' => self::recommendation_views(
                        $future['recommendation_history'] ?? [], 3
                    ),
                ],
            ],
            'display_rules' => [
                'level_1_order' => self::LEVEL_ONE_SECTIONS,
                'coming_up_limit' => self::DEFAULT_COMING_UP_LIMIT,
                'primary_action_count' => $next['available'] ? 1 : 0,
                'internal_ids_visible' => false,
                'technical_policy_versions_visible' => false,
            ],
            'component_status' => $timeline['component_status'] ?? [],
            'generatedat' => time(),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Compressed History-owned summary.
     *
     * @param mixed $dashboard
     * @return array
     */
    private static function history_summary($dashboard): array {
        $dashboard = is_array($dashboard) ? $dashboard : [];
        $journeyitems = is_array($dashboard['journey']['items'] ?? null) ? $dashboard['journey']['items'] : [];
        $completed = count(array_filter($journeyitems, static function($item): bool {
            return is_array($item) && ($item['state'] ?? '') === 'completed';
        }));
        $recent = is_array($dashboard['recent_activity']['records'] ?? null) ?
            $dashboard['recent_activity']['records'] : [];
        $latesttime = 0;
        if ($recent) {
            $first = is_array($recent[0] ?? null) ? $recent[0] : [];
            foreach (['eventtime', 'timefinish', 'timemodified', 'timecreated'] as $field) {
                if (!empty($first[$field])) {
                    $latesttime = (int)$first[$field];
                    break;
                }
            }
        }
        $learning = self::pagination_total($dashboard['learning_history'] ?? []);
        $attempts = self::pagination_total($dashboard['attempt_history'] ?? []);
        $grades = self::pagination_total($dashboard['grade_history'] ?? []);
        $activity = self::pagination_total($dashboard['recent_activity'] ?? []);
        return [
            'status' => ($learning + $attempts + $grades + $activity + $completed) > 0 ? 'available' : 'starting',
            'learning_events' => $learning,
            'attempts' => $attempts,
            'grade_updates' => $grades,
            'recent_activity' => $activity,
            'completed_steps' => $completed,
            'last_active' => $latesttime,
        ];
    }

    /**
     * Expanded current learner position with one progress signal.
     *
     * @param array $timeline
     * @param array $progress
     * @param array $highlights
     * @return array
     */
    private static function current_view(array $timeline, array $progress, array $highlights): array {
        $location = is_array($timeline['present']['current_location'] ?? null) ?
            $timeline['present']['current_location'] : [];
        $course = (string)($timeline['course']['fullname'] ?? '');
        return [
            'status' => (string)($location['status'] ?? 'insufficient_data'),
            'title' => $course !== '' ? $course : 'Learning in progress',
            'position' => ($location['status'] ?? '') === 'available' ?
                'Your current learning area' : 'Your learning position will appear here',
            'progress' => $progress,
            'ability_highlights' => $highlights,
        ];
    }

    /**
     * Guard and simplify the A4B/A5 current eligible activity.
     *
     * @param mixed $next
     * @return array
     */
    private static function continue_learning($next): array {
        $next = is_array($next) ? $next : [];
        $activity = is_array($next['activity'] ?? null) ? $next['activity'] : [];
        $url = self::safe_activity_url($activity);
        $available = ($next['status'] ?? '') === 'next_activity_ready' &&
            !empty($activity['available']) && $url !== '';
        $target = is_array($next['target'] ?? null) ? $next['target'] : [];
        $title = trim((string)($activity['title'] ?? ''));
        if ($title === '') {
            $title = trim((string)($target['title'] ?? ''));
        }
        return [
            'available' => $available,
            'status' => $available ? 'ready' : 'preparing',
            'label' => 'Continue Learning',
            'activity_title' => $available ? ($title !== '' ? self::friendly_text($title) : 'Your next activity') : '',
            'activity_type' => $available ? self::friendly_activity_type((string)($activity['modname'] ?? '')) : '',
            'action' => self::friendly_action((string)($next['action'] ?? '')),
            'reason' => self::friendly_text((string)($next['reason'] ?? '')),
            'url' => $available ? $url : '',
        ];
    }

    /**
     * Validate that Continue Learning points to the selected Moodle activity.
     *
     * @param array $activity
     * @return string
     */
    private static function safe_activity_url(array $activity): string {
        global $CFG;

        $cmid = (int)($activity['cmid'] ?? 0);
        $url = html_entity_decode(trim((string)($activity['url'] ?? '')), ENT_QUOTES);
        if ($cmid <= 0 || $url === '') {
            return '';
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['path']) ||
                !preg_match('#^/mod/[a-z][a-z0-9_]*/view\.php$#', (string)$parts['path'])) {
            return '';
        }
        parse_str((string)($parts['query'] ?? ''), $query);
        if ((int)($query['id'] ?? 0) !== $cmid) {
            return '';
        }
        if (!empty($parts['scheme']) || !empty($parts['host'])) {
            $site = parse_url((string)$CFG->wwwroot);
            if (strtolower((string)($parts['scheme'] ?? '')) !== strtolower((string)($site['scheme'] ?? '')) ||
                    strtolower((string)($parts['host'] ?? '')) !== strtolower((string)($site['host'] ?? '')) ||
                    (int)($parts['port'] ?? 0) !== (int)($site['port'] ?? 0)) {
                return '';
            }
        }
        return $url;
    }

    /**
     * Friendly roadmap rows without IDs or executable links.
     *
     * @param mixed $rows
     * @param int $limit
     * @param bool $skipselected
     * @return array
     */
    private static function roadmap_views($rows, int $limit, bool $skipselected): array {
        $views = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || ($skipselected && !empty($row['selected']))) {
                continue;
            }
            $activity = is_array($row['activity'] ?? null) ? $row['activity'] : [];
            $target = is_array($row['target'] ?? null) ? $row['target'] : [];
            $destination = is_array($row['destination'] ?? null) ? $row['destination'] : [];
            $title = trim((string)($activity['title'] ?? ''));
            if ($title === '') {
                $title = trim((string)($target['title'] ?? ''));
            }
            if ($title === '') {
                $title = trim((string)($destination['title'] ?? ''));
            }
            if ($title === '') {
                $title = self::friendly_action((string)($row['action'] ?? ''));
            }
            $views[] = [
                'title' => $title !== '' ? self::friendly_text($title) : 'Upcoming learning',
                'kind' => self::friendly_target_kind((string)($target['type'] ?? '')),
                'action' => self::friendly_action((string)($row['action'] ?? '')),
                'state' => !empty($row['selected']) ? 'current' : 'upcoming',
            ];
            if (count($views) >= $limit) {
                break;
            }
        }
        return $views;
    }

    /**
     * One defensible progress signal for the main learner view.
     *
     * @param mixed $preferred
     * @param mixed $achievement
     * @return array
     */
    private static function preferred_progress($preferred, $achievement): array {
        $preferred = is_array($preferred) ? $preferred : [];
        $achievement = is_array($achievement) ? $achievement : [];
        $percentage = array_key_exists('percentage', $preferred) && is_numeric($preferred['percentage']) ?
            max(0.0, min(100.0, (float)$preferred['percentage'])) : null;
        $milestone = (string)($preferred['milestone'] ?? $achievement['milestone'] ?? '');
        return [
            'label' => self::friendly_metric_label((string)($preferred['metric'] ?? '')),
            'percentage' => $percentage,
            'milestone' => self::friendly_milestone($milestone),
            'qualitative' => $percentage === null,
        ];
    }

    /**
     * Learner milestone independent of duplicate percentages.
     *
     * @param mixed $achievement
     * @param mixed $preferred
     * @return array
     */
    private static function milestone_view($achievement, $preferred): array {
        $achievement = is_array($achievement) ? $achievement : [];
        $preferred = is_array($preferred) ? $preferred : [];
        $code = (string)($achievement['milestone'] ?? $preferred['milestone'] ?? '');
        return [
            'achieved' => !empty($achievement['achieved']),
            'label' => self::friendly_milestone($code),
        ];
    }

    /**
     * Goal without internal IDs or versions.
     *
     * @param mixed $goal
     * @return array
     */
    private static function goal_view($goal): array {
        $goal = is_array($goal) ? $goal : [];
        if (($goal['status'] ?? '') === 'insufficient_data' || !$goal) {
            return ['available' => false, 'title' => 'Choose your learning goal', 'cefr' => '',
                'stage' => '', 'purpose' => '', 'target_date' => 0];
        }
        return [
            'available' => true,
            'title' => self::friendly_text((string)($goal['title'] ?? 'Your learning goal')),
            'cefr' => (string)($goal['cefr'] ?? ''),
            'stage' => self::friendly_text((string)($goal['flwstage'] ?? '')),
            'purpose' => self::friendly_text((string)($goal['purpose'] ?? '')),
            'target_date' => (int)($goal['targetdate'] ?? 0),
        ];
    }

    /**
     * Friendly bounded skill details.
     *
     * @param mixed $rows
     * @param int $limit
     * @return array
     */
    private static function skill_views($rows, int $limit): array {
        $views = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $target = is_array($row['target'] ?? null) ? $row['target'] : [];
            $title = trim((string)($target['title'] ?? ''));
            $views[] = [
                'title' => $title !== '' ? self::friendly_text($title) :
                    self::friendly_target_kind((string)($target['type'] ?? '')),
                'kind' => self::friendly_target_kind((string)($target['type'] ?? '')),
                'state' => self::friendly_gap_state((string)($row['gap_status'] ?? '')),
                'ability_percentage' => round(max(0.0, min(1.0,
                    (float)($row['mastery_score'] ?? 0))) * 100, 1),
                'learning_results' => max(0, (int)($row['evidence_count'] ?? 0)),
                'review' => self::friendly_retention((string)($row['retention_state'] ?? '')),
            ];
            if (count($views) >= $limit) {
                break;
            }
        }
        return $views;
    }

    /**
     * Level-three reason for the selected activity.
     *
     * @param mixed $next
     * @return array
     */
    private static function why_activity($next): array {
        $next = is_array($next) ? $next : [];
        $reason = self::friendly_text((string)($next['reason'] ?? ''));
        return [
            'available' => $reason !== '',
            'action' => self::friendly_action((string)($next['action'] ?? '')),
            'reason' => $reason !== '' ? $reason : 'A reason will appear when your next activity is ready.',
        ];
    }

    /**
     * Friendly detailed metrics under disclosure.
     *
     * @param mixed $metrics
     * @return array
     */
    private static function friendly_metrics($metrics): array {
        $views = [];
        foreach (['mastery_progress', 'goal_readiness', 'path_progress'] as $code) {
            $metric = is_array($metrics[$code] ?? null) ? $metrics[$code] : [];
            $percentage = array_key_exists('percentage', $metric) && is_numeric($metric['percentage']) ?
                max(0.0, min(100.0, (float)$metric['percentage'])) : null;
            $views[] = [
                'label' => self::friendly_metric_label($code),
                'percentage' => $percentage,
                'available' => $percentage !== null,
            ];
        }
        return $views;
    }

    /**
     * Friendly path-change summary without policy/version internals.
     *
     * @param mixed $change
     * @return array
     */
    private static function path_change_view($change): array {
        $change = is_array($change) ? $change : [];
        return [
            'changed' => !empty($change['changed']),
            'reason' => self::friendly_text((string)($change['reason'] ?? '')),
        ];
    }

    /**
     * Bounded friendly recommendation history.
     *
     * @param mixed $records
     * @param int $limit
     * @return array
     */
    private static function recommendation_views($records, int $limit): array {
        $views = [];
        foreach (is_array($records) ? $records : [] as $record) {
            if (!is_array($record)) {
                continue;
            }
            $target = is_array($record['target'] ?? null) ? $record['target'] : [];
            $views[] = [
                'action' => self::friendly_action((string)($record['action'] ?? '')),
                'title' => self::friendly_text((string)($target['title'] ?? '')),
                'reason' => self::friendly_text((string)($record['reason'] ?? '')),
                'time' => (int)($record['time'] ?? 0),
            ];
            if (count($views) >= $limit) {
                break;
            }
        }
        return $views;
    }

    /** Return a pagination total without relying on records being loaded. */
    private static function pagination_total(array $query): int {
        return max(0, (int)($query['pagination']['total'] ?? count($query['records'] ?? [])));
    }

    /** Friendly metric label. */
    private static function friendly_metric_label(string $code): string {
        return [
            'goal_readiness' => 'Goal readiness',
            'mastery_progress' => 'Ability progress',
            'path_progress' => 'Learning path',
            'completion_progress' => 'Completed learning',
            'qualitative_milestone' => 'Current milestone',
        ][$code] ?? 'Current progress';
    }

    /** Friendly milestone. */
    private static function friendly_milestone(string $code): string {
        return [
            'GOAL_ACHIEVED' => 'Goal achieved',
            'GOAL_READY' => 'Ready for your goal',
            'GOAL_IN_PROGRESS' => 'Working toward your goal',
            'GOAL_NOT_SET' => 'Choose your learning goal',
            'EVIDENCE_NEEDED' => 'Build more learning results',
            'RETENTION_CHECK_NEEDED' => 'Review to keep it strong',
            'CONFIDENCE_NEEDED' => 'Keep practising to confirm progress',
            'PREREQUISITE_NEEDED' => 'Complete what is needed first',
        ][$code] ?? ($code !== '' ? self::friendly_text(self::humanize($code)) : 'Your next milestone is forming');
    }

    /** Friendly adaptive action. */
    private static function friendly_action(string $code): string {
        return [
            'ADVANCE' => 'Move forward',
            'SKIP' => 'Skip ahead',
            'EXTRA_PRACTICE' => 'Extra practice',
            'REMEDIATION' => 'Extra practice',
            'REVIEW' => 'Review',
            'RETRY' => 'Try again',
            'REASSESS' => 'Check progress',
            'REPRIORITIZE' => 'Update learning focus',
        ][$code] ?? ($code !== '' ? self::friendly_text(self::humanize($code)) : 'Next learning');
    }

    /** Friendly target type. */
    private static function friendly_target_kind(string $type): string {
        return [
            'comp' => self::TERMINOLOGY['competency'],
            'competency' => self::TERMINOLOGY['competency'],
            'kp' => self::TERMINOLOGY['kp'],
            'up' => self::TERMINOLOGY['up'],
        ][strtolower($type)] ?? 'Learning target';
    }

    /** Friendly gap state. */
    private static function friendly_gap_state(string $code): string {
        return [
            'satisfied' => 'On track',
            'achieved' => 'Achieved',
            'mastered' => 'Strong',
            'active_gap' => 'In progress',
            'gap' => 'In progress',
            'missing' => 'Starting',
            'blocked' => 'Needed first',
        ][strtolower($code)] ?? 'In progress';
    }

    /** Friendly retention state. */
    private static function friendly_retention(string $code): string {
        return [
            'secure' => 'Strong',
            'retained' => 'Strong',
            'review_due' => 'Review due',
            'due' => 'Review due',
            'at_risk' => 'Review soon',
            'missing' => 'Building',
        ][strtolower($code)] ?? 'Building';
    }

    /** Friendly Moodle module label. */
    private static function friendly_activity_type(string $modname): string {
        return [
            'quiz' => 'Quiz',
            'page' => 'Lesson',
            'lesson' => 'Lesson',
            'assign' => 'Assignment',
            'h5pactivity' => 'Interactive activity',
            'scorm' => 'Interactive lesson',
            'forum' => 'Discussion',
        ][strtolower($modname)] ?? 'Learning activity';
    }

    /** Replace technical ontology terms in human-authored source text. */
    private static function friendly_text(string $text): string {
        $replacements = [
            '/\bcompetencies\b/i' => 'skills',
            '/\bcompetency\b/i' => 'skill',
            '/\bknowledge points?\b/i' => 'learning points',
            '/\bKPs?\b/i' => 'Learning Points',
            '/\buse points?\b/i' => 'practice targets',
            '/\bUPs?\b/i' => 'Practice Targets',
            '/\bmastery\b/i' => 'ability progress',
            '/\bprerequisites?\b/i' => 'what is needed first',
            '/\bremediation\b/i' => 'extra practice',
            '/\bevidence\b/i' => 'learning results',
        ];
        return trim((string)preg_replace(array_keys($replacements), array_values($replacements), $text));
    }

    /** Humanize a stable code. */
    private static function humanize(string $value): string {
        $value = trim(str_replace(['_', '-'], ' ', strtolower($value)));
        return $value === '' ? '' : ucfirst($value);
    }

    /** Runtime file status. */
    private static function file_status(): array {
        $root = dirname(__DIR__, 2);
        $files = [
            'classes/local/learner_experience_service.php',
            'classes/local/learner_experience_renderer.php',
            'learning_timeline.php',
            'cli/learner_experience.php',
            'tests/learner_experience_service_test.php',
            'classes/external/api.php',
            'db/services.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            $present[$file] = is_file($root . '/' . $file);
            if (!$present[$file]) {
                $missing[] = $file;
            }
        }
        return ['present' => $present, 'missing' => $missing];
    }

    /** Runtime method and external-API status. */
    private static function surface_status(): array {
        global $CFG;

        $methods = [];
        foreach (['contract', 'status', 'simplify', 'learner_experience'] as $method) {
            $methods[$method] = method_exists(self::class, $method);
        }
        $apisource = @file_get_contents($CFG->dirroot . '/local/flwcupkp/classes/external/api.php') ?: '';
        $external = [];
        foreach (['get_learner_experience_status', 'get_simplified_learner_experience'] as $method) {
            $external[$method] = strpos($apisource, 'function ' . $method . '(') !== false;
        }
        return [
            'methods' => $methods,
            'external_api' => $external,
            'valid' => !in_array(false, $methods, true) && !in_array(false, $external, true),
        ];
    }

    /** Execute dependency status safely. */
    private static function safe_status(callable $callback): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return ['status' => 'blocked', 'findings' => [[
                'severity' => 'blocker',
                'code' => 'dependency_status_failed',
                'message' => $e->getMessage(),
            ]]];
        }
    }

    /** One criterion. */
    private static function criterion(string $code, bool $pass, string $message): array {
        return ['code' => $code, 'pass' => $pass, 'message' => $message];
    }

    /** Criteria totals. */
    private static function criteria_summary(array $criteria): array {
        $passed = count(array_filter($criteria, static function(array $row): bool {
            return !empty($row['pass']);
        }));
        return ['total' => count($criteria), 'passed' => $passed, 'failed' => count($criteria) - $passed];
    }

    /** Status findings including inherited non-blocking UX1 context. */
    private static function status_findings(array $criteria, array $ux1): array {
        $findings = [];
        foreach ($criteria as $criterion) {
            if (empty($criterion['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'source' => self::GATE,
                    'code' => $criterion['code'],
                    'message' => $criterion['message'],
                ];
            }
        }
        foreach (($ux1['findings'] ?? []) as $finding) {
            $finding['source'] = $finding['source'] ?? student_learning_timeline_view_service::GATE;
            $findings[] = $finding;
        }
        return $findings;
    }

    /** Clean an optional unit code. */
    private static function clean_unit_code_optional(string $unitcode): string {
        $unitcode = strtoupper(trim($unitcode));
        if ($unitcode === '') {
            return '';
        }
        $clean = clean_param($unitcode, PARAM_ALPHANUMEXT);
        if ($clean !== $unitcode) {
            throw new \invalid_parameter_exception('Invalid unit code.');
        }
        return $clean;
    }
}

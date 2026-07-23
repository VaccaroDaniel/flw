<?php
// Threshold calibration proposal workflow.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates, previews, and activates threshold calibration proposals.
 */
final class calibration_proposal {
    /** @var array Ordered numeric threshold fields by target type. */
    private const FIELDS = [
        'kp' => ['introduced', 'practiced', 'controlled_use', 'independent_use', 'mastered'],
        'up' => ['emerging', 'developing', 'demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['developing', 'provisionally_achieved', 'achieved', 'sustained'],
    ];

    /** @var array Strong states used in the preview summary. */
    private const STRONG = [
        'kp' => ['mastered'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained'],
    ];

    /**
     * Return available target types.
     *
     * @return array
     */
    public static function target_types(): array {
        return ['kp', 'up', 'competency'];
    }

    /**
     * Ordered threshold fields for one target type.
     *
     * @param string $targettype
     * @return array
     */
    public static function fields(string $targettype): array {
        return self::FIELDS[$targettype] ?? [];
    }

    /**
     * Starting thresholds for a proposal.
     *
     * @param string $targettype
     * @return array
     */
    public static function current_thresholds(string $targettype): array {
        return array_intersect_key(mastery_engine::rules_for($targettype), array_flip(self::fields($targettype)));
    }

    /**
     * Normalize and validate submitted threshold values.
     *
     * @param string $targettype
     * @param array $values
     * @return array
     */
    public static function normalize_thresholds(string $targettype, array $values): array {
        $fields = self::fields($targettype);
        if (!$fields) {
            throw new \invalid_parameter_exception('Unsupported target type.');
        }

        $thresholds = [];
        $previous = 0.0;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                throw new \invalid_parameter_exception('Missing threshold: ' . $field);
            }
            $value = round((float)$values[$field], 5);
            if ($value < 0 || $value > 1) {
                throw new \invalid_parameter_exception('Thresholds must be between 0 and 1.');
            }
            if ($value < $previous) {
                throw new \invalid_parameter_exception('Thresholds must stay in ascending order.');
            }
            $thresholds[$field] = $value;
            $previous = $value;
        }

        if ($targettype === 'kp') {
            $thresholds['review_after_days'] = (int)(mastery_engine::rules_for('kp')['review_after_days'] ?? 21);
        }
        if ($targettype === 'competency') {
            $thresholds['direct_evidence_required'] =
                (bool)(mastery_engine::rules_for('competency')['direct_evidence_required'] ?? true);
        }
        $thresholds['calibration_status'] = 'calibrated';
        $thresholds['target_type'] = $targettype;

        return $thresholds;
    }

    /**
     * Preview state outcome changes from a saved snapshot.
     *
     * @param \stdClass $snapshot
     * @param string $targettype
     * @param array $thresholds
     * @return array
     */
    public static function preview(\stdClass $snapshot, string $targettype, array $thresholds): array {
        $rows = array_values(array_filter(
            calibration_report::snapshot_state_details($snapshot),
            static function(array $row) use ($targettype): bool {
                return (string)($row['targettype'] ?? '') === $targettype;
            }
        ));

        $current = [];
        $proposed = [];
        $transitions = [];
        $changed = 0;
        $strongcurrent = 0;
        $strongproposed = 0;

        foreach ($rows as $row) {
            $oldstate = (string)($row['masterystate'] ?? '');
            $newstate = self::state_name($targettype, (float)($row['masteryscore'] ?? 0), $thresholds);
            $current[$oldstate] = ($current[$oldstate] ?? 0) + 1;
            $proposed[$newstate] = ($proposed[$newstate] ?? 0) + 1;
            $transition = $oldstate . ' -> ' . $newstate;
            $transitions[$transition] = ($transitions[$transition] ?? 0) + 1;
            if ($oldstate !== $newstate) {
                $changed++;
            }
            if (in_array($oldstate, self::STRONG[$targettype] ?? [], true)) {
                $strongcurrent++;
            }
            if (in_array($newstate, self::STRONG[$targettype] ?? [], true)) {
                $strongproposed++;
            }
        }

        ksort($current);
        ksort($proposed);
        ksort($transitions);

        return [
            'snapshotid' => (int)$snapshot->id,
            'targettype' => $targettype,
            'total_states' => count($rows),
            'changed_states' => $changed,
            'strong_current' => $strongcurrent,
            'strong_proposed' => $strongproposed,
            'strong_delta' => $strongproposed - $strongcurrent,
            'current_outcomes' => $current,
            'proposed_outcomes' => $proposed,
            'transitions' => $transitions,
            'note' => 'Preview applies proposed thresholds to saved state scores; it does not rewrite learner states.',
        ];
    }

    /**
     * Save a draft proposal.
     *
     * @param int $snapshotid
     * @param string $targettype
     * @param string $name
     * @param string $note
     * @param array $thresholds
     * @return int
     */
    public static function save(int $snapshotid, string $targettype, string $name, string $note,
            array $thresholds): int {
        global $DB, $USER;

        $snapshot = calibration_report::snapshot($snapshotid);
        if (!$snapshot) {
            throw new \invalid_parameter_exception('Snapshot is required.');
        }

        $thresholds = self::normalize_thresholds($targettype, $thresholds);
        $preview = self::preview($snapshot, $targettype, $thresholds);
        $now = time();
        $name = trim($name) !== '' ? trim($name) : 'Calibration proposal ' . date('Ymd-His', $now);
        $version = self::version($targettype, $now);

        $record = (object)[
            'snapshotid' => $snapshotid,
            'targettype' => $targettype,
            'name' => substr($name, 0, 120),
            'version' => $version,
            'status' => 'draft',
            'thresholdsjson' => json_encode($thresholds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'previewjson' => json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'activatedruleid' => null,
            'note' => trim($note),
            'userid' => $USER->id ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $proposalid = (int)$DB->insert_record('flwcupkp_calproposal', $record);
        repository::audit('calibration_proposal_saved', 'calibration_proposal', $proposalid, [
            'snapshotid' => $snapshotid,
            'targettype' => $targettype,
            'version' => $version,
            'preview' => $preview,
        ]);

        return $proposalid;
    }

    /**
     * Fetch one proposal.
     *
     * @param int $proposalid
     * @return \stdClass|null
     */
    public static function proposal(int $proposalid): ?\stdClass {
        global $DB;

        return $DB->get_record('flwcupkp_calproposal', ['id' => $proposalid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Proposals saved for a snapshot.
     *
     * @param int $snapshotid
     * @return array
     */
    public static function proposals_for_snapshot(int $snapshotid): array {
        global $DB;

        return array_values($DB->get_records('flwcupkp_calproposal', ['snapshotid' => $snapshotid],
            'timecreated DESC, id DESC'));
    }

    /**
     * Decode a proposal preview.
     *
     * @param \stdClass $proposal
     * @return array
     */
    public static function proposal_preview(\stdClass $proposal): array {
        $preview = json_decode((string)$proposal->previewjson, true);
        return is_array($preview) ? $preview : [];
    }

    /**
     * Decode proposal thresholds.
     *
     * @param \stdClass $proposal
     * @return array
     */
    public static function proposal_thresholds(\stdClass $proposal): array {
        $thresholds = json_decode((string)$proposal->thresholdsjson, true);
        return is_array($thresholds) ? $thresholds : [];
    }

    /**
     * Activate a reviewed proposal as the target type's calibrated rule.
     *
     * @param int $proposalid
     * @return int
     */
    public static function activate(int $proposalid): int {
        global $DB;

        $proposal = $DB->get_record('flwcupkp_calproposal', ['id' => $proposalid], '*', MUST_EXIST);
        if ((string)$proposal->status === 'activated' && !empty($proposal->activatedruleid)) {
            return (int)$proposal->activatedruleid;
        }

        $targettype = (string)$proposal->targettype;
        $ruletype = $targettype . '_mastery';
        $thresholds = self::proposal_thresholds($proposal);
        $thresholds['source_proposalid'] = $proposalid;
        $thresholds['source_snapshotid'] = (int)$proposal->snapshotid;
        $thresholds['calibration_status'] = 'active_calibrated';
        $thresholds['target_type'] = $targettype;

        $now = time();
        $active = $DB->get_records('flwcupkp_rule', ['ruletype' => $ruletype, 'status' => 'active']);
        foreach ($active as $record) {
            $record->status = 'archived';
            $record->timemodified = $now;
            $DB->update_record('flwcupkp_rule', $record);
        }

        $rule = (object)[
            'ruletype' => $ruletype,
            'name' => (string)$proposal->name,
            'version' => (string)$proposal->version,
            'configjson' => json_encode($thresholds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'status' => 'active',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        if ($existing = $DB->get_record('flwcupkp_rule', ['ruletype' => $ruletype, 'version' => $rule->version],
                IGNORE_MISSING)) {
            $rule->id = $existing->id;
            $rule->timecreated = $existing->timecreated;
            $DB->update_record('flwcupkp_rule', $rule);
            $ruleid = (int)$existing->id;
        } else {
            $ruleid = (int)$DB->insert_record('flwcupkp_rule', $rule);
        }

        $proposal->status = 'activated';
        $proposal->activatedruleid = $ruleid;
        $proposal->timemodified = $now;
        $DB->update_record('flwcupkp_calproposal', $proposal);

        repository::audit('calibration_proposal_activated', 'calibration_proposal', $proposalid, [
            'ruleid' => $ruleid,
            'ruletype' => $ruletype,
            'version' => $rule->version,
            'preview' => self::proposal_preview($proposal),
        ]);

        return $ruleid;
    }

    /**
     * Preview state name from score thresholds.
     *
     * @param string $targettype
     * @param float $score
     * @param array $thresholds
     * @return string
     */
    private static function state_name(string $targettype, float $score, array $thresholds): string {
        $states = array_reverse(self::fields($targettype));
        foreach ($states as $state) {
            if ($score >= (float)($thresholds[$state] ?? 1.1)) {
                return $state;
            }
        }
        return $targettype === 'kp' ? 'not_introduced' : ($targettype === 'up' ? 'not_observed' : 'not_started');
    }

    /**
     * Stable calibrated rule version string.
     *
     * @param string $targettype
     * @param int $time
     * @return string
     */
    private static function version(string $targettype, int $time): string {
        return 'cal-' . $targettype . '-' . date('YmdHis', $time);
    }
}

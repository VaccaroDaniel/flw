<?php
// Install defaults for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

/**
 * Insert default provisional rules.
 */
function xmldb_local_flwcupkp_install(): bool {
    global $DB;

    $now = time();
    $rules = [
        [
            'ruletype' => 'mastery',
            'name' => 'Default provisional KP mastery',
            'version' => 'default-kp-v1',
            'configjson' => json_encode([
                'introduced' => 0.10,
                'practiced' => 0.35,
                'controlled_use' => 0.55,
                'independent_use' => 0.70,
                'mastered' => 0.85,
                'review_after_days' => 21,
                'calibration_status' => 'provisional',
            ]),
        ],
        [
            'ruletype' => 'mastery',
            'name' => 'Default provisional UP mastery',
            'version' => 'default-up-v1',
            'configjson' => json_encode([
                'emerging' => 0.20,
                'developing' => 0.45,
                'demonstrated' => 0.70,
                'stable' => 0.82,
                'transfer_ready' => 0.90,
                'calibration_status' => 'provisional',
            ]),
        ],
        [
            'ruletype' => 'mastery',
            'name' => 'Default provisional competency mastery',
            'version' => 'default-competency-v1',
            'configjson' => json_encode([
                'developing' => 0.35,
                'provisionally_achieved' => 0.70,
                'achieved' => 0.82,
                'sustained' => 0.90,
                'direct_evidence_required' => true,
                'calibration_status' => 'provisional',
            ]),
        ],
        [
            'ruletype' => 'recommendation',
            'name' => 'Default spiral distribution',
            'version' => 'default-recommendation-v1',
            'configjson' => json_encode([
                'current_or_new_target' => 0.70,
                'recent_review' => 0.20,
                'long_term_review' => 0.10,
                'calibration_status' => 'provisional',
            ]),
        ],
    ];

    foreach ($rules as $rule) {
        $rule = (object)($rule + [
            'status' => 'active',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        if (!$DB->record_exists('flwcupkp_rule', ['ruletype' => $rule->ruletype, 'version' => $rule->version])) {
            $DB->insert_record('flwcupkp_rule', $rule);
        }
    }

    return true;
}

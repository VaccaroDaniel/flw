<?php
// FLW VR Room evidence adapter for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts FLW VR Room attempts into mapped C-UP-KP evidence.
 */
final class flwvrroom_evidence_adapter {
    /**
     * Process the optional mod_flwvrroom attempt event.
     *
     * @param \core\event\base $event
     * @return array
     */
    public static function process_attempt_submitted(\core\event\base $event): array {
        $other = is_array($event->other ?? null) ? $event->other : [];
        $payload = (object)$other;

        $payload->cmid = (int)($payload->cmid ?? $event->contextinstanceid ?? 0);
        $payload->courseid = (int)($payload->courseid ?? $event->courseid ?? 0);
        $payload->userid = (int)($payload->userid ?? $event->relateduserid ?? $event->userid ?? 0);
        $payload->attemptid = (int)($payload->attemptid ?? $event->objectid ?? 0);
        $payload->timecreated = (int)($payload->timecreated ?? $event->timecreated ?? time());

        $payload = self::merge_stored_attempt($payload);
        return self::process_payload($payload);
    }

    /**
     * Process a trusted VR Room attempt payload.
     *
     * @param \stdClass $payload
     * @return array
     */
    public static function process_payload(\stdClass $payload): array {
        global $DB;

        $payload = self::normalise_payload($payload);
        if ($payload->cmid <= 0 || $payload->userid <= 0) {
            return ['status' => 'ignored', 'reason' => 'missing_cmid_or_user'];
        }

        $object = $DB->get_record('flwcupkp_object', ['cmid' => $payload->cmid], '*', IGNORE_MISSING);
        if (!$object) {
            return ['status' => 'ignored', 'reason' => 'unmapped_cmid', 'cmid' => $payload->cmid];
        }

        $courseid = $payload->courseid > 0 ? $payload->courseid : (int)($object->courseid ?? 0);
        try {
            evidence_guard::assert_object_scope($object, $courseid);
            evidence_guard::assert_user_enrolled_for_course($payload->userid, $courseid);
        } catch (\invalid_parameter_exception $e) {
            return ['status' => 'ignored', 'reason' => 'evidence_scope_rejected', 'message' => $e->getMessage()];
        }

        $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => $object->id]);
        if (!$maps) {
            return ['status' => 'ignored', 'reason' => 'object_has_no_targets', 'objectid' => (int)$object->id];
        }

        $signals = self::attempt_signals($payload);
        if (!$signals) {
            return [
                'status' => 'ignored',
                'reason' => 'attempt_has_no_evidence_signals',
                'cmid' => $payload->cmid,
                'userid' => $payload->userid,
                'objectid' => (int)$object->id,
            ];
        }

        $evidenceids = [];
        $rejectedmaps = [];
        $skippedmaps = [];
        foreach ($signals as $signal) {
            $result = self::record_signal($object, $maps, $payload, $courseid, $signal);
            $evidenceids = array_merge($evidenceids, $result['evidenceids']);
            $rejectedmaps = array_merge($rejectedmaps, $result['rejectedmaps']);
            $skippedmaps = array_merge($skippedmaps, $result['skippedmaps']);
        }

        return [
            'status' => 'processed',
            'cmid' => $payload->cmid,
            'userid' => $payload->userid,
            'objectid' => (int)$object->id,
            'attemptid' => $payload->attemptid,
            'signalcount' => count($signals),
            'evidenceids' => array_values(array_unique($evidenceids)),
            'rejectedmaps' => $rejectedmaps,
            'skippedmaps' => $skippedmaps,
        ];
    }

    /**
     * Merge a stored mod_flwvrroom attempt if the table is available.
     *
     * @param \stdClass $payload
     * @return \stdClass
     */
    private static function merge_stored_attempt(\stdClass $payload): \stdClass {
        global $DB;

        if (empty($payload->attemptid) || !$DB->get_manager()->table_exists('flwvrroom_attempts')) {
            return $payload;
        }
        $attempt = $DB->get_record('flwvrroom_attempts', ['id' => (int)$payload->attemptid], '*', IGNORE_MISSING);
        if (!$attempt) {
            return $payload;
        }

        foreach (get_object_vars($attempt) as $key => $value) {
            if (!isset($payload->{$key}) || $payload->{$key} === '' || $payload->{$key} === null) {
                $payload->{$key} = $value;
            }
        }
        if (empty($payload->cmid) && !empty($attempt->flwvrroomid)) {
            $cmid = self::cmid_from_instance((int)$attempt->flwvrroomid);
            if ($cmid > 0) {
                $payload->cmid = $cmid;
            }
        }

        return $payload;
    }

    /**
     * Resolve the course module ID from the VR Room instance ID.
     *
     * @param int $instanceid
     * @return int
     */
    private static function cmid_from_instance(int $instanceid): int {
        global $DB;

        if ($instanceid <= 0) {
            return 0;
        }
        return (int)$DB->get_field_sql(
            "SELECT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE m.name = :modname AND cm.instance = :instanceid",
            ['modname' => 'flwvrroom', 'instanceid' => $instanceid]
        );
    }

    /**
     * Normalize common payload aliases and JSON fields.
     *
     * @param \stdClass $payload
     * @return \stdClass
     */
    private static function normalise_payload(\stdClass $payload): \stdClass {
        $payload->cmid = (int)($payload->cmid ?? $payload->coursemoduleid ?? 0);
        $payload->courseid = (int)($payload->courseid ?? 0);
        $payload->userid = (int)($payload->userid ?? $payload->learnerid ?? 0);
        $payload->attemptid = (int)($payload->attemptid ?? $payload->id ?? 0);
        $payload->timecreated = (int)($payload->timecreated ?? time());
        $payload->score = self::nullable_float($payload->score ?? $payload->rawscore ?? null);
        $payload->maxscore = self::nullable_float($payload->maxscore ?? null);
        $payload->durationseconds = self::nullable_float($payload->durationseconds ?? $payload->duration ?? null);
        $payload->xrmode = (string)($payload->xrmode ?? $payload->mode ?? '');
        $payload->scenario = (string)($payload->scenario ?? '');
        $payload->kpcodes = self::normalise_codes($payload->kpcodes ?? []);
        $payload->hotspots = self::json_array($payload->hotspots ?? $payload->hotspotsjson ?? []);
        $payload->roleturns = self::json_array($payload->roleturns ?? $payload->roleturnsjson ?? []);
        $payload->speaking = self::json_array($payload->speaking ?? $payload->speakingjson ?? []);
        $payload->completedobjects = self::normalise_codes($payload->completedobjects ?? []);

        return $payload;
    }

    /**
     * Build evidence signals from the VR attempt.
     *
     * @param \stdClass $payload
     * @return array
     */
    private static function attempt_signals(\stdClass $payload): array {
        $signals = [];
        $overallscore = self::score_from_fields($payload);
        if ($overallscore !== null) {
            $signals[] = [
                'key' => 'overall',
                'evidencetype' => 'vr_room_attempt',
                'rawscore' => $payload->score ?? $overallscore,
                'normalizedscore' => $overallscore,
                'confidence' => 0.65,
                'strength' => 'controlled_production',
                'assessortype' => 'flwvrroom',
                'kpcodes' => $payload->kpcodes,
                'details' => [
                    'score' => $payload->score,
                    'maxscore' => $payload->maxscore,
                    'durationseconds' => $payload->durationseconds,
                ],
            ];
        }

        foreach (self::hotspot_signals($payload) as $signal) {
            $signals[] = $signal;
        }
        foreach (self::roleplay_signals($payload) as $signal) {
            $signals[] = $signal;
        }
        foreach (self::speaking_signals($payload, $overallscore) as $signal) {
            $signals[] = $signal;
        }

        return $signals;
    }

    /**
     * Build hotspot evidence signals.
     *
     * @param \stdClass $payload
     * @return array
     */
    private static function hotspot_signals(\stdClass $payload): array {
        $signals = [];
        $items = $payload->hotspots;
        if (!$items && $payload->completedobjects) {
            foreach ($payload->completedobjects as $id) {
                $items[] = ['id' => $id, 'completed' => true];
            }
        }

        foreach ($items as $index => $item) {
            $item = self::as_array($item);
            $completed = !isset($item['completed']) || (bool)$item['completed'];
            if (!$completed) {
                continue;
            }
            $id = (string)($item['id'] ?? $item['hotspotid'] ?? $item['code'] ?? ('hotspot' . $index));
            $signals[] = [
                'key' => 'hotspot:' . $id,
                'evidencetype' => 'vr_hotspot_interaction',
                'rawscore' => 1.0,
                'normalizedscore' => self::score_from_array($item, 1.0),
                'confidence' => 0.55,
                'strength' => 'recognition',
                'assessortype' => 'flwvrroom_hotspot',
                'kpcodes' => self::normalise_codes($item['kpcodes'] ?? []),
                'details' => $item,
            ];
        }

        return $signals;
    }

    /**
     * Build role-play turn evidence signals.
     *
     * @param \stdClass $payload
     * @return array
     */
    private static function roleplay_signals(\stdClass $payload): array {
        $signals = [];
        foreach ($payload->roleturns as $index => $turn) {
            $turn = self::as_array($turn);
            $score = self::score_from_array($turn, null);
            if ($score === null) {
                continue;
            }
            $role = (string)($turn['role'] ?? $turn['character'] ?? 'role');
            $signals[] = [
                'key' => 'role:' . $index . ':' . $role,
                'evidencetype' => 'vr_roleplay_turn',
                'rawscore' => $turn['score'] ?? $score,
                'normalizedscore' => $score,
                'confidence' => self::clamp((float)($turn['confidence'] ?? 0.75)),
                'strength' => 'guided_performance',
                'assessortype' => 'flwvrroom_roleplay',
                'kpcodes' => self::normalise_codes($turn['kpcodes'] ?? []),
                'details' => $turn,
            ];
        }

        return $signals;
    }

    /**
     * Build speaking evidence signals.
     *
     * @param \stdClass $payload
     * @param float|null $fallbackscore
     * @return array
     */
    private static function speaking_signals(\stdClass $payload, ?float $fallbackscore): array {
        $items = $payload->speaking;
        if (!$items && (!empty($payload->speakingtext) || !empty($payload->recognizedresponse) || !empty($payload->aifeedback))) {
            $items[] = [
                'recognizedresponse' => (string)($payload->recognizedresponse ?? $payload->speakingtext ?? ''),
                'feedback' => (string)($payload->aifeedback ?? ''),
                'normalizedscore' => $fallbackscore,
            ];
        }

        $signals = [];
        foreach ($items as $index => $item) {
            $item = self::as_array($item);
            $score = self::speaking_score($item);
            if ($score === null) {
                $score = $fallbackscore;
            }
            if ($score === null) {
                continue;
            }
            $signals[] = [
                'key' => 'speaking:' . $index,
                'evidencetype' => 'vr_speaking_ai',
                'rawscore' => $item['score'] ?? $score,
                'normalizedscore' => $score,
                'confidence' => self::clamp((float)($item['confidence'] ?? 0.70)),
                'strength' => 'guided_performance',
                'assessortype' => 'flw_speaking_scoring_service',
                'kpcodes' => self::normalise_codes($item['kpcodes'] ?? []),
                'details' => self::limited_speaking_details($item),
            ];
        }

        return $signals;
    }

    /**
     * Record one signal against matching object mappings.
     *
     * @param \stdClass $object
     * @param array $maps
     * @param \stdClass $payload
     * @param int $courseid
     * @param array $signal
     * @return array
     */
    private static function record_signal(\stdClass $object, array $maps, \stdClass $payload, int $courseid,
            array $signal): array {
        global $DB, $USER;

        $evidenceids = [];
        $rejectedmaps = [];
        $skippedmaps = [];
        $sourcetype = content_evidence_mapping_contract::source_type_for_evidence_type(
            (string)$signal['evidencetype'],
            (string)$signal['assessortype'],
            'mod_flwvrroom_attempt'
        );
        foreach ($maps as $map) {
            try {
                evidence_guard::assert_object_map($object, $map);
                content_evidence_mapping_contract::assert_source_can_count($sourcetype, $object, $map);
            } catch (\invalid_parameter_exception $e) {
                $rejectedmaps[] = ['mapid' => (int)$map->id, 'reason' => $e->getMessage()];
                continue;
            }
            if (!self::signal_matches_map($signal, $map)) {
                $skippedmaps[] = [
                    'mapid' => (int)$map->id,
                    'targettype' => (string)$map->targettype,
                    'targetid' => (int)$map->targetid,
                    'reason' => 'kp_code_filter',
                ];
                continue;
            }

            $sourceattempt = self::source_attempt($payload, $signal, $map);
            if ($DB->record_exists('flwcupkp_evidence', [
                'objectid' => (int)$object->id,
                'sourceattempt' => $sourceattempt,
                'targettype' => (string)$map->targettype,
                'targetid' => (int)$map->targetid,
            ])) {
                continue;
            }

            $result = mastery_engine::record_evidence((object)[
                'userid' => $payload->userid,
                'courseid' => $courseid,
                'unitcode' => (string)($object->unitcode ?? ''),
                'objectid' => (int)$object->id,
                'sourceattempt' => $sourceattempt,
                'evidencetype' => (string)$signal['evidencetype'],
                'targettype' => (string)$map->targettype,
                'targetid' => (int)$map->targetid,
                'rawscore' => (float)$signal['rawscore'],
                'normalizedscore' => self::clamp((float)$signal['normalizedscore']),
                'rubricjson' => json_encode(self::rubric($payload, $signal, $map), JSON_UNESCAPED_SLASHES),
                'assessortype' => (string)$signal['assessortype'],
                'confidence' => self::clamp((float)$signal['confidence']),
                'evidencestrength' => $map->evidencestrength ?: ($object->evidencestrength ?: (string)$signal['strength']),
                'provenance' => 'mod_flwvrroom_attempt',
                'sourceref' => 'flwvrroom_attempt:' . $payload->attemptid,
                'timecreated' => $payload->timecreated ?: time(),
                'usermodified' => $USER->id ?? 0,
            ]);
            $evidenceids[] = $result['evidenceid'];
        }

        return [
            'evidenceids' => $evidenceids,
            'rejectedmaps' => $rejectedmaps,
            'skippedmaps' => $skippedmaps,
        ];
    }

    /**
     * Decide whether signal KP codes should filter this mapping.
     *
     * @param array $signal
     * @param \stdClass $map
     * @return bool
     */
    private static function signal_matches_map(array $signal, \stdClass $map): bool {
        global $DB;

        $codes = $signal['kpcodes'] ?? [];
        if (!$codes || (string)$map->targettype !== 'kp') {
            return true;
        }
        $kp = $DB->get_record('flwcupkp_kp', ['id' => (int)$map->targetid], 'id,externalid', IGNORE_MISSING);
        if (!$kp) {
            return false;
        }

        return in_array(strtolower((string)$kp->externalid), $codes, true);
    }

    /**
     * Stable, short source attempt key.
     *
     * @param \stdClass $payload
     * @param array $signal
     * @param \stdClass $map
     * @return string
     */
    private static function source_attempt(\stdClass $payload, array $signal, \stdClass $map): string {
        $attemptid = $payload->attemptid > 0 ? (string)$payload->attemptid : sha1(json_encode([
            $payload->cmid,
            $payload->userid,
            $payload->timecreated,
            $payload->scenario,
        ]));
        $hash = substr(sha1((string)$signal['key'] . ':' . $map->targettype . ':' . (int)$map->targetid), 0, 16);
        return 'flwvr:' . substr($attemptid, 0, 40) . ':' . $hash;
    }

    /**
     * Store structured rubric details without raw audio.
     *
     * @param \stdClass $payload
     * @param array $signal
     * @param \stdClass $map
     * @return array
     */
    private static function rubric(\stdClass $payload, array $signal, \stdClass $map): array {
        return [
            'cmid' => $payload->cmid,
            'attemptid' => $payload->attemptid,
            'scenario' => $payload->scenario,
            'xrmode' => $payload->xrmode,
            'signal' => [
                'key' => $signal['key'],
                'type' => $signal['evidencetype'],
                'kpcodes' => $signal['kpcodes'],
                'details' => $signal['details'],
            ],
            'target' => [
                'type' => (string)$map->targettype,
                'id' => (int)$map->targetid,
            ],
        ];
    }

    /**
     * Score a full attempt from score/maxscore fields.
     *
     * @param \stdClass $payload
     * @return float|null
     */
    private static function score_from_fields(\stdClass $payload): ?float {
        if ($payload->score === null) {
            return null;
        }
        if ($payload->maxscore !== null && $payload->maxscore > 0) {
            return self::clamp($payload->score / $payload->maxscore);
        }
        return self::clamp($payload->score > 1 ? $payload->score / 100 : $payload->score);
    }

    /**
     * Score an array with common score aliases.
     *
     * @param array $item
     * @param float|null $default
     * @return float|null
     */
    private static function score_from_array(array $item, ?float $default): ?float {
        if (isset($item['normalizedscore'])) {
            return self::clamp((float)$item['normalizedscore']);
        }
        if (isset($item['score'], $item['maxscore']) && (float)$item['maxscore'] > 0) {
            return self::clamp((float)$item['score'] / (float)$item['maxscore']);
        }
        if (isset($item['score'])) {
            $score = (float)$item['score'];
            return self::clamp($score > 1 ? $score / 100 : $score);
        }
        return $default;
    }

    /**
     * Derive a speaking score from service dimensions.
     *
     * @param array $item
     * @return float|null
     */
    private static function speaking_score(array $item): ?float {
        $score = self::score_from_array($item, null);
        if ($score !== null) {
            return $score;
        }
        if (isset($item['similarity']) || isset($item['taskcompletion']) || isset($item['intelligibility'])) {
            $similarity = self::clamp((float)($item['similarity'] ?? 0));
            $completion = self::clamp((float)($item['taskcompletion'] ?? $similarity));
            $intelligibility = self::clamp((float)($item['intelligibility'] ?? $similarity));
            return self::clamp(($similarity * 0.50) + ($completion * 0.30) + ($intelligibility * 0.20));
        }
        return null;
    }

    /**
     * Keep text feedback, but never expect raw audio in evidence JSON.
     *
     * @param array $item
     * @return array
     */
    private static function limited_speaking_details(array $item): array {
        unset($item['audio'], $item['audiofile'], $item['audiobase64'], $item['blob']);
        return $item;
    }

    /**
     * Decode arrays stored as JSON.
     *
     * @param mixed $value
     * @return array
     */
    private static function json_array($value): array {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return [self::as_array($value)];
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded[0])) {
            return $decoded;
        }
        return [$decoded];
    }

    /**
     * Normalize comma, JSON, or array code lists to lower-case external IDs.
     *
     * @param mixed $value
     * @return array
     */
    private static function normalise_codes($value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/[,;\\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }
        } else if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return [];
        }

        $codes = [];
        foreach ($value as $code) {
            if (is_array($code) || is_object($code)) {
                $code = self::as_array($code);
                $code = $code['externalid'] ?? $code['code'] ?? $code['id'] ?? '';
            }
            $code = strtolower(trim((string)$code));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * Convert an object to an array.
     *
     * @param mixed $value
     * @return array
     */
    private static function as_array($value): array {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return get_object_vars($value);
        }
        return [];
    }

    /**
     * Nullable float conversion.
     *
     * @param mixed $value
     * @return float|null
     */
    private static function nullable_float($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }

    /**
     * Clamp score to 0..1.
     *
     * @param float $score
     * @return float
     */
    private static function clamp(float $score): float {
        return max(0.0, min(1.0, $score));
    }
}

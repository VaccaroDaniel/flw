<?php
// History service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service boundary for normalized learning source events.
 */
class history_service {
    /**
     * Record or replay a normalized source event.
     *
     * @param array $data Source event data.
     * @return int Source event id.
     */
    public static function record_source_event(array $data): int {
        return repository::upsert_source_event($data);
    }

    /**
     * Record a Moodle event as a normalized source event.
     *
     * @param \core\event\base $event Moodle event.
     * @param array $extra Extra normalized fields.
     * @return int Source event id.
     */
    public static function record_moodle_event(\core\event\base $event, array $extra = []): int {
        return repository::upsert_source_event(normalizer::moodle_event_to_source_event($event, $extra));
    }

    /**
     * Fetch a source event by source key.
     *
     * @param string $sourcekey Source key.
     * @return \stdClass|null
     */
    public static function get_source_event_by_key(string $sourcekey): ?\stdClass {
        return repository::get_source_event_by_key($sourcekey);
    }

    /**
     * Record a new normalized interpretation of the same source fact.
     *
     * The source fact identity is preserved in sourcefactkey. The old
     * normalized meaning remains auditable and is supersession-linked.
     *
     * @param int $sourceeventid Existing source event id.
     * @param array $newsummary New normalized summary.
     * @param string $newpolicyversion New normalization policy version.
     * @param string $reason Correction reason.
     * @param int|null $userid Actor id.
     * @return int New source event id.
     */
    public static function record_normalization_supersession(
        int $sourceeventid,
        array $newsummary,
        string $newpolicyversion,
        string $reason = '',
        ?int $userid = null
    ): int {
        $existing = repository::get_source_event($sourceeventid);
        if (!$existing) {
            throw new \invalid_parameter_exception('Source event does not exist.');
        }
        if ($newpolicyversion === (string)($existing->normpolicyversion ?? '')) {
            throw new \invalid_parameter_exception('New normalization policy version must differ from existing version.');
        }

        $sourcefactkey = !empty($existing->sourcefactkey) ? (string)$existing->sourcefactkey : (string)$existing->sourcekey;
        $data = (array)$existing;
        unset($data['id'], $data['timecreated'], $data['timemodified'], $data['supersededby']);
        $data['sourcefactkey'] = $sourcefactkey;
        $data['sourcekey'] = source_identity::make_key(
            (string)$existing->sourcesystem,
            (string)$existing->sourcetype,
            (string)$existing->sourceid,
            (string)($existing->sourceversion ?? ''),
            (string)$existing->eventtype . ':norm:' . $newpolicyversion
        );
        $data['summaryjson'] = $newsummary;
        $data['payloadhash'] = source_identity::payload_hash($newsummary);
        $data['normpolicyversion'] = $newpolicyversion;
        $data['correctionof'] = $sourceeventid;
        $data['status'] = 'recorded';

        $newid = repository::upsert_source_event($data);
        repository::record_correction([
            'recordtable' => 'flwhist_source_event',
            'recordid' => $newid,
            'correctedtable' => 'flwhist_source_event',
            'correctedid' => $sourceeventid,
            'correctiontype' => 'normalization_supersession',
            'reason' => $reason,
            'userid' => $userid,
        ]);
        return $newid;
    }

    /**
     * Fetch learner history timeline.
     *
     * @param int $userid User id.
     * @param int $courseid Optional course id.
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_learner_timeline(int $userid, int $courseid = 0, int $limit = 100): array {
        return repository::get_learner_timeline($userid, $courseid, $limit);
    }
}

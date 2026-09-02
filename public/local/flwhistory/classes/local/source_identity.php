<?php
// Source identity helpers for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds stable replay-safe source identifiers.
 */
class source_identity {
    /** Maximum length for unique source keys. */
    private const MAX_KEY_LENGTH = 191;

    /**
     * Build a stable source key.
     *
     * @param string $sourcesystem Source system, for example moodle or flwexam.
     * @param string $sourcetype Source entity type.
     * @param string $sourceid Source entity id.
     * @param string $sourceversion Source version or source timestamp.
     * @param string $eventtype Optional source event type.
     * @return string
     */
    public static function make_key(
        string $sourcesystem,
        string $sourcetype,
        string $sourceid,
        string $sourceversion = '',
        string $eventtype = ''
    ): string {
        $sourcesystem = self::clean_part($sourcesystem);
        $sourcetype = self::clean_part($sourcetype);
        $sourceid = self::clean_part($sourceid);
        $sourceversion = self::clean_part($sourceversion);
        $eventtype = self::clean_part($eventtype);

        if ($sourcesystem === '' || $sourcetype === '' || $sourceid === '') {
            throw new \invalid_parameter_exception('Source system, type, and id are required.');
        }

        $parts = [$sourcesystem, $sourcetype, $sourceid];
        if ($sourceversion !== '') {
            $parts[] = $sourceversion;
        }
        if ($eventtype !== '') {
            $parts[] = $eventtype;
        }

        $key = implode(':', $parts);
        if (strlen($key) <= self::MAX_KEY_LENGTH) {
            return $key;
        }

        $hash = substr(hash('sha256', $key), 0, 32);
        $prefix = substr($sourcesystem . ':' . $sourcetype . ':', 0, self::MAX_KEY_LENGTH - 33);
        return $prefix . $hash;
    }

    /**
     * Normalize source fields and generate a key when needed.
     *
     * @param array $record Source record.
     * @return array
     */
    public static function normalise_record(array $record): array {
        $record['sourcesystem'] = trim((string)($record['sourcesystem'] ?? ''));
        $record['sourcetype'] = trim((string)($record['sourcetype'] ?? ''));
        $record['sourceid'] = trim((string)($record['sourceid'] ?? ''));
        $record['sourceversion'] = trim((string)($record['sourceversion'] ?? ''));
        $record['eventtype'] = trim((string)($record['eventtype'] ?? 'recorded'));

        if (empty($record['sourcekey'])) {
            $record['sourcekey'] = self::make_key(
                $record['sourcesystem'],
                $record['sourcetype'],
                $record['sourceid'],
                $record['sourceversion'],
                $record['eventtype']
            );
        }

        return $record;
    }

    /**
     * Build source fields from a Moodle event without persisting it.
     *
     * @param \core\event\base $event Moodle event.
     * @param string $sourceversion Optional source version override.
     * @return array
     */
    public static function from_moodle_event(\core\event\base $event, string $sourceversion = ''): array {
        $data = $event->get_data();
        $eventtime = (int)($data['timecreated'] ?? time());
        $sourceid = (string)($data['objectid'] ?? '');
        if ($sourceid === '') {
            $sourceid = (string)($data['contextinstanceid'] ?? $eventtime);
        }

        $record = [
            'sourcesystem' => 'moodle',
            'sourcetype' => (string)($data['target'] ?? 'event'),
            'sourcefamily' => history_policy::source_family('moodle', (string)($data['target'] ?? 'event')),
            'sourceid' => $sourceid,
            'sourceversion' => $sourceversion !== '' ? $sourceversion : (string)$eventtime,
            'eventtype' => (string)($data['eventname'] ?? 'event'),
            'userid' => isset($data['userid']) ? (int)$data['userid'] : null,
            'courseid' => isset($data['courseid']) ? (int)$data['courseid'] : null,
            'cmid' => ((int)($data['contextlevel'] ?? 0) === CONTEXT_MODULE)
                ? (int)($data['contextinstanceid'] ?? 0)
                : null,
            'eventtime' => $eventtime,
            'summaryjson' => self::stable_json([
                'crud' => $data['crud'] ?? null,
                'edulevel' => $data['edulevel'] ?? null,
                'component' => $data['component'] ?? null,
                'target' => $data['target'] ?? null,
                'action' => $data['action'] ?? null,
                'objecttable' => $data['objecttable'] ?? null,
                'objectid' => $data['objectid'] ?? null,
                'contextid' => $data['contextid'] ?? null,
            ]),
        ];
        $record['payloadhash'] = self::payload_hash($record['summaryjson']);
        $record['normpolicyversion'] = history_policy::NORMALIZATION_POLICY_VERSION;

        return self::normalise_record($record);
    }

    /**
     * Hash a value using stable JSON representation.
     *
     * @param mixed $payload Payload to hash.
     * @return string
     */
    public static function payload_hash($payload): string {
        return hash('sha256', is_string($payload) ? $payload : self::stable_json($payload));
    }

    /**
     * Encode arrays/objects with stable key ordering.
     *
     * @param mixed $value Value to encode.
     * @return string
     */
    public static function stable_json($value): string {
        $encoded = json_encode(self::canonicalize($value), JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \coding_exception('Unable to encode stable JSON.');
        }
        return $encoded;
    }

    /**
     * Clean one source key part.
     *
     * @param string $value Raw value.
     * @return string
     */
    private static function clean_part(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[^A-Za-z0-9_.@-]+/', '-', $value);
        return trim((string)$value, '-');
    }

    /**
     * Sort associative arrays recursively.
     *
     * @param mixed $value Raw value.
     * @return mixed
     */
    private static function canonicalize($value) {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (self::is_associative($value)) {
            ksort($value);
        }
        foreach ($value as $key => $child) {
            $value[$key] = self::canonicalize($child);
        }
        return $value;
    }

    /**
     * Determine whether an array is associative.
     *
     * @param array $value Array.
     * @return bool
     */
    private static function is_associative(array $value): bool {
        if ($value === []) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }
}

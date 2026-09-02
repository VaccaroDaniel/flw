<?php
// H1B history coverage and normalization policy for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Frozen H1B semantic constants and helpers.
 */
class history_policy {
    /** Current H1B normalization policy version. */
    public const NORMALIZATION_POLICY_VERSION = 'H1B-20260827.1';

    public const COVERAGE_COMPLETE = 'COMPLETE';
    public const COVERAGE_PARTIAL = 'PARTIAL';
    public const COVERAGE_SOURCE_LIMITED = 'SOURCE_LIMITED';
    public const COVERAGE_NOT_BACKFILLED = 'NOT_BACKFILLED';
    public const COVERAGE_UNKNOWN = 'UNKNOWN';

    public const EVENT_AVAILABLE = 'EVENT_AVAILABLE';
    public const NO_EVENT_OCCURRED = 'NO_EVENT_OCCURRED';
    public const NO_EVENT_AVAILABLE = 'NO_EVENT_AVAILABLE';

    /**
     * Get valid coverage statuses.
     *
     * @return array
     */
    public static function coverage_statuses(): array {
        return [
            self::COVERAGE_COMPLETE,
            self::COVERAGE_PARTIAL,
            self::COVERAGE_SOURCE_LIMITED,
            self::COVERAGE_NOT_BACKFILLED,
            self::COVERAGE_UNKNOWN,
        ];
    }

    /**
     * Normalize and validate a coverage status.
     *
     * @param string $status Status.
     * @return string
     */
    public static function normalise_coverage_status(string $status): string {
        $status = strtoupper(trim($status));
        if (!in_array($status, self::coverage_statuses(), true)) {
            throw new \invalid_parameter_exception('Invalid history coverage status: ' . $status);
        }
        return $status;
    }

    /**
     * Get valid event availability statuses.
     *
     * @return array
     */
    public static function event_availability_statuses(): array {
        return [
            self::EVENT_AVAILABLE,
            self::NO_EVENT_OCCURRED,
            self::NO_EVENT_AVAILABLE,
        ];
    }

    /**
     * Normalize and validate an event availability status.
     *
     * @param string $status Status.
     * @return string
     */
    public static function normalise_event_availability(string $status): string {
        $status = strtoupper(trim($status));
        if (!in_array($status, self::event_availability_statuses(), true)) {
            throw new \invalid_parameter_exception('Invalid event availability status: ' . $status);
        }
        return $status;
    }

    /**
     * Infer whether missing events mean no event occurred or no history is available.
     *
     * @param string $coveragestatus Coverage status.
     * @param int $eventcount Number of events in scope.
     * @return string
     */
    public static function infer_event_availability(string $coveragestatus, int $eventcount = 0): string {
        $coveragestatus = self::normalise_coverage_status($coveragestatus);
        if ($eventcount > 0) {
            return self::EVENT_AVAILABLE;
        }
        if ($coveragestatus === self::COVERAGE_COMPLETE) {
            return self::NO_EVENT_OCCURRED;
        }
        return self::NO_EVENT_AVAILABLE;
    }

    /**
     * Decide whether a coverage record is sufficient for inactivity analytics.
     *
     * @param string $coveragestatus Coverage status.
     * @param int|null $earliest Earliest reliable event timestamp.
     * @param int|null $latest Latest reconciled timestamp.
     * @param int $windowstart Window start.
     * @param int $windowend Window end.
     * @return bool
     */
    public static function is_sufficient_for_interval(
        string $coveragestatus,
        ?int $earliest,
        ?int $latest,
        int $windowstart = 0,
        int $windowend = 0
    ): bool {
        if (self::normalise_coverage_status($coveragestatus) !== self::COVERAGE_COMPLETE) {
            return false;
        }
        if ($windowstart > 0 && ($earliest === null || $earliest > $windowstart)) {
            return false;
        }
        if ($windowend > 0 && ($latest === null || $latest < $windowend)) {
            return false;
        }
        return true;
    }

    /**
     * Infer a stable source family from source system and source type.
     *
     * @param string $sourcesystem Source system.
     * @param string $sourcetype Source type.
     * @return string
     */
    public static function source_family(string $sourcesystem, string $sourcetype): string {
        $combined = strtolower($sourcesystem . ':' . $sourcetype);
        $families = [
            'quiz' => ['quiz', 'question_attempt'],
            'gradebook' => ['grade', 'gradebook'],
            'completion' => ['completion'],
            'assignment' => ['assign', 'assignment'],
            'scorm' => ['scorm', 'sco'],
            'h5p' => ['h5p'],
            'flwexam' => ['flwexam', 'exam'],
            'placement' => ['flwplacement', 'placement'],
            'flwmedia' => ['flwmedia', 'media'],
            'flwaiassessment' => ['flwaiassessment'],
            'flwaispeaking' => ['flwaispeaking'],
            'flwvrroom' => ['flwvrroom', 'vrroom'],
        ];

        foreach ($families as $family => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($combined, $needle)) {
                    return $family;
                }
            }
        }

        return self::clean_family($sourcesystem !== '' ? $sourcesystem : 'unknown');
    }

    /**
     * Build an idempotent coverage source key.
     *
     * @param array $data Coverage data.
     * @return string
     */
    public static function coverage_source_key(array $data): string {
        $scope = [
            'sourcefamily' => self::clean_family((string)($data['sourcefamily'] ?? 'unknown')),
            'scopelevel' => (string)($data['scopelevel'] ?? 'course'),
            'userid' => isset($data['userid']) ? (int)$data['userid'] : null,
            'courseid' => isset($data['courseid']) ? (int)$data['courseid'] : null,
            'worldid' => $data['worldid'] ?? null,
            'stageid' => $data['stageid'] ?? null,
            'unitid' => $data['unitid'] ?? null,
            'timerangestart' => isset($data['timerangestart']) ? (int)$data['timerangestart'] : null,
            'timerangeend' => isset($data['timerangeend']) ? (int)$data['timerangeend'] : null,
        ];
        return source_identity::make_key(
            'flwhistory',
            'coverage',
            substr(source_identity::payload_hash($scope), 0, 32),
            (string)($data['normpolicyversion'] ?? self::NORMALIZATION_POLICY_VERSION)
        );
    }

    /**
     * Build an unknown/no-history coverage result.
     *
     * @param array $criteria Criteria.
     * @return array
     */
    public static function unknown_coverage(array $criteria = []): array {
        return [
            'id' => null,
            'sourcekey' => null,
            'scopelevel' => $criteria['scopelevel'] ?? 'unknown',
            'sourcefamily' => self::clean_family((string)($criteria['sourcefamily'] ?? 'unknown')),
            'userid' => $criteria['userid'] ?? null,
            'courseid' => $criteria['courseid'] ?? null,
            'worldid' => $criteria['worldid'] ?? null,
            'stageid' => $criteria['stageid'] ?? null,
            'unitid' => $criteria['unitid'] ?? null,
            'timerangestart' => $criteria['timerangestart'] ?? null,
            'timerangeend' => $criteria['timerangeend'] ?? null,
            'coveragestatus' => self::COVERAGE_UNKNOWN,
            'eventavailability' => self::NO_EVENT_AVAILABLE,
            'capturestartedat' => null,
            'backfillstartedat' => null,
            'backfillcompletedat' => null,
            'earliestreliableeventat' => null,
            'latestreconciledat' => null,
            'sourceavailable' => null,
            'eventcount' => 0,
            'sufficient' => false,
            'reasoncode' => 'NO_COVERAGE_RECORD',
            'normpolicyversion' => self::NORMALIZATION_POLICY_VERSION,
        ];
    }

    /**
     * Clean source family code.
     *
     * @param string $family Family.
     * @return string
     */
    public static function clean_family(string $family): string {
        $family = strtolower(trim($family));
        $family = preg_replace('/[^a-z0-9_@.-]+/', '_', $family);
        $family = trim((string)$family, '_');
        return $family === '' ? 'unknown' : $family;
    }
}

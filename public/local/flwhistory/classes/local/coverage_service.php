<?php
// Coverage query service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Query service for history coverage and completeness semantics.
 */
class coverage_service {
    /**
     * Record or update coverage for a source family/scope.
     *
     * @param array $data Coverage data.
     * @return int Coverage row id.
     */
    public static function record_coverage(array $data): int {
        return repository::upsert_coverage($data);
    }

    /**
     * Get coverage for a source family and scope.
     *
     * @param array $criteria Search criteria.
     * @return array
     */
    public static function get_coverage(array $criteria): array {
        $record = repository::get_coverage($criteria);
        if (!$record) {
            return history_policy::unknown_coverage($criteria);
        }
        return self::coverage_record_to_array($record,
            (int)($criteria['timerangestart'] ?? 0),
            (int)($criteria['timerangeend'] ?? 0));
    }

    /**
     * Get all coverage facts for a course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_course_coverage(int $courseid): array {
        return repository::get_course_coverage($courseid);
    }

    /**
     * Get a learner timeline.
     *
     * @param int $userid User id.
     * @param int $courseid Optional course id.
     * @param int $limit Result limit.
     * @return array
     */
    public static function get_learner_timeline(int $userid, int $courseid = 0, int $limit = 100): array {
        return repository::get_learner_timeline($userid, $courseid, $limit);
    }

    /**
     * Get source-event coverage counts for a course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_course_source_counts(int $courseid): array {
        return repository::get_course_source_counts($courseid);
    }

    /**
     * Get compact learner/course counts.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_learner_course_summary(int $userid, int $courseid): array {
        global $DB;

        $params = ['userid' => $userid, 'courseid' => $courseid];
        return [
            'sourceevents' => (int)$DB->count_records('flwhist_source_event', $params),
            'attempts' => (int)$DB->count_records('flwhist_attempt', $params),
            'questionattempts' => (int)$DB->count_records('flwhist_question_attempt', $params),
            'gradeversions' => (int)$DB->count_records('flwhist_grade_version', $params),
            'completions' => (int)$DB->count_records('flwhist_completion', $params),
            'coveragefacts' => (int)$DB->count_records('flwhist_coverage', $params),
            'latesteventtime' => self::get_latest_event_time($userid, $courseid),
        ];
    }

    /**
     * Return coverage needed before evaluating inactivity.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param string $sourcefamily Source family.
     * @param int $windowstart Window start.
     * @param int $windowend Window end.
     * @return array
     */
    public static function get_inactivity_coverage(
        int $userid,
        int $courseid,
        string $sourcefamily,
        int $windowstart,
        int $windowend
    ): array {
        $coverage = self::get_coverage([
            'sourcefamily' => $sourcefamily,
            'userid' => $userid,
            'courseid' => $courseid,
            'timerangestart' => $windowstart,
            'timerangeend' => $windowend,
        ]);
        $coverage['sufficient_for_inactivity'] = history_policy::is_sufficient_for_interval(
            $coverage['coveragestatus'],
            $coverage['earliestreliableeventat'],
            $coverage['latestreconciledat'],
            $windowstart,
            $windowend
        );
        return $coverage;
    }

    /**
     * Whether a teacher/admin inactivity signal may evaluate the interval.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param string $sourcefamily Source family.
     * @param int $windowstart Window start.
     * @param int $windowend Window end.
     * @return bool
     */
    public static function can_evaluate_inactivity(
        int $userid,
        int $courseid,
        string $sourcefamily,
        int $windowstart,
        int $windowend
    ): bool {
        $coverage = self::get_inactivity_coverage($userid, $courseid, $sourcefamily, $windowstart, $windowend);
        return !empty($coverage['sufficient_for_inactivity']);
    }

    /**
     * Get coverage context for a source event for Program 3 consumption.
     *
     * @param \stdClass $sourceevent Source event.
     * @return array
     */
    public static function get_coverage_for_event(\stdClass $sourceevent): array {
        $sourcefamily = !empty($sourceevent->sourcefamily)
            ? (string)$sourceevent->sourcefamily
            : history_policy::source_family((string)$sourceevent->sourcesystem, (string)$sourceevent->sourcetype);
        $eventtime = isset($sourceevent->eventtime) ? (int)$sourceevent->eventtime : 0;
        $userid = isset($sourceevent->userid) ? (int)$sourceevent->userid : null;
        $courseid = isset($sourceevent->courseid) ? (int)$sourceevent->courseid : null;

        $criteria = [
            'sourcefamily' => $sourcefamily,
            'timerangestart' => $eventtime,
            'timerangeend' => $eventtime,
        ];

        $candidates = [];
        if (!empty($sourceevent->unitid)) {
            $specific = $criteria;
            $specific['unitid'] = $sourceevent->unitid;
            if ($userid !== null) {
                $specific['userid'] = $userid;
            }
            if ($courseid !== null) {
                $specific['courseid'] = $courseid;
            }
            $candidates[] = $specific;

            if ($userid !== null) {
                unset($specific['userid']);
                $candidates[] = $specific;
            }
        }

        $specific = $criteria;
        if ($userid !== null) {
            $specific['userid'] = $userid;
        }
        if ($courseid !== null) {
            $specific['courseid'] = $courseid;
        }
        $candidates[] = $specific;

        if ($userid !== null && $courseid !== null) {
            $candidates[] = $criteria + ['courseid' => $courseid];
        }
        $candidates[] = $criteria;

        foreach ($candidates as $candidate) {
            $coverage = self::get_coverage($candidate);
            if ($coverage['id'] !== null) {
                return $coverage;
            }
        }

        return history_policy::unknown_coverage($specific);
    }

    /**
     * Get latest event time for a learner/course.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return int|null
     */
    private static function get_latest_event_time(int $userid, int $courseid): ?int {
        global $DB;

        $value = $DB->get_field_sql(
            'SELECT MAX(eventtime) FROM {flwhist_source_event} WHERE userid = :userid AND courseid = :courseid',
            ['userid' => $userid, 'courseid' => $courseid]
        );
        return $value === null ? null : (int)$value;
    }

    /**
     * Convert coverage record to an API-safe array.
     *
     * @param \stdClass $record Coverage record.
     * @param int $windowstart Optional window start.
     * @param int $windowend Optional window end.
     * @return array
     */
    private static function coverage_record_to_array(\stdClass $record, int $windowstart = 0, int $windowend = 0): array {
        $earliest = isset($record->earliestreliableeventat) ? (int)$record->earliestreliableeventat : null;
        $latest = isset($record->latestreconciledat) ? (int)$record->latestreconciledat : null;
        return [
            'id' => (int)$record->id,
            'sourcekey' => (string)$record->sourcekey,
            'scopelevel' => (string)$record->scopelevel,
            'sourcefamily' => (string)$record->sourcefamily,
            'userid' => isset($record->userid) ? (int)$record->userid : null,
            'courseid' => isset($record->courseid) ? (int)$record->courseid : null,
            'worldid' => $record->worldid ?? null,
            'stageid' => $record->stageid ?? null,
            'unitid' => $record->unitid ?? null,
            'timerangestart' => isset($record->timerangestart) ? (int)$record->timerangestart : null,
            'timerangeend' => isset($record->timerangeend) ? (int)$record->timerangeend : null,
            'coveragestatus' => (string)$record->coveragestatus,
            'eventavailability' => (string)$record->eventavailability,
            'capturestartedat' => isset($record->capturestartedat) ? (int)$record->capturestartedat : null,
            'backfillstartedat' => isset($record->backfillstartedat) ? (int)$record->backfillstartedat : null,
            'backfillcompletedat' => isset($record->backfillcompletedat) ? (int)$record->backfillcompletedat : null,
            'earliestreliableeventat' => $earliest,
            'latestreconciledat' => $latest,
            'sourceavailable' => isset($record->sourceavailable) ? (int)$record->sourceavailable : null,
            'eventcount' => isset($record->eventcount) ? (int)$record->eventcount : 0,
            'reasoncode' => $record->reasoncode ?? null,
            'normpolicyversion' => (string)$record->normpolicyversion,
            'sufficient' => history_policy::is_sufficient_for_interval(
                (string)$record->coveragestatus,
                $earliest,
                $latest,
                $windowstart,
                $windowend
            ),
        ];
    }
}

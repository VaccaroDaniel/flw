<?php
// Program 1 resolver boundary for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-through boundary for Program 1 content deployment identity.
 */
class p1_resolver {
    /**
     * Resolve a Moodle course to Program 1 world/stage identity.
     *
     * @param int $courseid Moodle course id.
     * @return array
     */
    public static function resolve_course(int $courseid): array {
        return self::resolve(['moodlecourseid' => $courseid]);
    }

    /**
     * Resolve a Moodle section to Program 1 unit identity.
     *
     * @param int $courseid Moodle course id.
     * @param int $sectionid Moodle course section id.
     * @return array
     */
    public static function resolve_section(int $courseid, int $sectionid): array {
        return self::resolve(['moodlecourseid' => $courseid, 'moodlesectionid' => $sectionid]);
    }

    /**
     * Resolve a Moodle course module to Program 1 unit/activity identity.
     *
     * @param int $cmid Moodle course module id.
     * @return array
     */
    public static function resolve_cmid(int $cmid): array {
        return self::resolve(['cmid' => $cmid]);
    }

    /**
     * Resolve a SCORM SCO to a Program 1 activity identity.
     *
     * @param int $cmid Moodle course module id.
     * @param string $scoidentifier SCORM SCO identifier.
     * @return array
     */
    public static function resolve_scorm_sco(int $cmid, string $scoidentifier): array {
        return self::resolve(['cmid' => $cmid, 'scoidentifier' => $scoidentifier]);
    }

    /**
     * Cache a Program 1 content link.
     *
     * @param array $data Content link data.
     * @return int Cache row id.
     */
    public static function cache_content_link(array $data): int {
        return repository::upsert_content_link($data);
    }

    /**
     * Resolve from cached content links.
     *
     * @param array $criteria Search criteria.
     * @return array
     */
    private static function resolve(array $criteria): array {
        $link = repository::get_content_link($criteria);
        if ($link) {
            return self::record_to_result($link);
        }
        return self::unresolved($criteria);
    }

    /**
     * Build a resolver result from a cache row.
     *
     * @param \stdClass $record Cache row.
     * @return array
     */
    private static function record_to_result(\stdClass $record): array {
        return [
            'status' => (string)$record->status,
            'freshness' => (string)$record->freshness,
            'sourcekey' => (string)$record->sourcekey,
            'moodlecourseid' => isset($record->moodlecourseid) ? (int)$record->moodlecourseid : null,
            'moodlesectionid' => isset($record->moodlesectionid) ? (int)$record->moodlesectionid : null,
            'cmid' => isset($record->cmid) ? (int)$record->cmid : null,
            'scoidentifier' => $record->scoidentifier ?? null,
            'worldid' => $record->worldid ?? null,
            'stageid' => $record->stageid ?? null,
            'unitid' => $record->unitid ?? null,
            'lessonid' => $record->lessonid ?? null,
            'componentid' => $record->componentid ?? null,
            'activityid' => $record->activityid ?? null,
            'assessmentid' => $record->assessmentid ?? null,
            'questionid' => $record->questionid ?? null,
            'sourcerevision' => $record->sourcerevision ?? null,
            'resolver' => $record->resolver ?? 'p1_contract',
        ];
    }

    /**
     * Build an unresolved result with stable keys present.
     *
     * @param array $criteria Criteria.
     * @return array
     */
    private static function unresolved(array $criteria): array {
        return [
            'status' => 'unresolved',
            'freshness' => 'unknown',
            'sourcekey' => null,
            'moodlecourseid' => $criteria['moodlecourseid'] ?? null,
            'moodlesectionid' => $criteria['moodlesectionid'] ?? null,
            'cmid' => $criteria['cmid'] ?? null,
            'scoidentifier' => $criteria['scoidentifier'] ?? null,
            'worldid' => null,
            'stageid' => null,
            'unitid' => null,
            'lessonid' => null,
            'componentid' => null,
            'activityid' => null,
            'assessmentid' => null,
            'questionid' => null,
            'sourcerevision' => null,
            'resolver' => 'p1_contract',
        ];
    }
}


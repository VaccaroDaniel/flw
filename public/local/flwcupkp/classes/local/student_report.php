<?php
// Student progress helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds student-facing U038 progress data.
 */
class student_report {
    /**
     * Build a learner's U038 progress report.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function u038_progress(int $courseid, int $userid): array {
        $report = teacher_report::u038_report($courseid, ['userid' => $userid]);
        $overview = teacher_report::u038_mastery_overview($courseid, ['userid' => $userid]);
        $rows = [];
        $parentrows = [];
        $mastered = 0;
        $verified = 0;
        $withevidence = 0;
        $competencyachieved = 0;
        $competencytotal = 0;
        $updemonstrated = 0;
        $uptotal = 0;

        foreach ($report['rows'] as $row) {
            $row['activity_url'] = self::activity_url($row);
            $row['is_mastered'] = $row['state'] === 'mastered';
            $row['is_gap'] = !$row['is_mastered'];
            $row['is_teacher_verified'] = !empty($row['verification']) &&
                in_array($row['verification']['action'], ['teacher_evidence_approved', 'teacher_state_overridden'], true);
            $row['next_activity'] = $row['is_gap'] ? self::next_activity($row) : null;

            if ($row['is_mastered']) {
                $mastered++;
            }
            if ($row['is_teacher_verified']) {
                $verified++;
            }
            if (!empty($row['evidence_id'])) {
                $withevidence++;
            }
            $rows[] = $row;
        }

        foreach ($overview['rows'] as $row) {
            $row['is_achieved'] = $row['targettype'] === 'competency' && self::is_competency_achieved($row['state']);
            $row['is_demonstrated'] = $row['targettype'] === 'up' && self::is_up_demonstrated($row['state']);

            if ($row['targettype'] === 'competency') {
                $competencytotal++;
                if ($row['is_achieved']) {
                    $competencyachieved++;
                }
            } else if ($row['targettype'] === 'up') {
                $uptotal++;
                if ($row['is_demonstrated']) {
                    $updemonstrated++;
                }
            }

            $parentrows[] = $row;
        }

        return [
            'rows' => $rows,
            'parent_rows' => $parentrows,
            'summary' => [
                'total' => count($rows),
                'mastered' => $mastered,
                'gaps' => count($rows) - $mastered,
                'verified' => $verified,
                'with_evidence' => $withevidence,
                'percent' => count($rows) > 0 ? round(($mastered / count($rows)) * 100) : 0,
            ],
            'parent_summary' => [
                'competency_total' => $competencytotal,
                'competency_achieved' => $competencyachieved,
                'up_total' => $uptotal,
                'up_demonstrated' => $updemonstrated,
            ],
            'next_recommendation' => self::first_gap($rows),
        ];
    }

    /**
     * Whether a competency state represents achieved mastery.
     *
     * @param string $state
     * @return bool
     */
    private static function is_competency_achieved(string $state): bool {
        return in_array($state, ['achieved', 'sustained', 'mastered'], true);
    }

    /**
     * Whether a Use Point state represents demonstrated use.
     *
     * @param string $state
     * @return bool
     */
    private static function is_up_demonstrated(string $state): bool {
        return in_array($state, ['demonstrated', 'stable', 'transfer_ready'], true);
    }

    /**
     * Build a Moodle activity URL for a report row.
     *
     * @param array $row
     * @return \moodle_url|null
     */
    private static function activity_url(array $row): ?\moodle_url {
        if (empty($row['cmid']) || empty($row['modname'])) {
            return null;
        }
        return new \moodle_url('/mod/' . $row['modname'] . '/view.php', ['id' => $row['cmid']]);
    }

    /**
     * Student-facing next activity copy.
     *
     * @param array $row
     * @return array
     */
    private static function next_activity(array $row): array {
        $reason = empty($row['evidence_id']) ?
            'Start here to collect your first mapped evidence for this Learning Point.' :
            'Practice this mapped activity again to strengthen your evidence.';

        return [
            'title' => $row['object_title'],
            'url' => $row['activity_url'],
            'reason' => $reason,
        ];
    }

    /**
     * Pick the first gap row as the top recommendation.
     *
     * @param array $rows
     * @return array|null
     */
    private static function first_gap(array $rows): ?array {
        foreach ($rows as $row) {
            if (!empty($row['is_gap'])) {
                return $row;
            }
        }
        return null;
    }
}

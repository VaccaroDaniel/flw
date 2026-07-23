<?php
// Audit and coverage service for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Curriculum quality audits.
 */
class audit_service {
    /**
     * Build coverage report.
     *
     * @param int|null $frameworkid
     * @return array
     */
    public static function coverage(?int $frameworkid = null): array {
        global $DB;

        $frameworksql = $frameworkid ? ' WHERE frameworkid = :frameworkid' : '';
        $params = $frameworkid ? ['frameworkid' => $frameworkid] : [];

        $competencies = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {flwcupkp_comp}{$frameworksql}", $params);
        $ups = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {flwcupkp_up}{$frameworksql}", $params);
        $kps = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {flwcupkp_kp}{$frameworksql}", $params);

        $compmapped = (int)$DB->count_records_sql('SELECT COUNT(DISTINCT competencyid) FROM {flwcupkp_comp_up}');
        $upmapped = (int)$DB->count_records_sql('SELECT COUNT(DISTINCT upid) FROM {flwcupkp_up_kp}');
        $kpmappedobjects = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT targetid) FROM {flwcupkp_object_map} WHERE targettype = 'kp'");
        $compdirect = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT targetid) FROM {flwcupkp_evidence} WHERE targettype = 'competency' AND evidencestrength IN ('guided_performance','independent_performance','transfer_performance')");

        return [
            'competencies' => $competencies,
            'use_points' => $ups,
            'knowledge_points' => $kps,
            'competencies_linked_to_up_percent' => self::pct($compmapped, $competencies),
            'use_points_linked_to_kp_percent' => self::pct($upmapped, $ups),
            'kps_linked_to_learning_objects_percent' => self::pct($kpmappedobjects, $kps),
            'competencies_with_direct_evidence_percent' => self::pct($compdirect, $competencies),
            'warnings' => self::warnings($competencies, $ups, $kps, $compmapped, $upmapped, $kpmappedobjects, $compdirect),
        ];
    }

    /**
     * Percentage helper.
     */
    private static function pct(int $part, int $whole): float {
        return $whole === 0 ? 0.0 : round(($part / $whole) * 100, 2);
    }

    /**
     * Build warnings.
     */
    private static function warnings(int $competencies, int $ups, int $kps, int $compmapped, int $upmapped, int $kpmappedobjects, int $compdirect): array {
        $warnings = [];
        if ($competencies > $compmapped) {
            $warnings[] = 'Some competencies are not linked to Use Points.';
        }
        if ($ups > $upmapped) {
            $warnings[] = 'Some Use Points are not linked to Knowledge Points.';
        }
        if ($kps > $kpmappedobjects) {
            $warnings[] = 'Some Knowledge Points are not linked to learning objects.';
        }
        if ($competencies > $compdirect) {
            $warnings[] = 'Some competencies do not yet have direct integrated performance evidence.';
        }
        return $warnings;
    }
}

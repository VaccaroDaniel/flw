<?php
// Import validation helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates C-UP-KP package structures.
 */
class validator {
    /** @var array Allowed KP domains. */
    private const KP_DOMAINS = ['LEX', 'GRAM', 'FUNC', 'PRON', 'ORTH', 'SCRIPT', 'LISTEN', 'SPEAK', 'READ', 'WRITE', 'DISC', 'STRAT', 'PRAG', 'CULT'];

    /** @var array Allowed C-UP-KP target types. */
    private const TARGET_TYPES = ['competency', 'up', 'kp'];

    /**
     * Validate decoded package.
     *
     * @param array $package
     * @return array
     */
    public static function validate_package(array $package): array {
        $errors = [];
        $warnings = [];

        foreach (['cupkp_schema_version', 'frameworks', 'competencies', 'use_points', 'knowledge_points'] as $required) {
            if (!array_key_exists($required, $package)) {
                $errors[] = "Missing required field: {$required}";
            }
        }

        foreach (['competencies', 'use_points', 'knowledge_points', 'learning_objects'] as $key) {
            $seen = [];
            foreach (($package[$key] ?? []) as $row) {
                $externalid = $row['externalid'] ?? '';
                if ($externalid === '') {
                    $errors[] = "{$key} row missing externalid";
                    continue;
                }
                if (isset($seen[$externalid])) {
                    $errors[] = "Duplicate {$key} externalid: {$externalid}";
                }
                $seen[$externalid] = true;
            }
        }

        $seenlessonobjects = [];
        foreach (($package['lesson_mappings'] ?? []) as $row) {
            $objectid = $row['object_externalid'] ?? ($row['externalid'] ?? '');
            if ($objectid === '') {
                $errors[] = 'lesson_mappings row missing object_externalid';
                continue;
            }
            if (isset($seenlessonobjects[$objectid])) {
                $errors[] = "Duplicate lesson_mappings object externalid: {$objectid}";
            }
            $seenlessonobjects[$objectid] = true;
            if (!empty($row['target_type']) && !in_array((string)$row['target_type'], self::TARGET_TYPES, true)) {
                $errors[] = 'Invalid lesson_mappings target_type for ' . $objectid . ': ' . $row['target_type'];
            }
            if (!empty($row['target_type']) && empty($row['target_externalid'])) {
                $errors[] = 'lesson_mappings row with target_type must include target_externalid: ' . $objectid;
            }
        }

        foreach (($package['project_competency_mappings'] ?? []) as $row) {
            $objectid = $row['object_externalid'] ?? ($row['externalid'] ?? '');
            if ($objectid === '') {
                $errors[] = 'project_competency_mappings row missing object_externalid';
            }
            if (empty($row['competency_externalid']) && empty($row['competency_externalids'])) {
                $errors[] = 'project_competency_mappings row missing competency_externalid';
            }
        }

        foreach (($package['knowledge_points'] ?? []) as $kp) {
            $domain = $kp['domain'] ?? '';
            if ($domain !== '' && !in_array($domain, self::KP_DOMAINS, true)) {
                $errors[] = 'Invalid KP domain for ' . ($kp['externalid'] ?? 'unknown') . ': ' . $domain;
            }
        }

        $cycle = self::find_mandatory_prereq_cycle($package['kp_prerequisites'] ?? []);
        if ($cycle !== null) {
            $errors[] = 'Circular mandatory KP prerequisite chain detected: ' . implode(' -> ', $cycle);
        }

        if (empty($package['project_evidence'])) {
            $warnings[] = 'No project_evidence entries found; competencies may lack direct integrated evidence.';
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Find a cycle in mandatory prerequisites.
     *
     * @param array $edges
     * @return array|null
     */
    private static function find_mandatory_prereq_cycle(array $edges): ?array {
        $graph = [];
        foreach ($edges as $edge) {
            if (($edge['requirement'] ?? '') !== 'mandatory') {
                continue;
            }
            $from = $edge['kp_externalid'] ?? null;
            $to = $edge['prereq_kp_externalid'] ?? null;
            if ($from && $to) {
                $graph[$from][] = $to;
            }
        }

        $visiting = [];
        $visited = [];
        $path = [];

        $walk = function ($node) use (&$walk, &$graph, &$visiting, &$visited, &$path) {
            if (isset($visiting[$node])) {
                $path[] = $node;
                return true;
            }
            if (isset($visited[$node])) {
                return false;
            }
            $visiting[$node] = true;
            $path[] = $node;
            foreach ($graph[$node] ?? [] as $next) {
                if ($walk($next)) {
                    return true;
                }
            }
            unset($visiting[$node]);
            $visited[$node] = true;
            array_pop($path);
            return false;
        };

        foreach (array_keys($graph) as $node) {
            if ($walk($node)) {
                return $path;
            }
        }

        return null;
    }
}

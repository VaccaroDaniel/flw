<?php
// Create/reuse native Moodle competencies and link them to C-UP-KP records.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'dryrun' => false,
    'nocourselinks' => false,
    'help' => false,
], [
    'd' => 'dryrun',
    'n' => 'nocourselinks',
    'h' => 'help',
]);

if ($options['help']) {
    echo "Link local_flwcupkp Framework/Competency rows to native Moodle competencies.\n";
    echo "Usage: php local/flwcupkp/cli/link_moodle_competencies.php [--dryrun] [--nocourselinks]\n";
    echo "--dryrun prints the planned operations without writing.\n";
    echo "--nocourselinks skips adding linked Moodle competencies to courses discovered from mapped learning objects.\n";
    exit(0);
}

$dryrun = (bool)$options['dryrun'];
$linkcourses = empty($options['nocourselinks']);

\core\session\manager::set_user(get_admin());

$scale = $DB->get_record('scale', ['name' => 'Default competence scale'], '*', IGNORE_MISSING);
if (!$scale) {
    cli_error('Default competence scale was not found.');
}

$summary = [
    'dryrun' => $dryrun,
    'course_links_enabled' => $linkcourses,
    'competencies_enabled_before' => (bool)get_config('core_competency', 'enabled'),
    'competencies_enabled_after' => null,
    'scaleid' => (int)$scale->id,
    'frameworks' => [],
    'competencies' => [],
    'course_links' => [],
];

if (!$dryrun && !get_config('core_competency', 'enabled')) {
    set_config('enabled', 1, 'core_competency');
}
$summary['competencies_enabled_after'] = $dryrun ? true : (bool)get_config('core_competency', 'enabled');

$scaleconfiguration = local_flwcupkp_cli_scale_configuration($scale);
$transaction = $dryrun ? null : $DB->start_delegated_transaction();
$nativeframeworkids = [];

try {
    foreach ($DB->get_records('flwcupkp_framework', null, 'id ASC') as $framework) {
        [$nativeid, $created] = local_flwcupkp_cli_link_framework($framework, $scale, $scaleconfiguration, $dryrun);
        $nativeframeworkids[(int)$framework->id] = $nativeid;
        $summary['frameworks'][] = [
            'flwcupkp_framework_id' => (int)$framework->id,
            'externalid' => $framework->externalid,
            'moodleframeworkid' => $nativeid,
            'created' => $created,
            'linked' => !$dryrun && (int)($framework->moodleframeworkid ?? 0) !== $nativeid,
        ];
    }

    foreach ($DB->get_records('flwcupkp_comp', null, 'id ASC') as $competency) {
        $nativeframeworkid = $nativeframeworkids[(int)$competency->frameworkid] ?? 0;
        if (!$nativeframeworkid) {
            throw new moodle_exception('Missing native Moodle framework for ' . $competency->externalid);
        }

        [$nativeid, $created] = local_flwcupkp_cli_link_competency($competency, $nativeframeworkid, $dryrun);
        $summary['competencies'][] = [
            'flwcupkp_competency_id' => (int)$competency->id,
            'externalid' => $competency->externalid,
            'moodlecompetencyid' => $nativeid,
            'moodleframeworkid' => $nativeframeworkid,
            'created' => $created,
            'linked' => !$dryrun && (int)($competency->moodlecompetencyid ?? 0) !== $nativeid,
        ];

        if ($linkcourses && $nativeid > 0) {
            foreach (local_flwcupkp_cli_courseids_for_framework((int)$competency->frameworkid) as $courseid) {
                [$added, $already] = local_flwcupkp_cli_link_course_competency($courseid, $nativeid, $dryrun);
                $summary['course_links'][] = [
                    'courseid' => $courseid,
                    'moodlecompetencyid' => $nativeid,
                    'created' => $added,
                    'alreadyexisted' => $already,
                ];
            }
        }
    }

    if ($transaction) {
        $transaction->allow_commit();
    }
} catch (Throwable $e) {
    if ($transaction) {
        $transaction->rollback($e);
    }
    cli_error(get_class($e) . ': ' . $e->getMessage());
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

/**
 * Build a Moodle competency scale configuration.
 *
 * @param stdClass $scale
 * @return string
 */
function local_flwcupkp_cli_scale_configuration(stdClass $scale): string {
    $items = array_map('trim', explode(',', $scale->scale));
    if (count($items) < 2) {
        cli_error('Default competence scale needs at least two items.');
    }

    $configuration = [['scaleid' => (int)$scale->id]];
    foreach ($items as $index => $item) {
        $grade = $index + 1;
        $configuration[] = [
            'id' => $grade,
            'scaledefault' => $grade === 1 ? 1 : 0,
            'proficient' => $grade === count($items) ? 1 : 0,
        ];
    }

    return json_encode($configuration);
}

/**
 * Create or reuse a native Moodle competency framework.
 *
 * @param stdClass $framework
 * @param stdClass $scale
 * @param string $scaleconfiguration
 * @param bool $dryrun
 * @return array
 */
function local_flwcupkp_cli_link_framework(stdClass $framework, stdClass $scale, string $scaleconfiguration,
        bool $dryrun): array {
    global $DB;

    $native = false;
    if (!empty($framework->moodleframeworkid)) {
        $native = $DB->get_record('competency_framework', ['id' => (int)$framework->moodleframeworkid],
            '*', IGNORE_MISSING);
    }
    if (!$native) {
        $native = $DB->get_record('competency_framework', ['idnumber' => $framework->externalid], '*', IGNORE_MISSING);
    }
    if ($native) {
        $nativeid = (int)$native->id;
        if (!$dryrun && ((int)$native->scaleid !== (int)$scale->id ||
                (string)$native->scaleconfiguration !== $scaleconfiguration)) {
            $native->scaleid = (int)$scale->id;
            $native->scaleconfiguration = $scaleconfiguration;
            \core_competency\api::update_framework($native);
            \local_flwcupkp\local\repository::audit('moodle_competency_framework_scale_normalized', 'framework',
                (int)$framework->id, ['moodleframeworkid' => $nativeid, 'externalid' => $framework->externalid]);
        }
        if (!$dryrun && (int)($framework->moodleframeworkid ?? 0) !== $nativeid) {
            $DB->set_field('flwcupkp_framework', 'moodleframeworkid', $nativeid, ['id' => (int)$framework->id]);
            \local_flwcupkp\local\repository::audit('moodle_competency_framework_linked', 'framework',
                (int)$framework->id, ['moodleframeworkid' => $nativeid, 'externalid' => $framework->externalid]);
        }
        return [$nativeid, false];
    }

    if ($dryrun) {
        return [0, true];
    }

    $record = (object)[
        'shortname' => $framework->name,
        'idnumber' => $framework->externalid,
        'description' => trim((string)($framework->description ?? '')) ?:
            'Created from local_flwcupkp framework ' . $framework->externalid,
        'descriptionformat' => FORMAT_PLAIN,
        'visible' => 1,
        'scaleid' => (int)$scale->id,
        'scaleconfiguration' => $scaleconfiguration,
        'contextid' => context_system::instance()->id,
        'taxonomies' => '',
    ];
    $nativeframework = \core_competency\api::create_framework($record);
    $nativeid = (int)$nativeframework->get('id');
    $DB->set_field('flwcupkp_framework', 'moodleframeworkid', $nativeid, ['id' => (int)$framework->id]);
    \local_flwcupkp\local\repository::audit('moodle_competency_framework_linked', 'framework',
        (int)$framework->id, [
            'moodleframeworkid' => $nativeid,
            'externalid' => $framework->externalid,
            'created' => true,
        ]);

    return [$nativeid, true];
}

/**
 * Create or reuse a native Moodle competency.
 *
 * @param stdClass $competency
 * @param int $nativeframeworkid
 * @param bool $dryrun
 * @return array
 */
function local_flwcupkp_cli_link_competency(stdClass $competency, int $nativeframeworkid, bool $dryrun): array {
    global $DB;

    $native = false;
    if (!empty($competency->moodlecompetencyid)) {
        $native = $DB->get_record('competency', [
            'id' => (int)$competency->moodlecompetencyid,
            'competencyframeworkid' => $nativeframeworkid,
        ], '*', IGNORE_MISSING);
    }
    if (!$native) {
        $native = $DB->get_record('competency', [
            'competencyframeworkid' => $nativeframeworkid,
            'idnumber' => $competency->externalid,
        ], '*', IGNORE_MISSING);
    }
    if ($native) {
        $nativeid = (int)$native->id;
        if (!$dryrun && (int)($competency->moodlecompetencyid ?? 0) !== $nativeid) {
            $DB->set_field('flwcupkp_comp', 'moodlecompetencyid', $nativeid, ['id' => (int)$competency->id]);
            \local_flwcupkp\local\repository::audit('moodle_competency_linked', 'competency',
                (int)$competency->id, [
                    'moodlecompetencyid' => $nativeid,
                    'moodleframeworkid' => $nativeframeworkid,
                    'externalid' => $competency->externalid,
                ]);
        }
        return [$nativeid, false];
    }

    if ($dryrun) {
        return [0, true];
    }

    $descriptionparts = array_filter([
        trim((string)($competency->cando ?? '')),
        trim((string)($competency->description ?? '')),
        'C-UP-KP external ID: ' . $competency->externalid,
    ]);
    $record = (object)[
        'shortname' => $competency->title,
        'idnumber' => $competency->externalid,
        'description' => implode("\n\n", $descriptionparts),
        'descriptionformat' => FORMAT_PLAIN,
        'competencyframeworkid' => $nativeframeworkid,
        'parentid' => 0,
    ];
    $nativecompetency = \core_competency\api::create_competency($record);
    $nativeid = (int)$nativecompetency->get('id');
    $DB->set_field('flwcupkp_comp', 'moodlecompetencyid', $nativeid, ['id' => (int)$competency->id]);
    \local_flwcupkp\local\repository::audit('moodle_competency_linked', 'competency', (int)$competency->id, [
        'moodlecompetencyid' => $nativeid,
        'moodleframeworkid' => $nativeframeworkid,
        'externalid' => $competency->externalid,
        'created' => true,
    ]);

    return [$nativeid, true];
}

/**
 * Discover Moodle courses linked to C-UP-KP learning objects in a framework.
 *
 * @param int $frameworkid
 * @return array
 */
function local_flwcupkp_cli_courseids_for_framework(int $frameworkid): array {
    global $DB;

    $courseids = [];
    $frameworkcourseid = $DB->get_field('flwcupkp_framework', 'courseid', ['id' => $frameworkid], IGNORE_MISSING);
    if (!empty($frameworkcourseid)) {
        $courseids[(int)$frameworkcourseid] = true;
    }
    $objectcourseids = $DB->get_fieldset_select(
        'flwcupkp_object',
        'DISTINCT courseid',
        'frameworkid = :frameworkid AND courseid IS NOT NULL AND courseid > 0',
        ['frameworkid' => $frameworkid]
    );
    foreach ($objectcourseids as $courseid) {
        $courseids[(int)$courseid] = true;
    }

    return array_keys($courseids);
}

/**
 * Add a native Moodle competency to a course.
 *
 * @param int $courseid
 * @param int $competencyid
 * @param bool $dryrun
 * @return array
 */
function local_flwcupkp_cli_link_course_competency(int $courseid, int $competencyid, bool $dryrun): array {
    global $DB;

    if (!$DB->record_exists('course', ['id' => $courseid])) {
        return [false, false];
    }

    $already = $DB->record_exists('competency_coursecomp', [
        'courseid' => $courseid,
        'competencyid' => $competencyid,
    ]);
    if ($already || $dryrun) {
        return [false, $already];
    }

    $added = \core_competency\api::add_competency_to_course($courseid, $competencyid);
    return [(bool)$added, false];
}

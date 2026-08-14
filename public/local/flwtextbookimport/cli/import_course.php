<?php
// This file is part of Moodle - http://moodle.org/.

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../classes/local/importer.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'input' => '',
        'execute' => false,
        'create-activities' => false,
        'compose-lesson' => false,
        'publish-lesson' => false,
        'unpublish-lesson' => false,
        'reuse-course' => false,
        'reuse-modules' => false,
        'visible' => false,
        'section' => '',
        'types' => 'page,assign',
        'review-statuses' => 'needs_teacher_review,needs_activity_review',
        'limit' => 0,
        'json' => false,
    ],
    [
        'h' => 'help',
        'i' => 'input',
    ]
);

if ($unrecognized) {
    cli_error("Unknown option(s):\n  " . implode("\n  ", $unrecognized));
}

if (!empty($options['help'])) {
    echo "Import an FLW textbook dry-run package into Moodle.\n\n";
    echo "The default mode validates and previews the package without writing to Moodle.\n";
    echo "The first execute boundary creates or updates only the course shell and section summaries;\n";
    echo "planned activities remain in the generated CSV/JSON review package.\n\n";
    echo "Usage:\n";
    echo "  php local/flwtextbookimport/cli/import_course.php --input=/path/to/dry_run.json [--json]\n";
    echo "  php local/flwtextbookimport/cli/import_course.php --input=/path/to/dry_run.json --execute [--reuse-course] [--visible]\n\n";
    echo "  php local/flwtextbookimport/cli/import_course.php --input=/path/to/dry_run.json --create-activities --section=1 [--reuse-modules]\n\n";
    echo "  php local/flwtextbookimport/cli/import_course.php --input=/path/to/dry_run.json --compose-lesson --section=1 [--visible]\n\n";
    echo "  php local/flwtextbookimport/cli/import_course.php --input=/path/to/dry_run.json --publish-lesson --section=1\n\n";
    echo "Options:\n";
    echo "  --input=PATH      Required dry-run JSON package from the FLW importer pilot.\n";
    echo "  --execute         Write the course shell and section summaries to Moodle.\n";
    echo "  --create-activities Create hidden Page/Assignment modules from approved activity-plan rows.\n";
    echo "  --compose-lesson Update an imported lesson section with learner-ready Page/Assignment templates.\n";
    echo "  --publish-lesson Publish only the selected composed lesson section.\n";
    echo "  --unpublish-lesson Hide the selected lesson section again for review.\n";
    echo "  --section=N       Required with --create-activities. Imports only one section.\n";
    echo "  --types=LIST      Comma-separated Moodle modules to create. Default: page,assign.\n";
    echo "  --review-statuses=LIST Comma-separated review statuses. Default: needs_teacher_review,needs_activity_review.\n";
    echo "  --limit=N         Maximum activities to create. Default: 0 for no cap.\n";
    echo "  --reuse-course    Allow updating an existing course with the same shortname.\n";
    echo "  --reuse-modules   Skip existing generated modules instead of failing.\n";
    echo "  --visible         Make the created/updated course visible. Default is hidden.\n";
    echo "  --json            Print machine-readable JSON instead of human-readable text.\n";
    echo "  --help, -h        Show this help.\n";
    exit(0);
}

try {
    $input = trim((string)$options['input']);
    $package = \local_flwtextbookimport\local\importer::load_package($input);

    $modes = array_filter([
        !empty($options['execute']),
        !empty($options['create-activities']),
        !empty($options['compose-lesson']),
        !empty($options['publish-lesson']),
        !empty($options['unpublish-lesson']),
    ]);
    if (count($modes) > 1) {
        cli_error('Use only one write mode: --execute, --create-activities, --compose-lesson, --publish-lesson, or --unpublish-lesson.');
    }

    if (!empty($options['publish-lesson']) || !empty($options['unpublish-lesson'])) {
        if ((string)$options['section'] === '') {
            cli_error('--section=N is required with --publish-lesson or --unpublish-lesson.');
        }
        $result = \local_flwtextbookimport\local\importer::set_lesson_published(
            $package,
            (int)$options['section'],
            !empty($options['publish-lesson'])
        );
    } else if (!empty($options['compose-lesson'])) {
        if ((string)$options['section'] === '') {
            cli_error('--section=N is required with --compose-lesson.');
        }
        $result = \local_flwtextbookimport\local\importer::compose_lesson_content(
            $package,
            (int)$options['section'],
            !empty($options['visible'])
        );
    } else if (!empty($options['create-activities'])) {
        if ((string)$options['section'] === '') {
            cli_error('--section=N is required with --create-activities.');
        }
        $result = \local_flwtextbookimport\local\importer::create_activities(
            $package,
            (int)$options['section'],
            local_flwtextbookimport_split_list((string)$options['types']),
            local_flwtextbookimport_split_list((string)$options['review-statuses']),
            max(0, (int)$options['limit']),
            !empty($options['reuse-modules']),
            !empty($options['visible'])
        );
    } else if (!empty($options['execute'])) {
        $result = \local_flwtextbookimport\local\importer::execute(
            $package,
            !empty($options['reuse-course']),
            !empty($options['visible'])
        );
    } else {
        $result = \local_flwtextbookimport\local\importer::preview($package);
    }

    if (!empty($options['json'])) {
        cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        exit(0);
    }

    local_flwtextbookimport_print_result($result);
    exit(0);
} catch (\Throwable $e) {
    if (!empty($options['json'])) {
        cli_writeln(json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'exception' => get_class($e),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        exit(1);
    }
    cli_error($e->getMessage());
}

/**
 * Print importer result for humans.
 *
 * @param array $result
 */
function local_flwtextbookimport_print_result(array $result): void {
    cli_writeln('FLW textbook importer');
    cli_writeln('Mode: ' . ($result['mode'] ?? 'unknown'));

    if (!empty($result['course'])) {
        cli_writeln('Course: ' . ($result['course']['shortname'] ?? '') . ' - ' . ($result['course']['fullname'] ?? ''));
        if (array_key_exists('id', $result['course'])) {
            cli_writeln('Course ID: ' . $result['course']['id']);
        }
        if (array_key_exists('existing_course_id', $result['course'])) {
            cli_writeln('Existing course ID: ' . ($result['course']['existing_course_id'] ?? 'none'));
        }
    }

    if (!empty($result['counts'])) {
        cli_writeln('Sections planned: ' . $result['counts']['sections']);
        cli_writeln('Lesson sections planned: ' . $result['counts']['lesson_sections']);
        cli_writeln('Activities in review plan: ' . $result['counts']['activities_in_plan']);
    }

    foreach ([
        'categoriescreated',
        'coursecreated',
        'courseupdated',
        'sectionscreated',
        'sectionsupdated',
        'activitiesconsidered',
        'activitiescreated',
        'activitiesupdated',
        'activitiesskipped',
        'activitiesfiltered',
        'activitiesunsupported',
        'activitiesleftasplan',
        'modulesconsidered',
        'modulesupdated',
        'modulesmissing',
        'modulesunsupported',
        'modulesvisible',
        'moduleshidden',
    ] as $key) {
        if (array_key_exists($key, $result)) {
            cli_writeln($key . ': ' . $result[$key]);
        }
    }

    if (!empty($result['categories'])) {
        cli_writeln('Category path:');
        foreach ($result['categories'] as $category) {
            $status = $category['exists'] ? 'exists' : 'missing';
            cli_writeln('  - ' . $category['name'] . ': ' . $status);
        }
    }

    foreach ($result['warnings'] ?? [] as $warning) {
        cli_writeln('Warning: ' . $warning);
    }
}

/**
 * Split comma-separated CLI options.
 *
 * @param string $value
 * @return array
 */
function local_flwtextbookimport_split_list(string $value): array {
    $parts = array_map('trim', explode(',', $value));
    return array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
}

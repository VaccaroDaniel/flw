<?php
// This file is part of FLW Moodle local tooling.

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'apply' => false,
    'update-existing' => false,
    'yes' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unknown option(s):\n  " . $unrecognized);
}

if (!empty($options['help'])) {
    echo "FLW SCORM Clean Viewer defaults helper\n\n";
    echo "Dry-run:\n";
    echo "  php scripts/flw_scorm_clean_defaults.php\n\n";
    echo "Apply global SCORM defaults for new activities:\n";
    echo "  php scripts/flw_scorm_clean_defaults.php --apply\n\n";
    echo "Apply defaults and update existing SCORM activities:\n";
    echo "  php scripts/flw_scorm_clean_defaults.php --apply --update-existing --yes\n";
    exit(0);
}

$recommended = [
    'displaycoursestructure' => 0,
    'skipview' => SCORM_SKIPVIEW_ALWAYS,
    'hidebrowse' => 1,
    'hidetoc' => SCORM_TOC_DISABLED,
    'displayattemptstatus' => SCORM_DISPLAY_ATTEMPTSTATUS_MY,
    'popup' => 0,
    'nav' => SCORM_NAV_DISABLED,
];

$labels = [
    'displaycoursestructure' => 'Display course structure on entry page: No',
    'skipview' => 'Student skip content structure page: Always',
    'hidebrowse' => 'Disable preview mode: Yes',
    'hidetoc' => 'Display course structure in player: Disabled',
    'displayattemptstatus' => 'Display attempt status: My home page only',
    'popup' => 'Display package: Current window',
    'nav' => 'Show navigation: No',
];

$apply = !empty($options['apply']);
$updateexisting = !empty($options['update-existing']);
$yes = !empty($options['yes']);

if ($updateexisting && (!$apply || !$yes)) {
    cli_error('Updating existing SCORM activities requires --apply --update-existing --yes.');
}

echo "FLW SCORM Clean Viewer v1 settings\n";
echo $apply ? "Mode: APPLY\n\n" : "Mode: DRY RUN\n\n";

foreach ($recommended as $name => $value) {
    $current = get_config('scorm', $name);
    $currentdisplay = ($current === false) ? '(not set)' : $current;
    echo $labels[$name] . "\n";
    echo "  Current: " . $currentdisplay . "\n";
    echo "  Target:  " . $value . "\n";

    if ($apply) {
        set_config($name, $value, 'scorm');
        echo "  Updated global default.\n";
    }

    echo "\n";
}

if ($updateexisting) {
    $record = (object) $recommended;
    $count = $DB->count_records('scorm');

    if ($count > 0) {
        $DB->set_field('scorm', 'displaycoursestructure', $record->displaycoursestructure);
        $DB->set_field('scorm', 'skipview', $record->skipview);
        $DB->set_field('scorm', 'hidebrowse', $record->hidebrowse);
        $DB->set_field('scorm', 'hidetoc', $record->hidetoc);
        $DB->set_field('scorm', 'displayattemptstatus', $record->displayattemptstatus);
        $DB->set_field('scorm', 'popup', $record->popup);
        $DB->set_field('scorm', 'nav', $record->nav);
    }

    echo "Existing SCORM activities updated: " . $count . "\n";
} else {
    echo "Existing SCORM activities were not changed.\n";
}

if (!$apply) {
    echo "\nRun again with --apply to update global defaults.\n";
}

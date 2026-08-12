<?php
// Validate a C-UP-KP JSON package from CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'file' => null,
    'format' => null,
    'type' => 'activity_mappings',
    'import' => false,
    'help' => false,
], [
    'f' => 'file',
    't' => 'type',
    'i' => 'import',
    'h' => 'help',
]);

if ($options['help'] || empty($options['file'])) {
    echo "Validate or import a C-UP-KP JSON package or supported CSV artifact.\n";
    echo "Usage: php local/flwcupkp/cli/validate_import.php --file=/path/package.json [--import]\n";
    echo "       php local/flwcupkp/cli/validate_import.php --file=/path/activity_cupkp_mapping.csv --format=csv --type=activity_mappings [--import]\n";
    echo "       php local/flwcupkp/cli/validate_import.php --file=/path/quiz_kp_mapping.csv --format=csv --type=quiz_kp_mappings [--import]\n";
    exit(0);
}

$file = $options['file'];
if (!is_readable($file)) {
    cli_error('File is not readable: ' . $file);
}

$content = file_get_contents($file);
$format = strtolower((string)($options['format'] ?: pathinfo($file, PATHINFO_EXTENSION)));
if ($format === 'csv') {
    if ($options['import']) {
        $result = \local_flwcupkp\local\import_service::import_csv($content, (string)$options['type'], $file);
    } else {
        $result = \local_flwcupkp\local\import_service::validate_csv($content, (string)$options['type']);
    }
} else {
    if ($options['import']) {
        $result = \local_flwcupkp\local\import_service::import_json($content, $file);
    } else {
        $package = json_decode($content, true);
        $result = \local_flwcupkp\local\validator::validate_package(is_array($package) ? $package : []);
    }
}

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

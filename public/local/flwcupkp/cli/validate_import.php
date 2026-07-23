<?php
// Validate a C-UP-KP JSON package from CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'file' => null,
    'import' => false,
    'help' => false,
], [
    'f' => 'file',
    'i' => 'import',
    'h' => 'help',
]);

if ($options['help'] || empty($options['file'])) {
    echo "Validate or import a C-UP-KP JSON package.\n";
    echo "Usage: php local/flwcupkp/cli/validate_import.php --file=/path/package.json [--import]\n";
    exit(0);
}

$file = $options['file'];
if (!is_readable($file)) {
    cli_error('File is not readable: ' . $file);
}

$json = file_get_contents($file);
if ($options['import']) {
    $result = \local_flwcupkp\local\import_service::import_json($json, $file);
} else {
    $package = json_decode($json, true);
    $result = \local_flwcupkp\local\validator::validate_package(is_array($package) ? $package : []);
}

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

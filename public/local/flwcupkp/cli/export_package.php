<?php
// Export a canonical C-UP-KP JSON package from CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'frameworkid' => 0,
    'output' => null,
    'help' => false,
], [
    'f' => 'frameworkid',
    'o' => 'output',
    'h' => 'help',
]);

if ($options['help']) {
    echo "Export a C-UP-KP JSON package.\n";
    echo "Usage: php local/flwcupkp/cli/export_package.php [--frameworkid=ID] [--output=/path/package.json]\n";
    exit(0);
}

$frameworkid = (int)$options['frameworkid'];
$package = \local_flwcupkp\local\curriculum_manager::export_package($frameworkid);
$json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if (!empty($options['output'])) {
    $output = (string)$options['output'];
    $directory = dirname($output);
    if (!is_dir($directory) || !is_writable($directory)) {
        cli_error('Output directory is not writable: ' . $directory);
    }
    if (file_put_contents($output, $json) === false) {
        cli_error('Unable to write export file: ' . $output);
    }
    cli_writeln('Exported C-UP-KP package to ' . $output);
    exit(0);
}

echo $json;

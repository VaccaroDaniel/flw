<?php
// Create/link a generic C-UP-KP unit shell in Moodle.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

global $USER;
if (!is_siteadmin()) {
    $USER = get_admin();
    \core\session\manager::set_user($USER);
}

[$options] = cli_get_params([
    'create-shell' => false,
    'link' => false,
    'status' => false,
    'unitcode' => '',
    'courseid' => 0,
    'shortname' => '',
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || (!$options['create-shell'] && !$options['link'] && !$options['status'])) {
    echo "Create/link a generic C-UP-KP unit course shell.\n";
    echo "Usage:\n";
    echo "  php local/flwcupkp/cli/link_unit.php --create-shell --unitcode=U037 [--shortname=SHORT]\n";
    echo "  php local/flwcupkp/cli/link_unit.php --link --unitcode=U037 --courseid=ID\n";
    echo "  php local/flwcupkp/cli/link_unit.php --status --unitcode=U037 [--courseid=ID]\n";
    exit(0);
}

$unitcode = clean_param((string)$options['unitcode'], PARAM_ALPHANUMEXT);
if ($unitcode === '') {
    cli_error('--unitcode is required.');
}

if ($options['create-shell']) {
    echo json_encode(\local_flwcupkp\local\unit_setup_service::create_shell(
        $unitcode,
        (string)$options['shortname'],
        true
    ), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['link']) {
    $courseid = (int)$options['courseid'];
    if ($courseid <= 0) {
        cli_error('--courseid is required for --link.');
    }
    echo json_encode(\local_flwcupkp\local\unit_setup_service::link_course($unitcode, $courseid),
        JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($options['status']) {
    echo json_encode(\local_flwcupkp\local\unit_setup_service::status($unitcode, (int)$options['courseid']),
        JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

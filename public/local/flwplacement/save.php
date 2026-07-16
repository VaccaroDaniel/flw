<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwplacement\service\result_repository;

require_login();
require_sesskey();

$context = context_system::instance();
local_flwplacement_require_take_access($context);

header('Content-Type: application/json');

if (!$DB->get_manager()->table_exists('local_flwplacement')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => get_string('pluginnotinstalled', 'local_flwplacement')]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['result']) || !is_array($payload['result'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => get_string('invalidpayload', 'local_flwplacement')]);
    exit;
}

$required = [
    'overall_cefr',
    'recommended_start_unit',
    'placement_confidence',
    'placement_status',
    'skill_levels',
    'kp_mastery',
    'support_flags',
    'speaking_profile',
    'learning_path',
    'audit',
];
foreach ($required as $key) {
    if (!array_key_exists($key, $payload['result'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => get_string('invalidpayload', 'local_flwplacement')]);
        exit;
    }
}

$id = result_repository::save_result(
    $USER->id,
    SITEID,
    $payload['result'],
    is_array($payload['attempt'] ?? null) ? $payload['attempt'] : []
);

echo json_encode([
    'success' => true,
    'id' => $id,
    'viewUrl' => (new moodle_url('/local/flwplacement/view.php', ['id' => $id]))->out(false),
    'message' => get_string('saved', 'local_flwplacement'),
]);

<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login();

$context = context_system::instance();
if (
    !has_capability('local/flwplacement:manage', $context) &&
    !has_capability('local/flwplacement:viewreports', $context)
) {
    local_flwplacement_require_take_access($context);
}

$path = __DIR__ . '/assets/exports/moodle-question-bank.csv';
if (!is_readable($path)) {
    throw new moodle_exception('filenotfound', 'error');
}

send_file($path, 'flw-placement-moodle-question-bank.csv', 0, 0, true, true, 'text/csv');

<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();

$params = ['view' => 'available'];
$language = optional_param('language', '', PARAM_ALPHANUMEXT);
$cefr = optional_param('cefr', '', PARAM_ALPHANUMEXT);
if ($language !== '') {
    $params['language'] = $language;
}
if ($cefr !== '') {
    $params['cefr'] = $cefr;
}

redirect(new moodle_url('/local/flwexam/index.php', $params));

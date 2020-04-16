<?php
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');

$entsync = \auth_entsync\container::services();
$server = $entsync->query('api_server');
$rep = $server->handle();
if (false === $rep) auth_entsync_error(404);
$OUTPUT->header();
echo json_encode($rep);

function auth_entsync_error($code) {
    http_response_code($code);
    die();
}

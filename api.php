<?php
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');
header('Content-type: application/json');

$entsync = \auth_entsync\container::services();
$server = $entsync->query('api_server');
$rep = $server->handle();
if (false === $rep) auth_entsync_error(404);
echo json_encode($rep, JSON_UNESCAPED_UNICODE);

function auth_entsync_error($code) {
    http_response_code($code);
    die();
}

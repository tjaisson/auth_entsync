<?php
define('NO_DEBUG_DISPLAY', true);
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIE', true);
require(__DIR__ . '/../../../config.php');
header('Content-type: application/json');
$entsync = \auth_entsync\container::services();
$server = $entsync->query('api_server');
echo $server->handle_public();

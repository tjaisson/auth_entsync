<?php
define('NO_DEBUG_DISPLAY', true);
define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_login();
header('Content-type: application/json');
$entsync = \auth_entsync\container::services();
$server = $entsync->query('api_server');
echo $server->handle_mdl();

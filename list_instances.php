<?php
define('NO_DEBUG_DISPLAY', true);
define('AJAX_SCRIPT', true);
// Détection du mode admin
if (count($_GET) > 1) auth_entsync_error(404);
if (count($_GET) === 1)  {
    if (!isset($_GET['admin'])) auth_entsync_error(404);
    if (!empty($_GET['admin'])) auth_entsync_error(404);
    $adminMode = true;
} else {
    // Pas besoin de la session.
    define('NO_MOODLE_COOKIE', true);
    $adminMode = false;
}
require(__DIR__ . '/../../config.php');
header('Content-type: application/json');
if ($adminMode) {
    require_login();
    $sitecontext = context_system::instance();
    require_capability('moodle/site:config', $sitecontext);
}
$entsync = \auth_entsync\container::services();
$instances = $entsync->query('instances');
return $instances->instances_json($adminMode);
function auth_entsync_error($code) {
    http_response_code($code);
    die();
}

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
    echo json_encode(auth_entsync_list(true));
    die();
}
$cache = \cache::make('auth_entsync', 'farm');
if (false !== ($jsonList = $cache->get('instances'))) {
    echo $jsonList;
    die();
}
$jsonList = json_encode(auth_entsync_list());
$cache->set('instances', $jsonList);
echo $jsonList;
die();
function auth_entsync_error($code) {
    http_response_code($code);
    die();
}
function auth_entsync_list($admin = false) {
    $entsync = \auth_entsync\container::services();
    $instances = $entsync->query('instances');
    $lst = [];
    $instancesList = $instances->get_instances([], 'dir');
    foreach ($instancesList as $instance) {
        if (($admin) || ($instance->get('rne') !== '00')) {
            $lst[] = ['dir' => $instance->get('dir'), 'name' => $instance->get('name')];
        }
    }
    return $lst;
}
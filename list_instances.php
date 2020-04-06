<?php
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

if ($adminMode) {
    require_login();
    $sitecontext = context_system::instance();
    require_capability('moodle/site:config', $sitecontext);
}

$OUTPUT->header();

$lst = [];
$instances = \auth_entsync\farm\instance::get_records([], 'name');
foreach ($instances as $instance) {
    if (($adminMode) || ($instance->get('rne') !== '00')) {
        $lst[] = ['dir' => $instance->get('dir'), 'name' => $instance->get('name')];
    }
}
echo json_encode($lst);

function auth_entsync_error($code) {
    http_response_code($code);
    die();
}

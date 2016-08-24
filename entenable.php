<?php

/**
 * Allows admin to edit all auth plugin settings.
 *
 * JH: copied and Hax0rd from admin/enrol.php and admin/filters.php
 *
 */

require(__DIR__ . '/../../config.php');

require_once('ent_defs.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$returnurl = new moodle_url("$CFG->wwwroot/auth/entsync/param.php");

$PAGE->set_url($returnurl);

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$entcode   = optional_param('ent', 0, PARAM_INT);

if(!confirm_sesskey()) {
    redirect($returnurl);
}

////////////////////////////////////////////////////////////////////////////////
// process actions

if($entsenabled = get_config('auth_entsync', 'enabledents')) {
    $entsenabled = explode(';', $entsenabled);
} else {
    $entsenabled = [];
}

switch ($action) {
    case 'disable':
        // remove from enabled list
        auth_entsync_ent_base::disable_ent($entcode);
        break;

    case 'enable':
        // add to enabled list
        auth_entsync_ent_base::enable_ent($entcode);
        break;

    default:
        break;
}

redirect($returnurl);



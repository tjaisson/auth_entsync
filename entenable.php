<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Pour activer ou déactiver les ENT.
 */

require(__DIR__ . '/../../config.php');

require_once('ent_defs.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$returnurl = new moodle_url("$CFG->wwwroot/auth/entsync/param.php");

$PAGE->set_url($returnurl);

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$entcode = optional_param('ent', 0, PARAM_INT);

if (!confirm_sesskey()) {
    redirect($returnurl);
}

// Process actions.

if ($entsenabled = get_config('auth_entsync', 'enabledents')) {
    $entsenabled = explode(';', $entsenabled);
} else {
    $entsenabled = [];
}

switch ($action) {
    case 'disable':
        // Remove from enabled list.
        auth_entsync_ent_base::disable_ent($entcode);
        break;

    case 'enable':
        // Add to enabled list.
        auth_entsync_ent_base::enable_ent($entcode);
        break;

    default:
        break;
}

redirect($returnurl);

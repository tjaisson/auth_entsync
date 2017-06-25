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
 * Connexion sso depuis le site maitre
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/user/lib.php');

use \auth_entsync\persistents\instance;

if (isloggedin() || instance::is_gw()) {
    redirect($CFG->wwwroot);
}

$ticket = optional_param('ticket', null, PARAM_RAW);

if (!$ticket) {
   // Pas de ticket.
    $jumpurl = new moodle_url(instance::gwroot() . '/auth/entsync/jump.php', ['inst' => instance::inst()]);
    redirect($jumpurl);
    die();
}

// Le ticket est présent.
$cu = new curl();
$valurl = new moodle_url(instance::gwroot() . '/auth/entsync/validate.php',
    ['inst' => instance::inst(), 'ticket' => $ticket]);
if (!($rep = $cu->get($valurl->out(false)))) {
    die();
}
if (!($rep === 'validate')) {
        die();
}

$mdlu = $DB->get_record('user', ['username' => 'pam.central.adm', 'auth' => 'entsync']);
$_mdlu = new stdClass();
$_mdlu->isdirty = false;
if (!$mdlu) {
    // L'utilisateur admin central n'existe pas.
    $_mdlu->isdirty = true;
    $_mdlu->mnethostid = $CFG->mnet_localhost_id;
    $_mdlu->username = 'pam.central.adm';
    $_mdlu->password = md5("entsync\\noPW");
    $_mdlu->auth = 'entsync';
    $_mdlu->confirmed = 1;
    $_mdlu->firstname = '-';
    $_mdlu->lastname = 'Administrateur Central PAM';
    $_mdlu->email = 'ad@ac.invalid';
    $_mdlu->emailstop = 1;
    $_mdlu->lang = 'fr';
    unset($_mdlu->isdirty);
    $_mdlu->id = user_create_user($_mdlu, false, true);
    $mdlu = $DB->get_record('user', ['id' => $_mdlu->id]);
} else {
    $_mdlu->id = $mdlu->id;
    if ($mdlu->suspended) {
        $_mdlu->suspended = 0;
        $_mdlu->isdirty = true;
    }
    if (!$mdlu->confirmed) {
        $_mdlu->confirmed = 1;
        $_mdlu->isdirty = true;
    }
    if ($_mdlu->isdirty) {
        unset($_mdlu->isdirty);
        user_update_user($_mdlu, false, true);
    }
}

set_user_preference('auth_forcepasswordchange', false, $mdlu->id);

if (!is_siteadmin($mdlu)) {
    $mdluid = (int)$mdlu->id;
    $admins = array();
    foreach (explode(',', $CFG->siteadmins) as $admin) {
        $admin = (int)$admin;
        if ($admin) {
            $admins[$admin] = $admin;
        }
    }
    $admins[$mdluid] = $mdluid;
    set_config('siteadmins', implode(',', $admins));
}

complete_user_login($mdlu);

\core\session\manager::apply_concurrent_login_limit($mdlu->id, session_id());

redirect($CFG->wwwroot);

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
 * Connexion sso
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require(__DIR__ . '/../../config.php');
$entsync = \auth_entsync\container::services();

// Try to prevent searching for sites that allow sign-up.
if (!isset($CFG->additionalhtmlhead)) {
    $CFG->additionalhtmlhead = '';
}
$CFG->additionalhtmlhead .= '<meta name="robots" content="noindex" />';
redirect_if_major_upgrade_required();

$page_url = new moodle_url('/auth/entsync/login.php');

$entclass = required_param('ent', PARAM_ALPHANUMEXT);
require_once('ent_defs.php');
if ((!$ent = auth_entsync_ent_base::get_ent($entclass)) ||
    (!$ent->is_sso()) ||
    (!$ent->is_enabled()) ||
    ('cas' !== $ent->get_mode())) entsync_print_error('userautherror');

    //TODO : voir si login/lib est nécessaire
    // voir à faire les autres require depuis les services qui les utilisent
require_once($CFG->dirroot.'/login/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');

/** @var \auth_entsync\conf $conf */
$conf = $entsync->query('conf');
$scope = $conf->inst() . ':' . $ent->get_entclass();
/** @var \auth_entsync\farm\iic $iic */
$iic = $entsync->query('iic');
$ui = optional_param('user', null, PARAM_ALPHANUMEXT);
if (!empty($ui)) {
    if ((count($_POST) + count($_GET)) !== 2) entsync_print_error('userautherror');
    //$page_param['user'] = $userdata;
    if (false === ($ui = $iic->open($ui, $scope))) entsync_print_error('expiredkey');
    $ui = json_decode($ui);
} else {
    if (!$cas = $ent->get_casconnector()) entsync_print_error('userautherror');
    $clienturl = new moodle_url($page_url, ['ent' => $entclass]);
    $cas->set_clienturl($clienturl);
    if (!($ui = $cas->validateorredirect())) entsync_print_error('userautherror');
}
// Here we have a $userdata.
/** @var \auth_entsync\directory\entus $entus */
$entus = $entsync->query('directory.entus');
list($entu, $mdlu) = $entus->find_update_by_id($ui, $ent);
if (null === $mdlu) {
    if (! $entus->is_auto_creatable($ui)) entsync_print_deny_error($ui, $ent);
    list($entu, $mdlu) = $entus->find_update_by_names_and_profile($entu, $ui, $ent);
    if (null === $mdlu) {
        list($entu, $mdlu) = $entus->create($entu, $ui, $ent);
    }
}
if (null === $mdlu) entsync_print_deny_error($ui, $ent);
$mdlu = get_complete_user_data('id', $mdlu->id);
if (!$mdlu) entsync_print_deny_error($ui, $ent);
// Here we have a $mdlu.
if (isloggedin()) {
    if ($USER->id === $mdlu->id) {
        // User is already logged in.
        redirect($CFG->wwwroot.'/');
    } else {
        entsync_require_logout();
        $k = $iic->getCrkey();
        $ui = json_encode($ui, JSON_UNESCAPED_UNICODE);
        $ui = $k->seal($ui, $scope);
        redirect(new moodle_url($page_url, ['ent' => $entclass, 'user' => $ui]));
    }
}
// Here we have to log $mdlu in.
if (! empty($mdlu->suspended)) entsync_print_deny_error($ui, $ent);
set_user_preference('auth_forcepasswordchange', false, $mdlu->id);
complete_user_login($mdlu);
\core\session\manager::apply_concurrent_login_limit($mdlu->id, session_id());
// Ajouter dans $USER que c'est un sso et quel est l'ent. Au log out, rediriger vers l'ent.
$USER->entsync = (object)[
    'code' => $ent->get_code(),
    'class' => $ent->get_entclass(),
    'login' => $entu->uid,
    'profile' => $entu->profile
];
$urltogo = core_login_get_return_url();
if (strstr($urltogo, 'entsync')) unset($SESSION->wantsurl);
else $SESSION->wantsurl = $urltogo;
// Discard any errors before the last redirect.
unset($SESSION->loginerrormsg);
// Test the session actually works by redirecting to self.
redirect(new moodle_url(get_login_url(), ['testsession' => $mdlu->id]));

function entsync_print_error($errorcode, $module = 'error', $link = '', $a = null, $debuginfo = null) {
    entsync_require_logout();
    print_error($errorcode, $module, $link, $a, $debuginfo);
}

function entsync_print_deny_error($ui, $ent) {
    entsync_print_error('notauthorized',
        'auth_entsync',
        '',
        (object)['ent' => $ent->nomcourt, 'user' => $ui->user]);
}

function entsync_require_logout() {
    global $USER;
    if (isset($USER) && (isset($USER->entsync))) unset($USER->entsync);
    require_logout();
}

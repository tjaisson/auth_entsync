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
use \auth_entsync\farm\instance;
use \auth_entsync\farm\iic;


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
    (!$ent->is_enabled())) print_error('userautherror');
    $page_param = ['ent' => $ent->get_entclass()];
require_once(__DIR__ . '/../../login/lib.php');
$userdata = optional_param('user', null, PARAM_ALPHANUMEXT);
if (!empty($userdata)) {
    if ((count($_POST) + count($_GET)) !== 2) print_error('userautherror');
    $page_param['user'] = $userdata;
    $scope = instance::inst() . ':' . $ent->get_entclass();
    if (false === ($userdata = iic::open($userdata, $scope)))
        print_error('expiredkey');
    $val = unserialize($userdata);
    $entu = auth_entsync_findEntu($val);
    if (!$entu) {
        auth_entsync_clearSession(false);
        print_error('notauthorized',
            'auth_entsync',
            '',
            (object)['ent' => $ent->nomcourt, 'user' => $val->user]);
    }
    if (isloggedin()) {
        if ($USER->id === $entu->userid) {
            redirect($CFG->wwwroot.'/');
        } else {
            auth_entsync_clearSession();
        }
    }
    auth_entsync_tryLogin($entu);
} else {
    if (isloggedin()) {
        auth_entsync_clearSession();
    }
    if (('cas' !== $ent->get_mode()) ||
        (!$cas = $ent->get_casconnector())) print_error('userautherror');
    $clienturl = new moodle_url($page_url, ['ent' => $entclass]);
    $cas->set_clienturl($clienturl);
    if ($val = $cas->validateorredirect()) {
        $entu = auth_entsync_findEntu($val, $ent);
        if (!$entu) {
            print_error('notauthorized',
                'auth_entsync',
                '',
                (object)['ent' => $ent->nomcourt, 'user' => $val->user]);
        }
        auth_entsync_tryLogin($entu, $ent);
    } else {
        print_error('userautherror');
    }
}
function auth_entsync_tryLogin($entu) {
    global $USER, $SESSION, $ent;
    if ($entu->archived) print_error('userautherror');
    if (!$mdlu = get_complete_user_data('id', $entu->userid)) print_error('userautherror');
    if ($mdlu->suspended) print_error('userautherror');
    set_user_preference('auth_forcepasswordchange', false, $mdlu->id);
    complete_user_login($mdlu);
    \core\session\manager::apply_concurrent_login_limit($mdlu->id, session_id());
    // Ajouter dans $USER que c'est un sso et quel est l'ent. Au log out, rediriger vers l'ent.
    $USER->entsync = $ent->get_code();
    $urltogo = core_login_get_return_url();
    if (strstr($urltogo, 'entsync')) unset($SESSION->wantsurl);
    else $SESSION->wantsurl = $urltogo;
    // Discard any errors before the last redirect.
    unset($SESSION->loginerrormsg);
    // Test the session actually works by redirecting to self.
    redirect(new moodle_url(get_login_url(), ['testsession' => $mdlu->id]));
}
function auth_entsync_findEntu($val) {
    global $DB, $ent;
    if ($val->uid) {
        if (!$entu = $DB->get_record('auth_entsync_user',
            ['uid' => $val->uid, 'ent' => $ent->get_code()])) {
                if ($entu = $DB->get_record('auth_entsync_user',
                    ['uid' => $val->user, 'ent' => $ent->get_code()])) {
                        $entu->uid = $val->uid;
                        $entu->checked = 1;
                        //$DB->update_record('auth_entsync_user', $entu);
                    }
            }
    } else {
        $entu = $DB->get_record('auth_entsync_user',
            ['uid' => $val->user, 'ent' => $ent->get_code()]);
    }
    return $entu;
}
function auth_entsync_clearSession($redir = true) {
    global $USER, $page_url, $page_param;
    if (isset($USER) && (isset($USER->entsync))) unset($USER->entsync);
    require_logout();
    if ($redir) {
        redirect(new moodle_url($page_url, $page_param));
    }
}

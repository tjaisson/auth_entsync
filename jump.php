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
 * Gestion des instances.
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
require(__DIR__ . '/../../config.php');
$entsync = \auth_entsync\container::services();
/** @var \auth_entsync\conf $conf */
$conf = $entsync->query('conf');
/** @var \auth_entsync\Security\TokenServiceInterface $tk_srv */
$tk_srv = $entsync->query('token');
$tk_sub = 'central.adm.switch';
/** @var \auth_entsync\farm\instances $instances */
$instances = $entsync->query('instances');
$inst = optional_param('inst', null, PARAM_TEXT);
if (!empty($inst)) {
    if (!$conf->is_gw()) die();
    if ((!isloggedin()) || (!has_capability('moodle/site:configview', context_system::instance()))) die();
    $instance = $instances->get_instance(['dir' => $inst]);
    if (!$instance) {
        die();
    }
    $dir = $instance->get('dir');
    $builder = $tk_srv->createBuilder();
    $builder->withData('jump')->withSubject($tk_sub)
    ->withTTL(5)->withNonce();
    $tk = $builder->toString();
    $redirecturl = new moodle_url($conf->pamroot() . '/' . $dir . '/auth/entsync/jump.php', ['tk' => $tk]);
    redirect($redirecturl);
}
$tk = optional_param('tk', null, PARAM_TEXT);
if (empty($tk)) die();
if ($conf->is_gw()) redirect($CFG->wwwroot);

if (isloggedin()) {
    require_logout();
    $redirecturl = new moodle_url('/auth/entsync/jump.php', ['tk' => $tk]);
    redirect($redirecturl);
}
$validator = $tk_srv->createValidator();
$validator->withToken($tk)->withSubject($tk_sub)->withNonce();
if ('jump' !== $validator->getData()) die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/user/lib.php');
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
$mdlu = $DB->get_record('user', ['id' => $_mdlu->id]);
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
redirect($CFG->wwwroot . '/');

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
require_once(__DIR__ . '/../../login/lib.php');
require_once('ent_defs.php');


// Try to prevent searching for sites that allow sign-up.
if (!isset($CFG->additionalhtmlhead)) {
    $CFG->additionalhtmlhead = '';
}
$CFG->additionalhtmlhead .= '<meta name="robots" content="noindex" />';

redirect_if_major_upgrade_required();

// HTTPS is required in this page when $CFG->loginhttps enabled.
$PAGE->https_required();

$context = context_system::instance();
$PAGE->set_url("$CFG->httpswwwroot/auth/entsync/login.php");
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');


$entclass = optional_param('ent', '', PARAM_RAW);

if (empty($entclass)) {
    printerrorpage('Erreur');
}

if (!$ent = auth_entsync_ent_base::get_ent($entclass)) {
    // Le code ne correspond pas à un ent, display erreur et redirect button.
    printerrorpage('Erreur');
}

if (!$ent->is_sso()) {
    // Si ce n'est pas sso, l'authentification ne passe pas par là.
    printerrorpage('Erreur');
}

// On doit être en authentification cas.

if (!$ent->is_enabled()) {
    // Le code ne correspond pas à un ent activé, display erreur et redirect button.
    printerrorpage('Erreur');
}

if ($ent->get_mode() !== 'cas') {
    // On ne gère que cas pout l'instant, display erreur et redirect button.
    printerrorpage('Erreur');
}

if (!$cas = $ent->get_casconnector()) {
    printerrorpage("Le connecteur {$ent->nomlong} n'est pas configuré.");
}
$clienturl = new moodle_url("$CFG->httpswwwroot/auth/entsync/login.php", ['ent' => $entclass]);
$cas->set_clienturl($clienturl);

if ($val = $cas->validateorredirect()) {
    if (!$entu = $DB->get_record('auth_entsync_user',
        ['uid' => $val->user, 'ent' => $ent->get_code()])) {
            // Utilisateur cas non connu, display erreur et redirect button
            // informer l'Utilisateur de son uid ent.
            $a = new stdClass();
            $a->ent = $ent->nomcourt;
            $a->user = $val->user;
            $msg = get_string('notauthorized', 'auth_entsync', $a);
            printerrorpage($msg);
    }
    if ($entu->archived) {
        printerrorpage('Utilisateur désactivé');
    }
    if (!$mdlu = get_complete_user_data('id', $entu->userid)) {
        // Ne devrait pas se produire, display erreur et redirect button.
        printerrorpage('Utilisateur inconnu !');
    }
    if ($mdlu->suspended) {
        // Utilisateur suspendu, display erreur et redirect button
        // TODO : informer l'Utilisateur.
        printerrorpage('Utilisateur inconnu !');
    }
    set_user_preference('auth_forcepasswordchange', false, $mdlu->id);
    complete_user_login($mdlu);

    \core\session\manager::apply_concurrent_login_limit($mdlu->id, session_id());

    // Ajouter dans $USER que c'est un sso et quel est l'ent. Au log out, rediriger vers l'ent.
    $USER->entsync = $ent->get_code();
    $urltogo = core_login_get_return_url();
    if (strstr($urltogo, 'entsync')) {
        unset($SESSION->wantsurl);
    } else {
        $SESSION->wantsurl = $urltogo;
    }

    // Discard any errors before the last redirect.
    unset($SESSION->loginerrormsg);

    // Test the session actually works by redirecting to self.
    redirect(new moodle_url(get_login_url(), array('testsession' => $mdlu->id)));
} else {
    // Display erreur et redirect button.
    printerrorpage('Ticket CAS non validé');
}

function printerrorpage($msg, $type = \core\output\notification::NOTIFY_ERROR, $url = null) {
    global $OUTPUT, $CFG;
    $url = is_null($var) ? $CFG->wwwroot : $url;
    echo $OUTPUT->header();
    echo $OUTPUT->notification($msg, $type);
    echo $OUTPUT->continue_button($url);
    echo $OUTPUT->footer();
    die();
}
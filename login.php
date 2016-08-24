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
 * Anobody can login with any password.
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../login/lib.php');

//require_once($CFG->libdir.'/adminlib.php');
//require_once($CFG->libdir.'/moodlelib.php');
require_once('ent_defs.php');

// Try to prevent searching for sites that allow sign-up.
if (!isset($CFG->additionalhtmlhead)) {
    $CFG->additionalhtmlhead = '';
}
$CFG->additionalhtmlhead .= '<meta name="robots" content="noindex" />';

redirect_if_major_upgrade_required();

//HTTPS is required in this page when $CFG->loginhttps enabled
$PAGE->https_required();

$context = context_system::instance();
$PAGE->set_url("$CFG->httpswwwroot/auth/entsync/login.php");
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');


$entclass = optional_param('ent', '', PARAM_RAW);

if(empty($entclass))
    printerrorpage('Erreur', \core\output\notification::NOTIFY_ERROR);

if(!$ent = auth_entsync_ent_base::get_ent($entclass)) {
    //le code ne correspond pas à un ent, display erreur et redirect button
    printerrorpage('Erreur', \core\output\notification::NOTIFY_ERROR);
}

if(!$ent->is_sso()) {
    //si ce nn'est pas sso, l'authentification ne passe pas par là
    printerrorpage('Erreur', \core\output\notification::NOTIFY_ERROR);
}
    
//on doit être en authentification cas

if(!$ent->is_enabled()) {
    //le code ne correspond pas à un ent activé, display erreur et redirect button
    printerrorpage('Erreur', \core\output\notification::NOTIFY_ERROR);
}

if($ent->get_mode() !== 'cas') {
    //On ne gère que cas pout l'instant, display erreur et redirect button
    printerrorpage('Erreur', \core\output\notification::NOTIFY_ERROR);
}

$cas = $ent->get_casconnector();


if($val = $cas->validateorredirect()) {
    if(!$entu = $DB->get_record('auth_entsync_user',
        ['uid' => $val->user, 'ent' => $ent->get_code()])) {
            //Utilisateur cas non connu, display erreur et redirect button
            //TODO : informer l'Utilisateur de son uid ent
            printerrorpage('Utilisateur inconnu !', \core\output\notification::NOTIFY_ERROR);
        }
        if($entu->archived) {
            printerrorpage('Utilisateur inconnu !', \core\output\notification::NOTIFY_ERROR);
        }
        if(!$mdlu = get_complete_user_data('id', $entu->userid))  {
            //Ne devrait pas se produire, display erreur et redirect button
            printerrorpage('Utilisateur inconnu !', \core\output\notification::NOTIFY_ERROR);
        }
        if($mdlu->suspended) {
            //Utilisateur suspendu, display erreur et redirect button
            //TODO : informer l'Utilisateur
            printerrorpage('Utilisateur inconnu !', \core\output\notification::NOTIFY_ERROR);
        }
        set_user_preference('auth_forcepasswordchange', false, $mdlu->id);
        complete_user_login($mdlu);

        \core\session\manager::apply_concurrent_login_limit($mdlu->id, session_id());

        $urltogo = core_login_get_return_url();

        // Discard any errors before the last redirect.
        unset($SESSION->loginerrormsg);

        // test the session actually works by redirecting to self
        $SESSION->wantsurl = $urltogo;
        redirect(new moodle_url(get_login_url(), array('testsession'=>$mdlu->id)));
} else {
    //display erreur et redirect button
    printerrorpage('Ticket CAS non validé', \core\output\notification::NOTIFY_ERROR);
}




    
function printerrorpage($msg, $type, $url = '/') {
    global $OUTPUT, $PAGE;
    echo $OUTPUT->header();
    echo $OUTPUT->notification($msg, $type);
    echo $OUTPUT->continue_button($url);
    echo $OUTPUT->footer();
    die();
}
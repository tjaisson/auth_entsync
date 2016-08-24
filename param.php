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
 * Gestion des utilisateurs de l'établissement
 *
 * Affiche le formulaire de configuration du plugin
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once('ent_defs.php');
require_once(__DIR__ . '/lib/rolehelper.php');
require_once('param_form.php');

require_login();
admin_externalpage_setup('authentsyncparam');
require_capability('moodle/site:config', context_system::instance());

$errorstr                   = get_string('error');

$stryes                     = get_string('yes');
$strno                      = get_string('no');
$stryesnooptions = array(0=>$strno, 1=>$stryes);

$returnurl = new moodle_url('/auth/entsync/param.php');

if(!is_enabled_auth('entsync')) {
    // le plugin d'authentification doit être activé
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string('entsyncparam', 'auth_entsync'), 'entsyncparam', 'auth_entsync');
    echo $OUTPUT->notification(get_string('pluginnotenabledwarn', 'auth_entsync',
        "$CFG->wwwroot/$CFG->admin/settings.php?section=manageauths"));
    echo $OUTPUT->footer();
    die;
}

$mform = new admin_entparam_form();
$config = get_config('auth/entsync');

if((!isset($config->code_ent)) ||
        (!$ent = tool_entsync_ent_base::get_ent($config->code_ent))) {
    $config->code_ent = 0;
    unset($ent);
}

if(!isset($config->role_ens)) $config->role_ens = 0;
$mform->set_data(['code_ent' => $config->code_ent, 'role_ens' => $config->role_ens]);

if ($formdata = $mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    //application des paramètres	
	//TODO : adapter au multi profile
	if($formdata->role_ens != $config->role_ens) {
	    if ($formdata->role_ens == 0) {
            unset_config('role_ens', 'auth/entsync');
            auth_entsync_rolehelper::removerolesallusers(2);
        } else {
            set_config('role_ens', $formdata->role_ens, 'auth/entsync');
            auth_entsync_rolehelper::updateroleallusers(2, $formdata->role_ens);
        }
    }
	redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('entsyncparam', 'auth_entsync'), 'entsyncparam', 'auth_entsync');
//liste des ents enable/disable
$mform->display();
echo $OUTPUT->footer();
die;
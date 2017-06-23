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

use \auth_entsync\config\entparam_form;

require_login();
admin_externalpage_setup('authentsyncparam');
require_capability('moodle/site:config', context_system::instance());

$returnurl = new moodle_url('/auth/entsync/param.php');

if (!is_enabled_auth('entsync')) {
    // Le plugin d'authentification doit être activé.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('entsyncparam', 'auth_entsync'));
    echo $OUTPUT->notification(get_string('entsyncparam_help', 'auth_entsync'),
        core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->notification(get_string('pluginnotenabledwarn', 'auth_entsync',
        "$CFG->wwwroot/$CFG->admin/settings.php?section=manageauths"), core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    die;
}

$mform = new entparam_form();
$config = get_config('auth_entsync');

if (!isset($config->role_ens)) {
    $config->role_ens = 0;
}
$mform->set_data(['role_ens' => $config->role_ens]);

auth_entsync_ent_base::set_formdata($config, $mform);


if ($formdata = $mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    // Application des paramètres.
    if ($formdata->role_ens != $config->role_ens) {
        if ($formdata->role_ens == 0) {
            unset_config('role_ens', 'auth_entsync');
            auth_entsync_rolehelper::removerolesallusers(2);
        } else {
            set_config('role_ens', $formdata->role_ens, 'auth_entsync');
            auth_entsync_rolehelper::updateroleallusers(2, $formdata->role_ens);
        }
    }
    auth_entsync_ent_base::save_formdata($config, $formdata);
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('entsyncparam', 'auth_entsync'), 'entsyncparam', 'auth_entsync');
// Liste des ents enable/disable.
$mform->display();
echo $OUTPUT->footer();
die;

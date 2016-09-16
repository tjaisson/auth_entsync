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
 * Gestion des utilisateurs.
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once $CFG->libdir.'/formslib.php';
require_once(__DIR__ . '/lib/table.php');
require_once(__DIR__ . '/lib/cohorthelper.php');
require_once('ent_defs.php');

require_login();
admin_externalpage_setup('authentsyncuser');
require_capability('moodle/user:viewdetails', context_system::instance());

$profile = optional_param('profile', -1, PARAM_INT);
$cohort =  optional_param('cohort', -1, PARAM_INT);



class user_select_form extends moodleform {
    function definition () {
        $mform = $this->_form;
        $mform->addElement('header', 'eleves', 'Eleves');
        $cllst = auth_entsync_cohorthelper::get_cohorts();
        $mform->addElement('select', 'cohort', 'classe', $cllst);
        $mform->setType('cohort', PARAM_INT);
        $mform->addElement('submit', 'dispelev', 'Afficher');
        
        $mform->addElement('header', 'profs', 'Profs');
        $mform->addElement('submit', 'dispprof', 'Afficher');
    }
}


$form = new user_select_form();

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('entsyncuser', 'auth_entsync'), 'entsyncuser', 'auth_entsync');
$form->display();

$t = new html_table();
$t->head = [get_string('firstname'), get_string('lastname'), get_string('username'), get_string('password')];

$lst = auth_entsync_usertbl::get_users_ent_elev(3);
foreach($lst as $u) {
    if($u->local === '0') { 
        $u->username = '';
        $u->password = '';
    } else {
        if(!isset($u->password)) $u->password = '&bull;&bull;&bull;&bull;&bull;';
    }
    $t->data[] = new html_table_row([$u->firstname, $u->lastname, $u->username, $u->password]);
}


echo html_writer::table($t);

//echo "<pre>$ret</pre>";
echo $OUTPUT->footer();



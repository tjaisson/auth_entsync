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

class user_select_form extends moodleform {
    function definition () {
        $mform = $this->_form;
        $mform->addElement('header', 'filter', get_string('choose'));
        $this->_form->setExpanded('filter');
        $cllst = auth_entsync_cohorthelper::get_cohorts();
        $grp=array();
        $grp[] = $mform->createElement('select', 'cohort', 'classe', $cllst);
        $grp[] = $mform->createElement('submit', 'dispelev', '> '. get_string('show'));
        $mform->addGroup($grp, 'elev', 'Elèves de', array(' '), false);
        $mform->setType('cohort', PARAM_INT);
        
        $grp=array();
        $grp[] = $mform->createElement('submit', 'dispprof', '> '. get_string('show'));
        $grp[] = $mform->createElement('html', '');
        $mform->addGroup($grp, 'ens', 'Enseignants', array(' '), false);
    }
    
    function collapse(){
        $this->_form->setExpanded('filter', false, true);
    }
}


$form = new user_select_form();

if ($formdata = $form->get_data()) {
    if(isset($formdata->dispelev)) {
        $profile = 1;
        $cohort = $formdata->cohort;
    } else if(isset($formdata->dispprof)) {
        $profile = 2;
    }
} else {
    $resetpw  = optional_param('resetpw', 0, PARAM_INT);
    $confirm  = optional_param('confirm', '', PARAM_ALPHANUM);   //md5 confirmation hash
    $profile  = optional_param('profile', 0, PARAM_INT);
    $cohort   = optional_param('cohort', 0, PARAM_INT);
}

$returnurl = new moodle_url('/auth/entsync/user.php', ['profile' => $profile, 'cohort' => $cohort]);



$lst = null;
$cbquery = '&amp;cb=users&amp;profile=';

if(($profile === 1) && ($cohort > 0)) {
    $lst = auth_entsync_usertbl::get_users_ent_elev($cohortid);
    $cohort = auth_entsync_cohorthelper::get_cohorts()[$cohortid];
    $ttl = "Elèves de {$cohort} :";
    $cbquery .= "1&amp;cohort={$cohort}";
} else if ($profile === 2) {
    $lst = auth_entsync_usertbl::get_users_ent_ens();
    $ttl = "Enseignants :";
    $cbquery .= '2';
}

if($lst) {
    $t = new html_table();
    $t->head = [get_string('firstname'), get_string('lastname')];
//icon
    $reset = $OUTPUT->pix_icon('t/reset', get_string('reset'));
    $approve = $OUTPUT->pix_icon('t/approve', get_string('yes'));
    $block = $OUTPUT->pix_icon('t/block', get_string('no'));
    
    
    $entheads = array();
    $entfields = array();
    $haslocalent = false;
    foreach (auth_entsync_ent_base::get_ents() as $entcode =>$ent) {
        if($ent->is_enabled()) {
            if($ent->get_mode() === 'local') $haslocalent = true;
            $entheads[] = $ent->nomcourt;
            $entfields[] = "ent{$entcode}";
        }
    }
    if($haslocalent) {
        $t->head[] = get_string('username');
        $t->head[] = get_string('password');
    }
    foreach($entheads as $entname) {
        $t->head[] = $entname;
    }

    $reseturl = 'resetpw.php?sesskey=' . sesskey() . $cbquery;

    foreach($lst as $u) {
        if($u->local === '0') { 
            $u->username = '-';
            $u->password = '-';
        } else {
            if(!isset($u->password)) $u->password =
            "&bull;&bull;&bull;&bull;&bull;
            <a href = \"{$reseturl}&amp;resetpw={$u->id}\">
            {$reset}</a>";
        }
        $row = [$u->firstname, $u->lastname];
        if($haslocalent) {
            $row[] = $u->username;
            $row[] = $u->password;
        }
        foreach($entfields as $field) {
            if($u->{$field} === '1') {
                $row[] = $approve;
            } else {
                $row[] = $block;
            }
        }
        $t->data[] = new html_table_row($row);
        $form->collapse();
    }
}
echo $OUTPUT->header();

$form->display();
// echo "<pre>hello</pre>";
if($lst) {
    echo $OUTPUT->heading($ttl);
    echo html_writer::table($t);
}
echo $OUTPUT->footer();



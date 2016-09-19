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
$sitecontext = context_system::instance();
require_capability('moodle/user:viewdetails', $sitecontext);

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

$returnurl = new moodle_url('/auth/entsync/users.php');

$resetpw  = optional_param('resetpw', 0, PARAM_INT);
$confirm  = optional_param('confirm', '', PARAM_ALPHANUM);   //md5 confirmation hash
$profile  = optional_param('profile', 0, PARAM_INT);
$cohort   = optional_param('cohort', 0, PARAM_INT);

//choix de la liste par les params de requete
if($profile === 1) {
    if($cohort <= 0) {
        unset($profile);
        unset($cohort);
    } else {
        $returnurl->params(['profile' => $profile, 'cohort' => $cohort]);
    }
} else if ($profile === 2) {
    unset($cohort);
    $returnurl->params(['profile' => $profile]);
} else {
    unset($profile);
    unset($cohort);
}

//y a t il des actions à faire
if($resetpw and confirm_sesskey()) {
    require_capability('moodle/user:update', $sitecontext);
    $u = auth_entsync_usertbl::get_user_ent($resetpw);
    if($u->local === '0') {
        redirect($returnurl);
    }
    if ($confirm != md5($resetpw)) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading('Réinitialiser le mote de passe');
    
        $optionsyes = array('resetpw'=>$resetpw, 'confirm'=>md5($resetpw), 'sesskey'=>sesskey());
        $reseturl = new moodle_url($returnurl, $optionsyes);
        $resetbutton = new single_button($reseturl, get_string('reset'), 'post');
    
        echo $OUTPUT->confirm("Etes vous sur de vouloir réinitialiser le mot de passe de {$u->firstname} {$u->lastname} ?", $resetbutton, $returnurl);
        echo $OUTPUT->footer();
        die;
    } else if (!empty($_POST)) {
        require_once(__DIR__ . '/lib/locallib.php');
        $_mdlu = new stdClass();
        $_mdlu->id = $resetpw;
        $pw = auth_entsync_stringhelper::rnd_string();
        $_mdlu->password = "entsync\\{$pw}";
        user_update_user($_mdlu, false, true);
        redirect($returnurl);
    }
    redirect($returnurl);
} else {
    $form = new user_select_form();
    
    if ($formdata = $form->get_data()) {
        if(isset($formdata->dispelev) && ($formdata->cohort > 0)) {
            $profile = 1;
            $cohort = $formdata->cohort;
            $returnurl->params(['profile' => $profile, 'cohort' => $cohort]);
        } else if(isset($formdata->dispprof)) {
            $profile = 2;
            $returnurl->params(['profile' => $profile]);
        }
    }
    
    $lst = null;
    
    
    if(isset($profile)) {
        if(($profile === 1) && ($cohort > 0)) {
            $lst = auth_entsync_usertbl::get_users_ent_elev($cohort);
            $cohortname = auth_entsync_cohorthelper::get_cohorts()[$cohort];
            $ttl = "Elèves de {$cohortname} :";
        } else if ($profile === 2) {
            $lst = auth_entsync_usertbl::get_users_ent_ens();
            $ttl = "Enseignants :";
        }

        $t = new html_table();
    //icon
        $resetico = $OUTPUT->pix_icon('t/reset', get_string('reset'));
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
        $selbutts = '<a href="javascript:selAll()">tous</a><br /><a href="javascript:selNone()">aucun</a>';
        $t->head = [$selbutts, get_string('firstname'), get_string('lastname'), get_string('username'), get_string('password')];
        } else {
            $t->head = [get_string('firstname'), get_string('lastname')];
        }
        foreach($entheads as $entname) {
            $t->head[] = $entname;
        }
    
    
        foreach($lst as $u) {
            $preselect = '';
            if($u->local === '0') { 
                $u->username = '-';
                $u->password = '-';
            } else {
                $reseturl = $returnurl->out(true, ['sesskey' => sesskey(), 'resetpw' => $u->id]);
                if(isset($u->password)) {
                    $preselect = ' checked';
                } else {
                    $u->password =
                        "&bull;&bull;&bull;&bull;&bull;
                        <a href = \"{$reseturl}\">
                        {$resetico}</a>";
                }
            }
            if($haslocalent) {
                $cb = "<input type=\"checkbox\" name=\"select[]\" value=\"{$u->id}\"{$preselect}></input>";
                $row = [$cb, $u->firstname, $u->lastname, $u->username, $u->password];
            } else {
                $row = [$u->firstname, $u->lastname];
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
    if($lst) {
        echo $OUTPUT->heading($ttl);
        if($haslocalent) { ?>
            <form method="post" action="etiqu.php" id="form1" target="_blank">
            <input type="submit" value="Imprimer" /> des étiquettes pour les utilisateurs sélectionnés
            <input type="hidden" name="profile" value="<?php echo $profile; ?>" />
        <?php
            if($profile === 1 ) { ?>
            <input type="hidden" name="cohort" value="<?php echo $cohort; ?>" />
            <?php 
            }
        }
        echo '<div id="listDiv">';
        echo html_writer::table($t);
        echo '</div>';
        if($haslocalent) { ?>
        	</form>
            <script type="text/javascript">
            function selAll()
            {
                t = document.getElementById("listDiv");
                l = t.getElementsByTagName("input");
                for(i = 0;i < l.length; i++)
                {
                    l[i].checked = true;
                }
            }
            function selNone()
            {
                t = document.getElementById("listDiv");
                l = t.getElementsByTagName("input");
                for (i = 0; i < l.length; i++) {
                    l[i].checked = false;
                }
            }
        </script>
        <?php
        }
    }
    echo $OUTPUT->footer();
}

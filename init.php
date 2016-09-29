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
//require_once(__DIR__ . '/lib/table.php');
//require_once('ent_defs.php');

require_login();
admin_externalpage_setup('authentsyncparam');
require_capability('moodle/site:config', context_system::instance());

$deverrou = optional_param('do', 'no', PARAM_TEXT);
if($deverrou !== 'init') {
    redirect(new moodle_url('/'));
}

//cherche les élèments déjà initialisés.
$data = new stdClass();
//rôles
$data->catrole = $DB->get_field('role', 'id', ['shortname' => 'catcreator']);
$data->ownerrole = $DB->get_field('role', 'id', ['shortname' => 'courseowner']);

$data->restorersnewrole = $CFG->restorernewroleid;
$data->creatornewroles = $CFG->creatornewroleid;

//theme
$data->currentthemename = core_useragent::get_device_type_theme('default');
if (!$data->currentthemename) {
    $data->currentthemename = theme_config::DEFAULT_THEME;
}
$themes = core_component::get_plugin_list('theme');
if(array_key_exists('acparis', $themes)) {
    $data->acparistheme = 'acparis';
} else {
    $data->acparistheme = false;
}

class init_form extends moodleform {
    function definition () {
        global $OUTPUT;
        $validico = $OUTPUT->pix_icon('i/valid', 'OK').' ';
        $warningico = $OUTPUT->pix_icon('i/warning', 'KO').' ';
        
        $mform = $this->_form;
        $data = $this->_customdata;
        $mform->addElement('header', 'themehdr', 'Thème');
        $mform->setExpanded('themehdr');
        if($data->acparistheme) {
            if($data->currentthemename === $data->acparistheme) {
                $msg = $validico . 'Le thème \'acparis\' est déjà sélectionné.';
                $chk = false;
            } else {
                $msg = $warningico . 'Le thème sélectionné n\'est pas \'acparis\'.';
                $chk = true;
            }
            $mform->addElement('html', $msg);
            $mform->addElement('checkbox', 'theme', 'Sélectionner le thème \'acparis\'');
            $mform->setType('theme', PARAM_BOOL);
            $mform->setDefault('theme', $chk);
            $mform->freeze('theme');
        } else {
            $mform->addElement('html', 'Le thème \'acparis\' n\'est pas installé.');
        }
        
        $mform->addElement('header', 'rolehdr', 'Rôles');
        $mform->setExpanded('rolehdr');
        
        if($data->catrole) {
            $msg = $validico . 'Rôle \'catcreator\' déjà créé.';
            $cblabel = 'Réinitialiser le rôle \'catcreator\'';
            $chk = false;
        } else {
            $msg = $warningico . 'Le rôle \'catcreator\' n\'existe pas.';
            $cblabel = 'Créer le rôle \'catcreator\'';
            $chk = true;
        }
        $mform->addElement('html', $msg);
        $mform->addElement('checkbox', 'catrole', $cblabel);
        $mform->setType('catrole', PARAM_BOOL);
        $mform->setDefault('catrole', $chk);
        
        if($data->ownerrole) {
            $msg = $validico . 'Rôle \'courseowner\' déjà créé.';
            $cblabel = 'Réinitialiser le rôle \'courseowner\'';
            $chk = false;
        } else {
            $msg = $warningico . 'Le rôle \'courseowner\' n\'existe pas.';
            $cblabel = 'Créer le rôle \'courseowner\'';
            $chk = true;
        }
        $mform->addElement('html', $msg);
        $mform->addElement('checkbox', 'ownerrole', $cblabel);
        $mform->setType('ownerrole', PARAM_BOOL);
        $mform->setDefault('ownerrole', $chk);
        
        
        $this->add_action_buttons();
    }
}

$posturl = new moodle_url('/auth/entsync/init.php', ['do' => 'init']);
$form = new init_form($posturl, $data); 

if($form->is_cancelled()) {
    redirect(new moodle_url('/'));
}

function init_role($roleid, $archetype, $caps = []) {
    global $DB;
    $systemcontext = context_system::instance();
    
    set_role_contextlevels($roleid, get_default_contextlevels('coursecreator'));
    
    foreach (['assign', 'override', 'switch'] as $type) {
        $sql = "SELECT r.*
        FROM {role} r
        JOIN {role_allow_{$type}} a ON a.allow{$type} = r.id
        WHERE a.roleid = :roleid
        ORDER BY r.sortorder ASC";
        $current = array_keys($DB->get_records_sql($sql, array('roleid'=>$roleid)));
        
        $addfunction = 'allow_'.$type;
        $deltable = 'role_allow_'.$type;
        $field = 'allow'.$type;
        
        $wanted = get_default_role_archetype_allows($type, $archetype);
        
        foreach ($current as $sroleid) {
            if (!in_array($sroleid, $wanted)) {
                $DB->delete_records($deltable, array('roleid'=>$roleid, $field=>$sroleid));
                continue;
            }
            $key = array_search($sroleid, $wanted);
            unset($wanted[$key]);
        }
        
        foreach ($wanted as $sroleid) {
            if ($sroleid == -1) {
                $sroleid = $roleid;
            }
            $addfunction($roleid, $sroleid);
        }
    }
    
    $wanted = get_default_capabilities($archetype);
    foreach ($caps as $cap) $wanted[$cap] = CAP_ALLOW;
    
    $current = $DB->get_records_menu('role_capabilities', array('roleid' => $roleid,
                'contextid' => $systemcontext->id), '', 'capability,permission');
    foreach($current as $cap => $perm) {
        if(!array_key_exists($cap, $wanted)) {
            unassign_capability($cap, $roleid, $systemcontext->id);
        }
    }
    foreach($wanted as $cap => $perm) {
        assign_capability($cap, $perm, $roleid, $systemcontext->id, true);
    }
    $systemcontext->mark_dirty();
}


if($formdata = $form->get_data())
{
    //on effectue les opération demandée
    //thème
    if((isset($formdata->theme)) && ($data->acparistheme) && ($data->currentthemename !== $data->acparistheme)) {
        $theme = theme_config::load('acparis');
        $themename = core_useragent::get_device_type_cfg_var_name('default');
        set_config($themename, $theme->name);
    }
    
    //rôles
    if(isset($formdata->catrole)) {
        if($data->catrole) {
            //réinitialiser le rôle créateur de catégorie
            $roleid = $data->catrole;
            
        } else {
            //créer le rôle créateur de catégorie
            $roleid = create_role('Créateur de cours et catégories', 'catcreator',
                'Les créateurs de cours et catégories peuvent créer de nouveau cours et de nouvelles catégories de cours',
                'coursecreator');
        }
        init_role($roleid, 'catcreator', ['moodle/category:manage', 'moodle/category:viewhiddencategories']);
    }
    
    if((isset($formdata->ownerrole)) && (!$data->ownerrole)) {
        //propriétaire de cours
        
    }
    
    redirect($posturl, 'Effectué');
}


echo $OUTPUT->header();
echo $OUTPUT->heading('Initialisation de cette instance Moodle');
$form->display();
echo $OUTPUT->footer();
die;
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

//TODO : gérer le format de cours par défaut

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
//require_once(__DIR__ . '/lib/table.php');
//require_once('ent_defs.php');

require_login();
admin_externalpage_setup('authentsyncparam');
require_capability('moodle/site:config', context_system::instance());

if(optional_param('do', 'no', PARAM_TEXT) !== 'init') {
    redirect(new moodle_url('/'));
}

//cherche les élèments déjà initialisés.
$data = new stdClass();
//rôles
$data->catrole = $DB->get_field('role', 'id', ['shortname' => 'catcreator']);
$data->ownerrole = $DB->get_field('role', 'id', ['shortname' => 'courseowner']);

$data->restorersnewrole = $CFG->restorernewroleid;
$data->creatornewrole = $CFG->creatornewroleid;

//theme
$data->currentthemename = core_useragent::get_device_type_theme('default');
if (!$data->currentthemename) {
    $data->currentthemename = theme_config::DEFAULT_THEME;
}
if(array_key_exists('acparis', core_component::get_plugin_list('theme'))) {
    $data->acparistheme = 'acparis';
} else {
    $data->acparistheme = false;
}

//homepage
$data->defaulthomepage = $CFG->defaulthomepage;

//plugin entsync
$data->pluginenabled = is_enabled_auth('entsync');

class init_form extends moodleform {
    function definition () {
        global $OUTPUT;
        $validico = $OUTPUT->pix_icon('i/valid', 'OK').' ';
        $warningico = $OUTPUT->pix_icon('i/warning', 'KO').' ';
        
        $mform = $this->_form;
        $data = $this->_customdata;
        
        //plugin entsync
        $mform->addElement('header', 'pluginhdr', 'Thème');
        $mform->setExpanded('pluginhdr');
        if($data->pluginenabled) {
            $msg = $validico . 'Le plugin \'entsync\' est déjà activé.';
            $chk = false;
            $freeze = true;
        } else {
            $msg = $warningico . 'Le plugin \'entsync\' n\'est pas activé.';
            $chk = true;
            $freeze = false;
        }
        $mform->addElement('html', $msg);
        if(!$freeze) {
            $mform->addElement('checkbox', 'plugin', 'Activer le plugin \'entsync\'');
            $mform->setType('plugin', PARAM_BOOL);
            $mform->setDefault('plugin', $chk);
        }
        
        //theme
        $mform->addElement('header', 'themehdr', 'Thème');
        $mform->setExpanded('themehdr');
        if($data->acparistheme) {
            if($data->currentthemename === $data->acparistheme) {
                $msg = $validico . 'Le thème \'acparis\' est déjà sélectionné.';
                $chk = false;
                $freeze = true;
            } else {
                $msg = $warningico . 'Le thème sélectionné n\'est pas \'acparis\'.';
                $chk = true;
                $freeze = false;
            }
            $mform->addElement('html', $msg);
            if(!$freeze) {
                $mform->addElement('checkbox', 'theme', 'Sélectionner le thème \'acparis\'');
                $mform->setType('theme', PARAM_BOOL);
                $mform->setDefault('theme', $chk);
            }
        } else {
            $mform->addElement('html', 'Le thème \'acparis\' n\'est pas installé.');
        }

        //Home page
        $mform->addElement('header', 'homehdr', 'Page d\'accueil');
        $mform->setExpanded('homehdr');
        if($data->defaulthomepage == HOMEPAGE_SITE) {
            $freeze = true;
            $chk = false;
            $msg = $validico . 'La page d\'accueil est déjà réglée sur \'Site\'.';
        } else {
            $freeze = false;
            $chk = true;
            $msg = $warningico . 'La page d\'accueil n\'est pas réglée sur \'Site\'.';
        }
        $mform->addElement('html', $msg);
        if(!$freeze) {
            $mform->addElement('checkbox', 'homepage', 'Régler la page d\'accueil sur \'Site\'');
            $mform->setType('homepage', PARAM_BOOL);
            $mform->setDefault('homepage', $chk);
        }
        
        //rôles
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
            
            if($data->ownerrole === $data->creatornewrole) {
                $msg2 =  $validico . 'Rôle \'courseowner\' déjà défini comme rôle par défaut dans les nouveaux cours.';
                $freeze2 = true;
                $chk2 = false;
            } else {
                $msg2 =  $warningico . 'Le rôle \'courseowner\' n\'est pas défini comme rôle par défaut dans les nouveaux cours.';
                $freeze2 = false;
                $chk2 = true;
            }
            if($data->ownerrole === $data->restorersnewrole) {
                $msg3 =  $validico . 'Rôle \'courseowner\' déjà défini comme rôle par défaut dans les cours restaurés.';
                $freeze3 = true;
                $chk3 = false;
            } else {
                $msg3 =  $warningico . 'Le rôle \'courseowner\' n\'est pas défini comme rôle par défaut dans les cours restaurés.';
                $freeze3 = false;
                $chk3 = true;
            }
            
        } else {
            $msg = $warningico . 'Le rôle \'courseowner\' n\'existe pas.';
            $cblabel = 'Créer le rôle \'courseowner\'';
            $chk = true;
            
            $msg2 = 0; $msg3 = 0;
            $freeze2 = false; $freeze3 = false;
            $chk2 = true; $chk3 = true;
        }
        $mform->addElement('html', $msg);
        $mform->addElement('checkbox', 'ownerrole', $cblabel);
        $mform->setType('ownerrole', PARAM_BOOL);
        $mform->setDefault('ownerrole', $chk);
        
        if($msg2) $mform->addElement('html', $msg2);
        if(!$freeze2) {
            $mform->addElement('checkbox', 'creatornewrole', 'Définir \'courseowner\' comme rôle par défaut dans les nouveaux cours');
            $mform->setType('creatornewrole', PARAM_BOOL);
            $mform->setDefault('creatornewrole', $chk2);
        } else {
            $mform->addElement('html', '<br />');
        }
        
        if($msg3) $mform->addElement('html', $msg3);
        if(!$freeze3) {
            $mform->addElement('checkbox', 'restorernewrole', 'Définir \'courseowner\' comme rôle par défaut dans les cours restaurés');
            $mform->setType('restorernewrole', PARAM_BOOL);
            $mform->setDefault('restorernewrole', $chk3);
        }

        $this->add_action_buttons();
    }
}

$data->posturl = new moodle_url('/auth/entsync/init.php', ['do' => 'init']);
$form = new init_form($data->posturl, $data); 

if($form->is_cancelled()) {
    redirect(new moodle_url('/'));
}

function entsync_updatesort($data) {
    global $DB;
    $rolelst = $DB->get_records('role', [], 'sortorder');
    if($data->catrole) {
        $catrole = $rolelst[$data->catrole];
        unset($rolelst[$data->catrole]);
    }
    if($data->ownerrole) {
        $ownerrole = $rolelst[$data->ownerrole];
        unset($rolelst[$data->ownerrole]);
    }
    
    $i=1;
    foreach($rolelst as $roleid => $role) {
        switch($role->shortname) {
            case 'editingteacher' :
                if($data->ownerrole) {
                    $ownerrole->neworder = $i++;
                }
                break;
            case 'coursecreator' :
                if($data->catrole) {
                    $catrole->neworder = $i++;
                }
                break;
        }
        $role->neworder = $i++;
    }
    if($data->catrole) {
        $rolelst[$data->catrole] = $catrole;
    }
    if($data->ownerrole) {
        $rolelst[$data->ownerrole] = $ownerrole;
    }
    $temp = $DB->get_field('role', 'MAX(sortorder) + 1', array());
    foreach($rolelst as $roleid => $role) {
        if($role->sortorder != $role->neworder) {
            $rec = new stdClass();
            $rec->id = $roleid;
            $rec->sortorder = $temp + $role->sortorder;
            $DB->update_record('role', $rec);
        }
    }
    foreach($rolelst as $roleid => $role) {
        if($role->sortorder != $role->neworder) {
            $rec = new stdClass();
            $rec->id = $roleid;
            $rec->sortorder = $role->neworder;
            $DB->update_record('role', $rec);
        }
    }
}

function entsync_settheme() {
    $theme = theme_config::load('acparis');
    $themename = core_useragent::get_device_type_cfg_var_name('default');
    set_config($themename, $theme->name);
}

function entsync_init_role($roleid, $archetype, $caps = []) {
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

function entsync_enableplugin() {
    get_enabled_auth_plugins(true); // fix the list of enabled auths
    if (empty($CFG->auth)) {
        $authsenabled = array();
    } else {
        $authsenabled = explode(',', $CFG->auth);
    }
    
    if (!exists_auth_plugin('entsync')) {
        print_error('pluginnotinstalled', 'auth', $returnurl, $auth);
    }
    
    if (!in_array('entsync', $authsenabled)) {
        $authsenabled[] = 'entsync';
        $authsenabled = array_unique($authsenabled);
        set_config('auth', implode(',', $authsenabled));
    }
    core_plugin_manager::reset_caches();
}


if($formdata = $form->get_data())
{
    //on effectue les opération demandée
    
    //plugin entsync
    if((isset($formdata->plugin)) && (!$data->pluginenabled)) {
        entsync_enableplugin();
    }
    
    //thème
    if((isset($formdata->theme)) && ($data->acparistheme) && ($data->currentthemename !== $data->acparistheme)) {
        entsync_settheme();
    }
    
    //rôles
    $data->updatesort = false;
    if(isset($formdata->catrole)) {
        $data->updatesort = true;
        if($data->catrole) {
            //réinitialiser le rôle créateur de catégorie
            
        } else {
            //créer le rôle créateur de catégorie
            $data->catrole = create_role('Créateur de cours et catégories', 'catcreator',
                'Les créateurs de cours et catégories peuvent créer de nouveau cours et de nouvelles catégories de cours',
                'coursecreator');
        }
        entsync_init_role($data->catrole, 'coursecreator', ['moodle/category:manage']);
    }

    if(isset($formdata->ownerrole)) {
        $data->updatesort = true;
        if($data->ownerrole) {
            //réinitialiser le rôle propriétaire de cours
    
        } else {
            //créer le rôle propriétaire de cours
            $data->ownerrole = create_role('Propriétaire du cours', 'courseowner',
                'Le propriétaire du cours peut le gérer et le supprimer',
                'editingteacher');
        }
        entsync_init_role($data->ownerrole, 'editingteacher', ['moodle/course:delete']);
    }
    
    if($data->updatesort) {
        entsync_updatesort($data);
    }
    
    if($data->ownerrole) {
        if(isset($formdata->creatornewrole)) {
            set_config('creatornewroleid', $data->ownerrole); 
        }
        if(isset($formdata->restorernewrole)) {
            set_config('restorernewroleid', $data->ownerrole);
        }
    }
    
    if(isset($formdata->homepage)) {
        set_config('defaulthomepage', HOMEPAGE_SITE);
    }
    
    redirect($data->posturl, 'Effectué');
}


echo $OUTPUT->header();
echo $OUTPUT->heading('Initialisation de cette instance Moodle');
$form->display();
echo $OUTPUT->footer();
die;

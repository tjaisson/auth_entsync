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
        $mform = $this->_form;
        $data = $this->_customdata;
        $mform->addElement('header', 'themehdr', 'Thème');
        $mform->setExpanded('themehdr');
        if($data->acparistheme) {
            if($data->currentthemename === $data->acparistheme) {
                $msg = 'Le thème \'acparis\' est déjà sélectionné.';
                $chk = false;
            } else {
                $msg = 'Le thème sélectionné n\'est pas \'acparis\'.';
                $chk = true;
            }
            $mform->addElement('html', $msg);
            $mform->addElement('checkbox', 'theme', 'Sélectionner le thème \'acparis\'');
            $mform->setType('theme', PARAM_BOOL);
            $mform->setDefault('theme', $chk);
        } else {
            $mform->addElement('html', 'Le thème \'acparis\' n\'est pas installé.');
        }
        
        $mform->addElement('header', 'rolehdr', 'Rôles');
        $mform->setExpanded('rolehdr');
        
        if($data->catrole) {
            $msg = 'Rôle \'catcreator\' déjà créé.';
            $chk = false;
        } else {
            $msg = 'Le rôle \'catcreator\' n\'existe pas.';
            $chk = true;
        }
        $mform->addElement('html', $msg);
        $mform->addElement('checkbox', 'catrole', 'Créer le rôle \'catcreator\'');
        $mform->setType('catrole', PARAM_BOOL);
        $mform->setDefault('catrole', $chk);
        
        if($data->ownerrole) {
            $msg = 'Rôle \'courseowner\' déjà créé.';
            $chk = false;
        } else {
            $msg = 'Le rôle \'courseowner\' n\'existe pas.';
            $chk = true;
        }
        $mform->addElement('html', $msg);
        $mform->addElement('checkbox', 'ownerrole', 'Créer le rôle \'courseowner\'');
        $mform->setType('ownerrole', PARAM_BOOL);
        $mform->setDefault('ownerrole', $chk);
        
        
        
        $this->add_action_buttons();
            
    }

}

$form = new init_form(null, $data); 

echo $OUTPUT->header();
echo $OUTPUT->heading('Initialisation de cette instance Moodle');
$form->display();
echo $OUTPUT->footer();
die;

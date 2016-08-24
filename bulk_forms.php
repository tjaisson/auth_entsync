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
 * Définition du formulaire de synchronisation
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';

/**
 * Définition du formulaire de synchronisation
 *
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_bulk_form extends moodleform {
    function definition () {
    	$displayproceed = (isset($this->_customdata['displayproceed'])) ? $this->_customdata['displayproceed'] : false;
        $mform = $this->_form;
		
        if($displayproceed)
        {
        	$infohtml = $this->_customdata['displayhtml'];
        	$multi = $this->_customdata['multi'];
        	
        	$mform->addElement('header', 'proceedgrp', get_string('proceed', 'auth_entsync'));
        	$mform->addElement('html', $infohtml);
        	$mform->addElement('submit', 'proceed', get_string('dosync', 'auth_entsync'));
        	$mform->addElement('hidden', 'step', 1);
            $mform->setType('step', PARAM_INT);
            if($multi) {
            	$mform->addElement('header', 'settingsheader', get_string('uploadadd', 'auth_entsync'));
            	$mform->addElement('html', get_string('uploadaddinfo', 'auth_entsync'));
            }
        } else {
        	$mform->addElement('header', 'settingsheader', get_string('upload'));
        	$multi = true;
        }
        
        if($multi) {
            $mform->addElement('filepicker', 'userfile', get_string('file'));
           
            $options = array();
            $txt = get_string('filetypemissingwarn', 'auth_entsync');
            $options[$txt] = [0 => '...'];
            foreach (auth_entsync_ent_base::get_ents() as $entcode=>$ent) {
                if($ent->is_enabled()) {
                    $suboption = array();
                    foreach($ent->get_filetypes() as $i => $desc) {
                        $suboption[$ent->get_code().'.'.$i] = "{$ent->nomcourt} - {$desc}";
                    }
                    $options[$ent->nomcourt] = $suboption;
                }
            }
            
            $mform->addElement('selectgroups', 'filetype', get_string('filetypeselect', 'auth_entsync'), $options);
            $mform->addRule('filetype', $txt, 'nonzero', null, 'client');
            $mform->setType('filetype', PARAM_TEXT);
            if($displayproceed)
            {
            	$mform->addElement('submit', 'déposer', get_string('upload'));
            } else {
                $mform->addElement('submit', 'déposer', get_string('next'));
            }
        } else {
            $mform->addElement('hidden', 'filetype', 0);
            $mform->setType('filetype', PARAM_TEXT);
        }
    }
    public function disable_filetype() {
        $this->_form->freeze(['filetype']);
    }
}

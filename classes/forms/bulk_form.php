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

namespace auth_entsync\forms;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Définition du formulaire de synchronisation
 *
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_form extends \moodleform {
    protected function definition () {
        $displayproceed = (isset($this->_customdata['displayproceed'])) ? $this->_customdata['displayproceed'] : false;
        $advanced = (isset($this->_customdata['advanced'])) ? $this->_customdata['advanced'] : false;
        $mform = $this->_form;

        if ($advanced) {
            $mform->addElement('hidden', 'advanced', true);
            $mform->setType('advanced', PARAM_BOOL);
        }

        if ($displayproceed) {
            $infohtml = $this->_customdata['displayhtml'];
            $displayupload = $this->_customdata['multi'];
            $storeid = $this->_customdata['storeid'];
            $mform->addElement('header', 'proceedgrp', \get_string('proceed', 'auth_entsync'));
            $mform->addElement('html', $infohtml);
            $mform->addElement('hidden', 'storeid', $storeid);
            $mform->setType('storeid', PARAM_INT);
            if ($advanced) {
                $mform->addElement('checkbox', 'recupcas', 'Récupérer les utilisateurs de auth_cas');
                $mform->setDefault('recupcas', false);
            }

            $mform->addElement('submit', 'proceed', \get_string('dosync', 'auth_entsync'));
            if ($displayupload) {
                $mform->addElement('header', 'settingsheader', \get_string('uploadadd', 'auth_entsync'));
                $mform->addElement('html', \get_string('uploadaddinfo', 'auth_entsync'));
                $mform->setExpanded('settingsheader', false, true);
            }
        } else {
            $mform->addElement('header', 'settingsheader', \get_string('upload'));
            $displayupload = true;
        }

        if ($displayupload) {
            $mform->addElement('filepicker', 'userfile', \get_string('file'));

            $options = array();
            $txt = \get_string('filetypemissingwarn', 'auth_entsync');
            $options[$txt] = [0 => '...'];
            foreach (\auth_entsync_ent_base::get_ents() as $entcode => $ent) {
                if ($ent->is_enabled()) {
                    $suboption = array();
                    foreach ($ent->get_filetypes() as $i => $desc) {
                        $suboption[$ent->get_code().'.'.$i] = "{$ent->nomcourt} - {$desc}";
                    }
                    $options[$ent->nomcourt] = $suboption;
                }
            }

            $mform->addElement('selectgroups', 'entfiletype', \get_string('filetypeselect', 'auth_entsync'), $options);
            $mform->addRule('entfiletype', $txt, 'nonzero', null, 'client');
            $mform->setType('entfiletype', PARAM_TEXT);
            if ($displayproceed) {
                $mform->freeze(['entfiletype']);
                $mform->addElement('submit', 'deposer', \get_string('upload'));
            } else {
                $mform->addElement('submit', 'deposer', \get_string('next'));
                $mform->addHelpButton('entfiletype', 'filetypeselect', 'auth_entsync');
            }
        } else {
            $mform->addElement('hidden', 'entfiletype', 0);
            $mform->setType('entfiletype', PARAM_TEXT);
        }
    }
}

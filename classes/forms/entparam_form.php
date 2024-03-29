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
 * Définition du formulaire de configuration
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\forms;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/accesslib.php');

/**
 * Définition du formulaire de configuration
 *
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entparam_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;

        // Ent list.
        $mform->addElement('header', 'entlistheader', \get_string('entlist', 'auth_entsync'));
        $mform->setExpanded('entlistheader');
        $mform->addHelpButton('entlistheader', 'entlist', 'auth_entsync');
        $mform->addElement('html', $this->buildentlist());

        // Rôles par profil.
        $mform->addElement('header', 'rolesheader', \get_string('entsyncparam', 'auth_entsync'));
        $mform->setExpanded('rolesheader');
        $sysroles[0] = \get_string('none');
        $roles = \auth_entsync\helpers\rolehelper::getsysrolemenu();
        foreach ($roles as $roleid => $rolename) {
            $sysroles[$roleid] = $rolename;
        }
        $mform->addElement('select', 'role_ens', \get_string('roleensselect', 'auth_entsync'), $sysroles);
        $mform->setType('role_ens', PARAM_INT);
        $mform->addHelpButton('role_ens', 'roleensselect', 'auth_entsync');
        $this->add_entsettings($mform);
        $this->add_action_buttons(false, \get_string("savechanges"));
    }

    private function buildentlist() {
        global $OUTPUT;
        $t = new \html_table();
        $txt = \get_strings(['entname', 'sso', 'connecturl'], 'auth_entsync');
        $txt2 = \get_strings(['enable', 'disable', 'yes', 'no']);
        $t->head = [$txt->entname, $txt2->enable, $txt->sso, $txt->connecturl];
        
        $actionurl = new \moodle_url('/auth/entsync/param.php', ['sesskey' => sesskey()]);
        $disableico = $OUTPUT->pix_icon('t/hide', $txt2->disable);
        $enableico = $OUTPUT->pix_icon('t/show', $txt2->enable);
        
        foreach (\auth_entsync_ent_base::get_ents() as $entcode => $ent) {
            // Hack : only show ent 4 & 6 if already activated
            if (($entcode == '4' || $entcode == '6') && (!$ent->is_enabled())) {
                continue;
            }
            $actionurlent = new \moodle_url($actionurl, ['ent' => $entcode]);
            $class = '';
            // Hide/show link.
            if ($ent->is_enabled()) {
                $actionurlent->param('action', 'disable');
                $hideshow = \html_writer::link($actionurlent, $disableico);
            } else {
                $actionurlent->param('action', 'enable');
                $hideshow = \html_writer::link($actionurlent, $enableico);
                $class = 'dimmed_text';
            }
            if ($ent->is_sso()) {
                $yn = $txt2->yes;
            } else {
                $yn = $txt2->no;
            }

            $helpname = $ent->nomlong . $OUTPUT->help_icon("ent{$ent->get_entclass()}", 'auth_entsync');
            $row = new \html_table_row([$helpname, $hideshow, $yn, $ent->get_connector_url()]);
            if ($class) {
                $row->attributes['class'] = $class;
            }
            $t->data[] = $row;
        }
        return \html_writer::table($t);
    }

    private function add_entsettings($mform) {
        foreach (\auth_entsync_ent_base::get_ents() as $entcode => $ent) {
            if ($ent->is_enabled() && $ent->has_settings()) {
                $hdr = "header-{$entcode}";
                $mform->addElement('header', $hdr, \get_string('entspecparam', 'auth_entsync', $ent->nomlong));
                $mform->setExpanded($hdr);
                $ent->add_formdef($mform);
            }
        }
    }
}

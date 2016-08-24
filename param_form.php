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
 * Définition du formulaire de configuration
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';
require_once $CFG->libdir.'/accesslib.php';

/**
 * Upload a file CVS file with user information.
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_entparam_form extends moodleform {
    function definition () {
        $mform = $this->_form;

        //ent list
        $mform->addElement('header', 'entlistheader', get_string('entlist', 'auth_entsync'));
        $mform->addElement('html', $this->buildentlist());
        
        //rôles par profil
        $mform->addElement('header', 'rolesheader', get_string('entsyncparam', 'auth_entsync'));
        $sysroles[0] = get_string('none');
        $roles =  auth_entsync_rolehelper::getsysrolemenu(); // get_roles_for_contextlevels(CONTEXT_SYSTEM);  //role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT);
        foreach ($roles as $roleid => $rolename) {
            $sysroles[$roleid] = $rolename;
        }
        $mform->addElement('select', 'role_ens', get_string('roleensselect', 'auth_entsync'), $sysroles);
        $mform->setType('role_ens', PARAM_INT);

        $this->add_action_buttons(false, get_string("savechanges"));
    }
    
    private function buildentlist() {
        global $OUTPUT;
        $t = new html_table();
        $txt = get_strings(['entname', 'sso', 'connecturl'], 'auth_entsync');
        $txt2 = get_strings(['enable', 'disable', 'yes', 'no']);
        $t->head = [$txt->entname, $txt2->enable, $txt->sso, $txt->connecturl];

        foreach (auth_entsync_ent_base::get_ents() as $entcode => $ent) {
            
            $url = "entenable.php?sesskey=" . sesskey();
            $class = '';
            // hide/show link
            if ($ent->is_enabled()) {
                $hideshow = "<a href=\"$url&amp;action=disable&amp;ent=$entcode\">";
                $hideshow .= "<img src=\"" . $OUTPUT->pix_url('t/hide') . "\" class=\"iconsmall\" alt=\"disable\" /></a>";
                // $hideshow = "<a href=\"$url&amp;action=disable&amp;auth=$auth\"><input type=\"checkbox\" checked /></a>";
            }
            else {
                $hideshow = "<a href=\"$url&amp;action=enable&amp;ent=$entcode\">";
                $hideshow .= "<img src=\"" . $OUTPUT->pix_url('t/show') . "\" class=\"iconsmall\" alt=\"enable\" /></a>";
                // $hideshow = "<a href=\"$url&amp;action=enable&amp;auth=$auth\"><input type=\"checkbox\" /></a>";
                $class = 'dimmed_text';
            }
            if($ent->is_sso()) {
                $yn = $txt2->yes;
            } else {
                $yn = $txt2->no;
            }
            
            $row = new html_table_row([$ent->nomlong, $hideshow, $yn, $ent->get_connector_url()]);
            if ($class) {
                $row->attributes['class'] = $class;
            }
            $t->data[] = $row;
        }
        
        return html_writer::table($t);
    }
}


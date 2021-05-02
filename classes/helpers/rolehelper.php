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
 * lib de fonctions utilisées pour la gestion des rôles système
 *
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\helpers;
defined('MOODLE_INTERNAL') || die();

use \auth_entsync\helpers\usertblhelper;

class rolehelper {
    static function removeroles($userid) {
        global $DB;
        $userroles = $DB->get_records_menu('role_assignments',
            ['component' => 'auth_entsync', 'userid' => $userid]);
        foreach($userroles as $roleid) {
            \role_unassign($roleid, $userid, \context_system::instance()->id, 'auth_entsync');
        }
    }

    static function updaterole($userid, $newroleid) {
        global $DB;
        $_already = false;
        $userroles = $DB->get_records_menu('role_assignments',
            ['component' => 'auth_entsync', 'userid' => $userid]);
        foreach($userroles as $roleid) {
            if($roleid == $newroleid) {
                $_already = true;
            } else {
                \role_unassign($roleid, $userid, \context_system::instance()->id, 'auth_entsync');
            }
        }
        if(($newroleid > 0) && (!$_already)) {
            \role_assign($newroleid, $userid, \context_system::instance()->id, 'auth_entsync');
        }
    }

    static function updateroleallusers($profile, $newroleid) {
        $iurs = usertblhelper::get_users($profile);
        foreach($iurs as $userid) {
            self::updaterole($userid, $newroleid);
        }
    }

    /**
     * @param int $profile le profil à traiter
     */
    static function removerolesallusers($profile) {
        $iurs = usertblhelper::get_users($profile);
        
        foreach($iurs as $userid) {
            self::removeroles($userid);
        }
    }

    static function getsysrolemenu() {
        static $ret;
        if(isset($ret)) return $ret;
        global $DB;
        $lst = \get_assignable_roles(\context_system::instance(), ROLENAME_ORIGINALANDSHORT);
        $ret = array();
        foreach($lst as $roleid => $rolename) {
            $archetype = $DB->get_field('role', 'archetype', ['id' => $roleid]);
            if($archetype === 'coursecreator') {
                $ret[$roleid] = $rolename;
            }
        }
        return $ret;
    }
}
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
 * ENT Connect
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

class auth_entsync_usertbl {
    private static function build_sql($select, $profile, $ent) {
        global $DB;
        if($profile === -1) {
            $param = array();
            $sql = '';
        } else {
            list($sql, $param) = $DB->get_in_or_equal($profile, SQL_PARAMS_NAMED, 'prf'); 
            $sql = ' AND b.profile ' . $sql;
        }
        if($ent != -1) {
            $sql .= ' AND b.ent = :ent';
            $param['ent'] = $ent;
        }

        $sql = "SELECT {$select}
        FROM {user} a
        WHERE a.auth = 'entsync'
        AND a.deleted = 0
        AND a.suspended = 0
        AND (SELECT COUNT(1) FROM {auth_entsync_user} b
        WHERE b.userid = a.id
        AND b.archived = 0{$sql}
        ) > 0";
        
        return [$sql, $param];
    }
    
    static function get_users($profile = -1, $ent = -1) {
        global $DB;
        list($sql, $param) = self::build_sql('a.id', $profile, $ent);
        return $DB->get_fieldset_sql($sql, $param);
    }
    
    static function count_users($profile = -1, $ent = -1) {
        global $DB;
        list($sql, $param) = self::build_sql('COUNT(1)', $profile, $ent);
        return $DB->count_records_sql($sql, $param);
    }
    
}
    class auth_entsync_tmptbl {
/**
     * Vide la table temporaire 'tool_entsync_tmpul'
     */
    static function reset() {
        global $DB;
        $DB->delete_records('auth_entsync_tmpul');
    }
    
    /**
     * Compte le nombre d'enregistrements présents dans la table
     * temporaire 'tool_entsync_tmpul'
     *
     * @param int $profile optionnel
     * @return number
     */
    static function count_users($profile = -1) {
        global $DB;
        if($profile === -1 )
            return $DB->count_records('auth_entsync_tmpul');
        else
            return $DB->count_records('auth_entsync_tmpul', ['profile' => $profile]);
    }
}
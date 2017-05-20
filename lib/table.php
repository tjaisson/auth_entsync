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
    private static function build_select() {
        global $DB;
        $select = 'a.id as id, a.username as username,
IF((SUBSTRING(a.password,1 , 8) = \'entsync\\\\\'), SUBSTRING(a.password,9), NULL) as password,
a.lastname as lastname, a.firstname as firstname, BIT_OR(b.profile) as profiles';
        $manual = array();
        foreach (auth_entsync_ent_base::get_ents() as $entcode =>$ent) {
            if($ent->is_enabled()) {
                $select .= ", BIT_OR(b.ent = {$entcode}) as ent{$entcode}";
                if($ent->get_mode() === 'local') {
                    $manual[] = $entcode;
                }
            }
        }
        if(empty($manual)) {
            $select .= ', 0 as local';
            $param = array();
        } else {
            list($sql, $param) = $DB->get_in_or_equal($manual, SQL_PARAMS_NAMED, 'man');
            $select .= ", BIT_OR(b.ent {$sql}) as local";
        }
        return [$select, $param];
    }

    static function get_user_ent($userid) {
        global $DB;
        list($select, $param) = self::build_select();
        
        $sql = "SELECT {$select}
            FROM {user} a
            JOIN {auth_entsync_user} b on b.userid = a.id
            WHERE a.auth = 'entsync' AND a.deleted = 0 AND a.suspended = 0 AND b.archived = 0 AND a.id = :userid
            GROUP BY a.id";
        $param['userid'] = $userid;
        return $DB->get_record_sql($sql, $param);
    }
    
    static function get_users_ent_ens() {
        global $DB;
        list($select, $param) = self::build_select();

        $sql = "SELECT {$select}
            FROM {user} a
            JOIN {auth_entsync_user} b on b.userid = a.id
            WHERE a.auth = 'entsync' AND a.deleted = 0 AND a.suspended = 0 AND b.archived = 0
            GROUP BY a.id
            HAVING profiles = 2
            ORDER BY a.lastname, a.firstname";
        
        return $DB->get_records_sql($sql, $param);
    }

    protected static function cleanu($u) {
        $ents = explode(',', $u->ents);
        $u->ents = array();
        foreach($ents as $ent) {
            $ent = explode(';', $ent);
            $u->ents[$ent[0]] = [
                'id' => $ent[0],
                'sync' => $ent[1],
                'uid' => $ent[2],
                'struct' => $ent[3],
                'profile' => $ent[4],
                'archived' => $ent[5],
                'archivedsince' => $ent[6]
            ];
        }
    }
    
    const userselect =
'SELECT a.id as id, a.firstname as firstname, a.lastname as lastname, a.username as username,
 IF((SUBSTRING(a.password,1 , 8) = \'entsync\\\\\'), SUBSTRING(a.password,9), NULL) as password,
 BIT_OR(b.profile) as profiles,
 GROUP_CONCAT(CONCAT_WS(\';\', b.ent, b.sync, b.uid, b.struct, b.profile, b.archived, b.archivedsince)) as ents';

    static function get_users_ent_ens2() {
        global $DB;
        $sql = self::userselect . '
            FROM {user} a
            JOIN {auth_entsync_user} b on b.userid = a.id
            WHERE a.auth = \'entsync\' AND a.deleted = 0 AND a.suspended = 0 AND b.archived = 0
            GROUP BY a.id
            HAVING profiles = 2
            ORDER BY a.lastname, a.firstname';
        
        $ret = $DB->get_records_sql($sql);
        foreach($ret as $u) {
            self::cleanu($u);
        }
        return $ret;
    }
    
    static function get_users_ent_elev($cohortid) {
    global $DB;
        list($select, $param) = self::build_select();
        
        $sql = "SELECT {$select}
            FROM {user} a
            JOIN {cohort_members} c on c.userid = a.id
            JOIN {auth_entsync_user} b on b.userid = a.id
            WHERE a.auth = 'entsync' AND a.deleted = 0 AND a.suspended = 0 AND b.archived = 0 AND c.cohortid = :cohortid
            GROUP BY a.id
            HAVING profiles = 1
            ORDER BY a.lastname, a.firstname";
        
        $param['cohortid'] = $cohortid;
        return $DB->get_records_sql($sql, $param);
    }
    
    
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
    
    /**
     * Retourne le tableau des userid des utilisateur ent, éventuellement filtrés par profile et/ou ent
     *
     * @param int|array(int) $profile un profil ou un tableau de profils
     * @param int $ent un entcode
     * @return array(entu)
     */
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
    
    /**
     * Retourne le tableau de tous les utilisateur entu (archived ou pas), éventuellement filtrés par profile et/ou ent  
     *
     * @param int|array(int) $profile un profil ou un tableau de profils
     * @param int $ent un entcode
     * @return array(entu)
     */
    static function get_entus($profile = -1, $ent = -1) {
        global $DB;
        list($_select, $params) = self::build_sql_entu($profile, $ent);
        return $DB->get_records_select('auth_entsync_user', $_select, $params);
    }

    private static function build_sql_entu($profile, $ent) {
        global $DB;
        if($profile === -1) {
            $param = array();
            $sql = '';
        } else {
            list($sql, $param) = $DB->get_in_or_equal($profile, SQL_PARAMS_NAMED, 'prf');
            $sql = ' AND profile ' . $sql;
        }
        if($ent != -1) {
            $sql .= ' AND ent = :ent';
            $param['ent'] = $ent;
        }
    
        $sql = "sync = 1{$sql}";
    
        return [$sql, $param];
    }
    
    
}

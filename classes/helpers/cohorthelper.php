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
 * lib de fonctions utilisées pour la gestion des cohorts système
 *
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\helpers;
defined('MOODLE_INTERNAL') || die();

use \auth_entsync\helpers\stringhelper;

class cohorthelper {
    const COHORT_PRFX = 'auto_cl_';

    /**
     * @var array [$cohortname => $cohortid]
     */
    protected static $_cohortsbyname;

    /**
     * @var array [$cohortid => $cohortname]
     */
    protected static $_cohortsbyid;

    /**
     * Prépare les tableaux des cohortes géréess
     */
    protected static function fillcohortsarrays() {
        global $DB;
        self::$_cohortsbyname = array();
        self::$_cohortsbyid = array();
        $_prfx_len = \strlen(self::COHORT_PRFX);
        $lst = $DB->get_records('cohort', ['component' => 'auth_entsync'],
            "SUBSTRING(idnumber, {$_prfx_len})", 'id, name, idnumber');
        foreach ($lst as $id => $c) {
            $id = (int)$id;
            if (0 === \strncmp($c->idnumber, self::COHORT_PRFX, $_prfx_len)) {
                $_cohort_name = stringhelper::simplify_cohort(substr($c->idnumber, $_prfx_len));
                self::$_cohortsbyname[$_cohort_name] = $id;
                self::$_cohortsbyid[$id] = $c->name;
            }
        }
    }

    /**
     * Retourne l'identifiant de la cohorte en la créant au besoin
     *
     * @param string $cohortname
     * @return int cohortid
     */
    protected static function getorcreate_cohort($cohortname) {
        $_cohort_simp_name = stringhelper::simplify_cohort($cohortname);
        if (!isset(self::$_cohortsbyid)) {
            self::fillcohortsarrays();
        }
        if (\array_key_exists($_cohort_simp_name, self::$_cohortsbyname)) {
            return (int)self::$_cohortsbyname[$_cohort_simp_name];
        }
        $newcohort = new \stdClass();
        $newcohort->name = $cohortname;
        $newcohort->idnumber = self::COHORT_PRFX . $_cohort_simp_name;
        $newcohort->component = 'auth_entsync';
        $newcohort->contextid = \context_system::instance()->id;
        $cohortid = \cohort_add_cohort($newcohort);
        self::$_cohortsbyid[$cohortid] = $_cohort_simp_name;
        self::$_cohortsbyname[$_cohort_simp_name] = $cohortid;
        return $cohortid;
    }

    public static function get_cohorts() {
        if (!isset(self::$_cohortsbyid)) {
            self::fillcohortsarrays();
        }
        return self::$_cohortsbyid;
    }

    /**
     * Retourne la liste des cohortes gérées de cet utilisateur
     *
     * @param int $userid
     * @return array of int
     */
    public static function get_usercohorts($userid) {
        global $DB;
        $ret = array();
        $sql = "SELECT a.id
            FROM {cohort} a
            JOIN {cohort_members} b ON b.cohortid = a.id
           WHERE a.component = 'auth_entsync'
             AND b.userid = :userid";
        return \array_unique($DB->get_fieldset_sql($sql, ['userid' => $userid]));
    }

    public static function set_cohort($userid, $cohortname) {
        $cohortid = self::getorcreate_cohort($cohortname);
        $lst = self::get_usercohorts($userid);
        $already = false;
        foreach ($lst as $id) {
            if ($id == $cohortid) {
                $already = true;
            } else {
                \cohort_remove_member($id, $userid);
            }
        }
        if (!$already) {
            \cohort_add_member($cohortid, $userid);
        }
        return $cohortid;
    }

    /**
     * Enlève l'utilisateur de toutes les cohortes gérées
     *
     * @param int $userid userid
     */
    public static function removecohorts($userid) {
        $lst = self::get_usercohorts($userid);
        foreach ($lst as $cohortid) {
            \cohort_remove_member($cohortid, $userid);
        }
    }
}
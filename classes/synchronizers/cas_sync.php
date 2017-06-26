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
 * lib de fonctions utilisées pour la synchronisation
 *
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\synchronizers;
defined('MOODLE_INTERNAL') || die();

/**
 * Synchronizer spécifique pour le mode d'authentification 'cas'
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cas_sync extends \auth_entsync\synchronizers\base_sync {
    protected $recupcas = false;

    public function set_recupcas($val) {
        $this->recupcas = $val;
    }

    protected function lookforuserbynames($iu) {
        global $DB, $CFG;
        if ($this->recupcas) {
            if ($mdlu = $DB->get_record('user', ['auth' => 'cas', 'username' => $iu->uid, 'mnethostid' => $CFG->mnet_localhost_id],
                'id, auth, confirmed, deleted, suspended, mnethostid, username, password, firstname, lastname')) {
                return $mdlu;
            }
        }
        return parent::lookforuserbynames($iu);
    }

    protected function validate_user($iu) {
        if (empty($iu->uid)) {
            return false;
        }
        return true;
    }

    protected function applycreds($_mdlu, $iu) {
        global $DB, $CFG;
        $_mdlu->username = "entsync.{$this->entcode}.{$iu->uid}";
        // TODO : gérer le cas où le username est déjà utilisé
        // ne devrait pas se produire mais déjà vu suite à bugg.
        $clean = \core_user::clean_field($_mdlu->username, 'username');
        if ($_mdlu->username !== $clean) {
            if (0 === $DB->count_records('user', ['username' => $clean])) {
                $_mdlu->username = $clean;
            } else {
                $i = 1;
                while (0 !== $DB->count_records('user', ['username' => $clean . $i])) {
                    ++$i;
                }
                $_mdlu->username = $clean . $i;
            }
        }

        $_mdlu->mnethostid = $CFG->mnet_localhost_id;
        $_mdlu->password = AUTH_PASSWORD_NOT_CACHED;
    }

    protected function updatecreds($mdlu, $_mdlu, $iu) {
    }
}

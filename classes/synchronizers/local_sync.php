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

use \auth_entsync\helpers\stringhelper;


/**
 * Synchronizer spécifique pour le mode d'authentification 'local'
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_sync extends \auth_entsync\synchronizers\base_sync{
    protected function validate_user($iu) {
        return (!empty($iu->firstname)) && (!empty($iu->lastname));
    }

    protected function applycreds($_mdlu, $iu) {
        global $CFG, $DB;
        $_mdlu->isdirty = true;
        $_fn = \core_text::substr(stringhelper::simplify_name($iu->firstname), 0, 1);
        $_ln = stringhelper::simplify_name($iu->lastname);
        $clean = \core_user::clean_field($_fn . $_ln, 'username');
        if (0 === $DB->count_records('user', ['username' => $clean])) {
            $_mdlu->username = $clean;
        } else {
            $i = 1;
            while (0 !== $DB->count_records('user', ['username' => $clean . $i])) {
                ++$i;
            }
            $_mdlu->username = $clean . $i;
        }
        $_mdlu->mnethostid = $CFG->mnet_localhost_id;
        $pw = stringhelper::rnd_string();
        $_mdlu->password = "entsync\\{$pw}";
    }

    protected function updatecreds($mdlu, $_mdlu, $iu) {
        if ($mdlu->password == AUTH_PASSWORD_NOT_CACHED) {
            $this->applycreds($_mdlu, $iu);
        }
    }
}

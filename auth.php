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

require_once($CFG->libdir.'/authlib.php');
require_once('ent_defs.php');

/**
 * Plugin for entsync authentication.
 */
class auth_plugin_entsync extends auth_plugin_base {

    private $locked = ['firstname', 'lastname', 'email'];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'entsync';
        $this->config = new stdClass();
        //$locked = ['firstname', 'lastname', 'email']; On ne bloque plus le champ email.
        $locked = ['firstname', 'lastname'];
        foreach ($this->locked as $field) {
            $cfgname = "field_lock_{$field}";
            $this->config->{$cfgname} = 'locked';
        }
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function auth_plugin_entsync() {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct();
    }

    /**
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login ($username, $password) {
        global $CFG, $DB;
        
        if(!$mdlu = $DB->get_record('user',
            ['username'=>$username, 'auth'=>'entsync', 'mnethostid'=>$CFG->mnet_localhost_id,
             'deleted'=>0, 'suspended'=>0
            ])) {
            return false;
        }

        $entus = $DB->get_records('auth_entsync_user', ['userid' => $mdlu->id, 'archived' => 0]);
        $hasenabledent = false;
        foreach ($entus as $entu) {
            if($ent = auth_entsync_ent_base::get_ent($entu->ent)) {
                if($ent->is_enabled() && (!$ent->is_sso())) {
                    $hasenabledent = true;
                    break;
                }
            }
        }
        if(!$hasenabledent) return false;
        $firstpw = "entsync\\{$password}";
        if($firstpw === $mdlu->password) {
            set_user_preference('auth_forcepasswordchange', true, $mdlu->id);
            return true;
        } else {
            return validate_internal_user_password($mdlu, $password);
        }
    }

    /**
     * Updates the user's password.
     *
     * called when the user password is updated.
     *
     * @param  object  $user        User table object
     * @param  string  $newpassword Plaintext password
     * @return boolean result
     *
     */
    function user_update_password($user, $newpassword) {
        $user = get_complete_user_data('id', $user->id);
        // This will also update the stored hash to the latest algorithm
        // if the existing hash is using an out-of-date algorithm (or the
        // legacy md5 algorithm).
        return update_internal_user_password($user, $newpassword);
    }

    /**
     * Called when the user record is updated.
     * Modifies user in external database. It takes olduser (before changes) and newuser (after changes)
     * compares information saved modified information to external db.
     *
     * @param mixed $olduser     Userobject before modifications    (without system magic quotes)
     * @param mixed $newuser     Userobject new modified userobject (without system magic quotes)
     * @return boolean true if updated or update ignored; false if error
     *
     */
    function user_update($olduser, $newuser) {
        //override if needed
        return true;
    }

    function prevent_local_passwords() {
        return false;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return true;
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    function change_password_url() {
        return null;
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    function can_reset_password() {
        return true;
    }

    /**
     * Returns true if plugin can be manually set.
     *
     * @return bool
     */
    function can_be_manually_set() {
        return false;
    }

    function loginpage_idp_list($wantsurl) {
        global $CFG;
        $lst = array();
        $ents = auth_entsync_ent_base::get_ents();
        foreach ($ents as $ent) {
            if($ent->is_enabled() && $ent->is_sso()) {
                $entclass = $ent->get_entclass();
                $lst[] = [
                    'url' => new moodle_url("{$CFG->wwwroot}/auth/entsync/login.php", ['ent' => $entclass]),
                    'name' => $ent->nomlong,
                    'icon' => $ent->get_icon()
                ];
            }
        }
        
        return $lst;
    }
    
    public function postlogout_hook($user) {
        global $CFG;
        if(($user->auth == 'entsync') && isset($user->entsync)) {
            $ent = auth_entsync_ent_base::get_ent($user->entsync);
            if($ent->get_mode() == 'cas') {
                $cas = $ent->get_casconnector();
                if($cas->support_gw()) {
                    $clienturl = new moodle_url("{$CFG->wwwroot}/auth/entsync/logout.php", ['ent' => $ent->get_entclass()]);
                    $cas->set_clienturl($clienturl);
                    $cas->redirtocas(true);
                } else {
                    $cas->redirtohome();
                }
            }
            
        }
    }
    
    
}



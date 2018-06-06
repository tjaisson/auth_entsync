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
 * Gestion de la connexion OAUTH
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace auth_entsync\connectors;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/filelib.php');

abstract class base_connect {

    /**
     * @return bool true if there is a code back from IdP.
     */
    public abstract function read_code();

    /**
     * Retrieve userinfo from Idp and return it.
     * Should only be called when @see read_code() returns true.
     *
     * {
     *  id: id,
     *
     * }
     *
     * @return \stdClass|false user or false if error
     */
    public abstract function get_user();

    /**
     * @var string json & base_64 encoded array of "state" query parameter
     */
    protected static $raw_query_state = null;

    /**
     * @var array decoded version of @see $raw_query_state
     */
    protected static $query_state_params = null;

    public static function get_raw_query_state() {
        if (self::$raw_query_state == null) {
            if (isset($_GET['state'])) {
                self::$raw_query_state = $_GET['state'];
            } else {
                self::$raw_query_state = '';
            }
        }
        return self::$raw_query_state;
    }

    public static function get_query_state_param($name, $def = null) {
        if (self::$query_state_params == null) {
            $raw_state = self::get_raw_query_state();
            if ($raw_state === '') {
                self::$query_state_params = [];
            } else {
                self::$query_state_params = \json_decode(\base64_decode($raw_state), true);
            }
        }
        if (\array_key_exists($name, self::$query_state_params)) {
            return self::$query_state_params[$name];
        } else {
            return $def;
        }
    }
    
    public static function get_ent_class() {
        return self::get_query_state_param('ent');
    }

    public function set_ent_class($ent_class) {
        $this->set_state_param('ent', $ent_class);
    }

    /**
     * @var array params that will be send in "state" query parameter as json & base_64 encoded string
     */
    protected $_state_params = [];
    
    public function get_state_param($name, $def = null) {
        if (\array_key_exists($name, $this->_state_params)) {
            return $this->_state_params[$name];
        } else {
            return self::get_query_state_param($name, $def);
        }
    }
    
    public function set_state_param($name, $value) {
        $this->_state_params[$name] = $value;
    }
    
    protected function get_encoded_state() {
        return \base64_encode(\json_encode($this->_state_params));
    }
    
    /**
     * @var string|null Null if ok, error msg otherwise
     */
    protected $_error;

    /**
     * @var \moodle_url
     */
    protected $_clienturl;

    /**
     * @param \moodle_url $url
     */
    public function set_clienturl($url) {
        $this->_clienturl = $url;
    }
    
    /**
     *   [
     *   'hostname' => 'www.parisclassenumerique.fr',
     *   'baseuri' => '/connexion/',
     *   'decodecallback' => null //optionnel callable
     *   ];
     *
     *
     * @var array connector parameters
     */
    protected $_params;

    public function set_params($params) {
        $this->_params = $params;
    }

    public function get_param($name, $def = null) {
        if (\array_key_exists($name, $this->_params)) {
            return $this->_params[$name];
        } else {
            return $def;
        }
    }

    public function redirtohome() {
        $homeuri = $this->get_param('homeuri', '/');
        $homehost = $this->get_param('homehost', $this->get_param('hostname'));
        self::_redirect("https://{$homehost}{$homeuri}");
    }

    /**
     * @return \moodle_url
     */
    public abstract function build_login_url();

    public function redir_to_login($gw = false) {
        if ($gw) {
            $this->_params['gw'] = true;
            $this->set_state_param('gw', 'true');
        }
        self::_redirect($this->build_login_url());
    }
    
    protected function BuilServerBaseURL() {
        $ret = 'https://' . $this->get_param('hostname');
        $port = $this->get_param('port', 443);
        if ($port != 443) {
            $ret .= ':' . $port;
        }
        $ret .= $this->get_param('baseuri');
        return $ret;
    }

    /**
     * Get last error
     *
     * @return string error text of null if none
     */
    public function get_error() {
        return $this->_error;
    }

    protected static function _redirect($url) {
        \redirect($url);
    }
    
    public function support_gw() {
        return $this->get_param('supportGW', false);
    }

    public function has_userinfo() {
        return $this->get_param('hasUserinfo', false);
    }

    protected function allow_untrust() {
        return $this->get_param('allowUntrust', false);
    }
}
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
     * @var string|null Null if ok, error msg otherwise
     */
    protected $_error;

    /**
     *   [
     *   'hostname' => 'www.parisclassenumerique.fr',
     *   'baseuri' => '/connexion/',
     *   'decodecallback' => null //optionnel callable
     *   ];
     *
     *
     * @var array
     */
    protected $_params;

    /**
     * @var \moodle_url
     */
    protected $_clienturl;
    
    protected $_params;

    public function set_params($params) {
        $this->_params = $params;
    }

    public function redirtohome() {
        $homeuri = $this->get_param('homeuri', '/');
        $homehost = $this->get_param('homehost', $this->get_param('hostname'));
        self::_redirect("https://{$homehost}{$homeuri}");
    }

    /**
     * Check if auth token or ticket is present,
     * if so retrieve userinfo from server and return it
     * else redirect to authentication server.
     *
     * @return user
     */
    public abstract function GetUserOrRedirect();

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
     * @param \moodle_url $url
     */
    public function set_clienturl($url) {
        $this->_clienturl = $url;
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
    
    public function get_param($name, $def = null) {
        if (\array_key_exists($name, $this->_params)) {
            return $this->_params[$name];
        } else {
            return $def;
        }
    }

    protected function allow_Untrust() {
        return $this->get_param('allowUntrust', false);
    }
}
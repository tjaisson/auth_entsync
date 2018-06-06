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

class oauth_connect extends \auth_entsync\connectors\base_connect {

    protected $_code = null;

    /**
     * @return \moodle_url
     */
    protected function buildvalidateurl() {
    }

    public function build_login_url() {
        $param = [
            'redirect_uri' => $this->_clienturl->out(false),
            'state' => $this->get_encoded_state(),
            'scope' => 'userinfo',
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'client_id' => $this->get_param('client_id'),
        ];
        return new \moodle_url($this->BuildServerBaseURL().'login', $param);
    }

    public function read_code() {
        if ($this->_code === null) {
            if (isset($_GET['code'])) {
                $this->_code = $_GET['code'];
            } else {
                $this->_code = '';
            }
        }
        if ($this->_code === '') {
            return false;
        } else {
            return true;
        }
    }

    public function get_token($code) {
        $cu = new \curl();
        if ($this->allow_untrust()) {
            $cu->setopt(['SSL_VERIFYHOST' => false]);
            $cu->setopt(['SSL_VERIFYPEER' => false]);
        }
        $MyUrl = $this->_clienturl->out(false);
        $PostParams = [
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $this->_clienturl->out(false),
        ];
        
        // Set Auth
        $creds = \base64_encode($this->get_param('client_id') . ':' . $this->get_param('client_id'));
        $cu->setHeader(['Authorization' => "Basic {$creds}"]);
        $rep = $cu->post($this->buildvalidateurl(), $PostParams);
        
    }

    public function read_token() {
        $this->_error = '';
        if (empty($this->_code)) {
            $this->_error = 'Erreur.';
            return false;
        }
        
    }

    public function get_user() {
        
    }
}
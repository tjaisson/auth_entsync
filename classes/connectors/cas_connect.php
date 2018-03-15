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
 * Gestion de la connexion CAS
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace auth_entsync\connectors;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/filelib.php');

class cas_connect extends \auth_entsync\connectors\base_connect {

    /**
     *   [
     *   'hostname' => 'www.parisclassenumerique.fr',
     *   'baseuri' => '/connexion/',
     *   'port' => 443,  //optionnel
     *   'casversion' => '2.0', //optionnel
     *   'retries' => 0 //optionnel
     *   'decodecallback' => null //optionnel callable
     *   ];
     *
     *
     * @var array $_params
     */

    protected $_ticket;

    public function get_cas_version(){
        return $this->get_param('casversion', '2.0');
    }

    /**
     * @deprecated
     * @param boolean $gw
     */
    public function redirtocas($gw = false) {
        $this->redir_to_login($gw);
    }

    /**
     * @return boolean
     */
    public function read_code() {
        $ticket = (isset($_GET['ticket']) ? $_GET['ticket'] : null);
        if (\preg_match('/^[SP]T-/', $ticket) ) {
            unset($_GET['ticket']);
            $this->_ticket = $ticket;
            return true;
        } else {
            // Pas de ticket.
            unset($this->_ticket);
            return false;
        }
    }

    /**
     * @deprecated
     * @return boolean|\stdClass
     */
    public function validateorredirect() {
        if ($this->read_code()) {
            return $this->get_user();
        } else {
            $this->redir_to_login();
        }
    }

    public function get_user() {
        $this->_error = '';
        if (!isset($this->_ticket)) {
            $this->_error = 'Erreur.';
            return false;
        }

        $valurl  = $this->buildvalidateurl()->out(false);
        $cu = new \curl();
        if ($this->allow_untrust()) {
            $cu->setopt(['SSL_VERIFYHOST' => false]);
            $cu->setopt(['SSL_VERIFYPEER' => false]);
        }
        $maxretries = $this->_params['retries'];
        $maxretries = $this->get_param('retries', 0);
        $retries = 0;

        do {
            if ($rep = $cu->get($valurl)) {
                // Create new DOMDocument object.
                $dom = new \DOMDocument();
                // ... fix possible whitspace problems.
                $dom->preserveWhiteSpace = false;
                // CAS servers should only return data in utf-8.
                $dom->encoding = "utf-8";
                // Read the response of the CAS server into a DOMDocument object.
                if ( !($dom->loadXML($rep))) {
                    // Read failed.
                    $this->_error = 'Réponse du serveur CAS incorrecte';
                    return false;
                } else if (!($tree_response = $dom->documentElement)) {     // Read the root node of the XML tree.
                    // Read failed.
                    $this->_error = 'Réponse du serveur CAS incorrecte';
                    return false;
                } else if ($tree_response->localName != 'serviceResponse') {
                    // Insure that tag name is 'serviceResponse'
                    // bad root node.
                    $this->_error = 'Réponse du serveur CAS incorrecte';
                    return false;
                } else if ($tree_response->getElementsByTagName("authenticationSuccess")->length != 0) {
                    // Authentication succeded, extract the user name.
                    $success_elements = $tree_response->getElementsByTagName("authenticationSuccess");
                    if ($success_elements->item(0)->getElementsByTagName("user")->length == 0) {
                        // No user specified => error.
                        $this->_error = 'Réponse du serveur CAS incorrecte';
                        return false;
                    } else {
                        $attr = new \stdClass();
                        $attr->raw = $rep;
                        $attr->user = \trim(
                            $success_elements->item(0)->getElementsByTagName("user")->item(0)->nodeValue
                            );
                        if (\array_key_exists('decodecallback', $this->_params)
                                && \is_callable($this->_params['decodecallback'], false)) {
                            \call_user_func($this->_params['decodecallback'], $attr, $success_elements);
                        }
                        $attr->retries = $retries;
                        return $attr;
                    }
                }
            } else {
                $this->_error = 'Impossible de contacter le serveur CAS';
                return false;
            }
        } while ($retries++ < $maxretries);

        $this->_error = 'Ticket non validé';
        return false;
    }

    public function build_login_url() {
        $gw = $this->get_param('gw', false);
        $service = new \moodle_url($this->_clienturl, ['state' => $this->get_encoded_state()]);
        $param = ['service' => $service->out(false)];
        if ($gw) {
            $param['gateway'] = 'true';
        }
        return new \moodle_url($this->BuildServerBaseURL().'login', $param);
    }

    /**
     * @return \moodle_url
     */
    protected function buildvalidateurl() {
        $ret = $this->BuildServerBaseURL();
        switch ($this->get_cas_version()) {
            case '1.0':
                $ret .= 'validate';
                break;
            case '2.0':
                $ret .= 'serviceValidate';
                break;
            case '3.0':
                $ret .= 'p3/serviceValidate';
                break;
            default:
                $ret .= 'serviceValidate';
                break;
        }
        $service = new \moodle_url($this->_clienturl, ['state' => self::get_raw_query_state()]);
        $param = ['service' => $service->out(false), 'ticket' => $this->_ticket];
        return new \moodle_url($ret, $param);
    }
}
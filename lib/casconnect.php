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
 * Anobody can login with any password.
*
* @package auth_entsync
* @copyright 2016 Thomas Jaisson
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
*/

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/filelib.php');

class auth_entsync_casconnect {
    
    /**
     * @var string|null Null if ok, error msg otherwise
     */
    protected $_error;
    
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
     * @var array
     */
    protected $_casparams;
    
    /**
     * @var moodle_url
     */
    protected $_clienturl;
    
    protected $_ticket;

    /**
     * Constructor.
     */
    public function __construct() {
    }
    
    public function set_param($casparams) {
        $this->_casparams = $casparams;
        if(! array_key_exists('retries', $this->_casparams)) $this->_casparams['retries'] = 0;
        if(! array_key_exists('casversion', $this->_casparams)) $this->_casparams['casversion'] = '2.0';
        if(! array_key_exists('port', $this->_casparams)) $this->_casparams['port'] = 443;
        if(! array_key_exists('supportGW', $this->_casparams)) $this->_casparams['supportGW'] = false;
        if(! array_key_exists('allowUntrust', $this->_casparams)) $this->_casparams['allowUntrust'] = false;
        if(! array_key_exists('homeuri', $this->_casparams)) $this->_casparams['homeuri'] = '/';
        if(! array_key_exists('homehost', $this->_casparams))
            $this->_casparams['homehost'] = $this->_casparams['hostname'];
    }
    
    public function support_gw() {
    	return $this->_casparams['supportGW'];
    }
    
    public function allow_Untrust() {
    	return $this->_casparams['allowUntrust'];
    }
    
    public function  redirtocas($gw = false) {
//        redirect($this->buildloginurl($gw));
        self::_redirect($this->buildloginurl($gw));
    }
    
    public function  redirtohome() {
//        redirect('https://' . $this->_casparams['homehost'] . $this->_casparams['homeuri']);
    	self::_redirect('https://' . $this->_casparams['homehost'] . $this->_casparams['homeuri']);
    }
    
    /**
     * @return boolean
     */
    public function read_ticket() {
        $ticket = (isset($_GET['ticket']) ? $_GET['ticket'] : null);
        if (preg_match('/^[SP]T-/', $ticket) ) {
            unset($_GET['ticket']);
            $this->_ticket = $ticket;
            return true;
        } else {
            //pas de ticket
            unset($this->_ticket);
            return false;
        }
    }
    
    /**
     * Get last error
     *
     * @return string error text of null if none
     */
    public function get_error() {
    	return $this->_error;
    }

    public function validateorredirect() {
        if($this->read_ticket()) {
            return $this->validate_ticket();
        } else {
            $this->redirtocas();
        }
    }
    
    public function validate_ticket() {
        $this->_error = '';
        if(!isset($this->_ticket)) {
            $this->_error = 'Erreur.';
            return false;
        }
        
        $valurl  = $this->buildvalidateurl()->out(false);
        
        $cu = new curl();
        
        if($this->allow_Untrust()) {
        	$cu->setopt(['SSL_VERIFYHOST' => false]);
        	$cu->setopt(['SSL_VERIFYPEER' => false]);
        }
        
        $maxretries = $this->_casparams['retries'];
        $retries = 0;
        
        do {
            if($rep = $cu->get($valurl)) {
                //*********************
                // create new DOMDocument object
                $dom = new DOMDocument();
                // Fix possible whitspace problems
                $dom->preserveWhiteSpace = false;
                // CAS servers should only return data in utf-8
                $dom->encoding = "utf-8";
                // read the response of the CAS server into a DOMDocument object
                if ( !($dom->loadXML($rep))) {
                    // read failed
                    $this->_error = 'Réponse du serveur CAS incorrecte';
                    return false;
                } else if ( !($tree_response = $dom->documentElement) ) {
                    // read the root node of the XML tree
                    // read failed
                    $this->_error = 'Réponse du serveur CAS incorrecte';
                    return false;
                } else if ($tree_response->localName != 'serviceResponse') {
                    // insure that tag name is 'serviceResponse'
                    // bad root node
                    $this->_error = 'Réponse du serveur CAS incorrecte';
                    return false;
                } else if ($tree_response->getElementsByTagName("authenticationSuccess")->length != 0) {
                    // authentication succeded, extract the user name
                    $success_elements = $tree_response
                        ->getElementsByTagName("authenticationSuccess");
                    if ( $success_elements->item(0)->getElementsByTagName("user")->length == 0) {
                        // no user specified => error
                        $this->_error = 'Réponse du serveur CAS incorrecte';
                        return false;
                    } else {
                        $attr = new stdClass();
                        $attr->user = trim(
                                $success_elements->item(0)->getElementsByTagName("user")->item(0)->nodeValue
                                );
                        if(array_key_exists('decodecallback', $this->_casparams)
                            && is_callable($this->_casparams['decodecallback'], false)) {
                                call_user_func($this->_casparams['decodecallback'], $attr, $success_elements);
                        }
                        $attr->retries = $retries;
                        return $attr;
                    }
                }
                //*********************
            } else {
                $this->_error = 'Impossible de contacter le serveur CAS';
                return false;
            }
        } while($retries++ < $maxretries);
    
        $this->_error = 'Ticket non validé';
        return false;
    }
    
    public function buildloginurl($gw = false)
    {
        $param = ['service' => $this->_clienturl->out(false)];
        if($gw) $param['gateway'] = 'true';
        return new moodle_url($this->_getServerBaseURL().'login', $param);
    }
    
    /**
     * @return moodle_url
     */
    protected function buildvalidateurl() {
        $ret =  $this->_getServerBaseURL();       
        switch ($this->_casparams['casversion']) {
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
        $param = ['service' => $this->_clienturl->out(false), 'ticket' => $this->_ticket];
        return new moodle_url($ret, $param);
    }

    protected function _getServerBaseURL()
    {
        $ret = 'https://' . $this->_casparams['hostname'];
        if ($this->_casparams['port']!=443) {
            $ret .= ':' . $this->_casparams['port'];
        }
        $ret .= $this->_casparams['baseuri'];
        return $ret;
    }

    /**
     * @param moodle_url $url
     */
    public function set_clienturl($url) {
        $this->_clienturl = $url;
        
    }

    protected static function _redirect($url) {
        redirect($url);
    }

}
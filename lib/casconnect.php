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
     * Constructor.
     */
    public function __construct() {
    }
    
    public function set_param($casparams) {
        $this->_casparams = $casparams;
        if(! array_key_exists('retries', $this->_casparams)) $this->_casparams['retries'] = 0;
        if(! array_key_exists('casversion', $this->_casparams)) $this->_casparams['casversion'] = '2.0';
        if(! array_key_exists('port', $this->_casparams)) $this->_casparams['port'] = 443;
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
        $this->_error = '';
        $ticket = (isset($_GET['ticket']) ? $_GET['ticket'] : null);
        if (preg_match('/^[SP]T-/', $ticket) ) {
            unset($_GET['ticket']);
        } else {
            //pas de ticket, on redirige
            redirect($this->buildloginurl());
            return false;
        }
        
        $valurl  = $this->buildvalidateurl($ticket);
        
        $cu = new curl();
        
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
    
    public function buildloginurl()
    {
        return $this->_buildQueryUrl($this->_getServerBaseURL().'login','service='.urlencode($this->getURL()));
    }
    
    protected function buildvalidateurl($ticket) {
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
                return false;
        }
        $ret  = $this->_buildQueryUrl($ret, 'service='.urlencode($this->getURL()));
        $ret .= '&ticket=' . urlencode($ticket);
        return $ret;
    }

    protected function getURL()
    {
            $final_uri = '';
            // remove the ticket if present in the URL
            $final_uri = ($this->_isHttps()) ? 'https' : 'http';
            $final_uri .= '://';

            $final_uri .= $this->_getClientUrl();
            
            //hack
            if (isset($_SERVER['REQUEST_URI'])) {
                $_REQUEST_URI = $_SERVER['REQUEST_URI'];
            } else {
                $_REQUEST_URI = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
            }
            
            
            $request_uri	= explode('?', $_REQUEST_URI, 2);
            $final_uri		.= $request_uri[0];

            if (isset($request_uri[1]) && $request_uri[1]) {
                $query_string= $this->_removeParameterFromQueryString('ticket', $request_uri[1]);

                // If the query string still has anything left,
                // append it to the final URI
                if ($query_string !== '') {
                    $final_uri	.= "?$query_string";
                }
            }
            return $final_uri;
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

    protected function _buildQueryUrl($url, $query)
    {
        $url .= (strstr($url, '?') === false) ? '?' : '&';
        $url .= $query;
        return $url;
    }
    
    protected function _isHttps()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        }
        if ( isset($_SERVER['HTTPS'])
            && !empty($_SERVER['HTTPS'])
            && strcasecmp($_SERVER['HTTPS'], 'off') !== 0
            ) {
                return true;
            } else {
                return false;
            }
    }
    
    protected function _getClientUrl()
    {
        $server_url = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            // explode the host list separated by comma and use the first host
            $hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
            // see rfc7239#5.3 and rfc7230#2.7.1: port is in HTTP_X_FORWARDED_HOST if non default
            return $hosts[0];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_SERVER'])) {
            $server_url = $_SERVER['HTTP_X_FORWARDED_SERVER'];
        } else {
            if (empty($_SERVER['SERVER_NAME'])) {
                $server_url = $_SERVER['HTTP_HOST'];
            } else {
                $server_url = $_SERVER['SERVER_NAME'];
            }
        }
        if (!strpos($server_url, ':')) {
            if (empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
                $server_port = $_SERVER['SERVER_PORT'];
            } else {
                $ports = explode(',', $_SERVER['HTTP_X_FORWARDED_PORT']);
                $server_port = $ports[0];
            }
    
            if ( ($this->_isHttps() && $server_port!=443)
                || (!$this->_isHttps() && $server_port!=80)
                ) {
                    $server_url .= ':';
                    $server_url .= $server_port;
                }
        }
        return $server_url;
    }

    protected function _removeParameterFromQueryString($parameterName, $queryString)
    {
        $parameterName	= preg_quote($parameterName);
        return preg_replace(
            "/&$parameterName(=[^&]*)?|^$parameterName(=[^&]*)?&?/",
            '', $queryString
            );
    }
    
    
    
}
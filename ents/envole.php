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
 * Définition des ents de l'académie de paris
 *
 * -> monlycee.net
 * -> pcn
 * -> open ent ng
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * scribe/envole
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class  auth_entsync_ent_envole extends auth_entsync_entcas {
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = 'Scribe/Envole';
        $this->nomlong = 'Scribe/Envole';
    }
    
    public function get_casparams() {
        $cp = [
        'baseuri' => '/',
        'port' => 8443,
        'supportGW' => true,
        'allowUntrust' => true
        ];
        $settings = $this->get_settings();
        $hn = $settings['cashost'];
        $cp['hostname'] = $hn;
        return $cp;
        
    }
    
    public function get_profileswithcohorts() {
        return [1];
    }

    public function get_filetypes() {
        return [
            1 => 'Élèves (fichier CSV)',
            2 => 'Enseignants (fichier CSV)',
        ];
    }

    public function accept_multifile($filetype) {
        return false;
    }
    
    public function get_profilesintype($filetype) {
        switch($filetype) {
            case 1 : return  [1];
            case 2 : return  [2];
        }
        return  [];
    }

    public function get_fileparser($filetype) {
        if( ($filetype < 1) || ($filetype>2)) return null; 
        require_once(__DIR__ . '/../lib/parsers.php');
        $fileparser = new auth_entsync_parser_CSV();
        $fileparser->match = ['lastname'=>'NOM', 'firstname'=>'PRENOM', 'uid'=>'LOGIN'];
        if($filetype == 1) $fileparser->match['cohortname'] = 'CLASSE';
        $fileparser->encoding = 'utf-8';
        $fileparser->delim = ';';
        $fileparser->set_validatecallback(partial([$this, 'validate'], $filetype));
        return $fileparser;
    }
    
    public function validate($profile, $record) {
        $record->profile = $profile;
        return true;
    }

    public function has_settings() {
    	return true;
    }
    
    public function settings() {
    	return [
    			(object)['name' => 'cashost', 'default' => ''],
    	];
    }
    
    public function add_formdef($mform) {
    	$prfx = "ent({$this->_code})_";
    	$elemname = $prfx . 'cashost';
    	$mform->addElement('text', $elemname, get_string('envolecashost', 'auth_entsync'));
    	$mform->setType($elemname, PARAM_RAW);
    	$mform->addHelpButton($elemname, 'envolecashost', 'auth_entsync');
    }
    
    public function get_casconnector() {
    	$conf = $this->get_settings();
    	if($conf['cashost'] == '') return false;
    	return parent::get_casconnector();
    }
    
}

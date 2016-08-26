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
 * Définition de l'ent Educ Horus
 *
 * -> lilie (bientôt monlycee.net)
 * -> pcn
 * -> open ent ng
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * -> lilie (bientôt monlycee.net)
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class  auth_entsync_ent_educhorus extends auth_entsync_entcas {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = 'Educ\'Horus';
        $this->nomlong = 'Educ\'Horus';
    }

    public function get_casparams() {
        $cp = [
            'retries' => 20
        ];
        //TODO : déterminer le RNE.
        $settings = $this->get_settings();
        $hn = $settings['cashost'];
        $uri = $settings['caspath'];
        $cp['hostname'] = $hn;
        $cp['baseuri'] = "/{$uri}/cas/";
        $cp['decodecallback'] = [$this, 'decodecallback'];
        return $cp;
    }
    
    public function decodecallback($attr, $elem) {
        $attr->user = core_text::strtolower($attr->user);
    }

    public function get_profileswithcohorts() {
        return [1];
    }

    public function get_filetypes() {
        return [
            1 => 'Élèves (fichier CSV)',
            2 => 'Enseignants (fichier CSV)',
            3 => 'Utilisateurs (élèves & enseignants) (fichier CSV)',
        ];
    }

    public function get_profilesintype($filetype) {
        switch($filetype) {
            case 1 : return  [1];
            case 2 : return  [2];
            case 3 : return  [1, 2];
        }
        return  [];
    }

    public function get_fileparser($filetype) {
        if(($filetype < 1) || ($filetype > 3)) return null;
        require_once(__DIR__ . '/../lib/parsers.php');
        $fileparser = new auth_entsync_parser_CSV();
        $fileparser->match = ['lastname'=>'Nom', 'firstname'=>'Prénom',
                            'uid'=>'Login', 'cohortname' => 'Classes', 'prf' =>'Profil'];
        $fileparser->encoding = 'ISO-8859-1';
        $fileparser->delim = ';';
        $fileparser->set_validatecallback(partial([$this, 'validate'], $this->get_profilesintype($filetype)));
        return $fileparser;
    }
    
    public function validate($profiles, $record) {
        global $DB;
        switch($record->prf) {
            case 'professeur' :
                $profile = 2;
                unset($record->cohortname);
                break;
            case 'eleve' :
                $profile = 1;
                break;
            default:
                $profile = -1;
        }
        if(!in_array($profile, $profiles)) return false;
        $record->uid = core_text::strtolower($record->uid);
        //il ne devrait pas y avoir deux fois le même uid, mais bon... mieux vaut tester.
        if($DB->record_exists('auth_entsync_tmpul',
            ['uid' => $record->uid, 'profile' => $profile])) return false;
        $record->profile = $profile;
        unset($record->prf);
        return true;
    }
    
    public function has_settings() {
       return true;
    }
    
    public function settings() {
        return [
            (object)['name' => 'cashost', 'default' => 'educhorus.enteduc.fr'],
            (object)['name' => 'caspath', 'default' => '']
        ];
    }
    
    public function add_formdef($mform) {
        $prfx = "ent({$this->_code})_";
        $elemname = $prfx . 'cashost';
        $mform->addElement('text', $elemname, get_string('educhoruscashost', 'auth_entsync'));
        $mform->setType($elemname, PARAM_RAW);
        $mform->addHelpButton($elemname, 'educhoruscashost', 'auth_entsync');
        
        $elemname = $prfx . 'caspath';
        $mform->addElement('text', $elemname, get_string('educhoruscaspath', 'auth_entsync'));
        $mform->setType($elemname, PARAM_RAW);
        $mform->addHelpButton($elemname, 'educhoruscaspath', 'auth_entsync');
        
    }
}

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
 * monlycee.net
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class  auth_entsync_ent_monlyceenet extends auth_entsync_entcas {
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = 'monlycée.net';
        $this->nomlong = 'monlycée.net';
    }
    
    public function get_casparams() {
        return [
        'hostname' => 'ent.iledefrance.fr',
        'baseuri' => '/connexion/',
        'supportGW' => true
        ];
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
        return true;
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
        $fileparser = new \auth_entsync\parsers\csv_parser();
        $fileparser->match = ['lastname'=>'Nom', 'firstname'=>'Prénom', 'uid'=>'Identifiant'];
        if($filetype == 1) $fileparser->match['cohortname'] = 'Classe';
        $fileparser->encoding = 'ISO-8859-1';
        $fileparser->delim = ';';
        $fileparser->set_validatecallback(partial([$this, 'validate'], $filetype));
        return $fileparser;
    }
    
    public function validate($profile, $record) {
        $record->profile = $profile;
        return true;
    }
}

/**
 * -> pcn
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class  auth_entsync_ent_pcn extends auth_entsync_entcas {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = 'PCN';
        $this->nomlong = 'PCN';
    }
    
    public function get_casparams() {
        return [
        'hostname' => 'www.parisclassenumerique.fr',
        'baseuri' => '/connexion/',
        'supportGW' => true
        ];
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

    public function get_profilesintype($filetype) {
        switch($filetype) {
            case 1 : return  [1];
            case 2 : return  [2];
        }
        return  [];
    }

    public function get_fileparser($filetype) {
        if( ($filetype < 1) || ($filetype>2)) return null; 
        $fileparser = new \auth_entsync\parsers\csv_parser();
        $fileparser->match = ['lastname'=>'Nom', 'firstname'=>'Prénom', 'uid'=>'Identifiant', 'prf' =>'Profil'];
        if($filetype == 1) $fileparser->match['cohortname'] = 'Classe';
        $fileparser->encoding = 'ISO-8859-1';
        $fileparser->delim = ';';
        $fileparser->set_validatecallback(partial([$this, 'validate'], $this->get_profilesintype($filetype)));
        return $fileparser;
    }

    public function validate($profiles, $record) {
        global $DB;
        switch($record->prf) {
            case 'Enseignant' :
                $profile = 2;
                unset($record->cohortname);
                break;
            case 'Elève' :
                $profile = 1;
                break;
            default:
                $profile = -1;
        }
        if(!in_array($profile, $profiles)) return false;
        $record->profile = $profile;
        unset($record->prf);
        return true;
    }
}

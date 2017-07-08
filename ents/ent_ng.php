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
 * -> open ent ng
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class auth_entsync_entng extends auth_entsync_entcas {
    public function can_switch() {
        return true;
    }
    
    public function get_casparams() {
        return [
                        'baseuri' => '/cas/',
                        'homeuri' => '/',
                        'supportGW' => false,
                        'decodecallback' => [$this, 'decodecallback']
        ];
    }
    
    public function decodecallback($attr, $elem) {
        //ENTPersonStructRattachRNE
        $attr->rnes = [];
        $rnenodelist = $elem->item(0)->getElementsByTagName("ENTPersonStructRattachRNE");
        foreach($rnenodelist as $rnenode) {
            $attr->rnes[] = $rnenode->nodeValue;
        }
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
        if( ($filetype < 1) || ($filetype>3)) return null;
        $fileparser = new \auth_entsync\parsers\csv_parser();
        $fileparser->match = ['lastname'=>'Nom', 'firstname'=>'Prénom',
                        'uid'=>'Login', 'cohortname' => 'Classe(s)', 'prf' =>'Type'];
        $fileparser->encoding = 'utf-8';
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
            case 'Élève' :
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

class  auth_entsync_ent_ng extends auth_entsync_entng {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = get_string('entng_sd', 'auth_entsync');
        $this->nomlong = get_string('entng', 'auth_entsync');
    }
    
    public function get_casparams() {
        $cp = parent::get_casparams();
        $cp['hostname'] = 'ent-ng.paris.fr';
        return $cp;
    }
}

class  auth_entsync_ent_ngcrif extends auth_entsync_entng {
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = get_string('entngcrif_sd', 'auth_entsync');
        $this->nomlong = get_string('entngcrif', 'auth_entsync');
    }
    
    public function get_casparams() {
        $cp = parent::get_casparams();
        $cp['hostname'] = 'prodng.ent.iledefrance.fr';
        return $cp;
    }

    public function accept_multifile($filetype) {
        return true;
    }
}

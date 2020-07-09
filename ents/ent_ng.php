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

defined('MOODLE_INTERNAL') || die();

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
    public function can_onthefly() {
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
    protected static function typesToProfile($types) {
        if (empty($types)) return 0;
        $flag = 0;
        foreach ($types as $t) {
            switch (strtolower($t)) {
                case student:
                    $flag |= 1;
                    break;
                case teacher:
                    $flag |= 2;
                    break;
                case personnel:
                    $flag |= 4;
                    break;
            }
        }
        if ($flag & 1) return 1;
        if ($flag & 2) return 2;
        if ($flag & 4) return 4;
        return 0;
    }
    public function decodecallback($attr, $elem) {
        $elem = $elem->item(0);
        $attr->rnes = [];
        if(false !== ($val = self::xmlget($elem, 'structureNodes'))) {
            $structs = json_decode($val);
            foreach ($structs as $struct) {
                $attr->rnes[] = $struct->UAI;
            }
        }
        $attr->uid = self::xmlget($elem, 'externalId');
        $attr->lastname = self::xmlget($elem, 'lastName');
        $attr->firstname = self::xmlget($elem, 'firstName');
        if (false !== ($val = self::xmlget($elem, 'type'))) {
            $attr->profile = self::typesToProfile(json_decode($val));
        } else {
            $attr->profile = 0;
        }
        if (1 === $attr->profile) {
            if ((false !== ($val = self::xmlget($elem, 'classes'))) &&
                (is_array($classes = json_decode($val))) &&
                (1 === count($classes))) {
                $classe = array_pop($classes);
                $parts = explode('$', $classe, 2);
                $attr->classe = (count($parts) === 2) ? $parts[1] : $classe;
            } else {
                $attr->classe = null;
            }
        }
    }
    public function get_profileswithcohorts() {
        return [1];
    }
    
    public function get_filetypes() {
        //return [];
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
        $fileparser->match = ['lastname'=>'Nom', 'firstname'=>'Prénom', 'user'=>'Login',
                        'uid'=>'Id', 'cohortname' => 'Classe(s)', 'prf' =>'Type'];
        $fileparser->encoding = 'utf-8';
        $fileparser->delim = ';';
        $fileparser->set_validatecallback(partial([$this, 'validate'], $this->get_profilesintype($filetype)));
        return $fileparser;
    }

    public function validate($profiles, $record) {
        global $DB;
        $prf = \auth_entsync\helpers\stringhelper::simplify_name($record->prf);
        switch($prf) {
            case 'enseignant' :
                $profile = 2;
                unset($record->cohortname);
                break;
            case 'eleve' :
                $profile = 1;
                if (false !== strpos($record->cohortname, ','))
                    $record->cohortname = false;
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
        $cp['hostname'] = 'ent.parisclassenumerique.fr';
        return $cp;
    }

    public function get_connector_url() {
        return 'Connecteur prédéfini dans l\'ENT';
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
        $cp['hostname'] = 'ent.iledefrance.fr';
        return $cp;
    }

    public function accept_multifile($filetype) {
        return true;
    }
    
    public function get_connector_url() {
        return 'Connecteur prédéfini dans l\'ENT';
    }
}

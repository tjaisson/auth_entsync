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
 * Définition du pseudo ent pour gérer les comptes locaux
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * -> comptes locaux
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class  auth_entsync_ent_local extends auth_entsync_ent_base {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->nomcourt = 'Local';
        $this->nomlong = 'Comptes locaux';
    }
    
    public function get_mode() {
        return 'local';
    }
    public function get_profileswithcohorts() {
        return [1];
    }
    
    public function get_filetypes() {
        return [
            1 => 'Élèves BEE (fichier xml ou zip)',
            2 => 'Enseignants STS (fichier xml ou zip)',
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
        require_once(__DIR__ . '/../lib/parsers.php');
        switch($filetype) {
            case 1 : return new auth_entsync_parser_bee();
            case 2 : return new auth_entsync_parser_sts();
        }
        return  null;
    }
    
    public function get_connector_url() {
        return "";
    }

    public function accept_multifile($filetype) {
        return true;
    }
    
}
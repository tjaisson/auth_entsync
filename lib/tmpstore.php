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
 * Classe pour gérer le stockage temporaire des utilisateurs importés
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

abstract class auth_entsync_tmpstore {
    protected $_progressreporter = null;
    public function set_progress_reporter($progressreporter) {
        $this->_progressreporter = $progressreporter;
    }
    
    public function __construct($storecode = null) {}
    public static function get_store($storecode = null) {
        return new auth_entsync_dbtmpstore($storecode);
    }
    public abstract function save();
    public abstract function add_ius($ius);
    public abstract function get_ius();
    public abstract function clear();
    public abstract function count();
}

class auth_entsync_dbtmpstore extends auth_entsync_tmpstore {
    protected $_storecode;
    public function __construct($storecode = null) {
        global $DB;
        if($storecode === null) {
            $DB->delete_records('auth_entsync_tmpul');
            $this->_storecode = 1;
        } else {
            $this->_storecode = $storecode;
        }
    }
    public function save() {return $this->_storecode;}
    public function add_ius($ius) {
        global $DB;
        if(empty($ius)) return;
        $this->_progressreporter->start_progress('Préparation',count($ius),1);
        $i = 1;
        while($ius) {
            $iu = array_pop($ius);
            $this->_progressreporter->progress($i++);
            if(!$DB->record_exists('auth_entsync_tmpul', ['uid' => $iu->uid])) {
                $DB->insert_record('auth_entsync_tmpul', $iu);
            }
        }
        $this->_progressreporter->end_progress();
    }
    public function get_ius() {
        global $DB;
        return $DB->get_records('auth_entsync_tmpul');
    }
    public function clear() {
        global $DB;
        $DB->delete_records('auth_entsync_tmpul');
    }
    public function count() {
        global $DB;
        return $DB->count_records('auth_entsync_tmpul');
    }
}
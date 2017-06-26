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

namespace auth_entsync\tmpstores;
defined('MOODLE_INTERNAL') || die();

abstract class base_tmpstore {
    protected $_progressreporter = null;
    public function set_progress_reporter($progressreporter) {
        $this->_progressreporter = $progressreporter;
    }
    
    public function __construct($storecode = null) {}
    public static function get_store($storecode = null) {
        return new \auth_entsync\tmpstores\fs_tmpstore($storecode);
    }
    public abstract function save();
    public abstract function add_ius($ius);
    public abstract function get_ius();
    public abstract function clear();
    public abstract function count();
}

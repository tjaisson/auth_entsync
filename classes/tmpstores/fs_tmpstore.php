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

class fs_tmpstore extends \auth_entsync\tmpstores\base_tmpstore {
    protected $_storecode;
    protected $_tmparray;

    public function __construct($storecode = null) {
        $this->_storecode = $storecode;
        if ($storecode) {
            $dir = \make_temp_directory('entsync');
            $file = $dir . '/' . $storecode;
            $this->_tmparray = (array)\json_decode(file_get_contents($file));
        } else  {
            $this->_tmparray = array();
        }
    }
    public function save() {
        $dir = \make_temp_directory('entsync');
        if ($this->_storecode) {
            $file = $dir . '/' . $this->_storecode;
        } else  {
            $i = 100;
            while(\file_exists($dir . '/' . $i)) {
                ++$i;
            }
            $this->_storecode = $i;
            $file = $dir . '/' . $this->_storecode;
        }
        \file_put_contents($file, \json_encode($this->_tmparray));
        return $this->_storecode;
    }
    public function add_ius($ius) {
        if (empty($ius)) return;
        $i = 1;
        while ($ius) {
            $iu = \array_pop($ius);
            $this->_tmparray[$iu->uid] = $iu;
        }
    }
    public function get_ius() {
        return $this->_tmparray;
    }
    public function clear() {
        if ($this->_storecode) {
            $dir = \make_temp_directory('entsync');
            $file = $dir . '/' . $this->_storecode;
            unlink($file);
        }
    }
    public function count() {
        return \count($this->_tmparray);
    }
}

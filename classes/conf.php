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
 *
 * Conf service
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace auth_entsync;
defined('MOODLE_INTERNAL') || die;
/**
 * Service to retrieve plugin configuration.
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conf {
    protected $_pn;
    protected $_pamroot;
    protected $_inst;
    protected $_gw;
    protected $_isgw;
    public function  __construct($pn) {
        $this->_pn = $pn;
    }
    public function pamroot() {
        if (!isset($this->_pamroot)) {
            $this->_pamroot = \get_config($this->_pn, 'pamroot');
        }
        return $this->_pamroot;
    }
    public function inst() {
        if (!isset($this->_inst)) {
            $this->_inst = \get_config($this->_pn, 'inst');
        }
        return $this->_inst;
    }
    public function gw() {
        if (!isset($this->_gw)) {
            $this->_gw = \get_config($this->_pn, 'gw');
        }
        return $this->_gw;
    }
    public function is_gw() {
        if (!isset($this->_isgw)) {
            $this->_isgw = ($gw = $this->gw()) ? ($gw === $this->inst()) : false;
        }
        return $this->_isgw;
    }
    public function sharedir() {
        if (!isset($this->_sharedir)) {
            $this->_sharedir = \get_config($this->_pn, 'sharedir');
        }
        return $this->_sharedir;
    }
}
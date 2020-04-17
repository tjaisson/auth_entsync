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
 * Service factory
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace auth_entsync;
defined('MOODLE_INTERNAL') || die;
/**
 * Class to call api.
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    const APIENTRY = '/auth/entsync/api.php';
    protected $CFG;
    protected $conf;
    protected $iic;
    protected $curl = null;
    protected function  __construct($conf, $iic, $CFG) {
        $this->conf = $conf;
        $this->iic = $iic;
        $this->CFG = $CFG;
    }
    public function get($func, $params, $target = null) {
        if (null === $target) $target = $this->conf->gw();
        $curl = $this->getCurl($target);
        $rep = $curl->get($this->serverURL($target), $params);
        if (0 === $cu->get_errno()) return json_decode($rep);
        return false;
    }
    public function post($func, $params, $target = null) {
        return null;
    }
    public function put($func, $params, $target = null) {
        return null;
    }
    protected function getCurl($target) {
        if (null == $this->curl) {
            require_once($this->CFG->libdir.'/filelib.php');
            $this->curl = new \curl();
        }
        $this->curl->resetopt();
        $this->curl->resetHeader();
        $k = $this->iic->getCrkey();
        $tk = $k->seal($this->conf->inst(), $target);
        $this->curl->setHeader('Authorization: IIC ' . $tk);
        return $this->curl;
    }
    protected function serverURL($target) {
        return $this->conf->pamroot() . '/' . $target;
    }
}

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
    const APIENTRY = '/auth/entsync/api/iic.php';
    protected $http_client;
    protected $conf;
    protected $iic;
    public function  __construct($conf, $iic, $http_client) {
        $this->conf = $conf;
        $this->iic = $iic;
        $this->http_client = $http_client;
    }
    public function get($func, $params = null, $target = null) {
        if (null === $target) $target = $this->conf->gw();
        if (null === $params) $params = [];
        $params['func'] = $func;
        $auth = $this->buildTk($target);
        $url = $this->serverURL($target);
        $rep = $this->http_client->get($url, $params, $auth);
        if (false === $rep) return false;
        if (200 === $rep['status']) return json_decode($rep['content']);
        return false;
    }
    public function post($func, $params, $target = null) {
        return null;
    }
    public function put($func, $params, $target = null) {
        return null;
    }
    protected function buildTk($target) {
        $k = $this->iic->getCrkey();
        $tk = $k->seal($this->conf->inst(), $target);
        return 'IIC ' . $tk;
    }
    protected function serverURL($target) {
        return new \moodle_url($this->conf->pamroot() . '/' . $target . self::APIENTRY);
    }
}

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
 * Class to register and instantiate api services.
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_server {
    protected $c;
    protected $iic;
    protected $conf;
    protected function  __construct($conf, $iic, $c) {
        $this->c = $c;
        $this->iic = $iic;
        $this->conf = $conf;
        $this->registerService('instance', function ($c) {
            include_once(__dir__ . '/farm/instances_api.php');
            return new farm\instances_api($c->query('conf'), $c->query('instances'));
        });
    }
    public function handle() {
        $func = \required_param('func', \PARAM_TEXT);
        if ((empty($auth = $_SERVER["HTTP_AUTHORIZATION"])) ||
            ('IIC ' !== \substr($auth, 0, 4)) ||
            (empty($inst = $this->iic->open(\substr($auth, 4), $this->conf->inst()))))
            $this->error();
        list($service, $func) = \explode('.', $func);
        $s = $this->query($service);
        $httpmethod = \strtolower($_SERVER['REQUEST_METHOD']);
        $func = $httpmethod . '_' . $func;
        switch ($httpmethod) {
            case 'get':
                unset($_GET['func']);
                $s->set_params($inst, $_GET);
            break;
            case 'post':
            case 'put':
                unset($_POST['func']);
                $s->set_params($inst, $_POST);
            break;
        }
        return $s->{$func}();
    }
    protected function error() {
        throw new \moodle_exception('api error', 'auth_entsync');
    }
    protected static function get($n){
        return (self::services())->query($n);
    }
    protected static function services() {
        static $inst = null;
        if (null === $inst) {
            $inst = new self();
        }
        return $inst;
    }
    protected $_services = [];
    protected function registerService($n, $fm) {
        $this->_services[$n] = new api_service_factory($fm);
    }
    protected function query($n) {
        if (! ($s = @$this->_services[$n]))
            throw new \moodle_exception('unknown service', 'auth_entsync');
        return $s->get($this->c);
    }
}
class api_service_factory {
    protected $fm;
    public function __construct($fm) {
        $this->fm = $fm;
    }
    public function get($c) {
        if (null === $this->inst) {
            $this->inst = ($this->fm)($c);
        }
        return $this->inst;
    }
}
abstract class api_service {
    protected $params;
    protected $inst = null;
    public function set_params($inst, $params) {
        $this->params = $params;
        $this->params = $params;
    }
}

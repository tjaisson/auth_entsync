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
 * Class to register and instantiate services.
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class container {
    protected function  __construct() {
        $this->registerService('DB', function ($c) {
            global $DB;
            return $DB;
        });
        $this->registerService('CFG', function ($c) {
            global $CFG;
            return $CFG;
        });
        $this->registerService('conf', function ($c) {
            include_once(__dir__ . '/conf.php');
            return new conf(self::NAME);
        });
        $this->registerService('iic', function ($c) {
            include_once(__dir__ . '/farm/iic.php');
            return new farm\iic($c->query('conf'));
        });
        $this->registerService('instances', function ($c) {
            include_once(__dir__ . '/farm/instances.php');
            return new farm\instances();
        });
        $this->registerService('instance_form', function ($c) {
            include_once(__dir__ . '/forms/instance_form.php');
            return new forms\instance_form($c->query('instances'));
        });
        $this->registerService('api_server', function ($c) {
            include_once(__dir__ . '/api_server.php');
            return new api_server($c->query('conf'), $c->query('iic'), $c);
        });
        $this->registerService('api_client', function ($c) {
            include_once(__dir__ . '/api_client.php');
            return new api_client($c->query('conf'), $c->query('iic'), $c->query('http_client'));
        });
        $this->registerService('http_client', function ($c) {
            include_once(__dir__ . '/helpers/http_client.php');
            return new helpers\http_client();
        });
        $this->registerService('instance_info', function ($c) {
            include_once(__dir__ . '/farm/instance_info.php');
            return new farm\instance_info($c->query('api_client'));
        });
        $this->registerService('casconnect', function ($c) {
            include_once(__dir__ . '/connectors/casconnect.php');
            return new connectors\casconnect($c->query('http_client'));
        });
        $this->registerService('directory.entus', function ($c) {
            include_once(__dir__ . '/directory/entus.php');
            return new directory\entus($c->query('CFG'), $c->query('DB'), $c->query('conf'));
        });
    }
    public const NAME = 'auth_entsync';
    public static function get($n){
        return (self::services())->query($n);
    }
    public static function services() {
        static $inst = null;
        if (null === $inst) {
            $inst = new self();
        }
        return $inst;
    }
    protected $_services = [];
    protected function registerService($n, $fm) {
        $this->_services[$n] = new service_factory($fm);
    }
    public function query($n) {
        if (! ($s = @$this->_services[$n]))
            throw new \moodle_exception('unknown service', 'auth_entsync');
        return $s->get($this);
    }
}
class service_factory {
    protected $fm;
    protected $inst = null;
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
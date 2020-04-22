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
 * instances api.
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace auth_entsync\farm;
use auth_entsync\api_service;

defined('MOODLE_INTERNAL') || die;
/**
 * Class to handle instances API calls.
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instances_api extends api_service {
    protected $instances;
    public function __construct($instances) {
        $this->instances = $instances;
    }
    public function iic_get_rnes() {
        $index = $this->instances->instancesIndex();
        return $this->json_encode($index[$this->inst]['rnes']);
    }
    public function public_get_instances() {
        $cache = \cache::make('auth_entsync', 'farm');
        if (!false === ($json = $cache->get('instances_json'))) {
            return $json;
        }
        $json = $this->json_encode($this->instances->instances_list());
        $cache->set('instances_json', $json);
        return $json;
    }
    public function mdl_get_instances() {
        $this->requireSiteAdmin();
        $json = $this->json_encode($this->instances->instances_list(true));
        return $json;
    }
}

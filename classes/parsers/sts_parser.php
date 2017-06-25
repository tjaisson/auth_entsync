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
 * files parsers (csv xml)
 *
 * TODO : implémenter les parsers xml pour siècle
 *
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\parsers;
defined('MOODLE_INTERNAL') || die();


/**
 * Classe pour parser les XML STS.
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sts_parser extends \auth_entsync\parsers\xml_parser {
    private $match = ['lastname' => 'NOM_USAGE', 'firstname' => 'PRENOM', 'fct' => 'FONCTION'];

    public function on_open($parser, $name, $attribs) {
        switch ($name) {
            case 'INDIVIDU' :
                $this->_record = new \stdClass();
                $this->_record->uid = $attribs['ID'];
                return;
        }

        if (isset($this->_record)) {
            if ($key = \array_search($name, $this->match)) {
                $this->_field = $key;
                $this->_record->{$key} = '';
            }
        }
    }

    public function on_close($parser, $name) {
        $this->_field = '';
        switch ($name) {
            case 'INDIVIDU' :
                $this->add_iu($this->_record);
                unset($this->_record);
                $this->_progressreporter->progress();
                return;
        }
    }

    protected function afterparse() {
        $lst = $this->_buffer;
        $this->_buffer = array();
        $this->_report->addedusers = 0;
        while ($lst) {
            $iu = \array_pop($lst);
            if (!empty($iu->fct) && ($iu->fct == 'ENS')) {
                unset($iu->fct);
                if (!empty($iu->firstname)) {
                    $iu->firstname = \trim($iu->firstname);
                }
                if (!empty($iu->lastname)) {
                    $iu->lastname = \trim($iu->lastname);
                }
                $iu->uid = 'STS.' . $iu->uid;
                $iu->profile = 2;
                ++$this->_report->addedusers;
                $this->_buffer[$iu->uid] = $iu;
            }
        }
    }
}
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
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\parsers;
defined('MOODLE_INTERNAL') || die();

/**
 * Classe pour parser les XML BEE.
 *
 * @todo Check TYPE_STRUCTURE and CODE_STRUCTURE === 'inactif'
 * @todo check document tree (eg parse ELEVE tag only if ELEVES tag was encountered)
 * @todo dont rely on ELEVES tag to always come before STRUCTURES tag. So has to fill $_buffer2 with associations
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bee_parser extends \auth_entsync\parsers\xml_parser {
    private $match1 = ['lastname' => 'NOM_DE_FAMILLE', 'firstname' => 'PRENOM'];
    private $match2 = ['cohortname' => 'CODE_STRUCTURE', 'type' => 'TYPE_STRUCTURE'];
    private $match;
    private $_record2;
    private $_buffer2;
    
    public function on_open($parser, $name, $attribs) {
        switch ($name) {
            case 'ELEVE' :
                $this->_record = new \stdClass();
                $this->_record->uid = $attribs['ELEVE_ID'];
                $this->match = $this->match1;
                return;
            case 'STRUCTURES_ELEVE' :
                unset($this->_record);
                $this->_record2 = new \stdClass();
                $this->_record2->uid = $attribs['ELEVE_ID'];
                return;
            case 'STRUCTURE' :
                if(isset($this->_record2)) {
                    $this->_record = new \stdClass();
                    $this->match = $this->match2;
                return;
                }
        }

        if (isset($this->_record)) {
            if ($key = array_search($name, $this->match)) {
                $this->_field = $key;
                $this->_record->{$key} = '';
            }
        }
    }

    public function on_close($parser, $name) {
        $this->_field = '';
        switch ($name) {
            case 'ELEVE' :
                $this->add_iu($this->_record);
                unset($this->_record);
                $this->_progressreporter->progress();
                return;
            case 'STRUCTURES_ELEVE' :
                if (\array_key_exists($this->_record2->uid, $this->_buffer)) {
                    $this->_buffer[$this->_record2->uid]->cohortname = $this->_record->cohortname;
                    $this->_progressreporter->progress();
                }
                unset($this->_record2);
                return;
            case 'STRUCTURE' :
                if (isset($this->_record2) && isset($this->_record) &&
                        isset($this->_record->type) && isset($this->_record->cohortname)) {
                    if ($this->_record->type === "D") {
                        $this->_record2->cohortname = $this->_record->cohortname;
                    }
                }
                unset($this->_record);
                return;
        }
    }

    protected function afterparse() {
        $lst = $this->_buffer;
        $this->_buffer = array();
        $this->_report->addedusers = 0;
        while ($lst) {
            $iu = \array_pop($lst);
            if (!empty($iu->cohortname)) {
                $iu->cohortname = \trim($iu->cohortname);
                if (!empty($iu->firstname)) {
                    $iu->firstname = \trim($iu->firstname);
                }
                if (!empty($iu->lastname)) {
                    $iu->lastname = \trim($iu->lastname);
                }
                $iu->uid = 'BEE.' . $iu->uid;
                $iu->profile = 1;
                ++$this->_report->addedusers;
                $this->_buffer[$iu->uid] = $iu;
            }
        }
    }
}

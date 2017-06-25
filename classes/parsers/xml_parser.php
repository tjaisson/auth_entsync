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
 * Classe de base pour parser les XML.
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class xml_parser extends \auth_entsync\parsers\base_parser {
    protected $_record;
    protected $_field = '';

    protected function do_parse($filename, $filecontent) {
        $this->_progressreporter->start_progress('lecture', 10);
        $_ext = \strtoupper(\pathinfo($filename, PATHINFO_EXTENSION));
        // Unzip si nécessaire.
        if ($_ext == 'ZIP') {
            $ret = $this->unzipone($filename, $filecontent);
            if (!$ret) {
                $this->_progressreporter->end_progress();
                return false;
            }
            list($filename, $filecontent) = $ret;
            $_ext = \strtoupper(\pathinfo($filename, PATHINFO_EXTENSION));
        } else {
            $this->_progressreporter->increment_progress();
        }

        // Ce doit être un xml.
        if ($_ext != 'XML') {
            $this->_error = 'Fichier xml requis';
            $this->_progressreporter->end_progress();
            return false;
        }
        $bf = $this->doparse($filecontent);
        $this->_progressreporter->end_progress();
        return $bf;
    }

    private function doparse($filecontent) {
        $this->_progressreporter->start_progress('lecture', \core\progress\display_if_slow::INDETERMINATE , 9);
        $parser = \xml_parser_create('UTF-8');
        \xml_set_element_handler($parser, [$this, 'on_open'], [$this, 'on_close']);
        \xml_set_character_data_handler($parser, [$this, 'on_data']);
        \xml_parse($parser, $filecontent, true);
        unset($filecontent);
        \xml_parser_free($parser);
        $this->afterparse();
        $this->_progressreporter->end_progress();
        return $this->_buffer;
    }

    public function on_data($parser, $data) {
        if (!empty($this->_field)) {
            if (isset($this->_record)) {
                $this->_record->{$this->_field} .= $data;
            }
        }
    }

    public abstract function on_open($parser, $name, $attribs);
    public abstract function on_close($parser, $name);
    protected function afterparse() {
    }
}

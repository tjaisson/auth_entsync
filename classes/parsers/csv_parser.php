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
 * Classe pour parser les fichiers CSV
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_parser extends \auth_entsync\parsers\base_parser {
    public $encoding;
    public $match;
    public $delim;
    protected function do_parse($filename, $filecontent) {
        $this->_progressreporter->start_progress('Lecture du fichier', 10, 1);
        $this->_progressreporter->start_progress('En tête', 1, 1);

        if (\strtoupper(\pathinfo($filename, PATHINFO_EXTENSION)) != 'CSV') {
            $this->_error = 'Fichier csv requis';
            $this->_progressreporter->end_progress();
            $this->_progressreporter->end_progress();
            return false;
        }

        $this->_error = null;
        $this->_parsedlines = 0;
        $this->_addedusers = 0;

        // On sépare les lignes.
        $lines = \preg_split("/\\r\\n|\\r|\\n/", $filecontent);
        unset($filecontent);

        $linessize = \count($lines);

        $this->_progressreporter->end_progress();
        if ($linessize < 2) {
            // Pas assez de lignes.
            $this->_error = "Le fichier $filename n'est pas lisible";
            $this->_progressreporter->end_progress();
            return false;
        }

        $this->_progressreporter->start_progress('En tête', 1, 1);

        // On retire le bom si nécessaire.
        $bom = \pack("CCC", 0xef, 0xbb, 0xbf);
        if (0 === \strncmp($lines[0], $bom, 3)) {
            $this->encoding = 'utf-8';
            $lines[0] = \substr($lines[0], 3);
        }

        // On lit les entêtes.
        $line = \core_text::convert($lines[0], $this->encoding, 'utf-8');
        $fields = \str_getcsv($line, $this->delim);
        for ($i = 0, $fieldssize = \count($fields); $i < $fieldssize; ++$i) {
            $fields[$i] = \trim($fields[$i]);
        }
        $revfields = \array_flip($fields);
        $columns = array();

        $minfiedssize = 0;
        foreach ($this->match as $key => $value) {
            if (!\array_key_exists($value, $revfields)) {
                $this->_error = 'Le fichier CSV ne contient pas toutes les colonnes nécessaires';
                $this->_progressreporter->end_progress();
                $this->_progressreporter->end_progress();
                return false;
            }
            $i = $revfields[$value];
            $columns[$key] = $i;
            if ($minfiedssize < $i) {
                $minfiedssize = $i;
            }
        }
        ++$minfiedssize;

        $this->_progressreporter->end_progress();

        $this->_progressreporter->start_progress('Liste', $linessize - 1, 8);
        // On traite chaque ligne.
        // TODO : economie de mémoire avec array_pop.
        for ($i = 1; $i < $linessize; ++$i) {
            $this->_progressreporter->progress($i);
            $line = \core_text::convert($lines[$i], $this->encoding, 'utf-8');
            // On sépare les champs.
            $fields = \str_getcsv($line, $this->delim);
            // Y a t-il assez de champs dans la ligne ?
            if (\count($fields) < $minfiedssize) {
                continue;
            }
            // On constitue un stdClass de l'utilisateur.
            $iu = new \stdClass();
            foreach ($columns as $key => $ii) {
                $iu->$key = \trim($fields[$ii]);
            }
            $this->add_iu($iu);
        }
        $this->_progressreporter->end_progress();
        $this->_progressreporter->end_progress();
        return true;
    }
}

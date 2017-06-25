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
 * Classe de base pour les parsers de fichiers d'import
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_parser {
    /**
     * @var string|null Null if ok, error msg otherwise
     */
    protected $_error;

    protected $_report;

    protected $_buffer;

    protected $_progressreporter = null;

    protected $_validatecallback = null;

    /**
     * Get last error
     *
     * @return string error text of null if none
     */
    public function get_error() {
        return $this->_error;
    }

    public function set_progress_reporter($progressreporter) {
        $this->_progressreporter = $progressreporter;
    }

    public function set_validatecallback($callback) {
        $this->_validatecallback = $callback;
    }

    /**
     * Get report
     *
     * @return stdClass avec les champs parsedlines, addedusers, uidcollision
     */
    public function get_report() {
        return $this->_report;
    }

    /**
     * Lit un fichier d'utilisateurs
     *
     * @param string $filename Nom du fichier
     * @param string $filecontent Contenu du fichier
     * @return false|array le tableau des $iu indexé par uid
     */
    public function parse($filename, $filecontent) {
        if (\is_null($this->_progressreporter)) {
            $this->_progressreporter = new \core\progress\none();
        }
        $this->_report = new \stdClass();
        $this->_report->parsedlines = 0;
        $this->_report->addedusers = 0;
        $this->_report->uidcollision = 0;
        $this->_buffer = array();
        if ($this->do_parse($filename, $filecontent)) {
            return $this->_buffer;
        } else {
            return false;
        }
    }

    /**
     * Lit un fichier d'utilisateurs
     *
     * @param string $filename Nom du fichier
     * @param string $filecontent Contenu du fichier
     * @return bool si réussi ou non
     */
    protected abstract function do_parse($filename, $filecontent);

    protected function validate_record($iu) {
        if (isset($this->_validatecallback)) {
            return \call_user_func($this->_validatecallback, $iu);
        } else {
            return true;
        }
    }

    protected function add_iu($iu) {
        ++$this->_report->parsedlines;
        if ($this->validate_record($iu)) {
            if (\array_key_exists($iu->uid, $this->_buffer)) {
                ++$this->_report->uidcollision;
            } else {
                $this->_buffer[$iu->uid] = $iu;
                ++$this->_report->addedusers;
            }
        }
    }

    protected function unzipone($filename, $filecontent) {
        $this->_progressreporter->start_progress('unzip', 3);
        // Il faut enregistrer le fichier temporairement.
        $tempdir = \make_request_directory();
        $tempfile = $tempdir . '/archiv.zip';
        $extractdir = \make_unique_writable_directory($tempdir);
        \file_put_contents($tempfile, $filecontent);
        $this->_progressreporter->progress(1);
        unset($filecontent);
        $fp = \get_file_packer('application/zip');
        $files = $fp->extract_to_pathname($tempfile, $extractdir);
        \unlink($tempfile);
        $this->_progressreporter->progress(2);

        if (\count($files) !== 1) {
            $this->_error = 'Le fichier zip ne doit contenir qu\'un fichier.';
            $this->_progressreporter->end_progress();
            return false;
        }
        foreach ($files as $file => $status) {
            if ($status !== true) {
                $this->_error = 'Fichier zip corrompu.';
                $this->_progressreporter->end_progress();
                return false;
            }
            break;
        }
        $_ext = \strtoupper(pathinfo($file, PATHINFO_EXTENSION));
        $file = $extractdir . '/' . $file;
        $filecontent = \file_get_contents($file);
        \unlink($file);
        $this->_progressreporter->end_progress();
        return [$file, $filecontent];
    }
}

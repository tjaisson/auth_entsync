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

defined('MOODLE_INTERNAL') || die();

/**
 * Classe de base pour les parsers de fichiers d'import
 *
 *
 * @package   tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class auth_entsync_parser {
    /**
     * @var string|null Null if ok, error msg otherwise
     */
    protected $_error;
    
    /**
     * @var int
     */
    protected $_parsedlines;

     /**
     * @var int
     */
    protected $_addedusers;

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
     * Get number of parsed lines
     *
     * @return int
     */
    public function get_parsedlines() {
        return $this->_parsedlines;
    }
    
    /**
     * Get number of added users
     *
     * @return int
     */
    public function get_addedusers() {
        return $this->_addedusers;
    }
    
    /**
     * Lit un fichier d'utilisateurs et compète la table
     * temporaire 'tool_entsync_tmpul'
     *
     * @param string $filename Nom du fichier
     * @param string $filecontent Contenu du fichier
     */
    public abstract function parse($filename, $filecontent);
    
    protected function validaterecord($record) {
        if(isset($this->_validatecallback)) {
            return call_user_func($this->_validatecallback, $record);            
        } else {
            return true;
        }
    }
}

/**
 * Classe pour parser les fichiers CSV
 *
 *
 * @package   tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_parser_CSV extends auth_entsync_parser {
    public $encoding;
    public $match;
    public $delim;
    public function parse($filename, $filecontent) {
        global $DB;
        
        $this->_progressreporter->start_progress('',10);
        $this->_progressreporter->start_progress('',1,1);
        
        if(strtoupper(pathinfo($filename, PATHINFO_EXTENSION)) != 'CSV') {
        	$this->_error = 'Fichier csv requis';
        	$this->_progressreporter->end_progress();
        	$this->_progressreporter->end_progress();
        	return false;
        }
        
        $this->_error = null;
        $this->_parsedlines = 0;
        $this->_addedusers = 0;

        //on sépare les lignes
        $lines = preg_split("/\\r\\n|\\r|\\n/", $filecontent);
        unset($filecontent);

        $linessize = count($lines);
        
        $this->_progressreporter->end_progress();
        if($linessize < 2) {
            //pas assez de lignes
            $this->_error = "Le fichier $filename n'est pas lisible";
        	$this->_progressreporter->end_progress();
            return false;
        }
        
        $this->_progressreporter->start_progress('',1,1);
        
        //on retire le bom si nécessaire
        $bom = pack("CCC", 0xef, 0xbb, 0xbf);
        if (0 === strncmp($lines[0], $bom, 3)) {
            $this->encoding = 'utf-8';
            $lines[0] = substr($lines[0], 3);
        }
        
        //on lit les entêtes
        $line = core_text::convert($lines[0], $this->encoding, 'utf-8');
        $fields = str_getcsv($line, $this->delim);
        for($i = 0, $fieldssize = count($fields); $i < $fieldssize; ++$i) {
            $fields[$i] = trim($fields[$i]);
        }
        $revfields = array_flip($fields);
        $columns = array();
        
        $minfiedssize = 0;
        foreach ($this->match as $key => $value) {
            if(!array_key_exists($value, $revfields)) {
                $this->_error = 'Le fichier CSV ne contient pas toutes les colonnes nécessaires';
        	   $this->_progressreporter->end_progress();
        	   $this->_progressreporter->end_progress();
        	   return false;
            }
            $i = $revfields[$value];
            $columns[$key] = $i;
            if($minfiedssize < $i) $minfiedssize = $i;
        }
        ++$minfiedssize;

        $this->_progressreporter->end_progress();
        
        $this->_progressreporter->start_progress('',$linessize-1,8);
        //on traite chaque ligne
        for($i = 1; $i < $linessize; ++$i) {
            $this->_progressreporter->progress($i);
            $line = core_text::convert($lines[$i], $this->encoding, 'utf-8');
            //on sépare les champs
            $fields = str_getcsv($line, $this->delim);
            //y a t-il assez de champs dans la ligne ?
            if(count($fields) < $minfiedssize) continue;
            //on constitue un enregistrement dans la base temporaire
            $record = new stdClass();
                    foreach ($columns as $key => $ii) {
                $record->$key = trim($fields[$ii]);
            }
            ++$this->_parsedlines;
            if($this->validaterecord($record)) {
                $DB->insert_record('auth_entsync_tmpul', $record);
                ++$this->_addedusers;
            }
        }
        $this->_progressreporter->end_progress();
        $this->_progressreporter->end_progress();
        return true;
    }
    
}

/**
 * Classe pour parser les CSV qui n'ont pas de champ profil.
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_parser_XML extends auth_entsync_parser {
    protected $_record;
    protected $_field = '';
    
    public function parse($filename, $filecontent) {
        
        $this->_progressreporter->start_progress('',10);
        $this->_progressreporter->start_progress('',1,1);
        
        
        $_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
        //unzip si nécessaire
        if($_ext == 'ZIP') {
            
        }
        if($_ext != 'XML') {
            $this->_error = 'Fichier xml requis';
            $this->_progressreporter->end_progress();
            $this->_progressreporter->end_progress();
            return false;
        }
        return $this->doparse($filecontent);
    }
    
    private function doparse($filecontent) {
        $parser = xml_parser_create('UTF-8');
        xml_set_element_handler($parser, [$this, 'on_open'], [$this, 'on_close']);
        xml_set_character_data_handler($parser, [$this, 'on_data']);
        xml_parse($parser, $filecontent);
        unset($filecontent);
        xml_parser_free($parser);
    }
    
    function on_data($parser, $data) {
        if(!empty($this->_field)) {
            if(isset($this->_record))
                $this->_record->{$this->_field} .= $data;
        }
    }
    
    
}
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
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class auth_entsync_parser {
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
     * Get repport
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
        $this->_report = new stdClass();
        $this->_report->parsedlines = 0;
        $this->_report->addedusers = 0;
        $this->_report->uidcollision = 0;
        $this->_buffer = array();
        if($this->do_parse($filename, $filecontent)) {
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
        if(isset($this->_validatecallback)) {
            return call_user_func($this->_validatecallback, $iu);            
        } else {
            return true;
        }
    }

    protected function add_iu($iu) {
        ++$this->_report->parsedlines;
        if($this->validate_record($iu)) {
            if(array_key_exists($iu->uid, $this->_buffer)) {
                ++$this->_report->uidcollision;
            } else {
                $this->_buffer[$iu->uid] = $iu;
                ++$this->_report->addedusers;
            }
        }
    }
    
    protected function unzipone($filename, $filecontent) {
        $this->_progressreporter->start_progress('unzip',3);
        //il faut enregistrer le fichier temporairement
        $tempdir = make_request_directory();
        $tempfile = $tempdir . '/archiv.zip';
        $extractdir = make_unique_writable_directory($tempdir);
        file_put_contents($tempfile, $filecontent);
        $this->_progressreporter->progress(1);
        unset($filecontent);
        $fp = get_file_packer('application/zip');
        $files = $fp->extract_to_pathname($tempfile, $extractdir);
        unlink($tempfile);
        $this->_progressreporter->progress(2);
        
        if(count($files) !== 1) {
            $this->_error = 'Le fichier zip ne doit contenir qu\'un fichier.';
            $this->_progressreporter->end_progress();
            return false;
        }
        foreach ($files as $file => $status) {
            if($status !== true) {
                $this->_error = 'Fichier zip corrompu.';
                $this->_progressreporter->end_progress();
                return false;
            }
            break;
        }
        $_ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
        $file = $extractdir . '/' . $file;
        $filecontent = file_get_contents($file);
        unlink($file);
        $this->_progressreporter->end_progress();
        return [$file, $filecontent];
    }
}

/**
 * Classe pour parser les fichiers CSV
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_parser_CSV extends auth_entsync_parser {
    public $encoding;
    public $match;
    public $delim;
    protected function do_parse($filename, $filecontent) {
        $this->_progressreporter->start_progress('Lecture du fichier',10,1);
        $this->_progressreporter->start_progress('En tête',1,1);

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

        $this->_progressreporter->start_progress('En tête',1,1);
        
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
        
        $this->_progressreporter->start_progress('Liste',$linessize-1,8);
        //on traite chaque ligne
        //TODO : economie de mémoire avec array_pop
        for($i = 1; $i < $linessize; ++$i) {
            $this->_progressreporter->progress($i);
            $line = core_text::convert($lines[$i], $this->encoding, 'utf-8');
            //on sépare les champs
            $fields = str_getcsv($line, $this->delim);
            //y a t-il assez de champs dans la ligne ?
            if(count($fields) < $minfiedssize) continue;
            //on constitue un stdClass de l'utilisateur
            $iu = new stdClass();
                    foreach ($columns as $key => $ii) {
                $iu->$key = trim($fields[$ii]);
            }
            $this->add_iu($iu);
        }
        $this->_progressreporter->end_progress();
        $this->_progressreporter->end_progress();
        return true;
    }
}

/**
 * Classe de base pour parser les XML.
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class auth_entsync_parser_XML extends auth_entsync_parser {
    protected $_record;
    protected $_field = '';
    
    protected function do_parse($filename, $filecontent) {
        
        $this->_progressreporter->start_progress('lecture',10);
        
        $_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
        //unzip si nécessaire
        if($_ext == 'ZIP') {
            $ret = $this->unzipone($filename, $filecontent);
            if(!$ret) {
                $this->_progressreporter->end_progress();
                return false;
            }
            list($filename, $filecontent) = $ret;
            $_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
        } else {
            $this->_progressreporter->increment_progress();
        }
        
        //ce doit être un xml
        if($_ext != 'XML') {
            $this->_error = 'Fichier xml requis';
            $this->_progressreporter->end_progress();
            return false;
        }
        $bf = $this->doparse($filecontent);
        $this->_progressreporter->end_progress();
        return $bf;
    }
    
    private function doparse($filecontent) {
        $this->_progressreporter->start_progress('lecture', \core\progress\display_if_slow::INDETERMINATE ,9);
        $parser = xml_parser_create('UTF-8');
        xml_set_element_handler($parser, [$this, 'on_open'], [$this, 'on_close']);
        xml_set_character_data_handler($parser, [$this, 'on_data']);
        xml_parse($parser, $filecontent, true);
        unset($filecontent);
        xml_parser_free($parser);
        $this->afterparse();
        $this->_progressreporter->end_progress();
        return $this->_buffer;
    }
    
    function on_data($parser, $data) {
        if(!empty($this->_field)) {
            if(isset($this->_record))
                $this->_record->{$this->_field} .= $data;
        }
    }
    
    public abstract function on_open($parser, $name, $attribs); 
    public abstract function on_close($parser, $name);
    protected function afterparse() {
        
    }
}

/**
 * Classe pour parser les XML BEE.
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_parser_bee extends auth_entsync_parser_XML {
    private $match1 = ['lastname' => 'NOM_DE_FAMILLE', 'firstname' => 'PRENOM'];
    private $match2 = ['cohortname' => 'CODE_STRUCTURE'];
    private $match;
    public function on_open($parser, $name, $attribs) {
        switch($name) {
            case 'ELEVE' :
                $this->_record = new stdClass();
                $this->_record->uid = $attribs['ELEVE_ID'];
                $this->match = $this->match1;
                return;
            case 'STRUCTURES_ELEVE' :
                $this->_record = new stdClass();
                $this->_record->uid = $attribs['ELEVE_ID'];
                $this->match = $this->match2;
                return;
        }
        
        if(isset($this->_record)) {
            if($key =  array_search($name, $this->match)) {
                $this->_field = $key;
                $this->_record->{$key} = '';
            }
        }
    }

    public function on_close($parser, $name) {
        $this->_field = '';
        switch($name) {
            case 'ELEVE' :
                $this->add_iu($this->_record);
                unset($this->_record);
                $this->_progressreporter->progress();
                return;
            case 'STRUCTURES_ELEVE' :
                if(array_key_exists($this->_record->uid, $this->_buffer)) {
                    $this->_buffer[$this->_record->uid]->cohortname = $this->_record->cohortname;
                    $this->_progressreporter->progress();
                }
                unset($this->_record);
                return;
        }
    }
    protected function afterparse() {
        $lst = $this->_buffer;
        $this->_buffer = array();
        $this->_report->addedusers = 0;
        while($lst) {
            $iu = array_pop($lst);
            if(!empty($iu->cohortname)) {
                ++$this->_report->addedusers;
                array_push($this->_buffer, $iu);
            }
        }
    }
}

/**
 * Classe pour parser les XML BEE.
 *
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_parser_sts extends auth_entsync_parser_XML {
    private $match = ['lastname' => 'NOM_DE_FAMILLE', 'firstname' => 'PRENOM'];
    public function on_open($parser, $name, $attribs) {
        switch($name) {
            case 'ELEVE' :
                $this->_record = new stdClass();
                $this->_record->uid = $attribs['ELEVE_ID'];
                $this->match = $this->match1;
                return;
            case 'STRUCTURES_ELEVE' :
                $this->_record = new stdClass();
                $this->_record->uid = $attribs['ELEVE_ID'];
                $this->match = $this->match2;
                return;
        }

        if(isset($this->_record)) {
            if($key =  array_search($name, $this->match)) {
                $this->_field = $key;
                $this->_record->{$key} = '';
            }
        }
    }

    public function on_close($parser, $name) {
        $this->_field = '';
        switch($name) {
            case 'ELEVE' :
                $this->add_iu($this->_record);
                unset($this->_record);
                $this->_progressreporter->progress();
                return;
            case 'STRUCTURES_ELEVE' :
                if(array_key_exists($this->_record->uid, $this->_buffer)) {
                    $this->_buffer[$this->_record->uid]->cohortname = $this->_record->cohortname;
                    $this->_progressreporter->progress();
                }
                unset($this->_record);
                return;
        }
    }
    protected function afterparse() {
        $lst = $this->_buffer;
        $this->_buffer = array();
        $this->_report->addedusers = 0;
        while($lst) {
            $iu = array_pop($lst);
            if(!empty($iu->cohortname)) {
                ++$this->_report->addedusers;
                array_push($this->_buffer, $iu);
            }
        }
    }
}
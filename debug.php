<?php

//require(__DIR__ . '/../../config.php');


class myparser {
    private $nb = 0;
    private $nb2 = 0;
    private $_iu;
    private $_struct;
    private $_field = '';
    private $match = ['lastname' => 'NOM', 'firstname' => 'PRENOM'];
    private $match2 = ['cohortname' => 'CODE_STRUCTURE'];
    function on_open($parser, $name, $attribs) {
        switch($name) {
            case 'ELEVE' :
                $this->_iu = new stdClass();
                $this->_iu->uid = $attribs['ELEVE_ID'];
                return;
            case 'STRUCTURES_ELEVE' :
                $this->_struct = new stdClass();
                $this->_struct->uid = $attribs['ELEVE_ID'];
                return;
        }
        
        if(isset($this->_iu)) {
            if($key =  array_search($name, $this->match)) {
                $this->_field = $key;
                $this->_iu->{$key} = '';
            }
        } else if(isset($this->_struct)) {
            if($key =  array_search($name, $this->match2)) {
                $this->_field = $key;
                $this->_struct->{$key} = '';
            }
        }
        
    }
    
    function on_close($parser, $name) {
        $this->_field = '';
        switch($name) {
            case 'ELEVE' :
                if($this->nb<20) {
                    var_dump($this->_iu);
                    echo '<hr />';
                    ++$this->nb;
                }
                unset($this->_iu);
                return;
            case 'STRUCTURES_ELEVE' :
                if($this->nb2<20) {
                    var_dump($this->_struct);
                    echo '<hr />';
                    ++$this->nb2;
                }
                unset($this->_struct);
                return;
        }
    }
    
    function on_data($parser, $data) {
        if(!empty($this->_field)) {
            if(isset($this->_iu))
                $this->_iu->{$this->_field} .= $data;
            else if(isset($this->_struct))
                $this->_struct->{$this->_field} .= $data;
        }
    }
    
    function parse($content) {
        $parser = xml_parser_create('UTF-8');
        xml_set_element_handler($parser, [$this, 'on_open'], [$this, 'on_close']);
        xml_set_character_data_handler($parser, [$this, 'on_data']);
        xml_parse($parser, $content);
        unset($content);
        xml_parser_free($parser);
    }
}




$content = file_get_contents('test.xml');
$myparser = new myparser();


?>
<!DOCTYPE html>
<html  dir="ltr" lang="fr" xml:lang="fr">
<head></head>
<body>
<?php $myparser->parse($content) ?>
</body>
</html>

<?php

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib/locallib.php');
require_once $CFG->libdir.'/formslib.php';


class myform extends moodleform  {
    function definition () {
        $mform = $this->_form;
        $mform->addElement('text', 'champ', 'champ');
        $mform->setType($elemname, PARAM_TEXT);
        $this->add_action_buttons();
    }
}

$form = new myform(); 

$txt = 'no data';

if($data = $form->get_data()) {
    $txt = $data->champ;
}


$txt2 = simplify_name($txt);




echo $OUTPUT->header();
echo $OUTPUT->heading('test');
echo "<pre>$txt</pre>";
echo "<pre>$txt2</pre>";
$form->display();
echo $OUTPUT->footer();


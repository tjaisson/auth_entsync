<?php

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib/locallib.php');
require_once $CFG->libdir.'/formslib.php';

$url = new moodle_url('/');
$PAGE->set_url($url);
unset($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());

class myform extends moodleform  {
    function definition () {
        $mform = $this->_form;
        $mform->addElement('text', 'champ', 'champ');
        $mform->setType('champ', PARAM_TEXT);
        $this->add_action_buttons();
    }
}

$form = new myform(); 

$txt = 'no data';

if($data = $form->get_data()) {
    $txt = $data->champ;
}


$txt2 = auth_entsync_stringhelper::simplify_name($txt);




echo $OUTPUT->header();
echo $OUTPUT->heading('test');
echo "<pre>$txt</pre>";
echo "<pre>$txt2</pre>";
$form->display();
echo $OUTPUT->footer();


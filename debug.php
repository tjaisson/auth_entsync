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

$txt3 =  auth_entsync_stringhelper::rnd_string();


echo $OUTPUT->header();
echo $OUTPUT->heading('test');
echo "<pre>$txt</pre>";
echo "<pre>$txt2</pre>";
echo "<pre>$txt3</pre>";

$mdlus = $DB->get_records('user');
$progress = new \core\progress\display_if_slow('Synchronisation', 0);
$progress->start_progress('',count($mdlus));
$i=0;
foreach ($mdlus as $mdlu) {
    $fn = auth_entsync_stringhelper::simplify_name($mdlu->firstname);
    $ln = auth_entsync_stringhelper::simplify_name($mdlu->lastname);
    $pw = auth_entsync_stringhelper::rnd_string();
    ++$i;
echo "<pre>$i $ln $fn $pw</pre>";
}


$form->display();
echo $OUTPUT->footer();


<?php

// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');

$page_url = new moodle_url('/auth/entsync/aboutpam.php');
$PAGE->set_url($page_url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('PAM');
echo $OUTPUT->header();
echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 80em; margin: 0 auto 0; padding: 2em;']);
echo html_writer::start_div('row-fluid');
echo html_writer::start_div('span1');
echo html_writer::img($OUTPUT->image_url('ac-paris', 'auth_entsync'), 'ac-paris');
echo html_writer::end_div();
echo html_writer::start_div('span11');
echo $OUTPUT->heading('Plateforme Académique Moodle');
echo $OUTPUT->heading('La plateforme Moodle de l\'académie de Paris', 5);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 80em; margin: 2em auto 0; padding: 2em;']);
?>
<h5>L'espace Moodle de votre établissement n'existe pas.</h5>
<p>Pour bénéficier de cet espace, le chef d'établissement doit formuler la demande
auprès des services du rectorat.</p>

<?php 
echo html_writer::end_div();
echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 80em; margin: 2em auto 0; padding: 2em;']);
?>
<h5>En savoir plus...</h5>
<ul>
<li>
<a href="https://www.ac-paris.fr/portail/jcms/p1_1499660">Présentation de l'offre Moodle sur le site académique.</a></li>

</ul>
<?php 
echo $OUTPUT->footer();


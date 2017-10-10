<?php
defined('MOODLE_INTERNAL') || die;

echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 80em; margin: 0 auto 0; padding: 2em;']);
echo html_writer::start_div('row-fluid');
echo html_writer::start_div('span4');
echo html_writer::img($OUTPUT->image_url('ac-paris', 'auth_entsync'), 'ac-paris');
//echo html_writer::img("https://www.ac-paris.fr/portail/upload/docs/image/png/2016-12/logo_web_ac-paris_bleu.png", 'ac-paris');

echo html_writer::end_div();
echo html_writer::start_div('span8');
echo $OUTPUT->heading('Plateforme Académique Moodle');
echo $OUTPUT->heading('La plateforme Moodle de l\'académie de Paris', 5);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 80em; margin: 2em auto 0; padding: 2em;']);
?>
<h5>L'espace Moodle de votre établissement n'existe pas&nbsp;!</h5>
<p>Pour bénéficier de cet espace, le chef d'établissement doit formuler la demande
auprès des services du rectorat.</p>
<?php 
echo html_writer::end_div();
echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 80em; margin: 2em auto 0; padding: 2em;']);
?>
<h5>Qu'est ce que <i>Moodle</i>&nbsp;?</h5>
<p>Moodle est une plate-forme <i>open source</i> d'apprentissage en ligne. Fréquemment utilisée dans l’enseignement supérieur,
c’est un outil qui se révèle aussi très intéressant pour l’enseignement secondaire.</p>

<h5>L'offre académique&nbsp;:</h5>
<p>Chaque établissement de l'académie de Paris qui le souhaite peut bénéficier d'un espace Moodle dédié, hébergé par le rectorat.</p>
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
echo html_writer::end_div();

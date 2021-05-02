<?php

// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');
require_once('ent_defs.php');


// Try to prevent searching for sites that allow sign-up.
if (!isset($CFG->additionalhtmlhead)) {
    $CFG->additionalhtmlhead = '';
}
$CFG->additionalhtmlhead .= '<meta name="robots" content="noindex" />';

$page_url = new moodle_url('/auth/entsync/testcas.php');

$entclass = optional_param('ent', '', PARAM_RAW);

$reponse = '';

if ((!empty($entclass)) &&
    ($ent = auth_entsync_ent_base::get_ent($entclass)) &&
    ($ent->is_sso()) &&
    ($cas = $ent->get_casconnector()) ) {
        $cas->set_clienturl(new moodle_url($page_url, ['ent' => $ent->get_entclass()]));
        
        $reponse .= "<h2>ENT :</h2>";
        $reponse .= "<pre>" . $ent->nomlong . "</pre>";
        
        if ($val = $cas->validateorredirect()) {
            $reponse .= "<h2>Ticket :</h2>";
            $reponse .= "<pre>" . $cas->get_ticket() . "</pre>";
            $reponse .= "<h2>Réponse :</h2>";
            $reponse .= "<pre>" . htmlspecialchars(var_export($val, true)) . "</pre>";
        } else {
            $reponse .= "<h2>Ticket :</h2>";
            $reponse .= "<pre>" . $cas->get_ticket() . "</pre>";
            $reponse .= "<h2>Erreur :</h2>";
            $reponse .= "<pre>" .$cas->get_error() . "</pre>";
        }
}

$page_url = new moodle_url('/auth/entsync/testcas.php');
$PAGE->set_url($page_url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('popup');
$PAGE->set_title('Test CAS');

echo $OUTPUT->header();
echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
echo $OUTPUT->heading('Connexions CAS disponibles&nbsp;:');

$arrowico = $OUTPUT->pix_icon('t/right', get_string('go'));
foreach (auth_entsync_ent_base::get_ents() as $ent) {
    if ($ent->is_sso() && $ent->get_mode() == 'cas') {
        if ($cas = $ent->get_casconnector()) {
            $lnk = $arrowico . '&nbsp;' . $ent->nomlong;
            $cas->set_clienturl(new moodle_url($page_url, ['ent' => $ent->get_entclass()]));
            $lnk = html_writer::link($cas->buildloginurl(), $lnk);
            echo html_writer::tag('p', $lnk, ['style' => 'padding-left: 5em;']);
        }
    }
}

if (!empty($reponse)) {
    $lnk = html_writer::link($page_url, 'Reset');
    echo html_writer::tag('p', $lnk, ['style' => 'padding-left: 5em;']);
    
    echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
    echo $OUTPUT->heading('Validation cas&nbsp;:');
    echo $reponse;
}

echo html_writer::end_div();
echo $OUTPUT->footer();

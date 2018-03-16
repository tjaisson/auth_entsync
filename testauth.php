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

$my_url = new moodle_url('/auth/entsync/testauth.php');
$PAGE->set_url($my_url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('popup');
$PAGE->set_title('Test CAS');

$entclass = optional_param('ent', '', PARAM_RAW);
if (!empty($entclass))
{
    if (!($ent = auth_entsync_ent_base::get_ent($entclass))) {
        printerrorpage("Code ENT '{$entclass}' inconnu.");
    }
    if (!$ent->is_sso()) {
        printerrorpage("L'ENT {$ent->nomlong} ne permet pas le sso.");
    }
    if (!($connector = $ent->get_connector())) {
        printerrorpage("Le connecteur pour {$ent->nomlong} n'est pas configuré.");
    }
    $connector->set_clienturl($my_url);
    $connector->redir_to_login();
}

$entclass = \auth_entsync\connectors\base_connect::get_ent_class();
if (!empty($entclass))
{
    if (!($ent = auth_entsync_ent_base::get_ent($entclass))) {
        printerrorpage("Code ENT '{$entclass}' inconnu.");
    }
    if (!$ent->is_sso()) {
        printerrorpage("L'ENT {$ent->nomlong} ne permet pas le sso.");
    }
    if (!($connector = $ent->get_connector())) {
        printerrorpage("Le connecteur pour {$ent->nomlong} n'est pas configuré.");
    }
    $connector->set_clienturl($my_url);
    if (!($user = $connector->get_user())) {
        $err = $connector->get_error();
        printerrorpage("Erreur d'authentification {$ent->nomlong}&nbsp;: {$err}.");
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
echo $OUTPUT->heading('Connexions CAS disponibles&nbsp;:');

$arrowico = $OUTPUT->pix_icon('t/right', get_string('go'));
foreach (auth_entsync_ent_base::get_ents() as $ent) {
    if ($ent->is_sso()) {
        $lnktxt = $arrowico . '&nbsp;' . $ent->nomlong;
        $lnk = new moodle_url($my_url, ['ent' => $ent->get_entclass()]);
        $lnk = html_writer::link($lnk, $lnktxt);
        echo html_writer::tag('p', $lnk, ['style' => 'padding-left: 5em;']);
    }
}

if (isset($user)) {
    $lnk = html_writer::link($my_url, 'Reset');
    echo html_writer::tag('p', $lnk, ['style' => 'padding-left: 5em;']);
    echo html_writer::end_div();
    
    echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
    echo $OUTPUT->heading('Résultat de l\'authentification&nbsp;:');
    echo "<h2>ENT$nbsp;:</h2><pre>{$ent->nomlong}</pre>";
    echo "<h2>user id$nbsp;:</h2><pre>{$user->id}</pre>";
    if ($connector->has_userinfo()) {
        echo "<h2>Nom$nbsp;:</h2><pre>{$user->lastname}</pre>";
        echo "<h2>Prénom$nbsp;:</h2><pre>{$user->firstname}</pre>";
        echo "<h2>Liste des UAIs&nbsp;:</h2><ol>";
        foreach ($user->uais as $uai) {
            echo "<li>{$uai}</li>";
        }
        echo "</ol>";
    }
}

echo html_writer::end_div();
echo $OUTPUT->footer();

function printerrorpage($msg, $type = \core\output\notification::NOTIFY_ERROR, $url = null) {
    global $OUTPUT, $CFG, $my_url;
    $url = is_null($url) ? $my_url : $url;
    echo $OUTPUT->header();
    echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
    echo $OUTPUT->notification($msg, $type);
    echo $OUTPUT->continue_button($url);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}

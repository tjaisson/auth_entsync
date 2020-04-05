<?php

// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');
require_once('ent_defs.php');

$page_url = new moodle_url('/auth/entsync/switch.php');
$PAGE->set_url($page_url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');

$entclass = optional_param('ent', '', PARAM_RAW);

if($entclass == 'testabout') {
    printinfopage();
}

if (empty($entclass)) {
	printerrorpage();
}

if ((!$ent = auth_entsync_ent_base::get_ent($entclass)) ||
        (!$ent->is_sso()) ||
        (!$ent->can_switch()) ||
        (!($cas = $ent->get_casconnector())) ) {
    // L'ENT spécifié pose problème.
    printerrorpage();
}

$cas->set_clienturl(new moodle_url($page_url, ['ent' => $ent->get_entclass()]));

if ($val = $cas->validateorredirect()) {
    if (count($val->rnes) <= 0) {
        // L'utilisateur n'a pas d'instance.
        printinfopage();
    }
    // On constitue la liste des instances de cet utilisateur.
    $instances = \auth_entsync\persistents\instance::get_records([], 'name');
    $userinsts = [];
    foreach ($instances as $instance) {
        if ($instance->has_rne($val->rnes)) {
            $userinsts[] = $instance;
        }
    }
    $instcount = count($userinsts);
    if ($instcount <= 0) {
        // L'utilisateur n'a pas d'instance, on lui présente aboutpam.
        printinfopage();
    } else if ($instcount == 1) {
        // L'utilisateur n'a qu'une instance, alors on redirige directement.
        redirect(build_connector_url($userinsts[0], $ent));
    } else {
        // L'utilisateur a plusieurs instances, alors on lui donne le choix.
        printselectpage($userinsts, $ent);
    }
}

function build_connector_url($instance, $ent) {
    return new moodle_url($instance->wwwroot() . '/auth/entsync/connect.php', ['ent' => $ent->get_entclass()]);
}

function printerrorpage(
        $msg = 'Accès non autorisé&nbsp;!',
        $type = \core\output\notification::NOTIFY_ERROR,
        $url = null) {
    global $OUTPUT, $CFG, $PAGE;
    $PAGE->set_title('Erreur');
    $url = is_null($url) ? \auth_entsync\persistents\instance::pamroot() : $url;
    echo $OUTPUT->header();
    echo $OUTPUT->notification($msg, $type);
    echo $OUTPUT->continue_button($url);
    echo $OUTPUT->footer();
    die();
}

function printselectpage($userinsts, $ent) {
    global $OUTPUT, $PAGE;
    $PAGE->set_title('Redirection');
    echo $OUTPUT->header();
    echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
    echo $OUTPUT->heading('Plateforme Académique Moodle');
    echo html_writer::tag('p', 'À quelle plateforme souhaitez-vous accéder&nbsp;?');
    $arrowico = $OUTPUT->pix_icon('t/right', get_string('go'));
    foreach ($userinsts as $instance) {
        $lnk = $arrowico . '&nbsp;' . $instance->get('name');
        $lnk = html_writer::link(build_connector_url($instance, $ent), $lnk);
        echo html_writer::tag('p', $lnk, ['style' => 'padding-left: 5em;']);
    }
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}

function printinfopage() {
    global $OUTPUT, $PAGE;
    $PAGE->set_title('PAM');
    echo $OUTPUT->header();
    include(__DIR__ . '/aboutpam.php');
    echo $OUTPUT->footer();
    die();
}
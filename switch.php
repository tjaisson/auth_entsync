<?php

// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');
require_once('ent_defs.php');

$PAGE->set_url(new moodle_url('/auth/entsync/switch.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('popup');
$PAGE->set_title('Redirection');

$entclass = optional_param('ent', '', PARAM_RAW);

if (empty($entclass)) {
	printerrorpage('Accès non autorisé&nbsp;!');
}

if (!$ent = auth_entsync_ent_base::get_ent($entclass)) {
	// Le code ne correspond pas à un ent, display erreur.
	printerrorpage('Accès non autorisé&nbsp;!');
}

if (!$ent->is_sso()) {
    // Si ce n'est pas sso, l'aiguillage n'est pas possible.
    printerrorpage('Accès non autorisé&nbsp;!');
}

if (!$ent->can_switch()) {
    // Cet ENT ne gère pas l'aiguillage.
    printerrorpage('Accès non autorisé&nbsp;!');
}

if (!$cas = $ent->get_casconnector()) {
    printerrorpage('Accès non autorisé&nbsp;!');
}

$clienturl = new moodle_url("$CFG->httpswwwroot/auth/entsync/switch.php", ['ent' => $entclass]);
$cas->set_clienturl($clienturl);

if ($val = $cas->validateorredirect()) {
    if (count($val->rnes) <= 0) {
        // L'utilisateur n'a pas d'instance.
        printerrorpage('Accès non autorisé&nbsp;!');
    }
    // On constitue la liste des instances.
    $instances = \auth_entsync\sw\instance::get_records([], 'name');
    $userinsts = [];
    foreach ($instances as $instance) {
        if ($instance->has_rne($val->rnes)) {
            $userinsts[] = $instance;
        }
    }
    $instcount = count($userinsts);
    if ($instcount <= 0) {
        // L'utilisateur n'a pas d'instance.
        printerrorpage('Accès non autorisé&nbsp;!');
    } else if ($instcount == 1) {
        // L'utilisateur n'a qu'une instance, alors on redirige directement.
        redirect(build_connector_url($userinsts[0], $ent));
    } else {
        // L'utilisateur a plusieurs instances, alors on lui donne le choix.
        echo $OUTPUT->header();
        echo $OUTPUT->heading('Plateforme Académique Moodle');
        echo html_writer::tag('p', 'À quelle plateforme souhaitez-vous accéder&nbsp;?');
        $arrowico = $OUTPUT->pix_icon('t/right', get_string('go'));
        
        foreach ($userinsts as $instance) {
            $lnk = html_writer::link(build_connector_url($instance, $ent), $arrowico);
            echo html_writer::tag('p', $lnk . '&nbsp;' . $instance->get('name'));
        }
        echo $OUTPUT->footer();
        die();
    }
}

function build_connector_url($instance, $ent) {
    
}

function printerrorpage($msg) {
    global $OUTPUT, $PAGE;
    echo $OUTPUT->header();
	echo "<div><p>{$msg}</p></div>";
	echo $OUTPUT->footer();
	die();
}

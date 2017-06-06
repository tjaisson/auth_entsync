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
    
}

printerrorpage('Accès non autorisé&nbsp;!');

function printerrorpage($msg) {
    global $OUTPUT, $PAGE;
    echo $OUTPUT->header();
	echo "<div><p>{$msg}</p></div>";
	echo $OUTPUT->footer();
	die();
}

<?php

// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);

require(__DIR__ . '/../../config.php');
require_once('ent_defs.php');

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
	PrintHead('Erreur');
	echo "<div class=\"msg\"><p>{$msg}</p></div>";
	PrintTail();
	die();
}


function PrintHead($title='moodle') {
?>
<!DOCTYPE html>
<html  dir="ltr" lang="fr" xml:lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title><?php echo($title);?></title>
<style type="text/css">
body {
    margin: 0;
    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
    font-size: 14px;
    line-height: 20px;
    color: #000;
    background-color: #1da694;
    }
div.msg {
    padding: 20px;
    width: 500px;
    margin: auto;
    border: 1px solid;
}
div.msg p {
    font-size: xx-large;
}
</style>
</head>
<body>
<?php }

function PrintTail() {
?>
</body>
</html>
<?php }

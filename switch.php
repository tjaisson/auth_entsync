<?php
// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);
require(__DIR__ . '/../../config.php');
$entsync = \auth_entsync\container::services();
require_once('ent_defs.php');
$page_url = new moodle_url('/auth/entsync/switch.php');
$entclass = required_param('ent', PARAM_ALPHANUMEXT);
if($entclass == 'testabout') {
    auth_entsync_printinfopage();
}
if ((!$ent = auth_entsync_ent_base::get_ent($entclass)) ||
        (!$ent->is_sso()) ||
        (!$ent->can_switch()) ||
        (!($cas = $ent->get_casconnector()))) print_error('userautherror');
$cas->set_clienturl(new moodle_url($page_url, ['ent' => $ent->get_entclass()]));
if ($val = $cas->validateorredirect()) {
    if (count($val->rnes) <= 0) {
        // L'utilisateur n'a pas d'instance.
        auth_entsync_printinfopage();
    }
    // On constitue la liste des instances de cet utilisateur.
    $instances = $entsync->query('instances');
    $userinsts = $instances->get_instancesForRnes($val->rnes);
    $instcount = count($userinsts);
    if ($instcount <= 0) {
        // L'utilisateur n'a pas d'instance, on lui présente aboutpam.
        auth_entsync_printinfopage();
    } else {
        $iic = $entsync->query('iic');
        $conf = $entsync->query('conf');
        $k = $iic->getCrkey();
        $userdata = json_encode($val, JSON_UNESCAPED_UNICODE);
        if ($instcount == 1) {
            // L'utilisateur n'a qu'une instance, alors on redirige directement.
            // array_key_first polyfill.
            foreach ($userinsts as $key => $v) {
                $inst = $key;
                break;
            }
            redirect(build_connector_url($inst, $userdata, $k));
        } else {
            // L'utilisateur a plusieurs instances, alors on lui donne le choix.
            auth_entsync_printselectpage($userinsts, $userdata, $k);
        }
    }
} else {
    print_error('userautherror');
}
function build_connector_url($inst, $userdata, $k) {
    global $conf, $ent;
    $scope = $inst . ':' . $ent->get_entclass();
    $userdata = $k->seal($userdata, $scope);
    return new moodle_url($conf->pamroot() . '/' . $inst . '/auth/entsync/login.php',
        ['ent' => $ent->get_entclass(), 'user' => $userdata]);
}
function auth_entsync_setupPage(){
    global $PAGE, $page_url;
    $PAGE->set_url($page_url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('embedded');
}
function auth_entsync_printselectpage($userinsts, $userdata, $k) {
    global $OUTPUT, $PAGE;
    auth_entsync_setupPage();
    $PAGE->set_title('Redirection');
    echo $OUTPUT->header();
    echo html_writer::start_div('block', ['style' => 'max-width: 80%; width: 50em; margin: 0 auto 0; padding: 2em;']);
    echo $OUTPUT->heading('Plateforme Académique Moodle');
    echo html_writer::tag('p', 'À quelle plateforme souhaitez-vous accéder&nbsp;?');
    $arrowico = $OUTPUT->pix_icon('t/right', get_string('go'));
    foreach ($userinsts as $rep => $instance) {
        $lnk = $arrowico . '&nbsp;' . $instance['name'];
        $lnk = html_writer::link(build_connector_url($rep, $userdata, $k), $lnk);
        echo html_writer::tag('p', $lnk, ['style' => 'padding-left: 5em;']);
    }
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}
function auth_entsync_printinfopage() {
    global $OUTPUT, $PAGE;
    auth_entsync_setupPage();
    $PAGE->set_title('PAM');
    echo $OUTPUT->header();
    include(__DIR__ . '/aboutpam.php');
    echo $OUTPUT->footer();
    die();
}

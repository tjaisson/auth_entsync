<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Gestion des utilisateurs ENT
 *
 * Affiche le formulaire de synchronisation
 *
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once('ent_defs.php');
use \auth_entsync\forms\bulk_form;
use \auth_entsync\helpers\usertblhelper;
use \auth_entsync\tmpstores\base_tmpstore;

core_php_time_limit::raise(60 * 60); // 1 hour should be enough.
raise_memory_limit(MEMORY_HUGE);

require_login();
admin_externalpage_setup('authentsyncbulk');
require_capability('moodle/site:uploadusers', context_system::instance());

$entsync = \auth_entsync\container::services();

$returnurl = new moodle_url('/auth/entsync/bulk.php');

$config = get_config('auth_entsync');
if (auth_entsync_ent_base::count_enabled() <= 0) {
    // Aucun ent paramétré, on ne peut pas synchroniser.
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string('entsyncbulk', 'auth_entsync'), 'entsyncbulk', 'auth_entsync');
    echo $OUTPUT->notification(get_string('notconfigwarn', 'auth_entsync',
        "{$CFG->wwwroot}/auth/entsync/param.php"));
    echo $OUTPUT->footer();
    die();
}

if (optional_param('proceed', false, PARAM_BOOL) && confirm_sesskey()) {
    // Il faut effectuer la synchro.
    if (!isset($config->role_ens)) {
        $config->role_ens = 0;
    }

    // Retrouver l'ent et le type de fichier.
    list($ent, $filetype) = bulk_form::decodeeft(required_param('frozeneft', PARAM_TEXT));
    if ((!$ent) || (!$ent->is_enabled())) {
        // Ne devrait pas se produire.
        redirect($returnurl,
            'Erreur', null, \core\output\notification::NOTIFY_ERROR);
    }

    // Retrouver les données temporaires.
    $storeid = required_param('storeid', PARAM_INT);
    $tmpstore = base_tmpstore::get_store($storeid);

    $readytosyncusers = $tmpstore->count();
    if ($readytosyncusers <= 0) {
        // Ne devrait pas se produire.
        redirect($returnurl,
            'Aucun utilisateur à synchroniser', null, \core\output\notification::NOTIFY_ERROR);
    }

    $synchronizer = $ent->get_synchronizer($filetype);

    // Mode avancé.
    if (optional_param('advanced', false, PARAM_BOOL)) {
        if (optional_param('recupcas', false, PARAM_BOOL) && ($ent->get_mode() === 'cas')) {
            $synchronizer->set_recupcas(true);
        }
    }

    $synchronizer->roles[2] = $config->role_ens;

    echo $OUTPUT->header();
    $progress = new \core\progress\display_if_slow('Synchronisation', 0);

    $synchronizer->set_progress_reporter($progress);

    $ius = $tmpstore->get_ius();
    $report = $synchronizer->dosync($ius);
    $tmpstore->clear();

    if ($report) {
        // Synchro ok.
        $msg = get_string('aftersyncinfo', 'auth_entsync', $report);
        $msg = $OUTPUT->notification($msg, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Il y a eu une erreur.
        $parseerror = $fileparser->get_error();
        $msg = "Erreur de synchronisation. {$parseerror}";
        $msg = $OUTPUT->notification($msg, \core\output\notification::NOTIFY_ERROR);
    }

    echo $OUTPUT->heading_with_help(get_string('entsyncbulk', 'auth_entsync'), 'entsyncbulk', 'auth_entsync');
    echo $msg;
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    die();
}


$formparams = array();
// Gestion des option avancées.
if (optional_param('advanced', false, PARAM_BOOL)) {
    $formparams['advanced'] = true;
}

// Formulaire et chargement des fichiers.
$mform = new bulk_form(null, $formparams);

echo $OUTPUT->header();

$FromLocal = optional_param('fromlocal', '', PARAM_RAW);
if (!empty($FromLocal)) {
    $entfiletype = $FromLocal;
    list($ent, $filetype) = bulk_form::decodeeft($$entfiletype);
    if ((!$ent) || (!$ent->is_enabled())) {
        // Ne devrait pas se produire.
        redirect($returnurl,
            'Erreur', null, \core\output\notification::NOTIFY_ERROR);
    }
   
    $fileparser = $ent->get_fileparser($filetype);
    $progress = new \core\progress\none();
    $fileparser->set_progress_reporter($progress);
    
    
    $conf = $entsync->query('conf');
    $localPath = '\\var\\www\\froment\\' . $conf->inst();
    $dir = dir($localPath);
    $iuss = [];
    $msgs = [];
    while (false !== ($item = $dir->read())) {
        if (substr($item, 0, 1) != '.') {
            $fullpath = "{$localPath}\{$item}";
            $ius = $fileparser->parse($item, file_get_contents($fullpath));
            if ($ius) {
                $report = $fileparser->get_report();
                $msgs[] = get_string('afterparseinfo', 'auth_entsync', [
                    'file' => $item,
                    'nblines' => $report->parsedlines,
                    'nbusers' => $report->addedusers
                ]);
                $iuss[] = $ius;
            }
        }
    }
    $tmpstore = base_tmpstore::get_store();
    $tmpstore->set_progress_reporter($progress);
    foreach ($iuss as $ius) {
        $tmpstore->add_ius($ius);
    }
    $storeid = $tmpstore->save();
    $progress->end_progress();
    echo $OUTPUT->notification($msg, \core\output\notification::NOTIFY_SUCCESS);
    $localDone = true;
} else {
    $localDone = false;
    //TODO peut être checker is_canceled
    $entfiletype = "X";
    if ($formdata = $mform->get_data()) {
        // Il y a un fichier à charger
        // retrouver l'ent et le type de fichier.
        // Il est peut-être dans frozeneft.
        if ($formdata->frozeneft !== "X") {
            $entfiletype = $formdata->frozeneft;
        } else {
            $entfiletype = $formdata->entfiletype;
        }
        list($ent, $filetype) = bulk_form::decodeeft($entfiletype);
        if ((!$ent) || (!$ent->is_enabled())) {
            // Ne devrait pas se produire.
            redirect($returnurl,
                'Erreur', null, \core\output\notification::NOTIFY_ERROR);
        }
        
        $storeid = optional_param('storeid', null, PARAM_INT);
        
        if ($filename = $mform->get_new_filename('userfile')) {
            $progress = new \core\progress\display_if_slow('veuillez patienter...', 0);
            $progress->set_display_names();
            
            $fileparser = $ent->get_fileparser($filetype);
            $fileparser->set_progress_reporter($progress);
            $filename = $mform->get_new_filename('userfile');
            $progress->start_progress('', 2);
            $ius = $fileparser->parse($filename, $mform->get_file_content('userfile'));
            
            if ($ius) {
                // Le chargement s'est bien passé.
                $report = $fileparser->get_report();
                
                $tmpstore = base_tmpstore::get_store($storeid);
                $tmpstore->set_progress_reporter($progress);
                $tmpstore->add_ius($ius);
                $storeid = $tmpstore->save();
                
                $progress->end_progress();
                
                $msg = get_string('afterparseinfo', 'auth_entsync', [
                    'file' => $filename,
                    'nblines' => $report->parsedlines,
                    'nbusers' => $report->addedusers
                ]);
                echo $OUTPUT->notification($msg, \core\output\notification::NOTIFY_SUCCESS);
            } else {
                // Il y a eu une erreur.
                if ($ius === false) {
                    $parseerror = $fileparser->get_error();
                    $msg = "Erreur de chargement du fichier. $parseerror";
                } else {
                    $msg = 'Aucun utilisateur trouvé.';
                }
                $progress->end_progress();
                echo $OUTPUT->notification($msg, \core\output\notification::NOTIFY_ERROR);
            }
        } else {
            $msg = get_string('filemissingwarn', 'auth_entsync');
            echo $OUTPUT->notification($msg, \core\output\notification::NOTIFY_WARNING);
        }
        unset($_POST['userfile']);
    } else {
        $storeid = null;
    }
}

unset($_POST['frozeneft']);
$formparams['entfiletype'] = $entfiletype;

if ($storeid) {
    $tmpstore = base_tmpstore::get_store($storeid);
    $readytosyncusers = $tmpstore->count();
    if ($readytosyncusers > 0) {
        // Déjà au moins un utilisateur en attente de synchro,
        // on donne la possibilité de procéder à la synchronisation.
        $formparams['displayproceed'] = true;
        $already = usertblhelper::count_users($ent->get_profilesintype($filetype), $ent->get_code());
        $formparams['displayhtml'] = $OUTPUT->notification(get_string('infoproceed', 'auth_entsync',
            ['nbusers' => $readytosyncusers, 'profiltype' => $ent->get_filetypes()[$filetype],
                'alreadyusers' => $already
            ]),
            \core\output\notification::NOTIFY_INFO);
        $formparams['storeid'] = $storeid;
        $formparams['multi'] = $ent->accept_multifile($filetype);
        $mform = new bulk_form(null, $formparams);
        // On donne la possibilité d'envoyer un autre fichier mais le type de fichier ne doit pas changer.
        $dispinfo = false;
    } else {
        $mform = new bulk_form(null, $formparams);
        $dispinfo = true;
    }
} else  {
    $dispinfo = true;
}

echo $OUTPUT->heading_with_help(get_string('entsyncbulk', 'auth_entsync'), 'entsyncbulk', 'auth_entsync');

$mform->display();

if ($dispinfo) {
    $i = usertblhelper::count_users(1);
    $ii = usertblhelper::count_users(2);
    $iii = usertblhelper::count_users([1, 2]);
    echo "<ul><li>{$i} élèves</li><li>{$ii} enseignants</li><li>Total : {$iii}</li></ul>";
}
echo $OUTPUT->footer();

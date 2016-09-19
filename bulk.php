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

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once('ent_defs.php');
require_once(__DIR__ . '/lib/table.php');
require_once(__DIR__ . '/lib/tmpstore.php');
require_once('bulk_forms.php');

core_php_time_limit::raise(60*60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

require_login();
admin_externalpage_setup('authentsyncbulk');
require_capability('moodle/site:uploadusers', context_system::instance());

$returnurl = new moodle_url('/auth/entsync/bulk.php');

$config = get_config('auth_entsync');
if(auth_entsync_ent_base::count_enabled() <= 0) {
    // Aucun ent paramétré, on ne peut pas synchroniser
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string('entsyncbulk', 'auth_entsync'), 'entsyncbulk', 'auth_entsync');
    echo $OUTPUT->notification(get_string('notconfigwarn', 'auth_entsync',
        	   		"$CFG->wwwroot/auth/entsync/param.php"));
    echo $OUTPUT->footer();
    die;
}

if(optional_param('proceed', false, PARAM_BOOL) && confirm_sesskey()) {
    //il faut effectuer la synchro
    if(!isset($config->role_ens)) $config->role_ens = 0;

    //retrouver l'ent et le type de fichier
    $filetype = required_param('entfiletype', PARAM_TEXT);
    list($entcode, $filetype) = explode('.', $filetype, 2);
    $filetype = (int)$filetype;
    if((!$ent = auth_entsync_ent_base::get_ent($entcode)) || (!$ent->is_enabled())) {
        // ne devrait pas se produire
        redirect($returnurl,
            'Erreur', null, \core\output\notification::NOTIFY_ERROR);
    }

    //retrouver les données temporaires
    $storeid = required_param('storeid', PARAM_INT);
    $tmpstore = auth_entsync_tmpstore::get_store($storeid);

    $readytosyncusers = $tmpstore->count();
    if($readytosyncusers <= 0) {
        // ne devrait pas se produire
        redirect($returnurl,
            'Aucun utilisateur à synchroniser', null, \core\output\notification::NOTIFY_ERROR);
    }

    $synchronizer = $ent->get_synchronizer($filetype);
    
    //mode advanced...
    if(optional_param('advanced', false, PARAM_BOOL)) {
        if(optional_param('recupcas', false, PARAM_BOOL) && ($ent->get_mode() === 'cas')) {
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

    if($report) {
        //synchro ok
        $msg = get_string('aftersyncinfo', 'auth_entsync', $report);
        $msg = $OUTPUT->notification($msg, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // il y a eu une erreur
        $parseerror = $fileparser->get_error();
        $msg = "Erreur de synchronisation. {$parseerror}";
        $msg = $OUTPUT->notification($msg, \core\output\notification::NOTIFY_ERROR);
    }

	echo $OUTPUT->heading_with_help(get_string('entsyncbulk', 'auth_entsync'), 'entsyncbulk', 'auth_entsync');
	echo $msg;
//	var_dump($report);
	echo $OUTPUT->continue_button($returnurl);
	echo $OUTPUT->footer();
	die;
}


$formparams = array();
//gestion des option avancées
if(optional_param('advanced', false, PARAM_BOOL)) {
    $formparams['advanced'] = true;
}

//formulaire et chargement des fichiers
$mform = new auth_entsync_bulk_form(null, $formparams);

echo $OUTPUT->header();

if ($formdata = $mform->get_data()) {
	//il y a un fichier à charger
    
    //retrouver l'ent et le type de fichier
    list($entcode, $filetype) = explode('.', $formdata->entfiletype, 2);
    $filetype = (int)$filetype;
    if((!$ent = auth_entsync_ent_base::get_ent($entcode)) || (!$ent->is_enabled())) {
        // ne devrait pas se produire
        redirect($returnurl,
            'Erreur', null, \core\output\notification::NOTIFY_ERROR);
    }

    $storeid = optional_param('storeid', null, PARAM_INT);

    if($filename = $mform->get_new_filename('userfile')) {
        $progress = new \core\progress\display_if_slow('veuillez patienter...', 0);
        $progress->set_display_names();

        $fileparser = $ent->get_fileparser($filetype);
        $fileparser->set_progress_reporter($progress);
        $filename = $mform->get_new_filename('userfile');
        $progress->start_progress('', 2);
        $ius= $fileparser->parse($filename, $mform->get_file_content('userfile'));
        
        if ($ius) {
            // le chargement s'est bien passé
            $report = $fileparser->get_report();
            
            $tmpstore = auth_entsync_tmpstore::get_store($storeid);
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
//            var_dump($ius);
        } else {
            // il y a eu une erreur
            if($ius === false) {
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


if($storeid) {
    $tmpstore = auth_entsync_tmpstore::get_store($storeid);
    $readytosyncusers = $tmpstore->count();
    if($readytosyncusers > 0) {
        //déjà au moins un utilisateur en attente de synchro.
        //On donne la possibilité de procéder à la synchronisation
        $formparams['displayproceed'] = true;
        $already = auth_entsync_usertbl::count_users($ent->get_profilesintype($filetype)  ,$ent->get_code());
        $formparams['displayhtml'] = $OUTPUT->notification(get_string('infoproceed', 'auth_entsync',
            ['nbusers' => $readytosyncusers, 'profiltype' => $ent->get_filetypes()[$filetype],
                'alreadyusers' => $already
            ]),
            \core\output\notification::NOTIFY_INFO);
        $formparams['storeid'] = $storeid;
        $formparams['multi'] = $ent->accept_multifile($filetype);
        $mform =  new auth_entsync_bulk_form(null, $formparams);
        //on donne la possibilité d'envoyer un autre fichier mais le type de fichier ne doit pas changer
    } else {
        $mform = new auth_entsync_bulk_form(null, $formparams);
    }
}

echo $OUTPUT->heading_with_help(get_string('entsyncbulk', 'auth_entsync'), 'entsyncbulk', 'auth_entsync');

$mform->display();

$i = auth_entsync_usertbl::count_users(1);
$ii = auth_entsync_usertbl::count_users(2);
$iii =  auth_entsync_usertbl::count_users([1,2]);
echo "<ul><li>{$i} élèves</li><li>{$ii} enseignants</li><li>Total : {$iii}</li></ul>";

echo $OUTPUT->footer();
die;

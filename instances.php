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
 * Gestion des instances.
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
$entsync = \auth_entsync\container::services();
$conf = $entsync->query('conf');
$instances = $entsync->query('instances');

if (!$conf->is_gw()) {
    redirect($CFG->wwwroot);
}

require_login();
admin_externalpage_setup('authentsyncinst');
$sitecontext = context_system::instance();
require_capability('moodle/site:config', $sitecontext);

$returnurl = new moodle_url('/auth/entsync/instances.php');

$action = optional_param('action', 'list', PARAM_ACTION);
if ($action == 'del') {
    // Suppression d'une instance demandée.
    $id = required_param('id', PARAM_INT);
    $instance = $instances->instance($id);
    $rne = $instance->get('dir');
    $name = $instance->get('name');
    $confirm  = optional_param('confirm', '', PARAM_ALPHANUM);   // The md5 confirmation hash.
    // ... la suppression est-elle confirmée ?
    if ($confirm != md5($id)) {
        // ... non, alors on demande confirmation.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('delete'));
        $delurl = new moodle_url($returnurl,
            ['action' => 'del', 'id' => $id, 'confirm' => md5($id), 'sesskey' => sesskey()]);
        $confirmbutt = new single_button($delurl, get_string('delete'), 'post');
        echo $OUTPUT->confirm("Etes vous sur de vouloir supprimer l'instance {$rne}, {$name} ?",
        	$confirmbutt, $returnurl);
        echo $OUTPUT->footer();
        die();
    } else if (confirm_sesskey() && !empty($_POST)) {
        // ... oui, alors on supprime l'instance.
        try {
            $instance->delete();
            \core\notification::success("L'instance {$rne}, {$name} a été supprimée.");
        } catch (Exception $e) {
            \core\notification::error($e->getMessage());
        }
    }
    redirect($returnurl);
} else if ($action == 'edit') {
    // Modification d'une instance demandée.
    $id = optional_param('id', null, PARAM_INT);
    $PAGE->set_url(new moodle_url('/auth/entsync/instances.php', ['id' => $id, 'action' => 'edit']));
    $instance = null;
    if (!empty($id)) {
        $instance = $instances->instance($id);
    }
    $customdata = [
        'persistent' => $instance,
    ];
    $instance_form = $entsync->query('instance_form');
    $form = $instance_form->createForm($PAGE->url->out(false), $customdata);
    if ($form->is_cancelled()) {
        redirect($returnurl);
    } else if (($data = $form->get_data())) {
        try {
            if (empty($data->id)) {
                // If we don't have an ID, we know that we must create a new record.
                // Call your API to create a new persistent from this data.
                // Or, do the following if you don't want capability checks (discouraged).
                $instance = $instances->instance(0, $data);
                $instance->create();
            } else {
                // We had an ID, this means that we are going to update a record.
                // Call your API to update the persistent from the data.
                // Or, do the following if you don't want capability checks (discouraged).
                $instance->from_record($data);
                $instance->update();
            }
            \core\notification::success(get_string('changessaved'));
        } catch (Exception $e) {
            \core\notification::error($e->getMessage());
        }
        
        // Modif ou création effectuée, donc redirection vers la liste d'instances.
        redirect($returnurl);
    }
    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
} else {
    // Il faut afficher la liste des instances.
    $jumpurl = new moodle_url('/auth/entsync/jump.php');
    $editurl = new moodle_url($returnurl, ['action' => 'edit']);
    $delurl = new moodle_url($returnurl, ['action' => 'del']);
    $instancesList = $instances->get_instances([], 'dir');
    $t = new html_table();
    // Icons.
    $editico = $OUTPUT->pix_icon('t/edit', get_string('edit'));
    $delico = $OUTPUT->pix_icon('t/delete', get_string('delete'));
    $editattr = ['title' => get_string('edit')];
    $jumpattr = ['target' => '_blank', 'title' => get_string('login')];
    $t->head = ['Répertoire', 'Nom', 'RNE', get_string('actions')];
    foreach ($instancesList as $instance) {
        $id = $instance->get('id');
        $jumplnk = new moodle_url($jumpurl, ['inst' => $instance->get('dir')]);
        $editlnk = new moodle_url($editurl, ['id' => $id]);
        $dellnk = new moodle_url($delurl, ['id' => $id]);
        $row = [];
        $row[] = html_writer::link($jumplnk, $instance->get('dir'), $jumpattr);
        $row[] = html_writer::link($editlnk, $instance->get('name'), $editattr);
        $row[] = html_writer::link($editlnk, $instance->get('rne'), $editattr);
        $buttons = [];
        $buttons[] = html_writer::link($editlnk, $editico);
        $buttons[] = html_writer::link($dellnk, $delico);
        $row[] = implode(' ', $buttons);
        $t->data[] = new html_table_row($row);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('entsyncinst', 'auth_entsync'));
    $nbinst = count($instancesList);
    $plural = ($nbinst <= 1) ? '' : 's';
    echo html_writer::tag('p', "Total&nbsp;: {$nbinst} instance{$plural}");
    echo html_writer::table($t);
    echo $OUTPUT->single_button($editurl, 'Ajouter une instance', 'get');
    echo $OUTPUT->footer();
}

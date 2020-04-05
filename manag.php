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
 * Gestion des utilisateurs.
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

//TODO : gérer le format de cours par défaut

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once('ent_defs.php');

$views = ['cohort'];
$view = optional_param('view', 'none', PARAM_TEXT);
if(!in_array($view, $views))
{
    redirect(new moodle_url('/'));
}

require_login();
admin_externalpage_setup('authentsyncuser');
require_capability('moodle/site:config', context_system::instance());

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //something posted
    switch ($view) {
        case 'cohort':
            $msg = traite_cohort();
            break;
    }
}

echo $OUTPUT->header();

if(!empty($msg)) {
    echo $OUTPUT->notification($msg,
        core\output\notification::NOTIFY_INFO);
}

switch ($view) {
    case 'cohort':
        affiche_cohort();
        break;
}

echo $OUTPUT->footer();
die();

function affiche_cohort() {
    global $OUTPUT, $DB;
    $clist = $DB->get_records('cohort', ['component' => 'auth_entsync']);
    $ct = new html_table();
    $ct->head = ['', 'Nom', 'Identifiant cohorte', 'Effectif de la cohorte'];
    foreach($clist as $c) {
        $cb = "<input type=\"checkbox\" name=\"selectc[]\" value=\"{$c->id}\"></input>";
        $count = $DB->count_records('cohort_members', ['cohortid' => $c->id]);
        $row = [$cb, $c->name, $c->idnumber, $count];
        
        $ct->data[] = new html_table_row($row);
    }
    echo $OUTPUT->heading('Gestion des cohortes');
    ?>
<form method="post">
<p>
Les cohortes suivantes sont gérées par le plugin 'entsync'.
Vous pouvez ici supprimer les cohortes inutiles.
</p>
<?php
    echo html_writer::table($ct);
    ?>
<input name="suppr" type="submit" value="Supprimer" />
</form>
<?php
}

function traite_cohort() {
    global $DB;
    $msg = "";
    if (isset($_POST['suppr'])) {
        if(isset($_POST['selectc'])) {
            $select = $_POST['selectc'];
            if (count($select) > 0){
                $msg = '<p>Cohortes supprimées&nbsp;:</p><p>';
                $premier = true;
                foreach($select as $cid) {
                    $c = $DB->get_record('cohort', ['id' => $cid]);
                    cohort_delete_cohort($c);
                    if($premier) {
                        $msg .= $c->name;
                        $premier = false;
                    } else {
                        $msg .= ', ' . $c->name;
                    }
                }
                $msg .= '</p>';
            }
        }
    }
    return $msg;
}



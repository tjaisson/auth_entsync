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


require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once(__DIR__ . '/../lib/synchroniz.php');
require_once(__DIR__ . '/../lib/table.php');
require_once(__DIR__ . '/../ent_defs.php');

require_login();
admin_externalpage_setup('authentsyncuser');

echo $OUTPUT->header();
echo $OUTPUT->heading('debug');


$sql = "SELECT a.id, a.uid
FROM {auth_entsync_user} a
WHERE (SELECT COUNT(1) FROM {user} b
WHERE (a.userid = b.id) AND (b.deleted=0)
) = 0";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	//something posted
	
	if (isset($_POST['purge'])) {
		$list = $DB->get_records_sql($sql);
		foreach($list as $entu) {
			$DB->delete_records('auth_entsync_user', ['id' => $entu->id]);
		}
		echo "<p>purged</p>";
	} else {
		if (isset($_POST['release'])) {
			if(isset($_POST['selectc'])) {
				$select = $_POST['selectc'];
				foreach($select as $cid) {
					$c = $DB->get_record('cohort', ['id' => $cid]);
					$c->component = '';
					cohort_update_cohort($c);
				}
				echo "<p>released</p>";
			}
		}
	}
}

?>
<form method="post">

<h1>Utilisateurs non reliés</h1>

<?php

$list = $DB->get_records_sql($sql);

$t = new html_table();

$t->head = ['id', 'Id ENT'];

foreach($list as $entu) {
	$row = [$entu->id, $entu->uid];
	$t->data[] = new html_table_row($row);
}


echo html_writer::table($t);


?>

<input name="purge" type="submit" value="Purge" />

<h1>Cohortes gérées</h1>

<?php


$clist = $DB->get_records('cohort', ['component' => 'auth_entsync']);

$ct = new html_table();

$ct->head = ['coche', 'Nom', 'Identifiant cohorte'];

foreach($clist as $c) {
	$cb = "<input type=\"checkbox\" name=\"selectc[]\" value=\"{$c->id}\"></input>";
	$row = [$cb, $c->name, $c->idnumber];
	$ct->data[] = new html_table_row($row);
}



echo html_writer::table($ct);

?>

<input name="release" type="submit" value="Release" />
</form>

<?php


echo $OUTPUT->footer();
die;

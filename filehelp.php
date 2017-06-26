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


require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once('ent_defs.php');


require_login();
admin_externalpage_setup('authentsyncbulk');
require_capability('moodle/site:uploadusers', context_system::instance());

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('entsyncfilehelp', 'auth_entsync')); ?>
<p>Quel que soit l'ENT utilisé, vous devez importer les comptes des élèves et des enseignants de l'ENT dans Moodle. Le format des fichiers utilisateurs
est différent d'un ENT à l'autre. Cette page d'aide
détaille la façon dont ces fichiers sont obtenus. Vous pouvez ensuite les importer dans Moodle <a href="<?php echo "{$CFG->wwwroot}/auth/entsync/bulk.php"; ?>">ici</a>.</p>
<p>Seuls les ENT activés sont listés ici.</p><br />
<?php $i=1;
foreach(auth_entsync_ent_base::get_ents() as $ent) {
    if($ent->is_enabled()) {
        echo "{$i}.&nbsp;<a href='#ent{$ent->get_code()}'>{$ent->nomlong}</a><br />";
        ++$i;
    }
}

$i=1;

foreach(auth_entsync_ent_base::get_ents() as $ent) {
    if($ent->is_enabled()) {
//        $head = $OUTPUT->heading("{$i}.&nbsp;{$ent->nomlong}", 3);
//        echo "<a id = 'ent{$ent->get_code()}'>{$head}</a>";
        echo "<a id='ent{$ent->get_code()}'></a>";
        echo "<hr />";
        echo $OUTPUT->heading("{$i}.&nbsp;{$ent->nomlong}", 3);
        $ent->include_filehelp();
        ++$i;
    }
}

echo $OUTPUT->footer();
die;

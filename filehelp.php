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
require_once(__DIR__ . '/lib/table.php');
require_once('ent_defs.php');

require_login();
admin_externalpage_setup('authentsyncbulk');
require_capability('moodle/site:uploadusers', context_system::instance());

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('entsyncfilehelp', 'auth_entsync'));
$i=1;
foreach(auth_entsync_ent_base::get_ents() as $ent) {
    if($ent->is_enabled()) {
        echo "{$i}.&nbsp;<a href='#ent{$ent->get_code()}'>{$ent->nomlong}</a><br />";
        ++$i;
    }
}

$i=1;
foreach(auth_entsync_ent_base::get_ents() as $ent) {
    if($ent->is_enabled()) {
        echo "<a name = 'ent{$ent->get_code()}'></a>";
        echo $OUTPUT->heading("{$i}.&nbsp;{$ent->nomlong}", 3);
        $ent->include_filehelp();
        ++$i;
    }
}

echo $OUTPUT->footer();
die;

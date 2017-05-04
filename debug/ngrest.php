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
//require_once($CFG->dirroot.'/cohort/lib.php');
//require_once(__DIR__ . '/../lib/synchroniz.php');
//require_once(__DIR__ . '/../lib/table.php');
//require_once(__DIR__ . '/../lib/locallib.php');
//require_once(__DIR__ . '/../ent_defs.php');

require_once($CFG->libdir.'/filelib.php');


require_login();
admin_externalpage_setup('authentsyncuser');

echo $OUTPUT->header();
echo $OUTPUT->heading('debug');

$user = 'entsync';
$code = '';

$cu = new curl();
$cu->setopt(['CURLOPT_USERPWD' => $user . ':' . $code]);

//$url = new moodle_url('https://ent-ng.paris.fr/directory/structures?format=json');
//$url = new moodle_url('https://ent-ng.paris.fr/directory/user/admin/list?structureId=ab7650e7-d370-4018-a4a7-8bbe5d7744ce&profile=Teacher');
$url = new moodle_url('https://ent-ng.paris.fr/directory/user/structures/list?format=xml&uai=0752606A');


$ret = $cu->get($url);

echo '<pre>' . htmlspecialchars($ret) . '</pre>';

$dec = json_decode($ret);
var_dump($dec);

echo $OUTPUT->footer();
die;

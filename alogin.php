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
 * Connexion sso depuis le site maitre
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/filelib.php');

use \auth_entsync\sw\instance;

if (isloggedin() || instance::is_gw()) {
    redirect($CFG->wwwroot);
}

$ticket = optional_param('ticket', null, PARAM_RAW);

if (!$ticket) {
   // Pas de ticket.
    $jumpurl = new moodle_url(instance::gwroot() . '/auth/entsync/jump.php', ['inst' => instance::inst()]);
    redirect($jumpurl);
    die();
}

// Le ticket est présent.
$cu = new curl();
$valurl = new moodle_url(instance::gwroot() . '/auth/entsync/validate.php',
    ['inst' => instance::inst(), 'ticket' => $ticket]);
if (!($rep = $cu->get($valurl->out(false)))) {
    die();
}
if (!($rep === 'validate')) {
        die();
}

echo 'good';
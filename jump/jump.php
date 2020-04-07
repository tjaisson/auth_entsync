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

require(__DIR__ . '/../../../config.php');

if (!isloggedin()) {
    die();
}

if (!is_siteadmin()) {
    die();
}

$inst = optional_param('inst', null, PARAM_TEXT);

if (!$inst) {
    die();
}
$instance = \auth_entsync\farm\instance::get_record(['dir' => $inst]);
if (!$instance) {
    die();
}
$k = \auth_entsync\farm\iic::createToken(5, true);
$redirecturl = new moodle_url($instance->wwwroot() . '/auth/entsync/jump/login.php', $k);
redirect($redirecturl);

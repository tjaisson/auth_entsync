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
 * Connexion sso
 *
 * @package auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require(__DIR__ . '/../../config.php');
require_once('ent_defs.php');

// Try to prevent searching for sites that allow sign-up.
if (!isset($CFG->additionalhtmlhead)) {
    $CFG->additionalhtmlhead = '';
}
$CFG->additionalhtmlhead .= '<meta name="robots" content="noindex" />';

// HTTPS is required in this page when $CFG->loginhttps enabled.
$PAGE->https_required();

$context = context_system::instance();
$PAGE->set_url("$CFG->httpswwwroot/auth/entsync/login.php");
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');


$entclass = optional_param('ent', '', PARAM_RAW);

$redirect = $CFG->wwwroot.'/';

if (empty($entclass)) {
    redirect($redirect);
}

if (!$ent = auth_entsync_ent_base::get_ent($entclass)) {
    redirect($redirect);
}

if (!$ent->is_sso()) {
    redirect($redirect);
}

if ($ent->get_mode() !== 'cas') {
    redirect($redirect);
}

$cas = $ent->get_casconnector();
$clienturl = new moodle_url("$CFG->httpswwwroot/auth/entsync/logout.php", ['ent' => $entclass]);
$cas->set_clienturl($clienturl);

if (!$cas->read_ticket()) {
    redirect($redirect);
}

if ($val = $cas->validate_ticket()) {
    $cas->redirtohome();
} else {
    redirect($redirect);
}

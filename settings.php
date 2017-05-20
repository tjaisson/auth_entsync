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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading('entsync/head', new lang_string('settings'),
        new lang_string('paramredirect', 'auth_entsync', "{$CFG->wwwroot}/auth/entsync/param.php")));

    $ADMIN->add('users', new admin_category('entsynccat', new lang_string('enttool', 'auth_entsync')));
    $ADMIN->add('entsynccat', new admin_externalpage('authentsyncparam',
        new lang_string('entsyncparam', 'auth_entsync'),
        "$CFG->wwwroot/auth/entsync/param.php",
        'moodle/site:config'));
    if (is_enabled_auth('entsync')) {
        $ADMIN->add('entsynccat',
            new admin_externalpage('authentsyncbulk',
            new lang_string('entsyncbulk', 'auth_entsync'),
            "$CFG->wwwroot/auth/entsync/bulk.php",
            'moodle/site:uploadusers'));
        $ADMIN->add('entsynccat',
            new admin_externalpage('authentsyncuser',
            new lang_string('entsyncuser', 'auth_entsync'),
            "$CFG->wwwroot/auth/entsync/users.php",
            'moodle/user:viewdetails'));
    }
}
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
 * Strings for component 'auth_entsync', language 'en'.
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['auth_entsyncdescription'] = 'ENT users authentication plugin.';
$string['pluginname'] = 'ENTSYNC authentication';
//admin menu
$string['enttool'] = 'Managed users';

//auth
$string['mdlauth'] = 'Other user';

//param
$string['entsyncparam'] = 'Settings';
$string['entsyncparam_help'] = 'You can configure WNE connector settings';
$string['entlist'] = 'Available WNEs';
$string['entname'] = 'Name';
$string['sso'] = 'SSO';
$string['connecturl'] = 'Connect URL';
$string['rolepersselect'] = 'Employe\'s system role';
$string['roleensselect'] = 'Teacher\'s system role';
$string['entspecparam'] = '{$a} settings';

$string['pluginnotenabledwarn'] = 'WARNING : ENTSYNC authentication plugin is not enabled<br />
Go to <a href=\'{$a}\'>Manage authentication</a>';
$string['paramredirect'] = 'Settings are in "Managed users".<br />
Go to <a href=\'{$a}\'>Settings</a>';

//bulk
$string['entsyncbulk'] = 'Synchronize users';
$string['entsyncbulk_help'] = 'Users may be uploaded (and optionally enrolled in courses) via text file.';
$string['notconfigwarn'] = 'No enabled WNE.<br />
Go to <a href=\'{$a}\'>Settings</a>';
$string['filetypeselect'] = 'File type';
$string['filetypemissingwarn'] = 'Choose a file type';
$string['filemissingwarn'] = 'Choose a file';

$string['dosync'] = 'Synchronize';
$string['proceed'] = 'Proceed with synchronization';
$string['infoproceed'] = 'INFO : {$a->nbusers} users ({$a->profiltype}) ready to be synchronised.';
$string['uploadadd'] = 'Or upload another file if needed';
$string['uploadaddinfo'] = 'Upload another file if needed';
$string['afterparseinfo'] = 'File {$a->file} upload success.<br />
{$a->nbusers} users found.';
$string['aftersyncinfo'] = 'Synchronization OK';

///user
$string['entsyncuser'] = 'Users list';
$string['entsyncuser_help'] = 'Users list';

//login
$string['notauthorized'] = '{$a->ent} authentication succeded but user "{$a->user}" is not alloved on this moodle.<br />
An administrator can allow this user.';

//educ'horus
$string['educhoruscashost'] = 'Hostname of Educ\'Horus CAS server';
$string['educhoruscashost_help'] = 'eg: educhorus.enteduc.fr';
$string['educhoruscaspath'] = 'Base URI';
$string['educhoruscaspath_help'] = 'Usually RNE.<br />eg: 0750677D';


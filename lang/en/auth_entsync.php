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
$string['entlist'] = 'Available WNEs';
$string['entlist_help'] = 'You can configure WNE connector settings';
$string['entlist_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';
$string['entname'] = 'Name';
$string['sso'] = 'SSO';
$string['connecturl'] = 'Connect URL';
$string['rolepersselect'] = 'Employe\'s system role';
$string['roleensselect'] = 'Teacher\'s system role';
$string['roleensselect_help'] = 'Choose system role you vant to assign to teachers.';
$string['entspecparam'] = '{$a} settings';
$string['pluginnotenabledwarn'] = 'WARNING : ENTSYNC authentication plugin is not enabled<br />
Go to <a href=\'{$a}\'>Manage authentication</a>';
$string['paramredirect'] = 'Settings are in "Managed users".<br />
Go to <a href=\'{$a}\'>Settings</a>';

//aide ent
$string['entsyncenthelp'] = 'Help on ENT';
$string['entsyncenthelpintro'] = 'This page';

//bulk
$string['entsyncbulk'] = 'Synchronize users';
$string['entsyncbulk_help'] = 'Users may be synchronized via file.';
$string['entsyncbulk_link'] = '%%WWWROOT%%/auth/entsync/filehelp.php';
$string['notconfigwarn'] = 'No enabled WNE.<br />
Go to <a href=\'{$a}\'>Settings</a>';
$string['filetypeselect'] = 'File type';
$string['filetypeselect_help'] = 'Select the type of the file you\'re uploading. You can find more help via the link below.';
$string['filetypeselect_link'] = '%%WWWROOT%%/auth/entsync/filehelp.php';
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

//file help
$string['entsyncfilehelp'] = 'How to retrieve users files';


///user
$string['entsyncuser'] = 'Users list';
$string['entsyncuser_help'] = 'Users list';

//task
$string['garbage'] = 'ENTSYNC Clean up';

//login
$string['notauthorized'] = '{$a->ent} authentication succeded but user "{$a->user}" is not alloved on this moodle.<br />
An administrator can allow this user.';

//comptes locaux
$string['entlocal'] = 'Local accounts';
$string['entlocal_help'] = 'Activate this if you don\'t have ENT for your structure.';
$string['entlocal_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//PCN
$string['entpcn'] = 'PCN';
$string['entpcn_help'] = 'Activate "PCN" if you use Paris Classe Numérique.';
$string['entpcn_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//lilie
$string['entmonlyceenet'] = 'Lilie';
$string['entmonlyceenet_help'] = 'Activate "Lilie" if you use Lilie.';
$string['entmonlyceenet_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//Open ENT NG
$string['entng'] = 'Open ENT NG';
$string['entng_help'] = 'Activate "Open ENT NG" if you use Open ENT NG.';
$string['entng_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//educ'horus
$string['enteduchorus'] = 'Educ\'Horus';
$string['enteduchorus_help'] = 'Activate "Educ\'Horus" if you use Educ\'Horus.';
$string['enteduchorus_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

$string['educhoruscashost'] = 'Hostname of Educ\'Horus CAS server';
$string['educhoruscashost_help'] = 'eg: educhorus.enteduc.fr';
$string['educhoruscaspath'] = 'Base URI';
$string['educhoruscaspath_help'] = 'Usually RNE.<br />eg: 0750677D';


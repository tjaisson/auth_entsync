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
$string['enttool'] = 'School users';

//auth
$string['mdlauth'] = 'Other user';

//param
$string['entsyncparam'] = 'Settings';
$string['entsyncparam_help'] = 'School users management simplify teacher and student account creation.';
$string['entlist'] = 'Available systems (ENT)';
$string['entlist_help'] = 'You can configure ENT connector settings';
$string['entlist_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';
$string['entname'] = 'Name';
$string['sso'] = 'SSO';
$string['connecturl'] = 'Connect URL';
$string['rolepersselect'] = 'Employe\'s system role';
$string['roleensselect'] = 'Teacher\'s system role';
$string['roleensselect_help'] = 'Choose system role you want to assign to teachers.';
$string['entspecparam'] = '{$a} settings';

$string['plugindesc'] = 'ENT users authentication plugin.';
$string['pluginnotenabledwarn'] = 'INFO : ENTSYNC authentication plugin is not enabled<br />
Go to <a href=\'{$a}\'>Manage authentication</a>';
$string['paramredirect'] = 'Settings are in "Managed users".<br />
Go to <a href=\'{$a}\'>Settings</a>';

//aide ent
$string['entsyncenthelp'] = 'Help on ENT SSO connectors';
$string['entsyncenthelpintro'] = 'This page present help on SSO';

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

//instances
$string['entsyncinst'] = 'PAM instances list';

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
$string['entmonlyceenet'] = 'monlycée.net';
$string['entmonlyceenet_help'] = 'Activate "monlycée.net" if you use monlycée.net.';
$string['entmonlyceenet_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//Open ENT NG Collèges
$string['entngcrif'] = 'Open ENT NG Collèges';
$string['entngcrif_help'] = 'Activate "Open ENT NG Collèges" if you use Open ENT NG from "Ville de Paris".';
$string['entngcrif_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//Open ENT NG Lycées
$string['entng'] = 'Open ENT NG Lycées';
$string['entng_help'] = 'Activate "Open ENT NG Lycées" if you use Open ENT NG from "Île de France".';
$string['entng_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

//educ'horus
$string['enteduchorus'] = 'Educ\'Horus';
$string['enteduchorus_help'] = 'Activate "Educ\'Horus" if you use Educ\'Horus.';
$string['enteduchorus_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

$string['educhoruscashost'] = 'Hostname of Educ\'Horus CAS server';
$string['educhoruscashost_help'] = 'eg: educhorus.enteduc.fr';
$string['educhoruscaspath'] = 'Base URI';
$string['educhoruscaspath_help'] = 'Usually RNE.<br />eg: 0750677D';

//scribe/envole
$string['entenvole'] = 'Scribe / Envole';
$string['entenvole_help'] = 'Activate "Scribe / Envole" if you use Scribe.';
$string['entenvole_link'] = '%%WWWROOT%%/auth/entsync/enthelp.php';

$string['envolecashost'] = 'Hostname of Envole extranet';
$string['envolecashost_help'] = 'eg: extranet.lyc-elisa-lemonnier.ac-paris.fr';

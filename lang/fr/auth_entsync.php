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
 * Strings for component 'auth_entsync', language 'fr'.
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['auth_entsyncdescription'] = 'Ce plugin permet de gérer l\'authentification des utilisateurs des ENT';
$string['pluginname'] = 'Authentification ENTSYNC';
//admin menu
$string['enttool'] = 'Utilisateurs de l\'établissement';

//auth
$string['mdlauth'] = 'Autre utilisateur';

//param
$string['entsyncparam'] = 'Configuration';
$string['entsyncparam_help'] = 'Vous pouvez configurer le mode de gestion des utilisateurs de l\'établissement';

$string['entlist'] = 'ENTs disponibles';
$string['entname'] = 'Nom';
$string['sso'] = 'SSO';
$string['connecturl'] = 'URL du connecteur';
$string['rolepersselect'] = 'Rôle système des personnels';
$string['roleensselect'] = 'Rôle système des enseignants';
$string['entspecparam'] = 'Configuration pour {$a}';


$string['pluginnotenabledwarn'] = 'ATTENTION : le plugin d\'authentification ENTSYNC n\'est pas activé.<br />
La gestion des utilisateurs de l\'établissement ne sera pas fonctionnelle tant que vous ne l\'aurez pas activé<br />
Aller à <a href=\'{$a}\'>Gestion de l\'authentification</a>';
$string['paramredirect'] = 'Le paramétrage se fait dans "Utilisateurs de l\'établissement".<br />
Aller à <a href=\'{$a}\'>Configuration</a>';

//bulk
$string['entsyncbulk'] = 'Synchronisation';
$string['entsyncbulk_help'] = 'La synchronisation permet de mettre à jour la liste des utilisateurs à l\'aide de fichiers obtenus dans les applications tierces.<br /><br />
<b>Attention</b>, les utilisateurs qui ne sont plus présents dans les fichiers importés seront archivés puis automatiquement supprimés au bout de 6 mois.<br /><br />
<b>Note</b> : il est possible de combiner plusieurs fichiers du même type en les chargeant successivement avant de procéder à la synchronisation.<br />
Cela est utile si plusieurs structures sont rattachées à ce moodle (dans le cas d\'une cité scolaire, par exemple).';

$string['notconfigwarn'] = 'Aucun ENT paramétré.<br />
Aller à <a href=\'{$a}\'>Configuration</a>';
$string['filetypeselect'] = 'Type du fichier';
$string['filetypeselect_help'] = 'Sélectionnez le type de fichier utilisateurs que vous voulez déposer. Plus d\'information sur la façon d\'obtenir les fichiers utilisateurs dans les ENT via le lien ci-dessous.';

$string['filetypemissingwarn'] = 'Selectionner un type de fichier';
$string['filemissingwarn'] = 'Choisir un fichier';

$string['dosync'] = 'Synchroniser';
$string['proceed'] = 'Procéder à la synchonisation';
$string['infoproceed'] = 'INFO : {$a->nbusers} utilisateurs ({$a->profiltype}) prêts à être synchronisés avec les
{$a->alreadyusers} utilisateurs existants.<br />
ATTENTION : les utilisateurs existants qui ne sont plus présents dans le fichier seront archivés puis automatiquement supprimés après 6 mois.';
$string['uploadadd'] = 'Ou déposer un autre fichier si besoin (cas d\'une cité scolaire...)';
$string['uploadaddinfo'] = 'IMPORTANT : si plusieurs structures sont rattachées à ce moodle
(cas d\'une cité scolaire, par exemple),
alors il faut les synchroniser en même temps.<br />
Par exemple, il faut charger le fichier des élèves du collège et celui des élèves du lycée
avant de procéder à la synchronisation.';
$string['afterparseinfo'] = 'Fichier {$a->file} lut avec succès.<br />
{$a->nbusers} utilisateurs trouvés.';
$string['aftersyncinfo'] = 'Synchronisation réussie.<br />
{$a->updated} utilisateurs mis à jours<br />
{$a->created} utilisateurs créés';

//file help
$string['entsyncfilehelp'] = 'Aide sur les types de fichiers utilisateurs pour les ENT activés';

//user
$string['entsyncuser'] = 'Listes d\'utilisateurs';
$string['entsyncuser_help'] = 'Il est possible de lister les utilisateurs de l\'établissement.';

//login
$string['notauthorized'] = 'Authentification {$a->ent} réussie mais l\'utilisateur "{$a->user}" n\'est pas autorisé à accèder à ce moodle.<br />
Un administrateur peut autoriser l\'utilisateur.';

//educ'horus
$string['educhoruscashost'] = 'Nom d\'hôte du serveur cas Educ\'Horus';
$string['educhoruscashost_help'] = 'Habituellement : educhorus.enteduc.fr';
$string['educhoruscaspath'] = 'URI de base';
$string['educhoruscaspath_help'] = 'Habituellement le RNE de l\'établissement.<br />Par exemple : 0750677D';


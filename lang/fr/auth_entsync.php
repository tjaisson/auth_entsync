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
$string['entsyncparam_help'] = 'La gestion des <b>Utilisateurs de l\'établissement</b> permet de simplifier la création des comptes des élèves et des enseignants
en important des fichiers standarts en provenance de Siècle ou d\'un ENT.
Si votre établissement utilise l\'un des ENT supportés, vous pouvez également mettre en place un connecteur d\'authentification unique (sso).';
$string['entlist'] = 'ENT disponibles';
$string['entlist_help'] = 'Vous pouvez activer ici la connexion avec l\'ENT de votre établissement.<br /><br />
Plus d\'information sur la procédure de mise en place côté ENT via le lien ci-dessous.';
$string['entname'] = 'Nom';
$string['sso'] = 'SSO';
$string['connecturl'] = 'URL du connecteur ou du signet à mettre en place côté ENT';
$string['rolepersselect'] = 'Rôle système des personnels';
$string['roleensselect'] = 'Rôle système des enseignants';
$string['roleensselect_help'] = 'Choisissez le rôle système que vous voulez attribuer aux enseignants&nbsp;:<ul>
<li>choissisez \'Aucun\' si vous voulez que l\'administrateur crée les cours pour les enseignants,</li>
<li>choissisez \'Créateur de cours\' ou \'Créateur de cours et catégories\'
si vous voulez que les enseignants soient autonomes pour créer leurs cours.</li></ul>';
$string['entspecparam'] = 'Configuration pour {$a}';


$string['pluginnotenabledwarn'] = 'NOTE : le plugin d\'authentification ENTSYNC n\'est pas activé.<br />
La gestion des utilisateurs de l\'établissement ne sera pas fonctionnelle tant que vous ne l\'aurez pas activé<br />
Aller à <a href=\'{$a}\'>Gestion de l\'authentification</a>';
$string['paramredirect'] = 'Le paramétrage se fait dans "Utilisateurs de l\'établissement".<br />
Aller à <a href=\'{$a}\'>Configuration</a>';

//aide ent
$string['entsyncenthelp'] = 'Aide sur la mise en place des connecteurs ENT';
$string['entsyncenthelpintro'] = 'Cette page présente l\'aide à la mise en place des connecteurs avec les ENT.<br /><br />
<b>NOTE</b> : Quel que soit l\'ENT utilisé, vous devez également importer les comptes des élèves et des enseignants de l\'ENT dans Moodle.
Ceci se fait dans <a href=\'{$a}\'>Importation des utilisateurs</a>. L\'aide concernant les fichiers d\'importation y est également disponible.';

//bulk
$string['entsyncbulk'] = 'Importation des utilisateurs';
$string['entsyncbulk_help'] = 'L\'importation permet de mettre à jour les comptes des utilisateurs à l\'aide de fichiers obtenus dans les applications tierces (ENT).<br /><br />
<b>Attention</b>, les utilisateurs qui ne sont plus présents dans les fichiers importés seront archivés puis automatiquement supprimés au bout de 6 mois.<br /><br />
<b>Note</b> : pour certains ENT, il est possible de combiner plusieurs fichiers du même type en les chargeant successivement avant de procéder à l\'importation.<br />
Cela est utile si plusieurs structures sont rattachées à ce moodle (dans le cas d\'une cité scolaire, par exemple).';
$string['notconfigwarn'] = 'Aucun ENT n\'est activé.<br />
Aller à <a href=\'{$a}\'>Configuration</a> pour le faire.';
$string['filetypeselect'] = 'Type du fichier';
$string['filetypeselect_help'] = 'Sélectionnez le type de fichier utilisateurs que vous voulez déposer.<br /><br />Plus d\'information sur la procédure permettant d\'obtenir ces fichiers utilisateurs dans les applications tierces (ENT) via le lien ci-dessous.';
$string['filetypemissingwarn'] = 'Selectionner un type de fichier';
$string['filemissingwarn'] = 'Choisir un fichier';
$string['dosync'] = 'Importation';
$string['proceed'] = 'Procéder à l\'importation';
$string['infoproceed'] = 'INFO : {$a->nbusers} utilisateurs ({$a->profiltype}) prêts à être synchronisés avec les
{$a->alreadyusers} utilisateurs existants.<br />
ATTENTION : les utilisateurs existants qui ne sont plus présents dans le fichier seront archivés puis automatiquement supprimés après 6 mois.';
$string['uploadadd'] = 'Ou déposer un autre fichier si besoin (cas d\'une cité scolaire...)';
$string['uploadaddinfo'] = 'IMPORTANT : si plusieurs structures sont rattachées à ce moodle
(cas d\'une cité scolaire, par exemple),
alors il faut les importer en même temps.<br />
Par exemple, il faut charger le fichier des élèves du collège <b>et</b> celui des élèves du lycée
avant de procéder à l\'importation.';
$string['afterparseinfo'] = 'Le fichier {$a->file} a été lut avec succès : {$a->nbusers} utilisateurs trouvés.<br />
Vous devez maintenant procéder à l\'importation de ces utilisateurs.';
$string['aftersyncinfo'] = 'Importation réussie.<br />
{$a->updated} utilisateurs mis à jours<br />
{$a->created} utilisateurs créés';

//file help
$string['entsyncfilehelp'] = 'Comment obtenir les fichiers utilisateurs dans les ENT';

//user
$string['entsyncuser'] = 'Listes d\'utilisateurs';
$string['entsyncuser_help'] = 'Il est possible de lister les utilisateurs de l\'établissement.';

//task
$string['garbage'] = 'Nettoyage ENTSYNC';

//login
$string['notauthorized'] = 'Authentification {$a->ent} réussie mais l\'utilisateur "{$a->user}" n\'est pas autorisé à accèder à ce moodle.<br />
Un administrateur peut autoriser l\'utilisateur.';

//comptes locaux
$string['entlocal'] = 'Comptes locaux';
$string['entlocal_help'] = 'Activez "Comptes locaux" si votre établissement ne dispose pas d\'un ENT.<br />
Cela vous permet de créer les comptes utilisateurs pour les élèves et les enseignants en important les fichiers "Siècle".';

//PCN
$string['entpcn'] = 'PCN';
$string['entpcn_help'] = 'Activez "PCN" si votre établissement utilise l\'ENT Paris Classe Numérique.<br />
Vous devez par ailleurs importer les comptes utilisateurs des élèves et des enseignants.';

//lilie
$string['entmonlyceenet'] = 'Lilie';
$string['entmonlyceenet_help'] = 'Activez "monlycée.net" si votre établissement utilise l\'ENT régional monlycée.net.<br />
Vous devez par ailleurs importer les comptes utilisateurs des élèves et des enseignants.';
    
//Open ENT NG
$string['entng'] = 'Open ENT NG';
$string['entng_help'] = 'Activez "Open ENT NG" si votre établissement utilise l\'ENT Open ENT NG.<br />
Vous devez par ailleurs importer les comptes utilisateurs des élèves et des enseignants.';

//educ'horus
$string['enteduchorus'] = 'Educ\'Horus';
$string['enteduchorus_help'] = 'Activez "Educ\'Horus" si votre établissement utilise Educ\'Horus.<br />
Des paramètres spécifiques à Educ\'Horus vous seront demandés.<br /> 
Vous devez par ailleurs importer les comptes utilisateurs des élèves et des enseignants.';

$string['educhoruscashost'] = 'Nom d\'hôte du serveur cas Educ\'Horus';
$string['educhoruscashost_help'] = 'Habituellement : educhorus.enteduc.fr';
$string['educhoruscaspath'] = 'URI de base';
$string['educhoruscaspath_help'] = 'Habituellement le RNE de l\'établissement.<br />Par exemple : 0750677D';

//scribe/envole
$string['entenvole'] = 'Scribe / Envole';
$string['entenvole_help'] = 'Activez "Scribe / Envole" si vous utilisez un serveur Scribe dans votre établissement et que vous souhaitez mettre en place l\'authentification par l\'extranet Envole.<br /> 
Vous devez par ailleurs importer les comptes utilisateurs des élèves et des enseignants.';

$string['envolecashost'] = 'Nom d\'hôte de l\'extranet Envole de l\'établissement';
$string['envolecashost_help'] = 'Par exemple : extranet.lyc-elisa-lemonnier.ac-paris.fr';

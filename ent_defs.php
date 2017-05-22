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
 * Enregistrement des ents à prendre en charge
 *
 * 1 -> lilie (bientôt monlycee.net)
 * 2 -> pcn
 * 3 -> open ent ng
 *
 * Il est possible d'ajouter de nouveaux ENT
 * mais le code de chacun doit être non nul, unique et immuable
 * le code 0 est réservé pour 'aucun ent'
 *
 * TODO : définir le pseudo ent 'siecle' avec méthode d'authentification 'manual'
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/ents/base.php');

/* Définition des différents ENT pris en charge
 * pour chaque ent : require_once + register
 * attention : le code choisi doit être non nul, unique et immuable
 *
 * L'ordre des register fait l'ordre de la liste
 *
 * toujoursn appeler end_register() à la fin
 */
require_once(__DIR__ . '/ents/local.php');
auth_entsync_ent_base::register('local', 4);

require_once(__DIR__ . '/ents/envole.php');
auth_entsync_ent_base::register('envole', 6);

require_once(__DIR__ . '/ents/ents_paris.php');
auth_entsync_ent_base::register('pcn', 2);
auth_entsync_ent_base::register('monlyceenet', 1);
auth_entsync_ent_base::register('ng', 3);

require_once(__DIR__ . '/ents/educ.php');
auth_entsync_ent_base::register('educhorus', 5);

auth_entsync_ent_base::end_register();

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
 * Bulk user registration functions
 *
 * @package    tool
 * @subpackage uploaduser
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function uu_process_template($template, $user) {
	if (is_array($template)) {
		// hack for for support of text editors with format
		$t = $template['text'];
	} else {
		$t = $template;
	}
	if (strpos($t, '%') === false) {
		return $template;
	}

	$username  = isset($user->username)  ? $user->username  : '';
	$firstname = isset($user->firstname) ? $user->firstname : '';
	$lastname  = isset($user->lastname)  ? $user->lastname  : '';

	$callback = partial('uu_process_template_callback', $username, $firstname, $lastname);

	$result = preg_replace_callback('/(?<!%)%([+-~])?(\d)*([flu])/', $callback, $t);

	if (is_null($result)) {
		return $template; //error during regex processing??
	}

	if (is_array($template)) {
		$template['text'] = $result;
		return $t;
	} else {
		return $result;
	}
}

/**
 * Internal callback function.
 */
function uu_process_template_callback($username, $firstname, $lastname, $block) {
	switch ($block[3]) {
		case 'u':
			$repl = $username;
			break;
		case 'f':
			$repl = $firstname;
			break;
		case 'l':
			$repl = $lastname;
			break;
		default:
			return $block[0];
	}

	switch ($block[1]) {
		case '+':
			$repl = core_text::strtoupper($repl);
			break;
		case '-':
			$repl = core_text::strtolower($repl);
			break;
		case '~':
			$repl = core_text::strtotitle($repl);
			break;
	}

	if (!empty($block[2])) {
		$repl = core_text::substr($repl, 0 , $block[2]);
	}

	return $repl;
}

<?php


defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 */

function xmldb_auth_entsync_upgrade($oldversion) {
	global $DB;
	$dbman = $DB->get_manager();
	
    return true;
}

<?php


defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 */

function xmldb_auth_entsync_upgrade($oldversion) {
	global $DB;
	$dbman = $DB->get_manager();
    
	
	if ($oldversion < 2016081100) {
	
	    // Define table auth_entsync_user to be created.
	    $table = new xmldb_table('auth_entsync_user');
	
	    // Adding fields to table auth_entsync_user.
	    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
	    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
	    $table->add_field('ent', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
	    $table->add_field('sync', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
	    $table->add_field('uid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
	    $table->add_field('struct', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
	    $table->add_field('profile', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
	    $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, null, null, '-1');
	    $table->add_field('checked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
	    $table->add_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
	    $table->add_field('archivedsince', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
	
	    // Adding keys to table auth_entsync_user.
	    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
	
	    // Adding indexes to table auth_entsync_user.
	    $table->add_index('userid', XMLDB_INDEX_UNIQUE, array('userid', 'ent'));
	    $table->add_index('uid', XMLDB_INDEX_NOTUNIQUE, array('uid'));
	
	    // Conditionally launch create table for auth_entsync_user.
	    if (!$dbman->table_exists($table)) {
	        $dbman->create_table($table);
	    }
	
	    // Entsync savepoint reached.
	    upgrade_plugin_savepoint(true, 2016081100, 'auth', 'entsync');
	}
	
	
	
    
    return true;
}

<?php


defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 */

function xmldb_auth_entsync_upgrade($oldversion) {
	global $DB;
	$dbman = $DB->get_manager();
    
	
	
	if ($oldversion < 2016090700) {
	
	    // Define table auth_entsync_tmpul to be dropped.
	    $table = new xmldb_table('auth_entsync_tmpul');
	
	    // Conditionally launch drop table for auth_entsync_tmpul.
	    if ($dbman->table_exists($table)) {
	        $dbman->drop_table($table);
	    }
	    
	    // Define field checked to be dropped from auth_entsync_user.
	    $table = new xmldb_table('auth_entsync_user');
	    $field = new xmldb_field('checked');
	    
	    // Conditionally launch drop field checked.
	    if ($dbman->field_exists($table, $field)) {
	        $dbman->drop_field($table, $field);
	    }
	    
	    $index = new xmldb_index('uid', XMLDB_INDEX_NOTUNIQUE, array('uid'));
	    
	    // Conditionally launch drop index uid.
	    if ($dbman->index_exists($table, $index)) {
	        $dbman->drop_index($table, $index);
	    }
	    
	    $index = new xmldb_index('uid', XMLDB_INDEX_UNIQUE, array('uid', 'ent'));
	    
	    // Conditionally launch add index uid.
	    if (!$dbman->index_exists($table, $index)) {
	        $dbman->add_index($table, $index);
	    }
	    
	
	    // Entsync savepoint reached.
	    upgrade_plugin_savepoint(true, 2016090700, 'auth', 'entsync');
	}
	
	if ($oldversion < 2016091400) {
	
	    // Define index uid (unique) to be dropped form auth_entsync_user.
	    $table = new xmldb_table('auth_entsync_user');
	    $index = new xmldb_index('uid', XMLDB_INDEX_UNIQUE, array('ent', 'uid'));
	
	    // Conditionally launch drop index uid.
	    if ($dbman->index_exists($table, $index)) {
	        $dbman->drop_index($table, $index);
	    }
	    
	    $index = new xmldb_index('uid', XMLDB_INDEX_NOTUNIQUE, array('ent', 'uid'));
	    
	    // Conditionally launch add index uid.
	    if (!$dbman->index_exists($table, $index)) {
	        $dbman->add_index($table, $index);
	    }
	     
	
	    // Entsync savepoint reached.
	    upgrade_plugin_savepoint(true, 2016091400, 'auth', 'entsync');
	}
	
	
    return true;
}

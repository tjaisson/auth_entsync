<?php


defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 */

function xmldb_auth_entsync_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2016091701) {

        // Define table auth_entsync_instances to be created.
        $table = new xmldb_table('auth_entsync_instances');

        // Adding fields to table auth_entsync_instances.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('dir', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('rne', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, null, null, null);

        // Adding keys to table auth_entsync_instances.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for auth_entsync_instances.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Entsync savepoint reached.
        upgrade_plugin_savepoint(true, 2016091701, 'auth', 'entsync');
    }

    return true;
}

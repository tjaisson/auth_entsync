<?php

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);


//ce script désactive un module d'activité

//nom du module à désactiver
$modname = 'hvp';


require(dirname(dirname(dirname(__FILE__))).'/config.php');

if ($module = $DB->get_record("modules", array("name"=>$modname))) {

    $DB->set_field("modules", "visible", "0", array("id"=>$module->id)); // Hide main module
    // Remember the visibility status in visibleold
    // and hide...
    $sql = "UPDATE {course_modules}
                       SET visibleold=visible, visible=0
                     WHERE module=?";
    $DB->execute($sql, array($module->id));
    // Increment course.cacherev for courses where we just made something invisible.
    // This will force cache rebuilding on the next request.
    increment_revision_number('course', 'cacherev',
        "id IN (SELECT DISTINCT course
                                    FROM {course_modules}
                                   WHERE visibleold=1 AND module=?)",
        array($module->id));
    core_plugin_manager::reset_caches();
}
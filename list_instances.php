<?php

// Pas besoin de la session.
define('NO_MOODLE_COOKIE', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json');
$lst = [];
$instances = \auth_entsync\persistents\instance::get_records([], 'name');
foreach ($instances as $instance) {
    if ($instance->get('rne') !== '00') {
        $lst[] = ['dir' => $instance->get('dir'), 'name' => $instance->get('name')];
    }
}
echo json_encode($lst);

<?php
define('CLI_SCRIPT', true);
//ce script supprime toutes les cohortes [DIV] & [GR] gérée par entsync
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'execute' => false,
        'list' => false,
        'help' => false,
    ], [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] or (empty($options['execute']) and empty($options['list']))) {
    $help = <<<EOT
Bascule d'année.

Options:
 -h, --help                Print out this help
 --execute                 Supprime les cohortes [DIV] et [GR] gérées par entsync
 --list                    Liste les cohortes [DIV] et [GR] gérées par entsync

EOT;

    echo $help;
    die;
}

/** @var \auth_entsync\directory\cohorts $entsync_cohorts */
$entsync_cohorts = \auth_entsync\container::get('directory.cohorts');

if ($options['list']) {
    foreach ($entsync_cohorts->list() as $c) {
        if ($c->is_div_or_group()) {
            cli_writeln($c->name);
        }
    }
} else if ($options['execute']) {
    foreach ($entsync_cohorts->list() as $c) {
        if ($c->is_div_or_group()) {
            cli_writeln($c->name);
            $c->delete();
        }
    }
}

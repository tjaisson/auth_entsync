<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');

require_once(__DIR__ . '/../ent_defs.php');

$help =
"Synchronise les élèves.
    
Options:
--run       Effectue la synchro.
--diag       Teste si la synchro est faisable.
-h, --help            Affiche l'aide.

";

list($options, $unrecognised) = cli_get_params(
    [
        'run'  => false,
        'diag'  => false,
        'help'    => false,
    ],
    [
        'h' => 'help',
    ]
    );

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}
if ($options['run']) {
    aes_tasks::run();
} else if($options['diag']) {
    aes_tasks::diag();
}

class aes_tasks {
    public static function diag() {
        $year = aes_farm_user::get_year();
        $info = aes_farm_user::get_instance_info();
        $syncid = aes_farm_user::get_syncid();
        $count = aes_farm_user::count_entu_students($info->ent);
        if (!$info->do_sync) {
            cli_writeln($info->name . ' non programmé pour synchronisation');
            return;
        }
        if (!$ent = auth_entsync_ent_base::get_ent($info->ent)) {
            cli_writeln($info->name . ' : Ent ' . $info->ent . ' inconnu');
            return;
        }
        if ((!$ent = auth_entsync_ent_base::get_ent($info->ent)) || (!$ent->is_enabled())) {
            cli_writeln($info->name . ' : Ent ' . $info->ent . ' non activé');
            return;
        }
        cli_writeln($info->name . ' : Ent ' . $info->ent . ' synchronisation possible, déjà ' . $count . ' élèves');
    }

    public static function run() {
        $year = aes_farm_user::get_year();
        $info = aes_farm_user::get_instance_info();
        $syncid = aes_farm_user::get_syncid();
        if (!$info->do_sync) {
            cli_writeln('Non programmé pour synchronisation');
            return;
        }
        if ((!$ent = auth_entsync_ent_base::get_ent($info->ent)) || (!$ent->is_enabled())) {
            cli_writeln('Ent non activé');
            return;
        }
        $ius = aes_farm_user::get_students($info->uais, $year, $syncid, $info->ent);
        $synchronizer = $ent->get_synchronizer(1);
        $report = $synchronizer->dosync($ius);
        var_dump($report);
    }
}

class aes_farm_user {
    public static function simplify_profile($p) {
        $p = intval($p);
        if (($p === 4) || ($p === 6)) return 2;
        return $p;
    }

    public static function get_year() {
        global $DB;
        $sql = "SELECT value FROM pam\$FARM.{ent_config} WHERE name = 'year_offset';";
        $offset = intval($DB->get_field_sql($sql));
        return intval(date('Y', time() - $offset));
    }

    public static function get_syncid() {
        global $DB;
        $sql = 'SELECT MAX(id) as sync_id FROM pam$FARM.{ent_synchro} WHERE end IS NOT NULL;';
        return $DB->get_field_sql($sql);
    }

    public static function get_instance_info() {
        global $DB;
        $entsync = \auth_entsync\container::services();
        $conf = $entsync->query('conf');
        $inst = $conf->inst();
        $sql = 'SELECT fi.id as id, fi.name as name, GROUP_CONCAT(fiu.uai SEPARATOR \',\') as uais, fi.ent as ent, fi.do_entsync as do_sync
FROM pam$FARM.{farm_instance} fi
LEFT JOIN pam$FARM.{farm_instance_uai} fiu on fiu.instanceid = fi.id WHERE fi.dir = :dir
GROUP BY fi.id;';
        $info = $DB->get_record_sql($sql, ['dir' => $inst]);
        if (empty($info->uais)) {
            $info->uais = [];
        } else {
            $info->uais = explode(',', $info->uais);
        }
        
        return $info;
    }

    public static function get_students($uais, $year, $syncid, $ent) {
        global $DB;
        list($sql, $param) = $DB->get_in_or_equal($uais, SQL_PARAMS_NAMED, 'uai');
        $sql = $DB->
        $sql = 'SELECT
farmu.id as id,
farmu.login as uid,
farmu.externalid as user,
farmu.firstname as firstname,
farmu.lastname as lastname,
farmu.profile as profile,
MAX(c.label) as cohortname,
COUNT(1) as cnt
FROM pam$FARM.{ent_etab} e
JOIN pam$FARM.{ent_classe} c on c.etabid = e.id
JOIN pam$FARM.{ent_user_classe} uc on uc.classeid = c.id
JOIN pam$FARM.{ent_user} farmu on farmu.id = uc.userid
WHERE e.uai ' . $sql . ' AND uc.year_seen = :year and uc.syncid >= :syncid and e.ent = :ent
GROUP BY farmu.id
HAVING cnt = 1;';
        $param['year'] = $year;
        $param['syncid'] = $syncid;
        $param['ent'] = $ent;
        return $DB->get_records_sql($sql, $param);
    }

    public static function count_entu_students($ent) {
        global $DB;
        return $DB->count_records('auth_entsync_user', ['ent' => $ent, 'profile' => 1]);
    }
}

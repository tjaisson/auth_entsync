<?php
namespace aes\sync;

use auth_entsync\init\console;
use auth_entsync\container;

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
        'only-ko' => false,
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
$container = container::services();
$console = new console(!!$options['only-ko']);
$synchronizer = new synchronizer($console, $container);

if ($options['run']) {
    $synchronizer->run();
} else if($options['diag']) {
    $synchronizer->diag();
}

class synchronizer {
    protected console $console;
    /** @var \moodle_database $db */
    protected $db;
    protected $cfg;
    /** @var \auth_entsync\conf $conf */
    protected $conf;

    public function __construct($console, container $container) {
        $this->console = $console;
        $this->db = $container->query('DB');
        $this->cfg = $container->query('CFG');
        $this->conf = $container->query('conf');
    }

    public function diag() {
        $year = $this->conf->current_scol_year();
        $info = $this->get_instance_info();
        $syncid = $this->get_syncid();
        $count = $this->count_entu_students($info->ent);
        if (!$info->do_sync) {
            $this->console->write_check($info->name . ' non programmé pour synchronisation');
            return;
        }
        if (!$ent = \auth_entsync_ent_base::get_ent($info->ent)) {
            $this->console->write_check($info->name . ' : Ent ' . $info->ent . ' inconnu', false);
            return;
        }
        if ((!$ent = \auth_entsync_ent_base::get_ent($info->ent)) || (!$ent->is_enabled())) {
            $this->console->write_check($info->name . ' : Ent ' . $info->ent . ' non activé');
            return;
        }
        $this->console->write_check($info->name . ' : Ent ' . $info->ent . ' synchronisation possible, déjà ' . $count . ' élèves');
    }

    public function run() {
        $year = $this->conf->current_scol_year();
        $info = $this->get_instance_info();
        $syncid = $this->get_syncid();
        if (!$info->do_sync) {
            $this->console->write_fix('Non programmé pour synchronisation');
            return;
        }
        if ((!$ent = \auth_entsync_ent_base::get_ent($info->ent)) || (!$ent->is_enabled())) {
            $this->console->write_fix('Ent non activé');
            return;
        }
        $i = 0;
        $ius = $this->get_students($info->uais, $year, $syncid, $info->ent);
        $synchronizer = $ent->get_synchronizer(1);
        $report = $synchronizer->dosync($ius);
        var_dump($report);
    }

    public function get_syncid() {
        $farmdb = $this->conf->farmdb();
        $sql = "SELECT MAX(id) as sync_id FROM {$farmdb}.{ent_synchro} WHERE end IS NOT NULL;";
        return $this->db->get_field_sql($sql);
    }

    public function get_instance_info() {
        $farmdb = $this->conf->farmdb();
        $inst = $this->conf->inst();
        $sql = "SELECT fi.id as id, fi.name as name, GROUP_CONCAT(fiu.uai SEPARATOR ',') as uais, fi.ent as ent, fi.do_entsync as do_sync
FROM {$farmdb}.{farm_instance} fi
LEFT JOIN {$farmdb}.{farm_instance_uai} fiu on fiu.instanceid = fi.id WHERE fi.dir = :dir
GROUP BY fi.id;";
        $info = $this->db->get_record_sql($sql, ['dir' => $inst]);
        if (empty($info->uais)) {
            $info->uais = [];
        } else {
            $info->uais = explode(',', $info->uais);
        }
        return $info;
    }

    public function get_students($uais, $year, $syncid, $ent) {
        $farmdb = $this->conf->farmdb();
        list($sql, $param) = $this->db->get_in_or_equal($uais, SQL_PARAMS_NAMED, 'uai');
        $sql =
"SELECT
farmu.id as id,
el.login as uid,
IF(fu.domain = 1 AND :ent1 = 7, CONCAT('PARIS-', fu.aafid), fu.aafid) as user,
farmu.firstname as firstname,
farmu.lastname as lastname,
farmu.profile as profile,
MAX(c.label) as cohortname,
COUNT(1) as cnt
FROM {$farmdb}.{ent_etab} e
JOIN {$farmdb}.{ent_classe} c on c.etabid = e.id
JOIN {$farmdb}.{ent_user_classe} uc on uc.classeid = c.id
JOIN {$farmdb}.{ent_user} farmu on farmu.id = uc.userid
JOIN {$farmdb}.{ent_login} el on el.id = farmu.login_id
JOIN {$farmdb}.{farm_user} fu on fu.id = farmu.farm_user_id
WHERE e.uai {$sql} AND uc.year_seen = :year and uc.syncid >= :syncid and e.ent = :ent
GROUP BY farmu.id
HAVING cnt = 1;";
        $param['year'] = $year;
        $param['syncid'] = $syncid;
        $param['ent'] = $ent;
        $param['ent1'] = $ent;
        return $this->db->get_records_sql($sql, $param);
    }

    public function count_entu_students($ent) {
        return $this->db->count_records('auth_entsync_user', ['ent' => $ent, 'profile' => 1]);
    }
}

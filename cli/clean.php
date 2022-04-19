<?php
namespace aes\clean;

use auth_entsync\init\console;
use auth_entsync\container;
use DateTime;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help =
"Netoie la base entsync.
    
Options:
--diag       Affiche un diagnostic.
-h, --help            Affiche l'aide.

";

list($options, $unrecognised) = \cli_get_params(
    [
        'diag' => false,
        'only-ko' => false,
        'run' => false,
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
    );

if ($unrecognised) {
    $unrecognised = \implode(PHP_EOL.'  ', $unrecognised);
    \cli_error(\get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    \cli_writeln($help);
    exit(0);
}

$container = container::services();
$console = new console(!!$options['only-ko']);
$user_cleaner = new user_cleaner($console, $container);
$diag = !!$options['diag'];
switch ($options['run']) {
    case 'clean1':
        $user_cleaner->fix_name_dups($diag);
        break;
    case 'fix-externalid':
        $user_cleaner->fix_externalid($diag);
        break;
    case 'clean3':
        $user_cleaner->remove_local_users($diag);
        break;
    case 'remove-old-users':
        $user_cleaner->remove_old_users($diag);
        break;
    case 'clean4':
        $user_cleaner->estimate_synchro_time();
        break;
    case 'clean5':
        $user_cleaner->check_enabled_ent();
        break;
    case 'fix-deleted':
        $user_cleaner->fix_deleted($diag);
        break;

}

class user_cleaner {
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

    public function fix_deleted($diag = true) {
        $not_found = 0;
        $deleted = 0;
        $fixed = 0;
        foreach ($this->get_deleted() as $u) {
            if ($u->mdluf) {
                if ($u->mdlud) {
                    if ($diag) {
                        $deleted++;
                    } else {
                        $this->db->delete_records('auth_entsync_user', ['id' => $u->id]);
                        $fixed++;
                    }
                }
            } else {
                if ($diag) {
                    $not_found++;
                } else {
                    $this->db->delete_records('auth_entsync_user', ['id' => $u->id]);
                    $fixed++;
                }
            }
        }
        if ($diag) {
            if ($not_found == 0) {
                $this->console->write_fix('Aucun not_found');
            } else {
                $this->console->write_fix('not_found : ' . $not_found, false);
            }
            if ($deleted == 0) {
                $this->console->write_fix('Aucun deleted');
            } else {
                $this->console->write_fix('deleted : ' . $deleted, false);
            }
        } else {
            $this->console->write_fix('removed ' . $fixed . ' orphan entus');
        }
    }

    protected function get_deleted() {
        $sql =
'SELECT entu.id as id,
(mdlu.id IS NOT NULL) as mdluf,
(mdlu.deleted <> 0) as mdlud,
mdlu.auth as auth,
mdlu.id as userid
FROM {auth_entsync_user} entu
LEFT JOIN {user} mdlu on mdlu.id = entu.userid
WHERE (mdlu.id IS NULL) OR (mdlu.deleted);';
        return $this->db->get_records_sql($sql);
    }

    public function fix_externalid($diag = true) {
        $matches = $this->get_externalids_matches();
        foreach ($matches as $match) {
            if ($match->login_pb) {
                $entu1 = $this->db->get_record('auth_entsync_user', ['id' => $match->id]);
                $entu2 = $this->db->get_record('auth_entsync_user', ['uid' => $match->login]);
                $mdlu1 = $this->db->get_record('user', ['id' => $entu1->userid]);
                $mdlu2 = $this->db->get_record('user', ['id' => $entu2->userid]);
                if ($mdlu1->firstaccess <= $mdlu2->firstaccess) {
                    $user = new \stdClass();
                    $user->id = $mdlu1->id;
                    $user->username = $mdlu1->username;
                    $this->console->write_fix('Delete ' . $user->id);
                    if (!$diag) {
                        $this->db->delete_records('auth_entsync_user', ['userid' => $user->id]);
                        \delete_user($user);
                    }
                } else {
                    $user = new \stdClass();
                    $user->id = $mdlu2->id;
                    $user->username = $mdlu2->username;
                    $this->console->write_fix('Delete ' . $user->id);
                    if (!$diag) {
                        $this->db->delete_records('auth_entsync_user', ['userid' => $user->id]);
                        \delete_user($user);
                    }
                    $obj = new \stdClass();
                    $obj->id = $entu1->id;
                    $obj->uid = $match->login;
                    $this->console->write_fix('Fix : ' . $match->login);
                    if (!$diag) {
                        $this->db->update_record('auth_entsync_user', $obj);
                    }
                }
            } else {
                $obj = new \stdClass();
                $obj->id = $match->id;
                $obj->uid = $match->login;
                $this->console->write_fix('Fix : ' . $match->login);
                if (!$diag) {
                    $this->db->update_record('auth_entsync_user', $obj);
                }
            }
        }
    }

    protected function get_externalids_matches() {
        $farmdb = $this->conf->farmdb();
        $sql =
"WITH
t1 AS (
 SELECT eu.*, IF(IF(eu.ent = 3, eu.uid REGEXP '^[0-9]*$', IF(eu.ent = 7, eu.uid REGEXP '^paris-[0-9]*$', 0)),1 ,eu.ent) AS domain,
 IF(eu.profile in (2,4,6), 6, eu.profile) as compat_profile
 FROM {auth_entsync_user} AS eu),
entu AS (
 SELECT t1.*, IF(t1.domain = 1 AND t1.ent = 7, substring(t1.uid, 7), t1.uid) AS aafid
 FROM t1)
SELECT entu.id as id, el.login as login, EXISTS(SELECT 1 FROM {auth_entsync_user} entuu WHERE entuu.ent = entu.ent and entuu.uid = el.login) as login_pb
FROM entu
JOIN {$farmdb}.{farm_user} fu on fu.domain = entu.domain AND fu.compat_profile = entu.compat_profile AND fu.aafid = entu.aafid
JOIN {$farmdb}.{ent_user} farmu on farmu.farm_user_id = fu.id AND farmu.ent = entu.ent
JOIN {$farmdb}.{ent_login} el on el.id = farmu.login_id;";
        return $this->db->get_records_sql($sql);
    }

    public function remove_old_users($diag) {
        $prev_scol_year = $this->conf->current_scol_year() - 1;
        $limit = $this->conf->scol_year_start_time($prev_scol_year);
        $limit_current = $this->conf->scol_year_start_time($prev_scol_year + 1);
        $nb_deleted = 0;
        $nb_to_del = 0;
        $nb_to_del_unused = 0;
        $max_last_access = 0;
        $nb_gone_but_access = 0;
        $max_gone_but_access = 0;
        foreach ($this->get_entus() as $mdlu) {
            if ($mdlu->deleted) {
                ++$nb_deleted;
            } else {
                $old_access = ($mdlu->lastaccess === 0) || ($mdlu->lastaccess < $limit);
                $farm_gone =  ($mdlu->hasFarmEnt) && ($mdlu->farm_year_seen < $prev_scol_year);
                if ($farm_gone) {
                    if ($old_access) {
                        ++$nb_to_del;
                        if ($mdlu->lastaccess === 0) ++$nb_to_del_unused;
                        if ($mdlu->lastaccess > $max_last_access) $max_last_access = $mdlu->lastaccess;
                    } else {
                        ++$nb_gone_but_access;
                        if ($mdlu->lastaccess > $max_gone_but_access) $max_gone_but_access = $mdlu->lastaccess;
                        if ($mdlu->lastaccess > $limit_current)
                            $this->console->write_fix('gone : ' . $mdlu->id, false);
                    }
                }
            }
        }
        $max_last_access = $max_last_access == 0 ? 'jamais' : $this->format_time($max_last_access);
        $this->console->writeln('nb to del : ' . $nb_to_del .
        ' (used : ' . ($nb_to_del - $nb_to_del_unused) .
        ' max access : ' . $max_last_access . ')');
        if ($nb_gone_but_access > 0) {
            $max_gone_but_access = $max_gone_but_access == 0 ? 'jamais' : $this->format_time($max_gone_but_access);
            $this->console->write_fix('nb farm gone but access : ' . $nb_gone_but_access .
            ' (max access : ' . $max_gone_but_access . ')' , false);
        }
        if ($nb_deleted > 0) {
            $this->console->write_fix('nb orphans entu : ' . $nb_deleted, false);
        }
    }

    public function remove_local_users($diag) {
        $nb_to_del = 0;
        $nb_to_del_archived = 0;
        $nb_to_del_unused = 0;
        $nb_to_unlink = 0;
        $nb_to_unlink_archived = 0;
        $nb_to_unlink_unused = 0;
        foreach ($this->get_entus() as $mdlu) {
            if ($mdlu->hasLocal) {
                $unused = substr($mdlu->password, 0, 8 ) === "entsync\\";
                if ($mdlu->hasFarmEnt || $mdlu->hasEnvole) {
                    $nb_to_unlink++;
                    if (\array_key_exists(7, $mdlu->entus)) {
                        if ($mdlu->entus[7]->archived) {
                            $nb_to_unlink_archived++;
                        }
                    }
                    if ($unused) $nb_to_unlink_unused++;
                } else {
                    $nb_to_del++;
                    if ($mdlu->entus[4]->archived) {
                        $nb_to_del_archived++;
                    }
                    if ($unused) $nb_to_del_unused++;
                    $user = new \stdClass();
                    $user->id = $mdlu->id;
                    $user->username = $mdlu->username;
                    if (!$diag) {
                        \delete_user($user);
                    }
                }
                if (!$diag) {
                    $this->db->delete_records('auth_entsync_user', ['userid' => $mdlu->id, 'ent' => 4]);
                }
            }
        }
        $this->console->writeln('nb to unlink : ' . $nb_to_unlink .
        ' (archived : ' . $nb_to_unlink_archived .
        ', unused : ' . $nb_to_unlink_unused . ')');
        $this->console->writeln('nb to del : ' . $nb_to_del .
        ' (archived : ' . $nb_to_del_archived .
        ', unused : ' . $nb_to_del_unused . ')');
    }

    protected function simplify_profile($p) {
        $p = \intval($p);
        if (($p === 4) || ($p === 6)) return 2;
        return $p;
    }

    public function fix_name_dups($diag) {
        $dups = $this->get_names_dups();
        $limit = \mktime(0,0,0,5,1,2021);
        foreach($dups as $dup) {
            foreach($dup->mdlus as $mdlu) {
                if (($mdlu->lastaccess === 0) && ($mdlu->hasFarmEnt) && (! $mdlu->farmFound) && ($mdlu->timecreated < $limit)) {
                    $this->console->write_fix('Delete : ' . $mdlu->id . ' ' . $mdlu->username);
                    $user = new \stdClass();
                    $user->id = $mdlu->id;
                    $user->username = $mdlu->username;
                    if (!$diag) {
                        $this->db->delete_records('auth_entsync_user', ['userid' => $user->id]);
                        \delete_user($user);
                    }
                }
            }
        }
    }

    protected function get_names_dups() {
        $sql =
"SELECT
 GROUP_CONCAT(mdlu.id order by mdlu.id SEPARATOR ',') as id,
 GROUP_CONCAT(mdlu.username order by mdlu.id SEPARATOR ',') as usernames,
 mdlu.lastname as lastname,
 mdlu.firstname as firstname,
 COUNT(1) as cnt,
 GROUP_CONCAT(profiles order by mdlu.id SEPARATOR ',') as profiles,
 GROUP_CONCAT(cnt order by mdlu.id SEPARATOR ',') as cnts,
 GROUP_CONCAT(ents order by mdlu.id SEPARATOR ',') as ents,
 GROUP_CONCAT(uids order by mdlu.id SEPARATOR ',') as uids,
 GROUP_CONCAT(lgfs order by mdlu.id SEPARATOR ',') as lgfs,
 GROUP_CONCAT(exfs order by mdlu.id SEPARATOR ',') as exfs,
 GROUP_CONCAT(mdlu.timecreated order by mdlu.id SEPARATOR ',') as timecreated,
 GROUP_CONCAT(mdlu.lastaccess order by mdlu.id SEPARATOR ',') as lastaccess
FROM
(SELECT
 imdlu.id as id,
 count(1) as cnt,
 GROUP_CONCAT(ientu.profile order by ientu.id SEPARATOR '|') as profiles,
 GROUP_CONCAT(ientu.ent order by ientu.id SEPARATOR '|') as ents,
 GROUP_CONCAT(ientu.uid order by ientu.id SEPARATOR '|') as uids,
 GROUP_CONCAT((ifarmu.id is not null) order by ientu.id SEPARATOR '|') as lgfs,
 GROUP_CONCAT((iifarmu.id is not null) order by ientu.id SEPARATOR '|') as exfs
FROM {user} imdlu join {auth_entsync_user} ientu on ientu.userid = imdlu.id
left join pam\$FARM.{ent_user} ifarmu on ifarmu.ent = ientu.ent and ifarmu.login = ientu.uid
left join pam\$FARM.{ent_user} iifarmu on iifarmu.ent = ientu.ent and iifarmu.externalid = ientu.uid
group by imdlu.id) ccmdlu
join {user} mdlu on mdlu.id = ccmdlu.id
group by mdlu.lastname, mdlu.firstname
having cnt > 1;";
        $bndlist = $this->db->get_records_sql($sql);
        $list = [];
        foreach ($bndlist as $bnddup) {
            $dup = new \stdClass();
            $dup->firstname = $bnddup->firstname;
            $dup->lastname = $bnddup->lastname;
            $dup->mdlus = [];
            $ids = \explode(',', $bnddup->id);
            $usernames = \explode(',', $bnddup->usernames);
            $profiles = \explode(',', $bnddup->profiles);
            $cnts = \explode(',', $bnddup->cnts);
            $ents = \explode(',', $bnddup->ents);
            $uids = \explode(',', $bnddup->uids);
            $lgfs = \explode(',', $bnddup->lgfs);
            $exfs = \explode(',', $bnddup->exfs);
            $timecreated = \explode(',', $bnddup->timecreated);
            $lastaccess = \explode(',', $bnddup->lastaccess);
            $count = \count($ids);
            if ((\count($usernames) !== $count) || (\count($profiles) !== $count) || (\count($cnts) !== $count) || (\count($ents) !== $count) ||
            (\count($uids) !== $count) || (\count($timecreated) !== $count) ||
            (\count($lastaccess) !== $count) || (\count($lgfs) !== $count) || (\count($exfs) !== $count)) throw new \Exception();
            $dup->count = $count;
            for ($i = 0; $i < $count; $i++) {
                $mdlu = new \stdClass();
                $mdlu->id = \intval($ids[$i]);
                $mdlu->username = $usernames[$i];
                $mdlu->timecreated = \intval($timecreated[$i]);
                $mdlu->lastaccess = \intval($lastaccess[$i]);
                $icount = \intval($cnts[$i]);
                $iprofiles = \explode('|', $profiles[$i]);
                $ients = \explode('|', $ents[$i]);
                $iuids = \explode('|', $uids[$i]);
                $iexfs = \explode('|', $exfs[$i]);
                $ilgfs = \explode('|', $lgfs[$i]);
                if ((\count($iprofiles) !== $icount) || (\count($ients) !== $icount) ||
                (\count($iuids) !== $icount) || (\count($ilgfs) !== $icount) || (\count($iexfs) !== $icount)) throw new \Exception();
                $mdlu->entus = [];
                $mdlu->found = false;
                $mdlu->hasFarmEnt = false;
                for ($ii = 0; $ii < $icount; $ii++) {
                    $entu = new \stdClass();
                    $entu->profile = $this->simplify_profile($iprofiles[$ii]);
                    $entu->ent = \intval($ients[$ii]);
                    $entu->uid = $iuids[$ii];
                    $entu->lgf = \intval($ilgfs[$ii]);
                    $entu->exf = \intval($iexfs[$ii]);
                    if (($entu->lgf) || ($entu->exf)) $mdlu->found = true;
                    if (($entu->ent === 3) || ($entu->ent === 7)) $mdlu->hasFarmEnt = true;
                    $mdlu->entus[$entu->ent] = $entu;
                }

                $dup->mdlus[] = $mdlu;
            }
            $list[] = $dup;
        }
        return $list;
    }

    public function get_entus() {
        $farmdb = $this->conf->farmdb();
        $sql =
"SELECT
imdlu.id as id,
imdlu.username as username,
imdlu.password as password,
imdlu.timecreated as timecreated,
imdlu.lastaccess as lastaccess,
imdlu.deleted as deleted,
COUNT(1) as cnt,
GROUP_CONCAT(ientu.profile order by ientu.id SEPARATOR '|') as profiles,
GROUP_CONCAT(ientu.ent order by ientu.id SEPARATOR '|') as ents,
GROUP_CONCAT(ientu.uid order by ientu.id SEPARATOR '|') as uids,
GROUP_CONCAT(ientu.archived order by ientu.id SEPARATOR '|') as archiveds,
GROUP_CONCAT(ientu.archivedsince order by ientu.id SEPARATOR '|') as archivedsinces,
GROUP_CONCAT((ifarmu.id is not null) order by ientu.id SEPARATOR '|') as lgfs,
GROUP_CONCAT(IF(ifarmu.year_seen is null, 0, ifarmu.year_seen) order by ientu.id SEPARATOR '|') as lgys
FROM {user} imdlu join {auth_entsync_user} ientu on ientu.userid = imdlu.id
left join {$farmdb}.{ent_login} el on el.login = ientu.uid
left join {$farmdb}.{ent_user} ifarmu on ifarmu.ent = ientu.ent and ifarmu.login_id = el.id
group by imdlu.id;";
        $bndlist = $this->db->get_records_sql($sql);
        $list = [];
        foreach ($bndlist as $bndu) {
            $mdlu = new \stdClass();
            $mdlu->id = $bndu->id;
            $mdlu->username = $bndu->username;
            $mdlu->password = $bndu->password;
            $mdlu->timecreated = \intval($bndu->timecreated);
            $mdlu->lastaccess = \intval($bndu->lastaccess);
            $mdlu->deleted = $bndu->deleted != '0';
            $count = \intval($bndu->cnt);
            $mdlu->count = $count;
            $profiles = \explode('|', $bndu->profiles);
            $ents = \explode('|', $bndu->ents);
            $uids = \explode('|', $bndu->uids);
            $archiveds = \explode('|', $bndu->archiveds);
            $archivedsinces = \explode('|', $bndu->archivedsinces);
            $lgfs = \explode('|', $bndu->lgfs);
            $lgys = \explode('|', $bndu->lgys);
            if ((\count($profiles) !== $count) || (\count($ents) !== $count) ||
            (\count($uids) !== $count) || (\count($lgfs) !== $count) ||
            (\count($lgys) !== $count) || (\count($archiveds) !== $count) ||
            (\count($archivedsinces) !== $count)) throw new \Exception();
            $mdlu->entus = [];
            $mdlu->farmUserFound = false;
            $mdlu->hasFarmEnt = false;
            $mdlu->hasLocal = false;
            $mdlu->hasEnvole = false;
            $mdlu->farm_year_seen = 0;
            for ($ii = 0; $ii < $count; $ii++) {
                $entu = new \stdClass();
                $entu->profile = \intval($profiles[$ii]);
                $entu->ent = \intval($ents[$ii]);
                $entu->uid = $uids[$ii];
                $entu->archived = $archiveds[$ii] <> 0;
                $entu->archivedsince = \intval($archivedsinces[$ii]);
                $entu->lgf = \intval($lgfs[$ii]);
                $year_seen = \intval($lgys[$ii]);
                if ($year_seen > $mdlu->farm_year_seen) $mdlu->farm_year_seen = $year_seen;
                if ($entu->lgf) $mdlu->farmUserFound = true;
                if (($entu->ent === 3) || ($entu->ent === 7)) $mdlu->hasFarmEnt = true;
                if ($entu->ent === 4) $mdlu->hasLocal = true;
                if ($entu->ent === 6) $mdlu->hasEnvole = true;
                $mdlu->entus[$entu->ent] = $entu;
            }
            $list[] = $mdlu;
        }
        return $list;
    }

    public function estimate_synchro_time() {
        foreach ($this->get_estimate_synchro_time() as $times) {
            if ($times->ent == '4' || $times->ent == '6') {
                $this->console->writeln('ent : ' . $times->ent);
                $this->console->writeln('  min_tc : ' . $this->format_time($times->min_tc));
                $this->console->writeln('  max_tc : ' . $this->format_time($times->max_tc));
                $this->console->writeln('  min_arch : ' . $this->format_time($times->min_arch));
                $this->console->writeln('  max_arch : ' . $this->format_time($times->max_arch));
            }
        }
    }

    protected function format_time($time) {
        return \userdate(\intval($time), get_string('strftimedaydate', 'core_langconfig'));
    }

    protected function get_estimate_synchro_time() {
        $sql = 
"WITH bndl AS (
SELECT
u.timecreated AS timecreated,
u.deleted AS deleted,
IF(entu.archived = 0, NULL, entu.archivedsince) AS archivedsince,
entu.ent AS ent
FROM {user} u
JOIN {auth_entsync_user} entu on entu.userid = u.id)
SELECT
ent AS ent,
MAX(timecreated) AS max_tc,
MIN(timecreated) AS min_tc,
MAX(archivedsince) AS max_arch,
MIN(archivedsince) AS min_arch
FROM bndl
GROUP BY ent;";
    return $this->db->get_records_sql($sql);
    }


    protected function get_enabled_ent() {
        $enabled = \get_config($this->conf->pn(), 'enabledents');
        if (false === $enabled) {
            return [];
        }
        $enabled = \trim($enabled);
        if (empty($enabled)) {
            return [];
        }
        return \array_map('\trim', \explode(';', $enabled));
    }

    public function check_enabled_ent() {
        $info = $this->get_instance_info();
        $this->console->start_section($info->name);
        $enabled = $this->get_enabled_ent();
        if (empty($enabled)) {
            $this->console->write_check('Aucun ENT.', false);
            return;
        }
        $entcore = [];
        $has_local = false;
        $has_envole = false;
        $unknown = [];
        foreach ($enabled as $ent) {
            if ($ent == '3' || $ent == '7') {
                $entcore[] = $ent;
            } else if ($ent == '4') {
                $has_local = true;
            } else if ($ent == '6') {
                $has_envole = true;
            } else {
                $unknown[] = $ent;
            }
        }
        if (\count($unknown) > 0) {
            $this->console->write_check('Ent inconnus : ' . \implode(', ', $unknown), false);
        }
        if (\count($entcore) > 0) {
            if (\count($entcore) == 1) {
                $this->console->write_check('Un seul entcore');
            } else {
                $this->console->write_check('Deux entcore', false);
            }
            if ($has_envole) {
                $this->console->write_check('Envole activé', false);
            }
        } else {
            if ($has_envole) {
                $this->console->write_check('Envole activé');
            } else {
                $this->console->write_check('Aucun ent', false);
            }
        }
        if ($has_local) {
            $this->console->write_check('Local activé', false);
        } else {
            $this->console->write_check('Local désactivé');
        }
    }

    public function get_instance_info() {
        $inst = $this->conf->inst();
        $sql = 'SELECT fi.id as id, fi.name as name, GROUP_CONCAT(fiu.uai SEPARATOR \',\') as uais, fi.ent as ent, fi.do_entsync as do_sync
FROM pam$FARM.{farm_instance} fi
LEFT JOIN pam$FARM.{farm_instance_uai} fiu on fiu.instanceid = fi.id WHERE fi.dir = :dir
GROUP BY fi.id;';
        $info = $this->db->get_record_sql($sql, ['dir' => $inst]);
        if (empty($info->uais)) {
            $info->uais = [];
        } else {
            $info->uais = explode(',', $info->uais);
        }
        return $info;
    }
}

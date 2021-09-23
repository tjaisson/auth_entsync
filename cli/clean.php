<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help =
"Netoie la base entsync.
    
Options:
--diag       Affiche un diagnostic.
-h, --help            Affiche l'aide.

";

list($options, $unrecognised) = cli_get_params(
    [
        'diag'  => false,
        'clean1'  => false,
        'clean2'  => false,
        'clean3'  => false,
        'clean4'  => false,
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
if ($options['diag']) {
    $dups = aes_clean_db::get_names_dups();
    foreach($dups as $dup) {
            cli_writeln($dup->lastname . ' ' . $dup->firstname . ' ' . $dup->count);
    }
} else if ($options['clean1']) {
    $dups = aes_clean_db::get_names_dups();
    $limit = mktime(0,0,0,5,1,2021);
    foreach($dups as $dup) {
        foreach($dup->mdlus as $mdlu) {
            if (($mdlu->lastaccess === 0) && ($mdlu->hasFarmEnt) && (! $mdlu->found) && ($mdlu->timecreated < $limit)) {
                cli_writeln('Delete : ' . $mdlu->id . ' ' . $mdlu->username);
                $user = new stdClass();
                $user->id = $mdlu->id;
                $user->username = $mdlu->username;
                $DB->delete_records('auth_entsync_user', ['userid' => $user->id]);
                delete_user($user);
            }
        }
    }
} else if ($options['clean2']) {
    $matches = aes_clean_db::get_externalids_matches();
    foreach ($matches as $match) {
        if ($match->profile_pb) {
            cli_writeln('PB profil : entu.id = ' . $match->id);
        } else {
            if ($match->login_pb) {
                $entu1 = $DB->get_record('auth_entsync_user', ['id' => $match->id]);
                $entu2 = $DB->get_record('auth_entsync_user', ['uid' => $match->login]);
                $mdlu1 = $DB->get_record('user', ['id' => $entu1->userid]);
                $mdlu2 = $DB->get_record('user', ['id' => $entu2->userid]);
                if ($mdlu1->firstaccess <= $mdlu2->firstaccess) {
                    $user = new stdClass();
                    $user->id = $mdlu1->id;
                    $user->username = $mdlu1->username;
                    cli_writeln('Delete ' . $user->id);
                    $DB->delete_records('auth_entsync_user', ['userid' => $user->id]);
                    delete_user($user);
                } else {
                    $user = new stdClass();
                    $user->id = $mdlu2->id;
                    $user->username = $mdlu2->username;
                    cli_writeln('Delete ' . $user->id);
                    $DB->delete_records('auth_entsync_user', ['userid' => $user->id]);
                    delete_user($user);
                    $obj = new stdClass();
                    $obj->id = $entu1->id;
                    $obj->uid = $match->login;
                    cli_writeln('Fix : ' . $match->login);
                    $DB->update_record('auth_entsync_user', $obj);
                }
            } else {
                $obj = new stdClass();
                $obj->id = $match->id;
                $obj->uid = $match->login;
                cli_writeln('Fix : ' . $match->login);
                $DB->update_record('auth_entsync_user', $obj);
            }
        }
    }
}  else if ($options['clean3']) {
    cli_writeln('do');
    set_config('defaulthomepage', HOMEPAGE_SITE);
    set_config('frontpage', '');
    set_config('frontpageloggedin', '7,2');
    set_config('maxcategorydepth', '1');
} else if ($options['clean4']) {
    $aes_fn = "{$CFG->dataroot}/.pamupgradelock";
    if (file_exists($aes_fn)) {
        cli_writeln('delete');
        unlink($aes_fn);
    } else {
        cli_writeln('not exists');
    }
}


class aes_clean_db {
    public static function simplify_profile($p) {
        $p = intval($p);
        if (($p === 4) || ($p === 6)) return 2;
        return $p;
    }
    public static function get_names_dups() {
        global $DB;
        $sql = "
SELECT
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
having cnt > 1;
        ";
        $bndlist = $DB->get_records_sql($sql);
        $list = [];
        foreach ($bndlist as $bnddup) {
            $dup = new stdClass();
            $dup->firstname = $bnddup->firstname;
            $dup->lastname = $bnddup->lastname;
            $dup->mdlus = [];
            $ids = explode(',', $bnddup->id);
            $usernames = explode(',', $bnddup->usernames);
            $profiles = explode(',', $bnddup->profiles);
            $cnts = explode(',', $bnddup->cnts);
            $ents = explode(',', $bnddup->ents);
            $uids = explode(',', $bnddup->uids);
            $lgfs = explode(',', $bnddup->lgfs);
            $exfs = explode(',', $bnddup->exfs);
            $timecreated = explode(',', $bnddup->timecreated);
            $lastaccess = explode(',', $bnddup->lastaccess);
            $count = count($ids);
            if ((count($usernames) !== $count) || (count($profiles) !== $count) || (count($cnts) !== $count) || (count($ents) !== $count) ||
            (count($uids) !== $count) || (count($timecreated) !== $count) ||
            (count($lastaccess) !== $count) || (count($lgfs) !== $count) || (count($exfs) !== $count)) throw new Exception();
            $dup->count = $count;
            for ($i = 0; $i < $count; $i++) {
                $mdlu = new stdClass();
                $mdlu->id = intval($ids[$i]);
                $mdlu->username = $usernames[$i];
                $mdlu->timecreated = intval($timecreated[$i]);
                $mdlu->lastaccess = intval($lastaccess[$i]);
                $icount = intval($cnts[$i]);
                $iprofiles = explode('|', $profiles[$i]);
                $ients = explode('|', $ents[$i]);
                $iuids = explode('|', $uids[$i]);
                $iexfs = explode('|', $exfs[$i]);
                $ilgfs = explode('|', $lgfs[$i]);
                if ((count($iprofiles) !== $icount) || (count($ients) !== $icount) ||
                (count($iuids) !== $icount) || (count($ilgfs) !== $icount) || (count($iexfs) !== $icount)) throw new Exception();
                $mdlu->entus = [];
                $mdlu->found = false;
                $mdlu->hasFarmEnt = false;
                for ($ii = 0; $ii < $icount; $ii++) {
                    $entu = new stdClass();
                    $entu->profile = self::simplify_profile($iprofiles[$ii]);
                    $entu->ent = intval($ients[$ii]);
                    $entu->uid = $iuids[$ii];
                    $entu->lgf = intval($ilgfs[$ii]);
                    $entu->exf = intval($iexfs[$ii]);
                    if (($entu->lgf) || ($entu->exf)) $entu->found = true;
                    if (($entu->ent === 3) || ($entu->ent === 7)) $mdlu->hasFarmEnt = true;
                    $mdlu->entus[$entu->ent] = $entu;
                }

                $dup->mdlus[] = $mdlu;
            }
            $list[] = $dup;
        }
        return $list;
    }
    public static function get_externalids_matches() {
        global $DB;
        $sql = '
SELECT entu.id as id, farmu.login as login, EXISTS(SELECT 1 FROM {auth_entsync_user} entuu WHERE entuu.ent = entu.ent and entuu.uid = farmu.login) as login_pb,
NOT ((entu.profile = farmu.profile) or (entu.profile in (2,4,6) and farmu.profile in (2,4,6))) as profile_pb
FROM {auth_entsync_user} entu
JOIN pam$FARM.{ent_user} farmu on farmu.ent = entu.ent and farmu.externalid = entu.uid;';
        return $DB->get_records_sql($sql);
    }
}

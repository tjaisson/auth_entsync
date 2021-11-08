<?php
namespace auth_entsync\directory;

use \auth_entsync\helpers\stringhelper;
use \auth_entsync\directory\cohorts;
use \auth_entsync\helpers\rolehelper;
use \auth_entsync\conf;
use \auth_entsync\farm\instance_info;

class entus {
    const TABLE = 'auth_entsync_user';
    const ENTU_FIELDS = ['userid', 'ent', 'uid', 'struct', 'profile', 'archived', 'archivedsince', 'sync'];
    const MDLU_FIELDS = ['auth', 'deleted', 'mnethostid', 'firstname', 'lastname', 'confirmed', 'suspended'];
    /**
     * Fausse email attribuée aux utilisateurs
     */
    const FAKEEMAIL = 'inconnu@ac-paris.invalid';
    const FAKEEMAIL_ELEV = 'eleve@ac-paris.invalid';
    const FAKEEMAIL_ENS = 'enseignant@ac-paris.invalid';
    const FAKEEMAIL_PERS = 'personnel@ac-paris.invalid';

    protected $CFG;
    protected \moodle_database $DB;
    protected conf $conf;
    protected instance_info $i_info;
    protected cohorts $cohorts;
    public function __construct($CFG, $DB, conf $conf, instance_info $i_info, cohorts $cohorts) {
        $this->CFG = $CFG;
        $this->DB = $DB;
        $this->conf = $conf;
        $this->i_info = $i_info;
        $this->cohorts = $cohorts;
    }

    /**
     * Attempt to find the user by id
     * 
     * return [$entu, $mdlu] on success. Updated if enought info in $ui.
     *        [$entu, null] if $mdlu is missing.
     *        [null, null] if no match
     *
     * @param unknown $ui The user info with at least $ui->user
     * @param unknown $ent The ent
     * @return [\stdClass, \stdClass]
     */
    public function find_update_by_id($ui, $ent) {
        $uids = [$ui->user];
        if (! empty($ui->uid)) $uids[] = $ui->uid;
        list($sql, $params) = $this->DB->get_in_or_equal($uids, \SQL_PARAMS_NAMED, 'uid');
        $select = 'a.id AS mdluid';
        foreach (self::MDLU_FIELDS as $field) $select .= ", a.{$field} as {$field}";
        $sql = "SELECT b.*, {$select}
        FROM {". self::TABLE . "} b
        LEFT JOIN {user} a ON a.id = b.userid
        WHERE b.ent = :ent AND b.uid {$sql}";
        $params['ent'] = $ent->get_code();
        $matches = $this->DB->get_records_sql($sql, $params);
        $cnt = \count($matches);
        if ($cnt === 0) {
            return [null, null];
        } else if ($cnt === 1) {
            return $this->clean_one_match(\array_pop($matches), $ui, $ent);
        } else {
            return $this->clean_multi_matches($matches, $ui, $ent);
        }
    }

    protected function clean_one_match($bndlu, $ui, $ent) {
        list($entu, $mdlu) = $this->split_bndlu($bndlu);
        if ((null !== $mdlu) && ($mdlu->deleted)) {
            $mdlu = null;
        }
        if ((null === $entu) || (null === $mdlu)) {
            return [$entu, $mdlu];
        }
        return $this->update($entu, $mdlu, $ui, $ent);
    }

    protected function clean_multi_matches($matches, $ui, $ent) {
        $list = [];
        foreach ($matches as $match) $list[$match->userid][] = $match;
        $cnt = \count($list);
        if ($cnt === 1) {
            $list = \array_pop($list);
            $bndlu = \array_pop($list);
            $ids = [];
            foreach ($list as $line) $ids[] = $line->id;
            $this->DB->delete_records_list(self::TABLE, 'id', $ids);
            return $this->clean_one_match($bndlu, $ui, $ent);
        } else {
            $ids = [];
            foreach ($matches as $line) $ids[] = $line->id;
            $this->DB->delete_records_list(self::TABLE, 'id', $ids);
            return [null, null];
        }
    }

    public function find_update_by_names_and_profile($entu, $ui, $ent) {
        if (!$this->is_creatable($ui)) return [$entu, null];
        $select = 'a.id AS mdluid, b.id AS entuid';
        foreach (self::MDLU_FIELDS as $field) $select .= ", a.{$field} as {$field}";
        foreach (self::ENTU_FIELDS as $field) $select .= ", b.{$field} as {$field}";
        $sql = $this->DB->sql_concat_join("'x'", ['a.id', 'b.ent']);
        $sql = "SELECT {$sql} AS id, {$select} 
        FROM {user} a
        JOIN {". self::TABLE . "} b ON b.userid = a.id
        WHERE a.deleted = 0
        AND b.profile = :profile
        AND a.firstname = :firstname
        AND a.lastname = :lastname";
        $matches = $this->DB->get_records_sql($sql,
            ['firstname' => $ui->firstname, 'lastname' => $ui->lastname, 'profile' => $ui->profile]);
        $list = [];
        foreach ($matches as $match) {
            if ($match->ent == $ent->get_code()) return [$entu, null];
            $list[$match->mdluid][] = $match;
        }
        $cnt = \count($list);
        if ($cnt === 1) {
            $list = \array_pop($list);
            $bndlu = \array_pop($list);
            list(, $mdlu) = $this->split_bndlu($bndlu);
            return $this->update($entu, $mdlu, $ui, $ent);
        } else {
            return [$entu, null];
        }
    }

    public function create($entu, $ui, $ent) {
        if (!$this->is_creatable($ui)) return [$entu, null];
        $mdlu = $this->create_mdlu($ui, $ent);
        return $this->update($entu, $mdlu, $ui, $ent);
    }

    public function get_entus($profiles = null, $ents = null, $other_profiles = false, $other_ents = false) {
        global $DB;
        if (null === $profiles) {
            if ($other_profiles) return [];
            $params = [];
            $sep = '';
            $sql = '';
        } else {
            list($sql, $params) = $DB->get_in_or_equal($profiles, \SQL_PARAMS_NAMED, 'prf', !$other_profiles);
            $sql = 'profile ' . $sql;
            $sep = ' AND ';
        }
        if (null === $ents) {
            if ($other_ents) return [];
        } else {
            list($sql2, $params2) = $DB->get_in_or_equal($ents, \SQL_PARAMS_NAMED, 'ent', !$other_ents);
            $sql .= $sep . 'ent ' . $sql2;
            $params = \array_merge($params, $params2);
        }
        return $DB->get_recordset_select(self::TABLE, $sql, $params);
    }

    public function is_creatable($ui) {
        return !(empty($ui->profile) || empty($ui->firstname) || empty($ui->lastname));
    }
    
    public function is_auto_creatable($ui) {
        if ((empty($ui->profile) || empty($ui->firstname) || empty($ui->lastname) || empty($ui->rnes))) {
            return false;
        };
        $auto_conf = $this->conf->auto_account();
        $user_auto_profile = intval($ui->profile) & $auto_conf->auto_profiles;
        if ($user_auto_profile) {
            if ($user_auto_profile & $auto_conf->no_uai_check) {
                return true;
            } else {
                return count(array_intersect($ui->rnes, $this->i_info->rnes())) != 0;
            }
        }
    }
    
    public function has_bad_profile($ui) {
        if (! isset($ui->profile)) return false;
        return empty($ui->profile);
    }

    protected function split_bndlu($bndlu) {
        if (null === $bndlu->mdluid) {
            $mdlu = null;
        } else {
            $mdlu = new \stdClass();
            $mdlu->id = $bndlu->mdluid;
            foreach (self::MDLU_FIELDS as $field) $mdlu->$field = $bndlu->$field;
        }
        if (null === $bndlu->id) {
            $entu = null;
        } else {
            $entu = new \stdClass();
            $entu->id = $bndlu->id;
            foreach (self::ENTU_FIELDS as $field) $entu->$field = $bndlu->$field;
        }
        return [$entu, $mdlu];
    }

    public function update($entu, $mdlu, $ui, $ent) {
        if ((null === $mdlu) || ($mdlu->deleted)) {
            throw new \Exception();
        }
        if ((null !== $entu) && ($entu->ent != $ent->get_code())) {
            throw new \Exception();
        }
        $mdlu = $this->update_mdlu($mdlu, $ui, $ent);
        if (null === $entu) {
            $entu = $this->create_entu($mdlu, $ui, $ent);
        } else {
            $entu = $this->update_entu($entu, $mdlu, $ui, $ent);
        }
        $this->update_role($entu, $mdlu, $ui, $ent);
        $this->update_cohort($entu, $mdlu, $ui, $ent);
        return [$entu, $mdlu];
    }

    protected function create_mdlu($ui, $ent) {
        $_mdlu = new \stdClass();
        $this->applycreds($_mdlu, $ui, $ent);
        $_mdlu->auth = 'entsync';
        $_mdlu->confirmed = 1;
        $_mdlu->firstname = $ui->firstname;
        $_mdlu->lastname = $ui->lastname;
        $_mdlu->email = $this->get_fakeemail($ui->profile);
        $_mdlu->emailstop = 1;
        $_mdlu->lang = 'fr';
        $_mdlu->id = \user_create_user($_mdlu, false, true);
        $_mdlu->deleted = 0;
        $_mdlu->suspended = 0;
        return $_mdlu;
    }

    protected function update_mdlu($mdlu, $ui, $ent) {
        $_mdlu = new \stdClass();
        $_mdlu->isdirty = false;
        $_mdlu->id = $mdlu->id;
        $this->updatecreds($mdlu, $_mdlu, $ui, $ent);
        if ($mdlu->auth != 'entsync') {
            $mdlu->auth = 'entsync';
            $_mdlu->auth = 'entsync';
            $_mdlu->isdirty = true;
        }
        if ($mdlu->suspended) {
            $mdlu->suspended = 0;
            $_mdlu->suspended = 0;
            $_mdlu->isdirty = true;
        }
        if (!$mdlu->confirmed) {
            $mdlu->confirmed = 1;
            $_mdlu->confirmed = 1;
            $_mdlu->isdirty = true;
        }
        if (!empty($ui->firstname) && ($mdlu->firstname != $ui->firstname)) {
            $mdlu->firstname = $ui->firstname;
            $_mdlu->firstname = $ui->firstname;
            $_mdlu->isdirty = true;
        }
        if (!empty($ui->lastname) && ($mdlu->lastname != $ui->lastname)) {
            $mdlu->lastname = $ui->lastname;
            $_mdlu->lastname = $ui->lastname;
            $_mdlu->isdirty = true;
        }
        if ($_mdlu->isdirty) {
            unset($_mdlu->isdirty);
            \user_update_user($_mdlu, false, true);
        }
        return $mdlu;
    }

    protected function create_entu($mdlu, $ui, $ent) {
        $_entu = new \stdClass();
        $_entu->sync = 1;
        $_entu->archived = 0;
        $_entu->archivedsince = 0;
        $_entu->userid = $mdlu->id;
        $_entu->ent = $ent->get_code();
        $_entu->profile = $ui->profile;
        $_entu->uid = $ui->user;
        $_entu->id = $this->DB->insert_record(self::TABLE, $_entu, true);
        return $_entu;
    }

    protected function update_entu($entu, $mdlu, $ui, $ent) {
        $_entu = new \stdClass();
        $_entu->isdirty = false;
        $_entu->id = $entu->id;
        if ($entu->archived) {
            $entu->archived = 0;
            $_entu->archived = 0;
            $entu->archivedsince = 0;
            $_entu->archivedsince = 0;
            $_entu->isdirty = true;
        }
        if ($entu->sync != 1) {
            $entu->sync = 1;
            $_entu->sync = 1;
            $_entu->isdirty = true;
        }
        if ($entu->userid != $mdlu->id) {
            $entu->userid = $mdlu->id;
            $_entu->userid = $mdlu->id;
            $_entu->isdirty = true;
        }
        if ($entu->ent != $ent->get_code()) {
            $entu->ent = $ent->get_code();
            $_entu->ent = $ent->get_code();
            $_entu->isdirty = true;
        }
        if ($entu->profile != $ui->profile) {
            $entu->profile = $ui->profile;
            $_entu->profile = $ui->profile;
            $_entu->isdirty = true;
        }
        if ($entu->uid != $ui->user) {
            $entu->uid = $ui->user;
            $_entu->uid = $ui->user;
            $_entu->isdirty = true;
        }
        if ($_entu->isdirty) {
            unset($_entu->isdirty);
            $this->DB->update_record(self::TABLE, $_entu);
        }
        return $entu;
    }

    protected function update_role($entu, $mdlu, $ui, $ent) {
        if (empty($ui->profile)) return;
        if ($ui->profile == 2) {
            $role = $this->conf->role_ens();
        } else {
            $role = -1;
        }
        rolehelper::updaterole($mdlu->id, $role);
    }

    protected function update_cohort($entu, $mdlu, $ui, $ent) {
        if (empty($ui->profile)) return;
        if (empty($ui->classe)) {
            $this->cohorts->clear_user_div_and_groups($mdlu->id);
        } else if (\in_array($ui->profile, $ent->get_profileswithcohorts())) {
            if (null == $ui->groupes) {
                $this->cohorts->set_user_div($mdlu->id, $ui->classe);
            } else {
                $this->cohorts->set_user_div_and_groups($mdlu->id, $ui->classe, $ui->groupes);
            }
        }
    }

    protected function get_fakeemail($profile) {
        switch ($profile) {
            case 1: return self::FAKEEMAIL_ELEV;
            case 2: return self::FAKEEMAIL_ENS;
            case 4: return self::FAKEEMAIL_PERS;
        }
        return self::FAKEEMAIL;
    }

    protected function applycreds($_mdlu, $ui, $ent) {
        if ($ent->is_sso()) {
            $_mdlu->password = AUTH_PASSWORD_NOT_CACHED;
            $entcode = $ent->get_code();
            $username = "entsync.{$entcode}.{$ui->user}";
        } else {
            $pw = stringhelper::rnd_string();
            $_mdlu->password = "entsync\\{$pw}";
            $_fn = \core_text::substr(stringhelper::simplify_name($ui->firstname), 0, 1);
            $_ln = stringhelper::simplify_name($ui->lastname);
            $username = $_fn . $_ln;
        }
        $clean = \core_user::clean_field($username, 'username');
        if (0 === $this->DB->count_records('user', ['username' => $clean])) {
            $_mdlu->username = $clean;
        } else {
            $clean = \rtrim($clean, '0..9');
            if (0 === $this->DB->count_records('user', ['username' => $clean])) {
                $_mdlu->username = $clean;
            } else {
                $i = 1;
                while (0 !== $this->DB->count_records('user', ['username' => $clean . $i])) {
                    ++$i;
                }
                $_mdlu->username = $clean . $i;
            }
        }
        $_mdlu->mnethostid = $this->CFG->mnet_localhost_id;
    }

    protected function updatecreds($mdlu, $_mdlu, $ui, $ent) {
        if (! $ent->is_sso()) {
            if (empty($mdlu->password))
                throw new \Exception();
            if ($mdlu->password === AUTH_PASSWORD_NOT_CACHED) {
                $this->applycreds($_mdlu, $ui, $ent);
                $mdlu->username = $_mdlu->username;
                $mdlu->mnethostid = $_mdlu->mnethostid;
                $mdlu->password = $_mdlu->password;
                $_mdlu->isdirty = true;
            }
        }
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * lib de fonctions utilisées pour la synchronisation
 *
 * @package    auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\synchronizers;
defined('MOODLE_INTERNAL') || die();

use \auth_entsync\helpers\cohorthelper;
use \auth_entsync\helpers\rolehelper;

/**
 * Classe qui expose les méthodes utilisées pour la synchronisation des utilisateurs
 *
 * Cette classe est dérivée en deux classes spécifiques suivant le mode
 * d'authentification 'cas' ou 'manual'
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_sync {
    /**
     * Durée d'archivage des utilisateurs en secondes
     */
    const ARCHIVDURATION = 15552000; // Php 5.4 ne permet pas : 3600*24*30*6 environ 6 mois.

    /**
     * Fausse email attribuée aux utilisateurs
     */
    const FAKEEMAIL = 'inconnu@ac-paris.invalid';
    const FAKEEMAIL_ELEV = 'eleve@ac-paris.invalid';
    const FAKEEMAIL_ENS = 'enseignant@ac-paris.invalid';
    const FAKEEMAIL_PERS = 'personnel@ac-paris.invalid';

    const COHORT_PRFX = 'auto_';
    const COHORT_PRFX_LEN = 5;

    /**
     * @var string|null Null if ok, error msg otherwise
     */
    protected $_error;

    protected $_report;

    protected $_progressreporter = null;

    protected $_readytosyncusers = [1 => -1, 2 => -1];

    protected $_currenttime;

    protected $_limitarchiv;

    protected $_otherlookup;

    protected $_profilestosync;

    protected $_profileswithcohort = [];

    protected $_existingentu;

    protected $_existingentuother;

    /**
     * @var int Code de l'ent synchronisé
     */
    public $entcode;

    /**
     * @var int Code de la structure
     */
    public $structcode = 0;

    /**
     * @var array Indique les rôles système par profil
     */
    public $roles = [1 => 0, 2 => 0];

    /**
     * Get last error
     *
     * @return string error text of null if none
     */
    public function get_error() {
        return $this->_error;
    }

    public function set_progress_reporter($progressreporter) {
        $this->_progressreporter = $progressreporter;
    }

    public function set_profilestosync($profilelist) {
        $this->_profilestosync = $profilelist;
    }

    public function set_profileswithcohorts($profilelist) {
        $this->_profileswithcohort = $profilelist;
    }

    protected function get_fakeemail($profile) {
        switch ($profile) {
            case 1 : return $this::FAKEEMAIL_ELEV;
            case 2 : return $this::FAKEEMAIL_ENS;
            case 3 : return $this::FAKEEMAIL_PERS;
        }
        return $this::FAKEEMAIL;
    }

    /**
     * effectue la mise à jour ou la création de l'utilisateur
     *
     * $mdlu si non false doit comporter les champs :
     * 'id, auth, confirmed, deleted, suspended, mnethostid, username, password, firstname, lastname'
     *
     * @param stdClass|false $entu L'utilisateur géré s'il a été trouvé
     * @param stdClass|false $mdlu L'utilisateur moodle associé s'il a été trouvé
     * @param stdClass $iu L'utilisateur importé
     */
    protected function update($entu, $mdlu, $iu) {
        global $DB;
        // Crée $_mdlu : record pour la mise à jour.
        $_mdlu = new \stdClass();
        $_mdlu->isdirty = false;

        if ($mdlu && $mdlu->deleted) {
            $mdlu = false;
        }

        // Création ou mise à jour de l'utilisateur moodle.
        if ($mdlu) {
            // Mise à jour de l'utilisateur.
            $_mdlu->id = $mdlu->id;
            $this->updatecreds($mdlu, $_mdlu, $iu);
            if ($mdlu->auth != 'entsync') {
                $_mdlu->auth = 'entsync';
                $_mdlu->isdirty = true;
            }
            if ($mdlu->suspended) {
                $_mdlu->suspended = 0;
                $_mdlu->isdirty = true;
            }
            if (!$mdlu->confirmed) {
                $_mdlu->confirmed = 1;
                $_mdlu->isdirty = true;
            }
            if ($mdlu->firstname != $iu->firstname) {
                $_mdlu->firstname = $iu->firstname;
                $_mdlu->isdirty = true;
            }
            if ($mdlu->lastname != $iu->lastname) {
                $_mdlu->lastname = $iu->lastname;
                $_mdlu->isdirty = true;
            }
            if ($_mdlu->isdirty) {
                unset($_mdlu->isdirty);
                \user_update_user($_mdlu, false, true);
                ++$this->_report->updated;
            }
        } else {
            // Création de l'utilisateur.
            $_mdlu->isdirty = true;
            $this->applycreds($_mdlu, $iu);
            $_mdlu->auth = 'entsync';
            $_mdlu->confirmed = 1;
            $_mdlu->firstname = $iu->firstname;
            $_mdlu->lastname = $iu->lastname;
            $_mdlu->email = $this->get_fakeemail($iu->profile);
            $_mdlu->emailstop = 1;
            $_mdlu->lang = 'fr';
            unset($_mdlu->isdirty);
            $_mdlu->id = \user_create_user($_mdlu, false, true);
            ++$this->_report->created;
        }

        // Crée $_entu : record pour la mise à jour.
        $_entu = new \stdClass();
        $_entu->isdirty = false;

        // Création ou mise à jour de l'utilisateur géré.
        if ($entu) {
            // L'utilisateur existe déjà dans la table ent.
            $entu->checked = true;
            $_entu->id = $entu->id;
            if ($entu->archived) {
                $_entu->archived = 0;
                $_entu->archivedsince = 0;
                $_entu->isdirty = true;
            }
            if ($entu->sync != 1) {
                $_entu->sync = 1;
                $_entu->isdirty = true;
            }
            if ($entu->userid != $_mdlu->id) {
                $_entu->userid = $_mdlu->id;
                $_entu->isdirty = true;
            }
            if ($entu->ent != $this->entcode) {
                $_entu->ent = $this->entcode;
                $_entu->isdirty = true;
            }
            if ($entu->profile != $iu->profile) {
                $_entu->profile = $iu->profile;
                $_entu->isdirty = true;
            }
            if ($entu->uid != $iu->uid) {
                $_entu->uid = $iu->uid;
                $_entu->isdirty = true;
            }
            if ($_entu->isdirty) {
                unset($_entu->isdirty);
                $DB->update_record('auth_entsync_user', $_entu);
            }
        } else {
            // Il s'agit d'un nouvel utilisateur dans la table ent.
            $_entu->isdirty = true;
            $_entu->sync = 1;
            $_entu->archived = 0;
            $_entu->archivedsince = 0;
            $_entu->userid = $_mdlu->id;
            $_entu->ent = $this->entcode;
            $_entu->profile = $iu->profile;
            $_entu->uid = $iu->uid;
            unset($_entu->isdirty);
            $_entu->id = $DB->insert_record('auth_entsync_user', $_entu, true);
        }

        rolehelper::updaterole($_mdlu->id, $this->roles[$iu->profile]);

        if (\in_array($iu->profile, $this->_profileswithcohort)) {
            cohorthelper::set_cohort($_mdlu->id, $iu->cohortname);
        }

        return [$_entu, $_mdlu];
    }

    /**
     * Archive l'utilisateur géré
     *
     * @param $entu L'utilisateur géré
     */
    protected function archive($entu) {
        global $DB;
        if (!$entu->archived) {
            // Il n'était pas déjà archivé.
            $_entu = new stdClass();
            $_entu->id = $entu->id;
            $_entu->archived = 1;
            $_entu->archivedsince = $this->_currenttime;
            $DB->update_record('auth_entsync_user', $_entu);

            if (0 === $DB->count_records('auth_entsync_user', ['userid' => $entu->userid, 'archived' => 0])) {
                // Il est archivé dans tous les ent
                // on retire son éventuel rôle système (si c'est un prof).
                rolehelper::removeroles($entu->userid);
                
                // On le sort de sa cohorte éventuelle (si c'est un élève).
                cohorthelper::removecohorts($entu->userid);
            }
        }

/*         // Le reste doit êter géré dans la tâche programmée. 
        //         //on supprime les $entu expirés
        //         $select = "userid = :userid AND archived = 1 AND archivedsince < :limit";
        //         $DB->delete_records_select('auth_entsync_user', $select,
        //             ['userid' => $entu->userid, 'limit' => $this->_limitarchiv]);
        //         //on recherche le $mdlu
        //         if( $mdlu = $DB->get_record('user',
        //             ['id' => $entu->userid, 'deleted' => 0], 'id, username, suspended') )  {
        //             if(0 === $DB->count_records('auth_entsync_user', ['userid' => $entu->userid])) {
        //                 //il n'est plus référencé
        //                 delete_user($mdlu);
        //             } else {
        //                 if(0 === $DB->count_records('auth_entsync_user', ['userid' => $entu->userid, 'archived' => 0])) {
        //                     //il est archivé
        //                     $_mdlu = new stdClass();
        //                     $_mdlu->id = $mdlu->id;
        //                     $_mdlu->suspended = true;
        //                     $DB->update_record('user', $_mdlu);
        //                 }
        //             }
        //         }.
 */    }

    public function dosync($iurs) {
        global $DB;
        if (is_null($this->_progressreporter)) {
            $this->_progressreporter = new \core\progress\none();
        }

        $this->_report = (object) [
            'errors' => 0,
            'byid' => 0,
            'bynames' => 0,
            'notbynames' => 0,
            'multinames' => 0,
            'profilmismatched' => 0,
            'checkedcollision' => 0,
            'entcollision' => 0,
            'created' => 0,
            'updated' => 0
        ];

        $this->_progressreporter->start_progress('', 10);
        $this->_progressreporter->start_progress('', 1, 1);
        $this->_currenttime = time();
        $this->_profileswithcohort = array_intersect($this->_profileswithcohort, $this->_profilestosync);
        $this->_otherlookup = $DB->count_records_select('auth_entsync_user', "ent <> :ent",
            ['ent' => $this->entcode]) > 0;
        $this->buildexistinglst();
        $this->_progressreporter->end_progress();
        $this->_progressreporter->start_progress('', count($iurs), 6);
        $progresscnt = 0;
        // On itère sur tous les utilisateurs importés.
        foreach ($iurs as $iu) {
            $this->_progressreporter->progress($progresscnt);
            ++$progresscnt;

            if (\in_array($iu->profile, $this->_profilestosync) && $this->validate_user($iu)) {
                // Chercher l'utilisateur.
                if ($ret = $this->lookforuser($iu)) {
                    list($entu, $mdlu) = $ret;
                    list($_entu, $_mdlu) = $this->update($entu, $mdlu, $iu);
                }
            }
        }
        unset($iurs);
        $this->_progressreporter->end_progress();

        $this->_limitarchiv = $this->_currenttime - $this::ARCHIVDURATION;

        $this->_progressreporter->start_progress('', \count($this->_existingentu), 3);
        $progresscnt = 0;
        foreach ($this->_existingentu as $entu) {
            $this->_progressreporter->progress($progresscnt);
            ++$progresscnt;
            if (!$entu->checked) {
                $_entu = $this->archive($entu);
            }
        }
        unset($this->_existingentu);
        unset($this->_existingentuother);
        $this->_progressreporter->end_progress();
        $this->_progressreporter->end_progress();
        return $this->_report;
    }

    protected function buildexistinglst() {
        $entus = auth_entsync_usertbl::get_entus(-1, $this->entcode);
        $this->_existingentu = array();
        $this->_existingentuother = array();
        while ($entus) {
            $entu = \array_pop($entus);
            if (\in_array($entu->profile, $this->_profilestosync)) {
                $entu->checked = false;
                $this->_existingentu[$entu->uid] = $entu;
            } else {
                $this->_existingentuother[$entu->uid] = 1;
            }
        }
    }

    /**
     * Recherche l'utilisateur dans les utilisateurs existants
     *
     * valeurs de retour :
     *  - false signifie que l'utilisateur ne pourra pas être créé (rare)
     *  - [$entu, $mdlu]
     *
     *  [set, set]     ->utilisateurs rappochés par uid
     *  [set, false]   ->ne devrait pas se produire, utilisateur moodle probablement effacé
     *  [false, set]   ->utilisateur trouvé par nom / prénom
     *  [false, false] ->nouvel utilisateurs
     *
     * @param stdClass $iu l'utilisateur à rechercher
     * @return false|array l'utilisateur trouvé sous la forme [$entu, $mdlu]. Peuvent être null, false
     * si l'utilisateur devrait être ignoré
     */
    protected function lookforuser($iu) {
        global $DB, $CFG;
        if (\array_key_exists($iu->uid, $this->_existingentu)) {
            $entu = $this->_existingentu[$iu->uid];
        } else {
            if (\array_key_exists($iu->uid, $this->_existingentuother)) {
                // Même ent mais autre profil
                // ne devrait pas se produire.
                ++$this->_report->profilmismatched;
                return false;
            } else {
                $entu = null;
            }
        }
        if ($entu) {
            if ($entu->checked) {
                // Ne devrait pas se produire.
                ++$this->_report->checkedcollision;
                return false;
            }
            if ($entu->profile == $iu->profile) {
                // Même ent et même profil, c'est bien lui.
                if ($mdlu = $DB->get_record('user', ['id' => $entu->userid, 'deleted' => 0,
                    'mnethostid' => $CFG->mnet_localhost_id],
                    'id, auth, confirmed, deleted, suspended, mnethostid, username, password, firstname, lastname'
                    )) {
                    ++$this->_report->byid;
                    return [$entu, $mdlu];
                }
                // Si $mdlu n'est pas là, alors recherche par nom prenom.
                return [$entu, $this->lookforuserbynames($iu)];

            } else {
                // Même ent mais autre profil
                // ne devrait pas se produire.
                ++$this->_report->profilmismatched;
                return false;
            }
        } else {
            // Chercher par nom prenom.
            return [false, $this->lookforuserbynames($iu)];
        }
    }

    protected function lookforuserbynames($iu) {
        global $DB, $CFG;
        if (!$this->_otherlookup) {
            ++$this->_report->notbynames;
            return false;
        }

        $sql = $DB->sql_concat('a.id', 'b.ent');
        $sql = "SELECT {$sql} AS id, a.id AS userid, b.ent AS ent, b.profile AS profile
        FROM {user} a
        JOIN {auth_entsync_user} b ON b.userid = a.id
        WHERE a.auth = 'entsync'
        AND a.deleted = 0
        AND a.mnethostid = :mnethostid
        AND UPPER(a.firstname) = UPPER(:firstname)
        AND UPPER(a.lastname) = UPPER(:lastname)";
        $lst = $DB->get_records_sql($sql,
            ['firstname' => $iu->firstname, 'lastname' => $iu->lastname, 'mnethostid' => $CFG->mnet_localhost_id]);
        if (count($lst) === 0) {
            ++$this->_report->notbynames;
            return false;
        }
        $userids = array();
        foreach ($lst as $rec) {
            if ($this->entcode == $rec->ent) {
                ++$this->_report->notbynames;
                ++$this->_report->entcollision;
                return false;
            }
            if ($iu->profile == $rec->profile)
                $userids[] = $rec->userid;
        }
        $userids = \array_unique($userids);
        if (count($userids) === 1) {
            ++$this->_report->bynames;
            return $DB->get_record('user', ['id' => $userids[0]],
                    'id, auth, confirmed, deleted, suspended, mnethostid, username, password, firstname, lastname'
                    );
        }
        ++$this->_report->notbynames;
        ++$this->_report->multinames;
        return false;
    }

    /**
     * Valide si l'utilisateur est importable
     *
     * @param stdClass $iu
     * @return boolean
     */
    protected abstract function validate_user($iu);
    protected abstract function applycreds($_mdlu, $iu);
    protected abstract function updatecreds($mdlu, $_mdlu, $iu);
}

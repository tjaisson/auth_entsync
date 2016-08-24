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

defined('MOODLE_INTERNAL') || die();

require_once('locallib.php');
require_once('rolehelper.php');
require_once('cohorthelper.php');

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
abstract class auth_entsync_sync {
	/**
	 * Durée d'archivage des utilisateurs en secondes
	 */
    const ARCHIVDURATION = 3600*24*30*6; // environ 6 mois;
    
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
    
    protected $_readytosyncusers = [1=>-1,2=>-1];
    
    protected $_currenttime;
    
    protected $_limitarchiv;
    
    protected $_otherlookup;

    protected $_profilestosync;
    
    protected $_profileswithcohort = [];
    
    /**
     * @var int Code de l'ent courant
     */
    public $entcode;

    /**
     * @var int Code de la structure
     */
    public $structcode = 0;

/**
     * @var array Indique les rôles système par profil
     */
    public $roles = [1=>0,2=>0];
    
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
    
    /**
     * Retourne le nombre d'utilisateurs présents dans la table temporaire
     * par profil (cached)
     *
     * @param int $profile Le profil des utilisateurs recherchés
     * @return int Le nombre d'utilisateurs présents dans la table temporaire
     */
    public function get_readytosyncusers($profile) {
        global $DB;
        if($this->_readytosyncusers[$profile] < 0) {
            $this->_readytosyncusers[$profile] = $DB->count_records('auth_entsync_tmpul',
                ['profile' => $profile]);
        }
        return $this->_readytosyncusers[$profile];
    }
    
    public function set_profilestosync($profilelist) {
        $this->_profilestosync = $profilelist;
    }
    
    public function set_profileswithcohorts($profilelist) {
        $this->_profileswithcohort = $profilelist;
    }
    
    protected function uncheckusers() {
    	global $DB;
    	$DB->set_field('auth_entsync_user', 'checked', false);
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
        // $_mdlu : record pour la mise à jour
        $_mdlu = new stdClass();
        $_mdlu->isdirty = false;

        if($mdlu && $mdlu->deleted) {
            $mdlu = false;
        }
        
        //création ou mise à jour de l'utilisateur moodle
        if($mdlu) {
            //mise à jour de l'utilisateur
            $_mdlu->id = $mdlu->id;
            $this->updatecreds($mdlu, $_mdlu, $iu);
            if($mdlu->auth != 'entsync') {$_mdlu->auth = 'entsync'; $_mdlu->isdirty = true;}
            if($mdlu->suspended) {$_mdlu->suspended = 0; $_mdlu->isdirty = true;}
            if(!$mdlu->confirmed) {$_mdlu->confirmed = 1; $_mdlu->isdirty = true;}
            if($mdlu->firstname != $iu->firstname) {$_mdlu->firstname = $iu->firstname; $_mdlu->isdirty = true;}
            if($mdlu->lastname != $iu->lastname) {$_mdlu->lastname = $iu->lastname; $_mdlu->isdirty = true;}
            if($_mdlu->isdirty) {
                unset($_mdlu->isdirty);
                user_update_user($_mdlu, false, true);
            }
        } else {
            //création de l'utilisateur
            $_mdlu->isdirty = true;
            $this->applycreds($_mdlu, $iu);
            $_mdlu->auth = 'entsync';
            $_mdlu->confirmed = 1;
            $_mdlu->firstname = $iu->firstname;
            $_mdlu->lastname = $iu->lastname;
            $_mdlu->email = $this->get_fakeemail($iu->profile);
            $_mdlu->emailstop = 1;
            unset($_mdlu->isdirty);
            $_mdlu->id = user_create_user($_mdlu, false, true);
        }

        // $_entu : record pour la mise à jour
        $_entu = new stdClass();
        $_entu->checked = true;

        //création ou mise à jour de l'utilisateur géré
        if($entu) {
        	//l'utilisateur existe déjà dans la table ent
        	$_entu->id = $entu->id;
        	if($entu->archived) {
        	    $_entu->archived = 0;
        	    $_entu->archivedsince = 0;
        	}
        	if($entu->sync != 1) $_entu->sync = 1;
            if($entu->userid != $_mdlu->id) $_entu->userid = $_mdlu->id;
        	if($entu->ent != $this->entcode) $_entu->ent = $this->entcode;
        	if($entu->profile != $iu->profile) $_entu->profile = $iu->profile;
        	if($entu->uid != $iu->uid) $_entu->uid = $iu->uid;
            $DB->update_record('auth_entsync_user', $_entu);
        } else {
        	//il s'agit d'un nouvel utilisateur dans la table ent
            $_entu->sync = 1;
            $_entu->userid = $_mdlu->id;
        	$_entu->ent = $this->entcode;
        	$_entu->profile = $iu->profile;
        	$_entu->uid = $iu->uid;
            $_entu->id = $DB->insert_record('auth_entsync_user', $_entu, true);
        }

        auth_entsync_rolehelper::updaterole($_mdlu->id, $this->roles[$iu->profile]);
        
        if(in_array($iu->profile, $this->_profileswithcohort)) {
            auth_entsync_cohorthelper::set_cohort($_mdlu->id, $iu->cohortname);
        }
        
        return [$_entu, $_mdlu];
    }

    /**
     * Archive ou supprime l'utilisateur géré
     *
     * @param $entu L'utilisateur géré
     */
    protected function archiveordelete($entu) {
        global $DB;
        //s'il n'est pas déjà archivé
        if(!$entu->archived) {
            $_entu = new stdClass();
            $_entu->id = $entu->id;
            $_entu->archived = 1;
            $_entu->archivedsince = $this->_currenttime;
            $DB->update_record('auth_entsync_user', $_entu);
        }
        
        //on supprime les $entu expirés
        $select = "userid = :userid AND archived = 1 AND archivedsince < :limit";
        $DB->delete_records_select('auth_entsync_user', $select,
            ['userid' => $entu->userid, 'limit' => $this->_limitarchiv]);
        
        //on recherche le $mdlu
        if( $mdlu = $DB->get_record('user',
            ['id' => $entu->userid, 'deleted' => 0], 'id, username, suspended') )  {
        
            if(0 === $DB->count_records('auth_entsync_user', ['userid' => $entu->id])) {
                //il n'est plus référencé
                delete_user($mdlu);
            } else {
                if(0 === $DB->count_records('auth_entsync_user', ['userid' => $entu->id, 'archived' => 0])) {
                    //il est archivé
                    $_mdlu = new stdClass();
                    $_mdlu->id = $mdlu->id;
                    $_mdlu->suspended = true;
                    $DB->update_record('user', $_mdlu);
                }
            }
        }
    }

	public function dosync() {
		global $DB;
		$this->_report = (object) [
		  'errors' => 0,
		  'byid' => 0,
		  'bynames' => 0,
		  'notbynames' => 0,
		  'multinames' => 0,
		  'profilmismatched' => 0,
		  'checkedcollision' => 0,
		  'entcollision' => 0
		];
		
		$this->_progressreporter->start_progress('',10);
		$this->_progressreporter->start_progress('',1,1);
		
		$this->_currenttime = time();
		//on décoche tous les utilisateurs de l'ent
        $this->uncheckusers();

        $this->_profileswithcohort = array_intersect($this->_profileswithcohort, $this->_profilestosync);

        $this->_otherlookup = $DB->count_records_select('auth_entsync_user', "ent <> :ent",
            ['ent' => $this->entcode]) > 0;

        $iurs = $DB->get_records('auth_entsync_tmpul');
        $this->_progressreporter->end_progress();
        $this->_progressreporter->start_progress('',count($iurs),6);
		$progresscnt = 0;
		//on itère sur tous les utilisateurs importés
		foreach ($iurs as $iu) {
            $this->_progressreporter->progress($progresscnt);
            ++$progresscnt;

		    if(in_array($iu->profile, $this->_profilestosync) && $this->validate_user($iu)) {
                //cherche l'utilsateur
                if($ret = $this->lookforuser($iu)) {
                   // if($iu->id == 302) var_dump($ret);
                    list($entu, $mdlu) = $ret;
                    list($_entu, $_mdlu) = $this->update($entu, $mdlu, $iu);
                }
            }
		}
		unset($iur);
        $this->_progressreporter->end_progress();

        $this->_progressreporter->start_progress('',1,1);
        $this->_limitarchiv = $this->_currenttime - $this::ARCHIVDURATION;
        
        list($_select, $params) = $DB->get_in_or_equal($this->_profilestosync, SQL_PARAMS_NAMED, 'prf');
        $_select = "( checked = 0 ) and ( sync = 1 ) and ( ent = :ent ) and ( profile {$_select} )";
        $params['ent'] = $this->entcode;
        $iurs = $DB->get_records_select('auth_entsync_user', $_select, $params);
        $this->_progressreporter->end_progress();

        $this->_progressreporter->start_progress('',count($iurs),2);
		$progresscnt = 0;
        foreach($iurs as $entu) {
            $this->_progressreporter->progress($progresscnt);
		    ++$progresscnt;
            $_entu = $this->archiveordelete($entu);
        }
        unset($iur);
        $this->_progressreporter->end_progress();
        $this->_progressreporter->end_progress();
        return $this->_report;
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
       $entu = $DB->get_record('auth_entsync_user', ['ent'=>$this->entcode, 'uid' => $iu->uid]);
       if($entu) {
           if($entu->checked) {
               //ne devrait pas se produire
               ++$this->_report->checkedcollision;
               return false;
           }
           if($entu->profile == $iu->profile) {
               //même ent et même profil, c'est bien lui
               if($mdlu = $DB->get_record('user', ['id' => $entu->userid, 'deleted' => 0,
                   'mnethostid' => $CFG->mnet_localhost_id],
                   'id, auth, confirmed, deleted, suspended, mnethostid, username, password, firstname, lastname'
                   )) {
                   ++$this->_report->byid;
                   return [$entu, $mdlu];
               }
               //si $mdlu n'est pas là -> recherche par nom prenom
               return [$entu, $this->lookforuserbynames($iu)];
                       
           } else {
               //même ent mais autre profil
               //ne devrait pas se produire
               ++$this->_report->profilmismatched;
               return false;
           }
       } else {
           //cherche par nom prenom
           return [false, $this->lookforuserbynames($iu)];
       }
	}
	
    protected function lookforuserbynames($iu) {
        global $DB, $CFG;
        if(!$this->_otherlookup) {
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
        if(count($lst) === 0) {
            ++$this->_report->notbynames;
            return false;
        }
        $userids = array();
        foreach ($lst as $rec) {
            if($this->entcode == $rec->ent) {
                ++$this->_report->notbynames;
                ++$this->_report->entcollision;
                return false;
            }
            if($iu->profile == $rec->profile)
                $userids[] = $rec->userid;
        }
        $userids = array_unique($userids);
        if(count($userids) === 1) {
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

/**
 * Synchronizer spécifique pour le mode d'authentification 'cas'
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_sync_cas extends auth_entsync_sync {
    protected function validate_user($iu) {
        if(empty($iu->uid)) return false;
        return true;
    }
    
    protected function applycreds($_mdlu, $iu) {
        global $DB, $CFG;
	    $_mdlu->username = "entsync.{$this->entcode}.{$iu->uid}";
	    $clean = core_user::clean_field($_mdlu->username, 'username');
	    if ($_mdlu->username !== $clean) {
	        if(0 === $DB->count_records('user', ['username' => $clean])) {
	            $_mdlu->username = $clean;
	        } else {
	            $i = 1;
	            while (0 !== $DB->count_records('user', ['username' => $clean . $i])) {
	                ++$i;
	            }
	            $_mdlu->username = $clean . $i;
	        }
	    }
	    
	    $_mdlu->mnethostid = $CFG->mnet_localhost_id;
	    $_mdlu->password = AUTH_PASSWORD_NOT_CACHED;
	}

	protected function updatecreds($mdlu, $_mdlu, $iu) {
	}

}

/**
 * Synchronizer spécifique pour le mode d'authentification 'local'
 * 
 * TODO : n'est pas encore fonctionnel
 *
 * @package   auth_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_entsync_sync_local extends auth_entsync_sync {
    protected function validate_user($iu) {
        return (!empty($iu->firstname)) && (!empty($iu->lastname));
    }
    protected function applycreds($_mdlu, $iu) {
        global $CFG;
        $_mdlu->isdirty = true;
//      TODO :  $_mdlu->username = create
        $_mdlu-> $CFG->mnet_localhost_id;
//      TODO :  $pw = create;
        $_mdlu->password = "entsync\\{$pw}";
    }

    protected function updatecreds($mdlu, $_mdlu, $iu) {
        if($mdlu->password == AUTH_PASSWORD_NOT_CACHED) {
            $this->applycreds($_mdlu, $iu);
        }
    }
}
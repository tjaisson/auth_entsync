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
 * Classe de base pour la gestion des ents
 *
 * ***
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Classe de base pour la définition des ENT
 *
 * Pour définir un ent, il faut dériver cette classe en donnant un nom de la
 * forme tool_entsyn_ent_XXXX
 *
 * @package   tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class auth_entsync_ent_base {
    private static $_profilelist = array();
    private static $_entinsts = array();
    private static $_enabledents = array();
    private static $_entclasses = array();

    public $nomlong;
    public $nomcourt;

    protected $_code;
    protected $_entclass;
    protected $_settings;

    public function get_code() {
        return $this->_code;
    }

    public function get_entclass() {
        return $this->_entclass;
    }

    public function is_enabled() {
        return in_array($this->_code, self::$_enabledents);
    }

    public function is_sso() {
        return $this->get_mode() !== 'local';
    }

    /**
     * Enregistre un ent dans la liste des ents disponibles
     *
     * Il faut attribuer à l'ent un code entier non nul, unique et immuable
     * L'ordre dans lequel les ents sont enregistrés définit l'ordre dans la liste
     * déroulante
     *
     * Lorsque tous les ents sont enregistrer, il faut appeler {@link end_register()}
     *
     * @param string $entclass le XXXX dans le nom de la classe
     *                          sous la forme auth_entsyn_ent_XXXX
     * @param int $entcode le code non nul, unique et invariable de l'ent
     */
    public static function register($entclass, $entcode) {
        $class = 'auth_entsync_ent_'.$entclass;
        $ent = new $class();
        $ent->_entclass = $entclass;
        $ent->_code = $entcode;
        self::$_entinsts[$entcode] = $ent;
    }

    public static function end_register() {
        if ($entsenabled = get_config('auth_entsync', 'enabledents')) {
            $entsenabled = array_unique(explode(';', $entsenabled));
            foreach ($entsenabled as $entcode) {
                if (self::ent_exists($entcode)) {
                    self::$_enabledents[] = $entcode;
                }
            }
        } else {
            self::$_enabledents = [];
        }
        $entclasses = array();
        foreach (self::$_entinsts as $entcode => $ent) {
            self::$_entclasses[$ent->_entclass] = $entcode;
        }
    }

     /**
      *
      * Retourne l'instance de la classe définissant l'ent du code donné.
      *
      * @param int|string $entcode Code de l'ent dont il faut retourner une instance
      * @return false|auth_entsync_ent_base false si aucun ent ne correspond au
      *                         code
      */
    public static function get_ent($entcode) {
        if (is_number($entcode)) {
            $entcode = (int)$entcode;
        } else {
            if (!array_key_exists($entcode, self::$_entclasses)) {
                return false;
            }
            $entcode = (int)self::$_entclasses[$entcode];
        }
        if (!self::ent_exists($entcode)) {
            return false;
        }
        return self::$_entinsts[$entcode];
    }

    /**
     * @return array[auth_entsync_ent_base]
     */
    public static function get_ents() {
        return self::$_entinsts;
    }

    public static function count_enabled() {
        return count(self::$_enabledents);
    }

    public static function ent_exists($entcode) {
        return array_key_exists($entcode, self::$_entinsts);
    }

    public static function ent_isenabled($entcode) {
        if(!self::ent_exists($entcode)) {
            return false;
        }
        return in_array($entcode, self::$_enabledents);
    }

    public static function enable_ent($entcode) {
        // Add to enabled list.
        if (!self::ent_exists($entcode)) {
            return;
        }

        if (!in_array($entcode, self::$_enabledents)) {
            self::$_enabledents[] = $entcode;
            self::$_enabledents = array_unique(self::$_enabledents);
            set_config('enabledents', implode(';', self::$_enabledents), 'auth_entsync');
        }
    }

    public static function disable_ent($entcode) {
        $key = array_search($entcode, self::$_enabledents);
        if ($key !== false) {
            unset(self::$_enabledents[$key]);
            set_config('enabledents', implode(';', self::$_enabledents), 'auth_entsync');
        }
    }

    public static function set_formdata($config, $mform) {
        $datas = array();
        foreach (self::$_entinsts as $ent) {
            if ($ent->is_enabled() && $ent->has_settings()) {
                $prfx = "ent({$ent->_code})_";
                foreach ($ent->settings() as $stg) {
                    $prfxname = $prfx . $stg->name;
                    if (isset($config->{$prfxname})) {
                        $datas[$prfxname] = $config->{$prfxname};
                    } else {
                        $datas[$prfxname] = $stg->default;
                    }
                }
            }
        }
        if (!empty($datas)) {
            $mform->set_data($datas);
        }
    }

    public static function save_formdata($config, $formdata) {
        foreach (self::$_entinsts as $ent) {
            if ($ent->is_enabled() && $ent->has_settings()) {
                $prfx = "ent({$ent->_code})_";
                foreach ($ent->settings() as $stg) {
                    $prfxname = $prfx . $stg->name;
                    if (isset($formdata->{$prfxname})) {
                        $new = $formdata->{$prfxname};
                        if (isset($config->{$prfxname})) {
                            $actual = $config->{$prfxname};
                        } else {
                            $actual = $stg->default;
                        }
                        if ($new != $actual) {
                            set_config($prfxname, $new, 'auth_entsync');
                        }
                    }
                }
            }
        }
    }

    public function get_settings() {
        if (isset($this->_settings)) {
            return $this->_settings;
        }
        if ($this->has_settings()) {
            $lst = $this->settings();
            $prfx = "ent({$this->_code})_";
            $stgs = array();
            foreach ($lst as $setting) {
                if ($stg = get_config('auth_entsync', $prfx . $setting->name)) {
                    $stgs[$setting->name] = $stg;
                } else {
                    $stgs[$setting->name] = $setting->default;
                }
            }
            $this->_settings = $stgs;
        } else {
            $this->_settings = array();
        }
        return $this->_settings;
    }

    /**
     * Retourne l'url à utiliser pour se connecter avec cas
     *
     * @return string
     */
    public abstract function get_connector_url();

    /**
     * Retourne la liste des profils
     *
     * @return string[]
     */
    public static function get_profile_list() {
        if (empty(self::$_profilelist)) {
            self::$_profilelist = [
                1 => 'Elèves',
                2 => 'Enseignants',
                3 => 'Personnels'
            ];
        }
        return self::$_profilelist;
    }

    /**
     * Retourne une instance de la classe qui permet de synchroniser
     * les utilisateurs du type de fichier donné de cet ent
     *
     * @param int $filetype le type de fichier
     * @return tool_entsync_sync_cas|tool_entsync_sync_manual
     */
    public function get_synchronizer($filetype) {
        require_once(__DIR__ . '/../lib/synchroniz.php');
        $syncclass = 'auth_entsync_sync_' . $this->get_mode();
        $synchroniser = new $syncclass();
        $synchroniser->entcode = $this->_code;
        $synchroniser->set_profilestosync($this->get_profilesintype($filetype));
        $synchroniser->set_profileswithcohorts($this->get_profileswithcohorts());
        return $synchroniser;
    }

    /**
     * Retourne le tableau des formats de fichiers acceptés
     *
     * @return array
     */
    public abstract function get_filetypes();

    /**
     * Indique si plusieurs fichiers de ce type peuvent être combinés
     *
     * @param int $filetype le type de fichier
     * @return bool
     */
    public function accept_multifile($filetype) {
        return false;
    }

    /**
     * Retourne un tableau des profils contenus dans le type de fichier
     *
     * @param int $filetype le type de fichier
     * @return array
     */
    public abstract function get_profilesintype($filetype);

    /**
     * Retourne une instance du parser adapté au format de fichier
     *
     * @param int $type
     * @return array
     */
    public abstract function get_fileparser($type);

    public abstract function get_mode();

    /**
     * Retourne un tableau des profils ayant des cohortes
     *
     * @return array
     */
    public abstract function get_profileswithcohorts();

    public function get_icon() {
        return new pix_icon('t/approve', $this->nomcourt);
    }

    public function include_filehelp() {
        $_helphtml = __DIR__ . "/help/{$this->_entclass}_filehelp.php";
        if (file_exists($_helphtml)) {
            include($_helphtml);
        } else {
            echo '<p>L\'aide n\'est pas encore disponible.</p>';
        }
    }

    public function include_help() {
        $_helphtml = __DIR__ . "/help/{$this->_entclass}_help.php";
        if (file_exists($_helphtml)) {
            include($_helphtml);
        } else {
            echo '<p>L\'aide n\'est pas encore disponible.</p>';
        }
    }

    /**
     * Définit si des paramètres sont nécessaires
     *
     * @return bool
     */
    public function has_settings() {
        return false;
    }
}

abstract class auth_entsync_entsso extends auth_entsync_ent_base {
    public function can_switch() {
        return false;
    }
}

abstract class auth_entsync_entcas extends auth_entsync_entsso {
    public function get_mode() {
        return 'cas';
    }

    public function get_connector_url() {
        global $CFG;
        return "{$CFG->wwwroot}/auth/entsync/connect.php?ent={$this->_entclass}";
    }

    public abstract function get_casparams();

    public function get_casconnector() {
        $con = new \auth_entsync\connectors\casconnect();
        $con->set_param($this->get_casparams());
        return $con;
    }
}

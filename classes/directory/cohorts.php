<?php
namespace auth_entsync\directory;
use \auth_entsync\conf;

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->dirroot.'/cohort/lib.php');


class cohorts {
    protected \moodle_database $DB;
    protected conf $conf;

    public function __construct($DB, conf $conf) {
        $this->DB = $DB;
        $this->conf = $conf;
    }

    /**
     * @param cohort_info[] $cis tableau indexé par idnumber
     * @return void
     */
    public function load_or_create($cis) {
        if (empty($cis)) return;
        $idns = [];
        foreach ($cis as $idn => $ci) {
            if ($ci->id === 0) {
                $idns[] = $idn;
            }
        }
        if (empty($idns)) return;
        list($sql, $params) = $this->DB->get_in_or_equal($idns, SQL_PARAMS_NAMED, 'idn');
        $sql =
'select
 c.id,
 c.idnumber
FROM {cohort} c
WHERE c.component = :pn
AND c.idnumber ' . $sql . ';';
        $params['pn'] = $this->conf->pn();
        $found = $this->DB->get_records_sql($sql, $params);
        foreach ($found as $f) {
            $cis[$f->idnumber]->set_id($f->id);
        }
        foreach ($cis as $idn => $ci) {
            if ($ci->id === 0) {
                $newcohort = new \stdClass();
                $newcohort->name = $ci->prefixed_name();
                $newcohort->idnumber = $idn;
                $newcohort->component = $this->conf->pn();
                $newcohort->contextid = \context_system::instance()->id;
                $ci->set_id(\cohort_add_cohort($newcohort));
            }
        }
    }

    /**
     * @return cohort_info[]
     */
    public function list() {
        $sql =
'SELECT
 c.*,
 substring(c.idnumber, 6, 2) as typ
FROM {cohort} c
WHERE c.component = :pn;';
        $list = $this->DB->get_records_sql($sql, ['pn' => $this->conf->pn()]);
        $ret = [];
        foreach($list as $c) $ret[] = cohort_info::from_record($c);
        return $ret;
    }


    /**
     * @param int       $userid
     * @return array $id => $idnumber
     */
    public function get_user_divs($userid) {
        $sql =
'WITH ch as (SELECT
 c.id,
 c.idnumber,
 substring(c.idnumber, 6, 2) as typ
FROM {cohort} c
JOIN {cohort_members} cm ON cm.cohortid = c.id
WHERE c.component = :pn AND cm.userid = :userid)
SELECT id, idnumber FROM ch WHERE typ = :typ;';
        return $this->DB->get_records_sql_menu($sql, ['pn' => $this->conf->pn(), 'userid' => $userid, 'typ' => cohort_info::C_TYPE_DIV]);
    }

    /**
     * @param int       $userid
     * @return array $id => $idnumber
     */
    public function get_user_divs_and_groups($userid) {
        $sql =
'WITH ch as (SELECT
 c.id,
 c.idnumber,
 substring(c.idnumber, 6, 2) as typ
FROM {cohort} c
JOIN {cohort_members} cm ON cm.cohortid = c.id
WHERE c.component = :pn AND cm.userid = :userid)
SELECT id, idnumber FROM ch WHERE typ IN (:typ1, :typ2);';
        return $this->DB->get_records_sql_menu($sql, ['pn' => $this->conf->pn(), 'userid' => $userid, 'typ1' => cohort_info::C_TYPE_DIV, 'typ2' => cohort_info::C_TYPE_GRP]);
    }

    /**
     * @param int       $userid
     */
    public function clear_user_div($userid) {
        foreach ($this->get_user_divs($userid) as $id => $idnumber) {
            \cohort_remove_member($id, $userid);
        }
    }

    /**
     * @param int       $userid
     */
    public function clear_user_div_and_groups($userid) {
        foreach ($this->get_user_divs_and_groups($userid) as $id => $idnumber) {
            \cohort_remove_member($id, $userid);
        }
    }

    /**
     * @param int       $userid
     * @param string    $div
     */
    public function set_user_div($userid, $div) {
        if (empty($div)) {
            $this->clear_user_div($userid);
        } else {
            $wanted = cohort_info::from_name_and_type($div, cohort_info::C_TYPE_DIV);
            $already = false;
            foreach ($this->get_user_divs($userid) as $id => $idnumber) {
                if ($idnumber === $wanted->idnumber) {
                    $already = true;
                } else {
                    \cohort_remove_member($id, $userid);
                }
            }
            if (!$already) {
                $this->load_or_create([$wanted->idnumber => $wanted]);
                \cohort_add_member($wanted->id, $userid);
            }
        }
    }

    /**
     * @param int       $userid
     * @param string    $div
     * @param string[]  $groups
     */
    public function set_user_div_and_groups($userid, $div, $groups) {
        $wanted = [];
        if (!empty($div)) {
            $ci = cohort_info::from_name_and_type($div, cohort_info::C_TYPE_DIV);
            $wanted[$ci->idnumber] = $ci;
        }
        foreach ($groups as $g) {
            if (!empty($g)) {
                $ci = cohort_info::from_name_and_type($g, cohort_info::C_TYPE_GRP);
                $wanted[$ci->idnumber] = $ci;
            }
        }
        $already = [];
        foreach ($this->get_user_divs_and_groups($userid) as $id => $idnumber) {
            if (\array_key_exists($idnumber, $wanted)) {
                $already[$idnumber] = true;
            } else {
                \cohort_remove_member($id, $userid);
            }
        }
        $missing = [];
        foreach ($wanted as $idnumber => $ci) {
            if (! \array_key_exists($idnumber, $already)) {
                $missing[$idnumber] = $ci;
            }
        }
        $this->load_or_create($missing);
        foreach ($missing as $idnumber => $ci) {
            \cohort_add_member($ci->id, $userid);
        }
    }
}

class cohort_info {
    const C_TYPE_DIV = 'cl';
    const C_TYPE_GRP = 'gr';
    const PRFX_DIV = '[DIV] ';
    const PRFX_GRP = '[GR] ';
    protected static \Transliterator $cohort_translit;
    protected static $cache = [];

    public int $id;
    public string $type;
    public string $name;
    public string $idnumber;
    public \stdClass $rec;
    public static function idnumber($name, $type) {
        $name = self::simplify_cohort($name);
        return 'auto_' . $type . '_' . $name;
    }

     /**
     * Simplifie les noms de cohorte
     * met en majuscule, enlève les lettres accentuées
     *
     * @param string $str Le nom à simplifier
     * @return string nom simplifié
     */
    public static function simplify_cohort($str) {
        if (!isset(self::$cohort_translit)) {
            self::$cohort_translit = \Transliterator::createFromRules(
                    "::Latin-ASCII; ::upper ;");
        }
        return \trim(self::$cohort_translit->transliterate($str), '-');
    }

    /**
     * @param string $name
     * @param string $type
     * @return cohort_info
     */
    public static function from_name_and_type($name, $type) {
        $idnumber = self::idnumber($name, $type);
        if (\array_key_exists($idnumber, self::$cache)) return self::$cache[$idnumber];
        $c = new cohort_info();
        $c->id = 0;
        $c->name = $name;
        $c->idnumber = $idnumber;
        $c->type = $type;
        return $c;
    }

    /**
     * @param \stdClass $rec
     * @return cohort_info
     */
    public static function from_record($rec) {
        if (\array_key_exists($rec->idnumber, self::$cache)) return self::$cache[$rec->idnumber];
        $c = new cohort_info();
        $c->id = $rec->id;
        $c->name = $rec->name;
        $c->idnumber = $rec->idnumber;
        $c->type = $rec->typ;
        unset($rec->typ);
        $c->rec = $rec;
        self::$cache[$rec->idnumber] = $c;
        return $c;
    }

    public function prefixed_name() {
        if ($this->type === self::C_TYPE_DIV) {
            return self::PRFX_DIV . $this->name;
        } else if ($this->type === self::C_TYPE_GRP) {
            return self::PRFX_GRP . $this->name;
        } else {
            return $this->name;
        }
    }

    public function set_id($id) {
        $this->id = $id;
        self::$cache[$this->idnumber] = $this;
    }

    public function is_div_or_group() {
        return $this->type === self::C_TYPE_DIV || $this->type === self::C_TYPE_GRP;
    }

    public function delete() {
        cohort_delete_cohort($this->rec);
    }
}
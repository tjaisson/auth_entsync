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

class auth_entsync_stringhelper {

    private static $lettres = 'abcdefghijkmnpqrstuvwxyz';
    
    public static function rnd_string() {
        return self::$lettres[rand(0, 23)]
        . rand(1, 9)
        . self::$lettres[rand(0, 23)]
        . rand(1, 9) . rand(0, 9);
    }
    
    private static $name_translit;
    private static $cohort_translit;
    
    /**
     * Simplifie les noms et prénoms
     * met en minuscule, enlève les lettres accentuées remplace les espaces par des "-"
     *
     * @param string $str Le nom à simplifier
     * @return string
     */
    public static function simplify_name($str) {
    	if(!isset(self::$name_translit)) {
    		self::$name_translit = Transliterator::createFromRules(
    				"::Latin-ASCII; [^[:L:] [:Separator:] [- _]] >; ::Lower ; [^[:L:]]+ > '-';");
    	}
    	return trim(self::$name_translit->transliterate($str), '-');
    }

    /**
     * Simplifie les noms de cohorte
     * met en majuscule, enlève les lettres accentuées
     *
     * @param string $str Le nom à simplifier
     * @return string
     */
    public static function simplify_cohort($str) {
    	if(!isset(self::$cohort_translit)) {
    		self::$cohort_translit = Transliterator::createFromRules(
    				"::Latin-ASCII; ::upper ;");
    	}
    	return trim(self::$cohort_translit->transliterate($str), '-');
    }
}

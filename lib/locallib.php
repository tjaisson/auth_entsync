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
    public static function rnd_string($length = 5) {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    private static $translit;
    
    public static function simplify_name($str) {
        if(!isset(self::$translit)) {
            self::$translit = Transliterator::createFromRules(
                "::Latin-ASCII; [^[:L:] [:Separator:] [- _]] >; ::Lower ; [^[:L:]]+ > '-';");
        }
        return trim(self::$translit->transliterate($str), '-');
    }
}

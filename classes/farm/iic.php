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
 *
 * IIC stands for Inter Instance Communication.
 *
 * Secure communication between instances.
 * Based on rotating shared keys stored in a shared directory.
 *
 * File pattern : [type]-[uid]-[expiration]
 * Type : T|K|UT|UK|~
 *    (U)T : (unique) token
 *    (U)K : (unique) key
 *    ~ : transcient state before rename
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace auth_entsync\farm;
defined('MOODLE_INTERNAL') || die;
/**
 * Class to handle secure communication between instances (IIC).
 *
 * @package    auth_entsync
 * @copyright  2020 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iic {
    const TTL = 60;
    const SAFE_TTL = 5;
    const UIDLEN = 10;
    const TOKENLEN = 15;
    const METHOD = 'aes128';
    protected static $_sharedir;
    protected static $_keys;
    protected static $_tokens;
    protected static $_shared_keys;
    protected static $_shared_tokens;
    protected static function sharedir() {
        if (!isset(self::$_sharedir)) {
            $sharedir = \get_config('auth_entsync', 'sharedir');
            if (! $sharedir) \debugging('auth_entsync : sharedir not configured');
            self::$_sharedir = "{$sharedir}/iic";
        }
        return self::$_sharedir;
    }
    protected static function keys() {
        if (!isset(self::$_keys)) self::readDir();
        return self::$_keys;
    }
    protected static function tokens() {
        if (!isset(self::$_tokens)) self::readDir();
        return self::$_tokens;
    }
    protected static function shared_keys() {
        if (!isset(self::$_shared_keys)) self::readDir();
        return self::$_shared_keys;
    }
    protected static function shared_tokens() {
        if (!isset(self::$_shared_tokens)) self::readDir();
        return self::$_shared_tokens;
    }
    protected static function readDir() {
        self::$_keys = [];
        self::$_tokens = [];
        self::$_shared_keys = [];
        self::$_shared_tokens = [];
        if ($dirPath = self::sharedir()) {
            $time = \time();
            $safeTime = $time - self::SAFE_TTL;
            $dir = \dir($dirPath);
            while (false !== ($item = $dir->read())) {
                $parts = \explode('-', $item, 3);
                if (\count($parts) === 3) {
                    list($type, $uid, $expir) = $parts;
                    $expir = (int)$expir;
                    if ($expir < $safeTime) {
                        \unlink("{$dirPath}/{$item}");
                    } else {
                        if (($type !== '~') && ($expir > $time)) {
                            $val = \file_get_contents("{$dirPath}/{$item}");
                            $k = [
                                'fileName' => $item,
                                'expir' => $expir,
                                'val' => $val,
                                'unique' => true,
                                'uid' => $uid,
                            ];
                            switch ($type) {
                                case 'K':
                                    $k['unique'] = false;
                                    self::$_shared_keys[] = $k;
                                case 'UK':
                                    self::$_keys[$uid] = $k;
                                    break;
                                case 'T':
                                    $k['unique'] = false;
                                    self::$_shared_tokens[] = $k;
                                case 'UT':
                                    self::$_tokens[$uid] = $k;
                                    break;
                            }
                        }
                    }
                }
            }
            $dir->close();
        }
    }
    public static function validateToken($uid, $tk) {
        $tokens = self::tokens();
        if (!($k = @$tokens[$uid])) return false;
        if (!($k['val'] === $tk)) return false;
        if (($k['unique']) && ($dirPath = self::sharedir())) {
            \unlink("{$dirPath}/{$k['fileName']}");
        }
        return true;
    }
    public static function createToken($ttl = self::TTL, $unique = false) {
        if ($unique) return self::newToken($ttl, true);
        $fk = false;
        $minExpir = \time() + $ttl;
        $maxExpir = $minExpir + $ttl;
        foreach (self::shared_tokens() as $k) {
            $expir = $k['expir'];
            if (($expir >= $minExpir) && ($expir <= $maxExpir)){
                $fk = $k;
                break;
            }
        }
        if ($fk) return $fk;
        $fk = self::newToken($ttl, false);
        return ['uid' => $fk['uid'], 'tk' => $fk['val']];
    }
    protected static function newToken($ttl, $unique) {
        $k = [
            'type' => $unique ? 'UT' : 'T',
            'val' => \random_string(self::TOKENLEN),
        ];
        return self::saveKey($k, $ttl);
    }
    public static function open($uid, $s) {
        $keys = self::keys();
        if (false === ($k = @$keys[$uid])) return false;
        if ((false === ($ivb = $k['iv'])) || (false === ($kb = $k['k']))) {
            list($ivb, $kb) = \explode(',', $k['val']);
            $k['iv'] = $ivb = \base64_decode($ivb);
            $k['k'] = $kb = \base64_decode($kb);
        }
        $s = self::base64_url_decode($s);
        $s = \openssl_decrypt($s, self::METHOD, $kb, \OPENSSL_RAW_DATA, $ivb);
        if ((false !== $s) && ($k['unique']) && (false !== ($dirPath = self::sharedir()))) {
            \unlink("{$dirPath}/{$k['fileName']}");
        }
        return $s;
    }
    public static function seal($s, $ttl = self::TTL, $unique = false) {
        $k = self::createKey($ttl, $unique);
        if ((false === ($ivb = $k['iv'])) || (false === ($kb = $k['k']))) {
            list($ivb, $kb) = \explode(',', $k['val']);
            $k['iv'] = $ivb = \base64_decode($ivb);
            $k['k'] = $kb = \base64_decode($kb);
        }
        $s = self::base64_url_encode($s);
        $s = \openssl_encrypt($s, self::METHOD, $kb, \OPENSSL_RAW_DATA, $ivb);
        return ['uid' => $k['uid'], 's' => $s];
    }
    protected static function createKey($ttl = self::TTL, $unique = false) {
        if ($unique) return self::newKey($ttl, true);
        $fk = false;
        $minExpir = \time() + $ttl;
        $maxExpir = $minExpir + $ttl;
        foreach (self::shared_keys() as $k) {
            $expir = $k['expir'];
            if (($expir >= $minExpir) && ($expir <= $maxExpir)){
                $fk = $k;
                break;
            }
        }
        if ($fk) return $fk;
        return self::newKey($ttl, false);
    }
    protected static function newKey($ttl, $unique) {
        $k = ['type' => $unique ? 'UK' : 'K'];
        $ivSize = \openssl_cipher_iv_length(self::METHOD);
        $ivb = \openssl_random_pseudo_bytes($ivSize);
        $kb = \openssl_random_pseudo_bytes($ivSize);
        $k['iv'] = $ivb;
        $k['k'] = $kb;
        $k['val'] = \base64_encode($ivb) . ',' . \base64_encode($kb);
        return self::saveKey($k, $ttl);
    }
    protected static function saveKey($k, $ttl) {
        $k['expir'] = $expir = \time() + (2 * $ttl);
        $k['uid'] = $uid = \random_string(self::UIDLEN);
        $val = $k['val'];
        $type = $k['type'];
        $filename = "-{$uid}-{$expir}";
        $sharedir = self::sharedir();
        $tempfp = "{$sharedir}/~{$filename}";
        \file_put_contents($tempfp, $val);
        \rename($tempfp, "{$sharedir}/{$type}{$filename}");
        return $k;
    }
    public static function base64_url_encode($input) {
        return strtr(base64_encode($input), '+/=', '._-');
    }
    
    public static function base64_url_decode($input) {
        return base64_decode(strtr($input, '._-', '+/='));
    }
    
}

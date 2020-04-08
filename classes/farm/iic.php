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
 * File pattern : [T|K]-[R|U]-[uid]-[expiration]-[scope]
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
abstract class iic {
    public $uid;
    protected $val;
    protected $fileName;
    protected $expir;
    protected $unique;
    protected $scope;
    

    const TTL = 60;
    const SAFE_TTL = 5;
    const UIDLEN = 10;
    const TOKENLEN = 15;
    const METHOD = 'aes128';
    const TYPE = null;
    protected static $_sharedir;
    protected static $dirRed = false;
    protected static $_keys = [];
    protected static $_tokens = [];
    protected static $_shared_keys = [];
    protected static $_shared_tokens = [];
    protected static function checkConfig($throw = false) {
        if (!isset(self::$_sharedir)) {
            $sharedir = \get_config('auth_entsync', 'sharedir');
            if (! $sharedir) {
                if ($throw) throw new \moodle_exception('sharedir not configured', 'auth_entsync');
                self::$_sharedir = false;
                return false;
            }
            self::$_sharedir = "{$sharedir}/iic";
        }
        return true;
    }
    protected static function ensureDirRed() {
        if (self::$dirRed) return;
        if (!self::checkConfig()) return;
        $time = \time();
        $safeTime = $time - self::SAFE_TTL;
        $path = self::$_sharedir;
        $dir = \dir($path);
        while (false !== ($item = $dir->read())) {
            $parts = \explode('-', $item, 5);
            if (\count($parts) >= 4) {
                if (\count($parts) === 4) $parts[4] = '';
                $parts[3] = (int)$parts[3];
                if ($parts[3] < $safeTime) {
                    \unlink("{$path}/{$item}");
                } else if ($parts[3] > $time) {
                    self::addItem($item, $parts);
                }
            }
        }
        $dir->close();
        self::$dirRed = true;
    }
    public static function addItem($item, $parts) {
        $unique = $parts[1];
        if ($unique === 'U') {
            $unique = true;
        } else if ($unique === 'R') {
            $unique = false;
        } else {
            return false;
        }
        $uid = $parts[2];
        $k = [
            'fileName' => $item,
            'unique' => $unique,
            'uid' => $uid,
            'expir' => $parts[3],
            'scope' => $parts[4],
        ];
        $type = $parts[0];
        if ($type === token::TYPE) {
            $k = new token($k);
            self::$_tokens[$uid] = $k;
            if (!$unique) self::$_shared_tokens[] = $k;
        } else if ($type === crkey::TYPE) {
            $k = new crkey($k);
            self::$_keys[$uid] = $k;
            if (!$unique) self::$_shared_keys[] = $k;
        } else {
            return false;
        }
        return $k;
    }
    public function __construct($params) {
        foreach ($params as $k => $v) {
            $this->{$k} = $v;
        }
        if (!isset($this->scope)) $this->scope = '';
    }
    protected function ensureVal() {
        if (!isset($this->val)) {
            if (empty($this->fileName))
                throw new \moodle_exception('key filename unknown', 'auth_entsync');
            self::checkConfig(true);
            $dirPath = self::$_sharedir;
            $this->val = \file_get_contents("{$dirPath}/{$this->fileName}");
        }
        if (empty($this->val))
            throw new \moodle_exception('key file not found', 'auth_entsync');
    }
    protected function removeFile() {
            if (empty($this->fileName))
                throw new \moodle_exception('key filename unknown', 'auth_entsync');
            self::checkConfig(true);
            $dirPath = self::$_sharedir;
            \unlink("{$dirPath}/{$this->fileName}");
    }
    protected function saveToFile() {
        if(empty($this->val)) return false;
        if(isset($this->uid)) return false;
        self::checkConfig(true);
        $dirPath = self::$_sharedir;
        $this->uid = \random_string(self::UIDLEN);
        $type = static::TYPE;
        $unique = $this->unique ? 'U' : 'R';
        $filename = "-{$unique}-{$this->uid}-{$this->expir}";
        if (!empty($this->scope)) $filename = "{$filename}-{$this->scope}";
        $this->fileName = $type . $filename;
        $tempfp = "{$dirPath}/~{$filename}";
        if(false === \file_put_contents($tempfp, $this->val)) return false;
        return \rename($tempfp, "{$dirPath}/{$this->fileName}");
    }
    public static function validateToken($uidtk, $scope = '') {
        $uid = \substr($uidtk, 0, self::UIDLEN);
        $tk = \substr($uidtk, self::UIDLEN);
        self::ensureDirRed();
        if (!($k = @self::$_tokens[$uid])) return false;
        return $k->validate($tk, $scope);
    }
    protected static function findExisting($set, $ttl, $scope) {
        $minExpir = \time() + $ttl;
        $maxExpir = $minExpir + $ttl;
        foreach ($set as $k) {
            if (($k->expir >= $minExpir) && ($k->expir <= $maxExpir) && ($k->scope === $scope)) 
                return  $k;
        }
        return false;
    }
    public static function getToken($ttl = null, $unique = null, $scope = '') {
        if (null === $ttl) $ttl = self::TTL;
        if (null === $unique) $unique = false;
        if ($unique) {
            $k = false;
        } else {
            self::ensureDirRed();
            $k = self::findExisting(self::$_shared_tokens, $ttl, $scope);
        }
        if (!$k) $k = token::newToken($ttl, $unique, $scope);
        return $k->getUidtk();
    }
    public static function getCrkey($ttl = null, $unique = null, $scope = '') {
        if (null === $ttl) $ttl = self::TTL;
        if (null === $unique) $unique = false;
        if ($unique) {
            $k = false;
        } else {
            self::ensureDirRed();
            $k = self::findExisting(self::$_shared_keys, $ttl, $scope);
        }
        if (!$k) $k = crkey::newCrkey($ttl, $unique, $scope);
        return $k;
    }
    public static function open($uids, $scope = '') {
        $uid = \substr($uids, 0, self::UIDLEN);
        $s = \substr($uids, self::UIDLEN);
        self::ensureDirRed();
        if (!($k = @self::$_keys[$uid])) return false;
        return $k->doOpen($s, $scope);
    }
    protected static $_ivLength;
    protected  static function ivLength() {
        if(!isset(self::$_ivLength))
            self::$_ivLength = \openssl_cipher_iv_length(self::METHOD);
        if (self::$_ivLength < 5)
            throw new \moodle_exception('cryptographic function missing', 'auth_entsync');
        return self::$_ivLength;
    }
    public static function base64_url_encode($input) {
        return strtr(base64_encode($input), '+/=', '._-');
    }
    
    public static function base64_url_decode($input) {
        return base64_decode(strtr($input, '._-', '+/='));
    }
    
}
class token extends iic {
    const TYPE = 'T';
    public static function newToken($ttl, $unique, $scope) {
        $k = [
            'unique' => $unique,
            'val' => \random_string(self::TOKENLEN),
            'scope' => $scope,
            'expir' => \time() + 2 * $ttl,
        ];
        $k = new token($k);
        if (!$k->saveToFile()) return false;
        if (!$unique) {
            self::$_shared_tokens[] = $k;
        }
        self::$_tokens[$k->uid] = $k;
        return $k;
    }
    public function validate($tk, $scope = '') {
        if ($scope !== $this->scope) return false;
        $this->ensureVal();
        if ($tk === $this->val) {
            if ($this->unique) $this->removeFile();
            return true;
        }
        return false;
    }
    public function getUidtk () {
        return $this->uid . $this->val;
    }
}
class crkey extends iic {
    const TYPE = 'K';
    protected $kb;
    protected function ensurekeys () {
        if (empty($this->kb)) {
            $this->ensureVal();
            $this->kb = \base64_decode($this->val);
        }
        $len = self::ivLength();
        if (\strlen($this->kb) !== $len)
            throw new \moodle_exception('key file corrupted', 'auth_entsync');
    }
    public static function newCrkey($ttl, $unique, $scope) {
        $ivSize = self::ivLength();
        $kb = \openssl_random_pseudo_bytes($ivSize);
        $k = [
            'unique' => $unique,
            'val' => \base64_encode($kb),
            'scope' => $scope,
            'expir' => \time() + 2 * $ttl,
            'kb' => $kb,
        ];
        $k = new crkey($k);
        if (!$k->saveToFile()) return false;
        if (!$unique) {
            self::$_shared_keys[] = $k;
        }
        self::$_keys[$k->uid] = $k;
        return $k;
    }
    public function doOpen($s, $scope = '') {
        if ($scope !== $this->scope) return false;
        $this->ensurekeys();
        $parts = \explode('~', $s);
        if (\count($parts) !==2) return false; 
        $ivb = self::base64_url_decode($parts[0]);
        $s = self::base64_url_decode($parts[1]);
        $s = \openssl_decrypt($s, self::METHOD, $this->kb, \OPENSSL_RAW_DATA, $ivb);
        if ((false !== $s) && ($this->unique))
            $this->removeFile();
        return $s;
    }
    public function seal($s) {
        $this->ensurekeys();
        $ivSize = self::ivLength();
        $ivb = \openssl_random_pseudo_bytes($ivSize);
        $s = \openssl_encrypt($s, self::METHOD, $this->kb, \OPENSSL_RAW_DATA, $ivb);
        return $this->uid . self::base64_url_encode($ivb) . '~' . self::base64_url_encode($s);
    }
}

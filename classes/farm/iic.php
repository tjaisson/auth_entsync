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
 * File pattern : [T|K]-[uid]-[expiration]
 * T : Tocken unique
 * K : Key reusable
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
            $parts = \explode('-', $item, 3);
            if (\count($parts) === 3) {
                $parts[2] = (int)$parts[2];
                if ($parts[2] < $safeTime) {
                    \unlink("{$path}/{$item}");
                } else if ($parts[2] > $time) {
                    self::addItem($item, $parts);
                }
            }
        }
        $dir->close();
        self::$dirRed = true;
    }
    public static function addItem($item, $parts) {
        $uid = $parts[1];
        $k = [
            'fileName' => $item,
            'uid' => $uid,
            'expir' => $parts[2],
        ];
        $type = $parts[0];
        if ($type === token::TYPE) {
            $k = new token($k);
            self::$_tokens[$uid] = $k;
        } else if ($type === crkey::TYPE) {
            $k = new crkey($k);
            self::$_keys[$uid] = $k;
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
        $filename = "-{$this->uid}-{$this->expir}";
        $this->fileName = $type . $filename;
        $tempfp = "{$dirPath}/~{$filename}";
        if(false === \file_put_contents($tempfp, $this->val)) return false;
        return \rename($tempfp, "{$dirPath}/{$this->fileName}");
    }
    public static function validateToken($uidtk, $scope = '*') {
        $uid = \substr($uidtk, 0, self::UIDLEN);
        $tk = \substr($uidtk, self::UIDLEN);
        self::ensureDirRed();
        if (!($k = @self::$_tokens[$uid])) return false;
        return $k->validate($tk, $scope);
    }
    protected static function findExistingKey($ttl) {
        $minExpir = \time() + $ttl;
        $maxExpir = $minExpir + $ttl;
        foreach (self::$_keys as $k) {
            if (($k->expir >= $minExpir) && ($k->expir <= $maxExpir)) 
                return  $k;
        }
        return false;
    }
    public static function createToken($scope = null, $data = null, $ttl = self::TTL) {
        if (null === $scope) $scope = '*';
        if (null === $data) $data = 'OK';
        $k = token::newToken($scope, $data, $ttl);
        return $k->getUidtk();
    }
    public static function getCrkey($ttl = self::TTL) {
        if (null === $ttl) $ttl = self::TTL;
        self::ensureDirRed();
        $k = self::findExistingKey($ttl);
        if (!$k) $k = crkey::newCrkey($ttl);
        return $k;
    }
    public static function open($uids, $scope = '*') {
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
        return \rtrim(\strtr(\base64_encode($input), '+/', '-_'), '=');
    }
    public static function base64_url_decode($input) {
        $input = \strtr($input, '-_', '+/');
        $pad = strlen($input) % 4;
        if ($pad === 2) $input .= '==';
        else if ($pad === 3)  $input .= '=';
        else if ($pad === 1)  $input .= '==='; // Should not happen.
        return \base64_decode($input);
    }
}
class token extends iic {
    const TYPE = 'T';
    protected $tk;
    protected $data;
    protected $scope;
    protected function ensureTk () {
        if (empty($this->tk)) {
            $this->ensureVal();
            $parts = \explode(',', $this->val, 3);
            if (3 !== \count($parts))
                throw new \moodle_exception('token file corrupted', 'auth_entsync');
            $this->tk = $parts[0];
            if (self::TOKENLEN !== strlen($this->tk))
                throw new \moodle_exception('token file corrupted', 'auth_entsync');
            if (empty($parts[1])) $this->data = 'OK';
            else $this->data = \base64_decode($parts[1]);
            if (empty($parts[2])) $this->scope = '*';
            else $this->scope = \base64_decode($parts[2]);
        }
    }
    public static function newToken($scope, $data, $ttl) {
        $expir = \time() + $ttl;
        $val = $tk = \random_string(self::TOKENLEN) . ',';
        if ('OK' !== $data) $val += \base64_encode($data);
        $val += ',';
        if ($scope !== '*') $val += \base64_encode($scope);
        $k = [
            'tk' => $tk,
            'val' => $val,
            'data' => $data,
            'scope' => $scope,
            'expir' => $expir,
        ];
        $k = new token($k);
        if (!$k->saveToFile()) return false;
        self::$_tokens[$k->uid] = $k;
        return $k;
    }
    public function validate($tk, $scope) {
        $this->ensureData();
        if (('*' !== $this->scope) && ($this->scope !== $scope)) return false;
        if ($tk !== $this->tk) return false;
        $this->removeFile();
        return $this->data;
    }
    public function getUidtk () {
        return $this->uid . $this->tk;
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
    public static function newCrkey($ttl) {
        $ivSize = self::ivLength();
        $kb = \openssl_random_pseudo_bytes($ivSize);
        $expir = \time() + 2 * $ttl;
        $k = [
            'val' => \base64_encode($kb),
            'expir' => $expir,
            'kb' => $kb,
        ];
        $k = new crkey($k);
        if (!$k->saveToFile()) return false;
        self::$_keys[$k->uid] = $k;
        return $k;
    }
    public function doOpen($s, $scope) {
        $this->ensurekeys();
        $parts = \explode('.', $s);
        $nb = \count($parts);
        if ($nb > 2) return false;
        if ($nb === 2) $scopelen = (int)$parts[1];
        else $scopelen = 0;
        $s = $parts[0];
        if (empty($s)) return false;
        $s = self::base64_url_decode($s);
        $ivSize = self::ivLength();
        $ivb = \substr($s, 0, $ivSize);
        $s = \substr($s, $ivSize);
        $s = \openssl_decrypt($s, self::METHOD, $this->kb, \OPENSSL_RAW_DATA, $ivb);
        if (false === $s) return false;
        if ($scopelen > 0) {
            $nb = \strlen($s) - $scopelen;
            if (\substr($s, $nb) !== $scope) return false;
            $s = \substr($s, 0, $nb);
        }
        return $s;
    }
    public function seal($s, $scope = '*') {
        $this->ensurekeys();
        $ivSize = self::ivLength();
        $ivb = \openssl_random_pseudo_bytes($ivSize);
        if ('*' === $scope) {
            $scopelen = 0;
        } else {
            $scopelen = \strlen($scope);
            $s .= $scope;
        }
        $s = $ivb . \openssl_encrypt($s, self::METHOD, $this->kb, \OPENSSL_RAW_DATA, $ivb);
        $s = $this->uid . self::base64_url_encode($s);
        if ($scopelen > 0) {
            $s .= ".{$scopelen}";
        }
        return $s;
    }
}

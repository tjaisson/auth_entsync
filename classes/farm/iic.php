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
class iic {
    const SAFE_TTL = 5;
    const UIDLEN = 10;
    const TYPE = null;
    const OK = 'OK';
    const NOSCOPE = '*';
    protected $conf;
    protected $dirRed = false;
    protected $lists;
    public function sharedir($throw = false) {
        $sharedir = $this->conf->sharedir();
        if (! $sharedir) {
            if ($throw) throw new \moodle_exception('sharedir not configured', 'auth_entsync');
            return false;
        }
        return "{$sharedir}/iic";
    }
    protected function ensureDirRed() {
        if ($this->dirRed) return;
        if (false === ($path = $this->sharedir())) return;
        $time = \time();
        $safeTime = $time - self::SAFE_TTL;
        $dir = \dir($path);
        while (false !== ($item = $dir->read())) {
            $parts = \explode('-', $item, 3);
            if (\count($parts) === 3) {
                $parts[2] = (int)$parts[2];
                if ($parts[2] < $safeTime) {
                    \unlink("{$path}/{$item}");
                } else if ($parts[2] > $time) {
                    $this->addItem($item, $parts);
                }
            }
        }
        $dir->close();
        $this->dirRed = true;
    }
    public function addItem($item, $parts) {
        $uid = $parts[1];
        $k = [
            'fileName' => $item,
            'uid' => $uid,
            'expir' => $parts[2],
        ];
        $type = $parts[0];
        if ($type === token::TYPE) $k = new token($k);
        else if ($type === crkey::TYPE) $k = new crkey($k);
        else return false;
        $this->list[$type][$k->uid] = $k;
        return $k;
    }
    public function doAdd($k) {
        $this->lists[$k::TYPE][$k->uid] = $k;
        return $k;
    }
    public function __construct($conf) {
        $this->conf = $conf;
        $this->lists = [
            token::TYPE => [],
            crkey::TYPE => [],
        ];
    }
    public function createToken($scope = null, $data = null, $ttl = null) {
        if (null === $scope) $scope = self::NOSCOPE;
        if (null === $data) $data = self::OK;
        $k = token::newToken($scope, $data, $ttl);
        $this->saveToFile($k);
        $this->doAdd($k);
        return $k->getUidtk();
    }
    public function getCrkey($ttl = null) {
        self::ensureDirRed();
        $k = $this->findExistingKey($ttl);
        if (!$k) {
            $k = crkey::newCrkey($ttl);
            $this->saveToFile($k);
            $this->doAdd($k);
        }
        return $k;
    }
    protected function findExistingKey($ttl) {
        if (null === $ttl) $ttl = crkey::TTL;
        $minExpir = \time() + $ttl;
        $maxExpir = $minExpir + $ttl;
        foreach ($this->lists[crkey::TYPE] as $k) {
            if (($k->expir >= $minExpir) && ($k->expir <= $maxExpir)) {
                $this->ensureVal($k);
                return $k;
            }
        }
        return false;
    }
    public function open($uids, $scope = self::NOSCOPE) {
        $type = \substr($uids, 0, 1);
        $this->ensureDirRed();
        if (!($list = @$this->lists[$type])) return false;
        $uid = \substr($uids, 1, self::UIDLEN);
        $s = \substr($uids, self::UIDLEN + 1);
        if (!($k = @$list[$uid])) return false;
        $this->ensureVal($k);
        $s = $k->open($s, $scope);
        if ((null !== $s) && ($k::UNIQUE)) {
            $this->removeFile($k);
        }
        return $s;
    }
    protected function ensureVal($k) {
        if (!isset($k->val)) {
            if (empty($k->fileName))
                throw new \moodle_exception('key filename unknown', 'auth_entsync');
            $dirPath = $this->sharedir(true);
            $k->val = \file_get_contents("{$dirPath}/{$k->fileName}");
        }
        if (empty($k->val))
            throw new \moodle_exception('key file not found', 'auth_entsync');
    }
    protected function removeFile($k) {
        if (empty($k->fileName))
            throw new \moodle_exception('key filename unknown', 'auth_entsync');
        $dirPath = $this->sharedir(true);
        \unlink("{$dirPath}/{$k->fileName}");
    }
    protected function saveToFile($k) {
        if(empty($k->val)) return false;
        if(isset($k->uid)) return false;
        $dirPath = $this->sharedir(true);
        $k->uid = \random_string(self::UIDLEN);
        $type = $k::TYPE;
        $filename = "-{$k->uid}-{$k->expir}";
        $k->fileName = $type . $filename;
        $tempfp = "{$dirPath}/~{$filename}";
        if(false === \file_put_contents($tempfp, $k->val)) return false;
        return \rename($tempfp, "{$dirPath}/{$k->fileName}");
    }
}

abstract class iicitem {
    public $uid;
    public $val;
    public $fileName;
    public $expir;

    const UNIQUE = false;
    const TYPE = null;
    public function __construct($params) {
        foreach ($params as $k => $v) {
            $this->{$k} = $v;
        }
        if (!isset($this->scope)) $this->scope = '';
    }
    public abstract function open($tk, $scope);
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


class token extends iicitem {
    const UNIQUE = true;
    const TTL = 10;
    const TYPE = 'T';
    const TOKENLEN = 15;
    protected $tk;
    protected $data;
    protected $scope;
    protected function ensureTk () {
        if (empty($this->tk)) {
            $parts = \explode(',', $this->val, 3);
            if (3 !== \count($parts))
                throw new \moodle_exception('token file corrupted', 'auth_entsync');
            $this->tk = $parts[0];
            if (self::TOKENLEN !== strlen($this->tk))
                throw new \moodle_exception('token file corrupted', 'auth_entsync');
            if (empty($parts[1])) $this->data = iic::OK;
            else $this->data = \base64_decode($parts[1]);
            if (empty($parts[2])) $this->scope = iic::NOSCOPE;
            else $this->scope = \base64_decode($parts[2]);
        }
    }
    public static function newToken($scope, $data, $ttl) {
        if (null === $ttl) $ttl = self::TTL;
        $expir = \time() + $ttl;
        $val = $tk = \random_string(self::TOKENLEN);
        $val .= ',';
        if (iic::OK !== $data) $val .= \base64_encode($data);
        $val .= ',';
        if (iic::NOSCOPE !== $scope) $val .= \base64_encode($scope);
        $k = [
            'tk' => $tk,
            'val' => $val,
            'data' => $data,
            'scope' => $scope,
            'expir' => $expir,
        ];
        $k = new token($k);
        return $k;
    }
    public function open($tk, $scope) {
        $this->ensureTk();
        if ((iic::NOSCOPE !== $this->scope) && ($this->scope !== $scope)) return false;
        if ($tk !== $this->tk) return false;
        return $this->data;
    }
    public function getUidtk () {
        return self::TYPE . $this->uid . $this->tk;
    }
}
class crkey extends iicitem {
    const TTL = 180;
    const TYPE = 'K';
    const METHOD = 'aes128';
    protected $kb;
    protected static $_ivLength;
    protected  static function ivLength() {
        if(!isset(self::$_ivLength))
            self::$_ivLength = \openssl_cipher_iv_length(self::METHOD);
            if (self::$_ivLength < 5)
                throw new \moodle_exception('cryptographic function missing', 'auth_entsync');
                return self::$_ivLength;
    }
    protected function ensurekeys () {
        if (empty($this->kb)) {
            $this->kb = \base64_decode($this->val);
        }
        $len = self::ivLength();
        if (\strlen($this->kb) !== $len)
            throw new \moodle_exception('key file corrupted', 'auth_entsync');
    }
    public static function newCrkey($ttl) {
        if (null === $ttl) $ttl = self::TTL;
        $ivSize = self::ivLength();
        $kb = \openssl_random_pseudo_bytes($ivSize);
        $expir = \time() + 2 * $ttl;
        $k = [
            'val' => \base64_encode($kb),
            'expir' => $expir,
            'kb' => $kb,
        ];
        $k = new crkey($k);
        return $k;
    }
    public function open($s, $scope) {
        $this->ensurekeys();
        if (empty($s)) return false;
        $s = self::base64_url_decode($s);
        $ivSize = self::ivLength();
        $ivb = \substr($s, 0, $ivSize);
        $s = \substr($s, $ivSize);
        $s = \openssl_decrypt($s, self::METHOD, $this->kb, \OPENSSL_RAW_DATA, $ivb);
        if (false === $s) return false;
        $scopelen = ord($s);
        if ($scopelen === 0) {
            return \substr($s, 1);
        } else {
            $nb = \strlen($s) - $scopelen;
            if (\substr($s, $nb) !== $scope) return false;
            return \substr($s, 1, $nb - 1);
        }
    }
    public function seal($s, $scope = iic::NOSCOPE) {
        if (empty($scope))
            throw new \moodle_exception('scope can\'t be empty', 'auth_entsync');
        $this->ensurekeys();
        $ivSize = self::ivLength();
        $ivb = \openssl_random_pseudo_bytes($ivSize);
        if (iic::NOSCOPE === $scope) {
            $s = chr(0) . $s;
        } else {
            $scopelen = \strlen($scope);
            if ($scopelen > 255)
                throw new \moodle_exception('scope to long', 'auth_entsync');
            $s = chr($scopelen) . $s . $scope;
        }
        $s = $ivb . \openssl_encrypt($s, self::METHOD, $this->kb, \OPENSSL_RAW_DATA, $ivb);
        return self::TYPE . $this->uid . self::base64_url_encode($s);
    }
}

<?php
namespace auth_entsync\helpers;

defined('MOODLE_INTERNAL') || die;

class http_client {
    const DEFAULTS = [
        \CURLOPT_SSL_VERIFYHOST => false,
        \CURLOPT_SSL_VERIFYPEER => false,
        \CURLOPT_FOLLOWLOCATION => false,
        \CURLOPT_HEADER => false,
        \CURLOPT_RETURNTRANSFER => true,
        \CURLOPT_TIMEOUT => 30,
    ];
    const HEADERS = [
        'User-Agent: entsync/1.0',
        'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
        'Connection: keep-alive',
    ];
    public function __construct() {
    }
    protected function prepareCurl() {
        $ch = \curl_init();
        \curl_setopt_array($ch, self::DEFAULTS);
        return $ch;
    }
    protected function setHeader($ch, $auth = null) {
        $headers = self::HEADERS;
        if (!empty($auth)) {
            $headers[] = 'Authorization: ' . $auth;
        }
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
    }
    protected function closeCurl($ch) {
        curl_close($ch);
    }
    public function get($mdlurl, $params = null, $auth = null) {
        $mdlurl = $mdlurl->out(false, $params);
        $ch = $this->prepareCurl();
        $this->setHeader($ch, $auth);
        \curl_setopt($ch, \CURLOPT_URL, $mdlurl);
        $ret = \curl_exec($ch);
        if(curl_errno($ch)) return false;
        $status = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $location = \curl_getinfo($ch, \CURLINFO_REDIRECT_URL);
        $this->closeCurl($ch);
        return [
            'content' => $ret,
            'status' => $status,
            'location' => $location,
        ];
    }
    public function beginSession() {
        return new http_client_session();
    }
}

class http_client_session extends http_client {
    protected $_share = null;
    public function __construct() {
        $sh = \curl_share_init();
        \curl_share_setopt($sh, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_COOKIE);
        \curl_share_setopt($sh, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_DNS);
        \curl_share_setopt($sh, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_SSL_SESSION);
        $this->_share = $sh;
    }
    protected function prepareCurl() {
        $ch = parent::prepareCurl();
        \curl_setopt($ch, \CURLOPT_SHARE, $this->_share);
        return $ch;
    }
    public function terminate() {
        \curl_share_close($this->_share);
    }
}

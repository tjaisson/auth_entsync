<?php
namespace auth_entsync\farm;
defined('MOODLE_INTERNAL') || die;
class instance_info {
    const TTL = 3600;
    protected $api_client;
    protected $_rnes = null;
    public function __construct($api_client) {
        $this->api_client = $api_client;
    }
    public function rnes() {
        if (null !== $this->_rnes) return $this->_rnes;
        $cache = \cache::make('auth_entsync', 'farm');
        $time = \time();
        if ((false !== ($rnesCh = $cache->get('rnes'))) && ($rnesCh['expir'] > $time)) {
            $this->_rnes = $rnesCh['rnes'];
            return $this->_rnes;
        } else {
            $rnes = $this->api_client->get('instance.rnes');
            $cache->set('rnes', ['expir' => $time + self::TTL, 'rnes' => $rnes]);
            $this->_rnes = $rnes;
            return $rnes;
        }
    }
}
<?php
namespace auth_entsync\Security\Impl;

use auth_entsync\Security\TokenServiceInterface;
use auth_entsync\Security\TokenBuilderInterface;
use auth_entsync\Security\TokenValidatorInterface;

class TokenService implements TokenServiceInterface
{
    /** @var \moodle_database $db */
    protected $db;

    /** @var \auth_entsync\conf $conf */
    protected $conf;

    /**
     * @param \moodle_database $db
     * @param \auth_entsync\conf $conf
     */
    public function __construct($db, $conf) { $this->db = $db; $this->conf = $conf; }

    public function createBuilder(): TokenBuilderInterface
    {
        return new TokenBuilder(
            $this->get_kr(),
            $this->get_nr()
        );
    }
    public function createValidator(): TokenValidatorInterface
    {
        return new TokenValidator(
            $this->get_kr(),
            $this->get_nr()
        );
    }

    protected $kr_inst = null;
    protected function get_kr()
    {
        $inst = $this->kr_inst;
        if (null === $inst) {
            $inst = new KeyRepository($this->db, $this->conf);
            $this->kr_inst = $inst;
        }
        return $inst;
    }
    protected $nr_inst = null;
    protected function get_nr()
    {
        $inst = $this->nr_inst;
        if (null === $inst) {
            $inst = new NonceRepository($this->db, $this->conf);
            $this->nr_inst = $inst;
        }
        return $inst;
    }
}
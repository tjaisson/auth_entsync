<?php
namespace auth_entsync\Security\Impl;

use auth_entsync\Security\NonceRepositoryInterface;


class NonceRepository implements NonceRepositoryInterface
{
    const TABLE = 'token_nonces';

    /** @var \moodle_database $db */
    protected $db;

    /** @var string $farmdb */
    protected $farmdb;

    /** 
     * @param \moodle_database $db
     * @param \auth_entsync\conf $conf
     */
    public function __construct($db, $conf) {
        $this->db = $db;
        $this->farmdb = $conf->farmdb();
    }
    
    const MAX_ATTEMPTS = 5;
    public function createNonce(): int
    {
        $this->clear();
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql = "SELECT EXISTS(SELECT 1 FROM {$farmdb}.{{$tbl}} WHERE val = :val)";
        $maxAttempts = self::MAX_ATTEMPTS;
        while ($maxAttempts-- > 0) {
            $val = \random_bytes(8);
            $val[0] = \chr(0x7f & \ord($val[0])); 
            $val = \unpack('J',$val)[1] ;
            $f = $this->db->count_records_sql($sql, ['val' => $val]);
            if (!$f) return $val;
        }
        throw new \Exception('Impossible de trouver un nonce qui n\'est pas déjà la table ' . self::TABLE .'.');
    }

    public function validateNonce(int $nonce, int $exp): bool
    {
        $this->clear();
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql = "SELECT EXISTS(SELECT 1 FROM {$farmdb}.{{$tbl}} WHERE val = :val)";
        $f = $this->db->count_records_sql($sql, ['val' => $nonce]);
        if (!!$f) return false;
        $sql = "INSERT INTO {$farmdb}.{{$tbl}} (val, exp) VALUES(:val, :exp)";
        $this->db->execute($sql, ['val' => $nonce, 'exp' => $exp]);
        return true;
    }

    protected function clear()
    {
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql = "DELETE FROM {$farmdb}.{{$tbl}} WHERE exp < :exp";
        $this->db->execute($sql, ['exp' => \time()]);
    }
}
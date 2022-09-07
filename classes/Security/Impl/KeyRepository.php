<?php
namespace auth_entsync\Security\Impl;

use auth_entsync\Security\KeyInterface;
use auth_entsync\Security\KeyRepositoryInterface;

Class KeyRepository implements KeyRepositoryInterface
{
    const TABLE = 'token_keys';
    const ROTATION = 3600;
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

    public function find(int $id): ?KeyInterface
    {
        $this->clear();
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql =
"SELECT id, sign, cypher, exp
FROM {$farmdb}.{{$tbl}}
WHERE id = :id";
        $params = ['id' => $id];
        $rec = $this->db->get_record_sql($sql, $params);
        if (! $rec) return null;
        return new Key($rec);
    }

    public function getSuitableKey(int $ttl): KeyInterface
    {
        $this->clear();
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $now = \time();
        $min = $now + $ttl;
        $max = $now + 2* \max(self::ROTATION, $ttl);
        $sql = 
"SELECT id, sign, cypher, exp
FROM {$farmdb}.{{$tbl}}
WHERE exp >= :min AND exp <= :max
ORDER BY exp LIMIT 1";
        $params = ['min' => $min, 'max' => $max];
        $rec = $this->db->get_record_sql($sql, $params);
        if ($rec) return new Key($rec);
        else return $this->createKey($max);
    }

    protected function clear()
    {
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql = "DELETE FROM {$farmdb}.{{$tbl}} WHERE exp < :exp";
        $this->db->execute($sql, ['exp' => \time()]);
    }

    protected function createKey(int $exp): KeyInterface
    {
        $rec = [
            'id' => $this->createId(),
            'sign' => \random_bytes(KeyInterface::SIGN_KEY_LEN),
            'cypher' => \random_bytes(KeyInterface::CYPHER_KEY_LEN),
            'exp' => $exp,
        ];
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql = "INSERT INTO {$farmdb}.{{$tbl}} (id, sign, cypher, exp) VALUES (:id, :sign, :cypher, :exp)";
        $this->db->execute($sql, $rec);
        return new Key((object)$rec);
    }

    const MAX_ATTEMPTS = 5;
    protected function createId()
    {
        $tbl = self::TABLE;
        $farmdb = $this->farmdb;
        $sql = "SELECT EXISTS(SELECT 1 FROM {$farmdb}.{{$tbl}} WHERE id = :id)";

        $maxAttempts = self::MAX_ATTEMPTS;
        while ($maxAttempts-- > 0) {
            $id = \unpack('V',\random_bytes(4))[1];
            $f = $this->db->count_records_sql($sql, ['id' => $id]);
            if (!$f) return $id;
        }
        throw new \Exception('Collision d\'identifiant dans la table ' . self::TABLE);
    }
}
<?php
namespace auth_entsync\Security\Impl;

use auth_entsync\Security\KeyInterface;

class Key implements KeyInterface
{
    public function __construct($rec)
    {
        $this->id = $rec->id;
        $this->sign = $rec->sign;
        $this->cypher = $rec->cypher;
        $this->exp = $rec->exp;
    }

    protected int $id;
    protected string $sign;
    protected string $cypher;
    protected int $exp;
    public function getId(): int
    {
        return $this->id;
    }
    public function getSignKey(): string
    {
        return $this->sign;
    }
    public function getCypherKey(): string
    {
        return $this->cypher;
    }
    public function getExpiration(): int
    {
        return $this->exp;
    }
}
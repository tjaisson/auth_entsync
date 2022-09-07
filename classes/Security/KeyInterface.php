<?php
namespace auth_entsync\Security;

interface KeyInterface
{
    const SIGN_KEY_LEN = 32;
    const CYPHER_KEY_LEN = 32;
    public function getId(): int;
    public function getSignKey(): string;
    public function getCypherKey(): string;
    public function getExpiration(): int;
}
<?php
namespace auth_entsync\Security;

use auth_entsync\Security\KeyInterface;

interface KeyRepositoryInterface
{
    public function find(int $id): ?KeyInterface;
    public function getSuitableKey(int $ttl): KeyInterface;
}
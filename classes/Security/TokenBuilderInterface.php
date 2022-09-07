<?php
namespace auth_entsync\Security;

interface TokenBuilderInterface
{
    public function withTTL(int $ttl): TokenBuilderInterface;
    public function withExpiration(int $expiration): TokenBuilderInterface;
    public function withData(string $data): TokenBuilderInterface;
    public function withEncription(): TokenBuilderInterface;
    public function withNonce(): TokenBuilderInterface;
    public function toString(): string;
}
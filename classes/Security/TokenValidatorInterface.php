<?php
namespace auth_entsync\Security;

interface TokenValidatorInterface
{
    public function withToken(string $data): TokenValidatorInterface;
    public function withEncription(): TokenValidatorInterface;
    public function withNonce(): TokenValidatorInterface;
    public function withSubject(string $subject): TokenValidatorInterface;
    public function validate(): bool;
    public function getData(): string;
    public function getExpiration(): int;
}
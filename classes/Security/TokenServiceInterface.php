<?php
namespace auth_entsync\Security;

interface TokenServiceInterface
{
    public function createBuilder(): TokenBuilderInterface;
    public function createValidator(): TokenValidatorInterface;
}
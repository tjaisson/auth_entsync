<?php
namespace auth_entsync\Security\Impl;

use auth_entsync\Security\TokenBuilderInterface;

class TokenBuilder implements TokenBuilderInterface
{
    /** @var \auth_entsync\Security\KeyRepositoryInterface $keyRepository */
    protected $keyRepository;
    /** @var \auth_entsync\Security\NonceRepositoryInterface $nonceRepository */
    protected $nonceRepository;
    protected bool $expirSet = false;
    protected int $ttl;
    protected int $exp;
    protected string $data = '';
    protected bool $useEncryption = false;
    protected bool $useNonce = false;
    /**
     * @param \auth_entsync\Security\KeyRepositoryInterface $keyRepository
     * @param \auth_entsync\Security\NonceRepositoryInterface $nonceRepository
     */
    public function __construct($keyRepository, $nonceRepository)
    {
        $this->keyRepository = $keyRepository;
        $this->nonceRepository = $nonceRepository;
    }
    public function withTTL(int $ttl): TokenBuilderInterface
    {
        if ($ttl < 0) throw new \Exception('Token expiration can\'t be in the past');
        $this->ttl = $ttl;
        $this->exp = \time() + $ttl;
        $this->expirSet = true;
        return $this;
    }
    public function withExpiration(int $expiration): TokenBuilderInterface
    {
        $now = \time();
        if ($expiration < $now) throw new \Exception('Token expiration can\'t be in the past');
        $this->exp = $expiration;
        $this->ttl = $expiration - $now;
        $this->expirSet = true;
        return $this;
    }
    public function withData(string $data): TokenBuilderInterface
    {
        $this->data = $data;
        return $this;
    }
    public function withEncription(): TokenBuilderInterface
    {
        $this->useEncryption = true;
        return $this;
    }
    public function withNonce(): TokenBuilderInterface
    {
        $this->useNonce = true;
        return $this;
    }
    public function toString(): string
    {
        if (! $this->expirSet) throw new \Exception('Token expiration must be set.');
        $key = $this->keyRepository->getSuitableKey($this->ttl);
        $kid = \pack('V', $key->getId());
        $dl = \strlen($this->data);
        if ($dl < 32) {
            $flag = 31 - $dl;
            if ($flag === 0) $flag = 1;
            $data = \random_bytes($flag + 1) . $this->data;
        } else {
            $flag = 0;
            $data = $this->data;
        }
        if ($this->useNonce) {
            $flag |= 0x80;
            $data = \pack('J', $this->nonceRepository->createNonce()) . $data;
        }
        $data = \chr($flag) . \pack('J', $this->exp) . $data;
        $signature = \hash_hmac('sha256', $data, $key->getSignKey(), true);
        $data .= $signature;
        if ($this->useEncryption) {
            $iv = \random_bytes(16);
            $data = \openssl_encrypt($data, 'aes-256-ctr', $key->getCypherKey(), \OPENSSL_RAW_DATA, $iv);
            $data = $iv . $data;
            $flag = \chr(0x10);
        } else {
            $flag = \chr(0x00);
        }
        $data = \strtr(\rtrim(\base64_encode($data), '='), '+/', '-_');
        $hdr = \strtr(\substr(\base64_encode($kid . $flag), 0, 6), '+/', '-_');
        return $hdr . $data;
    }
}
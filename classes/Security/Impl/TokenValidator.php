<?php
namespace auth_entsync\Security\Impl;

use auth_entsync\Security\TokenValidatorInterface;

class TokenValidator implements TokenValidatorInterface
{
    /** @var \auth_entsync\Security\KeyRepositoryInterface $keyRepository */
    protected $keyRepository;
    /** @var \auth_entsync\Security\NonceRepositoryInterface $nonceRepository */
    protected $nonceRepository;
    protected bool $tkSet = false;
    protected string $tk;
    protected string $subject = '';
    protected bool $useEncryption = false;
    protected bool $useNonce = false;
    protected bool $checked = false;
    protected bool $valid;
    protected string $data;
    protected int $exp;
    /**
     * @param \auth_entsync\Security\KeyRepositoryInterface $keyRepository
     * @param \auth_entsync\Security\NonceRepositoryInterface $nonceRepository
     */
    public function __construct($keyRepository, $nonceRepository)
    {
        $this->keyRepository = $keyRepository;
        $this->nonceRepository = $nonceRepository;
    }
    public function withToken(string $tk): TokenValidatorInterface
    {
        $this->tk = $tk;
        $this->tkSet = true;
        return $this;
    }
    public function withEncription(): TokenValidatorInterface
    {
        $this->useEncryption = true;
        return $this;
    }
    public function withNonce(): TokenValidatorInterface
    {
        $this->useNonce = true;
        return $this;
    }
    public function withSubject(string $subject): TokenValidatorInterface
    {
        $this->subject = $subject;
        return $this;
    }
    public function validate(): bool
    {
        if ($this->checked) return $this->valid;
        $this->checked = true;
        $this->valid = false;
        if (! $this->tkSet) throw new \Exception('Token must be set.');
        $hdr = \substr($this->tk, 0, 6) . 'A';
        $hdr = \base64_decode(\strtr($hdr, '-_', '+/'), true);
        $kid = \unpack('Vkid/Cflag', $hdr);
        $flag = $kid['flag'] & 0xf0;
        $kid = $kid['kid'];
        $key = $this->keyRepository->find($kid);
        if (! $key) return false;
        if ($flag === 0x10) {
            $useEncryption = true;
        } else if ($flag === 0) {
            if ($this->useEncryption) return false;
            $useEncryption = false;
        } else {
            return false;
        }
        $data = \substr($this->tk, 6);
        $data = \base64_decode(\strtr($data, '-_', '+/'), true);
        if ($useEncryption) {
            $iv = \substr($data, 0, 16);
            $data = \substr($data, 16);
            $data = \openssl_decrypt($data, 'aes-256-ctr', $key->getCypherKey(), \OPENSSL_RAW_DATA, $iv);
        }
        $flag = \ord($data[0]);
        $useNonce = ($flag & 0x80) != 0;
        $hasSubject = ($flag & 0x40) != 0;
        $flag &= 0x1f;
        if ($flag > 0) ++$flag;
        $exp = \unpack('J', $data, 1)[1];
        if ($exp < \time()) return false;
        $this->exp = $exp;
        if ($useNonce) {
            $nonce = \unpack('J', $data, 9)[1];
            if (! $this->nonceRepository->validateNonce($nonce, $exp + 60)) return false;
            $flag += 17;
        } else {
            if ($this->useNonce) return false;
            $flag += 9;
        }
        $signature = \substr($data, -32);
        $data = \substr($data, 0, -32);
        $compSignature = \hash_hmac('sha256', $data, $key->getSignKey(), true);
        if ($compSignature !== $signature) return false;
        if ($hasSubject) {
            if ($this->subject === '') return false;
            $subLen = ord($data[$flag]) + 1;
            $flag += 1;
            $subject = \substr($data, $flag, $subLen);
            if ($subject !== $this->subject) return false;
            $flag +=  $subLen;
        } else {
            if ($this->subject !== '') return false;
        }
        $this->data = \substr($data, $flag);
        $this->valid = true;
        return true;
    }

    public function getData(): string
    {
        if (! $this->checked) $this->validate();
        if (! $this->valid) throw new \Exception('Token is invalid.');
        return $this->data;
    }

    public function getExpiration(): int
    {
        if (! $this->checked) $this->validate();
        if (! $this->valid) throw new \Exception('Token is invalid.');
        return $this->exp;
    }
}
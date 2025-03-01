<?php declare(strict_types=1);

namespace Vulpes\Cloud\Access;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Clock\ClockInterface;
use RuntimeException;

class AccessToken implements AccessTokenInterface
{
    public const string ALGORITHM_RS_256 = 'RS256';
    protected const string CANNOT_GENERATE_TOKEN = 'Cannot generate token %s not set.';
    protected const string CANNOT_DECODE_TOKEN_PUBLIC_KEY_NOT_SET = 'Cannot decode token, public key not set.';

    protected null|string $token = null;
    protected null|int $tokenExpiresAt = null;

    public function __construct(
        protected ClockInterface|null $clock = null,
        protected null|string         $private_key_id = null,
        protected null|string         $private_key = null,
        protected null|string         $public_key = null,
        protected null|object|array   $payload = null,
        public null|string            $algorithm = self::ALGORITHM_RS_256,
    )
    {
    }

    public function getToken(): string
    {
        if ($this->token && $this->now()->getTimestamp() < ($this->tokenExpiresAt - 60)) {
            return $this->token;
        }
        return $this->generateToken();
    }

    public function generateToken(): string
    {
        $payload = $this->generatePayload();
        $this->tokenExpiresAt = $payload['exp'];
        return $this->token = JWT::encode($payload, $this->private_key, $this->algorithm, $this->private_key_id);
    }

    public function decodeToken(string $token): array
    {
        if (is_null($this->public_key)) {
            throw new RuntimeException(self::CANNOT_DECODE_TOKEN_PUBLIC_KEY_NOT_SET);
        }
        return get_object_vars(JWT::decode($token, new Key($this->public_key, $this->algorithm)));
    }

    protected function generatePayload(): array
    {
        $this->payload = is_object($this->payload) ? get_object_vars($this->payload) : $this->payload;
        if (is_array($this->payload) === false) {
            throw new RuntimeException(sprintf(self::CANNOT_GENERATE_TOKEN, 'payload'));
        }
        return array_merge($this->payload, array_filter([
            'iss' => $this->getIssuer(),
            'iat' => $this->getIssuedAt(),
            'nbf' => $this->getNotBefore(),
            'exp' => $this->getExpirationTime(),
            'sub' => $this->getSubject()
        ]));
    }

    public function getIssuer(): null|string
    {
        if (is_object($this->payload) && $this->payload->iss ?? null) {
            return strval($this->payload->iss);
        }
        if (is_array($this->payload) && $this->payload['iss'] ?? null) {
            return strval($this->payload['iss']);
        }
        throw new RuntimeException(sprintf(self::CANNOT_GENERATE_TOKEN, 'payload.issuer'));
    }

    public function getIssuedAt(): int
    {
        if (is_object($this->payload) && is_numeric($this->payload->iat ?? null)) {
            return intval($this->payload->iat);
        }
        if (is_array($this->payload) && is_numeric($this->payload['iat'] ?? null)) {
            return intval($this->payload['iat']);
        }
        return $this->now()->getTimestamp();
    }

    public function getNotBefore(): null|int
    {
        if (is_object($this->payload) && is_numeric($this->payload->nbf ?? null)) {
            return intval($this->payload->nbf);
        }
        if (is_array($this->payload) && is_numeric($this->payload['nbf'] ?? null)) {
            return intval($this->payload['nbf']);
        }
        return null;
    }

    public function getExpirationTime(): null|int
    {
        if (is_object($this->payload) && is_numeric($this->payload->exp ?? null)) {
            return intval($this->payload->exp);
        }
        if (is_array($this->payload) && is_numeric($this->payload['exp'] ?? null)) {
            return intval($this->payload['exp']);
        }
        return $this->now()->add(new DateInterval('PT1H'))->getTimestamp();
    }

    private function getSubject(): string|int|null
    {
        if (is_object($this->payload) && $this->payload->sub ?? null) {
            return $this->payload->sub;
        }
        if (is_array($this->payload) && $this->payload['sub'] ?? null) {
            return $this->payload['sub'];
        }
        throw new RuntimeException(sprintf(self::CANNOT_GENERATE_TOKEN, 'payload.subject'));
    }

    public function generateKeys(int $privateKeyBits = 2048, int $privateKeyType = OPENSSL_KEYTYPE_RSA): void
    {
        $resource = openssl_pkey_new(["private_key_bits" => $privateKeyBits, "private_key_type" => $privateKeyType]);

        openssl_pkey_export($resource, $privateKey);

        $this->private_key = $privateKey;

        $keyDetails = openssl_pkey_get_details($resource);

        $this->public_key = $keyDetails["key"];
        $this->private_key_id = base64_encode(hash('sha256', $keyDetails["key"], true));
    }

    public function getPrivateKey(): ?string
    {
        return $this->private_key;
    }

    public function getPrivateKeyId(): ?string
    {
        return $this->private_key_id;
    }

    public function getPublicKey(): ?string
    {
        return $this->public_key;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

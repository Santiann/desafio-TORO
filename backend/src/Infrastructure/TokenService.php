<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Identity;
use App\Domain\Role;
use App\Domain\User;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

final class TokenService
{
    private const ALGORITHM = 'HS256';
    private const TTL_SECONDS = 3600;
    private const MINIMUM_SECRET_BYTES = 32;

    public function __construct(private readonly string $secret)
    {
        if (strlen($this->secret) < self::MINIMUM_SECRET_BYTES) {
            throw new RuntimeException('JWT_SECRET must have at least 32 bytes');
        }
    }

    public function issue(User $user): string
    {
        $issuedAt = time();

        return JWT::encode([
            'sub' => $user->id,
            'role' => $user->role->value,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::TTL_SECONDS,
        ], $this->secret, self::ALGORITHM);
    }

    public function verify(string $token): ?Identity
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (InvalidArgumentException | DomainException | UnexpectedValueException) {
            return null;
        }

        $subject = $claims->sub ?? null;
        $role = is_string($claims->role ?? null) ? Role::tryFrom($claims->role) : null;

        if (!is_int($subject) || $subject <= 0 || $role === null) {
            return null;
        }

        return new Identity($subject, $role);
    }
}

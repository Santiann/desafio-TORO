<?php

declare(strict_types=1);

namespace App\Domain;

use App\Infrastructure\UserRepository;

final class AuthService
{
    private const DUMMY_HASH = '$2y$10$7HmlIhawwhVzJ1lvueAbZuv3.3EV2uLayJjQYFiqgyRZOTTFowoFG';

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        $matches = password_verify($password, $user?->passwordHash ?? self::DUMMY_HASH);

        if ($user === null || !$matches) {
            return null;
        }

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Role;
use App\Domain\User;

final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = ?'
        );
        $statement->execute([$email]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            Role::from((string) $row['role']),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Role;
use App\Domain\User;

final class UserRepository
{
    private const COLUMNS = 'id, name, email, password_hash, role';

    public function __construct(private readonly Database $database)
    {
    }

    public function find(int $id): ?User
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?'
        );
        $statement->execute([$email]);
        $row = $statement->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            Role::from((string) $row['role']),
        );
    }
}

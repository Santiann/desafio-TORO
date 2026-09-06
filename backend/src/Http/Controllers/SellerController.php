<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\User;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\UserRepository;

final class SellerController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function index(Request $request): Response
    {
        return Response::json([
            'data' => array_map(
                static fn (User $seller): array => [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'email' => $seller->email,
                ],
                $this->users->sellers(),
            ),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AuthService;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\TokenService;
use App\Support\HttpException;
use App\Support\Validator;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TokenService $tokens,
    ) {
    }

    public function login(Request $request): Response
    {
        $input = new Validator($request->body());
        $email = $input->string('email', 190);
        $password = $input->string('password', 200);
        $input->assertValid();

        $user = $this->auth->authenticate($email, $password);

        if ($user === null) {
            throw HttpException::invalidCredentials();
        }

        return Response::json([
            'token' => $this->tokens->issue($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }
}

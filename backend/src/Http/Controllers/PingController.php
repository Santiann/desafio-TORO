<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;

final class PingController
{
    public function pong(Request $request): Response
    {
        $identity = $request->identity();

        return Response::json([
            'pong' => true,
            'user_id' => $identity->id,
            'role' => $identity->role->value,
        ]);
    }
}

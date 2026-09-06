<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\WalletEntry;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\WalletEntryRepository;
use App\Support\HttpException;
use App\Support\Validator;

final class WalletController
{
    private const LIMIT_DEFAULT = 20;
    private const LIMIT_MAX = 100;
    private const OFFSET_MAX = 1000000;

    public function __construct(private readonly WalletEntryRepository $walletEntries)
    {
    }

    public function show(Request $request): Response
    {
        $sellerId = $request->identity()->id;
        $requested = $request->query('seller_id');

        if ($requested !== null && $requested !== (string) $sellerId) {
            throw HttpException::notFound();
        }

        $input = new Validator($request->queryParams());
        $limit = $input->optionalInteger('limit', self::LIMIT_DEFAULT, 1, self::LIMIT_MAX);
        $offset = $input->optionalInteger('offset', 0, 0, self::OFFSET_MAX);
        $input->assertValid();

        return Response::json([
            'seller_id' => $sellerId,
            'balance' => $this->walletEntries->balance($sellerId),
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $this->walletEntries->countBySeller($sellerId),
            ],
            'data' => array_map(
                static fn (WalletEntry $entry): array => $entry->toArray(),
                $this->walletEntries->listBySeller($sellerId, $limit, $offset),
            ),
        ]);
    }
}

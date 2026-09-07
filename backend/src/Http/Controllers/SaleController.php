<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\SaleStatus;
use App\Domain\SaleSummary;
use App\Domain\ScoringException;
use App\Domain\ScoringService;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\SaleRepository;
use App\Support\HttpException;
use App\Support\Validator;

final class SaleController
{
    private const EXTERNAL_ID_MAX = 64;
    private const EXTERNAL_ID_PATTERN = '/^[A-Za-z0-9._-]+$/';
    private const EXTERNAL_ID_MESSAGE = 'use apenas letras, números, ponto, hífen ou underline';
    private const ID_MAX = 4294967295;
    private const QUANTITY_MAX = 1000000;
    private const UNIT_VALUE_MAX = 9999999999.99;
    private const LIMIT_DEFAULT = 20;
    private const LIMIT_MAX = 100;
    private const OFFSET_MAX = 1000000;

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly SaleRepository $sales,
    ) {
    }

    public function index(Request $request): Response
    {
        $input = new Validator($request->queryParams());
        $campaignId = $input->nullableInteger('campaign_id', 1, self::ID_MAX);
        $sellerId = $input->nullableInteger('seller_id', 1, self::ID_MAX);
        $status = $input->nullableChoice('status', array_column(SaleStatus::cases(), 'value'));
        $limit = $input->optionalInteger('limit', self::LIMIT_DEFAULT, 1, self::LIMIT_MAX);
        $offset = $input->optionalInteger('offset', 0, 0, self::OFFSET_MAX);
        $input->assertValid();

        $saleStatus = $status === null ? null : SaleStatus::from($status);

        return Response::json([
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $this->sales->countMatching($campaignId, $sellerId, $saleStatus),
            ],
            'data' => array_map(
                static fn (SaleSummary $summary): array => $summary->toArray(),
                $this->sales->search($campaignId, $sellerId, $saleStatus, $limit, $offset),
            ),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = new Validator($request->body());
        $externalId = $input->pattern('external_id', self::EXTERNAL_ID_MAX, self::EXTERNAL_ID_PATTERN, self::EXTERNAL_ID_MESSAGE);
        $campaignId = $input->integer('campaign_id', 1, self::ID_MAX);
        $sellerId = $input->integer('seller_id', 1, self::ID_MAX);
        $productId = $input->integer('product_id', 1, self::ID_MAX);
        $quantity = $input->integer('quantity', 1, self::QUANTITY_MAX);
        $unitValue = $input->decimal('unit_value', 0, self::UNIT_VALUE_MAX);
        $input->assertValid();

        try {
            $outcome = $this->scoring->registerSale(
                $externalId,
                $campaignId,
                $sellerId,
                $productId,
                $quantity,
                $unitValue,
                $request->identity()->id,
            );
        } catch (ScoringException $e) {
            throw HttpException::rejected($e->errorCode, $e->getMessage(), $e->fields);
        }

        return Response::json(
            [...$outcome->sale->toArray(), 'duplicate' => !$outcome->applied],
            $outcome->applied ? 201 : 200,
        );
    }

    public function cancel(Request $request): Response
    {
        $externalId = (string) $request->param('external_id');

        if (mb_strlen($externalId) > self::EXTERNAL_ID_MAX || preg_match(self::EXTERNAL_ID_PATTERN, $externalId) !== 1) {
            throw HttpException::notFound();
        }

        $outcome = $this->scoring->cancelSale($externalId);

        if ($outcome === null) {
            throw HttpException::notFound();
        }

        return Response::json([...$outcome->sale->toArray(), 'already_canceled' => !$outcome->applied]);
    }
}

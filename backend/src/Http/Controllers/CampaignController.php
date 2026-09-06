<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Campaign;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\CampaignRepository;
use App\Support\HttpException;
use App\Support\Validator;

final class CampaignController
{
    private const NAME_MAX = 150;
    private const BUDGET_MAX = 1000000000;

    public function __construct(private readonly CampaignRepository $campaigns)
    {
    }

    public function index(Request $request): Response
    {
        return Response::json([
            'data' => array_map(
                static fn (Campaign $campaign): array => $campaign->toArray(),
                $this->campaigns->all(),
            ),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = new Validator($request->body());
        $name = $input->string('name', self::NAME_MAX);
        $budgetTotal = $input->integer('budget_total', 1, self::BUDGET_MAX);
        $startsAt = $input->dateTime('starts_at');
        $endsAt = $input->dateTime('ends_at');

        if ($startsAt !== '' && $endsAt !== '' && $startsAt >= $endsAt) {
            $input->fail('ends_at', 'deve ser posterior a starts_at');
        }

        $input->assertValid();

        $campaign = $this->campaigns->create($name, $budgetTotal, $startsAt, $endsAt);

        return Response::json($campaign->toArray(), 201);
    }

    public function close(Request $request): Response
    {
        $outcome = $this->campaigns->close($request->integerParam('id'));

        if ($outcome === null) {
            throw HttpException::notFound();
        }

        return Response::json([...$outcome->campaign->toArray(), 'already_closed' => !$outcome->applied]);
    }
}

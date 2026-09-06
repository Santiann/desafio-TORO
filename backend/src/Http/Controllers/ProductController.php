<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\ProductRepository;
use App\Support\HttpException;
use App\Support\Validator;

final class ProductController
{
    private const NAME_MAX = 150;
    private const SKU_MAX = 64;
    private const SKU_PATTERN = '/^[A-Za-z0-9._-]+$/';
    private const POINTS_MAX = 1000000;

    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function index(Request $request): Response
    {
        return Response::json([
            'data' => array_map(
                static fn (Product $product): array => $product->toArray(),
                $this->products->all(),
            ),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = new Validator($request->body());
        $name = $input->string('name', self::NAME_MAX);
        $sku = $input->pattern('sku', self::SKU_MAX, self::SKU_PATTERN, 'use apenas letras, números, ponto, hífen ou underline');
        $pointsPerUnit = $input->integer('points_per_unit', 0, self::POINTS_MAX);
        $active = $input->boolean('active');
        $input->assertValid();

        $product = $this->products->create($name, $sku, $pointsPerUnit, $active);

        return Response::json($product->toArray(), 201);
    }

    public function update(Request $request): Response
    {
        $id = $request->integerParam('id');

        $input = new Validator($request->body());
        $name = $input->string('name', self::NAME_MAX);
        $sku = $input->pattern('sku', self::SKU_MAX, self::SKU_PATTERN, 'use apenas letras, números, ponto, hífen ou underline');
        $pointsPerUnit = $input->integer('points_per_unit', 0, self::POINTS_MAX);
        $active = $input->boolean('active');
        $input->assertValid();

        $product = $this->products->update($id, $name, $sku, $pointsPerUnit, $active);

        if ($product === null) {
            throw HttpException::notFound();
        }

        return Response::json($product->toArray());
    }

    public function destroy(Request $request): Response
    {
        $product = $this->products->deactivate($request->integerParam('id'));

        if ($product === null) {
            throw HttpException::notFound();
        }

        return Response::json($product->toArray());
    }
}

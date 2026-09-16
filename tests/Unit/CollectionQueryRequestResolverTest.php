<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\Resolver\CollectionQueryRequestResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CollectionQueryRequestResolverTest extends TestCase
{
    public function testResolvesOnlyAllowedAndBoundedQueryParts(): void
    {
        $definition = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('name', true, true, true),
            new CollectionFieldPolicyDTO('status', false, true, true),
            new CollectionFieldPolicyDTO('priority', false, true, true, true, ['eq', 'neq', 'lt', 'lte', 'gt', 'gte']),
            new CollectionFieldPolicyDTO('secret', false, false, false, false),
        ], 25, 100);

        $request = Request::create('/items', 'GET', [
            'page' => 2,
            'limit' => 500,
            'q' => ' Acme ',
            'filter' => [
                'status' => 'active',
                'priority' => ['gte' => '10', 'unknown' => 'ignored'],
                'secret' => 'hidden',
            ],
            'sort' => '-name,secret',
            'fields' => 'name,secret',
            'cursor' => rtrim(strtr(base64_encode(json_encode(['name' => 'Acme', 'id' => 10], JSON_THROW_ON_ERROR)), '+/', '-_'), '='),
        ]);

        $query = (new CollectionQueryRequestResolver())->resolve($request, $definition);

        self::assertSame(2, $query->page->number);
        self::assertSame(100, $query->page->size);
        self::assertSame(100, $query->page->offset());
        self::assertSame('Acme', $query->search);
        self::assertCount(2, $query->filters);
        self::assertSame('status', $query->filters[0]->field);
        self::assertSame('eq', $query->filters[0]->operator);
        self::assertSame('active', $query->filters[0]->value);
        self::assertSame('priority', $query->filters[1]->field);
        self::assertSame('gte', $query->filters[1]->operator);
        self::assertSame('10', $query->filters[1]->value);
        self::assertCount(1, $query->sorts);
        self::assertSame('name', $query->sorts[0]->field);
        self::assertSame('desc', $query->sorts[0]->direction);
        self::assertSame(['name'], $query->fields);
        self::assertNotNull($query->cursor);
        self::assertSame(['name' => 'Acme', 'id' => 10], $query->cursor);
    }

    public function testResolvesMembershipOperatorsOnlyForScalarLists(): void
    {
        $definition = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('id', false, true, true, true, ['eq', 'in', 'notIn']),
        ]);
        $request = Request::create('/items', 'GET', [
            'filter' => [
                'id' => [
                    'in' => [1, 2, 3],
                    'notIn' => [],
                    'eq' => ['invalid'],
                ],
            ],
        ]);

        $query = (new CollectionQueryRequestResolver())->resolve($request, $definition);

        self::assertCount(1, $query->filters);
        self::assertSame('in', $query->filters[0]->operator);
        self::assertSame([1, 2, 3], $query->filters[0]->value);
    }
}

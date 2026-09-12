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
            new CollectionFieldPolicyDTO('secret', false, false, false, false),
        ], 25, 100);

        $request = Request::create('/items', 'GET', [
            'page' => 2,
            'limit' => 500,
            'q' => ' Acme ',
            'filter' => ['status' => 'active', 'secret' => 'hidden'],
            'sort' => '-name,secret',
            'fields' => 'name,secret',
        ]);

        $query = (new CollectionQueryRequestResolver())->resolve($request, $definition);

        self::assertSame(2, $query->page->number);
        self::assertSame(100, $query->page->size);
        self::assertSame(100, $query->page->offset());
        self::assertSame('Acme', $query->search);
        self::assertCount(1, $query->filters);
        self::assertSame('status', $query->filters[0]->field);
        self::assertCount(1, $query->sorts);
        self::assertSame('name', $query->sorts[0]->field);
        self::assertSame('desc', $query->sorts[0]->direction);
        self::assertSame(['name'], $query->fields);
    }
}

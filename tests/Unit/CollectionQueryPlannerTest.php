<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionSortDTO;
use App\Collectioning\Service\CollectionQueryPlanner;
use PHPUnit\Framework\TestCase;

final class CollectionQueryPlannerTest extends TestCase
{
    public function testBuildsProviderNeutralEffectivePlan(): void
    {
        $definition = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('id', false, true, true, true, ['eq', 'neq', 'lt', 'lte', 'gt', 'gte']),
            new CollectionFieldPolicyDTO('name', true, true, true, true, ['eq', 'neq']),
            new CollectionFieldPolicyDTO('secret', false, false, false, false),
        ], identifierFields: ['id']);

        $query = new CollectionQueryDTO(
            page: new CollectionPageDTO(),
            search: 'Acme',
            filters: [
                new CollectionFilterDTO('name', 'eq', 'Acme'),
                new CollectionFilterDTO('name', 'gte', 'A'),
                new CollectionFilterDTO('id', 'raw', '>= 2'),
                new CollectionFilterDTO('secret', 'eq', 'hidden'),
                new CollectionFilterDTO('missing', 'eq', 'ignored'),
            ],
            sorts: [
                new CollectionSortDTO('name', 'desc'),
                new CollectionSortDTO('secret', 'asc'),
                new CollectionSortDTO('missing', 'asc'),
            ],
            fields: ['name', 'secret', 'missing'],
            cursor: ['name' => 'Acme', 'id' => 10],
        );

        $plan = (new CollectionQueryPlanner())->plan($definition, $query);

        self::assertSame(['name'], $plan->searchFields);
        self::assertCount(1, $plan->filters);
        self::assertSame('name', $plan->filters[0]->field);
        self::assertSame('eq', $plan->filters[0]->operator);
        self::assertSame(['name', 'id'], array_map(
            static fn (CollectionSortDTO $sort): string => $sort->field,
            $plan->sorts,
        ));
        self::assertSame(['desc', 'asc'], array_map(
            static fn (CollectionSortDTO $sort): string => $sort->direction,
            $plan->sorts,
        ));
        self::assertSame(['name'], $plan->projection);
        self::assertTrue($plan->cursorApplicable);
        self::assertSame('cursor', $plan->paginationMode());
    }

    public function testNormalizesMembershipFilterShapes(): void
    {
        $definition = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('id', false, true, true, true, ['eq', 'in', 'notIn']),
        ]);
        $plan = (new CollectionQueryPlanner())->plan($definition, new CollectionQueryDTO(
            new CollectionPageDTO(),
            filters: [
                new CollectionFilterDTO('id', 'in', [1, 2]),
                new CollectionFilterDTO('id', 'notIn', []),
                new CollectionFilterDTO('id', 'eq', [1]),
            ],
        ));

        self::assertCount(1, $plan->filters);
        self::assertSame('in', $plan->filters[0]->operator);
        self::assertSame([1, 2], $plan->filters[0]->value);
    }

    public function testCursorIsNotApplicableWhenShapeDoesNotMatchEffectiveSorts(): void
    {
        $definition = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('id', false, true, true, true, ['eq']),
        ], identifierFields: ['id']);

        $plan = (new CollectionQueryPlanner())->plan(
            $definition,
            new CollectionQueryDTO(new CollectionPageDTO(), cursor: ['name' => 'Acme']),
        );

        self::assertSame([], $plan->searchFields);
        self::assertSame([], $plan->filters);
        self::assertSame([], $plan->projection);
        self::assertFalse($plan->cursorApplicable);
        self::assertSame('offset', $plan->paginationMode());
    }

    public function testCursorApplicabilityRequiresCursorAndEffectiveSorts(): void
    {
        $sortable = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('id', false, true, true),
        ], identifierFields: ['id']);
        $withoutCursor = (new CollectionQueryPlanner())->plan(
            $sortable,
            new CollectionQueryDTO(new CollectionPageDTO()),
        );
        self::assertFalse($withoutCursor->cursorApplicable);

        $notSortable = new CollectionDefinitionDTO(\stdClass::class, [
            new CollectionFieldPolicyDTO('id', false, true, false),
        ], identifierFields: ['id']);
        $withoutSorts = (new CollectionQueryPlanner())->plan(
            $notSortable,
            new CollectionQueryDTO(new CollectionPageDTO(), cursor: ['id' => 1]),
        );
        self::assertSame([], $withoutSorts->sorts);
        self::assertFalse($withoutSorts->cursorApplicable);
    }

    public function testEmptyDefinitionProducesEmptyEffectivePlan(): void
    {
        $plan = (new CollectionQueryPlanner())->plan(
            new CollectionDefinitionDTO(\stdClass::class, []),
            new CollectionQueryDTO(new CollectionPageDTO(), search: 'Acme', cursor: ['id' => 1]),
        );

        self::assertSame([], $plan->searchFields);
        self::assertSame([], $plan->filters);
        self::assertSame([], $plan->sorts);
        self::assertSame([], $plan->projection);
        self::assertFalse($plan->cursorApplicable);
        self::assertSame('offset', $plan->paginationMode());
    }
}

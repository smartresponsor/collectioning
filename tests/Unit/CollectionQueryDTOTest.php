<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionSortDTO;
use PHPUnit\Framework\TestCase;

final class CollectionQueryDTOTest extends TestCase
{
    public function testStableSortsAppendIdentifierTieBreakersAfterRequestedSorts(): void
    {
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(),
            sorts: [new CollectionSortDTO('name', 'desc')],
        );

        $sorts = $query->stableSorts(['id', 'version']);

        self::assertSame(['name', 'id', 'version'], array_map(
            static fn (CollectionSortDTO $sort): string => $sort->field,
            $sorts,
        ));
        self::assertSame(['desc', 'asc', 'asc'], array_map(
            static fn (CollectionSortDTO $sort): string => $sort->direction,
            $sorts,
        ));
    }

    public function testStableSortsDoNotDuplicateExplicitIdentifierSort(): void
    {
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(),
            sorts: [
                new CollectionSortDTO('id', 'desc'),
                new CollectionSortDTO('name', 'asc'),
            ],
        );

        $sorts = $query->stableSorts(['id']);

        self::assertCount(2, $sorts);
        self::assertSame('id', $sorts[0]->field);
        self::assertSame('desc', $sorts[0]->direction);
        self::assertSame('name', $sorts[1]->field);
    }

    public function testStableSortsCanonicalizeDirectionsAndIgnoreDuplicateFields(): void
    {
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(),
            sorts: [
                new CollectionSortDTO('name', 'DESC'),
                new CollectionSortDTO('name', 'asc'),
                new CollectionSortDTO('id', 'sideways'),
            ],
        );

        $sorts = $query->stableSorts(['id']);

        self::assertSame(['name', 'id'], array_map(
            static fn (CollectionSortDTO $sort): string => $sort->field,
            $sorts,
        ));
        self::assertSame(['desc', 'asc'], array_map(
            static fn (CollectionSortDTO $sort): string => $sort->direction,
            $sorts,
        ));
    }

    public function testStableSortsAllowEmptyRequestedAndIdentifierSorts(): void
    {
        $query = new CollectionQueryDTO(new CollectionPageDTO());

        self::assertSame([], $query->stableSorts([]));
    }
}

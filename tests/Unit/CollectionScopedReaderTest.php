<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionDataScopeDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionResultDTO;
use App\Collectioning\Service\CollectionScopedReader;
use App\Collectioning\ServiceInterface\CollectionQueryProcessorInterface;
use PHPUnit\Framework\TestCase;

final class CollectionScopedReaderTest extends TestCase
{
    public function testReadsFilteredScopeAcrossBoundedPages(): void
    {
        $processor = new class implements CollectionQueryProcessorInterface {
            /** @var list<CollectionQueryDTO> */
            public array $queries = [];

            public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionResultDTO
            {
                $this->queries[] = $query;
                $items = 1 === $query->page->number ? [['id' => 1], ['id' => 2]] : [['id' => 3]];

                return new CollectionResultDTO($items, 3, 3, $query->page);
            }
        };
        $definition = new CollectionDefinitionDTO(\stdClass::class, [], maxPageSize: 2);
        $query = new CollectionQueryDTO(new CollectionPageDTO(4, 1), search: 'acme', fields: ['id']);

        $items = iterator_to_array((new CollectionScopedReader($processor))->read(
            $definition,
            $query,
            new CollectionDataScopeDTO(CollectionDataScopeDTO::FILTERED),
        ));

        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $items);
        self::assertCount(2, $processor->queries);
        self::assertSame(1, $processor->queries[0]->page->number);
        self::assertSame(2, $processor->queries[0]->page->size);
        self::assertSame('acme', $processor->queries[0]->search);
        self::assertSame(['id'], $processor->queries[0]->fields);
        self::assertNull($processor->queries[0]->cursor);
    }

    public function testSelectedScopeAppendsSelectionFilters(): void
    {
        $processor = $this->createMock(CollectionQueryProcessorInterface::class);
        $processor->expects(self::once())->method('process')->willReturnCallback(
            static function (CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionResultDTO {
                self::assertCount(2, $query->filters);
                self::assertSame('status', $query->filters[0]->field);
                self::assertSame('id', $query->filters[1]->field);
                self::assertSame('in', $query->filters[1]->operator);

                return new CollectionResultDTO([['id' => 1]], 1, 1, $query->page);
            },
        );

        $query = new CollectionQueryDTO(
            new CollectionPageDTO(2, 10),
            filters: [new CollectionFilterDTO('status', 'eq', 'active')],
        );
        $scope = new CollectionDataScopeDTO(CollectionDataScopeDTO::SELECTED, [
            new CollectionFilterDTO('id', 'in', [1, 3]),
        ]);

        self::assertSame([['id' => 1]], iterator_to_array(
            (new CollectionScopedReader($processor))->read(
                new CollectionDefinitionDTO(\stdClass::class, [], maxPageSize: 50),
                $query,
                $scope,
            ),
        ));
    }

    public function testCurrentPagePreservesOriginalQuery(): void
    {
        $processor = $this->createMock(CollectionQueryProcessorInterface::class);
        $query = new CollectionQueryDTO(new CollectionPageDTO(3, 5), cursor: ['id' => 10]);
        $processor->expects(self::once())->method('process')->with(self::anything(), $query)->willReturn(
            new CollectionResultDTO([['id' => 11]], 20, 20, $query->page),
        );

        $items = iterator_to_array((new CollectionScopedReader($processor))->read(
            new CollectionDefinitionDTO(\stdClass::class, []),
            $query,
            new CollectionDataScopeDTO(CollectionDataScopeDTO::CURRENT_PAGE),
        ));

        self::assertSame([['id' => 11]], $items);
    }

    public function testSelectedScopeRequiresSelectionFilters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CollectionDataScopeDTO(CollectionDataScopeDTO::SELECTED);
    }
}

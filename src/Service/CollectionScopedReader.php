<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionDataScopeDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\ServiceInterface\CollectionQueryProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionScopedReaderInterface;

final readonly class CollectionScopedReader implements CollectionScopedReaderInterface
{
    public function __construct(private CollectionQueryProcessorInterface $queryProcessor)
    {
    }

    public function read(
        CollectionDefinitionDTO $definition,
        CollectionQueryDTO $query,
        CollectionDataScopeDTO $scope,
    ): iterable {
        if (CollectionDataScopeDTO::CURRENT_PAGE === $scope->mode) {
            foreach ($this->queryProcessor->process($definition, $query)->items as $item) {
                yield $item;
            }

            return;
        }

        $filters = $query->filters;
        if (CollectionDataScopeDTO::SELECTED === $scope->mode) {
            $filters = [...$filters, ...$scope->selectionFilters];
        }

        $page = 1;
        do {
            $scopedQuery = new CollectionQueryDTO(
                new CollectionPageDTO($page, $definition->maxPageSize),
                $query->search,
                $filters,
                $query->sorts,
                $query->fields,
                null,
            );

            $result = $this->queryProcessor->process($definition, $scopedQuery);
            foreach ($result->items as $item) {
                yield $item;
            }

            ++$page;
            $hasMore = [] !== $result->items
                && count($result->items) === $definition->maxPageSize
                && (($page - 1) * $definition->maxPageSize) < $result->filteredTotal;
        } while ($hasMore);
    }
}

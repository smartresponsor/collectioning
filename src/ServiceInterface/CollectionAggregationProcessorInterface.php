<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionAggregationDTO;
use App\Collectioning\DTO\CollectionAggregationResultDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;

interface CollectionAggregationProcessorInterface
{
    /**
     * @param list<CollectionAggregationDTO> $aggregations
     * @param list<string>                   $groupBy
     */
    public function process(
        CollectionDefinitionDTO $definition,
        CollectionQueryDTO $query,
        array $aggregations,
        array $groupBy = [],
    ): CollectionAggregationResultDTO;
}

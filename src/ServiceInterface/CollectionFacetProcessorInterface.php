<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFacetDTO;
use App\Collectioning\DTO\CollectionFacetResultDTO;
use App\Collectioning\DTO\CollectionQueryDTO;

interface CollectionFacetProcessorInterface
{
    /**
     * @param list<CollectionFacetDTO> $facets
     *
     * @return list<CollectionFacetResultDTO>
     */
    public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query, array $facets): array;
}

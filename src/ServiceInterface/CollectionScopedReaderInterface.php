<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionDataScopeDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;

interface CollectionScopedReaderInterface
{
    /** @return iterable<mixed> */
    public function read(
        CollectionDefinitionDTO $definition,
        CollectionQueryDTO $query,
        CollectionDataScopeDTO $scope,
    ): iterable;
}

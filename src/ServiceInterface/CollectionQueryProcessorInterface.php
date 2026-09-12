<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionResultDTO;

interface CollectionQueryProcessorInterface
{
    public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionResultDTO;
}

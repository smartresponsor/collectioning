<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionDefinitionDTO;

interface CollectionDefinitionFactoryInterface
{
    /** @param class-string $entityClass */
    public function create(string $entityClass): CollectionDefinitionDTO;
}

<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionQueryPlanDTO;

interface CollectionQueryPlannerInterface
{
    public function plan(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionQueryPlanDTO;
}

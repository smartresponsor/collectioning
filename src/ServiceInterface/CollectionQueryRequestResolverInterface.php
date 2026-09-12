<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use Symfony\Component\HttpFoundation\Request;

interface CollectionQueryRequestResolverInterface
{
    public function resolve(Request $request, CollectionDefinitionDTO $definition): CollectionQueryDTO;
}

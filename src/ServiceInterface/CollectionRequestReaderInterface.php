<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionResultDTO;

interface CollectionRequestReaderInterface
{
    /** @param class-string $entityClass */
    public function read(string $entityClass): CollectionResultDTO;
}

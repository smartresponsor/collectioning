<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionSortDTO
{
    public function __construct(
        public string $field,
        public string $direction = 'asc',
    ) {
    }
}

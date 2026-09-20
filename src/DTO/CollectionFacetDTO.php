<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionFacetDTO
{
    public function __construct(
        public string $field,
        public int $limit = 20,
        public bool $includeMissing = false,
        public bool $excludeOwnFilter = true,
    ) {
    }
}

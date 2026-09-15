<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionFacetBucketDTO
{
    public function __construct(
        public int|float|string|bool $value,
        public int $count,
    ) {
    }
}

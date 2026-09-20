<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionFacetResultDTO
{
    /** @param list<CollectionFacetBucketDTO> $buckets */
    public function __construct(
        public string $field,
        public array $buckets,
        public int $missingCount = 0,
    ) {
    }
}

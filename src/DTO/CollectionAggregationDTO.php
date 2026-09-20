<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionAggregationDTO
{
    public function __construct(
        public string $name,
        public string $function,
        public ?string $field = null,
    ) {
    }
}

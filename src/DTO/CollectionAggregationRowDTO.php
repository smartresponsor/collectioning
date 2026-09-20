<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionAggregationRowDTO
{
    /**
     * @param array<string, int|float|string|bool|null> $group
     * @param array<string, int|float|string|null>      $values
     */
    public function __construct(
        public array $group,
        public array $values,
    ) {
    }
}

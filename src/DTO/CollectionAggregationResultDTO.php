<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionAggregationResultDTO
{
    /**
     * @param list<CollectionAggregationRowDTO> $rows
     * @param list<string>                      $groupBy
     */
    public function __construct(
        public array $rows,
        public array $groupBy = [],
        public bool $truncated = false,
    ) {
    }
}

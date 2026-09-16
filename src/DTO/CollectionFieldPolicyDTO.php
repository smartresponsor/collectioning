<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionFieldPolicyDTO
{
    /**
     * @param list<string> $filterOperators
     * @param list<string> $aggregateFunctions
     */
    public function __construct(
        public string $field,
        public bool $searchable = false,
        public bool $filterable = false,
        public bool $sortable = false,
        public bool $projectable = true,
        public array $filterOperators = ['eq'],
        public bool $facetable = false,
        public array $aggregateFunctions = [],
    ) {
    }
}

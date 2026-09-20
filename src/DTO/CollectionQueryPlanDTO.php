<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionQueryPlanDTO
{
    /**
     * @param list<string>              $searchFields
     * @param list<CollectionFilterDTO> $filters
     * @param list<CollectionSortDTO>   $sorts
     * @param list<string>              $projection
     */
    public function __construct(
        public array $searchFields,
        public array $filters,
        public array $sorts,
        public array $projection,
        public bool $cursorApplicable,
    ) {
    }

    public function paginationMode(): string
    {
        return $this->cursorApplicable ? 'cursor' : 'offset';
    }
}

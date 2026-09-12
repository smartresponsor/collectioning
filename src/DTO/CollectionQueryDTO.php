<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionQueryDTO
{
    /**
     * @param list<CollectionFilterDTO> $filters
     * @param list<CollectionSortDTO>   $sorts
     * @param list<string>              $fields
     */
    public function __construct(
        public CollectionPageDTO $page,
        public ?string $search = null,
        public array $filters = [],
        public array $sorts = [],
        public array $fields = [],
    ) {
    }
}

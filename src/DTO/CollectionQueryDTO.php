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

    /**
     * Preserve requested sort precedence while appending deterministic identifier tie-breakers.
     *
     * @param list<string> $identifierFields
     *
     * @return list<CollectionSortDTO>
     */
    public function stableSorts(array $identifierFields): array
    {
        $sorts = $this->sorts;
        $sortedFields = [];

        foreach ($sorts as $sort) {
            $sortedFields[$sort->field] = true;
        }

        foreach ($identifierFields as $identifierField) {
            if (isset($sortedFields[$identifierField])) {
                continue;
            }

            $sorts[] = new CollectionSortDTO($identifierField);
            $sortedFields[$identifierField] = true;
        }

        return $sorts;
    }
}

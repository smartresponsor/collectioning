<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionQueryDTO
{
    /**
     * @param list<CollectionFilterDTO>                 $filters
     * @param list<CollectionSortDTO>                   $sorts
     * @param list<string>                              $fields
     * @param array<string, int|float|string|bool>|null $cursor
     */
    public function __construct(
        public CollectionPageDTO $page,
        public ?string $search = null,
        public array $filters = [],
        public array $sorts = [],
        public array $fields = [],
        public ?array $cursor = null,
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
        $sorts = [];
        $sortedFields = [];

        foreach ($this->sorts as $sort) {
            $direction = strtolower($sort->direction);
            if ('asc' !== $direction && 'desc' !== $direction) {
                continue;
            }
            if (isset($sortedFields[$sort->field])) {
                continue;
            }

            $sorts[] = new CollectionSortDTO($sort->field, $direction);
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

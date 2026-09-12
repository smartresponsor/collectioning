<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionDefinitionDTO
{
    /**
     * @param class-string                   $entityClass
     * @param list<CollectionFieldPolicyDTO> $fields
     */
    public function __construct(
        public string $entityClass,
        public array $fields,
        public int $defaultPageSize = 25,
        public int $maxPageSize = 500,
    ) {
    }
}

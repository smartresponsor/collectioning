<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionFilterDTO
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value,
    ) {
    }
}

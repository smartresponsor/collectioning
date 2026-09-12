<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionChangeDTO
{
    public function __construct(
        public string $collection,
        public string $operation,
        public string|int|null $identifier = null,
        public ?string $version = null,
    ) {
    }
}

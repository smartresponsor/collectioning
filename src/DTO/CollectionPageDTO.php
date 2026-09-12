<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionPageDTO
{
    public function __construct(
        public int $number = 1,
        public int $size = 25,
    ) {
    }

    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }
}

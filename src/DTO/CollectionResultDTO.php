<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionResultDTO
{
    /**
     * @param list<array<string, mixed>|object> $items
     * @param array<string, mixed>              $diagnostics
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $filteredTotal,
        public CollectionPageDTO $page,
        public ?string $nextCursor = null,
        public array $diagnostics = [],
    ) {
    }

    public function pageCount(): int
    {
        if (0 === $this->filteredTotal) {
            return 0;
        }

        return (int) ceil($this->filteredTotal / $this->page->size);
    }
}

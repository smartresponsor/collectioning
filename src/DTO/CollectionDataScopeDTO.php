<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionDataScopeDTO
{
    public const string CURRENT_PAGE = 'currentPage';
    public const string FILTERED = 'filtered';
    public const string SELECTED = 'selected';

    /** @param list<CollectionFilterDTO> $selectionFilters */
    public function __construct(
        public string $mode = self::FILTERED,
        public array $selectionFilters = [],
    ) {
        if (!in_array($mode, [self::CURRENT_PAGE, self::FILTERED, self::SELECTED], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported collection data scope "%s".', $mode));
        }
        if (self::SELECTED === $mode && [] === $selectionFilters) {
            throw new \InvalidArgumentException('Selected collection scope requires selection filters.');
        }
    }
}

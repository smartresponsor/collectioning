<?php

declare(strict_types=1);

namespace App\Collectioning\DTO;

final readonly class CollectionDataScopeDTO
{
    public const string CURRENT_PAGE = 'currentPage';
    public const string FILTERED = 'filtered';
    public const string SELECTED = 'selected';

    private const array SUPPORTED_MODES = [
        self::CURRENT_PAGE => true,
        self::FILTERED => true,
        self::SELECTED => true,
    ];

    /** @param list<CollectionFilterDTO> $selectionFilters */
    public function __construct(
        public string $mode = self::FILTERED,
        public array $selectionFilters = [],
    ) {
        if (!isset(self::SUPPORTED_MODES[$mode])) {
            throw new \InvalidArgumentException(sprintf('Unsupported collection data scope "%s".', $mode));
        }
        if (self::SELECTED === $mode && [] === $selectionFilters) {
            throw new \InvalidArgumentException('Selected collection scope requires selection filters.');
        }
    }
}

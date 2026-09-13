<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionResultDTO;
use PHPUnit\Framework\TestCase;

final class CollectionResultDTOTest extends TestCase
{
    public function testPageCountRoundsFilteredResultsUpToCompletePages(): void
    {
        $result = new CollectionResultDTO([], 100, 51, new CollectionPageDTO(size: 25));

        self::assertSame(3, $result->pageCount());
        self::assertSame([], $result->diagnostics);
    }

    public function testPageCountIsZeroForEmptyFilteredResult(): void
    {
        $result = new CollectionResultDTO([], 100, 0, new CollectionPageDTO(size: 25));

        self::assertSame(0, $result->pageCount());
        self::assertSame([], $result->diagnostics);
    }
}

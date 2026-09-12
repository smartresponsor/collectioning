<?php

declare(strict_types=1);

namespace App\Collectioning\ServiceInterface;

use App\Collectioning\DTO\CollectionChangeDTO;

interface CollectionRealtimePublisherInterface
{
    public function publish(CollectionChangeDTO $change): void;
}

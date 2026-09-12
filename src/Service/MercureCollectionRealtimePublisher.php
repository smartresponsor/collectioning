<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionChangeDTO;
use App\Collectioning\ServiceInterface\CollectionRealtimePublisherInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class MercureCollectionRealtimePublisher implements CollectionRealtimePublisherInterface
{
    public function __construct(
        private HubInterface $hub,
        private string $topicPrefix = 'collection',
    ) {
    }

    public function publish(CollectionChangeDTO $change): void
    {
        $topic = sprintf('%s/%s', trim($this->topicPrefix, '/'), trim($change->collection, '/'));
        $payload = json_encode([
            'collection' => $change->collection,
            'operation' => $change->operation,
            'identifier' => $change->identifier,
            'version' => $change->version,
        ], JSON_THROW_ON_ERROR);

        $this->hub->publish(new Update($topic, $payload));
    }
}

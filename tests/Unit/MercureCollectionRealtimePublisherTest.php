<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionChangeDTO;
use App\Collectioning\Service\CollectionMercureRealtimePublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercureCollectionRealtimePublisherTest extends TestCase
{
    public function testPublishUsesCollectionTopicAndStructuredChangePayload(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                self::assertSame(['collection/order'], $update->getTopics());
                self::assertJsonStringEqualsJsonString(
                    '{"collection":"order","operation":"update","identifier":42,"version":"7"}',
                    $update->getData(),
                );

                return true;
            }));

        $publisher = new CollectionMercureRealtimePublisher($hub);

        $publisher->publish(new CollectionChangeDTO(
            collection: 'order',
            operation: 'update',
            identifier: 42,
            version: '7',
        ));
    }

    public function testPublishSupportsCustomTrimmedTopicPrefix(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                self::assertSame(['tenant/orders'], $update->getTopics());

                return true;
            }));

        $publisher = new CollectionMercureRealtimePublisher($hub, '/tenant/');
        $publisher->publish(new CollectionChangeDTO(
            collection: '/orders/',
            operation: 'create',
            identifier: 'abc',
        ));
    }
}

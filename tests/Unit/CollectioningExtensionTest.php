<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DependencyInjection\CollectioningExtension;
use App\Collectioning\Service\CollectionQueryPlanner;
use App\Collectioning\Service\DoctrineCollectionQueryProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CollectioningExtensionTest extends TestCase
{
    public function testAliasIsCanonicalComponentName(): void
    {
        self::assertSame('collectioning', (new CollectioningExtension())->getAlias());
    }

    public function testLoadRegistersCollectioningServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new CollectioningExtension();

        $extension->load([], $container);

        self::assertTrue($container->hasDefinition(CollectionQueryPlanner::class));
        self::assertTrue($container->hasDefinition(DoctrineCollectionQueryProcessor::class));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionQueryPlannerInterface'));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionQueryProcessorInterface'));
    }
}

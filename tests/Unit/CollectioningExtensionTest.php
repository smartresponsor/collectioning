<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DependencyInjection\CollectioningExtension;
use App\Collectioning\Repository\CollectionDoctrineAggregationRepository;
use App\Collectioning\Repository\CollectionDoctrineFacetRepository;
use App\Collectioning\Repository\CollectionDoctrineQueryRepository;
use App\Collectioning\Service\CollectionQueryPlanner;
use App\Collectioning\Service\CollectionScopedReader;
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
        self::assertTrue($container->hasDefinition(CollectionScopedReader::class));
        self::assertTrue($container->hasDefinition(CollectionDoctrineAggregationRepository::class));
        self::assertTrue($container->hasDefinition(CollectionDoctrineFacetRepository::class));
        self::assertTrue($container->hasDefinition(CollectionDoctrineQueryRepository::class));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionAggregationProcessorInterface'));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionFacetProcessorInterface'));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionQueryPlannerInterface'));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionQueryProcessorInterface'));
        self::assertTrue($container->hasAlias('App\\Collectioning\\ServiceInterface\\CollectionScopedReaderInterface'));
    }
}

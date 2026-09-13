<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\Factory\DoctrineCollectionDefinitionFactory;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCollectionDefinitionFactoryTest extends TestCase
{
    public function testCreateRejectsEntityWithoutDoctrineOrmManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())
            ->method('getManagerForClass')
            ->with(\stdClass::class)
            ->willReturn(null);

        $factory = new DoctrineCollectionDefinitionFactory($registry);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No Doctrine ORM manager for collection entity "stdClass".');

        $factory->create(\stdClass::class);
    }
}

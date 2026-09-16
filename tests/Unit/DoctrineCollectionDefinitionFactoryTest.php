<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\Factory\DoctrineCollectionDefinitionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCollectionDefinitionFactoryTest extends TestCase
{
    public function testCreateBuildsFieldPoliciesAndScalarIdentifierTieBreakers(): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getFieldNames')->willReturn(['id', 'name', 'payload']);
        $metadata->method('getTypeOfField')->willReturnCallback(static fn (string $field): string => match ($field) {
            'id' => 'integer',
            'name' => 'string',
            'payload' => 'blob',
            default => throw new \LogicException(sprintf('Unexpected fixture field "%s".', $field)),
        });
        $metadata->method('getIdentifierFieldNames')->willReturn(['id', 'payload', 'relation']);
        $metadata->method('hasField')->willReturnCallback(static fn (string $field): bool => in_array($field, ['id', 'name', 'payload'], true));

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())
            ->method('getClassMetadata')
            ->with(\stdClass::class)
            ->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())
            ->method('getManagerForClass')
            ->with(\stdClass::class)
            ->willReturn($manager);

        $definition = (new DoctrineCollectionDefinitionFactory($registry))->create(\stdClass::class);

        self::assertSame(['id'], $definition->identifierFields);
        self::assertCount(3, $definition->fields);
        self::assertFalse($definition->fields[0]->searchable);
        self::assertTrue($definition->fields[0]->filterable);
        self::assertTrue($definition->fields[0]->sortable);
        self::assertTrue($definition->fields[0]->facetable);
        self::assertSame(['count', 'min', 'max', 'sum', 'avg'], $definition->fields[0]->aggregateFunctions);
        self::assertSame(['eq', 'neq', 'in', 'notIn', 'lt', 'lte', 'gt', 'gte'], $definition->fields[0]->filterOperators);
        self::assertTrue($definition->fields[1]->searchable);
        self::assertTrue($definition->fields[1]->facetable);
        self::assertSame(['count', 'min', 'max'], $definition->fields[1]->aggregateFunctions);
        self::assertSame(['eq', 'neq', 'in', 'notIn'], $definition->fields[1]->filterOperators);
        self::assertFalse($definition->fields[2]->filterable);
        self::assertFalse($definition->fields[2]->sortable);
        self::assertFalse($definition->fields[2]->facetable);
        self::assertSame(['count'], $definition->fields[2]->aggregateFunctions);
    }

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

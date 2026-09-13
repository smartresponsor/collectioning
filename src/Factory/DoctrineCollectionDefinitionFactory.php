<?php

declare(strict_types=1);

namespace App\Collectioning\Factory;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\ServiceInterface\CollectionDefinitionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineCollectionDefinitionFactory implements CollectionDefinitionFactoryInterface
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function create(string $entityClass): CollectionDefinitionDTO
    {
        $manager = $this->managerRegistry->getManagerForClass($entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('No Doctrine ORM manager for collection entity "%s".', $entityClass));
        }

        $metadata = $manager->getClassMetadata($entityClass);
        $fields = [];
        foreach ($metadata->getFieldNames() as $field) {
            $type = $metadata->getTypeOfField($field);
            $searchable = in_array($type, ['string', 'text', 'ascii_string'], true);
            $filterable = in_array($type, ['string', 'text', 'ascii_string', 'integer', 'smallint', 'bigint', 'decimal', 'float', 'boolean', 'guid', 'uuid', 'ulid'], true);
            $sortable = !in_array($type, ['blob', 'binary'], true);
            $filterOperators = ['eq', 'neq'];
            if (in_array($type, ['integer', 'smallint', 'bigint', 'decimal', 'float'], true)) {
                $filterOperators = [...$filterOperators, 'lt', 'lte', 'gt', 'gte'];
            }

            $fields[] = new CollectionFieldPolicyDTO($field, $searchable, $filterable, $sortable, true, $filterOperators);
        }

        $identifierFields = array_values(array_filter(
            $metadata->getIdentifierFieldNames(),
            static fn (string $field): bool => $metadata->hasField($field)
                && !in_array($metadata->getTypeOfField($field), ['blob', 'binary'], true),
        ));

        return new CollectionDefinitionDTO($entityClass, $fields, identifierFields: $identifierFields);
    }
}

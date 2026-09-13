<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionResultDTO;
use App\Collectioning\ServiceInterface\CollectionQueryProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineCollectionQueryProcessor implements CollectionQueryProcessorInterface
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionResultDTO
    {
        $manager = $this->managerRegistry->getManagerForClass($definition->entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('No Doctrine ORM manager for collection entity "%s".', $definition->entityClass));
        }

        $policy = [];
        foreach ($definition->fields as $field) {
            $policy[$field->field] = $field;
        }

        $base = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
        $total = (int) (clone $base)->select('COUNT(entity)')->getQuery()->getSingleScalarResult();

        $filtered = clone $base;
        $parameter = 0;
        if (null !== $query->search) {
            $or = $filtered->expr()->orX();
            foreach ($policy as $field => $fieldPolicy) {
                if (!$fieldPolicy->searchable) {
                    continue;
                }
                $name = 'search_'.$parameter++;
                $or->add(sprintf('LOWER(entity.%s) LIKE :%s', $field, $name));
                $filtered->setParameter($name, '%'.mb_strtolower($query->search).'%');
            }
            if ($or->count() > 0) {
                $filtered->andWhere($or);
            }
        }

        foreach ($query->filters as $filter) {
            if (!isset($policy[$filter->field]) || !$policy[$filter->field]->filterable) {
                continue;
            }
            $name = 'filter_'.$parameter++;
            $filtered->andWhere(sprintf('entity.%s = :%s', $filter->field, $name));
            $filtered->setParameter($name, $filter->value);
        }

        $filteredTotal = (int) (clone $filtered)
            ->select('COUNT(entity)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        foreach ($query->stableSorts($definition->identifierFields) as $sort) {
            if (isset($policy[$sort->field]) && $policy[$sort->field]->sortable) {
                $filtered->addOrderBy('entity.'.$sort->field, 'desc' === strtolower($sort->direction) ? 'DESC' : 'ASC');
            }
        }

        if ([] !== $query->fields) {
            $select = [];
            foreach ($query->fields as $field) {
                if (isset($policy[$field]) && $policy[$field]->projectable) {
                    $select[] = sprintf('entity.%s AS %s', $field, $field);
                }
            }
            $filtered->select([] !== $select ? $select : 'entity');
        } else {
            $filtered->select('entity');
        }

        $items = $filtered
            ->setFirstResult($query->page->offset())
            ->setMaxResults($query->page->size)
            ->getQuery()
            ->getResult();

        return new CollectionResultDTO(array_values($items), $total, $filteredTotal, $query->page);
    }
}

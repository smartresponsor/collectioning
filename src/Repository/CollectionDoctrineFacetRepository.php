<?php

declare(strict_types=1);

namespace App\Collectioning\Repository;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFacetBucketDTO;
use App\Collectioning\DTO\CollectionFacetDTO;
use App\Collectioning\DTO\CollectionFacetResultDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\ServiceInterface\CollectionFacetProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final readonly class CollectionDoctrineFacetRepository implements CollectionFacetProcessorInterface
{
    private const MAX_BUCKETS = 100;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private CollectionQueryPlannerInterface $queryPlanner,
    ) {
    }

    public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query, array $facets): array
    {
        $manager = $this->manager($definition);
        $policies = $this->policies($definition);
        $plan = $this->queryPlanner->plan($definition, $query);
        $results = [];

        foreach ($facets as $facet) {
            $policy = $policies[$facet->field] ?? null;
            if (null === $policy || !$policy->facetable) {
                continue;
            }

            $results[] = $this->processFacet($manager, $definition, $query, $plan->searchFields, $plan->filters, $facet);
        }

        return $results;
    }

    private function manager(CollectionDefinitionDTO $definition): EntityManagerInterface
    {
        $manager = $this->managerRegistry->getManagerForClass($definition->entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('No Doctrine ORM manager for collection entity "%s".', $definition->entityClass));
        }

        return $manager;
    }

    /** @return array<string, CollectionFieldPolicyDTO> */
    private function policies(CollectionDefinitionDTO $definition): array
    {
        $policies = [];
        foreach ($definition->fields as $policy) {
            $policies[$policy->field] = $policy;
        }

        return $policies;
    }

    /**
     * @param list<string>              $searchFields
     * @param list<CollectionFilterDTO> $filters
     */
    private function processFacet(
        EntityManagerInterface $manager,
        CollectionDefinitionDTO $definition,
        CollectionQueryDTO $query,
        array $searchFields,
        array $filters,
        CollectionFacetDTO $facet,
    ): CollectionFacetResultDTO {
        $builder = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
        $this->applySearch($builder, $query, $searchFields);
        $this->applyFilters($builder, $filters, $facet);

        $rows = array_values($builder
            ->select(sprintf('entity.%s AS value', $facet->field), 'COUNT(entity) AS bucketCount')
            ->andWhere(sprintf('entity.%s IS NOT NULL', $facet->field))
            ->groupBy('entity.'.$facet->field)
            ->orderBy('bucketCount', 'DESC')
            ->addOrderBy('entity.'.$facet->field, 'ASC')
            ->setMaxResults(max(1, min(self::MAX_BUCKETS, $facet->limit)))
            ->getQuery()
            ->getArrayResult());

        return new CollectionFacetResultDTO(
            $facet->field,
            $this->buckets($rows),
            $facet->includeMissing
                ? $this->missingCount($manager, $definition, $query, $searchFields, $filters, $facet)
                : 0,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<CollectionFacetBucketDTO>
     */
    private function buckets(array $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $value = $row['value'] ?? null;
            if (!(is_int($value) || is_float($value) || is_string($value) || is_bool($value))) {
                continue;
            }

            $buckets[] = new CollectionFacetBucketDTO($value, (int) ($row['bucketCount'] ?? 0));
        }

        return $buckets;
    }

    /**
     * @param list<string>              $searchFields
     * @param list<CollectionFilterDTO> $filters
     */
    private function missingCount(
        EntityManagerInterface $manager,
        CollectionDefinitionDTO $definition,
        CollectionQueryDTO $query,
        array $searchFields,
        array $filters,
        CollectionFacetDTO $facet,
    ): int {
        $builder = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
        $this->applySearch($builder, $query, $searchFields);
        $this->applyFilters($builder, $filters, $facet);

        return (int) $builder
            ->select('COUNT(entity)')
            ->andWhere(sprintf('entity.%s IS NULL', $facet->field))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<string> $searchFields */
    private function applySearch(QueryBuilder $builder, CollectionQueryDTO $query, array $searchFields): void
    {
        if (null === $query->search || [] === $searchFields) {
            return;
        }

        $or = $builder->expr()->orX();
        foreach ($searchFields as $index => $field) {
            $name = 'facet_search_'.$index;
            $or->add(sprintf('LOWER(entity.%s) LIKE :%s', $field, $name));
            $builder->setParameter($name, '%'.mb_strtolower($query->search).'%');
        }

        if ($or->count() > 0) {
            $builder->andWhere($or);
        }
    }

    /** @param list<CollectionFilterDTO> $filters */
    private function applyFilters(QueryBuilder $builder, array $filters, CollectionFacetDTO $facet): void
    {
        $parameter = 0;
        foreach ($filters as $filter) {
            if ($facet->excludeOwnFilter && $filter->field === $facet->field) {
                continue;
            }

            $operator = $this->operator($filter->operator);
            if (null === $operator) {
                continue;
            }

            $name = 'facet_filter_'.$parameter++;
            $builder->andWhere(sprintf(
                in_array($filter->operator, ['in', 'notIn'], true) ? 'entity.%s %s (:%s)' : 'entity.%s %s :%s',
                $filter->field,
                $operator,
                $name,
            ));
            $builder->setParameter($name, $filter->value);
        }
    }

    private function operator(string $operator): ?string
    {
        return match ($operator) {
            'eq' => '=',
            'neq' => '<>',
            'lt' => '<',
            'lte' => '<=',
            'gt' => '>',
            'gte' => '>=',
            'in' => 'IN',
            'notIn' => 'NOT IN',
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFacetBucketDTO;
use App\Collectioning\DTO\CollectionFacetDTO;
use App\Collectioning\DTO\CollectionFacetResultDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\ServiceInterface\CollectionFacetProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineCollectionFacetProcessor implements CollectionFacetProcessorInterface
{
    private const MAX_BUCKETS = 100;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private CollectionQueryPlannerInterface $queryPlanner,
    ) {
    }

    public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query, array $facets): array
    {
        $manager = $this->managerRegistry->getManagerForClass($definition->entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('No Doctrine ORM manager for collection entity "%s".', $definition->entityClass));
        }

        $policies = [];
        foreach ($definition->fields as $policy) {
            $policies[$policy->field] = $policy;
        }

        $plan = $this->queryPlanner->plan($definition, $query);
        $results = [];

        foreach ($facets as $facet) {
            $policy = $policies[$facet->field] ?? null;
            if (null === $policy || !$policy->facetable) {
                continue;
            }

            $builder = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
            $this->applySearch($builder, $query, $plan->searchFields);
            $this->applyFilters($builder, $plan->filters, $facet);

            $rows = $builder
                ->select(sprintf('entity.%s AS value', $facet->field), 'COUNT(entity) AS bucketCount')
                ->andWhere(sprintf('entity.%s IS NOT NULL', $facet->field))
                ->groupBy('entity.'.$facet->field)
                ->orderBy('bucketCount', 'DESC')
                ->addOrderBy('entity.'.$facet->field, 'ASC')
                ->setMaxResults(max(1, min(self::MAX_BUCKETS, $facet->limit)))
                ->getQuery()
                ->getArrayResult();

            $buckets = [];
            foreach ($rows as $row) {
                $value = $row['value'] ?? null;
                if (!(is_int($value) || is_float($value) || is_string($value) || is_bool($value))) {
                    continue;
                }

                $buckets[] = new CollectionFacetBucketDTO($value, (int) ($row['bucketCount'] ?? 0));
            }

            $missingCount = 0;
            if ($facet->includeMissing) {
                $missing = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
                $this->applySearch($missing, $query, $plan->searchFields);
                $this->applyFilters($missing, $plan->filters, $facet);
                $missingCount = (int) $missing
                    ->select('COUNT(entity)')
                    ->andWhere(sprintf('entity.%s IS NULL', $facet->field))
                    ->getQuery()
                    ->getSingleScalarResult();
            }

            $results[] = new CollectionFacetResultDTO($facet->field, $buckets, $missingCount);
        }

        return $results;
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

            $operator = match ($filter->operator) {
                'eq' => '=',
                'neq' => '<>',
                'lt' => '<',
                'lte' => '<=',
                'gt' => '>',
                'gte' => '>=',
                default => null,
            };
            if (null === $operator) {
                continue;
            }

            $name = 'facet_filter_'.$parameter++;
            $builder->andWhere(sprintf('entity.%s %s :%s', $filter->field, $operator, $name));
            $builder->setParameter($name, $filter->value);
        }
    }
}

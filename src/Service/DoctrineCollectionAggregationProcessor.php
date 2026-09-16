<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionAggregationDTO;
use App\Collectioning\DTO\CollectionAggregationResultDTO;
use App\Collectioning\DTO\CollectionAggregationRowDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\ServiceInterface\CollectionAggregationProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineCollectionAggregationProcessor implements CollectionAggregationProcessorInterface
{
    private const MAX_GROUP_ROWS = 500;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private CollectionQueryPlannerInterface $queryPlanner,
    ) {
    }

    public function process(
        CollectionDefinitionDTO $definition,
        CollectionQueryDTO $query,
        array $aggregations,
        array $groupBy = [],
    ): CollectionAggregationResultDTO {
        $manager = $this->managerRegistry->getManagerForClass($definition->entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('No Doctrine ORM manager for collection entity "%s".', $definition->entityClass));
        }

        $policies = [];
        foreach ($definition->fields as $policy) {
            $policies[$policy->field] = $policy;
        }

        $effectiveGroupBy = [];
        foreach ($groupBy as $field) {
            $policy = $policies[$field] ?? null;
            if (null === $policy || !$policy->facetable || in_array($field, $effectiveGroupBy, true)) {
                continue;
            }
            $effectiveGroupBy[] = $field;
        }

        $effectiveAggregations = [];
        foreach ($aggregations as $aggregation) {
            $function = strtolower($aggregation->function);
            if (!in_array($function, ['count', 'sum', 'avg', 'min', 'max'], true)) {
                continue;
            }
            if ('count' === $function && null === $aggregation->field) {
                $effectiveAggregations[] = new CollectionAggregationDTO($aggregation->name, $function);
                continue;
            }
            if (null === $aggregation->field) {
                continue;
            }
            $policy = $policies[$aggregation->field] ?? null;
            if (null === $policy || !in_array($function, $policy->aggregateFunctions, true)) {
                continue;
            }
            $effectiveAggregations[] = new CollectionAggregationDTO($aggregation->name, $function, $aggregation->field);
        }

        if ([] === $effectiveAggregations) {
            return new CollectionAggregationResultDTO([], $effectiveGroupBy);
        }

        $plan = $this->queryPlanner->plan($definition, $query);
        $builder = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
        $this->applySearch($builder, $query, $plan->searchFields);
        $this->applyFilters($builder, $plan->filters);

        $select = [];
        foreach ($effectiveGroupBy as $index => $field) {
            $alias = 'group_'.$index;
            $select[] = sprintf('entity.%s AS %s', $field, $alias);
            $builder->addGroupBy('entity.'.$field);
        }

        foreach ($effectiveAggregations as $index => $aggregation) {
            $alias = 'aggregation_'.$index;
            $expression = match ($aggregation->function) {
                'count' => null === $aggregation->field ? 'COUNT(entity)' : sprintf('COUNT(entity.%s)', $aggregation->field),
                'sum' => sprintf('SUM(entity.%s)', $aggregation->field),
                'avg' => sprintf('AVG(entity.%s)', $aggregation->field),
                'min' => sprintf('MIN(entity.%s)', $aggregation->field),
                'max' => sprintf('MAX(entity.%s)', $aggregation->field),
                default => throw new \LogicException(sprintf('Unsupported aggregation function "%s".', $aggregation->function)),
            };
            $select[] = sprintf('%s AS %s', $expression, $alias);
        }

        $builder->select(...$select);
        foreach ($effectiveGroupBy as $field) {
            $builder->addOrderBy('entity.'.$field, 'ASC');
        }
        if ([] !== $effectiveGroupBy) {
            $builder->setMaxResults(self::MAX_GROUP_ROWS + 1);
        }

        $rawRows = $builder->getQuery()->getArrayResult();
        $truncated = [] !== $effectiveGroupBy && count($rawRows) > self::MAX_GROUP_ROWS;
        if ($truncated) {
            array_pop($rawRows);
        }

        $rows = [];
        foreach ($rawRows as $rawRow) {
            $group = [];
            foreach ($effectiveGroupBy as $index => $field) {
                $value = $rawRow['group_'.$index] ?? null;
                if (null === $value || is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
                    $group[$field] = $value;
                }
            }

            $values = [];
            foreach ($effectiveAggregations as $index => $aggregation) {
                $value = $rawRow['aggregation_'.$index] ?? null;
                if (null === $value || is_int($value) || is_float($value) || is_string($value)) {
                    $values[$aggregation->name] = $value;
                }
            }
            $rows[] = new CollectionAggregationRowDTO($group, $values);
        }

        return new CollectionAggregationResultDTO($rows, $effectiveGroupBy, $truncated);
    }

    /** @param list<string> $searchFields */
    private function applySearch(QueryBuilder $builder, CollectionQueryDTO $query, array $searchFields): void
    {
        if (null === $query->search || [] === $searchFields) {
            return;
        }

        $or = $builder->expr()->orX();
        foreach ($searchFields as $index => $field) {
            $name = 'aggregation_search_'.$index;
            $or->add(sprintf('LOWER(entity.%s) LIKE :%s', $field, $name));
            $builder->setParameter($name, '%'.mb_strtolower($query->search).'%');
        }
        if ($or->count() > 0) {
            $builder->andWhere($or);
        }
    }

    /** @param list<CollectionFilterDTO> $filters */
    private function applyFilters(QueryBuilder $builder, array $filters): void
    {
        foreach ($filters as $index => $filter) {
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
            $name = 'aggregation_filter_'.$index;
            $builder->andWhere(sprintf('entity.%s %s :%s', $filter->field, $operator, $name));
            $builder->setParameter($name, $filter->value);
        }
    }
}

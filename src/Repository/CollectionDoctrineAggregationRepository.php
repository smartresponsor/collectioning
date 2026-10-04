<?php

declare(strict_types=1);

namespace App\Collectioning\Repository;

use App\Collectioning\DTO\CollectionAggregationDTO;
use App\Collectioning\DTO\CollectionAggregationResultDTO;
use App\Collectioning\DTO\CollectionAggregationRowDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\ServiceInterface\CollectionAggregationProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final readonly class CollectionDoctrineAggregationRepository implements CollectionAggregationProcessorInterface
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
        $manager = $this->manager($definition);
        $policies = $this->policies($definition);
        $effectiveGroupBy = $this->groupBy($groupBy, $policies);
        $effectiveAggregations = $this->aggregations($aggregations, $policies);

        if ([] === $effectiveAggregations) {
            return new CollectionAggregationResultDTO([], $effectiveGroupBy);
        }

        $plan = $this->queryPlanner->plan($definition, $query);
        $builder = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
        $this->applySearch($builder, $query, $plan->searchFields);
        $this->applyFilters($builder, $plan->filters);
        $this->applySelect($builder, $effectiveGroupBy, $effectiveAggregations);

        $rawRows = array_values($builder->getQuery()->getArrayResult());
        $truncated = [] !== $effectiveGroupBy && count($rawRows) > self::MAX_GROUP_ROWS;
        if ($truncated) {
            array_pop($rawRows);
        }

        return new CollectionAggregationResultDTO(
            $this->rows($rawRows, $effectiveGroupBy, $effectiveAggregations),
            $effectiveGroupBy,
            $truncated,
        );
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
     * @param list<string>                            $groupBy
     * @param array<string, CollectionFieldPolicyDTO> $policies
     *
     * @return list<string>
     */
    private function groupBy(array $groupBy, array $policies): array
    {
        $effective = [];
        foreach ($groupBy as $field) {
            $policy = $policies[$field] ?? null;
            if (null === $policy || !$policy->facetable || in_array($field, $effective, true)) {
                continue;
            }
            $effective[] = $field;
        }

        return $effective;
    }

    /**
     * @param list<CollectionAggregationDTO>          $aggregations
     * @param array<string, CollectionFieldPolicyDTO> $policies
     *
     * @return list<CollectionAggregationDTO>
     */
    private function aggregations(array $aggregations, array $policies): array
    {
        $effective = [];
        foreach ($aggregations as $aggregation) {
            $normalized = $this->aggregation($aggregation, $policies);
            if (null !== $normalized) {
                $effective[] = $normalized;
            }
        }

        return $effective;
    }

    /** @param array<string, CollectionFieldPolicyDTO> $policies */
    private function aggregation(CollectionAggregationDTO $aggregation, array $policies): ?CollectionAggregationDTO
    {
        $function = strtolower($aggregation->function);
        if (!in_array($function, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            return null;
        }
        if ('count' === $function && null === $aggregation->field) {
            return new CollectionAggregationDTO($aggregation->name, $function);
        }
        if (null === $aggregation->field) {
            return null;
        }

        $policy = $policies[$aggregation->field] ?? null;
        if (null === $policy || !in_array($function, $policy->aggregateFunctions, true)) {
            return null;
        }

        return new CollectionAggregationDTO($aggregation->name, $function, $aggregation->field);
    }

    /**
     * @param list<string>                   $groupBy
     * @param list<CollectionAggregationDTO> $aggregations
     */
    private function applySelect(QueryBuilder $builder, array $groupBy, array $aggregations): void
    {
        $select = [];
        foreach ($groupBy as $index => $field) {
            $select[] = sprintf('entity.%s AS group_%d', $field, $index);
            $builder->addGroupBy('entity.'.$field)->addOrderBy('entity.'.$field, 'ASC');
        }

        foreach ($aggregations as $index => $aggregation) {
            $select[] = sprintf('%s AS aggregation_%d', $this->aggregationExpression($aggregation), $index);
        }

        $builder->select(...$select);
        if ([] !== $groupBy) {
            $builder->setMaxResults(self::MAX_GROUP_ROWS + 1);
        }
    }

    private function aggregationExpression(CollectionAggregationDTO $aggregation): string
    {
        return match ($aggregation->function) {
            'count' => null === $aggregation->field ? 'COUNT(entity)' : sprintf('COUNT(entity.%s)', $aggregation->field),
            'sum' => sprintf('SUM(entity.%s)', $aggregation->field),
            'avg' => sprintf('AVG(entity.%s)', $aggregation->field),
            'min' => sprintf('MIN(entity.%s)', $aggregation->field),
            'max' => sprintf('MAX(entity.%s)', $aggregation->field),
            default => throw new \LogicException(sprintf('Unsupported aggregation function "%s".', $aggregation->function)),
        };
    }

    /**
     * @param list<array<string, mixed>>     $rawRows
     * @param list<string>                   $groupBy
     * @param list<CollectionAggregationDTO> $aggregations
     *
     * @return list<CollectionAggregationRowDTO>
     */
    private function rows(array $rawRows, array $groupBy, array $aggregations): array
    {
        $rows = [];
        foreach ($rawRows as $rawRow) {
            $rows[] = new CollectionAggregationRowDTO(
                $this->groupValues($rawRow, $groupBy),
                $this->aggregationValues($rawRow, $aggregations),
            );
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $rawRow
     * @param list<string>         $groupBy
     *
     * @return array<string, bool|float|int|string|null>
     */
    private function groupValues(array $rawRow, array $groupBy): array
    {
        $values = [];
        foreach ($groupBy as $index => $field) {
            $value = $rawRow['group_'.$index] ?? null;
            if (null === $value || is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed>           $rawRow
     * @param list<CollectionAggregationDTO> $aggregations
     *
     * @return array<string, float|int|string|null>
     */
    private function aggregationValues(array $rawRow, array $aggregations): array
    {
        $values = [];
        foreach ($aggregations as $index => $aggregation) {
            $value = $rawRow['aggregation_'.$index] ?? null;
            if (null === $value || is_int($value) || is_float($value) || is_string($value)) {
                $values[$aggregation->name] = $value;
            }
        }

        return $values;
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
            $operator = $this->operator($filter->operator);
            if (null === $operator) {
                continue;
            }

            $name = 'aggregation_filter_'.$index;
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

<?php

declare(strict_types=1);

namespace App\Collectioning\Repository;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionQueryPlanDTO;
use App\Collectioning\DTO\CollectionResultDTO;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;
use App\Collectioning\ServiceInterface\CollectionQueryProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final readonly class CollectionDoctrineQueryRepository implements CollectionQueryProcessorInterface
{
    private const int EXECUTED_QUERY_COUNT = 3;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private CollectionQueryPlannerInterface $queryPlanner,
    ) {
    }

    public function process(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionResultDTO
    {
        $startedAt = hrtime(true);
        $manager = $this->manager($definition);
        $plan = $this->queryPlanner->plan($definition, $query);
        $base = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');

        $total = (int) (clone $base)->select('COUNT(entity)')->getQuery()->getSingleScalarResult();
        [$filtered, $effectiveFilters] = $this->filteredBuilder(clone $base, $query, $plan);
        $filteredTotal = (int) (clone $filtered)
            ->select('COUNT(entity)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $cursorApplied = $this->applyCursor($filtered, $query, $plan);
        $this->applySorts($filtered, $plan);
        [$projectionCursorAliases, $projectionEnabled] = $this->applyProjection($filtered, $query, $plan);

        $items = $filtered
            ->setFirstResult($cursorApplied ? 0 : $query->page->offset())
            ->setMaxResults($query->page->size + 1)
            ->getQuery()
            ->getResult();

        $hasNext = count($items) > $query->page->size;
        if ($hasNext) {
            array_pop($items);
        }

        $nextCursor = $this->nextCursor(
            $manager,
            $definition,
            $items,
            $plan,
            $projectionCursorAliases,
            $hasNext,
        );
        if ($projectionEnabled && [] !== $projectionCursorAliases) {
            $this->stripProjectionCursorAliases($items, $projectionCursorAliases);
        }

        return new CollectionResultDTO(
            array_values($items),
            $total,
            $filteredTotal,
            $query->page,
            $nextCursor,
            $this->diagnostics($startedAt, $items, $total, $filteredTotal, $plan, $effectiveFilters, $cursorApplied),
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

    /** @return array{QueryBuilder, list<array{field: string, operator: string}>} */
    private function filteredBuilder(QueryBuilder $builder, CollectionQueryDTO $query, CollectionQueryPlanDTO $plan): array
    {
        $parameter = $this->applySearch($builder, $query, $plan->searchFields);
        $effectiveFilters = $this->applyFilters($builder, $plan, $parameter);

        return [$builder, $effectiveFilters];
    }

    /** @param list<string> $searchFields */
    private function applySearch(QueryBuilder $builder, CollectionQueryDTO $query, array $searchFields): int
    {
        if (null === $query->search || [] === $searchFields) {
            return 0;
        }

        $or = $builder->expr()->orX();
        $parameter = 0;
        foreach ($searchFields as $field) {
            $name = 'search_'.$parameter++;
            $or->add(sprintf('LOWER(entity.%s) LIKE :%s', $field, $name));
            $builder->setParameter($name, '%'.mb_strtolower($query->search).'%');
        }
        if ($or->count() > 0) {
            $builder->andWhere($or);
        }

        return $parameter;
    }

    /** @return list<array{field: string, operator: string}> */
    private function applyFilters(QueryBuilder $builder, CollectionQueryPlanDTO $plan, int $parameter): array
    {
        $effectiveFilters = [];
        foreach ($plan->filters as $filter) {
            $operator = $this->operator($filter->operator);
            if (null === $operator) {
                continue;
            }

            $effectiveFilters[] = ['field' => $filter->field, 'operator' => $filter->operator];
            $name = 'filter_'.$parameter++;
            $builder->andWhere(sprintf(
                in_array($filter->operator, ['in', 'notIn'], true) ? 'entity.%s %s (:%s)' : 'entity.%s %s :%s',
                $filter->field,
                $operator,
                $name,
            ));
            $builder->setParameter($name, $filter->value);
        }

        return $effectiveFilters;
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

    private function applyCursor(QueryBuilder $builder, CollectionQueryDTO $query, CollectionQueryPlanDTO $plan): bool
    {
        if (!$plan->cursorApplicable || null === $query->cursor) {
            return false;
        }

        $cursorOr = $builder->expr()->orX();
        foreach ($plan->sorts as $index => $sort) {
            $and = $builder->expr()->andX();
            $this->applyCursorEqualities($builder, $and, $plan, $query->cursor, $index);

            $name = 'cursor_'.$index;
            $and->add(sprintf(
                'entity.%s %s :%s',
                $sort->field,
                'desc' === strtolower($sort->direction) ? '<' : '>',
                $name,
            ));
            $builder->setParameter($name, $query->cursor[$sort->field]);
            $cursorOr->add($and);
        }

        if (0 === $cursorOr->count()) {
            return false;
        }

        $builder->andWhere($cursorOr);

        return true;
    }

    /** @param array<string, bool|float|int|string> $cursor */
    private function applyCursorEqualities(
        QueryBuilder $builder,
        Andx $and,
        CollectionQueryPlanDTO $plan,
        array $cursor,
        int $index,
    ): void {
        for ($previous = 0; $previous < $index; ++$previous) {
            $previousSort = $plan->sorts[$previous];
            $name = 'cursor_'.$index.'_'.$previous;
            $and->add(sprintf('entity.%s = :%s', $previousSort->field, $name));
            $builder->setParameter($name, $cursor[$previousSort->field]);
        }
    }

    private function applySorts(QueryBuilder $builder, CollectionQueryPlanDTO $plan): void
    {
        foreach ($plan->sorts as $sort) {
            $builder->addOrderBy(
                'entity.'.$sort->field,
                'desc' === strtolower($sort->direction) ? 'DESC' : 'ASC',
            );
        }
    }

    /** @return array{array<string, string>, bool} */
    private function applyProjection(QueryBuilder $builder, CollectionQueryDTO $query, CollectionQueryPlanDTO $plan): array
    {
        if ([] === $query->fields) {
            $builder->select('entity');

            return [[], false];
        }

        $select = array_map(
            static fn (string $field): string => sprintf('entity.%s AS %s', $field, $field),
            $plan->projection,
        );
        if ([] === $select) {
            $builder->select('entity');

            return [[], false];
        }

        $aliases = [];
        foreach ($plan->sorts as $index => $sort) {
            if (in_array($sort->field, $plan->projection, true)) {
                continue;
            }

            $alias = '__cursor_'.$index;
            $aliases[$sort->field] = $alias;
            $select[] = sprintf('entity.%s AS %s', $sort->field, $alias);
        }

        $builder->select($select);

        return [$aliases, true];
    }

    /**
     * @param list<mixed>           $items
     * @param array<string, string> $projectionCursorAliases
     */
    private function nextCursor(
        EntityManagerInterface $manager,
        CollectionDefinitionDTO $definition,
        array $items,
        CollectionQueryPlanDTO $plan,
        array $projectionCursorAliases,
        bool $hasNext,
    ): ?string {
        $lastItem = end($items);
        if (!$hasNext || false === $lastItem || [] === $plan->sorts) {
            return null;
        }

        $metadata = $manager->getClassMetadata($definition->entityClass);
        $cursorValues = [];
        foreach ($plan->sorts as $sort) {
            [$hasValue, $value] = $this->cursorValue($lastItem, $sort->field, $projectionCursorAliases, $metadata);
            if (!$hasValue || !(is_int($value) || is_float($value) || is_string($value) || is_bool($value))) {
                return null;
            }
            $cursorValues[$sort->field] = $value;
        }

        if (count($cursorValues) !== count($plan->sorts)) {
            return null;
        }

        return rtrim(strtr(base64_encode(json_encode($cursorValues, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @param array<string, string> $aliases
     * @param ClassMetadata<object> $metadata
     *
     * @return array{bool, mixed}
     */
    private function cursorValue(mixed $item, string $field, array $aliases, ClassMetadata $metadata): array
    {
        if (is_array($item)) {
            if (array_key_exists($field, $item)) {
                return [true, $item[$field]];
            }
            if (isset($aliases[$field]) && array_key_exists($aliases[$field], $item)) {
                return [true, $item[$aliases[$field]]];
            }

            return [false, null];
        }

        if (is_object($item) && $metadata->hasField($field)) {
            return [true, $metadata->getFieldValue($item, $field)];
        }

        return [false, null];
    }

    /**
     * @param list<mixed>           $items
     * @param array<string, string> $aliases
     */
    private function stripProjectionCursorAliases(array &$items, array $aliases): void
    {
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ($aliases as $alias) {
                unset($item[$alias]);
            }
        }
        unset($item);
    }

    /**
     * @param list<mixed>                                  $items
     * @param list<array{field: string, operator: string}> $effectiveFilters
     *
     * @return array<string, mixed>
     */
    private function diagnostics(
        int $startedAt,
        array $items,
        int $total,
        int $filteredTotal,
        CollectionQueryPlanDTO $plan,
        array $effectiveFilters,
        bool $cursorApplied,
    ): array {
        return [
            'searchApplied' => [] !== $plan->searchFields,
            'searchFields' => $plan->searchFields,
            'filters' => $effectiveFilters,
            'sorts' => array_map(
                static fn ($sort): array => ['field' => $sort->field, 'direction' => strtolower($sort->direction)],
                $plan->sorts,
            ),
            'paginationMode' => $cursorApplied ? 'cursor' : 'offset',
            'projection' => $plan->projection,
            'metrics' => [
                'durationMs' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
                'queryCount' => self::EXECUTED_QUERY_COUNT,
                'returnedItems' => count($items),
                'total' => $total,
                'filteredTotal' => $filteredTotal,
            ],
        ];
    }
}

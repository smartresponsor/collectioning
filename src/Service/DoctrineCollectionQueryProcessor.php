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
        $startedAt = hrtime(true);
        $executedQueries = 0;

        $manager = $this->managerRegistry->getManagerForClass($definition->entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('No Doctrine ORM manager for collection entity "%s".', $definition->entityClass));
        }

        $policy = [];
        foreach ($definition->fields as $field) {
            $policy[$field->field] = $field;
        }

        $base = $manager->createQueryBuilder()->from($definition->entityClass, 'entity');
        ++$executedQueries;
        $total = (int) (clone $base)->select('COUNT(entity)')->getQuery()->getSingleScalarResult();

        $filtered = clone $base;
        $parameter = 0;
        $searchFields = [];
        $effectiveFilters = [];
        if (null !== $query->search) {
            $or = $filtered->expr()->orX();
            foreach ($policy as $field => $fieldPolicy) {
                if (!$fieldPolicy->searchable) {
                    continue;
                }
                $searchFields[] = $field;
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

            if (!in_array($filter->operator, $policy[$filter->field]->filterOperators, true)) {
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

            $effectiveFilters[] = ['field' => $filter->field, 'operator' => $filter->operator];
            $name = 'filter_'.$parameter++;
            $filtered->andWhere(sprintf('entity.%s %s :%s', $filter->field, $operator, $name));
            $filtered->setParameter($name, $filter->value);
        }

        ++$executedQueries;
        $filteredTotal = (int) (clone $filtered)
            ->select('COUNT(entity)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $effectiveSorts = [];
        foreach ($query->stableSorts($definition->identifierFields) as $sort) {
            if (!isset($policy[$sort->field]) || !$policy[$sort->field]->sortable) {
                continue;
            }

            $effectiveSorts[] = $sort;
        }

        $cursorFields = array_map(static fn ($sort): string => $sort->field, $effectiveSorts);
        $cursorApplied = false;
        if (null !== $query->cursor && array_keys($query->cursor) === $cursorFields) {
            $cursorOr = $filtered->expr()->orX();
            foreach ($effectiveSorts as $index => $sort) {
                $and = $filtered->expr()->andX();
                for ($previous = 0; $previous < $index; ++$previous) {
                    $previousSort = $effectiveSorts[$previous];
                    $name = 'cursor_'.$index.'_'.$previous;
                    $and->add(sprintf('entity.%s = :%s', $previousSort->field, $name));
                    $filtered->setParameter($name, $query->cursor[$previousSort->field]);
                }

                $name = 'cursor_'.$index;
                $and->add(sprintf(
                    'entity.%s %s :%s',
                    $sort->field,
                    'desc' === strtolower($sort->direction) ? '<' : '>',
                    $name,
                ));
                $filtered->setParameter($name, $query->cursor[$sort->field]);
                $cursorOr->add($and);
            }

            if ($cursorOr->count() > 0) {
                $filtered->andWhere($cursorOr);
                $cursorApplied = true;
            }
        }

        foreach ($effectiveSorts as $sort) {
            $filtered->addOrderBy('entity.'.$sort->field, 'desc' === strtolower($sort->direction) ? 'DESC' : 'ASC');
        }

        $projectionCursorAliases = [];
        $projectionEnabled = false;
        $effectiveProjection = [];
        if ([] !== $query->fields) {
            $select = [];
            foreach ($query->fields as $field) {
                if (isset($policy[$field]) && $policy[$field]->projectable) {
                    $effectiveProjection[] = $field;
                    $select[] = sprintf('entity.%s AS %s', $field, $field);
                }
            }

            if ([] !== $select) {
                $projectionEnabled = true;
                foreach ($effectiveSorts as $index => $sort) {
                    if (in_array($sort->field, $query->fields, true)) {
                        continue;
                    }

                    $alias = '__cursor_'.$index;
                    $projectionCursorAliases[$sort->field] = $alias;
                    $select[] = sprintf('entity.%s AS %s', $sort->field, $alias);
                }
            }

            $filtered->select([] !== $select ? $select : 'entity');
        } else {
            $filtered->select('entity');
        }

        ++$executedQueries;
        $items = $filtered
            ->setFirstResult($cursorApplied ? 0 : $query->page->offset())
            ->setMaxResults($query->page->size + 1)
            ->getQuery()
            ->getResult();

        $hasNext = count($items) > $query->page->size;
        if ($hasNext) {
            array_pop($items);
        }

        $nextCursor = null;
        $lastItem = end($items);
        if ($hasNext && false !== $lastItem && [] !== $effectiveSorts) {
            $metadata = $manager->getClassMetadata($definition->entityClass);
            $cursorValues = [];
            foreach ($effectiveSorts as $sort) {
                $value = null;
                $hasValue = false;
                if (is_array($lastItem)) {
                    if (array_key_exists($sort->field, $lastItem)) {
                        $value = $lastItem[$sort->field];
                        $hasValue = true;
                    } elseif (isset($projectionCursorAliases[$sort->field]) && array_key_exists($projectionCursorAliases[$sort->field], $lastItem)) {
                        $value = $lastItem[$projectionCursorAliases[$sort->field]];
                        $hasValue = true;
                    }
                } elseif (is_object($lastItem) && $metadata->hasField($sort->field)) {
                    $value = $metadata->getFieldValue($lastItem, $sort->field);
                    $hasValue = true;
                }

                if (!$hasValue || !(is_int($value) || is_float($value) || is_string($value) || is_bool($value))) {
                    $cursorValues = [];
                    break;
                }

                $cursorValues[$sort->field] = $value;
            }

            if (count($cursorValues) === count($effectiveSorts)) {
                $nextCursor = rtrim(strtr(base64_encode(json_encode($cursorValues, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            }
        }

        if ($projectionEnabled && [] !== $projectionCursorAliases) {
            foreach ($items as &$item) {
                if (!is_array($item)) {
                    continue;
                }
                foreach ($projectionCursorAliases as $alias) {
                    unset($item[$alias]);
                }
            }
            unset($item);
        }

        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 3);
        $diagnostics = [
            'searchApplied' => [] !== $searchFields,
            'searchFields' => $searchFields,
            'filters' => $effectiveFilters,
            'sorts' => array_map(
                static fn ($sort): array => ['field' => $sort->field, 'direction' => strtolower($sort->direction)],
                $effectiveSorts,
            ),
            'paginationMode' => $cursorApplied ? 'cursor' : 'offset',
            'projection' => $effectiveProjection,
            'metrics' => [
                'durationMs' => $durationMs,
                'queryCount' => $executedQueries,
                'returnedItems' => count($items),
                'total' => $total,
                'filteredTotal' => $filteredTotal,
            ],
        ];

        return new CollectionResultDTO(array_values($items), $total, $filteredTotal, $query->page, $nextCursor, $diagnostics);
    }
}

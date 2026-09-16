<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionQueryPlanDTO;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;

final readonly class CollectionQueryPlanner implements CollectionQueryPlannerInterface
{
    private const array SUPPORTED_FILTER_OPERATORS = [
        'eq' => true,
        'neq' => true,
        'lt' => true,
        'lte' => true,
        'gt' => true,
        'gte' => true,
        'in' => true,
        'notIn' => true,
    ];

    public function plan(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionQueryPlanDTO
    {
        $policy = [];
        $filterOperatorPolicy = [];
        foreach ($definition->fields as $field) {
            $policy[$field->field] = $field;
            $filterOperatorPolicy[$field->field] = array_fill_keys($field->filterOperators, true);
        }

        $searchFields = [];
        if (null !== $query->search) {
            foreach ($policy as $field => $fieldPolicy) {
                if ($fieldPolicy->searchable) {
                    $searchFields[] = $field;
                }
            }
        }

        $filters = [];
        foreach ($query->filters as $filter) {
            if (!isset($policy[$filter->field])) {
                continue;
            }
            if (!$policy[$filter->field]->filterable) {
                continue;
            }
            if (!isset(self::SUPPORTED_FILTER_OPERATORS[$filter->operator])) {
                continue;
            }
            if (!isset($filterOperatorPolicy[$filter->field][$filter->operator])) {
                continue;
            }
            if ('in' === $filter->operator || 'notIn' === $filter->operator) {
                if (!is_array($filter->value) || !array_is_list($filter->value) || [] === $filter->value || count(array_filter($filter->value, 'is_scalar')) !== count($filter->value)) {
                    continue;
                }
            } elseif (is_array($filter->value)) {
                continue;
            }

            $filters[] = $filter;
        }

        $sorts = [];
        foreach ($query->stableSorts($definition->identifierFields) as $sort) {
            if (!isset($policy[$sort->field])) {
                continue;
            }
            if (!$policy[$sort->field]->sortable) {
                continue;
            }

            $sorts[] = $sort;
        }

        $projection = [];
        foreach ($query->fields as $field) {
            if (!isset($policy[$field])) {
                continue;
            }
            if (!$policy[$field]->projectable) {
                continue;
            }

            $projection[] = $field;
        }

        $cursorApplicable = false;
        if (null !== $query->cursor) {
            if ([] !== $sorts) {
                $cursorFields = array_map(static fn ($sort): string => $sort->field, $sorts);
                $cursorApplicable = array_keys($query->cursor) === $cursorFields;
            }
        }

        return new CollectionQueryPlanDTO($searchFields, $filters, $sorts, $projection, $cursorApplicable);
    }
}

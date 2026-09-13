<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionQueryPlanDTO;
use App\Collectioning\ServiceInterface\CollectionQueryPlannerInterface;

final readonly class CollectionQueryPlanner implements CollectionQueryPlannerInterface
{
    private const array SUPPORTED_FILTER_OPERATORS = ['eq', 'neq', 'lt', 'lte', 'gt', 'gte'];

    public function plan(CollectionDefinitionDTO $definition, CollectionQueryDTO $query): CollectionQueryPlanDTO
    {
        $policy = [];
        foreach ($definition->fields as $field) {
            $policy[$field->field] = $field;
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
            if (!in_array($filter->operator, self::SUPPORTED_FILTER_OPERATORS, true)) {
                continue;
            }
            if (!in_array($filter->operator, $policy[$filter->field]->filterOperators, true)) {
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

<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionQueryPlanDTO;
use App\Collectioning\DTO\CollectionSortDTO;
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
        [$policy, $filterOperatorPolicy] = $this->policies($definition);
        $sorts = $this->sorts($definition, $query, $policy);

        return new CollectionQueryPlanDTO(
            $this->searchFields($query, $policy),
            $this->filters($query, $policy, $filterOperatorPolicy),
            $sorts,
            $this->projection($query, $policy),
            $this->cursorApplicable($query, $sorts),
        );
    }

    /** @return array{array<string, CollectionFieldPolicyDTO>, array<string, array<string, true>>} */
    private function policies(CollectionDefinitionDTO $definition): array
    {
        $policy = [];
        $filterOperatorPolicy = [];
        foreach ($definition->fields as $field) {
            $policy[$field->field] = $field;
            $filterOperatorPolicy[$field->field] = array_fill_keys($field->filterOperators, true);
        }

        return [$policy, $filterOperatorPolicy];
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $policy
     *
     * @return list<string>
     */
    private function searchFields(CollectionQueryDTO $query, array $policy): array
    {
        if (null === $query->search) {
            return [];
        }

        $searchFields = [];
        foreach ($policy as $field => $fieldPolicy) {
            if ($fieldPolicy->searchable) {
                $searchFields[] = $field;
            }
        }

        return $searchFields;
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $policy
     * @param array<string, array<string, true>>      $filterOperatorPolicy
     *
     * @return list<CollectionFilterDTO>
     */
    private function filters(CollectionQueryDTO $query, array $policy, array $filterOperatorPolicy): array
    {
        $filters = [];
        foreach ($query->filters as $filter) {
            if (!isset($policy[$filter->field]) || !$policy[$filter->field]->filterable) {
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

        return $filters;
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $policy
     *
     * @return list<CollectionSortDTO>
     */
    private function sorts(CollectionDefinitionDTO $definition, CollectionQueryDTO $query, array $policy): array
    {
        $sorts = [];
        foreach ($query->stableSorts($definition->identifierFields) as $sort) {
            if (!isset($policy[$sort->field]) || !$policy[$sort->field]->sortable) {
                continue;
            }

            $sorts[] = $sort;
        }

        return $sorts;
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $policy
     *
     * @return list<string>
     */
    private function projection(CollectionQueryDTO $query, array $policy): array
    {
        $projection = [];
        $projectedFields = [];
        foreach ($query->fields as $field) {
            if (!isset($policy[$field]) || !$policy[$field]->projectable || isset($projectedFields[$field])) {
                continue;
            }

            $projection[] = $field;
            $projectedFields[$field] = true;
        }

        return $projection;
    }

    /** @param list<CollectionSortDTO> $sorts */
    private function cursorApplicable(CollectionQueryDTO $query, array $sorts): bool
    {
        if (null === $query->cursor || [] === $sorts) {
            return false;
        }

        $cursorFields = array_map(static fn ($sort): string => $sort->field, $sorts);

        return array_keys($query->cursor) === $cursorFields;
    }
}

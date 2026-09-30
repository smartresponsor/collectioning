<?php

declare(strict_types=1);

namespace App\Collectioning\Resolver;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionSortDTO;
use App\Collectioning\ServiceInterface\CollectionQueryRequestResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class CollectionQueryRequestResolver implements CollectionQueryRequestResolverInterface
{
    public function resolve(Request $request, CollectionDefinitionDTO $definition): CollectionQueryDTO
    {
        $pageNumber = max(1, $request->query->getInt('page', 1));
        $pageSize = max(1, min($definition->maxPageSize, $request->query->getInt('limit', $definition->defaultPageSize)));
        $search = trim((string) $request->query->get('q', ''));
        $allowed = $this->fieldPolicies($definition);

        return new CollectionQueryDTO(
            new CollectionPageDTO($pageNumber, $pageSize),
            '' === $search ? null : $search,
            $this->filters($request, $allowed),
            $this->sorts($request, $allowed),
            $this->projection($request, $allowed),
            $this->cursor($request),
        );
    }

    /** @return array<string, CollectionFieldPolicyDTO> */
    private function fieldPolicies(CollectionDefinitionDTO $definition): array
    {
        $allowed = [];
        foreach ($definition->fields as $field) {
            $allowed[$field->field] = $field;
        }

        return $allowed;
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $allowed
     *
     * @return list<CollectionFilterDTO>
     */
    private function filters(Request $request, array $allowed): array
    {
        $filters = [];
        foreach ($request->query->all('filter') as $field => $value) {
            if (!is_string($field) || !isset($allowed[$field]) || !$allowed[$field]->filterable) {
                continue;
            }

            array_push($filters, ...$this->fieldFilters($field, $value, $allowed[$field]));
        }

        return $filters;
    }

    /** @return list<CollectionFilterDTO> */
    private function fieldFilters(string $field, mixed $value, CollectionFieldPolicyDTO $policy): array
    {
        if (!is_array($value)) {
            return in_array('eq', $policy->filterOperators, true)
                ? [new CollectionFilterDTO($field, 'eq', $value)]
                : [];
        }

        $filters = [];
        foreach ($value as $operator => $operand) {
            $filter = is_string($operator)
                ? $this->operatorFilter($field, $operator, $operand, $policy)
                : null;
            if (null !== $filter) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    private function operatorFilter(
        string $field,
        string $operator,
        mixed $operand,
        CollectionFieldPolicyDTO $policy,
    ): ?CollectionFilterDTO {
        if (!in_array($operator, $policy->filterOperators, true)) {
            return null;
        }

        if ('in' === $operator || 'notIn' === $operator) {
            if (!is_array($operand) || !array_is_list($operand) || [] === $operand) {
                return null;
            }

            return count(array_filter($operand, 'is_scalar')) === count($operand)
                ? new CollectionFilterDTO($field, $operator, $operand)
                : null;
        }

        return is_array($operand) ? null : new CollectionFilterDTO($field, $operator, $operand);
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $allowed
     *
     * @return list<CollectionSortDTO>
     */
    private function sorts(Request $request, array $allowed): array
    {
        $sorts = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $request->query->get('sort', '')))) as $token) {
            $direction = str_starts_with($token, '-') ? 'desc' : 'asc';
            $field = ltrim($token, '+-');
            if (isset($allowed[$field]) && $allowed[$field]->sortable) {
                $sorts[] = new CollectionSortDTO($field, $direction);
            }
        }

        return $sorts;
    }

    /**
     * @param array<string, CollectionFieldPolicyDTO> $allowed
     *
     * @return list<string>
     */
    private function projection(Request $request, array $allowed): array
    {
        $fields = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $request->query->get('fields', '')))) as $field) {
            if (isset($allowed[$field]) && $allowed[$field]->projectable) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /** @return array<string, bool|float|int|string>|null */
    private function cursor(Request $request): ?array
    {
        $cursorToken = trim((string) $request->query->get('cursor', ''));
        if ('' === $cursorToken || strlen($cursorToken) > 4096) {
            return null;
        }

        $padding = (4 - strlen($cursorToken) % 4) % 4;
        $decoded = base64_decode(strtr($cursorToken.str_repeat('=', $padding), '-_', '+/'), true);
        if (false === $decoded) {
            return null;
        }

        try {
            $candidate = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($candidate) || array_is_list($candidate)) {
            return null;
        }

        $keys = array_keys($candidate);

        return count(array_filter($keys, 'is_string')) === count($keys)
            && count(array_filter($candidate, 'is_scalar')) === count($candidate)
            ? $candidate
            : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Collectioning\Resolver;

use App\Collectioning\DTO\CollectionDefinitionDTO;
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

        $allowed = [];
        foreach ($definition->fields as $field) {
            $allowed[$field->field] = $field;
        }

        $filters = [];
        foreach ($request->query->all('filter') as $field => $value) {
            if (!is_string($field) || !isset($allowed[$field]) || !$allowed[$field]->filterable) {
                continue;
            }

            $fieldPolicy = $allowed[$field];
            if (!is_array($value)) {
                if (in_array('eq', $fieldPolicy->filterOperators, true)) {
                    $filters[] = new CollectionFilterDTO($field, 'eq', $value);
                }
                continue;
            }

            foreach ($value as $operator => $operand) {
                if (is_string($operator) && in_array($operator, $fieldPolicy->filterOperators, true) && !is_array($operand)) {
                    $filters[] = new CollectionFilterDTO($field, $operator, $operand);
                }
            }
        }

        $sorts = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $request->query->get('sort', '')))) as $token) {
            $direction = str_starts_with($token, '-') ? 'desc' : 'asc';
            $field = ltrim($token, '+-');
            if (isset($allowed[$field]) && $allowed[$field]->sortable) {
                $sorts[] = new CollectionSortDTO($field, $direction);
            }
        }

        $fields = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $request->query->get('fields', '')))) as $field) {
            if (isset($allowed[$field]) && $allowed[$field]->projectable) {
                $fields[] = $field;
            }
        }

        $cursor = null;
        $cursorToken = trim((string) $request->query->get('cursor', ''));
        if ('' !== $cursorToken && strlen($cursorToken) <= 4096) {
            $padding = (4 - strlen($cursorToken) % 4) % 4;
            $decoded = base64_decode(strtr($cursorToken.str_repeat('=', $padding), '-_', '+/'), true);
            if (false !== $decoded) {
                try {
                    $candidate = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $candidate = null;
                }

                if (is_array($candidate) && !array_is_list($candidate)) {
                    $keys = array_keys($candidate);
                    if (count(array_filter($keys, 'is_string')) === count($keys)
                        && count(array_filter($candidate, 'is_scalar')) === count($candidate)) {
                        $cursor = $candidate;
                    }
                }
            }
        }

        return new CollectionQueryDTO(
            new CollectionPageDTO($pageNumber, $pageSize),
            '' === $search ? null : $search,
            $filters,
            $sorts,
            $fields,
            $cursor,
        );
    }
}

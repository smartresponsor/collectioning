# Collectioning

Collectioning is the platform-wide Symfony primitive for querying collections. It owns collection semantics, not presentation and not CRUD mutation.

## Responsibility

Collectioning owns pagination, global search, field filters, stable sorting, projection, total/filtered counts, facet aggregation, grouped/general aggregation, provider-neutral result contracts, HTTP query normalization, and realtime collection invalidation contracts.

The component does not own HTML, tables, CRUD forms, entity mutations, navigation menus, or a JavaScript data-grid implementation.

Canonical flow:

`Request -> CollectionQueryRequestResolver -> CollectionQueryDTO -> CollectionQueryPlannerInterface -> CollectionQueryPlanDTO -> CollectionQueryProcessorInterface -> CollectionResultDTO`

HTTP filters remain backward compatible with scalar equality syntax such as `filter[status]=active`. Typed comparison operators use nested syntax such as `filter[id][gte]=10`. Multi-value membership uses list operands such as `filter[id][in][]=1&filter[id][in][]=3`; `notIn` is also canonical. Field policies whitelist supported operators; Doctrine-derived numeric fields support `eq`, `neq`, `in`, `notIn`, `lt`, `lte`, `gt`, and `gte`, while other filterable scalar fields support `eq`, `neq`, `in`, and `notIn`. Empty or non-scalar membership lists are rejected during normalization/planning.

Offset pagination remains the default. Results may additionally expose an opaque `nextCursor`; clients can pass that value back as `cursor=<token>` with the same filter and sort contract to continue by keyset pagination. Cursor boundaries include all effective stable-sort fields, including identifier tie-breakers. Clients must treat the token as opaque and must not depend on its encoded format. Malformed tokens are ignored during request normalization, and tokens whose field shape does not match the effective stable sort fall back to normal offset pagination. Cursor emission currently requires non-null scalar sort-boundary values; explicit cross-database null ordering is not yet part of the cursor contract.

Stable sorting is canonicalized before planning and execution. Sort directions are normalized to lowercase `asc`/`desc`, unsupported directions are ignored, duplicate sort fields keep the first valid occurrence, and identifier tie-breakers are appended after that normalization. This keeps direct/programmatic DTO consumers consistent with HTTP-resolved queries and prevents contradictory `ORDER BY` clauses or impossible cursor shapes.

`CollectionResultDTO` also exposes provider-neutral diagnostics for the effective query plan: whether search was applied, searchable fields used, applied filter field/operator pairs, effective stable sorts, pagination mode, and public projection fields. Diagnostics never include SQL/DQL, search text, filter values, cursor contents, or bound parameter values. Consumers should treat diagnostics as observability metadata rather than executable query instructions. Diagnostics also include aggregate execution metrics (`durationMs`, `queryCount`, `returnedItems`, `total`, `filteredTotal`) derived from monotonic timing and actual Doctrine executions; these are observational values, not SLO thresholds.

`CollectionQueryPlannerInterface` is the provider-neutral normalization boundary. It resolves the effective searchable fields, canonical filter operators, stable sorts, projection, and cursor eligibility once. Execution providers such as Doctrine consume `CollectionQueryPlanDTO` and remain responsible only for translating that plan into provider-specific query primitives and executing it. A second backend is not bundled merely to demonstrate abstraction; future providers should implement the existing processor contract while reusing the shared planner semantics.

Faceted navigation is exposed through `CollectionFacetProcessorInterface`. A `CollectionFacetDTO` requests one policy-approved facet field, bucket limit, optional missing-value count, and whether that facet should exclude its own active filter when computing alternatives. `DoctrineCollectionFacetProcessor` reuses the canonical query planner for current search/filter semantics, rejects non-facetable fields, groups scalar buckets by count, and never applies pagination/projection state to facet counts. Facet execution is separate from table presentation so Tabling and non-table consumers can share the same aggregation semantics.

General summaries are exposed through `CollectionAggregationProcessorInterface`. `CollectionAggregationDTO` supports `count`, `sum`, `avg`, `min`, and `max`; field-backed operations are accepted only when the current `CollectionFieldPolicyDTO::aggregateFunctions` allow them. Optional `groupBy` dimensions must be facetable fields. Aggregations reuse the canonical search/filter plan but ignore collection pagination/projection state. Grouped results are bounded to 500 rows and `CollectionAggregationResultDTO::truncated` reports when more groups existed, preventing silent unbounded summary responses.

Scoped data reads are exposed through `CollectionScopedReaderInterface` for export and bulk-read workflows. `CollectionDataScopeDTO` distinguishes `currentPage`, `filtered`, and `selected`. Filtered/selected scopes restart from page one and read bounded pages using the collection's `maxPageSize` while preserving the canonical search, filters, sorts, and projection; selected scope appends explicit policy-validated selection filters, typically an identifier `in` filter. Cursor state from the visible grid is deliberately not reused for full-scope reads. The reader yields items incrementally rather than materializing the whole scope in memory. Cross-page snapshot consistency remains the responsibility of the caller/provider transaction boundary.

Cruding, Tabling, API endpoints and other consumers may depend on this package. Ant Design Pro Components and PrimeReact remain UI consumers rather than backend collection engines.

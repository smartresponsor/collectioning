# Collectioning

Collectioning is the platform-wide Symfony primitive for querying collections. It owns collection semantics, not presentation and not CRUD mutation.

## Responsibility

Collectioning owns pagination, global search, field filters, stable sorting, projection, total/filtered counts, provider-neutral result contracts, HTTP query normalization, and realtime collection invalidation contracts.

The component does not own HTML, tables, CRUD forms, entity mutations, navigation menus, or a JavaScript data-grid implementation.

Canonical flow:

`Request -> CollectionQueryRequestResolver -> CollectionQueryDTO -> CollectionQueryPlannerInterface -> CollectionQueryPlanDTO -> CollectionQueryProcessorInterface -> CollectionResultDTO`

HTTP filters remain backward compatible with scalar equality syntax such as `filter[status]=active`. Typed comparison operators use nested syntax such as `filter[id][gte]=10`. Field policies whitelist supported operators; Doctrine-derived numeric fields support `eq`, `neq`, `lt`, `lte`, `gt`, and `gte`, while other filterable scalar fields default to `eq` and `neq`.

Offset pagination remains the default. Results may additionally expose an opaque `nextCursor`; clients can pass that value back as `cursor=<token>` with the same filter and sort contract to continue by keyset pagination. Cursor boundaries include all effective stable-sort fields, including identifier tie-breakers. Clients must treat the token as opaque and must not depend on its encoded format. Malformed tokens are ignored during request normalization, and tokens whose field shape does not match the effective stable sort fall back to normal offset pagination. Cursor emission currently requires non-null scalar sort-boundary values; explicit cross-database null ordering is not yet part of the cursor contract.

`CollectionResultDTO` also exposes provider-neutral diagnostics for the effective query plan: whether search was applied, searchable fields used, applied filter field/operator pairs, effective stable sorts, pagination mode, and public projection fields. Diagnostics never include SQL/DQL, search text, filter values, cursor contents, or bound parameter values. Consumers should treat diagnostics as observability metadata rather than executable query instructions. Diagnostics also include aggregate execution metrics (`durationMs`, `queryCount`, `returnedItems`, `total`, `filteredTotal`) derived from monotonic timing and actual Doctrine executions; these are observational values, not SLO thresholds.

`CollectionQueryPlannerInterface` is the provider-neutral normalization boundary. It resolves the effective searchable fields, canonical filter operators, stable sorts, projection, and cursor eligibility once. Execution providers such as Doctrine consume `CollectionQueryPlanDTO` and remain responsible only for translating that plan into provider-specific query primitives and executing it. A second backend is not bundled merely to demonstrate abstraction; future providers should implement the existing processor contract while reusing the shared planner semantics.

Cruding, Tabling, API endpoints and other consumers may depend on this package. Ant Design Pro Components and PrimeReact remain UI consumers rather than backend collection engines.

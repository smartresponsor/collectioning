# Collectioning

Collectioning is the platform-wide Symfony primitive for querying collections. It owns collection semantics, not presentation and not CRUD mutation.

## Responsibility

Collectioning owns pagination, global search, field filters, stable sorting, projection, total/filtered counts, provider-neutral result contracts, HTTP query normalization, and realtime collection invalidation contracts.

The component does not own HTML, tables, CRUD forms, entity mutations, navigation menus, or a JavaScript data-grid implementation.

Canonical flow:

`Request -> CollectionQueryRequestResolver -> CollectionQueryDTO -> CollectionQueryProcessorInterface -> CollectionResultDTO`

HTTP filters remain backward compatible with scalar equality syntax such as `filter[status]=active`. Typed comparison operators use nested syntax such as `filter[id][gte]=10`. Field policies whitelist supported operators; Doctrine-derived numeric fields support `eq`, `neq`, `lt`, `lte`, `gt`, and `gte`, while other filterable scalar fields default to `eq` and `neq`.

Cruding, Tabling, API endpoints and other consumers may depend on this package. Ant Design Pro Components and PrimeReact remain UI consumers rather than backend collection engines.

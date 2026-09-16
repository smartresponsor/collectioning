# CMCP Execution Journal

## Iteration 1 — reconnaissance and baseline

### Repository facts

- Workspace: `D:\PhpstormProjects\www\Collectioning`.
- Active branch: `collection-query-hardening`, tracking `origin/collection-query-hardening` at `20e91c507ab6202e44a8cac14cd77494d50c73a7` before this run.
- Pre-existing worktree state: untracked `.gating/`; this run does not claim or mutate it.
- Package identity: `collectioning/collection`; PSR-4 root `App\\Collectioning\\ => src/`.
- Responsibility: provider-neutral collection query semantics: search, filters, sorting, pagination, projection, counts, request normalization, and realtime invalidation contracts. HTML, generic CRUD mutation/routes/controllers, navigation, and JavaScript data grids remain outside this repository.

### Sources read

- Target: `README.md`, `composer.json`, `config/services.yaml`, all current `src/**` PHP files discovered under the package namespace, and the current unit test.
- Canonization: root `README.md`, `AGENTS.md`, architecture `README.md`, `GUARD_MATRIX.md`, and Canon000, Canon001, Canon002, Canon007, Canon008, Canon017, Canon018, Canon019, Canon022, Canon023, Canon024, Canon025, Canon026, Canon029, Canon033, Canon038, Canon039, Canon040.
- Gating: `README.md`, `AGENTS.md`, `composer.json`, plus the target-local materialized `.gating/` catalog/profile surfaces discovered during reconnaissance. `.gating/` is pre-existing untracked state and is not owned by this run.
- Related application contour: Objecting, Cruding, Viewing, and Interfacing root `README.md`, `composer.json`, available `AGENTS.md`, and available manifests.

### Target-to-canon mapping

- Canon000/018: `collectioning/collection` correctly maps component identity to `App\\Collectioning\\` and subject vocabulary to `Collection*`.
- Canon001/002/007: current source is role-first and service interfaces mirror service roles; PSR-4 paths/namespaces are structurally aligned.
- Canon008: no production PHP imports from Objecting, Cruding, Viewing, or Interfacing were found in the current Collectioning source, so adding compile-time dependencies solely for unused namespaces is not justified by this rule.
- Canon022: the full application dependency baseline applies to standalone Symfony applications. The current target has no `bin/console` standalone boot surface; therefore its baseline dependency requirement is not yet triggered by standalone detection alone.
- Canon023/024/025: the repository is a reusable Symfony bundle but does not currently expose the canonical dual-runtime standalone surfaces or `composer.prod.json`; this is bounded packaging debt and is kept separate from the selected query-correctness RC workstream.
- Canon026: current PHP `^8.4` and Symfony `^8.1` constraints match the platform floor.
- Canon029: PHP-CS-Fixer and PHPStan dependencies/scripts exist; repository-owned PHP-CS-Fixer config exists. PHPStan config still requires gate verification.
- Canon039/040: PHPUnit is declared and a test script exists, but repository-owned PHPUnit/coverage configuration and persistent branch-coverage evidence require verification/remediation.
- Canon017: README promises stable sorting; current Doctrine processor does not add an identifier tie-breaker, so runtime can violate documented stable-pagination semantics.
- Canon019: no alternative `Domain/Application/Infrastructure/Port/Adapter/Adaptor` root taxonomy was discovered in current source.

### Market / maturity baseline

- Mature collection/query engines treat filtering, sorting, pagination, projection, and counts as separate backend semantics, while presentation remains downstream.
- Offset pagination needs deterministic ordering to avoid duplicate/missing records across pages when sort keys tie or no explicit sort is supplied.
- Advanced post-RC capabilities include richer typed operators, cursor/keyset pagination, query diagnostics, and broader provider abstractions; these are growth items unless required for correctness.

### Selected workstreams

- **RC-critical:** make Doctrine-backed offset pagination deterministically ordered by appending entity identifier fields as stable tie-breakers; preserve caller-requested sort precedence; add focused tests and factual documentation.
- **Growth:** typed filter operators, cursor/keyset pagination, richer query diagnostics/observability, provider-neutral adapters beyond Doctrine, and query-plan/performance instrumentation.

### Material risks and gates

- Composite identifiers must append deterministic tie-breakers without duplicating explicit caller sorts.
- Identifier tie-breakers remain subject to the same sortable field policy as explicit sorts.
- Planned gates: PHPUnit, branch coverage, PHPStan, PHP-CS-Fixer, strict Composer validation, and Gating where the Console execution policy exposes an allowed path.

## Iteration 2 — material implementation

- Added scalar Doctrine identifier metadata to `CollectionDefinitionDTO`.
- Added `CollectionQueryDTO::stableSorts()` to preserve requested sort precedence and append missing identifier tie-breakers.
- `DoctrineCollectionDefinitionFactory` now derives sortable scalar identifiers from Doctrine metadata.
- `DoctrineCollectionQueryProcessor` now applies stable sort composition before offset pagination.
- Added focused stable-sort tests; PHPUnit reached 3 tests / 16 assertions and PHPStan level 8 remained green.

## Iteration 3 — verification and fix

- Added repository-owned `phpstan.neon` required by Canon029.
- Added repository-owned `phpunit.xml.dist` and `test:coverage` execution contract required by Canon039.
- Added behavioral tests for result pagination, request-reader orchestration/failure behavior, Mercure topic/payload publication, and Doctrine manager rejection.
- Final coverage evidence after remediation: lines 51.85% (70/135), methods 68.18% (15/22), branches 78.72% (74/94). This removes Canon040 `HIGH_TEST_DEBT`; line/method coverage remain below the 80% maturity target and stay as non-blocking quality debt.
- A PHPUnit 13 test-double API mismatch was found during verification and fixed before acceptance.

## Iteration 4 — debt closure and integration

- Strict Composer validation passes.
- PHPUnit, PHPStan, and coverage execution pass.
- PHP-CS-Fixer identified only line-ending/format normalization in newly added tests; canonical `cs:fix` was applied and is re-verified in final acceptance.
- The target-local `.gating/` materialization was inspected but remains pre-existing untracked state and is not owned or staged by this run.
- Console MCP's named gate runner did not expose a `gating` check, and its bounded PowerShell policy rejects scripts outside `tool/` or `bin/`; therefore `.gating/gate.ps1` could not be executed without bypassing Console policy. No shell bypass was used.
- Canon023/024/025 dual-runtime packaging debt (`bin/console`, `config/bundles.php`, production manifest) is not expanded in this RC because doing so would activate Canon022's standalone dependency baseline and risks introducing a circular Cruding↔Collectioning dependency. This requires a separate ecosystem packaging decision rather than an unsafe local patch.

## Iteration 5 — final acceptance and handoff

- Final local gates after formatting are green: PHPUnit 9/9 with 39 assertions, PHPStan level 8 with 0 errors, PHP-CS-Fixer with 0 pending fixes, and strict Composer validation/check-lock valid.
- Coverage evidence: lines 51.85% (70/135), methods 68.18% (15/22), branches 78.72% (74/94); no Canon040 high-test-debt dimension remains.
- Signed implementation commit created: `749f5db` (`Harden collection query pagination and quality gates`).
- `.gating/` remains pre-existing untracked state and is deliberately excluded from commits.
- This journal closure is committed separately so the implementation commit identity can be recorded factually; current branch is then published to its configured upstream and re-inspected.

## Development continuation

- Continued after RC publication to close canonical coverage debt before adding new query capabilities.
- Added behavioral coverage for Doctrine metadata policy derivation and Symfony extension loading/alias contracts without widening Collectioning responsibility.
- The extension load test exposed a real package-integrity defect: `CollectioningExtension` uses Symfony `YamlFileLoader`, but `symfony/yaml` was not declared. Added runtime dependency `symfony/yaml:^8.1`; Composer resolved and locked `symfony/yaml v8.1.6`.
- Added an in-memory SQLite/Doctrine integration test for the core `DoctrineCollectionQueryProcessor`, covering total/filtered counts, case-insensitive search, field-policy filtering, explicit sort precedence, projection, stable identifier fallback ordering, and offset pagination.
- Doctrine test bootstrap uses PHP 8.4 native lazy objects rather than adding the legacy proxy dependency path.
- Final canonical coverage: lines 98.51% (133/135), methods 81.81% (18/22), branches 90.78% (128/141), satisfying Canon040 thresholds in every dimension.
- Final gates: PHPUnit 14/14 with 62 assertions; PHPStan 0 errors; PHP-CS-Fixer 0 pending fixes; strict Composer validation/check-lock valid; Composer audit reports no security vulnerability advisories.
- No new public Collectioning capability was added in this continuation: the work hardened existing runtime semantics and package installability first.

## Growth continuation — typed filters

- Added policy-driven typed filter capabilities to `CollectionFieldPolicyDTO`; equality-only behavior remains the default for manually constructed policies.
- `DoctrineCollectionDefinitionFactory` now derives `eq`/`neq` for filterable scalar fields and adds `lt`/`lte`/`gt`/`gte` for numeric Doctrine types (`integer`, `smallint`, `bigint`, `decimal`, `float`). Decimal and float fields are now consistently filterable.
- HTTP normalization preserves `filter[field]=value` as `eq` and adds nested operator syntax such as `filter[id][gte]=10`; unknown or disallowed operators are discarded by the resolver.
- `DoctrineCollectionQueryProcessor` executes only a fixed DQL operator map after field-policy validation, so raw operator strings never reach query construction. An integration test verifies that even a malformed/future policy-listed unknown operator is ignored by the executor.
- Added Doctrine integration coverage for composed `gte` + `neq` filtering and expanded factory/resolver contract assertions.
- README now documents the backward-compatible scalar syntax, typed nested syntax, and operator capabilities.
- Final gates: PHPUnit 16/16 with 76 assertions; PHPStan 0 errors; PHP-CS-Fixer 0 pending fixes; strict Composer validation/check-lock valid; Composer audit reports no security vulnerability advisories.
- Final coverage remains above Canon040 in every dimension: lines 96.17% (151/157), methods 81.81% (18/22), branches 88.33% (159/180).

## Growth continuation — cursor pagination

- Added optional keyset pagination alongside existing offset pagination. Offset remains the default and existing page/limit behavior is preserved.
- `CollectionQueryDTO` carries an optional decoded cursor boundary map as a trailing argument; `CollectionResultDTO` carries an optional trailing `nextCursor`, preserving constructor compatibility.
- HTTP `cursor=<token>` is normalized as an opaque bounded base64url-JSON transport token. Malformed tokens are ignored rather than treated as query errors.
- Cursor boundaries contain all effective stable-sort fields, including identifier tie-breakers. Doctrine keyset predicates are lexicographic: each OR arm fixes prior sort fields and compares the current field using `>` for ascending and `<` for descending order.
- Cursor application requires the token field order/shape to exactly match effective policy-approved sorts. A syntactically valid mismatched cursor falls back to normal offset pagination instead of silently resetting the offset.
- Query execution reads `page size + 1` rows to determine continuation and emits `nextCursor` from the final returned row when another row exists.
- Projection mode internally selects missing sort-boundary fields using `__cursor_*` aliases, then removes those aliases before returning public items; integration tests verify no internal cursor data leaks into projections.
- Cursor emission currently accepts only non-null scalar sort-boundary values. Null ordering remains deliberately out of scope until Collectioning defines a cross-database NULLS FIRST/LAST contract.
- A standalone `CollectionCursorDTO` prototype was rejected as unnecessary public API surface; cursor transport stays inside resolver/processor and temporary prototypes were preserved only under ignored `var/` backup paths.
- Doctrine identifier selection was made explicit with a branch-testable loop while preserving exclusion of association identifiers and blob/binary identifiers.
- Final gates: PHPUnit 18/18 with 88 assertions; PHPStan 0 errors; PHP-CS-Fixer 0 pending fixes; strict Composer validation/check-lock valid; Composer audit reports no security vulnerability advisories.
- Final Canon040 coverage: lines 95.10% (233/245), methods 81.81% (18/22), branches 88.58% (256/289).

## Growth continuation — query diagnostics

- Added a trailing provider-neutral `diagnostics` payload to `CollectionResultDTO`; existing constructor calls remain compatible through the default empty array.
- Doctrine processing now reports only the effective query plan: `searchApplied`, `searchFields`, applied filter `{field, operator}` pairs, effective stable sorts, pagination mode (`offset` or `cursor`), and public projection fields.
- Diagnostics deliberately exclude SQL/DQL, search text, filter values, cursor contents, and bound parameter values to keep observability useful without leaking user/query data.
- Diagnostics are derived after policy/executor validation, so rejected filters and disallowed sorts/projections do not appear as effective behavior.
- Integration coverage verifies offset diagnostics, cursor-mode diagnostics, stable identifier tie-breakers, projection reporting, and exclusion of unknown filter operators.
- Final gates: PHPUnit 18/18 with 96 assertions; PHPStan 0 errors; PHP-CS-Fixer 0 pending fixes; strict Composer validation/check-lock valid; Composer audit reports no security vulnerability advisories.
- Final Canon040 coverage: lines 95.41% (250/262), methods 81.81% (18/22), branches 88.69% (259/292).

## Growth continuation — performance instrumentation

- Extended existing provider-neutral diagnostics with aggregate execution metrics rather than introducing a new public instrumentation API.
- `DoctrineCollectionQueryProcessor` now uses monotonic `hrtime(true)` timing and increments an execution counter at each actual Doctrine query execution.
- `diagnostics.metrics` exposes only `durationMs`, `queryCount`, `returnedItems`, `total`, and `filteredTotal`; no SQL/DQL, parameter values, search text, filters, or cursor contents are added.
- Integration coverage verifies exact query/count metrics while treating duration as a non-negative observational float to avoid flaky timing thresholds.
- Final gates: PHPUnit 18/18 with 103 assertions; PHPStan 0 errors; PHP-CS-Fixer 0 pending fixes; strict Composer validation/check-lock valid; Composer audit reports no security vulnerability advisories.
- Final Canon040 coverage: lines 95.63% (263/275), methods 81.81% (18/22), branches 88.69% (259/292).

## Growth continuation — provider-neutral query planning

- Introduced `CollectionQueryPlanDTO` plus mirrored `CollectionQueryPlannerInterface` / `CollectionQueryPlanner` as the provider-neutral normalization boundary.
- The planner now resolves searchable fields, canonical supported filter operators, field-policy filtering, deterministic stable sorts, public projection, and cursor applicability exactly once.
- `DoctrineCollectionQueryProcessor` now consumes the normalized plan and no longer owns field-policy normalization; its responsibility is reduced to DQL/operator translation, cursor predicate construction, query execution, projection cleanup, cursor emission, diagnostics, and metrics.
- Canonical filter operator support (`eq`, `neq`, `lt`, `lte`, `gt`, `gte`) is enforced in the shared planner, preventing future providers from accidentally accepting provider-specific raw operators.
- `CollectionQueryPlanDTO::paginationMode()` provides canonical `offset` / `cursor` naming for provider consumers.
- Symfony service configuration now binds `CollectionQueryPlannerInterface` to `CollectionQueryPlanner`; extension coverage verifies the planner definition and alias.
- No artificial second backend was added. The abstraction is material because Doctrine now depends on the shared plan; a future provider can implement `CollectionQueryProcessorInterface` while reusing the same semantic planner.
- Added focused planner tests covering allowed/disallowed filters, unknown fields, stable identifier tie-breakers, projection, cursor shape matching, absent cursor, no effective sorts, and empty definitions. Added explicit Mercure custom-prefix coverage while closing existing quality debt.
- Final gates after canonical formatting: PHPUnit 23/23 with 131 assertions; PHPStan 0 errors; PHP-CS-Fixer 0 pending fixes; strict Composer validation/check-lock valid; Composer audit reports no security vulnerability advisories.
- Final Canon040 coverage: lines 96.33% (289/300), methods 80.00% (20/25), branches 89.58% (284/317).

## RC continuation — canonical stable sorts

- Re-read the current Collectioning README, Composer manifest, DI/quality configuration, complete current PHP namespace surface, PHPUnit suite, and target-local `.gating/AGENTS.md` before patching.
- Re-read Objecting, Cruding, Viewing, and Interfacing package contracts. Cruding already requires `collectioning/collection`; a reverse Collectioning -> Cruding dependency would be circular. Objecting entity packs and Viewing/Interfacing presentation surfaces remain boundary references rather than direct runtime dependencies of this lower-level component.
- Consulted authoritative Canonization rules `Canon004SubjectFolderPlacementRule`, `Canon018ComposerIdentityMappingRule`, `Canon019NoAlternativeLayerTaxonomyRule`, `Canon022StandaloneApplicationDependencyBaselineRule`, and `Canon045DevelopmentComposerRepositoryClosureRule`, plus Gating README/composer/AGENTS. Canon022 remains non-applicable because Collectioning is a component package rather than a standalone Symfony application.
- Market/maturity comparison against API Platform, Spatie Query Builder, and Hasura reinforced allowlisted query semantics, deterministic sorting, and pagination as baseline expectations; speculative aggregation/facet expansion remains post-RC.
- RC defect fixed: direct/programmatic DTO consumers could provide unsupported sort directions or duplicate sort fields. The Doctrine executor interpreted unknown directions as ascending while diagnostics retained the invalid token, and duplicate fields could produce contradictory ordering or an unrepresentable associative cursor shape.
- `CollectionQueryDTO::stableSorts()` now normalizes directions to lowercase `asc`/`desc`, discards unsupported directions, keeps the first valid occurrence of each field, then appends missing identifier tie-breakers. This keeps planner, executor, diagnostics, and cursor shape aligned.
- Added focused regression coverage and documented the canonical behavior in README.
- Verification after the patch: PHPUnit 24/24 with 133 assertions; PHPStan level 8 passes; PHP-CS-Fixer dry-run reports 0 fixable files; changed-file PHP lint passes; strict Composer validation had already passed in the same run. Coverage execution also passes: lines 96.40% (295/306), branches 89.50% (290/324), methods 76.00% (19/25), classes 62.50% (10/16).
- Growth remains separate: explicit cross-database null ordering, optional versioned/signed opaque cursors, and broader operators/aggregations only with provider-neutral policy and parity.

## Growth continuation — facet aggregation

- Added explicit provider-neutral facet contracts: `CollectionFacetDTO`, `CollectionFacetBucketDTO`, `CollectionFacetResultDTO`, and `CollectionFacetProcessorInterface`.
- Extended `CollectionFieldPolicyDTO` with trailing backward-compatible `facetable` capability. Doctrine definition derivation enables facets only for bounded/scalar-friendly field types and keeps text/blob/binary fields non-facetable by default.
- Added `DoctrineCollectionFacetProcessor`, reusing the canonical `CollectionQueryPlannerInterface` for current search/filter semantics. Facet aggregation ignores pagination/projection state, rejects fields not marked facetable, bounds bucket counts to 100, and supports optional missing-value counts.
- Facet requests default to excluding the facet field's own active filter, which supports standard faceted-navigation alternative counts while preserving all other active search/filter constraints. Callers may opt back into own-filter inclusion per facet.
- Symfony DI now binds `CollectionFacetProcessorInterface`; extension and Doctrine-definition tests cover the new contract.
- Added SQLite/Doctrine integration coverage for search/filter composition, own-filter exclusion, own-filter inclusion, missing counts, facet policy rejection, and bucket limits.
- Final gates: PHPUnit 27/27 with 158 assertions, PHPStan level 8 zero errors, PHP-CS-Fixer zero pending fixes. Coverage: lines 94.72% (359/379), methods 75.00% (24/32), branches 88.14% (342/388).

## Growth continuation — general aggregation and grouping

- Added provider-neutral `CollectionAggregationDTO`, `CollectionAggregationRowDTO`, `CollectionAggregationResultDTO`, and `CollectionAggregationProcessorInterface` contracts.
- Extended `CollectionFieldPolicyDTO` with trailing backward-compatible `aggregateFunctions`; Doctrine metadata derives `count` for all scalar fields, `min`/`max` for non-text/non-binary fields, and `sum`/`avg` for numeric fields.
- Added `DoctrineCollectionAggregationProcessor` for `count`, `sum`, `avg`, `min`, and `max`, reusing the canonical query planner for search/filter semantics while deliberately ignoring page/projection state.
- Optional grouping is restricted to facetable fields and duplicate/disallowed group dimensions are removed. Grouped responses are capped at 500 rows with one sentinel row used to set `CollectionAggregationResultDTO::truncated`, avoiding silent unbounded response growth.
- Aggregate names never become DQL aliases directly; internal aliases are generated, and field/function execution is constrained by fixed functions plus field policy allowlists.
- Added SQLite/Doctrine integration coverage for filtered global summaries, grouped summaries, policy rejection, and empty effective aggregation requests; extension/factory coverage now includes aggregation service and derived function policies.
- Final gates: PHPUnit 30/30 with 181 assertions, PHPStan level 8 zero errors, PHP-CS-Fixer zero pending fixes, strict Composer validation/check-lock valid, Composer audit clean, and changed-file PHP lint green. Coverage: lines 93.54% (449/480), methods 74.35% (29/39), branches 87.54% (450/514).

## Growth continuation — scoped reads and membership filters

- Added canonical `in` / `notIn` membership operators to the shared query planner and Doctrine-derived field policies. HTTP normalization accepts only non-empty scalar lists, and programmatic DTO planning rejects malformed membership lists or array-valued scalar operators.
- Doctrine collection, facet, and aggregation processors now execute membership predicates with bound list parameters, preserving one canonical filter language across rows, facets, and summaries.
- Added `CollectionDataScopeDTO` with explicit `currentPage`, `filtered`, and `selected` modes plus `CollectionScopedReaderInterface` / `CollectionScopedReader`.
- Filtered and selected scoped reads restart from page one, preserve canonical search/filter/sort/projection state, clear visible-grid cursor state, and iterate bounded `maxPageSize` pages while yielding incrementally. Selected scope appends explicit policy-validated selection filters, normally identifier `in` filters.
- Current-page scope preserves the original query exactly. Selected scope fails closed when no selection filter is supplied. Snapshot consistency across multiple page executions remains the caller/provider transaction responsibility rather than being implied by Collectioning.
- Added unit/integration coverage for membership request normalization, planner validation, Doctrine row execution, facet/aggregation consistency, current-page/filtered/selected scoped reads, and Symfony DI registration.
- Final gates: PHPUnit 39/39 with 223 assertions, PHPStan level 8 zero errors, PHP-CS-Fixer zero pending fixes, strict Composer validation/check-lock valid, Composer audit clean, and changed-file PHP lint green. Coverage: lines 93.71% (507/541), methods 73.80% (31/42), branches 87.87% (522/594).

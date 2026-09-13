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

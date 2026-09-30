<?php

declare(strict_types=1);

/** Produces auditable Canon042 inventories from repository-owned Collectioning test contracts. */

$root = dirname(__DIR__);

$contracts = [
    'functional' => [
        'runtime:standalone-http' => ['tests/Playwright/runtime.spec.ts', 'standalone Collectioning runtime boots through Symfony'],
    ],
    'behavioral' => [
        'query:search-filter-sort-projection-counts' => ['tests/Integration/DoctrineCollectionQueryProcessorTest.php', 'testProcessAppliesSearchFilterSortProjectionAndCounts'],
        'query:composite-cursor-pagination' => ['tests/Integration/DoctrineCollectionQueryProcessorTest.php', 'testProcessSupportsCompositeCursorPaginationWithProjection'],
        'facet:search-filter-buckets' => ['tests/Integration/DoctrineCollectionFacetProcessorTest.php', 'testFacetBucketsRespectSearchAndExcludeOwnFilter'],
        'aggregation:filtered-numeric' => ['tests/Integration/DoctrineCollectionAggregationProcessorTest.php', 'testComputesFilteredTotalsAndNumericAggregates'],
    ],
    'ui' => [],
    'critical' => [
        'critical:stable-cursor-boundary' => ['tests/Integration/DoctrineCollectionQueryProcessorTest.php', 'testProcessEmitsCursorWhenRequestedProjectionContainsNonProjectableSortField'],
        'critical:unknown-operator-rejection' => ['tests/Integration/DoctrineCollectionQueryProcessorTest.php', 'testProcessIgnoresUnknownExecutorOperatorEvenWhenPolicyListsIt'],
    ],
];

$dimensions = [];
foreach ($contracts as $dimension => $inventory) {
    $eligible = array_keys($inventory);
    $covered = [];
    foreach ($inventory as $identifier => [$relativePath, $needle]) {
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path)) {
            continue;
        }

        $contents = file_get_contents($path);
        if (false !== $contents && str_contains($contents, $needle)) {
            $covered[] = $identifier;
        }
    }

    $dimensions[$dimension] = ['eligible' => $eligible, 'covered' => $covered];
}

$target = $root.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'coverage'.DIRECTORY_SEPARATOR.'behavioral-ui.json';
$directory = dirname($target);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create behavioral coverage directory.\n");
    exit(1);
}

$payload = [
    'schema' => 'behavioral-ui-coverage-v2',
    'generatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
    'producer' => ['kind' => 'repository_script', 'script' => 'test:behavioral-coverage'],
    'dimensions' => $dimensions,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json) || false === file_put_contents($target, $json.PHP_EOL)) {
    fwrite(STDERR, "Unable to write behavioral coverage evidence.\n");
    exit(1);
}

echo "Behavioral/UI coverage evidence written to var/coverage/behavioral-ui.json.\n";

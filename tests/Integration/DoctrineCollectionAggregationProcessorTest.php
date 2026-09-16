<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Integration;

use App\Collectioning\DTO\CollectionAggregationDTO;
use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\Service\CollectionQueryPlanner;
use App\Collectioning\Service\DoctrineCollectionAggregationProcessor;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCollectionAggregationProcessorTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->entityManager = new EntityManager($connection, $configuration);

        $metadata = $this->entityManager->getClassMetadata(CollectioningAggregationFixture::class);
        (new SchemaTool($this->entityManager))->createSchema([$metadata]);

        $this->entityManager->persist(new CollectioningAggregationFixture(1, 'Alpha', 'north', 10));
        $this->entityManager->persist(new CollectioningAggregationFixture(2, 'Alpine', 'north', 20));
        $this->entityManager->persist(new CollectioningAggregationFixture(3, 'Alfred', 'south', 30));
        $this->entityManager->persist(new CollectioningAggregationFixture(4, 'Beta', 'south', 40));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testComputesFilteredTotalsAndNumericAggregates(): void
    {
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(1, 1),
            search: 'al',
            filters: [new CollectionFilterDTO('region', 'eq', 'north')],
        );

        $result = $this->processor()->process($this->definition(), $query, [
            new CollectionAggregationDTO('rows', 'count'),
            new CollectionAggregationDTO('sumAmount', 'sum', 'amount'),
            new CollectionAggregationDTO('avgAmount', 'avg', 'amount'),
            new CollectionAggregationDTO('minAmount', 'min', 'amount'),
            new CollectionAggregationDTO('maxAmount', 'max', 'amount'),
        ]);

        self::assertSame([], $result->groupBy);
        self::assertCount(1, $result->rows);
        self::assertSame('2', (string) $result->rows[0]->values['rows']);
        self::assertSame('30', (string) $result->rows[0]->values['sumAmount']);
        self::assertSame('15', rtrim(rtrim((string) $result->rows[0]->values['avgAmount'], '0'), '.'));
        self::assertSame('10', (string) $result->rows[0]->values['minAmount']);
        self::assertSame('20', (string) $result->rows[0]->values['maxAmount']);
    }

    public function testGroupsByFacetableFieldAndRejectsUnauthorizedOperations(): void
    {
        $result = $this->processor()->process($this->definition(), new CollectionQueryDTO(new CollectionPageDTO(1, 1)), [
            new CollectionAggregationDTO('rows', 'count'),
            new CollectionAggregationDTO('sumAmount', 'sum', 'amount'),
            new CollectionAggregationDTO('badSum', 'sum', 'name'),
            new CollectionAggregationDTO('badFn', 'median', 'amount'),
        ], ['region', 'name', 'region']);

        self::assertSame(['region'], $result->groupBy);
        self::assertCount(2, $result->rows);
        self::assertSame('north', $result->rows[0]->group['region']);
        self::assertSame('2', (string) $result->rows[0]->values['rows']);
        self::assertSame('30', (string) $result->rows[0]->values['sumAmount']);
        self::assertArrayNotHasKey('badSum', $result->rows[0]->values);
        self::assertArrayNotHasKey('badFn', $result->rows[0]->values);
        self::assertSame('south', $result->rows[1]->group['region']);
        self::assertSame('70', (string) $result->rows[1]->values['sumAmount']);
    }

    public function testReturnsEmptyResultWhenNoAggregationSurvivesPolicy(): void
    {
        $result = $this->processor()->process($this->definition(), new CollectionQueryDTO(new CollectionPageDTO(1, 25)), [
            new CollectionAggregationDTO('bad', 'sum', 'name'),
        ], ['region']);

        self::assertSame(['region'], $result->groupBy);
        self::assertSame([], $result->rows);
    }

    private function processor(): DoctrineCollectionAggregationProcessor
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        return new DoctrineCollectionAggregationProcessor($registry, new CollectionQueryPlanner());
    }

    private function definition(): CollectionDefinitionDTO
    {
        return new CollectionDefinitionDTO(CollectioningAggregationFixture::class, [
            new CollectionFieldPolicyDTO('id', filterable: true, sortable: true, facetable: true, aggregateFunctions: ['count', 'min', 'max', 'sum', 'avg']),
            new CollectionFieldPolicyDTO('name', searchable: true, filterable: true, sortable: true),
            new CollectionFieldPolicyDTO('region', filterable: true, sortable: true, facetable: true, aggregateFunctions: ['count', 'min', 'max']),
            new CollectionFieldPolicyDTO('amount', filterable: true, sortable: true, facetable: true, aggregateFunctions: ['count', 'min', 'max', 'sum', 'avg']),
        ], identifierFields: ['id']);
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'collectioning_aggregation_fixture')]
final class CollectioningAggregationFixture
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string', length: 100)]
        public string $name,
        #[ORM\Column(type: 'string', length: 32)]
        public string $region,
        #[ORM\Column(type: 'integer')]
        public int $amount,
    ) {
    }
}

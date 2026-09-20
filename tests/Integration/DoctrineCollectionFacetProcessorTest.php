<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Integration;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFacetDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\Service\CollectionQueryPlanner;
use App\Collectioning\Service\DoctrineCollectionFacetProcessor;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCollectionFacetProcessorTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->entityManager = new EntityManager($connection, $configuration);

        $metadata = $this->entityManager->getClassMetadata(CollectioningFacetFixture::class);
        (new SchemaTool($this->entityManager))->createSchema([$metadata]);

        $this->entityManager->persist(new CollectioningFacetFixture(1, 'Alpha', 'active', 'north'));
        $this->entityManager->persist(new CollectioningFacetFixture(2, 'Alpine', 'inactive', 'north'));
        $this->entityManager->persist(new CollectioningFacetFixture(3, 'Alfred', 'active', 'south'));
        $this->entityManager->persist(new CollectioningFacetFixture(4, 'Beta', null, 'south'));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testFacetBucketsRespectSearchAndExcludeOwnFilter(): void
    {
        $query = new CollectionQueryDTO(
            page: new CollectionPageDTO(1, 10),
            search: 'al',
            filters: [
                new CollectionFilterDTO('status', 'eq', 'active'),
                new CollectionFilterDTO('region', 'eq', 'north'),
            ],
        );

        $results = $this->processor()->process($this->definition(), $query, [
            new CollectionFacetDTO('status'),
            new CollectionFacetDTO('region'),
            new CollectionFacetDTO('name'),
        ]);

        self::assertCount(2, $results);
        self::assertSame('status', $results[0]->field);
        self::assertSame('active', $results[0]->buckets[0]->value);
        self::assertSame(1, $results[0]->buckets[0]->count);
        self::assertSame('inactive', $results[0]->buckets[1]->value);
        self::assertSame(1, $results[0]->buckets[1]->count);
        self::assertSame('region', $results[1]->field);
        self::assertSame('north', $results[1]->buckets[0]->value);
        self::assertSame(1, $results[1]->buckets[0]->count);
        self::assertSame('south', $results[1]->buckets[1]->value);
        self::assertSame(1, $results[1]->buckets[1]->count);
    }

    public function testFacetCanKeepOwnFilterAndCountMissingValues(): void
    {
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(1, 10),
            filters: [new CollectionFilterDTO('status', 'eq', 'active')],
        );

        $results = $this->processor()->process($this->definition(), $query, [
            new CollectionFacetDTO('status', includeMissing: true, excludeOwnFilter: false),
        ]);

        self::assertCount(1, $results);
        self::assertCount(1, $results[0]->buckets);
        self::assertSame('active', $results[0]->buckets[0]->value);
        self::assertSame(2, $results[0]->buckets[0]->count);
        self::assertSame(0, $results[0]->missingCount);
    }

    public function testFacetLimitIsBoundedAndMissingCountUsesOtherFilters(): void
    {
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(1, 10),
            filters: [new CollectionFilterDTO('region', 'eq', 'south')],
        );

        $results = $this->processor()->process($this->definition(), $query, [
            new CollectionFacetDTO('status', limit: 1, includeMissing: true),
        ]);

        self::assertCount(1, $results[0]->buckets);
        self::assertSame('active', $results[0]->buckets[0]->value);
        self::assertSame(1, $results[0]->buckets[0]->count);
        self::assertSame(1, $results[0]->missingCount);
    }

    public function testFacetRespectsMembershipFilters(): void
    {
        $definition = new CollectionDefinitionDTO(CollectioningFacetFixture::class, [
            new CollectionFieldPolicyDTO('id', filterable: true, sortable: true, facetable: true),
            new CollectionFieldPolicyDTO('status', filterable: true, sortable: true, facetable: true),
            new CollectionFieldPolicyDTO('region', filterable: true, sortable: true, filterOperators: ['eq', 'in'], facetable: true),
        ], identifierFields: ['id']);
        $query = new CollectionQueryDTO(
            new CollectionPageDTO(1, 10),
            filters: [new CollectionFilterDTO('region', 'in', ['north'])],
        );

        $results = $this->processor()->process($definition, $query, [new CollectionFacetDTO('status')]);

        self::assertCount(1, $results);
        self::assertCount(2, $results[0]->buckets);
        self::assertSame('active', $results[0]->buckets[0]->value);
        self::assertSame(1, $results[0]->buckets[0]->count);
        self::assertSame('inactive', $results[0]->buckets[1]->value);
        self::assertSame(1, $results[0]->buckets[1]->count);
    }

    private function processor(): DoctrineCollectionFacetProcessor
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        return new DoctrineCollectionFacetProcessor($registry, new CollectionQueryPlanner());
    }

    private function definition(): CollectionDefinitionDTO
    {
        return new CollectionDefinitionDTO(CollectioningFacetFixture::class, [
            new CollectionFieldPolicyDTO('id', filterable: true, sortable: true, facetable: true),
            new CollectionFieldPolicyDTO('name', searchable: true, filterable: true, sortable: true, facetable: false),
            new CollectionFieldPolicyDTO('status', filterable: true, sortable: true, facetable: true),
            new CollectionFieldPolicyDTO('region', filterable: true, sortable: true, facetable: true),
        ], identifierFields: ['id']);
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'collectioning_facet_fixture')]
final class CollectioningFacetFixture
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string', length: 100)]
        public string $name,
        #[ORM\Column(type: 'string', length: 32, nullable: true)]
        public ?string $status,
        #[ORM\Column(type: 'string', length: 32)]
        public string $region,
    ) {
    }
}

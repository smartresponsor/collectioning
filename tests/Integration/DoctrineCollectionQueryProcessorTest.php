<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Integration;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionFieldPolicyDTO;
use App\Collectioning\DTO\CollectionFilterDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionSortDTO;
use App\Collectioning\Service\DoctrineCollectionQueryProcessor;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCollectionQueryProcessorTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->entityManager = new EntityManager($connection, $configuration);

        $metadata = $this->entityManager->getClassMetadata(CollectioningProcessorFixture::class);
        (new SchemaTool($this->entityManager))->createSchema([$metadata]);

        $this->entityManager->persist(new CollectioningProcessorFixture(1, 'Alpha', 'active'));
        $this->entityManager->persist(new CollectioningProcessorFixture(2, 'Alpine', 'inactive'));
        $this->entityManager->persist(new CollectioningProcessorFixture(3, 'Alfred', 'active'));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testProcessAppliesSearchFilterSortProjectionAndCounts(): void
    {
        $query = new CollectionQueryDTO(
            page: new CollectionPageDTO(1, 10),
            search: 'AL',
            filters: [new CollectionFilterDTO('status', 'eq', 'active')],
            sorts: [new CollectionSortDTO('name', 'desc')],
            fields: ['id', 'name'],
        );

        $result = $this->processor()->process($this->definition(), $query);

        self::assertSame(3, $result->total);
        self::assertSame(2, $result->filteredTotal);
        self::assertSame([
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 3, 'name' => 'Alfred'],
        ], $result->items);
    }

    public function testProcessUsesStableIdentifierOrderingForUnspecifiedSort(): void
    {
        $query = new CollectionQueryDTO(new CollectionPageDTO(2, 1));

        $result = $this->processor()->process($this->definition(), $query);

        self::assertSame(3, $result->total);
        self::assertSame(3, $result->filteredTotal);
        self::assertCount(1, $result->items);
        self::assertInstanceOf(CollectioningProcessorFixture::class, $result->items[0]);
        self::assertSame(2, $result->items[0]->id);
    }

    public function testProcessSupportsCompositeCursorPaginationWithProjection(): void
    {
        $firstQuery = new CollectionQueryDTO(
            page: new CollectionPageDTO(1, 1),
            sorts: [new CollectionSortDTO('name', 'asc')],
            fields: ['name'],
        );

        $first = $this->processor()->process($this->definition(), $firstQuery);

        self::assertSame([['name' => 'Alfred']], $first->items);
        self::assertNotNull($first->nextCursor);
        $padding = (4 - strlen($first->nextCursor) % 4) % 4;
        $decoded = base64_decode(strtr($first->nextCursor.str_repeat('=', $padding), '-_', '+/'), true);
        self::assertNotFalse($decoded);
        $cursor = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($cursor);
        self::assertSame(['name' => 'Alfred', 'id' => 3], $cursor);

        $second = $this->processor()->process($this->definition(), new CollectionQueryDTO(
            page: new CollectionPageDTO(1, 1),
            sorts: [new CollectionSortDTO('name', 'asc')],
            fields: ['name'],
            cursor: $cursor,
        ));

        self::assertSame([['name' => 'Alpha']], $second->items);
        self::assertNotNull($second->nextCursor);
    }

    public function testProcessFallsBackToOffsetWhenCursorShapeDoesNotMatchSorts(): void
    {
        $result = $this->processor()->process($this->definition(), new CollectionQueryDTO(
            page: new CollectionPageDTO(2, 1),
            cursor: ['name' => 'Alpha'],
        ));

        self::assertCount(1, $result->items);
        self::assertInstanceOf(CollectioningProcessorFixture::class, $result->items[0]);
        self::assertSame(2, $result->items[0]->id);
    }

    public function testProcessAppliesTypedComparisonOperators(): void
    {
        $query = new CollectionQueryDTO(
            page: new CollectionPageDTO(1, 10),
            filters: [
                new CollectionFilterDTO('id', 'gte', 2),
                new CollectionFilterDTO('id', 'neq', 3),
            ],
        );

        $result = $this->processor()->process($this->definition(), $query);

        self::assertSame(3, $result->total);
        self::assertSame(1, $result->filteredTotal);
        self::assertCount(1, $result->items);
        self::assertInstanceOf(CollectioningProcessorFixture::class, $result->items[0]);
        self::assertSame(2, $result->items[0]->id);
    }

    public function testProcessIgnoresUnknownExecutorOperatorEvenWhenPolicyListsIt(): void
    {
        $definition = new CollectionDefinitionDTO(CollectioningProcessorFixture::class, [
            new CollectionFieldPolicyDTO('id', false, true, true, true, ['eq', 'raw']),
        ], identifierFields: ['id']);
        $query = new CollectionQueryDTO(
            page: new CollectionPageDTO(1, 10),
            filters: [new CollectionFilterDTO('id', 'raw', '>= 2')],
        );

        $result = $this->processor()->process($definition, $query);

        self::assertSame(3, $result->filteredTotal);
        self::assertCount(3, $result->items);
    }

    private function processor(): DoctrineCollectionQueryProcessor
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        return new DoctrineCollectionQueryProcessor($registry);
    }

    private function definition(): CollectionDefinitionDTO
    {
        return new CollectionDefinitionDTO(CollectioningProcessorFixture::class, [
            new CollectionFieldPolicyDTO('id', false, true, true, true, ['eq', 'neq', 'lt', 'lte', 'gt', 'gte']),
            new CollectionFieldPolicyDTO('name', true, true, true, true, ['eq', 'neq']),
            new CollectionFieldPolicyDTO('status', false, true, true, true, ['eq', 'neq']),
        ], identifierFields: ['id']);
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'collectioning_processor_fixture')]
final class CollectioningProcessorFixture
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string', length: 100)]
        public string $name,
        #[ORM\Column(type: 'string', length: 32)]
        public string $status,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Collectioning\Tests\Unit;

use App\Collectioning\DTO\CollectionDefinitionDTO;
use App\Collectioning\DTO\CollectionPageDTO;
use App\Collectioning\DTO\CollectionQueryDTO;
use App\Collectioning\DTO\CollectionResultDTO;
use App\Collectioning\Service\CollectionRequestReader;
use App\Collectioning\ServiceInterface\CollectionDefinitionFactoryInterface;
use App\Collectioning\ServiceInterface\CollectionQueryProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionQueryRequestResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CollectionRequestReaderTest extends TestCase
{
    public function testReadResolvesAndProcessesCurrentRequest(): void
    {
        $request = Request::create('/items');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $definition = new CollectionDefinitionDTO(\stdClass::class, []);
        $query = new CollectionQueryDTO(new CollectionPageDTO());
        $result = new CollectionResultDTO([], 0, 0, $query->page);

        $definitionFactory = $this->createMock(CollectionDefinitionFactoryInterface::class);
        $definitionFactory->expects(self::once())
            ->method('create')
            ->with(\stdClass::class)
            ->willReturn($definition);

        $queryResolver = $this->createMock(CollectionQueryRequestResolverInterface::class);
        $queryResolver->expects(self::once())
            ->method('resolve')
            ->with($request, $definition)
            ->willReturn($query);

        $queryProcessor = $this->createMock(CollectionQueryProcessorInterface::class);
        $queryProcessor->expects(self::once())
            ->method('process')
            ->with($definition, $query)
            ->willReturn($result);

        $reader = new CollectionRequestReader($requestStack, $definitionFactory, $queryResolver, $queryProcessor);

        self::assertSame($result, $reader->read(\stdClass::class));
    }

    public function testReadRequiresCurrentHttpRequest(): void
    {
        $reader = new CollectionRequestReader(
            new RequestStack(),
            $this->createStub(CollectionDefinitionFactoryInterface::class),
            $this->createStub(CollectionQueryRequestResolverInterface::class),
            $this->createStub(CollectionQueryProcessorInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Collection request reader requires an active HTTP request.');

        $reader->read(\stdClass::class);
    }
}

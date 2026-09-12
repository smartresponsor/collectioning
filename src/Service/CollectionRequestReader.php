<?php

declare(strict_types=1);

namespace App\Collectioning\Service;

use App\Collectioning\DTO\CollectionResultDTO;
use App\Collectioning\ServiceInterface\CollectionDefinitionFactoryInterface;
use App\Collectioning\ServiceInterface\CollectionQueryProcessorInterface;
use App\Collectioning\ServiceInterface\CollectionQueryRequestResolverInterface;
use App\Collectioning\ServiceInterface\CollectionRequestReaderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CollectionRequestReader implements CollectionRequestReaderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private CollectionDefinitionFactoryInterface $definitionFactory,
        private CollectionQueryRequestResolverInterface $queryResolver,
        private CollectionQueryProcessorInterface $queryProcessor,
    ) {
    }

    public function read(string $entityClass): CollectionResultDTO
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \RuntimeException('Collection request reader requires an active HTTP request.');
        }

        $definition = $this->definitionFactory->create($entityClass);
        $query = $this->queryResolver->resolve($request, $definition);

        return $this->queryProcessor->process($definition, $query);
    }
}

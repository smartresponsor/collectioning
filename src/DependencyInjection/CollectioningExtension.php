<?php

declare(strict_types=1);

namespace App\Collectioning\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CollectioningExtension extends Extension
{
    /** @param list<array<string, mixed>> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'collectioning';
    }
}

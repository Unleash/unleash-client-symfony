<?php

namespace Unleash\Client\Bundle\DependencyInjection\Compiler;

use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @todo Make internal in next major
 */
final class CacheServiceResolverCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $configs = $container->getParameter('unleash.client.internal.service_configs');
        assert(is_array($configs));

        $cacheServiceName = $configs['cache_service'];
        if ($container->hasAlias($cacheServiceName)) {
            $cacheServiceName = $container->getAlias($cacheServiceName);
        }
        $definition = $container->getDefinition($cacheServiceName);
        $class = $definition->getClass();
        assert(is_string($class));
        if (!is_a($class, CacheInterface::class, true)) {
            if (is_a($class, CacheItemPoolInterface::class, true)) {
                $definition = new Definition(Psr16Cache::class);
                $definition
                    ->addArgument(new Reference($cacheServiceName));
                $container->setDefinition('unleash.client.internal.cache', $definition);
            } else {
                throw new InvalidConfigurationException('The cache service must implement either ' . CacheInterface::class . ' or ' . CacheItemPoolInterface::class . ' interfaces');
            }
        } else {
            $container->setAlias('unleash.client.internal.cache', $cacheServiceName);
        }
    }
}

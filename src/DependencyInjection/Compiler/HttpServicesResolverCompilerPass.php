<?php

namespace Unleash\Client\Bundle\DependencyInjection\Compiler;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @todo Make internal in next major
 */
final class HttpServicesResolverCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $configs = $container->getParameter('unleash.client.internal.service_configs');
        assert(is_array($configs));

        $httpClientServiceName = $configs['http_client_service'];
        $definition = $container->getDefinition($httpClientServiceName);
        $class = $definition->getClass();
        assert(is_string($class));
        if (!is_a($class, ClientInterface::class, true)) {
            if (is_a($class, HttpClientInterface::class, true)) {
                $definition = new Definition(Psr18Client::class);
                $definition
                    ->addArgument(new Reference($httpClientServiceName))
                    ->addArgument(new Reference('unleash.client.internal.request_factory'));
                $container->setDefinition('unleash.client.internal.http_client', $definition);
            } else {
                throw new InvalidConfigurationException('The http client service must implement either ' . ClientInterface::class . ' or ' . HttpClientInterface::class . ' interfaces');
            }
        } else {
            $container->setAlias('unleash.client.internal.http_client', $httpClientServiceName);
        }

        $requestFactoryServiceName = $configs['request_factory_service'];
        if ($requestFactoryServiceName === null) {
            if ($container->hasDefinition('nyholm.psr7.psr17_factory')) {
                $requestFactoryServiceName = 'nyholm.psr7.psr17_factory';
            } elseif ($container->hasDefinition('http_discovery.psr17_factory')) {
                $requestFactoryServiceName = 'http_discovery.psr17_factory';
            } else {
                throw new InvalidConfigurationException('Cannot find any supported default request factory service, tried nyholm and http_discovery');
            }
        }
        $definition = $container->getDefinition($requestFactoryServiceName);
        $class = $definition->getClass();
        assert(is_string($class));
        if (!is_a($class, RequestFactoryInterface::class, true)) {
            throw new InvalidConfigurationException('The request factory service must implement ' . RequestFactoryInterface::class);
        }
        $container->setAlias('unleash.client.internal.request_factory', $requestFactoryServiceName);
    }
}

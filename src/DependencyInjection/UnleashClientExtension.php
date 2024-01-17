<?php

namespace Unleash\Client\Bundle\DependencyInjection;

use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Twig\Extension\ExtensionInterface;
use Unleash\Client\Strategy\StrategyHandler;

/**
 * @internal
 */
final class UnleashClientExtension extends Extension
{
    /**
     * @param array<string,mixed> $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('autowiring.yaml');
        if (interface_exists(ExtensionInterface::class)) {
            $loader->load('twig.yaml');
        }

        $configs = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $container->setParameter('unleash.client.internal.service_configs', [
            'http_client_service' => $configs['http_client_service'],
            'request_factory_service' => $configs['request_factory_service'],
            'cache_service' => $configs['cache_service'],
        ]);
        $container->setParameter('unleash.client.internal.app_url', $configs['app_url'] ?? '');
        $container->setParameter('unleash.client.internal.instance_id', $configs['instance_id'] ?? '');
        $container->setParameter('unleash.client.internal.app_name', $configs['app_name'] ?? '');
        $container->setParameter('unleash.client.internal.cache_ttl', $configs['cache_ttl']);
        $container->setParameter('unleash.client.internal.metrics_send_interval', $configs['metrics_send_interval']);
        $container->setParameter('unleash.client.internal.metrics_enabled', $configs['metrics_enabled']);
        $container->setParameter('unleash.client.internal.custom_headers', $configs['custom_headers']);
        $container->setParameter('unleash.client.internal.auto_registration', $configs['auto_registration']);
        $container->setParameter('unleash.client.internal.user_id_field', $configs['context']['user_id_field']);
        $container->setParameter('unleash.client.internal.custom_properties', $configs['context']['custom_properties']);
        $container->setParameter('unleash.client.internal.twig_functions_enabled', $configs['twig']['functions']);
        $container->setParameter('unleash.client.internal.twig_filters_enabled', $configs['twig']['filters']);
        $container->setParameter('unleash.client.internal.twig_tests_enabled', $configs['twig']['tests']);
        $container->setParameter('unleash.client.internal.twig_tags_enabled', $configs['twig']['tags']);
        $container->setParameter('unleash.client.internal.disabled_strategies', $configs['disabled_strategies']);
        $container->setParameter('unleash.client.internal.bootstrap', $configs['bootstrap']);
        $container->setParameter('unleash.client.internal.fetching_enabled', $configs['fetching_enabled']);
        $container->setParameter('unleash.client.internal.stale_ttl', $configs['stale_ttl']);

        if (class_exists(ExpressionLanguage::class)) {
            $definition = new Definition(ExpressionLanguage::class);
            $container->setDefinition('unleash.client.internal.expression_language', $definition);
        }
    }

    /**
     * @param array<mixed> $config
     *
     * @throws ReflectionException
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $handlerNames = [];
        foreach ($this->getDefaultStrategyHandlers($container) as $defaultStrategyHandler) {
            $reflection = new ReflectionClass($defaultStrategyHandler);
            $instance = $reflection->newInstanceWithoutConstructor();
            assert($instance instanceof StrategyHandler);
            $handlerNames[] = $instance->getStrategyName();
        }

        return new Configuration($handlerNames);
    }

    /**
     * @return array<string,string>
     */
    private function getDefaultStrategyHandlers(ContainerBuilder $container): array
    {
        $result = [];
        foreach ($container->findTaggedServiceIds('unleash.client.built_in_strategy_handler') as $handler => $tags) {
            $definition = $container->getDefinition($handler);
            $class = $definition->getClass();
            assert(is_string($class));
            $result[$handler] = $class;
        }

        return $result;
    }
}

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
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Twig\Extension\ExtensionInterface;
use Unleash\Client\Bundle\DependencyInjection\Dsn\LateBoundDsnParameter;
use Unleash\Client\Bundle\DependencyInjection\Dsn\StaticStringableParameter;
use Unleash\Client\Strategy\StrategyHandler;

/**
 * @internal
 */
final class UnleashClientExtension extends Extension
{
    /**
     * @var bool
     */
    private $servicesYamlLoaded = false;

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
        $this->servicesYamlLoaded = true;

        $configs = $this->processConfiguration($this->getConfiguration([], $container), $configs);

        $container->setParameter('unleash.client.internal.service_configs', [
            'http_client_service' => $configs['http_client_service'],
            'request_factory_service' => $configs['request_factory_service'],
            'cache_service' => $configs['cache_service'],
        ]);

        $dsn = $configs['dsn'] ?? null;
        if ($dsn !== null) {
            if ($this->isEnvPlaceholder($dsn, $container)) {
                $envName = $container->resolveEnvPlaceholders($dsn, '%s');
                $container->setDefinition('unleash.client.internal.app_url', new Definition(LateBoundDsnParameter::class, [$envName, 'url']));
                $container->setDefinition('unleash.client.internal.instance_id', new Definition(LateBoundDsnParameter::class, [$envName, 'instance_id']));
                $container->setDefinition('unleash.client.internal.app_name', new Definition(LateBoundDsnParameter::class, [$envName, 'app_name']));
            } else {
                $details = $this->parseDsn($dsn);
                $container->setDefinition('unleash.client.internal.app_url', new Definition(StaticStringableParameter::class, [$details['url'] ?? '']));
                $container->setDefinition('unleash.client.internal.instance_id', new Definition(StaticStringableParameter::class, [$details['instanceId'] ?? '']));
                $container->setDefinition('unleash.client.internal.app_name', new Definition(StaticStringableParameter::class, [$details['appName'] ?? '']));
            }
        } else {
            $container->setDefinition('unleash.client.internal.app_url', new Definition(StaticStringableParameter::class, [$configs['app_url'] ?? '']));
            $container->setDefinition('unleash.client.internal.instance_id', new Definition(StaticStringableParameter::class, [$configs['instance_id'] ?? '']));
            $container->setDefinition('unleash.client.internal.app_name', new Definition(StaticStringableParameter::class, [$configs['app_name'] ?? '']));
        }

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
        if (!$this->servicesYamlLoaded) {
            $loader->load('services.yaml');
            $this->servicesYamlLoaded = true;
        }

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

    /**
     * @return array{url: string|null, instanceId: string|null, appName: string|null}
     */
    private function parseDsn(string $dsn): array
    {
        $query = parse_url($dsn, PHP_URL_QUERY);
        assert(is_string($query));
        $instanceUrl = str_replace("?{$query}", '', $dsn);
        if (strpos($instanceUrl, '%3F') !== false) {
            $instanceUrl = urldecode($instanceUrl);
        }
        parse_str($query, $queryParts);

        $instanceId = $queryParts['instance_id'] ?? null;
        $appName = $queryParts['app_name'] ?? null;

        assert(is_string($instanceId));
        assert(is_string($appName));

        return [
            'url' => $instanceUrl,
            'instanceId' => $instanceId,
            'appName' => $appName,
        ];
    }

    private function isEnvPlaceholder(string $value, ContainerBuilder $container): bool
    {
        $bag = $container->getParameterBag();
        if (!$bag instanceof EnvPlaceholderParameterBag) {
            return false;
        }
        foreach ($bag->getEnvPlaceholders() as $placeholders) {
            if (in_array($value, $placeholders, true)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace Unleash\Client\Bundle\DependencyInjection;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Extension\ExtensionInterface;
use Unleash\Client\Bootstrap\BootstrapProvider;

/**
 * @todo Make internal in next major
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * @var array<string>
     * @readonly
     */
    private array $defaultStrategyNames;
    /**
     * @param array<string> $defaultStrategyNames
     */
    public function __construct(array $defaultStrategyNames)
    {
        $this->defaultStrategyNames = $defaultStrategyNames;
    }
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('unleash_client');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('dsn')
                    ->info('You can provide the connection details as a DSN instead of app_url, instance_id and app_name. DSN takes precedence over individual parameters.')
                    ->example('https://localhost:4242/api?instance_id=myCoolApp-Server1&app_name=myCoolApp')
                    ->defaultNull()
                ->end()
                ->scalarNode('app_url')
                    ->info('The application api URL')
                    ->defaultNull()
                ->end()
                ->scalarNode('instance_id')
                    ->info('The instance ID, for Unleash it can be anything, for GitLab it must be the generated value')
                    ->defaultNull()
                ->end()
                ->scalarNode('app_name')
                    ->info('Application name, for Unleash it can be anything, for GitLab it should be a GitLab environment name')
                    ->defaultNull()
                ->end()
                ->arrayNode('context')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('user_id_field')
                            ->info('The field name to use as user id, if set to null getUserIdentifier() or getUsername() method will be called')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('custom_properties')
                            ->info('Any additional context properties')
                            ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('fetching_enabled')
                    ->info('Whether to enable communication with Unleash server or not. If you set it to false you must also provide a bootstrap.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('http_client_service')
                    ->info('The http client service, must implement the ' . ClientInterface::class . ' or ' . HttpClientInterface::class . ' interface')
                    ->defaultValue('psr18.http_client')
                ->end()
                ->scalarNode('request_factory_service')
                    ->info('The request factory service, must implement the ' . RequestFactoryInterface::class . ' interface. Providing null means autodetect between supported default services.')
                    ->defaultNull()
                ->end()
                ->scalarNode('cache_service')
                    ->info('The cache service, must implement the ' . CacheInterface::class . ' or ' . CacheItemPoolInterface::class . ' interface')
                    ->defaultValue('cache.app')
                ->end()
                ->scalarNode('bootstrap')
                    ->info(sprintf('Default bootstrap in case contacting Unleash servers fails. Can be a path to file (prefixed with file://) or a service implementing %s (prefixed with @)', BootstrapProvider::class))
                    ->defaultNull()
                ->end()
                ->arrayNode('disabled_strategies')
                    ->info('Disabled default strategies, must be one of: ' . implode(', ', $this->defaultStrategyNames))
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->integerNode('metrics_send_interval')
                    ->info('The interval at which to send metrics to the server in milliseconds')
                    ->defaultValue(30_000)
                ->end()
                ->booleanNode('metrics_enabled')
                    ->info('Whether to allow sending feature usage metrics to your instance of Unleash, set this to false for GitLab')
                    ->defaultTrue()
                ->end()
                ->booleanNode('auto_registration')
                    ->info('Whether to allow automatic client registration on client initialization, set this to false for GitLab')
                    ->defaultTrue()
                ->end()
                ->integerNode('cache_ttl')
                    ->info('The time in seconds the features will stay valid in cache')
                    ->defaultValue(30)
                ->end()
                ->integerNode('stale_ttl')
                    ->info('The maximum age (in seconds) old features will be served from cache if http request fails for some reason')
                    ->defaultValue(30 * 60)
                ->end()
                ->arrayNode('custom_headers')
                    ->info('Additional headers to use in http client, for Unleash "Authorization" is required')
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->arrayNode('twig')
                    ->addDefaultsIfNotSet()
                    ->info('Enable or disable twig function/filter/tests')
                    ->children()
                        ->booleanNode('functions')
                            ->info('Enables the "feature_is_enabled" and "feature_variant" twig functions')
                            ->defaultValue(interface_exists(ExtensionInterface::class))
                        ->end()
                        ->booleanNode('filters')
                            ->info('Enables the "feature_is_enabled" and "feature_variant" filters')
                            ->defaultValue(interface_exists(ExtensionInterface::class))
                        ->end()
                        ->booleanNode('tests')
                            ->info('Enables the "enabled" test, allowing you to write {% if "featureName" is enabled %}')
                            ->defaultValue(interface_exists(ExtensionInterface::class))
                        ->end()
                        ->booleanNode('tags')
                            ->info('Enables the "feature" twig tag')
                            ->defaultValue(interface_exists(ExtensionInterface::class))
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

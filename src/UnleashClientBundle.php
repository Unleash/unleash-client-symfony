<?php

namespace Unleash\Client\Bundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Unleash\Client\Bootstrap\BootstrapProvider;
use Unleash\Client\Bundle\DependencyInjection\Compiler\BootstrapResolver;
use Unleash\Client\Bundle\DependencyInjection\Compiler\CacheServiceResolverCompilerPass;
use Unleash\Client\Bundle\DependencyInjection\Compiler\HttpServicesResolverCompilerPass;
use Unleash\Client\Strategy\StrategyHandler;

final class UnleashClientBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(StrategyHandler::class)
            ->addTag('unleash.client.strategy_handler');
        $container->registerForAutoconfiguration(BootstrapProvider::class)
            ->addTag('unleash.client.bootstrap_provider');

        $container->addCompilerPass(
            new HttpServicesResolverCompilerPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            -100000
        );
        $container->addCompilerPass(
            new CacheServiceResolverCompilerPass(),
            PassConfig::TYPE_OPTIMIZE
        );
        $container->addCompilerPass(new BootstrapResolver());
    }
}

<?php

namespace Unleash\Client\Bundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Unleash\Client\Bundle\DependencyInjection\Compiler\CacheServiceResolverCompilerPass;
use Unleash\Client\Bundle\DependencyInjection\Compiler\HttpServicesResolverCompilerPass;
use Unleash\Client\Strategy\StrategyHandler;

final class UnleashSymfonyClientBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(StrategyHandler::class)
            ->addTag('rikudou.unleash.strategy_handler');
        $container->addCompilerPass(
            new HttpServicesResolverCompilerPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            -100_000
        );
        $container->addCompilerPass(
            new CacheServiceResolverCompilerPass(),
            PassConfig::TYPE_OPTIMIZE
        );
    }
}

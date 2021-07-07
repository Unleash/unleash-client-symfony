<?php

namespace Rikudou\Unleash\Bundle;

use Rikudou\Unleash\Bundle\DependencyInjection\Compiler\CacheServiceResolverCompilerPass;
use Rikudou\Unleash\Bundle\DependencyInjection\Compiler\HttpServicesResolverCompilerPass;
use Rikudou\Unleash\Strategy\StrategyHandler;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RikudouUnleashSdkBundle extends Bundle
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

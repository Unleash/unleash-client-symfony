<?php

declare(strict_types=1);

namespace Unleash\Client\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Unleash\Client\Bundle\DependencyInjection\Dsn\LateBoundDsnParameter;

final class ProcessLateBoundParameters implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $processors = array_map(
            fn (string $serviceName) => new Reference($serviceName),
            array_keys($container->findTaggedServiceIds('container.env_var_processor')),
        );

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() !== LateBoundDsnParameter::class) {
                continue;
            }
            $definition->setArgument('$preprocessors', $processors);
        }
    }
}

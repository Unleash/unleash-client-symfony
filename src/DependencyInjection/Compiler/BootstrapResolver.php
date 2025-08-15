<?php

namespace Unleash\Client\Bundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Unleash\Client\Bootstrap\EmptyBootstrapProvider;
use Unleash\Client\Bootstrap\FileBootstrapProvider;

/**
 * @todo Make internal in next major
 */
final class BootstrapResolver implements CompilerPassInterface
{
    private const TAG = 'unleash.client.bootstrap_provider';

    private const INTERNAL_SERVICE_NAME = 'unleash.client.internal.bootstrap_service';

    public function process(ContainerBuilder $container): void
    {
        $bootstrap = $container->getParameter('unleash.client.internal.bootstrap');
        assert(is_string($bootstrap) || $bootstrap === null);

        if ($bootstrap === null) {
            $this->registerTaggedService($container);
        } elseif (str_starts_with($bootstrap, 'file://')) {
            $this->registerFileService($bootstrap, $container);
        } elseif (str_starts_with($bootstrap, '@')) {
            $this->registerServiceService(substr($bootstrap, 1), $container);
        } else {
            throw new LogicException("Unknown value for bootstrap: {$bootstrap}");
        }
    }

    private function registerTaggedService(ContainerBuilder $container): void
    {
        $serviceIds = array_keys($container->findTaggedServiceIds(self::TAG));
        if (!count($serviceIds)) {
            $this->registerEmptyService($container);

            return;
        }

        $serviceId = $serviceIds[array_key_first($serviceIds)];
        if (count($serviceIds) > 1) {
            trigger_error(
                sprintf("More than one service with tag '%s' found, choosing service '%s'", self::TAG, $serviceId),
                E_USER_WARNING
            );
        }

        $this->registerServiceService($serviceId, $container);
    }

    private function registerFileService(string $file, ContainerBuilder $container): void
    {
        $definition = new Definition(FileBootstrapProvider::class, [$file]);
        $container->setDefinition(self::INTERNAL_SERVICE_NAME, $definition);
    }

    private function registerServiceService(string $serviceId, ContainerBuilder $container): void
    {
        $container->setAlias(self::INTERNAL_SERVICE_NAME, $serviceId);
    }

    private function registerEmptyService(ContainerBuilder $container): void
    {
        $definition = new Definition(EmptyBootstrapProvider::class);
        $container->setDefinition(self::INTERNAL_SERVICE_NAME, $definition);
    }
}

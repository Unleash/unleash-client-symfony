<?php

namespace Unleash\Client\Bundle\Unleash;

use Generator;
use Unleash\Client\Client\RegistrationService;
use Unleash\Client\Configuration\Context;
use Unleash\Client\Configuration\UnleashConfiguration;
use Unleash\Client\DefaultUnleash;
use Unleash\Client\DTO\Variant;
use Unleash\Client\Metrics\MetricsHandler;
use Unleash\Client\Repository\UnleashRepository;
use Unleash\Client\Strategy\StrategyHandler;
use Unleash\Client\Unleash;
use Unleash\Client\Variant\VariantHandler;

final class UnleashDecorator implements Unleash
{
    /**
     * @var \Unleash\Client\Unleash
     */
    private $proxy;
    /**
     * @var string[]
     */
    private $disabledHandlers;
    /**
     * @param array<string>             $disabledHandlers
     * @param iterable<StrategyHandler> $strategyHandlers
     */
    public function __construct(array $disabledHandlers, iterable $strategyHandlers, UnleashRepository $repository, RegistrationService $registrationService, UnleashConfiguration $configuration, MetricsHandler $metricsHandler, VariantHandler $variantHandler)
    {
        $this->disabledHandlers = $disabledHandlers;
        $strategyHandlers = $this->filter($strategyHandlers);
        $this->proxy = new DefaultUnleash(
            iterator_to_array($strategyHandlers),
            $repository,
            $registrationService,
            $configuration,
            $metricsHandler,
            $variantHandler
        );
    }

    public function isEnabled(string $featureName, ?Context $context = null, bool $default = false): bool
    {
        return $this->proxy->isEnabled($featureName, $context, $default);
    }

    public function getVariant(string $featureName, ?Context $context = null, ?Variant $fallbackVariant = null): Variant
    {
        return $this->proxy->getVariant($featureName, $context, $fallbackVariant);
    }

    public function register(): bool
    {
        return $this->proxy->register();
    }

    /**
     * @param iterable<StrategyHandler> $strategyHandlers
     *
     * @return Generator<StrategyHandler>
     */
    private function filter(iterable $strategyHandlers): Generator
    {
        foreach ($strategyHandlers as $strategyHandler) {
            if (!in_array($strategyHandler->getStrategyName(), $this->disabledHandlers, true)) {
                yield $strategyHandler;
            }
        }
    }
}

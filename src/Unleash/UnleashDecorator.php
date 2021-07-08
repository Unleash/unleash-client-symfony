<?php

namespace Rikudou\Unleash\Bundle\Unleash;

use Generator;
use Rikudou\Unleash\Client\RegistrationService;
use Rikudou\Unleash\Configuration\Context;
use Rikudou\Unleash\Configuration\UnleashConfiguration;
use Rikudou\Unleash\DefaultUnleash;
use Rikudou\Unleash\DTO\Variant;
use Rikudou\Unleash\Metrics\MetricsHandler;
use Rikudou\Unleash\Repository\UnleashRepository;
use Rikudou\Unleash\Strategy\StrategyHandler;
use Rikudou\Unleash\Unleash;
use Rikudou\Unleash\Variant\VariantHandler;

final class UnleashDecorator implements Unleash
{
    private Unleash $proxy;

    /**
     * @param array<string>             $disabledHandlers
     * @param iterable<StrategyHandler> $strategyHandlers
     */
    public function __construct(
        private array $disabledHandlers,
        iterable $strategyHandlers,
        UnleashRepository $repository,
        RegistrationService $registrationService,
        UnleashConfiguration $configuration,
        MetricsHandler $metricsHandler,
        VariantHandler $variantHandler,
    ) {
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

<?php

namespace Rikudou\Unleash\Bundle\Twig;

use Rikudou\Unleash\Configuration\Context;
use Rikudou\Unleash\DTO\Variant;
use Rikudou\Unleash\Unleash;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

final class UnleashTwigExtension extends AbstractExtension
{
    public function __construct(
        private Unleash $unleash,
        private bool $functionsEnabled,
        private bool $filtersEnabled,
        private bool $testsEnabled,
    ) {
    }

    /**
     * @return array<TwigFunction>
     */
    public function getFunctions(): array
    {
        if (!$this->functionsEnabled) {
            return [];
        }

        return [
            new TwigFunction('feature_is_enabled', [$this, 'isEnabled']),
            new TwigFunction('feature_variant', [$this, 'getVariant']),
        ];
    }

    /**
     * @return array<TwigFilter>
     */
    public function getFilters(): array
    {
        if (!$this->filtersEnabled) {
            return [];
        }

        return [
            new TwigFilter('feature_is_enabled', [$this, 'isEnabled']),
        ];
    }

    /**
     * @return array<TwigTest>
     */
    public function getTests(): array
    {
        if (!$this->testsEnabled) {
            return [];
        }

        return [
            new TwigTest('enabled', [$this, 'isEnabled']),
        ];
    }

    public function isEnabled(string $featureName, ?Context $context = null, bool $default = false): bool
    {
        return $this->unleash->isEnabled($featureName, $context, $default);
    }

    public function getVariant(string $featureName, ?Context $context = null, ?Variant $fallback = null): Variant
    {
        return $this->unleash->getVariant($featureName, $context, $fallback);
    }
}

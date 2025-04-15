<?php

namespace Unleash\Client\Bundle\Twig;

use JetBrains\PhpStorm\Pure;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

final class UnleashTwigExtension extends AbstractExtension
{
    public function __construct(
        private bool $functionsEnabled,
        private bool $filtersEnabled,
        private bool $testsEnabled,
        private bool $tagsEnabled,
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
            new TwigFunction('feature_is_enabled', [UnleashTwigRuntime::class, 'isEnabled']),
            new TwigFunction('feature_variant', [UnleashTwigRuntime::class, 'getVariant']),
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
            new TwigFilter('feature_is_enabled', [UnleashTwigRuntime::class, 'isEnabled']),
            new TwigFilter('feature_variant', [UnleashTwigRuntime::class, 'getVariant']),
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
            new TwigTest('enabled', [UnleashTwigRuntime::class, 'isEnabled']),
        ];
    }

    /**
     * @return array<FeatureTagTokenParser>
     */
    #[Pure]
    public function getTokenParsers(): array
    {
        if (!$this->tagsEnabled) {
            return [];
        }

        return [
            new FeatureTagTokenParser(get_class($this)),
        ];
    }
}

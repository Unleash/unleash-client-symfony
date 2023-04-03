<?php

namespace Unleash\Client\Bundle\Twig;

use JetBrains\PhpStorm\Pure;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Variant;
use Unleash\Client\Unleash;

final class UnleashTwigExtension extends AbstractExtension
{
    /**
     * @var \Unleash\Client\Unleash
     */
    private $unleash;
    /**
     * @var bool
     */
    private $functionsEnabled;
    /**
     * @var bool
     */
    private $filtersEnabled;
    /**
     * @var bool
     */
    private $testsEnabled;
    /**
     * @var bool
     */
    private $tagsEnabled;
    public function __construct(Unleash $unleash, bool $functionsEnabled, bool $filtersEnabled, bool $testsEnabled, bool $tagsEnabled)
    {
        $this->unleash = $unleash;
        $this->functionsEnabled = $functionsEnabled;
        $this->filtersEnabled = $filtersEnabled;
        $this->testsEnabled = $testsEnabled;
        $this->tagsEnabled = $tagsEnabled;
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
            new TwigFilter('feature_variant', [$this, 'getVariant']),
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

    /**
     * @return array<FeatureTagTokenParser>
     */
    public function getTokenParsers(): array
    {
        if (!$this->tagsEnabled) {
            return [];
        }
        return [
            new FeatureTagTokenParser(get_class($this)),
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

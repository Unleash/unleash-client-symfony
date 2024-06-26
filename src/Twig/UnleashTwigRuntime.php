<?php

namespace Unleash\Client\Bundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Variant;
use Unleash\Client\Unleash;

final readonly class UnleashTwigRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Unleash $unleash,
    ) {
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

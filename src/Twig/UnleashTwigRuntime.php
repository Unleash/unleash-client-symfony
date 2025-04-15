<?php

namespace Unleash\Client\Bundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Variant;
use Unleash\Client\Unleash;

final class UnleashTwigRuntime implements RuntimeExtensionInterface
{
    /**
     * @readonly
     */
    private Unleash $unleash;
    public function __construct(Unleash $unleash)
    {
        $this->unleash = $unleash;
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

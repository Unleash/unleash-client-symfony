<?php

namespace Unleash\Client\Bundle\Context;

use Unleash\Client\Configuration\Context;
use Unleash\Client\ContextProvider\UnleashContextProvider;

final class SymfonyContextProvider implements UnleashContextProvider
{
    public function __construct(
        private readonly SymfonyUnleashContext $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

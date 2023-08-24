<?php

namespace Unleash\Client\Bundle\Context;

use Unleash\Client\Configuration\Context;
use Unleash\Client\ContextProvider\UnleashContextProvider;

final readonly class SymfonyContextProvider implements UnleashContextProvider
{
    public function __construct(
        private SymfonyUnleashContext $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

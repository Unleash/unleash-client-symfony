<?php

namespace Unleash\Client\Bundle\Context;

use Unleash\Client\Configuration\Context;
use Unleash\Client\ContextProvider\UnleashContextProvider;

final class SymfonyContextProvider implements UnleashContextProvider
{
    /**
     * @readonly
     * @var \Unleash\Client\Bundle\Context\SymfonyUnleashContext
     */
    private $context;
    public function __construct(SymfonyUnleashContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

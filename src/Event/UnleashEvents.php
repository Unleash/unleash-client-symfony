<?php

namespace Unleash\Client\Bundle\Event;

final class UnleashEvents
{
    /**
     * @Event("Unleash\Client\Bundle\Event\ContextValueNotFoundEvent")
     */
    public const CONTEXT_VALUE_NOT_FOUND = 'unleash.client.event.context_not_found';
}

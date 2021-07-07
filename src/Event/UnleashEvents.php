<?php

namespace Rikudou\Unleash\Bundle\Event;

final class UnleashEvents
{
    /**
     * @Event("Rikudou\Unleash\Bundle\Event\ContextValueNotFoundEvent")
     */
    public const CONTEXT_VALUE_NOT_FOUND = 'rikudou.unleash.event.context_not_found';
}

<?php

namespace Unleash\Client\Bundle\Event;

final class UnleashEvents
{
    /**
     * @Event("Unleash\Client\Bundle\Event\ContextValueNotFoundEvent")
     */
    public const CONTEXT_VALUE_NOT_FOUND = 'unleash.client.event.context_not_found';

    /**
     * @Event("Unleash\Client\Bundle\Event\BeforeExceptionThrownForAttributeEvent")
     */
    public const BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE = 'unleash.client.event.before_exception_attribute';
}

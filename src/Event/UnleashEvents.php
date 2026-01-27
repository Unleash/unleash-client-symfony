<?php

namespace Unleash\Client\Bundle\Event;

final readonly class UnleashEvents
{
    /**
     * @Event("Unleash\Client\Bundle\Event\ContextValueNotFoundEvent")
     * @var string
     */
    public const CONTEXT_VALUE_NOT_FOUND = 'unleash.client.event.context_not_found';

    /**
     * @Event("Unleash\Client\Bundle\Event\BeforeExceptionThrownForAttributeEvent")
     * @var string
     */
    public const BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE = 'unleash.client.event.before_exception_attribute';
}

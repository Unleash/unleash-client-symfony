<?php

namespace Unleash\Client\Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ContextValueNotFoundEvent extends Event
{
    /**
     * @readonly
     * @var string
     */
    private $contextName;
    /**
     * @var string|null
     */
    private $value;

    public function __construct(string $contextName)
    {
        $this->contextName = $contextName;
    }

    public function getContextName(): string
    {
        return $this->contextName;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }
}

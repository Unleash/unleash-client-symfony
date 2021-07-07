<?php

namespace Rikudou\Unleash\Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ContextValueNotFoundEvent extends Event
{
    public function __construct(private string $contextName, private ?string $value = null)
    {
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

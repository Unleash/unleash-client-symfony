<?php

namespace Unleash\Client\Bundle\DependencyInjection\Dsn;

use Stringable;

final class StaticStringableParameter implements Stringable
{
    public function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

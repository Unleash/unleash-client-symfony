<?php

namespace Unleash\Client\Bundle\Attribute;

use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\HttpFoundation\Response;

interface ControllerAttribute
{
    public function getFeatureName(): string;

    public function getErrorCode(): int;
}

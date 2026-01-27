<?php

namespace Unleash\Client\Bundle\Attribute;

use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\HttpFoundation\Response;

/**
 * @todo Make internal in next major
 */
interface ControllerAttribute
{
    public function getFeatureName(): string;

    public function getErrorCode(): int;
}

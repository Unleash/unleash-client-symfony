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

    #[ExpectedValues([
        Response::HTTP_NOT_FOUND,
        Response::HTTP_FORBIDDEN,
        Response::HTTP_BAD_REQUEST,
        Response::HTTP_UNAUTHORIZED,
        Response::HTTP_SERVICE_UNAVAILABLE,
    ])]
    public function getErrorCode(): int;
}

<?php

namespace Unleash\Client\Bundle\Attribute;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\HttpFoundation\Response;

/**
 * @todo Make readonly in next major
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class IsEnabled implements ControllerAttribute
{
    public string $featureName;
    public int $errorCode = Response::HTTP_NOT_FOUND;
    public function __construct(
        string $featureName,
        #[\JetBrains\PhpStorm\ExpectedValues([\Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND, \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN, \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST, \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED, \Symfony\Component\HttpFoundation\Response::HTTP_SERVICE_UNAVAILABLE])]
        int $errorCode = Response::HTTP_NOT_FOUND
    )
    {
        $this->featureName = $featureName;
        $this->errorCode = $errorCode;
    }
    public function getFeatureName(): string
    {
        return $this->featureName;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}

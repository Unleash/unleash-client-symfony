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
    public function __construct(
        public string $featureName,
        #[ExpectedValues([
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED,
            Response::HTTP_SERVICE_UNAVAILABLE,
        ])]
        public int $errorCode = Response::HTTP_NOT_FOUND,
    ) {
    }

    public function getFeatureName(): string
    {
        return $this->featureName;
    }

    #[ExpectedValues([
        Response::HTTP_NOT_FOUND,
        Response::HTTP_FORBIDDEN,
        Response::HTTP_BAD_REQUEST,
        Response::HTTP_UNAUTHORIZED,
        Response::HTTP_SERVICE_UNAVAILABLE,
    ])]
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}

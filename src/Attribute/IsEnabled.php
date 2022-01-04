<?php

namespace Unleash\Client\Bundle\Attribute;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class IsEnabled
{
    public function __construct(
        public string $featureName,
        #[ExpectedValues([
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED,
        ])]
        public int $errorCode = Response::HTTP_NOT_FOUND,
    ) {
    }
}

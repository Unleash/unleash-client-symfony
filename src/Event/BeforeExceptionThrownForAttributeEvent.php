<?php

namespace Unleash\Client\Bundle\Event;

use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BeforeExceptionThrownForAttributeEvent
{
    private ?Throwable $exception = null;

    public function __construct(
        #[ExpectedValues([
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED,
        ])]private int $errorCode,
    ) {
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    public function setException(?Throwable $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    #[ExpectedValues([
        Response::HTTP_NOT_FOUND,
        Response::HTTP_FORBIDDEN,
        Response::HTTP_BAD_REQUEST,
        Response::HTTP_UNAUTHORIZED,
    ])]
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}

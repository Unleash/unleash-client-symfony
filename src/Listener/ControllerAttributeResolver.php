<?php

namespace Unleash\Client\Bundle\Listener;

use JetBrains\PhpStorm\ArrayShape;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Unleash\Client\Bundle\Attribute\ControllerAttribute;
use Unleash\Client\Bundle\Attribute\IsEnabled;
use Unleash\Client\Bundle\Attribute\IsNotEnabled;
use Unleash\Client\Bundle\Event\BeforeExceptionThrownForAttributeEvent;
use Unleash\Client\Bundle\Event\UnleashEvents;
use Unleash\Client\Exception\InvalidValueException;
use Unleash\Client\Unleash;

final class ControllerAttributeResolver implements EventSubscriberInterface
{
    public function __construct(
        private readonly Unleash $unleash,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[ArrayShape([KernelEvents::CONTROLLER => 'string'])]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onControllerResolved',
        ];
    }

    public function onControllerResolved(ControllerEvent $event): void
    {
        if (PHP_VERSION_ID < 80000) {
            return;
        }

        $controller = $event->getController();
        if (
            !is_array($controller)
            && (is_object($controller) || is_string($controller))
            && method_exists($controller, '__invoke')
        ) {
            $controller = [$controller, '__invoke'];
        }

        if (!is_array($controller)) {
            return;
        }

        [$class, $method] = $controller;
        $reflectionClass = new ReflectionClass($class);
        $reflectionMethod = $reflectionClass->getMethod($method);

        /** @var array<ReflectionAttribute<ControllerAttribute>> $attributes */
        $attributes = [
            ...$reflectionClass->getAttributes(ControllerAttribute::class, ReflectionAttribute::IS_INSTANCEOF),
            ...$reflectionMethod->getAttributes(ControllerAttribute::class, ReflectionAttribute::IS_INSTANCEOF),
        ];

        foreach ($attributes as $attribute) {
            $attribute = $attribute->newInstance();
            assert($attribute instanceof ControllerAttribute);

            $isFeatureEnabled = $this->unleash->isEnabled($attribute->getFeatureName());
            $throwException = match ($attribute::class) {
                IsEnabled::class => !$isFeatureEnabled,
                IsNotEnabled::class => $isFeatureEnabled,
                default => false,
            };
            if ($throwException) {
                throw $this->getException($attribute);
            }
        }
    }

    private function getException(ControllerAttribute $attribute): HttpException|Throwable
    {
        $event = new BeforeExceptionThrownForAttributeEvent($attribute->getErrorCode());
        $this->eventDispatcher->dispatch($event, UnleashEvents::BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE);
        $exception = $event->getException();
        if ($exception !== null) {
            return $exception;
        }

        return match ($attribute->getErrorCode()) {
            Response::HTTP_BAD_REQUEST => new BadRequestHttpException(),
            Response::HTTP_UNAUTHORIZED => new UnauthorizedHttpException('Unauthorized'),
            Response::HTTP_FORBIDDEN => new AccessDeniedHttpException(),
            Response::HTTP_NOT_FOUND => new NotFoundHttpException(),
            Response::HTTP_SERVICE_UNAVAILABLE => new ServiceUnavailableHttpException(),
            default => throw new InvalidValueException("Unsupported status code: {$attribute->getErrorCode()}"),
        };
    }
}

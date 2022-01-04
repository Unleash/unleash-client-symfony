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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Unleash\Client\Bundle\Attribute\IsEnabled;
use Unleash\Client\Bundle\Event\BeforeExceptionThrownForAttributeEvent;
use Unleash\Client\Bundle\Event\UnleashEvents;
use Unleash\Client\Exception\InvalidValueException;
use Unleash\Client\Unleash;

final class ControllerAttributeResolver implements EventSubscriberInterface
{
    public function __construct(
        private Unleash $unleash,
        private EventDispatcherInterface $eventDispatcher,
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

        /** @var array<ReflectionAttribute<IsEnabled>> $attributes */
        $attributes = [
            ...$reflectionClass->getAttributes(IsEnabled::class),
            ...$reflectionMethod->getAttributes(IsEnabled::class),
        ];

        foreach ($attributes as $attribute) {
            $attribute = $attribute->newInstance();
            assert($attribute instanceof IsEnabled);
            if (!$this->unleash->isEnabled($attribute->featureName)) {
                throw $this->getException($attribute);
            }
        }
    }

    private function getException(IsEnabled $attribute): HttpException|Throwable
    {
        $event = new BeforeExceptionThrownForAttributeEvent($attribute->errorCode);
        $this->eventDispatcher->dispatch($event, UnleashEvents::BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE);
        $exception = $event->getException();
        if ($exception !== null) {
            return $exception;
        }

        return match ($attribute->errorCode) {
            Response::HTTP_BAD_REQUEST => new BadRequestHttpException(),
            Response::HTTP_UNAUTHORIZED => new UnauthorizedHttpException('Unauthorized'),
            Response::HTTP_FORBIDDEN => new AccessDeniedHttpException(),
            Response::HTTP_NOT_FOUND => new NotFoundHttpException(),
            default => throw new InvalidValueException("Unsupported status code: {$attribute->errorCode}"),
        };
    }
}

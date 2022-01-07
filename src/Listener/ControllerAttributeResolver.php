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
    /**
     * @var \Unleash\Client\Unleash
     */
    private $unleash;
    /**
     * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
     */
    private $eventDispatcher;
    public function __construct(Unleash $unleash, EventDispatcherInterface $eventDispatcher)
    {
        $this->unleash = $unleash;
        $this->eventDispatcher = $eventDispatcher;
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
        $item0Unpacked = $reflectionClass->getAttributes(IsEnabled::class);
        $item1Unpacked = $reflectionMethod->getAttributes(IsEnabled::class);

        /** @var array<ReflectionAttribute<IsEnabled>> $attributes */
        $attributes = array_merge($item0Unpacked, $item1Unpacked);

        foreach ($attributes as $attribute) {
            $attribute = $attribute->newInstance();
            assert($attribute instanceof IsEnabled);
            if (!$this->unleash->isEnabled($attribute->featureName)) {
                throw $this->getException($attribute);
            }
        }
    }

    /**
     * @return \Symfony\Component\HttpKernel\Exception\HttpException|\Throwable
     */
    private function getException(IsEnabled $attribute)
    {
        $event = new BeforeExceptionThrownForAttributeEvent($attribute->errorCode);
        $this->eventDispatcher->dispatch($event, UnleashEvents::BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE);
        $exception = $event->getException();
        if ($exception !== null) {
            return $exception;
        }

        switch ($attribute->errorCode) {
            case Response::HTTP_BAD_REQUEST:
                return new BadRequestHttpException();
            case Response::HTTP_UNAUTHORIZED:
                return new UnauthorizedHttpException('Unauthorized');
            case Response::HTTP_FORBIDDEN:
                return new AccessDeniedHttpException();
            case Response::HTTP_NOT_FOUND:
                return new NotFoundHttpException();
            default:
                throw new InvalidValueException("Unsupported status code: {$attribute->errorCode}");
        }
    }
}

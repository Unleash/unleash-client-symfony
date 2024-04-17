<?php

namespace Unleash\Client\Bundle\Context;

use DateTimeImmutable;
use DateTimeInterface;
use Error;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use ReflectionObject;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use TypeError;
use Unleash\Client\Bundle\Event\ContextValueNotFoundEvent;
use Unleash\Client\Bundle\Event\UnleashEvents;
use Unleash\Client\Configuration\Context;
use Unleash\Client\Enum\ContextField;
use Unleash\Client\Enum\Stickiness;

final class SymfonyUnleashContext implements Context
{
    private ?string $currentUserId = null;

    private ?string $ipAddress = null;

    private ?string $sessionId = null;
    /**
     * @readonly
     */
    private ?TokenStorageInterface $userTokenStorage;
    /**
     * @readonly
     */
    private ?string $userIdField;
    /**
     * @var array<string, string>
     */
    private array $customProperties;
    /**
     * @readonly
     */
    private ?RequestStack $requestStack;
    /**
     * @readonly
     */
    private ?ExpressionLanguage $expressionLanguage;
    /**
     * @readonly
     */
    private ?EventDispatcherInterface $eventDispatcher;
    private ?string $environment = null;
    /**
     * @param array<string,string> $customProperties
     */
    public function __construct(?TokenStorageInterface $userTokenStorage, ?string $userIdField, array $customProperties, ?RequestStack $requestStack, ?ExpressionLanguage $expressionLanguage, ?EventDispatcherInterface $eventDispatcher, ?string $environment = null)
    {
        $this->userTokenStorage = $userTokenStorage;
        $this->userIdField = $userIdField;
        $this->customProperties = $customProperties;
        $this->requestStack = $requestStack;
        $this->expressionLanguage = $expressionLanguage;
        $this->eventDispatcher = $eventDispatcher;
        $this->environment = $environment;
    }

    public function getCurrentUserId(): ?string
    {
        if ($this->currentUserId !== null) {
            return $this->currentUserId;
        }
        $user = $this->getCurrentUser();
        if ($user === null) {
            return null;
        }
        if ($this->userIdField !== null) {
            if (property_exists($user, $this->userIdField)) {
                try {
                    return (string) $user->{$this->userIdField};
                } catch (Error $exception) {
                    // ignore
                }
            }
            $reflection = new ReflectionObject($user);
            $idProperty = $reflection->getProperty($this->userIdField);
            $idProperty->setAccessible(true);

            $value = $idProperty->getValue($user);
            if (!is_scalar($value) && !$value instanceof Stringable) {
                throw new TypeError(sprintf(
                    "The value of %s::%s must be convertable to string, '%s' given",
                    get_class($user),
                    $this->userIdField,
                    is_object($value) ? get_class($value) : gettype($value),
                ));
            }

            return (string) $value;
        }

        try {
            return $user->getUserIdentifier();
        } catch (Error $exception) {
            return method_exists($user, 'getUsername') ? $user->getUsername() : null;
        }
    }

    public function getIpAddress(): ?string
    {
        if ($this->ipAddress !== null) {
            return $this->ipAddress;
        }
        if ($this->requestStack === null) {
            return null;
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        return $request->getClientIp();
    }

    public function getSessionId(): ?string
    {
        if ($this->sessionId !== null) {
            return $this->sessionId;
        }
        if ($this->requestStack === null) {
            return null;
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }
        if ($request->hasSession() !== true) {
            return null;
        }
        $session = $request->getSession();

        return $session->getId();
    }

    public function getCustomProperty(string $name): string
    {
        if (!$this->hasCustomProperty($name)) {
            if ($this->eventDispatcher !== null) {
                $event = new ContextValueNotFoundEvent($name);
                $this->eventDispatcher->dispatch($event, UnleashEvents::CONTEXT_VALUE_NOT_FOUND);

                $value = $event->getValue();
                if ($value !== null) {
                    return $value;
                }
            }

            throw new InvalidArgumentException("The context doesn't contain property named '{$name}'");
        }

        $value = $this->customProperties[$name];
        if (
            $this->expressionLanguage !== null
            && strncmp($value, '>', strlen('>')) === 0
        ) {
            $expression = substr($value, 1);
            $value = $this->expressionLanguage->evaluate($expression, [
                'user' => $this->getCurrentUser(),
            ]);
            if (!is_scalar($value) && !$value instanceof Stringable) {
                throw new TypeError(sprintf(
                    "The expression %s must evaluate to a type that is convertable to string, '%s' given",
                    $expression,
                    is_object($value) ? get_class($value) : gettype($value),
                ));
            }
            $value = (string) $value;
        } elseif (strncmp($value, '\>', strlen('\>')) === 0) {
            $value = substr($value, 1);
        }

        return $value;
    }

    public function setCustomProperty(string $name, string $value): self
    {
        $this->customProperties[$name] = $value;

        return $this;
    }

    public function hasCustomProperty(string $name): bool
    {
        return array_key_exists($name, $this->customProperties);
    }

    public function removeCustomProperty(string $name, bool $silent = true): self
    {
        if (!$this->hasCustomProperty($name) && !$silent) {
            throw new InvalidArgumentException("The context doesn't contain property with name '{$name}'");
        }
        unset($this->customProperties[$name]);

        return $this;
    }

    public function setCurrentUserId(?string $currentUserId): self
    {
        $this->currentUserId = $currentUserId;

        return $this;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function hasMatchingFieldValue(string $fieldName, array $values): bool
    {
        $fieldValue = $this->findContextValue($fieldName);
        if ($fieldValue === null) {
            return false;
        }

        return in_array($fieldValue, $values, true);
    }

    public function findContextValue(string $fieldName): ?string
    {
        switch ($fieldName) {
            case ContextField::USER_ID:
            case Stickiness::USER_ID:
                return $this->getCurrentUserId();
            case ContextField::SESSION_ID:
            case Stickiness::SESSION_ID:
                return $this->getSessionId();
            case ContextField::IP_ADDRESS:
                return $this->getIpAddress();
            case ContextField::ENVIRONMENT:
                return $this->getEnvironment();
            case ContextField::CURRENT_TIME:
                return $this->getCurrentTime()->format(DateTimeInterface::ISO8601);
            default:
                return $this->findCustomProperty($fieldName);
        }
    }

    public function findCustomProperty(string $name): ?string
    {
        try {
            return $this->getCustomProperty($name);
        } catch (InvalidArgumentException $exception) {
            return null;
        }
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function getHostname(): ?string
    {
        if ($this->hasCustomProperty('hostname')) {
            return $this->getCustomProperty('hostname');
        }

        return gethostname() ?: null;
    }

    public function setHostname(?string $hostname): self
    {
        if ($hostname === null) {
            $this->removeCustomProperty(ContextField::HOSTNAME);
        } else {
            $this->setCustomProperty(ContextField::HOSTNAME, $hostname);
        }

        return $this;
    }

    public function getCurrentTime(): DateTimeInterface
    {
        if (!$this->hasCustomProperty('currentTime')) {
            return new DateTimeImmutable();
        }

        return new DateTimeImmutable($this->getCustomProperty('currentTime'));
    }

    /**
     * @param \DateTimeInterface|string|null $time
     */
    public function setCurrentTime($time): self
    {
        if ($time === null) {
            $this->removeCustomProperty('currentTime');
        } else {
            $value = is_string($time) ? $time : $time->format(DateTimeInterface::ISO8601);
            $this->setCustomProperty('currentTime', $value);
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getCustomProperties(): array
    {
        $result = [];
        foreach (array_keys($this->customProperties) as $propertyName) {
            $result[$propertyName] = $this->getCustomProperty($propertyName);
        }

        return $result;
    }

    private function getCurrentUser(): ?UserInterface
    {
        if ($this->userTokenStorage === null) {
            return null;
        }
        $token = $this->userTokenStorage->getToken();
        if ($token === null) {
            return null;
        }
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return $user;
    }
}

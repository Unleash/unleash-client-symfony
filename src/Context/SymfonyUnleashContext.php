<?php

namespace Unleash\Client\Bundle\Context;

use Error;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use ReflectionObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Unleash\Client\Bundle\Event\ContextValueNotFoundEvent;
use Unleash\Client\Bundle\Event\UnleashEvents;
use Unleash\Client\Configuration\Context;
use Unleash\Client\Enum\ContextField;
use Unleash\Client\Enum\Stickiness;

final class SymfonyUnleashContext implements Context
{
    /**
     * @var string|null
     */
    private $currentUserId;

    /**
     * @var string|null
     */
    private $ipAddress;

    /**
     * @var string|null
     */
    private $sessionId;
    /**
     * @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface|null
     */
    private $userTokenStorage;
    /**
     * @var string|null
     */
    private $userIdField;
    /**
     * @var array<string, string>
     */
    private $customProperties;
    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack|null
     */
    private $requestStack;
    /**
     * @var \Symfony\Component\ExpressionLanguage\ExpressionLanguage|null
     */
    private $expressionLanguage;
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
     */
    private $eventDispatcher;
    /**
     * @var string|null
     */
    private $environment;
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

            return (string) $idProperty->getValue($user);
        }

        try {
            return $user->getUserIdentifier();
        } catch (Error $exception) {
            return $user->getUsername();
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
            $value = substr($value, 1);
            $value = (string) $this->expressionLanguage->evaluate($value, [
                'user' => $this->getCurrentUser(),
            ]);
        } elseif (strncmp($value, '\>', strlen('\>')) === 0) {
            $value = substr($value, 1);
        }

        return $value;
    }

    public function setCustomProperty(string $name, string $value): \Unleash\Client\Configuration\Context
    {
        $this->customProperties[$name] = $value;

        return $this;
    }

    #[Pure]
    public function hasCustomProperty(string $name): bool
    {
        return array_key_exists($name, $this->customProperties);
    }

    public function removeCustomProperty(string $name, bool $silent = true): \Unleash\Client\Configuration\Context
    {
        if (!$this->hasCustomProperty($name) && !$silent) {
            throw new InvalidArgumentException("The context doesn't contain property with name '{$name}'");
        }
        unset($this->customProperties[$name]);

        return $this;
    }

    public function setCurrentUserId(?string $currentUserId): \Unleash\Client\Configuration\Context
    {
        $this->currentUserId = $currentUserId;

        return $this;
    }

    public function setIpAddress(?string $ipAddress): \Unleash\Client\Configuration\Context
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function setSessionId(?string $sessionId): \Unleash\Client\Configuration\Context
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
            default:
                return $this->hasCustomProperty($fieldName) ? $this->getCustomProperty($fieldName) : null;
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

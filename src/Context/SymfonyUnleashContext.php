<?php

namespace Rikudou\Unleash\Bundle\Context;

use Error;
use JetBrains\PhpStorm\Pure;
use ReflectionObject;
use Rikudou\Unleash\Configuration\Context;
use Rikudou\Unleash\Configuration\UnleashContext;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SymfonyUnleashContext implements Context
{
    /**
     * @param array<string,string> $customProperties
     */
    public function __construct(
        private UnleashContext $context,
        private ?TokenStorageInterface $userTokenStorage,
        private ?string $userIdField,
        array $customProperties,
        private ?RequestStack $requestStack,
        private ?ExpressionLanguage $expressionLanguage,
    ) {
        foreach ($customProperties as $key => $value) {
            $this->context->setCustomProperty($key, $value);
        }
    }

    public function getCurrentUserId(): ?string
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return $this->context->getCurrentUserId();
        }
        if ($this->userIdField !== null) {
            if (property_exists($user, $this->userIdField)) {
                try {
                    return (string) $user->{$this->userIdField};
                } catch (Error) {
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
        } catch (Error) {
            return $user->getUsername();
        }
    }

    public function getIpAddress(): ?string
    {
        if ($this->requestStack === null) {
            return $this->context->getIpAddress();
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->context->getIpAddress();
        }

        return $request->getClientIp();
    }

    public function getSessionId(): ?string
    {
        if ($this->requestStack === null) {
            return $this->context->getSessionId();
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->context->getSessionId();
        }
        $session = $request->getSession();

        return $session->getId();
    }

    public function getCustomProperty(string $name): string
    {
        return $this->context->getCustomProperty($name);
    }

    public function setCustomProperty(string $name, string $value): self
    {
        $this->context->setCustomProperty($name, $value);

        return $this;
    }

    #[Pure]
    public function hasCustomProperty(string $name): bool
    {
        return $this->context->hasCustomProperty($name);
    }

    public function removeCustomProperty(string $name, bool $silent = true): self
    {
        $this->context->removeCustomProperty($name, $silent);

        return $this;
    }

    public function setCurrentUserId(?string $currentUserId): self
    {
        $this->context->setCurrentUserId($currentUserId);

        return $this;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->context->setIpAddress($ipAddress);

        return $this;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->context->setSessionId($sessionId);

        return $this;
    }

    public function hasMatchingFieldValue(string $fieldName, array $values): bool
    {
        return $this->context->hasMatchingFieldValue($fieldName, $values);
    }

    public function findContextValue(string $fieldName): ?string
    {
        $value = $this->context->findContextValue($fieldName);
        if ($value === null) {
            return null;
        }
        if (
            $this->expressionLanguage !== null
            && str_starts_with($value, '>')
        ) {
            $value = substr($value, 1);
            $value = (string) $this->expressionLanguage->evaluate($value, [
                'user' => $this->getCurrentUser(),
            ]);
        } elseif (str_starts_with($value, '\>')) {
            $value = substr($value, 1);
        }

        return $value;
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

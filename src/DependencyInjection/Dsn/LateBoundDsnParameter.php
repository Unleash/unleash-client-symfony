<?php

namespace Unleash\Client\Bundle\DependencyInjection\Dsn;

use Stringable;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Unleash\Client\Bundle\Exception\UnknownEnvPreprocessorException;
use Unleash\Client\Exception\InvalidValueException;

final readonly class LateBoundDsnParameter implements Stringable
{
    /**
     * @param iterable<EnvVarProcessorInterface> $preprocessors
     */
    public function __construct(
        private string $envName,
        private string $parameter,
        private iterable $preprocessors,
    ) {
    }

    public function __toString(): string
    {
        $dsn = $this->getEnvValue($this->envName);
        if ($dsn === null) {
            return '';
        }
        if (!is_string($dsn)) {
            $type = is_object($dsn) ? get_class($dsn) : gettype($dsn);
            throw new InvalidValueException("The environment variables {$this->envName} must resolve to a string, {$type} given instead");
        }

        $query = parse_url($dsn, PHP_URL_QUERY);
        if ($query === null) {
            return '';
        }
        assert(is_string($query));
        $instanceUrl = str_replace("?{$query}", '', $dsn);
        if (str_contains($instanceUrl, '%3F')) {
            $instanceUrl = urldecode($instanceUrl);
        }
        if ($this->parameter === 'url') {
            return $instanceUrl;
        }
        parse_str($query, $queryParts);

        $result = $queryParts[$this->parameter] ?? '';
        assert(is_string($result));

        return $result;
    }

    private function getEnvValue(string $env): mixed
    {
        $parts = array_reverse(explode(':', $env));

        $envName = array_shift($parts);
        $result = getenv($envName) ?: $_ENV[$envName] ?? null;

        $buffer = [];
        while (count($parts) > 0) {
            $current = array_shift($parts);
            $preprocessor = $this->findPreprocessor($current);
            if ($preprocessor === null) {
                $buffer[] = $current;
                continue;
            }
            $envToPass = $envName;
            if (count($buffer)) {
                $envToPass = implode(':', array_reverse($buffer));
                $envToPass .= ":{$envName}";
                $buffer = [];
            }

            $result = $preprocessor->getEnv($current, $envToPass, function (string $name) use ($envName, $result) {
                if ($name === $envName) {
                    return $result;
                }

                return getenv($name) ?: $_ENV[$envName] ?? null;
            });
        }

        if (count($buffer)) {
            throw new UnknownEnvPreprocessorException('Unknown env var processor: ' . implode(':', array_reverse($buffer)));
        }

        return $result;
    }

    private function findPreprocessor(string $prefix): ?EnvVarProcessorInterface
    {
        foreach ($this->preprocessors as $preprocessor) {
            $types = array_keys($preprocessor::getProvidedTypes());
            $currentType = explode(':', $prefix)[0];
            if (!in_array($currentType, $types, true)) {
                continue;
            }

            return $preprocessor;
        }

        return null;
    }
}

<?php

namespace Unleash\Client\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Unleash\Client\Unleash;

final class UnleashEnvVarProcessor implements EnvVarProcessorInterface
{
    public function __construct(private Unleash $client)
    {
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): bool
    {
        $value = $this->client->isEnabled($name);

        // Env vars declared from yaml/xml files have string type
        // 1 : Use unleash value first
        // 2 : Retrieve the value of declared/default env var of unleash value is false or not retrieved
        return $value || filter_var($getEnv($name), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return string[]
     */
    public static function getProvidedTypes(): array
    {
        return [
            'unleash' => 'string',
        ];
    }
}

<?php

namespace Unleash\Client\Bundle\DependencyInjection\Dsn;

use Stringable;

final class LateBoundDsnParameter
{
    /**
     * @readonly
     */
    private string $envName;
    /**
     * @readonly
     */
    private string $parameter;
    public function __construct(string $envName, string $parameter)
    {
        $this->envName = $envName;
        $this->parameter = $parameter;
    }
    public function __toString(): string
    {
        $dsn = getenv($this->envName) ?: $_ENV[$this->envName] ?? null;
        if ($dsn === null) {
            return '';
        }

        $query = parse_url($dsn, PHP_URL_QUERY);
        if ($query === null) {
            return '';
        }
        assert(is_string($query));
        $instanceUrl = str_replace("?{$query}", '', $dsn);
        if (strpos($instanceUrl, '%3F') !== false) {
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
}

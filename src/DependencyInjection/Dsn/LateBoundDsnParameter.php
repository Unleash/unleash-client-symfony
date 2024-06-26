<?php

namespace Unleash\Client\Bundle\DependencyInjection\Dsn;

use Stringable;

final readonly class LateBoundDsnParameter implements Stringable
{
    public function __construct(
        private string $envName,
        private string $parameter,
    ) {
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
}

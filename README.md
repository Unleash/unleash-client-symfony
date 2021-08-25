A Symfony bundle for PHP implementation of the [Unleash protocol](https://www.getunleash.io/)
aka [Feature Flags](https://docs.gitlab.com/ee/operations/feature_flags.html) in GitLab.

View the standalone PHP version at [Packagist](https://packagist.org/packages/rikudou/unleash-sdk)
or [GitHub](https://github.com/RikudouSage/UnleashSDK).

> Unleash allows you to gradually release your app's feature before doing a full release based on multiple strategies 
> like releasing to only specific users or releasing to a percentage of your user base. 
> Read more in the above linked documentations.

Requires php 7.3 or newer.

> For generic description of the methods read the [standalone package](https://github.com/RikudouSage/UnleashSDK)
> documentation, this README will focus on Symfony specific things

## Installation

`composer require rikudou/unleash-sdk-bundle`

> If you use [flex](https://packagist.org/packages/symfony/flex) the bundle should be enabled automatically, otherwise
> add `Rikudou\Unleash\Bundle\RikudouUnleashSdkBundle` to your `config/bundles.php`

## Basic usage

First configure the basic parameters, these three are mandatory:

```yaml
rikudou_unleash_sdk:
  app_url: http://localhost:4242/api
  instance_id: myCoolApp-Server1
  app_name: myCoolApp
```

> Tip: Generate the default config by running
> `php bin/console config:dump rikudou_unleash_sdk > config/packages/rikudou_unleash_sdk.yaml`
> which will create the default config file which you can then tweak

```php
<?php

use Rikudou\Unleash\Unleash;

class MyService
{
    public function __construct(Unleash $unleash)
    {
        if ($unleash->isEnabled('someFeatureName')) {
            // todo
        }
    }
}
```

## Context

The context object supplies additional parameters to Unleash and supports Symfony features out of the box.
This context is also being injected to the `Unleash` service instead of the generic one.

```php
<?php

use Rikudou\Unleash\Configuration\Context;
use Rikudou\Unleash\Enum\ContextField;

class MyService
{
    public function __construct(Context $context)
    {
        $context->getCurrentUserId();
        $context->getSessionId();
        $context->getIpAddress();
        $context->hasCustomProperty('someProperty');
        $context->getCustomProperty('someProperty');
        $context->hasMatchingFieldValue('someProperty', ['someValue1', 'someValue2']);
        $context->findContextValue(ContextField::USER_ID);
    }
}
```

The current user id is assigned automatically if the `Symfony Security` component is installed. You can configure which
field to use for the user id, by default it uses either the 
`Symfony\Component\Security\Core\User\UserInterface::getUserIdentifier()` 
or `Symfony\Component\Security\Core\User\UserInterface::getUsername()`.

```yaml
rikudou_unleash_sdk:
  context:
    user_id_field: id 
```

With this configuration this bundle will use the `id` property to assign user id. The property doesn't have to be public.

The bundle also automatically integrates with Symfony's request stack getting the IP address and session id from it,
which may be particularly useful if you're behind proxy and have it in your trusted proxies list.

### Custom Properties

You can also define your own properties that will be present in the context. If you use the `Symfony Expression Language`
you can also use expressions in them. If the value is an expression it must start with the `>` character. If you want
your value to start with `>` and not be an expression, escape it using `\`. All expressions have access to `user`
variable which is either the user object or null.

```yaml
rikudou_unleash_sdk:
  context:
    custom_properties:
      myCustomProperty: someValue # just a good old string
      myOtherProperty: '> 1+1' # starts with >, it will be turned into expression, meaning the value will be 2
      myEscapedProperty: '\> someValue' # will be turned into '> someValue'
      someUserField: '> user.getCustomField()' # will be the result of the getCustomField() method call
      safeUserField: '> user ? user.getCustomField() : null'
```

If you don't want to embed your logic in config, you can also listen to an event:

```php
<?php

use Unleash\Client\Bundle\Event\UnleashEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Unleash\Client\Bundle\Event\ContextValueNotFoundEvent;

class MyListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UnleashEvents::CONTEXT_VALUE_NOT_FOUND => 'handleNotFoundContextValue',
        ];
    }
    
    public function handleNotFoundContextValue(ContextValueNotFoundEvent $event)
    {
        switch ($event->getContextName()) {
            case 'myProperty':
                $value = '...'; // dynamically create the value
                $event->setValue($value);
                break;
        }
    }
}
```

## Twig

If you use twig you can make use of functions, filters, test and a custom tag. The names are generic, that's why you can
disable any of them in case they would clash with your own functions/filters/tests/tags.

The default is that everything is enabled if twig is installed.

```yaml
rikudou_unleash_sdk:
  twig:
    functions: true
    filters: true
    tests: true
    tags: true
```

### Twig functions

There are two functions: `feature_is_enabled()` and `feature_variant()`.

The first returns a boolean and the second one returns an instance of `Rikudou\Unleash\DTO\Variant`.

```twig
{% if feature_is_enabled('featureName') %}
    {% set variant = feature_variant('featureName') %}
    {% if variant.enabled %}
        {{ variant.name }}
    {% endif %}
{% endif %}
```

### Twig filter

Instead of function you can use a filter with the same name.

```twig
{% if 'featureName'|feature_is_enabled %}
    {% set variant = 'featureName' | feature_variant %}
    {# Do something #}
{% endif %}
```

### Twig test

You can also use a test with the name `enabled`.

```twig
{% if 'featureName' is enabled %}
    {# Do something #}
{% endif %}
```

### Twig tag

You can use a custom `feature` tag. Anything in the body will get processed only if the feature is enabled. You also
have access to implicit `variant` variable.

```twig
{% feature 'featureName' %}
    {{ variant.name }} {# Implicit variant variable that only exists in the scope of feature block #}
{% endfeature %}
```

## Custom strategies

Defining custom strategies is very easy because they get automatically injected, you just need to create a class
implementing `Rikudou\Unleash\Strategy\StrategyHandler` (or extending `Rikudou\Unleash\Strategy\AbstractStrategyHandler`).

```php
<?php

use Rikudou\Unleash\Strategy\AbstractStrategyHandler;
use Rikudou\Unleash\DTO\Strategy;
use Rikudou\Unleash\Configuration\Context;

class MyCustomStrategy extends AbstractStrategyHandler
{
    public function getStrategyName() : string
    {
        return 'my_custom_strategy';
    }

    public function isEnabled(Strategy $strategy, Context $context) : bool
    {
        $someCustomProperty = $this->findParameter('customProperty', $strategy);
        if ($someCustomProperty === false) {
            return false;
        }
        
        // assume it's a list
        $someCustomProperty = array_map('trim', explode(',', $someCustomProperty));
        
        $enabled = $context->hasMatchingFieldValue('customProperty', $someCustomProperty);
        
        // check if the constraints are valid using the abstract class' method
        if (!$enabled || !$this->validateConstraints($strategy, $context)) {
            return false;
        }
        
        return true;
    }
}
```

And that's it, due to implementing the interface (by extending the abstract class) your class is automatically
registered as a strategy handler and the `Unleash` service can handle it.

If you want to make use of one of the default strategies, you can, all of them support autowiring.

## Disabling built-in strategies

If for some reason you want to disable any of the built-in strategies, you can do so in config.

```yaml
rikudou_unleash_sdk:
  disabled_strategies:
    - default
    - remoteAddress
```

## Cache and http

By default the services are set to make use of `symfony/http-client`, `nyholm/psr7` and `symfony/cache`.

You can overwrite the default values in config:

```yaml
rikudou_unleash_sdk:
  http_client_service: my_custom_http_client_service
  request_factory_service: my_custom_request_factory_service
  cache_service: my_custom_cache_service
```

The http client service must implement `Psr\Http\Client\ClientInterface`
or `Symfony\Contracts\HttpClient\HttpClientInterface`.

The request factory service must implement `Psr\Http\Message\RequestFactoryInterface`.

The cache service must implement `Psr\SimpleCache\CacheInterface` or `Psr\Cache\CacheItemPoolInterface` (which by
extension means it can implement the standard `Symfony\Component\Cache\Adapter\AdapterInterface` which extends it).

## Configuration reference

This is the autogenerated config dump (by running `php bin/console config:dump rikudou_unleash_sdk`):

```yaml
# Default configuration for extension with alias: "rikudou_unleash_sdk"
rikudou_unleash_sdk:

  # The application api URL
  app_url:              null

  # The instance ID, for Unleash it can be anything, for GitLab it must be the generated value
  instance_id:          null

  # Application name, for Unleash it can be anything, for GitLab it should be a GitLab environment name
  app_name:             null
  
  context:

    # The field name to use as user id, if set to null getUserIdentifier() or getUsername() method will be called
    user_id_field:        null

    # Any additional context properties
    custom_properties:    []

  # The http client service, must implement the Psr\Http\Client\ClientInterface or Symfony\Contracts\HttpClient\HttpClientInterface interface
  http_client_service:  psr18.http_client

  # The request factory service, must implement the Psr\Http\Message\RequestFactoryInterface interface
  request_factory_service: nyholm.psr7.psr17_factory

  # The cache service, must implement the Psr\SimpleCache\CacheInterface or Psr\Cache\CacheItemPoolInterface interface
  cache_service:        cache.app

  # Disabled default strategies, must be one of: flexibleRollout, gradualRolloutRandom, gradualRolloutSessionId, gradualRolloutUserId, remoteAddress, userWithId, default
  disabled_strategies:  []

  # The interval at which to send metrics to the server in milliseconds
  metrics_send_interval: 30000

  # Whether to allow sending feature usage metrics to your instance of Unleash, set this to false for GitLab
  metrics_enabled:      true

  # Whether to allow automatic client registration on client initialization, set this to false for GitLab
  auto_registration:    true

  # The time in seconds the features will stay valid in cache
  cache_ttl:            30

  # Additional headers to use in http client, for Unleash "Authorization" is required
  custom_headers:       []

  # Enable or disable twig function/filter/tests
  twig:

    # Enables the "feature_is_enabled" and "feature_variant" twig functions
    functions:            true

    # Enables the "feature_is_enabled" filter
    filters:              true

    # Enables the "enabled" test, allowing you to write {% if "featureName" is enabled %}
    tests:                true

    # Enables the "feature" twig tag
    tags:                 true
```

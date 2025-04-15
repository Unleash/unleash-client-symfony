# This branch is auto generated
[![Download](https://img.shields.io/packagist/dt/unleash/symfony-client-bundle.svg)](https://packagist.org/packages/unleash/symfony-client-bundle)

A Symfony bundle for PHP implementation of the [Unleash protocol](https://www.getunleash.io/)
aka [Feature Flags](https://docs.gitlab.com/ee/operations/feature_flags.html) in GitLab.

View the standalone PHP version at [Packagist](https://packagist.org/packages/unleash/client)
or [GitHub](https://github.com/Unleash/unleash-client-php).

> Unleash allows you to gradually release your app's feature before doing a full release based on multiple strategies 
> like releasing to only specific users or releasing to a percentage of your user base. 
> Read more in the above linked documentations.

Requires php 7.3 or newer.

> For generic description of the methods read the [standalone package](https://github.com/Unleash/unleash-client-php)
> documentation, this README will focus on Symfony specific things

## Installation

`composer require unleash/symfony-client-bundle`

> If you use [flex](https://packagist.org/packages/symfony/flex) the bundle should be enabled automatically, otherwise
> add `Unleash\Client\Bundle\UnleashSymfonyClientBundle` to your `config/bundles.php`

## Basic usage

First configure the basic parameters, either using a DSN or as separate parameters:

```yaml
unleash_client:
  dsn: http://localhost:4242/api?instance_id=myCoolApp-Server1&app_name=myCoolApp
```

or

```yaml
unleash_client:
  app_url: http://localhost:4242/api
  instance_id: myCoolApp-Server1
  app_name: myCoolApp
```

> Tip: Generate the default config by running
> `php bin/console config:dump unleash_client > config/packages/unleash_client.yaml`
> which will create the default config file which you can then tweak

```php
<?php

use Unleash\Client\Unleash;

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

## Controller attribute

You can also check for feature flag using `#[IsEnabled]` and `#[IsNotEnabled]` attributes on a controller. You can use
it on the whole controller class as well as on a concrete method.

```php
<?php

use Unleash\Client\Bundle\Attribute\IsEnabled;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsEnabled('my_awesome_feature')]
final class MyController
{
    #[IsEnabled('another_awesome_feature', Response::HTTP_BAD_REQUEST)]
    #[Route('/my-route')]
    public function myRoute(): Response
    {
        // todo
    }
    
    #[Route('/other-route')]
    public function otherRoute(): Response
    {
        // todo
    }
}
```

In the example above the user on `/my-route` needs both `my_awesome_feature` and `another_awesome_feature` enabled
(because of one attribute on the class and another attribute on the method) while the `/other-route` needs only
`my_awesome_feature` enabled (because of class attribute).

```php
<?php

use Unleash\Client\Bundle\Attribute\IsNotEnabled;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsNotEnabled('kill_switch')]
final class MyHeavyController
{
    #[Route('/my-route')]
    public function myRoute(): Response
    {
        // todo
    }
}
```

In the second example, `/my-route` route is only enabled if `kill_switch` is **not** enabled.

You can also notice that one of the attributes specifies a second optional parameter with status code. The supported
status codes are:
- `404` - `NotFoundHttpException`
- `403` - `AccessDeniedHttpException`
- `400` - `BadRequestHttpException`
- `401` - `UnauthorizedHttpException` with message "Unauthorized".
- `503` - `ServiceUnavailableHttpException`

The default status code is `404`. If you use an unsupported status code `InvalidValueException` will be thrown.

### Setting custom exception for attribute

If you want custom exception for situations when user is denied access based on the attribute, you can listen to an event:

```php
<?php

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Unleash\Client\Bundle\Event\UnleashEvents;
use Unleash\Client\Bundle\Event\BeforeExceptionThrownForAttributeEvent;
use Symfony\Component\HttpFoundation\Response;

final class MySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UnleashEvents::BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE => 'handleException',
        ];
    }
    
    public function handleException(BeforeExceptionThrownForAttributeEvent $event): void
    {
        $statusCode = $event->getErrorCode();
        switch ($statusCode) {
            case Response::HTTP_NOT_FOUND:
                $exception = new CustomException('Custom message');
                break;
            default:
                $exception = null;
        }
        
        // the exception can be a Throwable or null, null means that this bundle reverts 
        // to its own default exceptions
        $event->setException($exception);
    }
}

```

## Context

The context object supplies additional parameters to Unleash and supports Symfony features out of the box.
This context is also being injected to the `Unleash` service instead of the generic one.

```php
<?php

use Unleash\Client\Configuration\Context;
use Unleash\Client\Enum\ContextField;

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
unleash_client:
  context:
    user_id_field: id 
```

With this configuration this bundle will use the `id` property to assign user id. The property doesn't have to be public.

The bundle also automatically integrates with Symfony's request stack getting the IP address and session id from it,
which may be particularly useful if you're behind proxy and have it in your trusted proxies list.

The context environment defaults to the value of `kernel.environment` parameter.

### Custom Properties

You can also define your own properties that will be present in the context. If you use the `Symfony Expression Language`
you can also use expressions in them. If the value is an expression it must start with the `>` character. If you want
your value to start with `>` and not be an expression, escape it using `\`. All expressions have access to `user`
variable which is either the user object or null.

```yaml
unleash_client:
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

## Events

There are two kinds of events, events from the base Unleash php sdk and from this Symfony bundle.

### Base SDK events

- `\Unleash\Client\Event\UnleashEvents::FEATURE_TOGGLE_NOT_FOUND`
- `\Unleash\Client\Event\UnleashEvents::FEATURE_TOGGLE_DISABLED`
- `\Unleash\Client\Event\UnleashEvents::FEATURE_TOGGLE_MISSING_STRATEGY_HANDLER`

> For more information read the [documentation](https://github.com/Unleash/unleash-client-php/blob/main/doc/events.md) 
> in the base SDK.

### Symfony bundle events

- `\Unleash\Client\Bundle\Event\UnleashEvents::CONTEXT_VALUE_NOT_FOUND`
- `\Unleash\Client\Bundle\Event\UnleashEvents::BEFORE_EXCEPTION_THROWN_FOR_ATTRIBUTE`

> The events are documented in relevant parts of this README.

## Twig

If you use twig you can make use of functions, filters, test and a custom tag. The names are generic, that's why you can
disable any of them in case they would clash with your own functions/filters/tests/tags.

The default is that everything is enabled if twig is installed.

```yaml
unleash_client:
  twig:
    functions: true
    filters: true
    tests: true
    tags: true
```

### Twig functions

There are two functions: `feature_is_enabled()` and `feature_variant()`.

The first returns a boolean and the second one returns an instance of `Unleash\Client\DTO\Variant`.

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

> This tag is experimental and may be removed in future version

You can use a custom `feature` tag. Anything in the body will get processed only if the feature is enabled. You also
have access to implicit `variant` variable.

```twig
{% feature 'featureName' %}
    {{ variant.name }} {# Implicit variant variable that only exists in the scope of feature block #}
{% endfeature %}
```

## Custom strategies

Defining custom strategies is very easy because they get automatically injected, you just need to create a class
implementing `Unleash\Client\Strategy\StrategyHandler` (or extending `Unleash\Client\Strategy\AbstractStrategyHandler`).

```php
<?php

use Unleash\Client\Strategy\AbstractStrategyHandler;
use Unleash\Client\DTO\Strategy;
use Unleash\Client\Configuration\Context;

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
unleash_client:
  disabled_strategies:
    - default
    - remoteAddress
```

## Cache and http

By default the services are set to make use of `symfony/http-client`, `nyholm/psr7` and `symfony/cache`.

You can overwrite the default values in config:

```yaml
unleash_client:
  http_client_service: my_custom_http_client_service
  request_factory_service: my_custom_request_factory_service
  cache_service: my_custom_cache_service
```

The http client service must implement `Psr\Http\Client\ClientInterface`
or `Symfony\Contracts\HttpClient\HttpClientInterface`.

The request factory service must implement `Psr\Http\Message\RequestFactoryInterface`.

The cache service must implement `Psr\SimpleCache\CacheInterface` or `Psr\Cache\CacheItemPoolInterface` (which by
extension means it can implement the standard `Symfony\Component\Cache\Adapter\AdapterInterface` which extends it).

## Bootstrapping

You can set a default response from the SDK in cases when for some reason contacting Unleash server fails.

You can bootstrap using a file or a service implementing `\Unleash\Client\Bootstrap\BootstrapProvider`.

### Service

```php
<?php

use Unleash\Client\Bootstrap\BootstrapProvider;

final class MyBootstrap implements BootstrapProvider
{
    public function getBootstrap() : array|JsonSerializable|Traversable|null{
        // TODO: Implement getBootstrap() method.
    }
}
```
```yaml
unleash_client:
  bootstrap: '@MyBootstrap'
```

> Tip: If you create only one service that implements `BootstrapProvider` it will be injected automatically.
> If you create more than one you need to manually choose a bootstrap as in example above.

### File

Let's say you create a file called `bootstrap.json` in your config directory, this is how you can inject it as Unleash
bootstrap:

```yaml
unleash_client:
  bootstrap: 'file://%kernel.project_dir%/config/bootstrap.json'
```

> Note: All files must start with the `file://` prefix.

### Disabling communication with Unleash server

It may be useful to disable communication with the Unleash server for local development and using a bootstrap instead.

Note that when you disable communication with Unleash and don't provide a bootstrap, an exception will be thrown.

> Tip: Set the cache interval to 0 to always have a fresh bootstrap content.

> The usually required parameters (app name, instance id, app url) are not required when communication is disabled.

```yaml
unleash_client:
  bootstrap: '@MyBootstrap'
  cache_ttl: 0
  fetching_enabled: false
```

## Test command

If you need to quickly test what will your flags evaluate to, you can use the built-in command `unleash:test-flag`.

The command is documented and here's the output of `./bin/console unleash:test-flag --help`:

```
Description:
  Check the status of an Unleash feature

Usage:
  unleash:test-flag [options] [--] <flag>

Arguments:
  flag                                 The name of the feature flag to check the result for

Options:
  -f, --force                          When this flag is present, fresh results without cache will be forced
      --user-id=USER-ID                [Context] Provide the current user's ID
      --ip-address=IP-ADDRESS          [Context] Provide the current IP address
      --session-id=SESSION-ID          [Context] Provide the current session ID
      --hostname=HOSTNAME              [Context] Provide the current hostname
      --environment=ENVIRONMENT        [Context] Provide the current environment
      --current-time=CURRENT-TIME      [Context] Provide the current date and time
      --custom-context=CUSTOM-CONTEXT  [Context] Custom context values in the format [contextName]=[contextValue], for example: myCustomContextField=someValue (multiple values allowed)
      --expected=EXPECTED              For use in testing, if this option is present, the exit code will be either 0 or 1 depending on whether the expectation matches the result
  -h, --help                           Display help for the given command. When no command is given display help for the list command
  -q, --quiet                          Do not output any message
  -V, --version                        Display this application version
      --ansi|--no-ansi                 Force (or disable --no-ansi) ANSI output
  -n, --no-interaction                 Do not ask any interactive question
  -e, --env=ENV                        The Environment name. [default: "dev"]
      --no-debug                       Switch off debug mode.
      --profile                        Enables profiling (requires debug).
  -v|vv|vvv, --verbose                 Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

## Configuration reference

This is the autogenerated config dump (by running `php bin/console config:dump unleash_client`):

```yaml
# Default configuration for extension with alias: "unleash_client"
# Default configuration for extension with alias: "unleash_client"
unleash_client:

  # You can provide the connection details as a DSN instead of app_url, instance_id and app_name. DSN takes precedence over individual parameters.
  dsn:                  null # Example: 'https://localhost:4242/api?instance_id=myCoolApp-Server1&app_name=myCoolApp'

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

  # Whether to enable communication with Unleash server or not. If you set it to false you must also provide a bootstrap.
  fetching_enabled:     true

  # The http client service, must implement the Psr\Http\Client\ClientInterface or Symfony\Contracts\HttpClient\HttpClientInterface interface
  http_client_service:  psr18.http_client

  # The request factory service, must implement the Psr\Http\Message\RequestFactoryInterface interface. Providing null means autodetect between supported default services.
  request_factory_service: null

  # The cache service, must implement the Psr\SimpleCache\CacheInterface or Psr\Cache\CacheItemPoolInterface interface
  cache_service:        cache.app

  # Default bootstrap in case contacting Unleash servers fails. Can be a path to file (prefixed with file://) or a service implementing Unleash\Client\Bootstrap\BootstrapProvider (prefixed with @)
  bootstrap:            null

  # Disabled default strategies, must be one of: default, flexibleRollout, gradualRolloutRandom, gradualRolloutSessionId, gradualRolloutUserId, remoteAddress, userWithId, applicationHostname
  disabled_strategies:  []

  # The interval at which to send metrics to the server in milliseconds
  metrics_send_interval: 30000

  # Whether to allow sending feature usage metrics to your instance of Unleash, set this to false for GitLab
  metrics_enabled:      true

  # Whether to allow automatic client registration on client initialization, set this to false for GitLab
  auto_registration:    true

  # The time in seconds the features will stay valid in cache
  cache_ttl:            30

  # The maximum age (in seconds) old features will be served from cache if http request fails for some reason
  stale_ttl:            1800

  # Additional headers to use in http client, for Unleash "Authorization" is required
  custom_headers:       []

  # Enable or disable twig function/filter/tests
  twig:

    # Enables the "feature_is_enabled" and "feature_variant" twig functions
    functions:            false

    # Enables the "feature_is_enabled" and "feature_variant" filters
    filters:              false

    # Enables the "enabled" test, allowing you to write {% if "featureName" is enabled %}
    tests:                false

    # Enables the "feature" twig tag
    tags:                 false
```

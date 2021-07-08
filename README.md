A Symfony bundle for PHP implementation of the [Unleash protocol](https://www.getunleash.io/)
aka [Feature Flags](https://docs.gitlab.com/ee/operations/feature_flags.html) in GitLab.

View the standalone PHP version at [Packagist](https://packagist.org/packages/rikudou/unleash-sdk)
or [GitHub](https://github.com/RikudouSage/UnleashSDK).

Unleash allows you to gradually release your app's feature before doing a full release based on multiple strategies 
like releasing to only specific users or releasing to a percentage of your user base. 
Read more in the above linked documentations.

Requires php 7.3 or newer.

> For generic description of the methods read the [standalone package](https://github.com/RikudouSage/UnleashSDK)
> documentation, this README will focus on Symfony specific things

## Installation

`composer require rikudou/unleash-sdk-bundle`

> If you use [flex](https://packagist.org/packages/symfony/flex) the bundle should be enabled automatically, otherwise
> add `Rikudou\Unleash\Bundle\RikudouUnleashSdkBundle` to your `config/bundles.php`

## Basic usage

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

use Rikudou\Unleash\Bundle\Event\UnleashEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Rikudou\Unleash\Bundle\Event\ContextValueNotFoundEvent;

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

If you use twig you can make use of functions, filter, test and a custom tag. The names are generic that's why you can
disable any of them in case they would clash with your own function/filters/tests/tags.

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

Instead of function you can use a filter with the name `feature_is_enabled`.

```twig
{% if 'featureName'|feature_is_enabled %}
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

You can use a custom `feature` tag. Anything in the body will get printed only if the feature is enabled. You also
have access to implicit `variant` variable.

```twig
{% feature 'featureName' %}
    {{ variant.name }} {# Implicit variant variable that only exists in the scope of feature block #}
{% endfeature %}
```

UPGRADE FROM 5.0 to 5.1
=======================

Config
------

 * The signature of method `NodeDefinition::setDeprecated()` has been updated to `NodeDefinition::setDeprecated(string $package, string $version, string $message)`.
 * The signature of method `BaseNode::setDeprecated()` has been updated to `BaseNode::setDeprecated(string $package, string $version, string $message)`.
 * Passing a null message to `BaseNode::setDeprecated()` to un-deprecate a node is deprecated
 * Deprecated `BaseNode::getDeprecationMessage()`, use `BaseNode::getDeprecation()` instead

Console
-------

 * `Command::setHidden()` is final since Symfony 5.1

DependencyInjection
-------------------

 * The signature of method `Definition::setDeprecated()` has been updated to `Definition::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `Alias::setDeprecated()` has been updated to `Alias::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `DeprecateTrait::deprecate()` has been updated to `DeprecateTrait::deprecation(string $package, string $version, string $message)`.
 * Deprecated the `Psr\Container\ContainerInterface` and `Symfony\Component\DependencyInjection\ContainerInterface` aliases of the `service_container` service,
   configure them explicitly instead.
 * Deprecated `Definition::getDeprecationMessage()`, use `Definition::getDeprecation()` instead.
 * Deprecated `Alias::getDeprecationMessage()`, use `Alias::getDeprecation()` instead.
 * The `inline()` function from the PHP-DSL has been deprecated, use `inline_service()` instead
 * The `ref()` function from the PHP-DSL has been deprecated, use `service()` instead

Dotenv
------

 * Deprecated passing `$usePutenv` argument to Dotenv's constructor, use `Dotenv::usePutenv()` instead.

EventDispatcher
---------------

 * Deprecated `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

Form
----

 * Not configuring the `rounding_mode` option of the `PercentType` is deprecated. It will default to `\NumberFormatter::ROUND_HALFUP` in Symfony 6.
 * Not passing a rounding mode to the constructor of `PercentToLocalizedStringTransformer` is deprecated. It will default to `\NumberFormatter::ROUND_HALFUP` in Symfony 6.
 * Implementing the `FormConfigInterface` without implementing the `getIsEmptyCallback()` method
   is deprecated. The method will be added to the interface in 6.0.
 * Implementing the `FormConfigBuilderInterface` without implementing the `setIsEmptyCallback()` method
   is deprecated. The method will be added to the interface in 6.0.
 * Added argument `callable|null $filter` to `ChoiceListFactoryInterface::createListFromChoices()` and `createListFromLoader()` - not defining them is deprecated.
 * Using `Symfony\Component\Form\Extension\Validator\Util\ServerParams` class is deprecated, use its parent `Symfony\Component\Form\Util\ServerParams` instead.
 * The `NumberToLocalizedStringTransformer::ROUND_*` constants have been deprecated, use `\NumberFormatter::ROUND_*` instead.

FrameworkBundle
---------------

 * Deprecated passing a `RouteCollectionBuilder` to `MicroKernelTrait::configureRoutes()`, type-hint `RoutingConfigurator` instead
 * Deprecated *not* setting the "framework.router.utf8" configuration option as it will default to `true` in Symfony 6.0
 * Deprecated `session.attribute_bag` service and `session.flash_bag` service.

HttpFoundation
--------------

 * Deprecate `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)
 * Made the Mime component an optional dependency

HttpKernel
----------

 * Made `WarmableInterface::warmUp()` return a list of classes or files to preload on PHP 7.4+
   not returning an array is deprecated
 * Deprecated support for `service:action` syntax to reference controllers. Use `serviceOrFqcn::method` instead.

Inflector
---------

 * The component has been deprecated, use `EnglishInflector` from the String component instead.

Mailer
------

 * Deprecated passing Mailgun headers without their "h:" prefix.
 * Deprecated the `SesApiTransport` class. It has been replaced by SesApiAsyncAwsTransport Run `composer require async-aws/ses` to use the new classes.
 * Deprecated the `SesHttpTransport` class. It has been replaced by SesHttpAsyncAwsTransport Run `composer require async-aws/ses` to use the new classes.

Messenger
---------

 * Deprecated AmqpExt transport. It has moved to a separate package. Run `composer require symfony/amqp-messenger` to use the new classes.
 * Deprecated Doctrine transport. It has moved to a separate package. Run `composer require symfony/doctrine-messenger` to use the new classes.
 * Deprecated RedisExt transport. It has moved to a separate package. Run `composer require symfony/redis-messenger` to use the new classes.
 * Deprecated use of invalid options in Redis and AMQP connections.
 * Deprecated *not* declaring a `\Throwable` argument in `RetryStrategyInterface::isRetryable()`
 * Deprecated *not* declaring a `\Throwable` argument in `RetryStrategyInterface::getWaitingTime()`

Notifier
--------

 * [BC BREAK] The `ChatMessage::fromNotification()` method's `$recipient` and `$transport`
   arguments were removed.
 * [BC BREAK] The `EmailMessage::fromNotification()` and `SmsMessage::fromNotification()`
   methods' `$transport` argument was removed.

OptionsResolver
---------------

 * The signature of method `OptionsResolver::setDeprecated()` has been updated to `OptionsResolver::setDeprecated(string $option, string $package, string $version, $message)`.
 * Deprecated `OptionsResolverIntrospector::getDeprecationMessage()`, use `OptionsResolverIntrospector::getDeprecation()` instead.

PhpUnitBridge
-------------

 * Deprecated the `@expectedDeprecation` annotation, use the `ExpectDeprecationTrait::expectDeprecation()` method instead.

Routing
-------

 * Deprecated `RouteCollectionBuilder` in favor of `RoutingConfigurator`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Deprecated the `RouteCompiler::REGEX_DELIMITER` constant

SecurityBundle
--------------

 * Deprecated `anonymous: lazy` in favor of `lazy: true`

   *Before*
   ```yaml
   security:
       firewalls:
           main:
               anonymous: lazy
   ```

   *After*
   ```yaml
   security:
       firewalls:
           main:
               anonymous: true
               lazy: true
   ```
 * Marked the `AnonymousFactory`, `FormLoginFactory`, `FormLoginLdapFactory`, `GuardAuthenticationFactory`,
   `HttpBasicFactory`, `HttpBasicLdapFactory`, `JsonLoginFactory`, `JsonLoginLdapFactory`, `RememberMeFactory`, `RemoteUserFactory`
   and `X509Factory` as `@internal`. Instead of extending these classes, create your own implementation based on
   `SecurityFactoryInterface`.

Security
--------

 * Deprecated `ROLE_PREVIOUS_ADMIN` role in favor of `IS_IMPERSONATOR` attribute.

   *before*
   ```twig
   {% if is_granted('ROLE_PREVIOUS_ADMIN') %}
       <a href="">Exit impersonation</a>
   {% endif %}
   ```

   *after*
   ```twig
   {% if is_granted('IS_IMPERSONATOR') %}
       <a href="">Exit impersonation</a>
   {% endif %}
   ```

 * Deprecated `LogoutSuccessHandlerInterface` and `LogoutHandlerInterface`, register a listener on the `LogoutEvent` event instead.
 * Deprecated `DefaultLogoutSuccessHandler` in favor of `DefaultLogoutListener`.
 * Deprecated `RememberMeServicesInterface` implementations without a `logout(Request $request, Response $response, TokenInterface $token)` method.

Yaml
----

 * Added support for parsing numbers prefixed with `0o` as octal numbers.
 * Deprecated support for parsing numbers starting with `0` as octal numbers. They will be parsed as strings as of Symfony 6.0. Prefix numbers with `0o`
   so that they are parsed as octal numbers.

   Before:

   ```yaml
   Yaml::parse('072');
   ```

   After:

   ```yaml
   Yaml::parse('0o72');
   ```

 * Deprecated using the `!php/object` and `!php/const` tags without a value.

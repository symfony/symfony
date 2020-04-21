UPGRADE FROM 5.x to 6.0
=======================

Config
------

 * The signature of method `NodeDefinition::setDeprecated()` has been updated to `NodeDefinition::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `BaseNode::setDeprecated()` has been updated to `BaseNode::setDeprecation(string $package, string $version, string $message)`.
 * Passing a null message to `BaseNode::setDeprecated()` to un-deprecate a node is not supported anymore.
 * Removed `BaseNode::getDeprecationMessage()`, use `BaseNode::getDeprecation()` instead.

Console
-------

 * `Command::setHidden()` has a default value (`true`) for `$hidden` parameter

DependencyInjection
-------------------

 * The signature of method `Definition::setDeprecated()` has been updated to `Definition::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `Alias::setDeprecated()` has been updated to `Alias::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `DeprecateTrait::deprecate()` has been updated to `DeprecateTrait::deprecation(string $package, string $version, string $message)`.
 * Removed the `Psr\Container\ContainerInterface` and `Symfony\Component\DependencyInjection\ContainerInterface` aliases of the `service_container` service,
   configure them explicitly instead.
 * Removed `Definition::getDeprecationMessage()`, use `Definition::getDeprecation()` instead.
 * Removed `Alias::getDeprecationMessage()`, use `Alias::getDeprecation()` instead.
 * The `inline()` function from the PHP-DSL has been removed, use `service()` instead

Dotenv
------

 * Removed argument `$usePutenv` from Dotenv's constructor, use `Dotenv::usePutenv()` instead.

EventDispatcher
---------------

 * Removed `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

Form
----

 * Added the `getIsEmptyCallback()` method to the `FormConfigInterface`.
 * Added the `setIsEmptyCallback()` method to the `FormConfigBuilderInterface`.
 * Added argument `callable|null $filter` to `ChoiceListFactoryInterface::createListFromChoices()` and `createListFromLoader()`.
 * The `Symfony\Component\Form\Extension\Validator\Util\ServerParams` class has been removed, use its parent `Symfony\Component\Form\Util\ServerParams` instead.

FrameworkBundle
---------------

 * `MicroKernelTrait::configureRoutes()` is now always called with a `RoutingConfigurator`
 * The "framework.router.utf8" configuration option defaults to `true`
 * Removed `session.attribute_bag` service and `session.flash_bag` service.

HttpFoundation
--------------

 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

HttpKernel
----------

 * Made `WarmableInterface::warmUp()` return a list of classes or files to preload on PHP 7.4+
 * Removed support for `service:action` syntax to reference controllers. Use `serviceOrFqcn::method` instead.

Messenger
---------

 * Removed AmqpExt transport. Run `composer require symfony/amqp-messenger` to keep the transport in your application.
 * Removed Doctrine transport. Run `composer require symfony/doctrine-messenger` to keep the transport in your application.
 * Removed RedisExt transport. Run `composer require symfony/redis-messenger` to keep the transport in your application.
 * Use of invalid options in Redis and AMQP connections now throws an error.
 * The signature of method `RetryStrategyInterface::isRetryable()` has been updated to `RetryStrategyInterface::isRetryable(Envelope $message, \Throwable $throwable = null)`.
 * The signature of method `RetryStrategyInterface::getWaitingTime()` has been updated to `RetryStrategyInterface::getWaitingTime(Envelope $message, \Throwable $throwable = null)`.

OptionsResolver
---------------

 * The signature of method `OptionsResolver::setDeprecated()` has been updated to `OptionsResolver::setDeprecated(string $option, string $package, string $version, $message)`.
 * Removed `OptionsResolverIntrospector::getDeprecationMessage()`, use `OptionsResolverIntrospector::getDeprecation()` instead.

PhpUnitBridge
-------------

 * Removed support for `@expectedDeprecation` annotations, use the `ExpectDeprecationTrait::expectDeprecation()` method instead.

Routing
-------

 * Removed `RouteCollectionBuilder`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Removed the `RouteCompiler::REGEX_DELIMITER` constant

Security
--------

 * Removed `ROLE_PREVIOUS_ADMIN` role in favor of `IS_IMPERSONATOR` attribute
 * Removed `LogoutSuccessHandlerInterface` and `LogoutHandlerInterface`, register a listener on the `LogoutEvent` event instead.
 * Removed `DefaultLogoutSuccessHandler` in favor of `DefaultLogoutListener`.

Yaml
----

 * Removed support for using the `!php/object` and `!php/const` tags without a value.

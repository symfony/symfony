UPGRADE FROM 5.0 to 5.1
=======================

Console
-------

 * `Command::setHidden()` is final since Symfony 5.1

Dotenv
------

 * Deprecated passing `$usePutenv` argument to Dotenv's constructor, use `Dotenv::usePutenv()` instead.

EventDispatcher
---------------

 * Deprecated `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

Form
----

 * Implementing the `FormConfigInterface` without implementing the `getIsEmptyCallback()` method
   is deprecated. The method will be added to the interface in 6.0.
 * Implementing the `FormConfigBuilderInterface` without implementing the `setIsEmptyCallback()` method
   is deprecated. The method will be added to the interface in 6.0.

FrameworkBundle
---------------

 * Deprecated passing a `RouteCollectionBuiler` to `MicroKernelTrait::configureRoutes()`, type-hint `RoutingConfigurator` instead
 * Deprecated *not* setting the "framework.router.utf8" configuration option as it will default to `true` in Symfony 6.0

HttpFoundation
--------------

 * Deprecate `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)
 * Made the Mime component an optional dependency

Messenger
---------

 * Deprecated AmqpExt transport. It has moved to a separate package. Run `composer require symfony/amqp-messenger` to use the new classes.
 * Deprecated Doctrine transport. It has moved to a separate package. Run `composer require symfony/doctrine-messenger` to use the new classes.
 * Deprecated RedisExt transport. It has moved to a separate package. Run `composer require symfony/redis-messenger` to use the new classes.
 * Deprecated use of invalid options in Redis and AMQP connections.

Notifier
--------

 * [BC BREAK] The `ChatMessage::fromNotification()` method's `$recipient` and `$transport`
   arguments were removed.
 * [BC BREAK] The `EmailMessage::fromNotification()` and `SmsMessage::fromNotification()`
   methods' `$transport` argument was removed.

Routing
-------

 * Deprecated `RouteCollectionBuilder` in favor of `RoutingConfigurator`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Deprecated the `RouteCompiler::REGEX_DELIMITER` constant

Yaml
----

 * Deprecated using the `!php/object` and `!php/const` tags without a value.

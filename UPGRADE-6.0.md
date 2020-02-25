UPGRADE FROM 5.x to 6.0
=======================

Console
-------

 * `Command::setHidden()` has a default value (`true`) for `$hidden` parameter

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

FrameworkBundle
---------------

 * `MicroKernelTrait::configureRoutes()` is now always called with a `RoutingConfigurator`
 * The "framework.router.utf8" configuration option defaults to `true`

HttpFoundation
--------------

 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Messenger
---------

 * Removed AmqpExt transport. Run `composer require symfony/amqp-messenger` to keep the transport in your application.
 * Removed Doctrine transport. Run `composer require symfony/doctrine-messenger` to keep the transport in your application.
 * Removed RedisExt transport. Run `composer require symfony/redis-messenger` to keep the transport in your application.
 * Use of invalid options in Redis and AMQP connections now throws an error.

Routing
-------

 * Removed `RouteCollectionBuilder`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Removed the `RouteCompiler::REGEX_DELIMITER` constant

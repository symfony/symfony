UPGRADE FROM 5.0 to 5.1
=======================

EventDispatcher
---------------

 * Deprecated `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

FrameworkBundle
---------------

 * Deprecated passing a `RouteCollectionBuiler` to `MicroKernelTrait::configureRoutes()`, type-hint `RoutingConfigurator` instead
 * Deprecated *not* setting the "framework.router.utf8" configuration option as it will default to `true` in Symfony 6.0

HttpFoundation
--------------

 * Deprecate `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Messenger
---------

 * Deprecated AmqpExt transport. It has moved to a separate package. Run `composer require symfony/amqp-messenger` to use the new classes.
 * Deprecated Doctrine transport. It has moved to a separate package. Run `composer require symfony/doctrine-messenger` to use the new classes.
 * Deprecated RedisExt transport. It has moved to a separate package. Run `composer require symfony/redis-messenger` to use the new classes.

Routing
-------

 * Deprecated `RouteCollectionBuilder` in favor of `RoutingConfigurator`.

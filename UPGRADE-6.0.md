UPGRADE FROM 5.x to 6.0
=======================

EventDispatcher
---------------

 * Removed `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

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

Routing
-------

 * Removed `RouteCollectionBuilder`.

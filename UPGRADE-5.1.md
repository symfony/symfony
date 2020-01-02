UPGRADE FROM 5.0 to 5.1
=======================

EventDispatcher
---------------

 * Deprecated `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

FrameworkBundle
---------------

 * Deprecated passing a `RouteCollectionBuiler` to `MicroKernelTrait::configureRoutes()`, type-hint `RoutingConfigurator` instead

HttpFoundation
--------------

 * Deprecate `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Notifier
--------

 * [BC BREAK] The `ChatMessage::fromNotification()` method's `$recipient` and `$transport`
   arguments were removed.
 * [BC BREAK] The `EmailMessage::fromNotification()` and `SmsMessage::fromNotification()`
   methods' `$transport` argument was removed.

Routing
-------

 * Deprecated `RouteCollectionBuilder` in favor of `RoutingConfigurator`.

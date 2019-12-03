UPGRADE FROM 5.0 to 5.1
=======================

FrameworkBundle
---------------

 * Marked `MicroKernelTrait::configureRoutes()` as `@internal` and `@final`.
 * Deprecated not overriding `MicroKernelTrait::configureRouting()`.

HttpFoundation
--------------

 * Deprecate `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Routing
-------

 * Deprecated `RouteCollectionBuilder` in favor of `RoutingConfigurator`.

UPGRADE FROM 5.x to 6.0
=======================

FrameworkBundle
---------------

 * `MicroKernelTrait::configureRoutes()` is now always called with a `RoutingConfigurator`

HttpFoundation
--------------

 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Routing
-------

 * Removed `RouteCollectionBuilder`.
